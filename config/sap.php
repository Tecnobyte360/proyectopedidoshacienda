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

    'connection' => [
        'mode'       => env('SAP_SL_MODE', 'direct'),
        'url'        => env('SAP_SL_URL', 'https://vm-hbt-hm7.heinsohncloud.com.co:50000'),
        'database'   => env('SAP_SL_COMPANY', 'PRUEBAS_DOBLAMOS_11MAR'),
        'username'   => env('SAP_SL_USER', 'manager'),
        'password'   => env('SAP_SL_PASSWORD', ''),
        'timeout'    => (int) env('SAP_SL_TIMEOUT', 30),

        // Solo para mode = 'bridge'
        'bridge_url'   => env('SAP_SL_BRIDGE_URL', ''),
        'bridge_token' => env('SAP_SL_BRIDGE_TOKEN', ''),
    ],

    /*
    | Módulos del asistente que están habilitados. Se irán activando por fases.
    | Cada módulo aporta sus propias herramientas (tools) a la IA.
    */
    'modulos' => [
        'ventas'  => true,
        'compras' => false,
    ],
];
