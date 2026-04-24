<?php

namespace App\Http\Controllers;

use App\Models\ChatWidget;
use App\Models\ChatWidgetMensaje;
use App\Models\ChatWidgetSesion;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\ConversacionService;
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
        try {

        $widget = ChatWidget::withoutGlobalScopes()
            ->where('token', $token)
            ->where('activo', true)
            ->first();
        if (!$widget) return $this->cors(response()->json(['error' => 'Widget no encontrado', 'reply' => 'Widget no encontrado o inactivo.'], 404), $request);

        if (!$widget->dominioAutorizado($request->header('Origin'))) {
            return $this->cors(response()->json(['error' => 'Dominio no autorizado', 'reply' => 'Este sitio no está autorizado.'], 403), $request);
        }

        $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($widget->tenant_id);
        if (!$tenant) {
            return $this->cors(response()->json(['error' => 'Tenant no encontrado', 'reply' => 'Configuración incompleta del widget.'], 500), $request);
        }
        app(\App\Services\TenantManager::class)->set($tenant);

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

        // Persistir mensaje del visitante (tabla dedicada del widget)
        ChatWidgetMensaje::create([
            'sesion_id' => $sesion->id,
            'tenant_id' => $widget->tenant_id,
            'rol'       => ChatWidgetMensaje::ROL_USER,
            'contenido' => $data['mensaje'],
        ]);

        // ESPEJO en conversaciones_whatsapp para que aparezca en Chat en vivo
        $conv = $this->espejoConversacion($widget, $sesion);
        app(ConversacionService::class)->agregarMensaje(
            $conv,
            MensajeWhatsapp::ROL_USER,
            $data['mensaje'],
            ['meta' => ['canal_widget' => true, 'widget_session_id' => $sesion->session_id]]
        );

        // Si el operador ya tomó control → NO invocar IA, solo persistir y esperar respuesta humana
        if ($conv->atendida_por_humano) {
            return $this->cors(response()->json([
                'ok'     => true,
                'reply'  => null,   // sin respuesta inmediata del bot; operador responderá manualmente
                'modo'   => 'humano',
            ]), $request);
        }

        // Generar respuesta con IA del bot (modo widget)
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

        // Espejo de respuesta en conversaciones_whatsapp
        app(ConversacionService::class)->agregarMensaje(
            $conv,
            MensajeWhatsapp::ROL_ASSISTANT,
            $respuesta,
            ['meta' => ['canal_widget' => true, 'widget_session_id' => $sesion->session_id]]
        );

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

        } catch (\Throwable $e) {
            \Log::error('Widget /mensaje fatal: ' . $e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
            ]);
            return $this->cors(response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
                'reply' => 'Error en el servidor: ' . $e->getMessage(),
            ], 500), $request);
        }
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

    /**
     * Busca o crea una ConversacionWhatsapp espejo para esta sesión del widget.
     * Se usa telefono_normalizado = "web_{session_id}" para identificarla de forma única,
     * canal='widget' para distinguirla en la UI del Chat en vivo.
     */
    private function espejoConversacion(ChatWidget $widget, ChatWidgetSesion $sesion): ConversacionWhatsapp
    {
        // La columna telefono_normalizado tiene longitud limitada (≈20 chars).
        // Usamos un hash CRC32 del session_id como identificador único corto.
        $telFake = 'w' . substr(hash('crc32b', $sesion->session_id), 0, 8)   // 9 chars
                 . dechex(abs(crc32($widget->token)) % 0xFFFF);              // +4 chars = 13 total

        $conv = ConversacionWhatsapp::where('telefono_normalizado', $telFake)->first();
        if ($conv) return $conv;

        // Crear cliente placeholder para la sesión (requerido por ConversacionService)
        $nombreVisitante = $sesion->visitante_nombre ?: 'Visitante web';
        $cliente = \App\Models\Cliente::firstOrCreate(
            ['telefono_normalizado' => $telFake],
            ['nombre' => $nombreVisitante, 'telefono' => $telFake, 'activo' => true]
        );

        return ConversacionWhatsapp::create([
            'tenant_id'            => $widget->tenant_id,
            'cliente_id'           => $cliente->id,
            'telefono_normalizado' => $telFake,
            'canal'                => 'widget',
            'estado'               => ConversacionWhatsapp::ESTADO_ACTIVA,
            'primer_mensaje_at'    => now(),
            'ultimo_mensaje_at'    => now(),
        ]);
    }

    /**
     * Endpoint que el widget pollea para ver si el operador respondió algo nuevo.
     * GET /api/widget/{token}/mensajes?session_id=X&since=timestamp
     */
    public function mensajes(string $token, Request $request)
    {
        $widget = ChatWidget::where('token', $token)->where('activo', true)->first();
        if (!$widget) return $this->cors(response()->json(['error' => 'Widget no encontrado'], 404), $request);

        if (!$widget->dominioAutorizado($request->header('Origin'))) {
            return $this->cors(response()->json(['error' => 'Dominio no autorizado'], 403), $request);
        }

        app(\App\Services\TenantManager::class)->set($widget->tenant);

        $sessionId = $request->query('session_id');
        $since     = $request->query('since');

        if (!$sessionId) return $this->cors(response()->json(['error' => 'session_id requerido'], 400), $request);

        $telFake = 'w' . substr(hash('crc32b', $sessionId), 0, 8)
                 . dechex(abs(crc32($token)) % 0xFFFF);
        $conv = ConversacionWhatsapp::where('telefono_normalizado', $telFake)->first();

        if (!$conv) {
            return $this->cors(response()->json(['mensajes' => []]), $request);
        }

        $q = $conv->mensajes()
            ->where('rol', MensajeWhatsapp::ROL_ASSISTANT)
            ->whereJsonContains('meta->enviado_por_humano', true);

        if ($since) {
            try {
                $q->where('created_at', '>', \Carbon\Carbon::parse($since));
            } catch (\Throwable $e) { /* ignore */ }
        }

        $mensajes = $q->orderBy('id')
            ->get(['contenido', 'tipo', 'meta', 'created_at'])
            ->map(fn ($m) => [
                'texto'       => $m->contenido,
                'tipo'        => $m->tipo ?: 'text',
                'operador'    => ($m->meta['usuario_id'] ?? null) ? 'Operador' : 'Agente',
                'media_url'   => $m->meta['media_url'] ?? null,
                'created_at'  => $m->created_at->toIso8601String(),
            ]);

        return $this->cors(response()->json([
            'ok'            => true,
            'mensajes'      => $mensajes,
            'modo_humano'   => (bool) $conv->atendida_por_humano,
        ]), $request);
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
