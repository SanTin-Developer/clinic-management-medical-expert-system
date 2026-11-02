<?php

namespace Tests\Unit;

use App\Support\AppKey;
use PHPUnit\Framework\TestCase;

class AppKeyTest extends TestCase
{
    public function test_it_prefixes_a_standard_base64_key(): void
    {
        $raw = random_bytes(32);
        $key = base64_encode($raw);

        $normalised = AppKey::normalise($key);

        $this->assertStringStartsWith('base64:', $normalised);
        $this->assertSame($raw, base64_decode(substr($normalised, 7)));
    }

    public function test_it_accepts_url_safe_base64_without_padding(): void
    {
        $raw = random_bytes(32);
        $key = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $normalised = AppKey::normalise($key);

        $this->assertStringStartsWith('base64:', $normalised);
        $this->assertSame($raw, base64_decode(substr($normalised, 7)));
    }

    public function test_it_converts_a_hex_key(): void
    {
        $raw = random_bytes(32);

        $normalised = AppKey::normalise(bin2hex($raw));

        $this->assertStringStartsWith('base64:', $normalised);
        $this->assertSame($raw, base64_decode(substr($normalised, 7)));
    }

    public function test_it_leaves_an_already_prefixed_key_untouched(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        $this->assertSame($key, AppKey::normalise($key));
    }

    public function test_it_leaves_empty_and_missing_keys_untouched(): void
    {
        $this->assertNull(AppKey::normalise(null));
        $this->assertSame('', AppKey::normalise(''));
    }
}
