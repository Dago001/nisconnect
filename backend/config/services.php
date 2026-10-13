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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'endpoint' => env('SMS_ENDPOINT'),
        'key' => env('SMS_API_KEY'),
        'sender_id' => env('SMS_SENDER_ID', 'NISconnect'),
    ],

    // Termii SMS gateway (OTP_DRIVER=termii). Sender ID must be approved by Termii.
    'termii' => [
        'base_url' => env('TERMII_BASE_URL', 'https://v3.api.termii.com'),
        'key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'NISconnect'),
        'channel' => env('TERMII_CHANNEL', 'dnd'),
    ],

    'livekit' => [
        'host' => env('LIVEKIT_HOST'),
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
    ],

    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'), // log | http
        'fcm' => [
            // Path to the Firebase service-account JSON key. Preferred: the
            // server mints its own short-lived access tokens from it, so
            // nothing expires in a day. Keep the file outside the repo.
            'credentials' => env('FCM_CREDENTIALS'),
            // Optional. Derived from the key's project id when left unset.
            'endpoint' => env('FCM_ENDPOINT'),
            // Legacy fallback: a manually pasted bearer token. Expires hourly.
            'key' => env('FCM_SERVER_KEY'),
        ],
        'apns' => [
            'endpoint' => env('APNS_ENDPOINT'),
            'key' => env('APNS_KEY'),
        ],
    ],

];
