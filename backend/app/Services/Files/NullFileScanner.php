<?php

namespace App\Services\Files;

/**
 * No-op scanner used for local development and tests. It performs no scanning
 * and always reports the file as clean. Never use this driver in production.
 */
class NullFileScanner implements FileScanner
{
    public function scan(string $absolutePath, string $originalName): ScanResult
    {
        return ScanResult::clean();
    }
}
