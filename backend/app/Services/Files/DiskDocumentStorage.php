<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores documents on a Laravel filesystem disk. Works with the local disk and
 * any S3-compatible object store, so the application is not tied to a single
 * cloud provider.
 */
class DiskDocumentStorage implements DocumentStorage
{
    public function __construct(private readonly string $disk) {}

    public function store(UploadedFile $file, string $directory, string $filename): string
    {
        return (string) $file->storeAs($directory, $filename, $this->disk);
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk)->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    public function download(string $path, string $downloadName): Response
    {
        return Storage::disk($this->disk)->download($path, $downloadName);
    }
}
