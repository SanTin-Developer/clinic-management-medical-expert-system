<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores documents in Cloudinary and returns secure delivery URLs. The stored
 * "path" is the Cloudinary secure URL. The destination folder is controlled by
 * the CLOUDINARY_FOLDER environment variable.
 */
class CloudinaryStorage implements DocumentStorage
{
    public function __construct(
        private readonly CloudinaryClient $client,
        private readonly string $folder = 'clinic-documents',
    ) {}

    public function store(UploadedFile $file, string $directory, string $filename): string
    {
        $publicId = pathinfo($filename, PATHINFO_FILENAME);

        return $this->client->upload($file, trim($this->folder.'/'.$directory, '/'), $publicId);
    }

    public function delete(string $path): void
    {
        if (! str_starts_with($path, 'http')) {
            return;
        }

        $this->client->destroy($path);
    }

    public function exists(string $path): bool
    {
        return str_starts_with($path, 'http');
    }

    public function download(string $path, string $downloadName): Response
    {
        return new RedirectResponse($path);
    }
}
