<?php

namespace App\Services\Website;

use App\Exceptions\MediaAssetDeletionException;
use App\Exceptions\MediaAssetInUseException;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates and removes media library assets from uploaded files.
 */
class WebsiteMediaService
{
    public function __construct(
        private readonly WebsiteMediaStorage $storage,
        private readonly MediaAssetUsageService $usage,
    ) {}

    public function store(UploadedFile $file, ?int $userId = null, ?string $name = null): MediaAsset
    {
        $stored = $this->storage->store($file);

        return MediaAsset::create([
            'name' => $name ?: ($file->getClientOriginalName() ?: basename($stored->path)),
            'file_path' => $stored->path,
            'public_id' => $stored->publicId,
            'resource_type' => $stored->resourceType,
            'disk' => $this->storage->diskLabel($stored->path),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * Permanently delete an asset after proving that no CMS record references it.
     *
     * The remote object is deleted before the database row. If Cloudinary fails,
     * the row remains and the administrator can retry. If the final database
     * delete fails, the same retry is safe because Cloudinary returns "not found"
     * for an already-deleted asset.
     */
    public function delete(MediaAsset $asset): void
    {
        $references = $this->usage->forAsset($asset);

        if ($references !== []) {
            throw new MediaAssetInUseException($references);
        }

        if ($asset->disk === 'cloudinary') {
            if (! $asset->public_id || ! in_array($asset->resource_type, ['image', 'raw', 'video'], true)) {
                throw MediaAssetDeletionException::missingCloudinaryMetadata();
            }

            try {
                $this->storage->deleteCloudinaryAsset($asset->public_id, $asset->resource_type);
            } catch (Throwable $exception) {
                report($exception);

                throw MediaAssetDeletionException::cloudinaryFailed($exception);
            }
        } else {
            try {
                $this->storage->delete($asset->file_path);
            } catch (Throwable $exception) {
                report($exception);

                throw new MediaAssetDeletionException(
                    'The media file could not be deleted. The media record was kept so you can retry safely.',
                    502,
                    $exception,
                );
            }
        }

        try {
            $asset->delete();
        } catch (Throwable $exception) {
            report($exception);

            throw MediaAssetDeletionException::databaseFailed($exception);
        }
    }

    /**
     * Best-effort cleanup used after an image is detached/replaced or a CMS
     * record is deleted. Shared assets are intentionally left untouched.
     *
     * @return array{status: string, media_asset_id: int, references?: list<array{type: string, id: int, label: string}>, message?: string}|null
     */
    public function cleanupIfUnused(?int $mediaId): ?array
    {
        if ($mediaId === null) {
            return null;
        }

        $asset = MediaAsset::find($mediaId);

        if (! $asset) {
            return [
                'status' => 'already_deleted',
                'media_asset_id' => $mediaId,
            ];
        }

        try {
            $this->delete($asset);

            return [
                'status' => 'deleted',
                'media_asset_id' => $mediaId,
            ];
        } catch (MediaAssetInUseException $exception) {
            return [
                'status' => 'in_use',
                'media_asset_id' => $mediaId,
                'references' => $exception->references,
            ];
        } catch (MediaAssetDeletionException $exception) {
            Log::warning('Media asset cleanup failed after a CMS change.', [
                'media_asset_id' => $mediaId,
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'media_asset_id' => $mediaId,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
