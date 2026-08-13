<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * 📚 Documentación de la API (Swagger / OpenAPI). Sirve:
 *   GET /api/docs          → página Swagger UI (para integradores)
 *   GET /api/openapi.json  → especificación OpenAPI 3.0
 * El integrador abre /api/docs, pulsa "Authorize", pega su api_key y prueba.
 */
class ApiDocsController extends Controller
{
    /** Especificación OpenAPI 3.0 de la API pública de KIVOX. */
    public function openapi(): JsonResponse
    {
        $base = rtrim(config('app.url'), '/');

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => 'KIVOX — API de WhatsApp',
                'version'     => '1.0.0',
                'description' => "API para que sistemas externos envíen mensajes por WhatsApp a través de KIVOX.\n\n"
                    . "**Autenticación:** cada tenant tiene su propia `api_key`. Envíala en el header "
                    . "`X-API-KEY`. El mensaje sale desde el número de WhatsApp de ese tenant.",
            ],
            'servers' => [['url' => $base, 'description' => 'Producción']],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in'   => 'header',
                        'name' => 'X-API-KEY',
                        'description' => 'API key del tenant (empieza por kvx_...).',
                    ],
                ],
            ],
            'security' => [['ApiKeyAuth' => []]],
            'paths' => [
                '/api/v1/whatsapp/plantillas' => [
                    'get' => [
                        'summary'    => 'Listar plantillas del tenant',
                        'description'=> 'Devuelve las plantillas disponibles (nombre, idioma, estado y vista previa).',
                        'tags'       => ['WhatsApp'],
                        'responses'  => [
                            '200' => [
                                'description' => 'Lista de plantillas',
                                'content' => ['application/json' => ['example' => [
                                    'ok' => true,
                                    'data' => [[
                                        'nombre' => 'bienvenida_cliente',
                                        'idioma' => 'es',
                                        'categoria' => 'MARKETING',
                                        'estado' => 'aprobada',
                                        'vista_previa' => 'Hola {{1}}, bienvenido a {{2}} 🍽️',
                                    ]],
                                ]]],
                            ],
                            '401' => ['description' => 'API key inválida o ausente'],
                        ],
                    ],
                ],
                '/api/v1/whatsapp/plantilla' => [
                    'post' => [
                        'summary'    => 'Enviar plantilla a un cliente',
                        'description'=> 'Envía una plantilla aprobada a un número específico. Las variables reemplazan {{1}}, {{2}}, ... en orden.',
                        'tags'       => ['WhatsApp'],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['telefono', 'plantilla'],
                                    'properties' => [
                                        'telefono'  => ['type' => 'string',  'example' => '573001234567', 'description' => 'Número con indicativo, solo dígitos.'],
                                        'plantilla' => ['type' => 'string',  'example' => 'bienvenida_cliente', 'description' => 'Nombre EXACTO de la plantilla aprobada.'],
                                        'idioma'    => ['type' => 'string',  'example' => 'es', 'description' => 'Código de idioma (opcional).'],
                                        'variables' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['Juan', 'Guayacán Café'], 'description' => 'Valores para {{1}}, {{2}}, ...'],
                                        'imagen_url'=> ['type' => 'string', 'example' => null, 'description' => 'Opcional: si la plantilla tiene header de imagen.'],
                                        'boton_url' => ['type' => 'string', 'example' => null, 'description' => 'Opcional: parámetro del botón URL dinámico.'],
                                    ],
                                ],
                            ]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Enviada',
                                'content' => ['application/json' => ['example' => [
                                    'ok' => true, 'message' => 'Plantilla enviada.',
                                    'telefono' => '573001234567', 'plantilla' => 'bienvenida_cliente', 'idioma' => 'es',
                                ]]],
                            ],
                            '401' => ['description' => 'API key inválida'],
                            '422' => ['description' => 'Datos inválidos o plantilla no enviable'],
                        ],
                    ],
                ],
            ],
        ];

        return response()->json($spec, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Página Swagger UI. */
    public function ui(): Response
    {
        $specUrl = rtrim(config('app.url'), '/') . '/api/openapi.json';

        $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>KIVOX — API WhatsApp · Documentación</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"/>
  <style>body{margin:0;background:#fafafa} .topbar{display:none}</style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = () => {
      window.ui = SwaggerUIBundle({
        url: "{$specUrl}",
        dom_id: "#swagger-ui",
        deepLinking: true,
        persistAuthorization: true,
        presets: [SwaggerUIBundle.presets.apis],
      });
    };
  </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
