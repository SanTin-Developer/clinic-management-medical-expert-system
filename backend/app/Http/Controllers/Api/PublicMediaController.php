<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves website media library files. Remote assets (e.g. Cloudinary) are
 * redirected; files on a local disk are streamed inline.
 */
class PublicMediaController extends Controller
{
    public function show(MediaAsset $mediaAsset): Response
    {
        if ($mediaAsset->isExternal()) {
            return redirect()->away($mediaAsset->file_path);
        }

        $disk = $mediaAsset->disk;

        abort_unless(in_array($disk, ['local', 'public', 's3'], true), 404);
        abort_unless(Storage::disk($disk)->exists($mediaAsset->file_path), 404);

        return Storage::disk($disk)->response($mediaAsset->file_path);
    }
}
