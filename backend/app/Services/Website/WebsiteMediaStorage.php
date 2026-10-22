<?php

namespace App\Services\Website;

use App\Services\Files\CloudinaryStorage;
use App\Services\Files\DocumentStorage;
use App\Services\Files\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores public-website images. Uploads go to Cloudinary when it is configured
 * (the application default) and to a local filesystem disk otherwise. The stored
 * value is a Cloudinary secure URL or a disk-relative path.
 */
class WebsiteMediaStorage
{
    public const DIRECTORY = 'website/media';

    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly ?string $localDisk = null,
    ) {}

    public function store(UploadedFile $file): StoredFile
    {
        $extension = strtolower($file->extension() ?: 'jpg');

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        $directory = self::DIRECTORY;
        $filename = Str::uuid()->toString().'.'.$extension;

        if ($this->storage instanceof CloudinaryStorage) {
            return $this->storage->storeWithMetadata($file, $directory, $filename);
        }

        return new StoredFile($this->storage->store($file, $directory, $filename));
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->storage->delete($path);
        }
    }

    /**
     * Permanently remove a Cloudinary asset using the identifiers stored on the
     * media record rather than guessing them from the delivery URL.
     */
    public function deleteCloudinaryAsset(string $publicId, string $resourceType): bool
    {
        if (! $this->storage instanceof CloudinaryStorage) {
            throw new \RuntimeException('Cloudinary storage is not configured.');
        }

        return $this->storage->deleteAsset($publicId, $resourceType);
    }

    public function isExternal(?string $path): bool
    {
        return $path !== null && preg_match('#^https?://#i', $path) === 1;
    }

    /**
     * Stream a locally-stored image inline. Returns null when the image is not
     * on a local disk (e.g. it is a Cloudinary URL) or does not exist.
     */
    public function inlineResponse(string $path): ?Response
    {
        if ($this->localDisk === null || ! Storage::disk($this->localDisk)->exists($path)) {
            return null;
        }

        return Storage::disk($this->localDisk)->response($path);
    }

    /**
     * The local filesystem disk images are stored on, or null when a remote
     * driver such as Cloudinary is active.
     */
    public function disk(): ?string
    {
        return $this->localDisk;
    }

    /**
     * The label persisted on the media asset to describe where the file lives.
     */
    public function diskLabel(string $path): string
    {
        if ($this->isExternal($path)) {
            return 'cloudinary';
        }

        return $this->localDisk ?? 'public';
    }
}
