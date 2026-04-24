<?php

namespace App\Http\Controllers;

use App\Models\ChatWidget;
use App\Models\ChatWidgetMensaje;
use App\Models\ChatWidgetSesion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatWidgetController extends Controller
{
    /**
     * Config pública del widget (CORS abierto).
     * GET /api/widget/{token}/config
     */
    public function config(string $token, Request $request)
    {
        $widget = ChatWidget::where('token', $token)->where('activo', true)->first();
        if (!$widget) return $this->cors(response()->json(['error' => 'Widget no encontrado o inactivo'], 404), $request);

        if (!$widget->dominioAutorizado($request->header('Origin'))) {
            return $this->cors(response()->json(['error' => 'Dominio no autorizado'], 403), $request);
        }

        // Setear tenant actual para que scopes funcionen si hace falta
        app(\App\Services\TenantManager::class)->set($widget->tenant);

        return $this->cors(response()->json([
            'ok'                  => true,
            'nombre'              => $widget->nombre,
            'titulo'              => $widget->titulo,
            'subtitulo'           => $widget->subtitulo,
            'saludo_inicial'      => $widget->saludo_inicial,
            'placeholder'         => $widget->placeholder,
            'avatar_url'          => $widget->avatar_url,
            'color_primario'      => $widget->color_primario,
            'color_secundario'    => $widget->color_secundario,
            'posicion'            => $widget->posicion,
            'pedir_nombre'        => $widget->pedir_nombre,
            'pedir_telefono'      => $widget->pedir_telefono,
            'sonido_notificacion' => $widget->sonido_notificacion,
        ]), $request);
    }

    /**
     * Envía un mensaje desde el widget → procesa con la IA del bot → devuelve respuesta.
     * POST /api/widget/{token}/mensaje
     * Body: { session_id, mensaje, visitante_nombre?, visitante_telefono?, url_origen? }
     */
    public function mensaje(string $token, Request $request)
    {
        $widget = ChatWidget::where('token', $token)->where('activo', true)->first();
        if (!$widget) return $this->cors(response()->json(['error' => 'Widget no encontrado'], 404), $request);

        if (!$widget->dominioAutorizado($request->header('Origin'))) {
            return $this->cors(response()->json(['error' => 'Dominio no autorizado'], 403), $request);
        }

        app(\App\Services\TenantManager::class)->set($widget->tenant);

        $data = $request->validate([
            'session_id'         => 'required|string|max:60',
            'mensaje'            => 'required|string|max:2000',
            'visitante_nombre'   => 'nullable|string|max:120',
            'visitante_telefono' => 'nullable|string|max:30',
            'visitante_email'    => 'nullable|email|max:150',
            'url_origen'         => 'nullable|string|max:500',
        ]);

        // Encontrar o crear la sesión
        $sesion = ChatWidgetSesion::firstOrCreate(
            ['session_id' => $data['session_id']],
            [
                'widget_id'          => $widget->id,
                'tenant_id'          => $widget->tenant_id,
                'visitante_nombre'   => $data['visitante_nombre']   ?? null,
                'visitante_telefono' => $data['visitante_telefono'] ?? null,
                'visitante_email'    => $data['visitante_email']    ?? null,
                'url_origen'         => $data['url_origen']         ?? $request->header('Referer'),
                'ip'                 => $request->ip(),
                'user_agent'         => mb_substr((string) $request->userAgent(), 0, 300),
            ]
        );

        // Actualizar datos del visitante si llegaron nuevos
        $actualiza = array_filter([
            'visitante_nombre'   => $data['visitante_nombre']   ?? null,
            'visitante_telefono' => $data['visitante_telefono'] ?? null,
            'visitante_email'    => $data['visitante_email']    ?? null,
        ]);
        if ($actualiza) $sesion->update($actualiza);

        // Persistir mensaje del visitante
        ChatWidgetMensaje::create([
            'sesion_id' => $sesion->id,
            'tenant_id' => $widget->tenant_id,
            'rol'       => ChatWidgetMensaje::ROL_USER,
            'contenido' => $data['mensaje'],
        ]);

        // Generar respuesta con el servicio de IA del bot (modo widget)
        try {
            $respuesta = app(\App\Services\WidgetChatService::class)->responder(
                widget: $widget,
                sesion: $sesion,
                mensaje: $data['mensaje'],
            );
        } catch (\Throwable $e) {
            \Log::error('Widget IA falló: ' . $e->getMessage());
            $respuesta = 'Uy, tuve un problema procesando tu mensaje. ¿Me lo puedes repetir?';
        }

        ChatWidgetMensaje::create([
            'sesion_id' => $sesion->id,
            'tenant_id' => $widget->tenant_id,
            'rol'       => ChatWidgetMensaje::ROL_ASSISTANT,
            'contenido' => $respuesta,
        ]);

        // Contadores
        $sesion->increment('total_mensajes', 2);
        $sesion->update(['ultimo_mensaje_at' => now()]);
        $widget->increment('total_mensajes', 2);
        if ($sesion->total_mensajes === 2) {
            $widget->increment('total_conversaciones');
        }

        return $this->cors(response()->json([
            'ok'     => true,
            'reply'  => $respuesta,
        ]), $request);
    }

    /**
     * Script JS embebible.
     * GET /widget.js?token=XXX
     * Devuelve el JS que monta el widget en la página.
     */
    public function script(Request $request)
    {
        $token = $request->query('token');
        if (!$token) abort(400, 'token requerido');

        $widget = ChatWidget::where('token', $token)->where('activo', true)->first();
        if (!$widget) abort(404);

        $baseUrl = rtrim(config('app.url'), '/');
        $apiBase = $baseUrl . '/api/widget/' . $token;
        $cfgJson = json_encode([
            'token'   => $token,
            'apiBase' => $apiBase,
            'color1'  => $widget->color_primario,
            'color2'  => $widget->color_secundario,
            'pos'     => $widget->posicion,
            'titulo'  => $widget->titulo,
            'saludo'  => $widget->saludo_inicial,
            'holder'  => $widget->placeholder,
            'avatar'  => $widget->avatar_url,
            'nombre'  => $widget->nombre,
        ], JSON_UNESCAPED_UNICODE);

        $js = view('widget.script', [
            'config' => $cfgJson,
        ])->render();

        return response($js, 200, ['Content-Type' => 'application/javascript; charset=UTF-8']);
    }

    private function cors($response, Request $request)
    {
        $origin = $request->header('Origin') ?: '*';
        return $response->withHeaders([
            'Access-Control-Allow-Origin'  => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-Requested-With',
            'Access-Control-Max-Age'       => 86400,
        ]);
    }

    public function preflight(Request $request)
    {
        return $this->cors(response('', 204), $request);
    }
}
