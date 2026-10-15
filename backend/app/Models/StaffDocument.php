<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confidential staff documents (CV, certificates, licenses, employment contracts).
 * Stored on private storage; downloads are always streamed through an authorized
 * controller and never exposed via public URLs.
 */
class StaffDocument extends Model
{
    use HasFactory;

    public const CATEGORY_CV = 'cv';

    public const CATEGORY_PHOTO = 'photo';

    public const CATEGORY_CERTIFICATE = 'certificate';

    public const CATEGORY_LICENSE = 'license';

    public const CATEGORY_CONTRACT = 'contract';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_CV,
        self::CATEGORY_PHOTO,
        self::CATEGORY_CERTIFICATE,
        self::CATEGORY_LICENSE,
        self::CATEGORY_CONTRACT,
        self::CATEGORY_OTHER,
    ];

    protected $fillable = [
        'staff_id',
        'uploaded_by',
        'title',
        'category',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
