<?php

namespace App\Services\Files;

final class ScanResult
{
    private function __construct(
        public readonly bool $clean,
        public readonly string $status,
        public readonly ?string $signature = null,
        public readonly ?string $message = null,
    ) {}

    public static function clean(): self
    {
        return new self(true, 'clean');
    }

    public static function infected(?string $signature): self
    {
        return new self(false, 'infected', $signature);
    }

    public static function error(string $message): self
    {
        return new self(false, 'error', null, $message);
    }
}
