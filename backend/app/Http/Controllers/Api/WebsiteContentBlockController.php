<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithWebsiteMedia;
use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteContentBlockController extends Controller
{
    use InteractsWithWebsiteMedia;

    /** Default content-block group used when a bulk payload omits `group`. */
    private const DEFAULT_GROUP = 'about';

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.settings.view');

        $group = $request->query('group');
        $query = ContentBlock::with(['image'])->orderBy('group')->orderBy('key');

        if ($group) {
            $query->where('group', $group);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(ContentBlock $contentBlock): JsonResponse
    {
        $this->authorize('website.settings.view');

        return response()->json([
            'data' => $contentBlock->load(['image']),
        ]);
    }

    public function update(Request $request, ContentBlock $contentBlock): JsonResponse
    {
        $this->authorize('website.settings.update');

        $request->merge(
            collect($request->except(['image']))
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all()
        );

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'data' => ['nullable'],
            'image' => $this->imageRules(),
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $previousMediaId = $contentBlock->image_media_id;
        $contentBlock->image_media_id = $this->resolveMediaId(
            $request,
            $this->media,
            'image',
            $contentBlock->image_media_id,
            'remove_image'
        );

        $contentBlock->fill(collect($validated)->except(['image', 'remove_image'])->all());
        $contentBlock->updated_by = $request->user()->id;
        $contentBlock->save();

        return response()->json([
            'message' => 'Content block updated successfully.',
            'data' => $contentBlock->fresh(['image']),
            'media_cleanup' => $this->cleanupReplacedMedia(
                $this->media,
                $previousMediaId,
                $contentBlock->image_media_id,
            ),
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('website.settings.update');

        $validated = $request->validate([
            'blocks' => ['required', 'array'],
            'blocks.*.key' => ['required', 'string'],
            'blocks.*.group' => ['nullable', 'string', 'max:63'],
            'blocks.*.title' => ['nullable', 'string'],
            'blocks.*.body' => ['nullable', 'string'],
            'blocks.*.data' => ['nullable'],
        ]);

        foreach ($validated['blocks'] as $block) {
            // Only the fields present in the payload are written, so an image
            // block keeps its text and a text block keeps its list.
            $attributes = ['updated_by' => $request->user()->id];

            foreach (['title', 'body', 'data'] as $field) {
                if (array_key_exists($field, $block)) {
                    $attributes[$field] = $block[$field];
                }
            }

            ContentBlock::updateOrCreate(
                ['key' => $block['key'], 'group' => $block['group'] ?? self::DEFAULT_GROUP],
                $attributes
            );
        }

        return response()->json([
            'message' => 'Content blocks updated successfully.',
        ]);
    }
}
