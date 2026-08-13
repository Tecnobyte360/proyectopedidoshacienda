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
    public function openapi(\Illuminate\Http\Request $request): JsonResponse
    {
        // 🌐 El "server" es el host DESDE DONDE se abre la doc (el subdominio del
        //    tenant, ej. https://doblamos-sas.kivox.co), no un dominio fijo.
        $base = rtrim($request->getSchemeAndHttpHost(), '/');

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => 'KIVOX — API',
                'version'     => '1.0.0',
                'description' => "API de KIVOX para integrar otros sistemas: enviar plantillas de WhatsApp, y gestionar productos, catálogo, promociones y zonas.\n\n"
                    . "**Autenticación (login requerido):**\n"
                    . "1. Llama `POST /api/v1/login` con tu email y contraseña → recibes un `token`.\n"
                    . "2. Pulsa **Authorize** 🔒 arriba y pega el token (sin la palabra Bearer).\n"
                    . "3. Todas las peticiones van con `Authorization: Bearer <token>` y operan sobre TU empresa.",
            ],
            'servers' => [['url' => $base, 'description' => 'Tu empresa (' . $request->getHost() . ')']],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type'   => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Token que devuelve POST /api/v1/login.',
                    ],
                ],
            ],
            'security' => [['BearerAuth' => []]],
            'paths' => [
                // ══════════════ LOGIN ══════════════
                '/api/v1/login' => [
                    'post' => [
                        'summary'  => 'Login — obtener token',
                        'tags'     => ['Autenticación'],
                        'security' => [], // público
                        'requestBody' => ['required' => true, 'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email'    => ['type' => 'string', 'example' => 'integracion@guayacancafe.com'],
                                    'password' => ['type' => 'string', 'example' => '••••••••'],
                                ],
                            ],
                        ]]],
                        'responses' => [
                            '200' => ['description' => 'Token emitido', 'content' => ['application/json' => ['example' => [
                                'ok' => true, 'token' => '12|abcdef...', 'token_type' => 'Bearer',
                                'tenant' => ['id' => 5, 'nombre' => 'Guayacán Café', 'slug' => 'guayacan-cafe'],
                            ]]]],
                            '401' => ['description' => 'Credenciales incorrectas'],
                            '403' => ['description' => 'Usuario sin empresa / inactivo'],
                        ],
                    ],
                ],
                '/api/v1/logout' => [
                    'post' => ['summary' => 'Logout — revocar token', 'tags' => ['Autenticación'], 'responses' => ['200' => ['description' => 'Sesión cerrada']]],
                ],
                '/api/v1/yo' => [
                    'get' => ['summary' => 'Ver mi token/empresa', 'tags' => ['Autenticación'], 'responses' => ['200' => ['description' => 'Datos del token']]],
                ],

                // ══════════════ MENSAJES (WhatsApp) ══════════════
                '/api/v1/whatsapp/plantillas' => [
                    'get' => [
                        'summary'     => 'Listar plantillas disponibles',
                        'description' => 'Devuelve las plantillas del tenant (nombre, idioma, estado APROBADA/PENDIENTE, y vista previa con {{1}}, {{2}}...). Úsalas para saber qué puedes enviar.',
                        'tags'        => ['Mensajes'],
                        'responses'   => [
                            '200' => ['description' => 'Lista de plantillas', 'content' => ['application/json' => ['example' => [
                                'ok' => true,
                                'data' => [['nombre' => 'bienvenida_cliente', 'idioma' => 'es', 'categoria' => 'MARKETING', 'estado' => 'aprobada', 'vista_previa' => 'Hola {{1}}, bienvenido a {{2}}']],
                            ]]]],
                            '401' => ['description' => 'No autenticado'],
                        ],
                    ],
                ],
                '/api/v1/whatsapp/plantilla' => [
                    'post' => [
                        'summary'     => 'Enviar plantilla a un cliente',
                        'description' => 'Dispara una plantilla APROBADA a un número. Las variables reemplazan {{1}}, {{2}}, ... en orden. Sale desde el WhatsApp de tu empresa.',
                        'tags'        => ['Mensajes'],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'required' => ['telefono', 'plantilla'],
                            'properties' => [
                                'telefono'  => ['type' => 'string', 'example' => '573001234567', 'description' => 'Número con indicativo, solo dígitos.'],
                                'plantilla' => ['type' => 'string', 'example' => 'bienvenida_cliente', 'description' => 'Nombre EXACTO de la plantilla aprobada.'],
                                'idioma'    => ['type' => 'string', 'example' => 'es', 'description' => 'Código de idioma (opcional).'],
                                'variables' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['Juan', 'Doblamos'], 'description' => 'Valores para {{1}}, {{2}}, ...'],
                                'imagen_url'=> ['type' => 'string', 'description' => 'Opcional: si la plantilla tiene header de imagen.'],
                                'boton_url' => ['type' => 'string', 'description' => 'Opcional: parámetro del botón URL dinámico.'],
                            ],
                        ]]]],
                        'responses' => [
                            '200' => ['description' => 'Enviada', 'content' => ['application/json' => ['example' => ['ok' => true, 'message' => 'Plantilla enviada.', 'telefono' => '573001234567', 'plantilla' => 'bienvenida_cliente']]]],
                            '401' => ['description' => 'No autenticado'],
                            '422' => ['description' => 'Datos inválidos, sin WhatsApp configurado, o plantilla no enviable'],
                        ],
                    ],
                ],

                // ══════════════ PRODUCTOS ══════════════
                '/api/v1/productos' => [
                    'get' => [
                        'summary' => 'Listar productos', 'tags' => ['Productos'],
                        'parameters' => [
                            ['name'=>'q','in'=>'query','schema'=>['type'=>'string'],'description'=>'Búsqueda por nombre'],
                            ['name'=>'categoria_id','in'=>'query','schema'=>['type'=>'integer']],
                            ['name'=>'solo_activos','in'=>'query','schema'=>['type'=>'boolean']],
                            ['name'=>'solo_destacados','in'=>'query','schema'=>['type'=>'boolean']],
                            ['name'=>'per_page','in'=>'query','schema'=>['type'=>'integer','default'=>30]],
                        ],
                        'responses' => ['200'=>['description'=>'Lista paginada de productos'], '401'=>['description'=>'API key inválida']],
                    ],
                    'post' => [
                        'summary' => 'Crear producto', 'tags' => ['Productos'],
                        'requestBody' => ['required'=>true,'content'=>['application/json'=>['schema'=>[
                            'type'=>'object','required'=>['nombre','unidad','precio_base'],
                            'properties'=>[
                                'nombre'=>['type'=>'string','example'=>'Café Guayacán 250g'],
                                'unidad'=>['type'=>'string','example'=>'unidad'],
                                'precio_base'=>['type'=>'number','example'=>40000],
                                'codigo'=>['type'=>'string','example'=>'GA250G'],
                                'categoria_id'=>['type'=>'integer','example'=>null],
                                'descripcion'=>['type'=>'string'],
                                'imagen_url'=>['type'=>'string'],
                                'activo'=>['type'=>'boolean','example'=>true],
                                'destacado'=>['type'=>'boolean','example'=>false],
                            ],
                        ]]]],
                        'responses' => ['201'=>['description'=>'Producto creado'], '401'=>['description'=>'API key inválida'], '422'=>['description'=>'Datos inválidos']],
                    ],
                ],
                '/api/v1/productos/{id}' => [
                    'get'    => ['summary'=>'Ver producto','tags'=>['Productos'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Producto'],'404'=>['description'=>'No encontrado']]],
                    'put'    => ['summary'=>'Editar producto','tags'=>['Productos'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'requestBody'=>['content'=>['application/json'=>['schema'=>['type'=>'object','properties'=>['nombre'=>['type'=>'string'],'precio_base'=>['type'=>'number'],'activo'=>['type'=>'boolean']]]]]],'responses'=>['200'=>['description'=>'Actualizado']]],
                    'delete' => ['summary'=>'Eliminar producto','tags'=>['Productos'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Eliminado']]],
                ],

                // ══════════════ CATEGORÍAS ══════════════
                '/api/v1/categorias' => [
                    'get'  => ['summary'=>'Listar categorías','tags'=>['Categorías'],'responses'=>['200'=>['description'=>'Lista']]],
                    'post' => ['summary'=>'Crear categoría','tags'=>['Categorías'],'requestBody'=>['required'=>true,'content'=>['application/json'=>['schema'=>['type'=>'object','required'=>['nombre'],'properties'=>['nombre'=>['type'=>'string','example'=>'Cafés especiales'],'orden'=>['type'=>'integer'],'activa'=>['type'=>'boolean']]]]]],'responses'=>['201'=>['description'=>'Creada']]],
                ],
                '/api/v1/categorias/{id}' => [
                    'get'    => ['summary'=>'Ver categoría','tags'=>['Categorías'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Categoría']]],
                    'put'    => ['summary'=>'Editar categoría','tags'=>['Categorías'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Actualizada']]],
                    'delete' => ['summary'=>'Eliminar categoría','tags'=>['Categorías'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Eliminada']]],
                ],

                // ══════════════ PROMOCIONES ══════════════
                '/api/v1/promociones' => [
                    'get'  => ['summary'=>'Listar promociones','tags'=>['Promociones'],'responses'=>['200'=>['description'=>'Lista']]],
                    'post' => ['summary'=>'Crear promoción','tags'=>['Promociones'],'requestBody'=>['required'=>true,'content'=>['application/json'=>['schema'=>['type'=>'object','properties'=>['nombre'=>['type'=>'string'],'descripcion'=>['type'=>'string'],'activa'=>['type'=>'boolean']]]]]],'responses'=>['201'=>['description'=>'Creada']]],
                ],
                '/api/v1/promociones/{id}' => [
                    'get'    => ['summary'=>'Ver promoción','tags'=>['Promociones'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Promoción']]],
                    'put'    => ['summary'=>'Editar promoción','tags'=>['Promociones'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Actualizada']]],
                    'delete' => ['summary'=>'Eliminar promoción','tags'=>['Promociones'],'parameters'=>[['name'=>'id','in'=>'path','required'=>true,'schema'=>['type'=>'integer']]],'responses'=>['200'=>['description'=>'Eliminada']]],
                ],

                // ══════════════ ZONAS DE COBERTURA ══════════════
                '/api/v1/zonas' => [
                    'get' => ['summary'=>'Listar zonas de cobertura','tags'=>['Zonas'],'responses'=>['200'=>['description'=>'Lista de zonas']]],
                ],
                '/api/v1/zonas/resolver' => [
                    'post' => [
                        'summary'=>'Resolver cobertura de una dirección','tags'=>['Zonas'],
                        'requestBody'=>['required'=>true,'content'=>['application/json'=>['schema'=>[
                            'type'=>'object','required'=>['direccion'],
                            'properties'=>['direccion'=>['type'=>'string','example'=>'Calle 10 #20-30'],'barrio'=>['type'=>'string'],'ciudad'=>['type'=>'string','example'=>'Bello']],
                        ]]]],
                        'responses'=>['200'=>['description'=>'Resultado de cobertura (cubierta, costo_envio, zona...)']],
                    ],
                ],
            ],
        ];

        return response()->json($spec, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Página Swagger UI. */
    public function ui(): Response
    {
        // Cargar el spec del MISMO host (subdominio del tenant), no uno fijo.
        $specUrl = '/api/openapi.json';

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
