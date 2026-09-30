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
    | SMS Gateway
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "log" (writes a masked line to the logs, never the code or the full
    | number; development and tests) and "null" (discards the message). No real provider is
    | integrated yet (pending product decision): an unknown driver stops the application at boot.
    |
    | In production and staging, the "log" driver is refused at boot (no SMS would ever be
    | delivered) unless SMS_ALLOW_LOG_DRIVER=true: an explicit, documented override for a
    | pre-production that has no SMS provider yet. Never set it on the real production.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'allow_log_driver' => (bool) env('SMS_ALLOW_LOG_DRIVER', false),
    ],

];
