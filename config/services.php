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
    | Gravatar (optional external avatar service)
    |--------------------------------------------------------------------------
    |
    | When enabled, users without a locally uploaded avatar will have their
    | profile image resolved from Gravatar using the SHA-256 hash of their
    | email address. The email is never sent to Gravatar in plain text and the
    | Gravatar image is never stored on this server. Set GRAVATAR_ENABLED=false
    | to disable the feature entirely and always fall back to the default
    | generated avatar.
    |
    */
    'gravatar' => [
        'enabled' => env('GRAVATAR_ENABLED', true),
        'size' => (int) env('GRAVATAR_SIZE', 80),
        // "404" makes Gravatar respond with a 404 when the email has no image,
        // which the frontend uses to fall back to the app's default avatar.
        'default' => env('GRAVATAR_DEFAULT', '404'),
    ],

];
