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
    | Website checks: how politely and how long LeadBreave crawls a lead's site.
    */
    'website_check' => [
        'host_delay_ms' => (int) env('WEBSITE_CHECK_HOST_DELAY_MS', 1000),
        'budget_seconds' => (int) env('WEBSITE_CHECK_BUDGET_SECONDS', 45),
        'timeout_seconds' => (int) env('WEBSITE_CHECK_TIMEOUT_SECONDS', 10),
        'max_bytes' => 2 * 1024 * 1024,
        'per_domain_per_minute' => 6,
    ],

];
