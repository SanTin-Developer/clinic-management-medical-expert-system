<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A safe, administrator-facing failure while permanently deleting media.
 */
class MediaAssetDeletionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function missingCloudinaryMetadata(): self
    {
        return new self(
            'This image cannot be permanently deleted because its Cloudinary identifier is missing.',
            422,
        );
    }

    public static function cloudinaryFailed(Throwable $previous): self
    {
        return new self(
            'Cloudinary could not delete this image. The media record was kept so you can retry safely.',
            502,
            $previous,
        );
    }

    public static function databaseFailed(Throwable $previous): self
    {
        return new self(
            'The image was removed from Cloudinary, but its media record could not be deleted. Retry the deletion to finish cleanup.',
            500,
            $previous,
        );
    }
}
