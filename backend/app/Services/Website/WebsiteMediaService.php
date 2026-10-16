<?php

namespace App\Services\Website;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;

/**
 * Creates and removes media library assets from uploaded files.
 */
class WebsiteMediaService
{
    public function __construct(private readonly WebsiteMediaStorage $storage) {}

    public function store(UploadedFile $file, ?int $userId = null, ?string $name = null): MediaAsset
    {
        $path = $this->storage->store($file);

        return MediaAsset::create([
            'name' => $name ?: ($file->getClientOriginalName() ?: basename($path)),
            'file_path' => $path,
            'disk' => $this->storage->diskLabel($path),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    public function delete(MediaAsset $asset): void
    {
        $this->storage->delete($asset->file_path);
        $asset->delete();
    }
}
