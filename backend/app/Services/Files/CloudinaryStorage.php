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

    public function storeWithMetadata(UploadedFile $file, string $directory, string $filename): StoredFile
    {
        $publicId = pathinfo($filename, PATHINFO_FILENAME);

        return $this->client->upload($file, trim($this->folder.'/'.$directory, '/'), $publicId);
    }

    public function store(UploadedFile $file, string $directory, string $filename): string
    {
        return $this->storeWithMetadata($file, $directory, $filename)->path;
    }

    public function deleteAsset(string $publicId, string $resourceType): bool
    {
        return $this->client->destroy($publicId, $resourceType);
    }

    public function delete(string $path): void
    {
        $metadata = $this->metadataFromUrl($path);

        if ($metadata === null) {
            return;
        }

        $this->client->destroy($metadata['public_id'], $metadata['resource_type']);
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
     * Derive the Cloudinary public id and resource type from a delivery URL
     * such as https://res.cloudinary.com/<cloud>/image/upload/v123/<folder>/<id>.png.
     * This is only used for legacy document/poster paths; website media uses the
     * identifiers stored on the media asset.
     *
     * @return array{public_id: string, resource_type: string}|null
     */
    private function metadataFromUrl(string $url): ?array
    {
        if (! str_starts_with($url, 'http')) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if (! preg_match('#/(image|raw|video)/upload/(?:v\d+/)?(.+)$#', $path, $matches)) {
            return null;
        }

        $publicId = rawurldecode((string) $matches[2]);

        if ($matches[1] !== 'raw') {
            $publicId = (string) preg_replace('#\.[a-zA-Z0-9]+$#', '', $publicId);
        }

        return $publicId === ''
            ? null
            : ['public_id' => $publicId, 'resource_type' => $matches[1]];
    }
}
