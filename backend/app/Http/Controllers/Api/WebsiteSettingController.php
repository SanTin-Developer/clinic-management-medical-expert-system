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
 */
class WebsiteSettingController extends Controller
{
    use InteractsWithWebsiteMedia;

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function show(): JsonResponse
    {
        $this->authorize('website.settings.view');

        return response()->json([
            'data' => WebsiteSetting::current()->load(['logo', 'favicon', 'heroMedia']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('website.settings.update');

        $request->merge(
            collect($request->except(['logo', 'favicon', 'hero_image']))
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all()
        );

        $validated = $request->validate([
            'site_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],
            'about_summary' => ['nullable', 'string'],
            'primary_phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'map_embed_url' => ['nullable', 'string'],
            'business_hours' => ['nullable', 'string'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'logo' => $this->imageRules(),
            'favicon' => $this->imageRules(),
            'hero_image' => $this->imageRules(),
        ]);

        $settings = WebsiteSetting::current();

        $settings->logo_media_id = $this->resolveMediaId($request, $this->media, 'logo', $settings->logo_media_id, 'remove_logo');
        $settings->favicon_media_id = $this->resolveMediaId($request, $this->media, 'favicon', $settings->favicon_media_id, 'remove_favicon');
        $settings->hero_media_id = $this->resolveMediaId($request, $this->media, 'hero_image', $settings->hero_media_id, 'remove_hero_image');

        $settings->fill(collect($validated)->except([
            'logo', 'favicon', 'hero_image',
        ])->all());

        $settings->updated_by = $request->user()->id;
        $settings->save();

        return response()->json([
            'message' => 'Website settings updated successfully.',
            'data' => $settings->fresh(['logo', 'favicon', 'heroMedia']),
        ]);
    }
}
