<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public "News & Health" article feed.
 */
class PublicArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Article::query()->published()->with('cover');

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $articles = $query->latest('published_at')->paginate(9);

        $featured = Article::query()
            ->published()
            ->with('cover')
            ->where('is_featured', true)
            ->latest('published_at')
            ->first();

        return response()->json(array_merge($articles->toArray(), [
            'featured' => $featured,
            'categories' => Article::query()
                ->published()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ]));
    }

    public function show(Article $article): JsonResponse
    {
        abort_unless($article->isPublished(), 404);

        $article->increment('views');

        return response()->json([
            'data' => $article->fresh('cover'),
            'related' => Article::query()
                ->published()
                ->with('cover')
                ->where('id', '!=', $article->id)
                ->when($article->category, fn (Builder $builder) => $builder->where('category', $article->category))
                ->latest('published_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
