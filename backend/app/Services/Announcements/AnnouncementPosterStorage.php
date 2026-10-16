<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Services\Files\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores announcement poster/thumbnail images. Uploads go to Cloudinary when it
 * is configured (the application default) and to a local filesystem disk
 * otherwise. The stored value is a Cloudinary secure URL or a disk-relative
 * path, so callers must treat "isExternal" values as ready-to-use URLs.
 */
class AnnouncementPosterStorage
{
    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly ?string $localDisk = null,
    ) {}

    public function store(UploadedFile $file): string
    {
        $extension = strtolower($file->extension() ?: 'jpg');

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        return $this->storage->store(
            $file,
            Announcement::POSTER_DIRECTORY,
            Str::uuid()->toString().'.'.$extension,
        );
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->storage->delete($path);
        }
    }

    public function isExternal(?string $path): bool
    {
        return $path !== null && preg_match('#^https?://#i', $path) === 1;
    }

    /**
     * Stream a locally-stored poster inline. Returns null when the poster is
     * not on a local disk (e.g. it is a Cloudinary URL) or does not exist.
     */
    public function inlineResponse(string $path): ?Response
    {
        if ($this->localDisk === null || ! Storage::disk($this->localDisk)->exists($path)) {
            return null;
        }

        return Storage::disk($this->localDisk)->response($path);
    }

    /**
     * The local filesystem disk posters are stored on, or null when a remote
     * driver such as Cloudinary is active.
     */
    public function disk(): ?string
    {
        return $this->localDisk;
    }
}
