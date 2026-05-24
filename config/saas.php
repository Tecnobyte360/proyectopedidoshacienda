<?php

/**
 * Configuración del modelo SaaS de Kivox (TecnoByte360).
 *
 * Estas son las credenciales del DUEÑO de Kivox para cobrar las mensualidades
 * a los tenants. NO confundir con las credenciales Wompi de cada tenant
 * (que están en Tenant::wompi_config y sirven para cobrar a sus clientes finales).
 */
return [
    'wompi' => [
        'modo'             => env('SAAS_WOMPI_MODO', 'sandbox'), // sandbox | produccion
        'public_key'       => env('SAAS_WOMPI_PUBLIC_KEY'),
        'private_key'      => env('SAAS_WOMPI_PRIVATE_KEY'),
        'integrity_secret' => env('SAAS_WOMPI_INTEGRITY_SECRET'),
        'events_secret'    => env('SAAS_WOMPI_EVENTS_SECRET'),
        'redirect_url'     => env('SAAS_WOMPI_REDIRECT_URL', 'https://admin.kivox.co/billing/gracias'),
    ],

    'empresa' => [
        'razon_social' => env('SAAS_RAZON_SOCIAL', 'TecnoByte360 SAS'),
        'nit'          => env('SAAS_NIT'),
        'telefono'     => env('SAAS_TELEFONO'),
        'email'        => env('SAAS_EMAIL', 'comercial@tecnobyte360.com'),
    ],
];
