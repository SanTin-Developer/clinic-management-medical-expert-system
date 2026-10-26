<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContactMessage;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\PublicPage;
use App\Models\PublicTeamProfile;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

/**
 * Summary counts backing the Website Management overview screen.
 */
class WebsiteDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('website.view');

        return response()->json([
            'stats' => [
                'pages' => PublicPage::count(),
                'pages_published' => PublicPage::where('status', PublicPage::STATUS_PUBLISHED)->count(),
                'services' => Service::count(),
                'services_published' => Service::where('is_published', true)->count(),
                'team' => PublicTeamProfile::count(),
                'team_published' => PublicTeamProfile::where('is_published', true)->count(),
                'gallery' => GalleryItem::count(),
                'gallery_published' => GalleryItem::where('is_published', true)->count(),
                'articles' => Article::count(),
                'articles_published' => Article::where('status', Article::STATUS_PUBLISHED)->count(),
                'media' => MediaAsset::count(),
                'contact_messages' => ContactMessage::count(),
                'contact_messages_unread' => ContactMessage::where('status', 'new')->count(),
            ],
            'recent_articles' => Article::query()
                ->with('cover:id,file_path,disk')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'title', 'slug', 'status', 'cover_media_id', 'published_at', 'created_at']),
        ]);
    }
}
