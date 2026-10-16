<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public const CATEGORIES = [
        'general',
        'hr',
        'maintenance',
        'event',
        'health',
        'policy',
        'other',
    ];

    /**
     * Default local filesystem disk used for posters when Cloudinary is not the
     * active driver (see config('documents.poster_disk')).
     */
    public const POSTER_DISK = 'public';

    /**
     * Directory (on the poster disk) that stores announcement poster images.
     */
    public const POSTER_DIRECTORY = 'announcement-posters';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 'normal',
        'is_published' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'category',
        'poster_path',
        'audience',
        'priority',
        'is_published',
        'published_at',
        'expires_at',
        'archived_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['poster_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function hasPoster(): bool
    {
        return $this->poster_path !== null;
    }

    /**
     * Whether the poster is a remote URL (e.g. Cloudinary) rather than a path
     * on a local disk.
     */
    public function posterIsExternal(): bool
    {
        return $this->poster_path !== null && preg_match('#^https?://#i', $this->poster_path) === 1;
    }

    /**
     * Publicly reachable URL for the announcement poster, or null when none is set.
     */
    protected function posterUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => match (true) {
            $this->poster_path === null => null,
            $this->posterIsExternal() => $this->poster_path,
            default => route('announcements.poster', ['announcement' => $this->getKey()]),
        });
    }

    /**
     * Limit the query to announcements that are currently live to their audience:
     * published, not archived, and within their publication/expiry window.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNull('archived_at')
            ->where(function (Builder $builder): void {
                $builder->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Limit the query to announcements that have not been archived.
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isScheduled(): bool
    {
        return ! $this->is_published
            && ! $this->isArchived()
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    public function isLive(): bool
    {
        return $this->is_published
            && ! $this->isArchived()
            && ($this->published_at === null || $this->published_at->isPast())
            && ! $this->isExpired();
    }

    /**
     * A single derived status label: archived, expired, scheduled, published, draft.
     */
    public function status(): string
    {
        return match (true) {
            $this->isArchived() => 'archived',
            $this->isExpired() => 'expired',
            $this->is_published => 'published',
            $this->isScheduled() => 'scheduled',
            default => 'draft',
        };
    }
}
