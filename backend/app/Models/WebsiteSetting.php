<?php

namespace App\Models;

use Database\Factories\WebsiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton row holding global public-website configuration (branding, clinic
 * information, homepage hero and SEO defaults).
 */
class WebsiteSetting extends Model
{
    /** @use HasFactory<WebsiteSettingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_name',
        'tagline',
        'logo_media_id',
        'favicon_media_id',
        'hero_title',
        'hero_subtitle',
        'hero_media_id',
        'about_summary',
        'primary_phone',
        'secondary_phone',
        'email',
        'address',
        'map_embed_url',
        'business_hours',
        'facebook_url',
        'twitter_url',
        'instagram_url',
        'linkedin_url',
        'youtube_url',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'updated_by',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public function favicon(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'favicon_media_id');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hero_media_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The current settings row, creating it with defaults on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'site_name' => (string) config('app.name', 'RoyalCare'),
        ]);
    }
}
