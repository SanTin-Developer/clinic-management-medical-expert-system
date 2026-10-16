<?php

namespace App\Models;

use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public "News & Health" article published on the website. Distinct from
 * internal clinic Announcements, which remain private to staff.
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
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
        'slug',
        'excerpt',
        'body',
        'cover_media_id',
        'category',
        'tags',
        'status',
        'is_featured',
        'views',
        'published_at',
        'author_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'is_featured' => false,
        'views' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_featured' => 'boolean',
            'views' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Limit the query to articles that are live to the public.
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
