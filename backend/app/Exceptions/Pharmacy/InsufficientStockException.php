<?php

namespace App\Exceptions\Pharmacy;

use RuntimeException;

/**
 * Thrown when a pharmacy operation cannot be satisfied by usable stock.
 * Extends RuntimeException so existing 422 handlers keep working.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $medicineName = null,
        public readonly int $requested = 0,
        public readonly int $available = 0,
    ) {
        parent::__construct($message);
    }
}
