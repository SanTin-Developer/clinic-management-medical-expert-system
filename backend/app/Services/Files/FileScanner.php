<?php

namespace App\Services\Files;

interface FileScanner
{
    /**
     * Scan a file on disk for malware.
     *
     * Implementations must never throw for a clean file. A failed scan should
     * return ScanResult::error() so the caller can decide whether to fail open
     * or closed.
     */
    public function scan(string $absolutePath, string $originalName): ScanResult;
}
