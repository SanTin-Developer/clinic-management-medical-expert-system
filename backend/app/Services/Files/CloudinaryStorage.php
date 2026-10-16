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
        $publicId = $this->publicIdFromUrl($path);

        if ($publicId === null) {
            return;
        }

        $this->client->destroy($publicId);
    }

    public function exists(string $path): bool
    {
        return str_starts_with($path, 'http');
    }

    public function download(string $path, string $downloadName): Response
    {
        return new RedirectResponse($path);
    }

    /**
     * Derive the Cloudinary public id (including folder) from a secure delivery
     * URL such as
     * https://res.cloudinary.com/<cloud>/image/upload/v123/<folder>/<id>.png.
     * Returns null when the value is not a Cloudinary delivery URL.
     */
    private function publicIdFromUrl(string $url): ?string
    {
        if (! str_starts_with($url, 'http')) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $marker = '/upload/';

        if (! str_contains($path, $marker)) {
            return null;
        }

        $path = substr($path, strpos($path, $marker) + strlen($marker));

        $path = (string) preg_replace('#^v\d+/#', '', $path);
        $path = (string) preg_replace('#\.[a-zA-Z0-9]+$#', '', $path);

        return $path === '' ? null : rawurldecode($path);
    }
}
