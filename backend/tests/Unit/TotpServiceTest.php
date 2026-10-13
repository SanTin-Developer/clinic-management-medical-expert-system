<?php

namespace Tests\Unit;

use App\Services\Auth\Support\Base32;
use App\Services\Auth\TotpService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    /**
     * RFC 6238 Appendix B test vectors (SHA-1), including a 6-digit variant.
     * The shared secret is the ASCII string "12345678901234567890".
     *
     * @return array<string, array{0: int, 1: string}>
     */
    public static function rfcVectors(): array
    {
        return [
            '59' => [59, '287082'],
            '1111111109' => [1111111109, '081804'],
            '1111111111' => [1111111111, '050471'],
            '1234567890' => [1234567890, '005924'],
            '2000000000' => [2000000000, '279037'],
            '20000000000' => [20000000000, '353130'],
        ];
    }

    /**
     * @dataProvider rfcVectors
     */
    #[DataProvider('rfcVectors')]
    public function test_it_matches_rfc6238_test_vectors(int $timestamp, string $expected): void
    {
        $secret = Base32::encode('12345678901234567890');
        $totp = new TotpService;

        $this->assertSame($expected, $totp->codeAt($secret, intdiv($timestamp, 30)));
    }

    public function test_base32_round_trips_binary_data(): void
    {
        $binary = random_bytes(20);

        $this->assertSame($binary, Base32::decode(Base32::encode($binary)));
    }

    public function test_generated_secret_is_valid_base32_and_correct_length(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertSame(32, strlen($secret));
    }

    public function test_verify_accepts_a_current_code_and_reports_the_step(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();
        $code = $totp->currentCode($secret);

        $result = $totp->verify($secret, $code);

        $this->assertTrue($result['valid']);
        $this->assertIsInt($result['step']);
    }

    public function test_verify_rejects_a_replayed_step(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();
        $code = $totp->currentCode($secret);

        $first = $totp->verify($secret, $code);

        $this->assertTrue($first['valid']);

        $replay = $totp->verify($secret, $code, $first['step']);

        $this->assertFalse($replay['valid']);
    }

    public function test_verify_rejects_malformed_codes(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();

        $this->assertFalse($totp->verify($secret, 'abcdef')['valid']);
        $this->assertFalse($totp->verify($secret, '12345')['valid']);
        $this->assertFalse($totp->verify($secret, '')['valid']);
    }

    public function test_provisioning_uri_contains_secret_and_issuer(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();

        $uri = $totp->provisioningUri($secret, 'user@example.com', 'RoyalCare Clinic');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
        $this->assertStringContainsString('issuer=RoyalCare', $uri);
    }
}
