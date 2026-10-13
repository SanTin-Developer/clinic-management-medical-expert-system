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
