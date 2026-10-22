<?php

namespace App\Services\Files;

/**
 * The result of storing a file. Cloudinary-backed uploads also expose the
 * identifiers required for a later permanent deletion; local disks leave them
 * null because the stored path is sufficient.
 */
final readonly class StoredFile
{
    public function __construct(
        public string $path,
        public ?string $publicId = null,
        public ?string $resourceType = null,
    ) {}
}
