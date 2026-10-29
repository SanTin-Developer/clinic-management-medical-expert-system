<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithWebsiteMedia;
use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management of the singleton website settings row.
 *
 * Only site-wide settings live here: branding, contact details, social links
 * and the SEO defaults. Page-specific copy is owned by the content blocks
 * (see WebsiteContentBlockController), so the legacy hero columns are kept in
 * the table but are no longer written from this endpoint and are not part of
 * the payload.
 */
class WebsiteSettingController extends Controller
{
    use InteractsWithWebsiteMedia;

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function show(): JsonResponse
    {
        $this->authorize('website.settings.view');

        return response()->json([
            'data' => WebsiteSetting::current()->load(['logo', 'favicon']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('website.settings.update');

        $request->merge(
            collect($request->except(['logo', 'favicon']))
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all()
        );

        $validated = $request->validate([
            'site_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'tagline_km' => ['nullable', 'string', 'max:255'],
            'primary_phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_km' => ['nullable', 'string', 'max:255'],
            'map_embed_url' => ['nullable', 'string'],
            'business_hours' => ['nullable', 'string'],
            'business_hours_km' => ['nullable', 'string'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_title_km' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_description_km' => ['nullable', 'string'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'logo' => $this->imageRules(),
            'favicon' => $this->imageRules(),
        ]);

        $settings = WebsiteSetting::current();
        $previousMediaIds = [
            'logo' => $settings->logo_media_id,
            'favicon' => $settings->favicon_media_id,
        ];

        $settings->logo_media_id = $this->resolveMediaId($request, $this->media, 'logo', $settings->logo_media_id, 'remove_logo');
        $settings->favicon_media_id = $this->resolveMediaId($request, $this->media, 'favicon', $settings->favicon_media_id, 'remove_favicon');

        $settings->fill(collect($validated)->except([
            'logo', 'favicon',
        ])->all());

        $settings->updated_by = $request->user()->id;
        $settings->save();

        return response()->json([
            'message' => 'Website settings updated successfully.',
            'data' => $settings->fresh(['logo', 'favicon']),
            'media_cleanup' => array_filter([
                'logo' => $this->cleanupReplacedMedia($this->media, $previousMediaIds['logo'], $settings->logo_media_id),
                'favicon' => $this->cleanupReplacedMedia($this->media, $previousMediaIds['favicon'], $settings->favicon_media_id),
            ]),
        ]);
    }
}
