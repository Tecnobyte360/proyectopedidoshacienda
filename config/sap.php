<?php

/*
|--------------------------------------------------------------------------
| Conexión a SAP Business One vía Service Layer (OData)
|--------------------------------------------------------------------------
| Usada por el Asistente IA + SAP (App\Services\Sap\*).
|
| 'mode':
|   - 'direct' → KIVOX llama directo al Service Layer (requiere que la IP del
|                servidor esté autorizada en el firewall de SAP/Heinsohn).
|   - 'bridge' → KIVOX llama a un agente/puente en la red autorizada, que a su
|                vez consulta el Service Layer (las credenciales SAP no salen
|                de la red del cliente). En este modo se usa 'bridge_url'.
|
| Las credenciales NO deben quedar en el repo: van en el .env del servidor.
*/

return [

    /*
    | Conexión GLOBAL de respaldo (fallback). En producción cada tenant tiene su
    | propia conexión en la tabla `sap_tenant_configs` (ver SapTenantConfig).
    | Este bloque se usa cuando un tenant no tiene conexión propia (p.ej. pruebas
    | locales de un solo cliente).
    */
    'connection' => [
        'mode'       => env('SAP_SL_MODE', 'direct'),
        'url'        => env('SAP_SL_URL', 'https://vm-hbt-hm7.heinsohncloud.com.co:50000'),
        'database'   => env('SAP_SL_COMPANY', 'PRUEBAS_DOBLAMOS_11MAR'),
        'username'   => env('SAP_SL_USER', 'manager'),
        'password'   => env('SAP_SL_PASSWORD', ''),
        'timeout'    => (int) env('SAP_SL_TIMEOUT', 30),
        'bridge_url'   => env('SAP_SL_BRIDGE_URL', ''),
        'bridge_token' => env('SAP_SL_BRIDGE_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de AGENTES (unidades activables por tenant)
    |--------------------------------------------------------------------------
    | Cada agente expone una o más herramientas (tools) a la IA. Se activan por
    | tenant en `sap_tenant_configs.agentes` (JSON con las claves activas).
    */
    'agentes' => [
        'ventas.cotizaciones' => [
            'nombre'      => 'Gestión de Cotizaciones',
            'modulo'      => 'ventas',
            'codigo'      => '1.0',
            'descripcion' => 'Analiza cotizaciones abiertas/cerradas e indica cuáles se convirtieron en pedido (ganadas) y cuáles se perdieron.',
            'tools'       => ['analizar_cotizaciones'],
        ],
        'ventas.estados_pedidos' => [
            'nombre'      => 'Análisis de Estados de Pedidos',
            'modulo'      => 'ventas',
            'codigo'      => '1.1',
            'descripcion' => 'Analiza pedidos abiertos: despacho, facturación, fechas de vencimiento y cantidades retrasadas.',
            'tools'       => ['analizar_estados_pedidos'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de PLANES (paquetes de agentes)
    |--------------------------------------------------------------------------
    | Un plan es un preset: al activarlo se habilitan sus agentes. El tenant
    | también puede activar/desactivar agentes individuales.
    */
    'planes' => [
        'ventas' => [
            'nombre'  => 'Plan Ventas',
            'icono'   => 'fa-solid fa-cart-shopping',
            'agentes' => ['ventas.cotizaciones', 'ventas.estados_pedidos'],
        ],
        // Próximas fases:
        // 'compras' => ['nombre' => 'Plan Compras', 'agentes' => [...]],
    ],
];
