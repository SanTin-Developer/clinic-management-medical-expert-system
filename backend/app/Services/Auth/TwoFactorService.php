<?php

namespace App\Services\Auth;

use App\Models\User;

class TwoFactorService
{
    public function __construct(
        private readonly TotpService $totp,
    ) {}

    /**
     * Generate a fresh secret for the user and return it together with the
     * provisioning URI. The secret is not yet active until confirmed.
     *
     * @return array{secret: string, uri: string}
     */
    public function enroll(User $user): array
    {
        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri(
                $secret,
                $user->email,
                (string) config('auth.two_factor.issuer', config('app.name')),
            ),
        ];
    }

    /**
     * Confirm enrollment by verifying the first code, then issue recovery codes.
     *
     * @return array<int, string>|null the plaintext recovery codes, or null on failure
     */
    public function confirm(User $user, string $code): ?array
    {
        $secret = $user->two_factor_secret;

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $result = $this->totp->verify($secret, $code);

        if (! $result['valid']) {
            return null;
        }

        $recoveryCodes = $this->generateRecoveryCodes($user);

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $result['step'],
        ])->save();

        return $recoveryCodes;
    }

    /**
     * Disable two-factor authentication and remove all related material.
     */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        $user->twoFactorRecoveryCodes()->delete();
    }

    /**
     * Verify a TOTP code with replay protection (the same step cannot be used
     * twice). Updates the last accepted step on success.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if (! $user->hasTwoFactorEnabled() || ! is_string($secret) || $secret === '') {
            return false;
        }

        $result = $this->totp->verify($secret, $code, $user->two_factor_last_step);

        if (! $result['valid']) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $result['step']])->save();

        return true;
    }

    /**
     * Verify and consume a single-use recovery code.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return false;
        }

        $candidate = hash('sha256', $code);

        foreach ($user->twoFactorRecoveryCodes()->whereNull('used_at')->get() as $recovery) {
            if (hash_equals($recovery->code_hash, $candidate)) {
                $recovery->forceFill(['used_at' => now()])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Regenerate the recovery codes, invalidating any previous codes.
     *
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $user->twoFactorRecoveryCodes()->delete();

        return $this->generateRecoveryCodes($user);
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(User $user): array
    {
        $count = max(1, (int) config('auth.two_factor.recovery_codes', 8));
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $plain = $this->randomCode();
            $codes[] = $plain;

            $user->twoFactorRecoveryCodes()->create([
                'code_hash' => hash('sha256', $plain),
            ]);
        }

        return $codes;
    }

    private function randomCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];

        for ($group = 0; $group < 2; $group++) {
            $chunk = '';
            for ($i = 0; $i < 5; $i++) {
                $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $chunk;
        }

        return implode('-', $groups);
    }
}
