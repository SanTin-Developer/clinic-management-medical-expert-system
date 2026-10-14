<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Server-side step-up authorization. Sensitive operations require the caller
 * to re-prove their identity; a short-lived grant is bound to the user and the
 * exact action it was issued for.
 *
 * The password is always required. When `auth.step_up.require_two_factor` is
 * enabled, accounts that have TOTP enrolled must also present a valid
 * authenticator code (or a single-use recovery code) before a grant is issued.
 */
class StepUpService
{
    public function __construct(
        private readonly SecurityEventService $events,
        private readonly TwoFactorService $twoFactor,
    ) {}

    /**
     * Authorize the action using the account password and, for enrolled users
     * when required, a second TOTP factor.
     *
     * @return array{ok: bool, message?: string, token?: string, expires_in?: int, two_factor_required?: bool}
     */
    public function authorize(
        User $user,
        string $action,
        string $password,
        Request $request,
        ?string $twoFactorCode = null,
    ): array {
        if (! Hash::check($password, $user->password)) {
            $this->events->record(SecurityEventService::STEP_UP_DENIED, $user, 'warning', ['action' => $action], $request);

            return ['ok' => false, 'message' => 'The password is incorrect.'];
        }

        if ($this->twoFactorRequired($user)) {
            if ($twoFactorCode === null || $twoFactorCode === '') {
                return [
                    'ok' => false,
                    'two_factor_required' => true,
                    'message' => 'Enter the code from your authenticator app to continue.',
                ];
            }

            $valid = $this->twoFactor->verifyCode($user, $twoFactorCode)
                || $this->twoFactor->verifyRecoveryCode($user, $twoFactorCode);

            if (! $valid) {
                $this->events->record(SecurityEventService::STEP_UP_DENIED, $user, 'warning', ['action' => $action], $request);

                return ['ok' => false, 'message' => 'The verification code is incorrect.'];
            }
        }

        $token = Str::random(64);
        $ttl = (int) config('auth.step_up.ttl_minutes', 10);

        $user->stepUpAuthorizations()->create([
            'token_hash' => hash('sha256', $token),
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->events->record(SecurityEventService::STEP_UP_GRANTED, $user, 'info', ['action' => $action], $request);

        return ['ok' => true, 'token' => $token, 'expires_in' => $ttl * 60];
    }

    /**
     * Whether this user must present a second TOTP factor for step-up.
     */
    private function twoFactorRequired(User $user): bool
    {
        return (bool) config('auth.step_up.require_two_factor', false) && $user->hasTwoFactorEnabled();
    }

    /**
     * Consume a step-up grant, marking it used so it cannot be replayed.
     */
    public function consume(User $user, string $action, ?string $token, Request $request): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $authorization = $user->stepUpAuthorizations()
            ->where('token_hash', hash('sha256', $token))
            ->where('action', $action)
            ->first();

        if ($authorization === null || $authorization->isConsumed() || $authorization->isExpired()) {
            return false;
        }

        $authorization->forceFill(['consumed_at' => now()])->save();

        $this->events->record(SecurityEventService::STEP_UP_USED, $user, 'info', ['action' => $action], $request);

        return true;
    }
}
