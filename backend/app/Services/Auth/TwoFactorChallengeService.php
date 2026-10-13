<?php

namespace App\Services\Auth;

use App\Models\TwoFactorChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Issues and verifies the second-factor challenge presented after a correct
 * password when TOTP is required for the account.
 */
class TwoFactorChallengeService
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly SecurityEventService $events,
    ) {}

    /**
     * Issue a fresh challenge, invalidating any outstanding ones for the user.
     */
    public function issue(User $user, Request $request, bool $rememberDevice): TwoFactorChallenge
    {
        $user->twoFactorChallenges()->whereNull('consumed_at')->delete();

        $challenge = $user->twoFactorChallenges()->create([
            'challenge_token' => Str::random(64),
            'method' => 'totp',
            'attempts' => 0,
            'max_attempts' => (int) config('auth.two_factor.challenge_max_attempts', 5),
            'remember_device' => $rememberDevice,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'expires_at' => now()->addMinutes((int) config('auth.two_factor.challenge_expire_minutes', 10)),
        ]);

        $this->events->record(SecurityEventService::TWO_FACTOR_CHALLENGE_ISSUED, $user, 'info', [], $request);

        return $challenge;
    }

    /**
     * Verify the submitted TOTP or recovery code against the challenge.
     *
     * @return array{ok: bool, message?: string, user?: User, challenge?: TwoFactorChallenge, used_recovery?: bool}
     */
    public function verify(string $challengeToken, string $code, Request $request): array
    {
        $challenge = TwoFactorChallenge::query()
            ->where('challenge_token', $challengeToken)
            ->first();

        if ($challenge === null || $challenge->isConsumed()) {
            return ['ok' => false, 'message' => 'This verification request is no longer valid.'];
        }

        if ($challenge->isExpired()) {
            return ['ok' => false, 'message' => 'This verification request has expired. Please sign in again.'];
        }

        if ($challenge->hasExceededAttempts()) {
            return ['ok' => false, 'message' => 'Too many incorrect attempts. Please sign in again.'];
        }

        $challenge->increment('attempts');

        $user = $challenge->user;
        $usedRecovery = false;

        $valid = $this->twoFactor->verifyCode($user, $code);

        if (! $valid) {
            $valid = $this->twoFactor->verifyRecoveryCode($user, $code);
            $usedRecovery = $valid;
        }

        if (! $valid) {
            $this->events->record(SecurityEventService::TWO_FACTOR_FAILED, $user, 'warning', [], $request);

            return ['ok' => false, 'message' => 'The verification code is incorrect.'];
        }

        if (! $usedRecovery) {
            $this->events->record(SecurityEventService::TWO_FACTOR_CONFIRMED, $user, 'info', [], $request);
        } else {
            $this->events->record(SecurityEventService::RECOVERY_CODE_USED, $user, 'warning', [], $request);
        }

        $challenge->forceFill(['consumed_at' => now(), 'method' => $usedRecovery ? 'recovery' : 'totp'])->save();

        return ['ok' => true, 'user' => $user, 'challenge' => $challenge, 'used_recovery' => $usedRecovery];
    }
}
