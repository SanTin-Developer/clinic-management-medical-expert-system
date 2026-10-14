<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpResendRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Http\Requests\Auth\TwoFactorVerifyRequest;
use App\Models\LoginChallenge;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Auth\LoginChallengeService;
use App\Services\Auth\SecurityEventService;
use App\Services\Auth\TrustedDeviceService;
use App\Services\Auth\TwoFactorChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    private const TOKEN_NAME = 'clinic-api-token';

    public function __construct(
        private readonly LoginChallengeService $challenges,
        private readonly TrustedDeviceService $trustedDevices,
        private readonly TwoFactorChallengeService $twoFactorChallenges,
        private readonly SecurityEventService $events,
    ) {}

    // login user and create a Sanctum token (or start a second-factor challenge)
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $identifier = $credentials['email'];
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if ($field === 'username') {
            $identifier = strtolower($identifier);
        }

        $candidate = User::query()->where($field, $identifier)->first();

        if ($candidate !== null && $candidate->isLocked()) {
            $this->events->record(SecurityEventService::LOGIN_LOCKED, $candidate, 'critical', [], $request);

            return response()->json([
                'message' => 'This account is temporarily locked. Please try again later.',
            ], 429);
        }

        if (! Auth::attempt([$field => $identifier, 'password' => $credentials['password']])) {
            $this->registerFailedAttempt($candidate, $request);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            $this->events->record(SecurityEventService::LOGIN_BLOCKED, $user, 'warning', [
                'status' => $user->account_status,
            ], $request);

            return response()->json([
                'message' => $this->blockedMessage($user),
                'account_status' => $user->account_status,
            ], 403);
        }

        $rememberDevice = $request->boolean('remember_device', true);

        if ($this->otpEnabled() && ! $this->trustedDevices->isTrusted($request, $user)) {
            $challenge = $this->challenges->issue($user, $request);

            return response()->json([
                'message' => 'A verification code has been sent to your email address.',
                'otp_required' => true,
                'challenge_token' => $challenge->challenge_token,
                'expires_in' => (int) config('auth.otp.expire_minutes', 10) * 60,
            ], 202);
        }

        return $this->postPasswordGate($user, $request, $rememberDevice);
    }

    // complete an OTP challenge; may raise a second-factor challenge next
    public function verifyOtp(OtpVerifyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->challenges->verify($validated['challenge_token'], $validated['code']);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $user = $result['challenge']->user;

        return $this->postPasswordGate($user, $request, $request->boolean('remember_device', true));
    }

    // complete a TOTP/recovery-code challenge and issue a token
    public function verifyTwoFactor(TwoFactorVerifyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->twoFactorChallenges->verify($validated['challenge_token'], $validated['code'], $request);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $user = $result['user'];
        $challenge = $result['challenge'];

        if (! $user->isActive()) {
            $this->events->record(SecurityEventService::LOGIN_BLOCKED, $user, 'warning', [], $request);

            return response()->json([
                'message' => $this->blockedMessage($user),
                'account_status' => $user->account_status,
            ], 403);
        }

        $device = null;

        if ($challenge->remember_device) {
            $device = $this->trustedDevices->remember($request, $user);
        }

        return $this->tokenResponse($user, $device, $request, [
            'two_factor' => true,
            'recovery_code' => (bool) $result['used_recovery'],
        ]);
    }

    // resend the OTP for an outstanding challenge (cooldown enforced)
    public function resendOtp(OtpResendRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $challenge = LoginChallenge::query()
            ->where('challenge_token', $validated['challenge_token'])
            ->first();

        if ($challenge === null || $challenge->isConsumed() || $challenge->isExpired()) {
            return response()->json(['message' => 'This verification request is no longer valid.'], 422);
        }

        if (! $this->challenges->canResend($challenge)) {
            return response()->json(['message' => 'Please wait before requesting another code.'], 429);
        }

        $this->challenges->resend($challenge);

        return response()->json([
            'message' => 'A new verification code has been sent.',
            'otp_required' => true,
            'challenge_token' => $challenge->challenge_token,
            'expires_in' => (int) config('auth.otp.expire_minutes', 10) * 60,
        ]);
    }

    // list the devices this user has chosen to trust
    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->trustedDevices->match($request, $user);

        return response()->json([
            'data' => $user->trustedDevices()
                ->latest()
                ->get()
                ->map(fn (TrustedDevice $device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'ip_address' => $device->ip_address,
                    'last_used_at' => $device->last_used_at,
                    'expires_at' => $device->expires_at,
                    'is_current' => $current !== null && $current->id === $device->id,
                ]),
        ]);
    }

    // revoke a single trusted device
    public function revokeDevice(Request $request, TrustedDevice $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);

        $device->delete();

        $this->events->record(SecurityEventService::TRUSTED_DEVICE_REVOKED, $request->user(), 'info', [], $request);

        return response()->json(['message' => 'Device revoked.']);
    }

    // revoke every trusted device for the current user
    public function revokeAllDevices(Request $request): JsonResponse
    {
        $this->trustedDevices->forgetAll($request->user());

        $this->events->record(SecurityEventService::TRUSTED_DEVICES_REVOKED_ALL, $request->user(), 'info', [], $request);

        return response()->json(['message' => 'All trusted devices have been revoked.']);
    }

    // logout current user (revoke the token and clear the device cookie)
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();

        $this->events->record(SecurityEventService::LOGOUT, $user, 'info', [], $request);

        $response = response()->json([
            'message' => 'Logout successful.',
        ]);

        $response->headers->setCookie($this->deviceCookie('', 0));

        return $response;
    }

    // Get the authenticated user's details
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    /**
     * After the password (and any email OTP) succeeds, either issue a token or
     * raise the TOTP challenge when mandatory two-factor is required.
     */
    private function postPasswordGate(User $user, Request $request, bool $rememberDevice): JsonResponse
    {
        if ($this->twoFactorRequiredFor($user)) {
            $challenge = $this->twoFactorChallenges->issue($user, $request, $rememberDevice);

            return response()->json([
                'message' => 'Enter the code from your authenticator app to continue.',
                'two_factor_required' => true,
                'challenge_token' => $challenge->challenge_token,
                'expires_in' => (int) config('auth.two_factor.challenge_expire_minutes', 10) * 60,
            ], 202);
        }

        $device = $rememberDevice ? $this->trustedDevices->remember($request, $user) : null;

        return $this->tokenResponse($user, $device, $request);
    }

    private function twoFactorRequiredFor(User $user): bool
    {
        return (bool) config('auth.two_factor.mandatory', false) && $user->hasTwoFactorEnabled();
    }

    private function otpEnabled(): bool
    {
        return (bool) config('auth.otp.enabled', false);
    }

    private function blockedMessage(User $user): string
    {
        return match (true) {
            $user->isPending() => 'Your account has not been activated yet. Please accept your invitation.',
            $user->isPendingApproval() => 'Your account is awaiting administrator approval. You will be able to sign in once it has been approved.',
            $user->isRejected() => 'Your account registration was not approved. Please contact your administrator.',
            $user->isSuspended() => 'Your account has been suspended. Please contact your administrator.',
            $user->isDisabled() => 'Your account has been disabled. Please contact your administrator.',
            default => 'Your account is not permitted to sign in.',
        };
    }

    private function registerFailedAttempt(?User $user, Request $request): void
    {
        if ($user === null) {
            return;
        }

        $user->increment('failed_login_attempts');

        if ((bool) config('auth.lockout.enabled', false)) {
            $max = (int) config('auth.lockout.max_attempts', 10);

            if ($user->failed_login_attempts >= $max) {
                $user->forceFill([
                    'locked_until' => now()->addMinutes((int) config('auth.lockout.decay_minutes', 15)),
                ])->save();
            }
        }

        $this->events->record(SecurityEventService::LOGIN_FAILED, $user, 'warning', [], $request);
    }

    /**
     * @param  array{device: TrustedDevice, cookie: string, minutes: int}|null  $device
     * @param  array<string, mixed>  $context
     */
    private function tokenResponse(User $user, ?array $device = null, ?Request $request = null, array $context = []): JsonResponse
    {
        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration ? now()->addMinutes((int) $expiration) : null;

        $token = $user->createToken(self::TOKEN_NAME, ['*'], $expiresAt)->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        if ($request !== null) {
            $this->events->record(SecurityEventService::LOGIN_SUCCEEDED, $user, 'info', $context, $request);
        }

        $response = response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);

        if ($device !== null) {
            $response->headers->setCookie($this->deviceCookie($device['cookie'], $device['minutes']));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'account_status' => $user->account_status,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'must_change_password' => (bool) $user->must_change_password,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }

    private function deviceCookie(string $value, int $minutes): Cookie
    {
        $secure = app()->environment('production');

        $expires = $minutes > 0 ? now()->addMinutes($minutes) : now()->subMinutes(1);

        return new Cookie(
            (string) config('auth.otp.cookie_name', 'clinic_trusted_device'),
            $value,
            $expires,
            '/',
            null,
            $secure,
            true,
            false,
            'lax',
        );
    }
}
