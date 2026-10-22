<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MediaAssetDeletionException;
use App\Exceptions\MediaAssetInUseException;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Website\MediaAssetUsageService;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management of the website media library.
 */
class WebsiteMediaController extends Controller
{
    public function __construct(
        private readonly WebsiteMediaService $media,
        private readonly MediaAssetUsageService $usage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.media.manage');

        $query = MediaAsset::query()->with('uploader:id,name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->query('type')) {
            $query->where('mime_type', 'like', $type.'/%');
        }

        $assets = $query->latest('id')->paginate(24);
        $references = $this->usage->forCollection($assets->getCollection());

        $assets->getCollection()->transform(function (MediaAsset $asset) use ($references): MediaAsset {
            $assetReferences = $references[$asset->id] ?? [];

            $asset->setAttribute('usage_count', count($assetReferences));
            $asset->setAttribute('usage_references', $assetReferences);

            return $asset;
        });

        return response()->json($assets);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('website.media.manage');

        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) config('documents.max_size_kb', 10240)],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $this->media->store($request->file('file'), $request->user()->id, $validated['name'] ?? null);
        $asset->setAttribute('usage_count', 0);
        $asset->setAttribute('usage_references', []);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'data' => $asset,
        ], 201);
    }

    public function destroy(MediaAsset $mediaAsset): JsonResponse
    {
        $this->authorize('website.media.manage');

        try {
            $this->media->delete($mediaAsset);
        } catch (MediaAssetInUseException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'references' => $exception->references,
            ], 409);
        } catch (MediaAssetDeletionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }

        return response()->json(['message' => 'File permanently deleted.']);
    }
}
