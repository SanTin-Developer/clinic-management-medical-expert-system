<?php

namespace App\Services\Auth;

use App\Services\Auth\Support\Base32;
use InvalidArgumentException;

/**
 * RFC 6238 Time-based One-Time Password implementation.
 *
 * Uses HMAC-SHA1 with the standard 30 second time-step and 6 digit codes so it
 * is compatible with Google Authenticator, Authy, 1Password, Microsoft
 * Authenticator and similar applications. No external dependency is required.
 */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly int $period = 30,
        private readonly int $digits = 6,
        private readonly string $algorithm = 'sha1',
        private readonly int $window = 1,
    ) {}

    /**
     * Generate a new random base32-encoded secret.
     */
    public function generateSecret(int $length = 20): string
    {
        return Base32::encode(random_bytes($length));
    }

    /**
     * Generate the one-time code for a given secret and time-step.
     */
    public function codeAt(string $secret, int $step): string
    {
        $key = Base32::decode($secret);

        if ($key === '') {
            throw new InvalidArgumentException('The provided TOTP secret is not valid base32.');
        }

        $high = intdiv($step, 4294967296);
        $low = $step % 4294967296;

        $hash = hash_hmac($this->algorithm, pack('NN', $high, $low), $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binary % (10 ** $this->digits);

        return str_pad((string) $otp, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * The current one-time code for a secret at the given timestamp.
     */
    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->codeAt($secret, $this->stepAt($timestamp ?? time()));
    }

    /**
     * Verify a submitted code allowing for a small clock drift window.
     *
     * When $afterStep is provided (the last accepted step) any code whose
     * time-step is less than or equal to it is rejected, preventing replay of
     * a code that was already used within its validity window.
     *
     * @return array{valid: bool, step: int|null}
     */
    public function verify(string $secret, string $code, ?int $afterStep = null, ?int $timestamp = null): array
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.$this->digits.'}$/', $code)) {
            return ['valid' => false, 'step' => null];
        }

        $current = $this->stepAt($timestamp ?? time());

        for ($offset = -$this->window; $offset <= $this->window; $offset++) {
            $step = $current + $offset;

            if ($step < 0) {
                continue;
            }

            if ($afterStep !== null && $step <= $afterStep) {
                continue;
            }

            if (hash_equals($this->codeAt($secret, $step), $code)) {
                return ['valid' => true, 'step' => $step];
            }
        }

        return ['valid' => false, 'step' => null];
    }

    /**
     * Build an otpauth:// provisioning URI the frontend renders as a QR code.
     */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$accountName);

        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->digits,
            'period' => $this->period,
        ]);

        return 'otpauth://totp/'.$label.'?'.$params;
    }

    private function stepAt(int $timestamp): int
    {
        return intdiv($timestamp, $this->period);
    }
}
