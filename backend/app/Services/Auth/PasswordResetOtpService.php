<?php

namespace App\Services\Auth;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetOtpService
{
    /**
     * Create a fresh one-time passcode for the user and email it to them. Any
     * outstanding codes for the user are invalidated so only the most recent
     * code can be used.
     */
    public function issue(User $user, Request $request): PasswordResetOtp
    {
        $user->passwordResetOtps()->whereNull('consumed_at')->delete();

        $code = $this->generateCode();
        $expireMinutes = $this->expireMinutes();

        $otp = $user->passwordResetOtps()->create([
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => $this->maxAttempts(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'expires_at' => now()->addMinutes($expireMinutes),
            'last_sent_at' => now(),
        ]);

        $user->notify(new PasswordResetOtpNotification($code, $expireMinutes));

        return $otp;
    }

    /**
     * Check the one-time passcode without consuming it. On success a fresh,
     * short-lived reset token is issued for the final "set a new password"
     * step; each successful verification replaces the previous token so only
     * the most recent one stays valid.
     *
     * @return array{ok: bool, message?: string, reset_token?: string}
     */
    public function verify(User $user, string $code): array
    {
        $otp = $user->passwordResetOtps()
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if ($otp === null || $otp->isConsumed()) {
            return ['ok' => false, 'message' => 'The verification code is invalid or has expired.'];
        }

        if ($otp->isExpired()) {
            return ['ok' => false, 'message' => 'This verification code has expired. Please request a new one.'];
        }

        if ($otp->hasExceededAttempts()) {
            return ['ok' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return ['ok' => false, 'message' => 'The verification code is incorrect.'];
        }

        $resetToken = Str::random(64);

        $otp->forceFill([
            'reset_token_hash' => Hash::make($resetToken),
            'reset_token_expires_at' => now()->addMinutes($this->tokenExpireMinutes()),
        ])->save();

        return ['ok' => true, 'reset_token' => $resetToken];
    }

    /**
     * Consume the reset token issued by a successful code verification and mark
     * the OTP as used so it can no longer be verified.
     *
     * @return array{ok: bool, message?: string}
     */
    public function complete(User $user, string $resetToken): array
    {
        $otp = $user->passwordResetOtps()
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (
            $otp === null
            || $otp->isConsumed()
            || $otp->reset_token_hash === null
            || $otp->reset_token_expires_at === null
            || $otp->reset_token_expires_at->isPast()
        ) {
            return ['ok' => false, 'message' => 'This reset session has expired. Please request a new code.'];
        }

        if (! Hash::check($resetToken, $otp->reset_token_hash)) {
            return ['ok' => false, 'message' => 'This reset session is invalid. Please request a new code.'];
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return ['ok' => true];
    }

    private function generateCode(): string
    {
        $length = (int) config('auth.password_reset.code_length', 6);
        $maximum = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $maximum), $length, '0', STR_PAD_LEFT);
    }

    private function expireMinutes(): int
    {
        return (int) config('auth.password_reset.expire_minutes', 15);
    }

    private function tokenExpireMinutes(): int
    {
        return (int) config('auth.password_reset.token_expire_minutes', 10);
    }

    private function maxAttempts(): int
    {
        return (int) config('auth.password_reset.max_attempts', 5);
    }
}
