<?php

namespace App\Models;

use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file uploaded through the website media library. The stored value is either
 * a Cloudinary secure URL or a disk-relative path, so callers must treat the
 * derived "url" attribute as ready-to-use and check isExternal() before serving
 * from a local disk.
 */
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'file_path',
        'public_id',
        'resource_type',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'uploaded_by',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Whether the file is a remote URL (e.g. Cloudinary) rather than a path on a
     * local disk.
     */
    public function isExternal(): bool
    {
        return preg_match('#^https?://#i', $this->file_path) === 1;
    }

    /**
     * Publicly reachable URL for the asset.
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => $this->isExternal()
            ? $this->file_path
            : route('website.media.show', ['mediaAsset' => $this->getKey()]));
    }
}
