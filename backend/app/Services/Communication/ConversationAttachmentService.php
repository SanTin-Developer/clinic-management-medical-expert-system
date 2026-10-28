<?php

namespace App\Services\Communication;

use App\Models\Conversation;
use App\Services\Files\CloudinaryClient;
use App\Services\Files\FileScanner;
use App\Services\Files\VideoInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and stores chat attachments.
 *
 * Validation is content-driven: the client-supplied MIME type and filename are
 * never trusted. Images are checked with getimagesize(), videos are parsed for
 * their real container/codec, and regular documents are checked with finfo
 * against the extension's expected MIME list.
 */
class ConversationAttachmentService
{
    public function __construct(
        private readonly FileScanner $scanner,
        private readonly VideoInspector $videos,
        private readonly CloudinaryClient $cloudinary,
    ) {}

    /**
     * @return array{kind: string, path: string, name: string, mime: string, size: int}
     */
    public function store(UploadedFile $file, Conversation $conversation): array
    {
        $originalName = $this->sanitizeName((string) $file->getClientOriginalName());
        $extension = $this->extension($originalName);
        $detectedMime = $this->detectMime($file->getRealPath());
        $kind = $this->classify($extension, $detectedMime);

        $this->validateExtension($extension, $kind);
        $this->validateSize($file, $kind);

        $mime = match ($kind) {
            'image' => $this->validateImage($file, $extension),
            'video' => $this->validateVideo($file, $extension),
            default => $this->validateFile($file, $extension, $detectedMime),
        };

        $this->scan($file, $originalName);

        if ($this->cloudinary->isConfigured()) {
            $folder = trim((string) config('services.cloudinary.folder', 'Clinic Management System and Medical Expert systems').'/chat-attachments/'.$conversation->id, '/');
            $publicId = Str::uuid()->toString();
            $stored = $this->cloudinary->upload($file, $folder, $publicId);

            return [
                'kind' => $kind,
                'path' => $stored->path,
                'name' => $originalName,
                'mime' => $mime,
                'size' => (int) ($file->getSize() ?: 0),
            ];
        }

        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs(
            "chat-attachments/{$conversation->id}",
            $filename,
            $this->disk(),
        );

        if (! is_string($path) || $path === '') {
            $this->fail('The attachment could not be saved.');
        }

        return [
            'kind' => $kind,
            'path' => $path,
            'name' => $originalName,
            'mime' => $mime,
            'size' => (int) ($file->getSize() ?: 0),
        ];
    }

    public function delete(string $path): void
    {
        if ($this->cloudinary->isConfigured() && str_starts_with($path, 'http')) {
            $cloudinaryStorage = new \App\Services\Files\CloudinaryStorage($this->cloudinary, (string) config('services.cloudinary.folder', 'Clinic Management System and Medical Expert systems'));
            $cloudinaryStorage->delete($path);
            return;
        }

        try {
            Storage::disk($this->disk())->delete($path);
        } catch (\Throwable) {
            // ignore
        }
    }

    public function disk(): string
    {
        // Cloudinary assets are stored as absolute URLs, so no filesystem disk
        // is involved. Legacy/local attachments always live on the configured
        // local disk; never return a pseudo-disk such as "cloudinary".
        return (string) config('communication.chat.attachments.disk', 'local');
    }

    private function classify(string $extension, ?string $detectedMime): string
    {
        $mime = strtolower((string) $detectedMime);

        $videoExtensions = $this->configList('video_extensions');
        $videoMimes = $this->configList('video_mime_types');
        $imageExtensions = $this->configList('image_extensions');
        $imageMimes = $this->configList('image_mime_types');

        if (in_array($extension, $videoExtensions, true) || in_array($mime, $videoMimes, true)) {
            return 'video';
        }

        if (in_array($extension, $imageExtensions, true) || in_array($mime, $imageMimes, true)) {
            return 'image';
        }

        return 'file';
    }

    private function validateExtension(string $extension, string $kind): void
    {
        $denied = array_map('strtolower', (array) config('documents.denied_extensions', []));
        $allowed = $this->configList("{$kind}_extensions");

        if ($extension === '' || in_array($extension, $denied, true)) {
            $this->fail('This file type is not allowed.');
        }

        if (! in_array($extension, $allowed, true)) {
            $this->fail('This file type is not allowed.');
        }
    }

