<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal Medical Expert System (FastAPI)
    |--------------------------------------------------------------------------
    |
    | The expert system runs on the private Docker network and is only ever
    | reached through this backend. Never expose its port publicly.
    |
    */

    'expert_system' => [
        'url' => env('EXPERT_SYSTEM_URL', 'http://expert-system:8001'),
        'timeout' => (int) env('EXPERT_SYSTEM_TIMEOUT', 5),
        'connect_timeout' => (int) env('EXPERT_SYSTEM_CONNECT_TIMEOUT', 2),
        'api_key' => env('EXPERT_SYSTEM_API_KEY'),
    ],

];
