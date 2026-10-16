<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\Website\WebsiteMediaService;
use Illuminate\Http\Request;

trait InteractsWithWebsiteMedia
{
    /**
     * Validation rules for an uploaded website image.
     *
     * @return array<int, mixed>
     */
    protected function imageRules(): array
    {
        return [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp,gif',
            'max:'.(int) config('documents.max_size_kb', 10240),
        ];
    }

    /**
     * Resolve the media asset id for an upload field, honouring an optional
     * removal flag, or keep the current value when nothing changed.
     */
    protected function resolveMediaId(
        Request $request,
        WebsiteMediaService $media,
        string $field,
        ?int $current,
        ?string $removeFlag = null,
    ): ?int {
        if ($request->hasFile($field)) {
            return $media->store($request->file($field), $request->user()?->id)->id;
        }

        if ($removeFlag !== null && $request->boolean($removeFlag)) {
            return null;
        }

        return $current;
    }

    /**
     * Build a unique slug from an optional provided value or the given title.
     */
    protected function uniqueSlug(string $modelClass, string $base, ?int $ignoreId = null): string
    {
        $base = $base !== '' ? $base : 'item';
        $slug = $base;
        $suffix = 2;

        while (
            $modelClass::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
