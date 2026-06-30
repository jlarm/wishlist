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

    'scrapingbee' => [
        'key' => env('SCRAPINGBEE_API_KEY'),
        // JS rendering helps SPA-style product pages (5 credits/call).
        'render_js' => env('SCRAPINGBEE_RENDER_JS', true),
        // Residential proxies are needed for hard bot-walls like Amazon and
        // Walmart, but cost far more credits (~25-75/call). Enable if those
        // sites still come back empty.
        'premium_proxy' => env('SCRAPINGBEE_PREMIUM_PROXY', false),
    ],

];
