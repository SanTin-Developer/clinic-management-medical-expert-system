<?php

namespace App\Models;

use Database\Factories\WebsiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton row holding global public-website configuration (branding, clinic
 * information and SEO defaults).
 *
 * This row owns the site-wide settings only. Every piece of page copy - the
 * homepage hero, the section headings, the closing banner and the copy of the
 * other public pages - belongs to the content blocks (see the ContentBlock
 * model), which are edited from the "Page Content" screen of the admin CMS.
 *
 * The legacy hero_title, hero_subtitle, about_summary and hero_media_id
 * columns are kept in the table for backwards compatibility but are no longer
 * written and are hidden from every API payload, so one field can never drive
 * two sections of the website again.
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

    /**
     * Retired duplicate columns. They stay in the database, but they are never
     * serialised: the content blocks own that copy now.
     *
     * @var list<string>
     */
    protected $hidden = [
        'hero_title',
        'hero_subtitle',
        'about_summary',
        'hero_media_id',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public function favicon(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'favicon_media_id');
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
