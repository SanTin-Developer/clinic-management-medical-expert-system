<?php

namespace App\Support;

/**
 * Normalises the application encryption key.
 *
 * Render (and some other hosts) generate a raw base64 or hex string for
 * APP_KEY instead of Laravel's `base64:`-prefixed value. Laravel treats an
 * unprefixed key as literal bytes, so a 44-character base64 string would be
 * rejected as an invalid AES-256 key length. Converting the key here keeps the
 * config array serialisable for `php artisan config:cache`.
 */
class AppKey
{
    public static function normalise(?string $key): ?string
    {
        if (! is_string($key) || $key === '' || str_starts_with($key, 'base64:')) {
            return $key;
        }

        // Render's generateValue produces standard base64; accept the URL-safe
        // alphabet (and missing padding) in case a host switches encodings.
        $encoded = strtr($key, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);

        if ($decoded !== false && strlen($decoded) === 32) {
            return 'base64:'.base64_encode($decoded);
        }

        if (strlen($key) === 64 && ctype_xdigit($key)) {
            return 'base64:'.base64_encode(hex2bin($key));
        }

        return $key;
    }
}
