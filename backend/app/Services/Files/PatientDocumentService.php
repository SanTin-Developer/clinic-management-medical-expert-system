<?php

namespace App\Services\Files;

use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientDocumentService
{
    public function __construct(
        private readonly FileScanner $scanner,
        private readonly DocumentStorage $storage,
    ) {}

    /**
     * Validate, scan and persist an uploaded patient document.
     *
     * @return array{path: string, original_name: string, mime_type: ?string, size: int}
     */
    public function store(UploadedFile $file, Patient $patient): array
    {
        $originalName = $this->sanitizeName((string) $file->getClientOriginalName());
        $extension = $this->validateExtension($originalName);
        $this->validateContent($file, $extension);
        $this->scan($file, $originalName);

        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $this->storage->store($file, "patient-documents/{$patient->id}", $filename);

        return [
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) ($file->getSize() ?: 0),
        ];
    }

    /**
     * Strip any directory component (path traversal) and reject null bytes.
     */
    private function sanitizeName(string $name): string
    {
        $name = str_replace(['\\', "\0"], ['/', ''], $name);
        $name = basename($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $this->fail('The file name is invalid.');
        }

        return $name;
    }

    /**
     * Enforce the extension allow-list and reject double extensions that hide a
     * disallowed type (e.g. "invoice.pdf.exe") or compressed archives.
     */
    private function validateExtension(string $name): string
    {
        $lower = strtolower($name);
        $allowed = array_map('strtolower', (array) config('documents.allowed_extensions', []));
        $denied = array_map('strtolower', (array) config('documents.denied_extensions', []));

        $segments = explode('.', $lower);

        if (count($segments) < 2) {
            $this->fail('The file must have a valid extension.');
        }

        $extension = array_pop($segments);

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (in_array($segment, $denied, true)) {
                $this->fail('The file name contains a disallowed extension.');
            }
        }

        if (in_array($extension, $denied, true) || ! in_array($extension, $allowed, true)) {
            $this->fail('This file type is not allowed.');
        }

        return $extension;
    }

    /**
     * Verify the magic-byte MIME type matches the file extension.
     */
    private function validateContent(UploadedFile $file, string $extension): void
    {
        $detected = $this->detectMime($file->getRealPath());

        if ($detected === null) {
            return;
        }

        $detected = strtolower($detected);

        $dangerous = [
            'x-sharedlib', 'x-dosexec', 'x-msdownload', 'x-msdos-program',
            'x-executable', 'x-php', 'x-httpd-php', 'html', 'javascript',
            'x-sh', 'x-shellscript', 'x-bat',
        ];

        foreach ($dangerous as $needle) {
            if (str_contains($detected, $needle)) {
                $this->fail('The file content is not allowed.');
            }
        }

        $expected = $this->expectedMimes($extension);

        if ($expected !== []) {
            if (! in_array($detected, $expected, true)) {
                $this->fail('The file content does not match its extension.');
            }

            return;
        }

        if (! in_array($detected, array_map('strtolower', (array) config('documents.allowed_mime_types', [])), true)) {
            $this->fail('The file content is not an allowed type.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function expectedMimes(string $extension): array
    {
        return match ($extension) {
            'jpg', 'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/x-ole-storage', 'application/vnd.ms-office', 'application/cdfv2'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/x-zip', 'application/x-zip-compressed',
            ],
            default => [],
        };
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

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
