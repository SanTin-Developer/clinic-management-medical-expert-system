<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithWebsiteMedia;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin CRUD for public website services.
 */
class WebsiteServiceController extends Controller
{
    use InteractsWithWebsiteMedia;

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.view');

        $query = Service::query()->with('image:id,file_path,disk');

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        return response()->json($query->ordered()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('website.services.manage');

        $validated = $request->validate($this->rules(true));

        $service = new Service;
        $service->fill(collect($validated)->except(['image', 'slug'])->all());
        $service->slug = $this->uniqueSlug(Service::class, Str::slug($validated['slug'] ?? $validated['title']));
        $service->image_media_id = $this->resolveMediaId($request, $this->media, 'image', null);
        $service->save();

        return response()->json([
            'message' => 'Service created successfully.',
            'data' => $service->load('image'),
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('website.view');

        return response()->json(['data' => $service->load('image')]);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $this->authorize('website.services.manage');

        $validated = $request->validate($this->rules());

        $service->fill(collect($validated)->except(['image', 'slug'])->all());

        if (! empty($validated['slug']) || ! empty($validated['title'])) {
            $service->slug = $this->uniqueSlug(
                Service::class,
                Str::slug($validated['slug'] ?? $validated['title']),
                $service->id,
            );
        }

        $previousMediaId = $service->image_media_id;
        $service->image_media_id = $this->resolveMediaId($request, $this->media, 'image', $service->image_media_id, 'remove_image');
        $service->save();

        return response()->json([
            'message' => 'Service updated successfully.',
            'data' => $service->fresh('image'),
            'media_cleanup' => $this->cleanupReplacedMedia(
                $this->media,
                $previousMediaId,
                $service->image_media_id,
            ),
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('website.services.manage');

        $mediaId = $service->image_media_id;
        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully.',
            'media_cleanup' => $this->media->cleanupIfUnused($mediaId),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $creating = false): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'title_km' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'short_description_km' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'description_km' => ['nullable', 'string'],
            'image' => $this->imageRules(),
            'remove_image' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
