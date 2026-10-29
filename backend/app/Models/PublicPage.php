<?php

namespace App\Models;

use Database\Factories\PublicPageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CMS-managed informational page (About Us, Privacy Policy, Terms of Use, or
 * any additional custom page).
 */
class PublicPage extends Model
{
    /** @use HasFactory<PublicPageFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'title_km',
        'slug',
        'excerpt',
        'excerpt_km',
        'body',
        'body_km',
        'policy_content',
        'internal_notes',
        'cover_media_id',
        'status',
        'is_system',
        'show_in_footer',
        'seo_title',
        'seo_title_km',
        'seo_description',
        'seo_description_km',
        'published_at',
        'updated_by',
    ];

    /** Internal editorial notes are never serialized to public clients. */
    protected $hidden = ['internal_notes'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'is_system' => false,
        'show_in_footer' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'show_in_footer' => 'boolean',
            'published_at' => 'datetime',
            'policy_content' => 'array',
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Limit the query to pages that are live to the public.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $builder): void {
                $builder->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
