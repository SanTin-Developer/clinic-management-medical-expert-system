<?php

namespace App\Services\Auth\Support;

/**
 * Minimal RFC 4648 base32 codec (no padding) used for TOTP secrets.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($bytes) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $output;
    }

    public static function decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $encoded) ?? '');

        if ($encoded === '') {
            return '';
        }

        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($encoded) as $char) {
            $value = strpos(self::ALPHABET, $char);

            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
