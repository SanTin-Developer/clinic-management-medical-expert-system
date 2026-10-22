<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithWebsiteMedia;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for public "News & Health" articles.
 */
class WebsiteArticleController extends Controller
{
    use InteractsWithWebsiteMedia;

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.articles.view');

        $query = Article::query()->with('cover:id,file_path,disk')->with('author:id,name');

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $articles = $query->latest('id')->paginate(15);

        return response()->json(array_merge($articles->toArray(), [
            'stats' => [
                'total' => Article::count(),
                'published' => Article::where('status', Article::STATUS_PUBLISHED)->count(),
                'draft' => Article::where('status', Article::STATUS_DRAFT)->count(),
            ],
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('website.articles.manage');

        $validated = $request->validate($this->rules(true));

        $article = new Article;
        $article->fill(collect($validated)->except(['cover', 'slug'])->all());
        $article->slug = $this->uniqueSlug(Article::class, Str::slug($validated['slug'] ?? $validated['title']));
        $article->cover_media_id = $this->resolveMediaId($request, $this->media, 'cover', null);
        $article->author_id = $request->user()->id;
        $this->applyStatus($article);
        $article->save();

        return response()->json([
            'message' => 'Article created successfully.',
            'data' => $article->load('cover'),
        ], 201);
    }

    public function show(Article $article): JsonResponse
    {
        $this->authorize('website.articles.view');

        return response()->json(['data' => $article->load(['cover', 'author:id,name'])]);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $this->authorize('website.articles.manage');

        $validated = $request->validate($this->rules());

        $article->fill(collect($validated)->except(['cover', 'slug'])->all());

        if (! empty($validated['slug']) || ! empty($validated['title'])) {
            $article->slug = $this->uniqueSlug(
                Article::class,
                Str::slug($validated['slug'] ?? $validated['title']),
                $article->id,
            );
        }

        $previousMediaId = $article->cover_media_id;
        $article->cover_media_id = $this->resolveMediaId($request, $this->media, 'cover', $article->cover_media_id, 'remove_cover');
        $this->applyStatus($article);
        $article->save();

        return response()->json([
            'message' => 'Article updated successfully.',
            'data' => $article->fresh('cover'),
            'media_cleanup' => $this->cleanupReplacedMedia(
                $this->media,
                $previousMediaId,
                $article->cover_media_id,
            ),
        ]);
    }

    public function destroy(Article $article): JsonResponse
    {
        $this->authorize('website.articles.manage');

        $mediaId = $article->cover_media_id;
        $article->delete();

        return response()->json([
            'message' => 'Article deleted successfully.',
            'media_cleanup' => $this->media->cleanupIfUnused($mediaId),
        ]);
    }

    public function publish(Article $article): JsonResponse
    {
        $this->authorize('website.articles.publish');

        $article->update([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => $article->published_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Article published successfully.',
            'data' => $article->fresh('cover'),
        ]);
    }

    public function unpublish(Article $article): JsonResponse
    {
        $this->authorize('website.articles.publish');

        $article->update(['status' => Article::STATUS_DRAFT]);

        return response()->json([
            'message' => 'Article unpublished successfully.',
            'data' => $article->fresh('cover'),
        ]);
    }

    private function applyStatus(Article $article): void
    {
        if ($article->status === Article::STATUS_PUBLISHED) {
            $article->published_at = $article->published_at ?? now();
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $creating = false): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => [$presence, 'string'],
            'cover' => $this->imageRules(),
            'remove_cover' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'status' => ['sometimes', Rule::in(Article::STATUSES)],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }
}