    private function validateSize(UploadedFile $file, string $kind): void
    {
        $limitKb = match ($kind) {
            'image' => (int) config('communication.chat.attachments.max_image_size_kb', 10240),
            'video' => (int) config('communication.chat.attachments.max_video_size_kb', 51200),
            default => (int) config('communication.chat.attachments.max_file_size_kb', 20480),
        };

        $limitMb = (int) round($limitKb / 1024);

        if ((int) $file->getSize() > $limitKb * 1024) {
            $this->fail(match ($kind) {
                'image' => "Images must be {$limitMb} MB or smaller.",
                'video' => "Videos must be {$limitMb} MB or smaller.",
                default => "Files must be {$limitMb} MB or smaller.",
            });
        }
    }

    private function validateImage(UploadedFile $file, string $extension): string
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        if ($info === false) {
            $this->fail('The image could not be read.');
        }

        $actual = strtolower((string) ($info['mime'] ?? ''));

        $expected = match ($extension) {
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
            default => [],
        };

        if ($expected === [] || ! in_array($actual, $expected, true)) {
            $this->fail('The image content does not match its extension.');
        }

        return $actual;
    }

    private function validateVideo(UploadedFile $file, string $extension): string
    {
        $path = $file->getRealPath();
        $inspection = $path ? $this->videos->inspect($path) : null;

        if ($inspection === null) {
            $this->fail('The video could not be verified as a valid MP4, WebM or MOV file.');
        }

        if ($extension === 'webm' && $inspection['container'] !== 'webm') {
            $this->fail('The video content does not match its .webm extension.');
        }

        if (in_array($extension, ['mp4', 'mov'], true) && $inspection['container'] === 'webm') {
            $this->fail('The video content does not match its extension.');
        }

        $supported = array_filter(
            $inspection['codecs'],
            fn (string $codec) => in_array(strtolower($codec), $this->configList('video_codecs'), true),
        );

        if ($supported === []) {
            $this->fail('The video codec is not supported. Use H.264, VP8, VP9 or AV1.');
        }

        return match ($inspection['container']) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    private function validateFile(UploadedFile $file, string $extension, ?string $detectedMime): string
    {
        $expected = array_map(
            'strtolower',
            (array) config("communication.chat.attachments.file_expected_mimes.{$extension}", []),
        );

        if ($expected === []) {
            $this->fail('This file type is not allowed.');
        }

        $detected = strtolower((string) $detectedMime);

        if ($detected === '' || ! in_array($detected, $expected, true)) {
            $this->fail('The file content does not match its extension.');
        }

        return $detected;
    }

    private function scan(UploadedFile $file, string $name): void
    {
        $result = $this->scanner->scan((string) $file->getRealPath(), $name);

        if ($result->clean) {
            return;
        }

        if ($result->status === 'infected') {
            $this->fail('The file was rejected because malware was detected.');
        }

        if (! config('documents.scanner.allow_on_error', false)) {
            $this->fail('The file could not be verified as safe and was rejected.');
        }
    }

    private function detectMime(?string $path): ?string
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return $mime === false ? null : $mime;
    }

    private function sanitizeName(string $name): string
    {
        $name = str_replace(['\\', "\0"], ['/', ''], $name);
        $name = basename($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $this->fail('The file name is invalid.');
        }

        if (mb_strlen($name) > 180) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $base = pathinfo($name, PATHINFO_FILENAME);
            $name = mb_substr($base, 0, 160).($extension !== '' ? '.'.$extension : '');
        }

        return $name;
    }

    private function extension(string $name): string
    {
        $segments = explode('.', strtolower($name));

        if (count($segments) < 2) {
            $this->fail('The file must have a valid extension.');
        }

        $extension = array_pop($segments);

        if (in_array($extension, [''], true)) {
            $this->fail('The file must have a valid extension.');
        }

        return $extension;
    }

    /**
     * @return array<int, string>
     */
    private function configList(string $key): array
    {
        return array_values(array_map(
            'strtolower',
            (array) config("communication.chat.attachments.{$key}", []),
        ));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['attachment' => [$message]]);
    }
}
