<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithWebsiteMedia;
use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for the public facilities gallery.
 */
class WebsiteGalleryController extends Controller
{
    use InteractsWithWebsiteMedia;

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.view');

        $query = GalleryItem::query()->with('image:id,file_path,disk');

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        return response()->json($query->ordered()->paginate(18));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('website.gallery.manage');

        $validated = $request->validate($this->rules(true));

        $item = new GalleryItem;
        $item->fill(collect($validated)->except(['image', 'remove_image'])->all());
        $item->image_media_id = $this->resolveMediaId($request, $this->media, 'image', null);
        $item->save();

        return response()->json([
            'message' => 'Gallery item created successfully.',
            'data' => $item->load('image'),
        ], 201);
    }

    public function show(GalleryItem $galleryItem): JsonResponse
    {
        $this->authorize('website.view');

        return response()->json(['data' => $galleryItem->load('image')]);
    }

    public function update(Request $request, GalleryItem $galleryItem): JsonResponse
    {
        $this->authorize('website.gallery.manage');

        $validated = $request->validate($this->rules());

        $galleryItem->fill(collect($validated)->except(['image', 'remove_image'])->all());
        $previousMediaId = $galleryItem->image_media_id;
        $galleryItem->image_media_id = $this->resolveMediaId($request, $this->media, 'image', $galleryItem->image_media_id, 'remove_image');
        $galleryItem->save();

        return response()->json([
            'message' => 'Gallery item updated successfully.',
            'data' => $galleryItem->fresh('image'),
            'media_cleanup' => $this->cleanupReplacedMedia(
                $this->media,
                $previousMediaId,
                $galleryItem->image_media_id,
            ),
        ]);
    }

    public function destroy(GalleryItem $galleryItem): JsonResponse
    {
        $this->authorize('website.gallery.manage');

        $mediaId = $galleryItem->image_media_id;
        $galleryItem->delete();

        return response()->json([
            'message' => 'Gallery item deleted successfully.',
            'media_cleanup' => $this->media->cleanupIfUnused($mediaId),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $creating = false): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'image' => $creating
                ? ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) config('documents.max_size_kb', 10240)]
                : $this->imageRules(),
            'remove_image' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
