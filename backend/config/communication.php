<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat
    |--------------------------------------------------------------------------
    |
    | Limits applied to staff conversations. Messages are paginated and a
    | maximum body length is enforced at the validation layer.
    |
    */

    'chat' => [
        'messages_per_page' => (int) env('CHAT_MESSAGES_PER_PAGE', 30),
        'max_message_length' => (int) env('CHAT_MAX_MESSAGE_LENGTH', 4000),

        /*
        |----------------------------------------------------------------------
        | Message attachments
        |----------------------------------------------------------------------
        |
        | Images, documents and videos are stored on a private disk and served
        | through short-lived signed URLs. Limits are expressed in kilobytes
        | (Laravel's file validation unit). The video limit is 50 MB; the
        | two-minute duration is a recommendation shown in the UI, not a hard
        | server-side limit.
        |
        */

        'attachments' => [
            'disk' => env('CHAT_ATTACHMENTS_DISK', 'local'),
            'signed_url_ttl_minutes' => (int) env('CHAT_ATTACHMENT_URL_TTL', 360),
            'recommended_video_seconds' => (int) env('CHAT_RECOMMENDED_VIDEO_SECONDS', 120),

            'max_image_size_kb' => (int) env('CHAT_MAX_IMAGE_KB', 10240),
            'max_file_size_kb' => (int) env('CHAT_MAX_FILE_KB', 20480),
            'max_video_size_kb' => (int) env('CHAT_MAX_VIDEO_KB', 51200),

            'image_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'image_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],

            'video_extensions' => ['mp4', 'webm', 'mov'],
            'video_mime_types' => ['video/mp4', 'video/webm', 'video/quicktime'],

            // Only browser-playable video codecs. HEVC/H.265 is deliberately
            // excluded because it is not broadly supported outside Safari.
            'video_codecs' => ['avc1', 'avc3', 'av01', 'vp09', 'V_VP8', 'V_VP9', 'V_AV1'],

            'file_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],

            'file_expected_mimes' => [
                'pdf' => ['application/pdf'],
                'doc' => ['application/msword', 'application/x-ole-storage', 'application/vnd.ms-office', 'application/cdfv2'],
                'docx' => [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                    'application/x-zip',
                    'application/x-zip-compressed',
                ],
                'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/vnd.ms-office', 'application/cdfv2'],
                'xlsx' => [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip',
                    'application/x-zip',
                    'application/x-zip-compressed',
                ],
                'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/vnd.ms-office', 'application/cdfv2'],
                'pptx' => [
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/zip',
                    'application/x-zip',
                    'application/x-zip-compressed',
                ],
                'txt' => ['text/plain'],
                'csv' => ['text/plain', 'text/csv', 'application/csv'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram
    |--------------------------------------------------------------------------
    |
    | Credentials and behaviour for the optional Telegram integration. When
    | disabled (or without a token) the integration is a no-op: linking is
    | refused and delivery jobs are skipped gracefully.
    |
    */

    'telegram' => [
        'enabled' => (bool) env('TELEGRAM_ENABLED', false),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),

        // Delivery mode. Exactly one of webhook / polling must be active at a
        // time; Telegram refuses getUpdates while a webhook is registered.
        // `webhook_enabled=true` means the public HTTPS webhook is authoritative
        // and the local poller disables itself. `polling_enabled` drives the
        // `telegram:poll` worker for environments without a public URL.
        'webhook_enabled' => (bool) env('TELEGRAM_WEBHOOK_ENABLED', false),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'polling_enabled' => (bool) env('TELEGRAM_POLLING_ENABLED', true),
        'poll_timeout' => (int) env('TELEGRAM_POLL_TIMEOUT', 30),
        'poll_sleep' => (int) env('TELEGRAM_POLL_SLEEP', 1),

        'link_token_ttl_minutes' => (int) env('TELEGRAM_LINK_TOKEN_TTL', 15),
        'api_base_url' => rtrim((string) env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'), '/'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 10),
    ],

];
