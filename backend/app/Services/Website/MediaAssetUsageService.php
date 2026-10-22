<?php

namespace App\Services\Website;

use App\Models\Article;
use App\Models\ContentBlock;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\PublicPage;
use App\Models\PublicTeamProfile;
use App\Models\Service;
use App\Models\WebsiteSetting;
use Illuminate\Support\Collection;

/**
 * Finds every CMS record that references a media asset so permanent deletion can
 * be blocked while the image is still in use.
 */
class MediaAssetUsageService
{
    /**
     * @param  iterable<int>  $mediaIds
     * @return array<int, list<array{type: string, id: int, label: string}>>
     */
    public function forAssets(iterable $mediaIds): array
    {
        $ids = collect($mediaIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $references = [];

        $add = static function (int $mediaId, string $type, int $recordId, string $label) use (&$references): void {
            $references[$mediaId][] = [
                'type' => $type,
                'id' => $recordId,
                'label' => $label,
            ];
        };

        Service::query()
            ->whereIn('image_media_id', $ids)
            ->get(['id', 'title', 'image_media_id'])
            ->each(static fn (Service $service) => $add(
                (int) $service->image_media_id,
                'service',
                $service->id,
                'Service: '.$service->title,
            ));

        PublicTeamProfile::query()
            ->whereIn('photo_media_id', $ids)
            ->get(['id', 'name', 'photo_media_id'])
            ->each(static fn (PublicTeamProfile $profile) => $add(
                (int) $profile->photo_media_id,
                'team',
                $profile->id,
                'Team profile: '.$profile->name,
            ));

        GalleryItem::query()
            ->whereIn('image_media_id', $ids)
            ->get(['id', 'title', 'image_media_id'])
            ->each(static fn (GalleryItem $item) => $add(
                (int) $item->image_media_id,
                'gallery',
                $item->id,
                'Gallery item: '.($item->title ?: 'Untitled'),
            ));

        Article::query()
            ->whereIn('cover_media_id', $ids)
            ->get(['id', 'title', 'cover_media_id'])
            ->each(static fn (Article $article) => $add(
                (int) $article->cover_media_id,
                'article',
                $article->id,
                'Article: '.$article->title,
            ));

        PublicPage::query()
            ->whereIn('cover_media_id', $ids)
            ->get(['id', 'title', 'cover_media_id'])
            ->each(static fn (PublicPage $page) => $add(
                (int) $page->cover_media_id,
                'page',
                $page->id,
                'Page: '.$page->title,
            ));

        ContentBlock::query()
            ->whereIn('image_media_id', $ids)
            ->get(['id', 'group', 'key', 'title', 'image_media_id'])
            ->each(static fn (ContentBlock $block) => $add(
                (int) $block->image_media_id,
                'content_block',
                $block->id,
                'Content block: '.($block->title ?: $block->group.'/'.$block->key),
            ));

        WebsiteSetting::query()
            ->whereIn('logo_media_id', $ids)
            ->orWhereIn('favicon_media_id', $ids)
            ->orWhereIn('hero_media_id', $ids)
            ->get(['id', 'logo_media_id', 'favicon_media_id', 'hero_media_id'])
            ->each(function (WebsiteSetting $settings) use ($ids, $add): void {
                foreach ([
                    'logo_media_id' => 'Website settings: logo',
                    'favicon_media_id' => 'Website settings: favicon',
                    'hero_media_id' => 'Website settings: hero image',
                ] as $column => $label) {
                    $mediaId = $settings->{$column};

                    if ($mediaId !== null && $ids->contains((int) $mediaId)) {
                        $add((int) $mediaId, 'settings', $settings->id, $label);
                    }
                }
            });

        return $references;
    }

    /**
     * @return list<array{type: string, id: int, label: string}>
     */
    public function forAsset(MediaAsset $asset): array
    {
        return $this->forAssets([$asset->id])[$asset->id] ?? [];
    }

    /**
     * @param  Collection<int, MediaAsset>  $assets
     * @return array<int, list<array{type: string, id: int, label: string}>>
     */
    public function forCollection(Collection $assets): array
    {
        return $this->forAssets($assets->pluck('id')->all());
    }
}
