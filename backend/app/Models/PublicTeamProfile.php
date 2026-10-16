<?php

namespace App\Models;

use Database\Factories\PublicTeamProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public-facing staff profile shown in the "Our Team" section. Optionally
 * linked to a Staff directory record, but never modifies that record.
 */
class PublicTeamProfile extends Model
{
    /** @use HasFactory<PublicTeamProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
        'specialization',
        'bio',
        'photo_media_id',
        'email',
        'phone',
        'is_featured',
        'is_published',
        'sort_order',
        'staff_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_featured' => false,
        'is_published' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
