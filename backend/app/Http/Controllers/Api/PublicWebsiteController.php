<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentBlock;
use App\Models\GalleryItem;
use App\Models\PublicPage;
use App\Models\PublicTeamProfile;
use App\Models\Service;
use App\Models\WebsiteSetting;
use Illuminate\Http\JsonResponse;

/**
 * Unauthenticated endpoints backing the public informational website.
 */
class PublicWebsiteController extends Controller
{
    /**
     * Global payload the public layout needs: branding, footer links and the
     * service list used by the header/footer navigation.
     */
    public function bootstrap(): JsonResponse
    {
        return response()->json([
            'settings' => WebsiteSetting::current()->load(['logo', 'favicon']),
            'footer_pages' => PublicPage::query()
                ->published()
                ->where('show_in_footer', true)
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
            'services' => Service::query()
                ->published()
                ->ordered()
                ->get(['id', 'title', 'slug']),
            'content_blocks' => ContentBlock::query()
                ->with('image')
                ->orderBy('group')
                ->orderBy('key')
                ->get(),
        ]);
    }

    /**
     * Aggregated homepage content.
     */
    public function home(): JsonResponse
    {
        return response()->json([
            'settings' => WebsiteSetting::current()->load(['logo', 'favicon']),
            'services' => Service::query()
                ->published()
                ->with('image')
                ->where('is_featured', true)
                ->ordered()
                ->limit(6)
                ->get(),
            'team' => PublicTeamProfile::query()
                ->published()
                ->with('photo')
                ->where('is_featured', true)
                ->ordered()
                ->limit(8)
                ->get(),
            'articles' => Article::query()
                ->published()
                ->with('cover')
                ->latest('published_at')
                ->limit(3)
                ->get(),
            'gallery' => GalleryItem::query()
                ->published()
                ->with('image')
                ->ordered()
                ->limit(8)
                ->get(),
        ]);
    }
}
