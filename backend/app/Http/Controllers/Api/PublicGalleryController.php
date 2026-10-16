<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public listing of published gallery images.
 */
class PublicGalleryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GalleryItem::query()->published()->with('image');

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $items = $query->ordered()->get();

        return response()->json([
            'data' => $items,
            'categories' => GalleryItem::query()
                ->published()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ]);
    }
}
