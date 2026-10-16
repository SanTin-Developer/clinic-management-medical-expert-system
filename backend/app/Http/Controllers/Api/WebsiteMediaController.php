<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management of the website media library.
 */
class WebsiteMediaController extends Controller
{
    public function __construct(private readonly WebsiteMediaService $media) {}

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

        return response()->json($query->latest('id')->paginate(24));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('website.media.manage');

        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) config('documents.max_size_kb', 10240)],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $this->media->store($request->file('file'), $request->user()->id, $validated['name'] ?? null);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'data' => $asset,
        ], 201);
    }

    public function destroy(MediaAsset $mediaAsset): JsonResponse
    {
        $this->authorize('website.media.manage');

        $this->media->delete($mediaAsset);

        return response()->json(['message' => 'File deleted successfully.']);
    }
}
