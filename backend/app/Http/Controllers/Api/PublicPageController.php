<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicPage;
use Illuminate\Http\JsonResponse;

/**
 * Public access to CMS-managed informational pages.
 */
class PublicPageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PublicPage::query()
                ->published()
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'excerpt', 'show_in_footer']),
        ]);
    }

    public function show(PublicPage $page): JsonResponse
    {
        abort_unless($page->isPublished(), 404);

        return response()->json([
            'data' => $page->load('cover'),
        ]);
    }
}
