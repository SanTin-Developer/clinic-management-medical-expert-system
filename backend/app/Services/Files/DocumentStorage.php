<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

interface DocumentStorage
{
    /**
     * Persist the uploaded file and return its storage path/identifier.
     */
    public function store(UploadedFile $file, string $directory, string $filename): string;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    /**
     * Return a downloadable HTTP response for the stored document.
     */
    public function download(string $path, string $downloadName): Response;
}
