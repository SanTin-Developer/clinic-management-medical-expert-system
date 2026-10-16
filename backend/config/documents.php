<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documents Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used to store patient documents. Defaults to the
    | private local disk. Set to "s3" for any S3-compatible object store, or
    | "cloudinary" to use the Cloudinary adapter.
    |
    */

    'disk' => env('DOCUMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Announcement Poster Disk
    |--------------------------------------------------------------------------
    |
    | Storage driver for announcement poster/thumbnail images. Defaults to
    | Cloudinary so posters are uploaded to and served from the CDN. When
    | Cloudinary credentials are not configured the poster storage silently
    | falls back to the public disk. Set to "public" or "local" to always keep
    | posters on a local disk (useful for offline development and tests).
    |
    */

    'poster_disk' => env('ANNOUNCEMENT_POSTER_DISK', 'cloudinary'),

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Maximum accepted upload size (in kilobytes) and the strict extension /
    | MIME allow-list. A file must match both an allowed extension and an
    | allowed detected (magic-byte) MIME type to be accepted.
    |
    */

    'max_size_kb' => (int) env('DOCUMENTS_MAX_SIZE_KB', 10240),

    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'],

    'allowed_mime_types' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    /*
    |--------------------------------------------------------------------------
    | Denied Extensions
    |--------------------------------------------------------------------------
    |
    | Executables, scripts, web documents and compressed archives are rejected
    | outright, including when hidden behind a double extension such as
    | "invoice.pdf.exe". The .z/.gz/.zip families are rejected explicitly.
    |
    */

    'denied_extensions' => [
        'exe', 'dll', 'bat', 'cmd', 'com', 'cpl', 'scr', 'msi', 'jar',
        'js', 'jse', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1',
        'sh', 'bash', 'run', 'php', 'phtml', 'phar', 'py', 'rb', 'pl',
        'asp', 'aspx', 'jsp', 'htm', 'html', 'svg',
        'z', 'gz', 'tgz', 'zip', 'rar', '7z', 'tar', 'bz2', 'xz',
    ],

    /*
    |--------------------------------------------------------------------------
    | Antivirus Scanner
    |--------------------------------------------------------------------------
    |
    | The driver used to scan uploaded files. "null" performs no scanning (the
    | safe default for local development and tests). "clamav" streams the file
    | to a clamd daemon over TCP. Never point the scanner at an untrusted host.
    |
    */

    'scanner' => [
        'driver' => env('FILE_SCANNER_DRIVER', 'null'),

        'clamav' => [
            'host' => env('CLAMAV_HOST', 'clamav'),
            'port' => (int) env('CLAMAV_PORT', 3310),
            'timeout' => (int) env('CLAMAV_TIMEOUT', 10),
            'chunk_size' => 8192,
        ],
    ],

];
