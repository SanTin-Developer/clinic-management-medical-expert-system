<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithWebsiteMedia;
use App\Http\Controllers\Controller;
use App\Models\PublicPage;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for CMS-managed informational pages.
 */
class WebsitePageController extends Controller
{
    use InteractsWithWebsiteMedia;

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.pages.view');

        $query = PublicPage::query()->with('cover:id,file_path,disk');

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $pages = $query->orderByDesc('is_system')->orderBy('title')->paginate(15);

        return response()->json(array_merge($pages->toArray(), [
            'stats' => [
                'total' => PublicPage::count(),
                'published' => PublicPage::where('status', PublicPage::STATUS_PUBLISHED)->count(),
                'draft' => PublicPage::where('status', PublicPage::STATUS_DRAFT)->count(),
            ],
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('website.pages.manage');

        $validated = $request->validate($this->rules(true));

        $page = new PublicPage;
        $page->fill(collect($validated)->except(['cover', 'slug'])->all());
        $page->slug = $this->uniqueSlug(PublicPage::class, Str::slug($validated['slug'] ?? $validated['title']));
        $page->cover_media_id = $this->resolveMediaId($request, $this->media, 'cover', null);
        $page->updated_by = $request->user()->id;
        $this->applyPublishedAt($page);
        $page->save();

        return response()->json([
            'message' => 'Page created successfully.',
            'data' => $page->load('cover'),
        ], 201);
    }

    public function show(PublicPage $page): JsonResponse
    {
        $this->authorize('website.pages.view');

        return response()->json(['data' => $page->load('cover')->makeVisible('internal_notes')]);
    }

    public function update(Request $request, PublicPage $page): JsonResponse
    {
        $this->authorize('website.pages.manage');

        $validated = $request->validate($this->rules());

        $page->fill(collect($validated)->except(['cover', 'slug'])->all());

        if (! empty($validated['slug']) || ! empty($validated['title'])) {
            $page->slug = $this->uniqueSlug(
                PublicPage::class,
                Str::slug($validated['slug'] ?? $validated['title']),
                $page->id,
            );
        }

        $previousMediaId = $page->cover_media_id;
        $page->cover_media_id = $this->resolveMediaId($request, $this->media, 'cover', $page->cover_media_id, 'remove_cover');
        $page->updated_by = $request->user()->id;
        $this->applyPublishedAt($page);
        $page->save();

        return response()->json([
            'message' => 'Page updated successfully.',
            'data' => $page->fresh('cover')->makeVisible('internal_notes'),
            'media_cleanup' => $this->cleanupReplacedMedia(
                $this->media,
                $previousMediaId,
                $page->cover_media_id,
            ),
        ]);
    }

    public function destroy(PublicPage $page): JsonResponse
    {
        $this->authorize('website.pages.manage');

        if ($page->is_system) {
            return response()->json([
                'message' => 'System pages cannot be deleted. Unpublish it instead.',
            ], 422);
        }

        $mediaId = $page->cover_media_id;
        $page->delete();

        return response()->json([
            'message' => 'Page deleted successfully.',
            'media_cleanup' => $this->media->cleanupIfUnused($mediaId),
        ]);
    }

    public function publish(PublicPage $page): JsonResponse
    {
        $this->authorize('website.pages.publish');

        $page->update([
            'status' => PublicPage::STATUS_PUBLISHED,
            'published_at' => $page->published_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Page published successfully.',
            'data' => $page->fresh('cover'),
        ]);
    }

    public function unpublish(PublicPage $page): JsonResponse
    {
        $this->authorize('website.pages.publish');

        $page->update(['status' => PublicPage::STATUS_DRAFT]);

        return response()->json([
            'message' => 'Page unpublished successfully.',
            'data' => $page->fresh('cover'),
        ]);
    }

    private function applyPublishedAt(PublicPage $page): void
    {
        if ($page->status === PublicPage::STATUS_PUBLISHED) {
            $page->published_at = $page->published_at ?? now();
        } else {
            $page->published_at = null;
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
            'body' => ['nullable', 'string'],
            // Policy text is rendered as text (not HTML) by the public page.
            'policy_content' => ['nullable', 'array'],
            'policy_content.en' => ['nullable', 'array'],
            'policy_content.en.title' => ['nullable', 'string', 'max:255'],
            'policy_content.en.introduction' => ['nullable', 'string', 'max:5000'],
            'policy_content.en.sections' => ['nullable', 'array', 'max:30'],
            'policy_content.km' => ['nullable', 'array'],
            'policy_content.km.title' => ['nullable', 'string', 'max:255'],
            'policy_content.km.introduction' => ['nullable', 'string', 'max:5000'],
            'policy_content.km.sections' => ['nullable', 'array', 'max:30'],
            'policy_content.*.sections.*.heading' => ['required_with:policy_content.*.sections', 'string', 'max:255'],
            'policy_content.*.sections.*.body' => ['required_with:policy_content.*.sections', 'string', 'max:20000'],
            'policy_content.*.sections.*.order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'policy_content.*.sections.*.visible' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'cover' => $this->imageRules(),
            'remove_cover' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(PublicPage::STATUSES)],
            'show_in_footer' => ['sometimes', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
        ];
    }
}
