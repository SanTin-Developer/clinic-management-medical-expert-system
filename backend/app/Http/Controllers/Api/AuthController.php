<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpResendRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Models\LoginChallenge;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Auth\LoginChallengeService;
use App\Services\Auth\TrustedDeviceService;
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
    ) {}

    // login user and create a Sanctum token (or start an OTP challenge)
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $identifier = $credentials['email'];
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if ($field === 'username') {
            $identifier = strtolower($identifier);
        }

        if (! Auth::attempt([$field => $identifier, 'password' => $credentials['password']])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        if ($this->otpEnabled() && ! $this->trustedDevices->isTrusted($request, $user)) {
            $challenge = $this->challenges->issue($user, $request);

            return response()->json([
                'message' => 'A verification code has been sent to your email address.',
                'otp_required' => true,
                'challenge_token' => $challenge->challenge_token,
                'expires_in' => (int) config('auth.otp.expire_minutes', 10) * 60,
            ], 202);
        }

        return $this->tokenResponse($user);
    }

    // complete an OTP challenge and issue a token, optionally trusting the device
    public function verifyOtp(OtpVerifyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->challenges->verify($validated['challenge_token'], $validated['code']);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $user = $result['challenge']->user;
        $device = null;

        if ($request->boolean('remember_device', true)) {
            $device = $this->trustedDevices->remember($request, $user);
        }

        return $this->tokenResponse($user, $device);
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

        return response()->json(['message' => 'Device revoked.']);
    }

    // revoke every trusted device for the current user
    public function revokeAllDevices(Request $request): JsonResponse
    {
        $this->trustedDevices->forgetAll($request->user());

        return response()->json(['message' => 'All trusted devices have been revoked.']);
    }

    // logout current user (revoke the token and clear the device cookie)
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

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

    private function otpEnabled(): bool
    {
        return (bool) config('auth.otp.enabled', false);
    }

    /**
     * @param  array{device: TrustedDevice, cookie: string, minutes: int}|null  $device
     */
    private function tokenResponse(User $user, ?array $device = null): JsonResponse
    {
        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration ? now()->addMinutes((int) $expiration) : null;

        $token = $user->createToken(self::TOKEN_NAME, ['*'], $expiresAt)->plainTextToken;

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
