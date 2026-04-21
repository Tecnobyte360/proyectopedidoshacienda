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

    'api' => [
        'key' => env('API_KEY'),
    ],

    'hostinger' => [
        'api_key'    => env('HOSTINGER_API_KEY'),
        'domain'     => env('HOSTINGER_DOMAIN', 'tecnobyte360.com'),
        'server_ip'  => env('HOSTINGER_SERVER_IP'), // IP del VPS donde apuntan los subdominios
        'ttl'        => (int) env('HOSTINGER_DNS_TTL', 300),
    ],

    'certbot' => [
        'email' => env('CERTBOT_EMAIL', 'admin@tecnobyte360.com'),
    ],

];
