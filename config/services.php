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

    // 🛡️ BUG-C4: Configuración del webhook de WhatsApp.
    // Token compartido: si está definido, se exige en cada POST al webhook.
    // IP whitelist: si está definida (CSV), solo IPs en la lista pueden entrar.
    // Rate limit: requests por minuto por IP (siempre activo).
    'whatsapp' => [
        'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'), // ej: token compartido con EstradaHub
        'allowed_ips'   => array_filter(array_map('trim', explode(',', env('WHATSAPP_WEBHOOK_ALLOWED_IPS', '')))),
        'rate_limit'    => (int) env('WHATSAPP_WEBHOOK_RATE_LIMIT', 120),
        // 🛡️ BUG-08: límites de cantidad por producto. Si se excede,
        // el pedido se deriva a humano (canal comercial).
        'max_kg_por_producto'       => (float) env('WHATSAPP_MAX_KG_PRODUCTO', 200.0),
        'max_unidades_por_producto' => (int) env('WHATSAPP_MAX_UNIDADES_PRODUCTO', 500),
    ],

];
