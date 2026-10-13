<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Http\Requests\Auth\TwoFactorDisableRequest;
use App\Http\Requests\Auth\TwoFactorEnrollRequest;
use App\Services\Auth\SecurityEventService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly SecurityEventService $events,
    ) {}

    // report the current two-factor state for the authenticated user
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending_enrollment' => ! $user->hasTwoFactorEnabled()
                && is_string($user->two_factor_secret)
                && $user->two_factor_secret !== '',
            'confirmed_at' => $user->two_factor_confirmed_at,
            'recovery_codes_remaining' => $user->twoFactorRecoveryCodes()->whereNull('used_at')->count(),
            'mandatory' => (bool) config('auth.two_factor.mandatory', false),
        ]);
    }

    // begin enrolment: generate a secret and return the provisioning URI
    public function enroll(TwoFactorEnrollRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->assertPassword($request, $user->password);

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'password' => ['Two-factor authentication is already enabled. Disable it first to re-enroll.'],
            ]);
        }

        $enrollment = $this->twoFactor->enroll($user);

        $this->events->record(SecurityEventService::TWO_FACTOR_ENROLLED, $user, 'info', [], $request);

        return response()->json([
            'message' => 'Scan the QR code or enter the secret in your authenticator app, then confirm with a code.',
            'secret' => $enrollment['secret'],
            'otpauth_url' => $enrollment['uri'],
        ]);
    }

    // confirm enrolment and return the one-time recovery codes
    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        $user = $request->user();

        $codes = $this->twoFactor->confirm($user, $request->validated()['code']);

        if ($codes === null) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is incorrect.'],
            ]);
        }

        return response()->json([
            'message' => 'Two-factor authentication has been enabled.',
            'recovery_codes' => $codes,
        ]);
    }

    // disable two-factor authentication
    public function disable(TwoFactorDisableRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->assertPassword($request, $user->password);

        $this->twoFactor->disable($user);

        $this->events->record(SecurityEventService::TWO_FACTOR_DISABLED, $user, 'warning', [], $request);

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
        ]);
    }

    // regenerate recovery codes (invalidates previous codes)
    public function regenerateRecoveryCodes(TwoFactorDisableRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->assertPassword($request, $user->password);

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'password' => ['Enable two-factor authentication first.'],
            ]);
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        $this->events->record(SecurityEventService::RECOVERY_CODES_REGENERATED, $user, 'warning', [], $request);

        return response()->json([
            'message' => 'New recovery codes have been generated. Store them somewhere safe.',
            'recovery_codes' => $codes,
        ]);
    }

    private function assertPassword(Request $request, string $hash): void
    {
        if (! Hash::check((string) $request->input('password'), $hash)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }
    }
}
