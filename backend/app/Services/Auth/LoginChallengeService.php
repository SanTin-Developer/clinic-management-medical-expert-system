<?php

namespace App\Services\Auth;

use App\Models\LoginChallenge;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginChallengeService
{
    /**
     * Create a fresh one-time passcode challenge for the given user and email
     * it to them. Any outstanding challenges for the user are invalidated so
     * only the most recent code can be used.
     */
    public function issue(User $user, Request $request): LoginChallenge
    {
        $user->loginChallenges()->whereNull('consumed_at')->delete();

        $length = (int) config('auth.otp.length', 6);
        $expireMinutes = (int) config('auth.otp.expire_minutes', 10);
        $code = $this->generateCode($length);

        $challenge = $user->loginChallenges()->create([
            'challenge_token' => Str::random(64),
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => (int) config('auth.otp.max_attempts', 5),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'expires_at' => now()->addMinutes($expireMinutes),
            'last_sent_at' => now(),
        ]);

        $user->notify(new LoginOtpNotification($code, $expireMinutes));

        return $challenge;
    }

    /**
     * @return array{ok: bool, message?: string, challenge?: LoginChallenge}
     */
    public function verify(string $challengeToken, string $code): array
    {
        $challenge = LoginChallenge::query()
            ->where('challenge_token', $challengeToken)
            ->first();

        if ($challenge === null || $challenge->isConsumed()) {
            return ['ok' => false, 'message' => 'This verification request is no longer valid.'];
        }

        if ($challenge->isExpired()) {
            return ['ok' => false, 'message' => 'This verification code has expired. Please sign in again.'];
        }

        if ($challenge->hasExceededAttempts()) {
            return ['ok' => false, 'message' => 'Too many incorrect attempts. Please sign in again.'];
        }

        $challenge->increment('attempts');

        if (! Hash::check($code, $challenge->code_hash)) {
            return ['ok' => false, 'message' => 'The verification code is incorrect.'];
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        return ['ok' => true, 'challenge' => $challenge];
    }

    /**
     * Whether a new code may be sent for the challenge (cooldown respected).
     */
    public function canResend(LoginChallenge $challenge): bool
    {
        if ($challenge->last_sent_at === null) {
            return true;
        }

        $cooldown = (int) config('auth.otp.resend_cooldown_seconds', 60);

        return $challenge->last_sent_at->addSeconds($cooldown)->isPast();
    }

    /**
     * Regenerate the code for an existing challenge and email it again.
     */
    public function resend(LoginChallenge $challenge): LoginChallenge
    {
        $length = (int) config('auth.otp.length', 6);
        $expireMinutes = (int) config('auth.otp.expire_minutes', 10);
        $code = $this->generateCode($length);

        $challenge->forceFill([
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expireMinutes),
            'last_sent_at' => now(),
        ])->save();

        $challenge->user->notify(new LoginOtpNotification($code, $expireMinutes));

        return $challenge;
    }

    private function generateCode(int $length): string
    {
        $maximum = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $maximum), $length, '0', STR_PAD_LEFT);
    }
}
