<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const AUDIENCES = [
        'all',
        'staff',
        'doctors',
        'nurses',
        'receptionists',
        'pharmacists',
        'patients',
    ];

    /**
     * @var list<string>
     */
    public const PRIORITIES = ['normal', 'high', 'urgent'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'audience',
        'priority',
        'is_published',
        'published_at',
        'expires_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Limit the query to announcements that are currently live to their audience.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $builder): void {
                $builder->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isLive(): bool
    {
        return $this->is_published
            && ($this->published_at === null || $this->published_at->isPast())
            && ! $this->isExpired();
    }
}
