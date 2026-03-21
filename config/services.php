<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'agente_ia' => [
        'base_url' => env('AGENTE_IA_BASE_URL', 'http://127.0.0.1:8001'),
        'timeout_seconds' => (int) env('AGENTE_IA_TIMEOUT_SECONDS', 120),
        'connect_timeout_seconds' => (int) env('AGENTE_IA_CONNECT_TIMEOUT_SECONDS', 10),
        'usar_cloudflare_access' => filter_var(env('AGENTE_IA_USAR_CLOUDFLARE_ACCESS', false), FILTER_VALIDATE_BOOL),
        'cf_access_client_id' => env('AGENTE_IA_CF_ACCESS_CLIENT_ID'),
        'cf_access_client_secret' => env('AGENTE_IA_CF_ACCESS_CLIENT_SECRET'),
    ],

];
