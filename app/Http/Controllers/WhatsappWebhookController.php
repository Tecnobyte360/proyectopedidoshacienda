<?php

namespace App\Http\Controllers;

use App\Events\PedidoActualizado;
use App\Events\PedidoConfirmado;
use App\Models\AnsPedido;
use App\Models\Cliente;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\ZonaCobertura;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\BotCatalogoService;
use App\Services\BotPromptService;
use App\Services\ConversacionService;
use App\Services\GeocodingService;
use App\Services\ZonaResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WhatsappWebhookController extends Controller
{
    /*
    |==========================================================================
    | ENDPOINTS PÚBLICOS
    |==========================================================================
    */

    /**
     * Webhook ESPECÍFICO POR TENANT (URL: /api/whatsapp-webhook/tenant/{slug}).
     * Activa el tenant por slug ANTES de procesar el mensaje. Esto evita
     * depender del connection_id para identificarlo y permite que cada
     * tenant tenga su URL propia para configurar en TecnoByteApp.
     */
    public function receivePorTenant(Request $request, string $slug)
    {
        $tenant = \App\Models\Tenant::where('slug', $slug)->first();

        if (!$tenant) {
            Log::warning('🚫 Webhook tenant slug desconocido', ['slug' => $slug, 'ip' => $request->ip()]);
            return response()->json(['ok' => false, 'error' => 'tenant no encontrado'], 404);
        }

        if (!$tenant->activo) {
            Log::warning('🚫 Webhook tenant inactivo', ['slug' => $slug]);
            return response()->json(['ok' => false, 'error' => 'tenant inactivo'], 403);
        }

        // Forzar tenant activo en este request (todo el flujo respeta este context)
        app(\App\Services\TenantManager::class)->set($tenant);

        Log::info('📩 WEBHOOK por tenant', ['slug' => $slug, 'tenant_id' => $tenant->id]);

        // Reusar el método existente — el resto del flujo es idéntico.
        return $this->receive($request);
    }

    public function receive(Request $request)
    {
        $rawBody = $request->getContent();

        Log::info('📩 WEBHOOK RECIBIDO', [
            'raw_body'    => $rawBody,
            'parsed_data' => $request->all(),
            'ip'          => $request->ip(),
            'time'        => now()->toDateTimeString(),
        ]);

        $data = $request->all();

        if (empty($data) && $rawBody) {
            $data = json_decode($rawBody, true);
        }

        if (empty($data)) {
            Log::warning('⚠️ Webhook vacío');
            return response()->json(['status' => 'error', 'message' => 'Payload vacío'], 400);
        }

        // 🔔 Evento de cambio de estado (ack) — actualiza el mensaje correspondiente
        if (($data['event'] ?? null) === 'message_status') {
            return $this->procesarStatusUpdate($data);
        }

        // 📸 Foto de perfil del contacto — actualizar si llegó
        $profilePicUrl = $data['chat']['profilePicUrl'] ?? null;

        $from    = $data['chat']['phone'] ?? $data['from'] ?? $data['phoneNumber'] ?? null;
        $name    = $data['chat']['name'] ?? $data['name'] ?? 'Cliente';

        // Si vino profilePicUrl + teléfono, actualizar/guardar en el cliente.
        // Lo hacemos acá arriba para que el chat en vivo lo vea aunque sea
        // un mensaje rechazado por otras validaciones.
        if ($profilePicUrl && $from) {
            try {
                $telNorm = preg_replace('/\D+/', '', $from);
                \App\Models\Cliente::withoutGlobalScopes()
                    ->where('telefono_normalizado', $telNorm)
                    ->update(['profile_pic_url' => $profilePicUrl]);
            } catch (\Throwable $e) { /* no bloquear webhook */ }
        }
        $message = trim(
            $data['mensaje']['body'] ?? $data['body'] ?? $data['message'] ?? $data['text'] ?? ''
        );
        $messageId    = $data['mensaje']['id'] ?? $data['message']['id'] ?? $data['id'] ?? null;
        $fromMe       = (bool) ($data['mensaje']['fromMe'] ?? $data['fromMe'] ?? false);
        $connectionId = $data['conexion']['id'] ?? $data['connectionId'] ?? $data['whatsappId'] ?? null;

        // 🎤/🖼️ MEDIA: detectar tipo y URL del archivo
        $tipoMensaje = strtolower(
            $data['mensaje']['type']
                ?? $data['mensaje']['mediaType']
                ?? $data['type']
                ?? $data['messageType']
                ?? $data['mediaType']
                ?? ''
        );
        $mediaUrl = $data['mensaje']['audio']['url']
            ?? $data['mensaje']['mediaUrl']
            ?? $data['audio']['url']
            ?? $data['audioUrl']
            ?? $data['mediaUrl']
            ?? null;
        $audioUrl = $mediaUrl;   // alias para el resto de la lógica de audio

        // Detectar audio
        $esAudio = !empty($audioUrl) && (
            str_starts_with($tipoMensaje, 'audio')
            || in_array($tipoMensaje, ['voice', 'ptt'], true)
            || preg_match('/\.(ogg|opus|mp3|m4a|wav|webm|mpga)(\?|$)/i', $audioUrl) === 1
        );

        // Detectar imagen
        $esImagen = !empty($mediaUrl) && !$esAudio && (
            str_starts_with($tipoMensaje, 'image')
            || preg_match('/\.(jpe?g|png|gif|webp|bmp)(\?|$)/i', $mediaUrl) === 1
        );

        // Si es audio o imagen, el "body" normalmente trae el nombre del archivo — lo ignoramos
        if ($esAudio || $esImagen) {
            $message = '';
        }

        // 🖼️ IMAGEN: descargar, guardar en storage/public y persistir como mensaje del cliente
        if ($esImagen) {
            try {
                $urlLocal = $this->descargarYGuardarImagen($mediaUrl);

                // Resolver tenant por connection_id antes de persistir
                if ($connectionId) {
                    $t = app(\App\Services\WhatsappResolverService::class)->tenantPorConnectionId((int) $connectionId);
                    if ($t) app(\App\Services\TenantManager::class)->set($t);
                }

                $telefonoNorm = preg_replace('/\D+/', '', $from);
                $cliente = \App\Models\Cliente::encontrarOCrearPorTelefono($telefonoNorm, $name);
                $conv = app(\App\Services\ConversacionService::class)
                    ->obtenerOCrearActiva($telefonoNorm, $cliente->id, null, $connectionId ? (int) $connectionId : null);

                app(\App\Services\ConversacionService::class)->agregarMensaje(
                    $conv,
                    \App\Models\MensajeWhatsapp::ROL_USER,
                    '🖼️ Imagen',
                    [
                        'tipo' => 'image',
                        'meta' => [
                            'media_url'     => $urlLocal ?: $mediaUrl,
                            'media_url_src' => $mediaUrl,
                        ],
                    ]
                );

                Log::info('🖼️ Imagen recibida y persistida', ['url' => $urlLocal ?: $mediaUrl]);
                return response()->json(['status' => 'image_received']);
            } catch (\Throwable $e) {
                Log::error('🖼️ Error procesando imagen: ' . $e->getMessage());
                // Seguimos el flujo normal — el cliente al menos verá el aviso
            }
        }

        if ($esAudio) {
            try {
                $config = \App\Models\ConfiguracionBot::actual();
                $transcribir = property_exists($config, 'transcribir_audios')
                    ? (bool) ($config->transcribir_audios ?? true)
                    : true;

                if (!$transcribir) {
                    Log::info('🎤 Audio ignorado (transcripción desactivada)');
                    return response()->json(['status' => 'audio_disabled']);
                }

                Log::info('🎤 Detectado audio, transcribiendo...', ['url' => $audioUrl, 'from' => $from]);
                $texto = app(\App\Services\TranscripcionAudioService::class)->transcribir($audioUrl);

                if ($texto !== '') {
                    $message = $texto;
                    Log::info('🎤 Transcripción OK', ['preview' => mb_substr($texto, 0, 120)]);
                } else {
                    Log::warning('🎤 Transcripción vacía; respondiendo al cliente con nota amigable');
                    // Responder al cliente pero NO abortar el proceso;
                    // devolvemos mensaje amigable en el flujo normal.
                    $message = '[El cliente envió una nota de voz pero no se pudo transcribir. Pídele amablemente que la reenvíe o que escriba el mensaje.]';
                }
            } catch (\Throwable $e) {
                Log::error('🎤 Error procesando audio: ' . $e->getMessage());
                $message = '[Audio recibido pero falló la transcripción. Pídele al cliente que escriba.]';
            }
        }

        Log::info('📥 DATOS NORMALIZADOS', compact('from', 'name', 'message', 'messageId', 'fromMe', 'connectionId'));

        if (!$from || !$message) {
            Log::warning('⚠️ Mensaje ignorado: faltan datos', compact('from', 'message'));
            return response()->json(['status' => 'ignored']);
        }

        // 🏢 MULTI-TENANT: detectar a qué tenant pertenece esta conexión
        if ($connectionId) {
            $tenant = app(\App\Services\WhatsappResolverService::class)
                ->tenantPorConnectionId((int) $connectionId);

            if ($tenant) {
                app(\App\Services\TenantManager::class)->set($tenant);
                Log::info('🏢 Tenant detectado por connection_id', [
                    'connection_id' => $connectionId,
                    'tenant_id'     => $tenant->id,
                    'tenant'        => $tenant->nombre,
                ]);
            } else {
                // Si no hay tenant para esta conexión, usar el tenant 1 (legacy/default)
                $defaultTenant = app(\App\Services\TenantManager::class)->withoutTenant(
                    fn () => \App\Models\Tenant::where('activo', true)->orderBy('id')->first()
                );
                if ($defaultTenant) {
                    app(\App\Services\TenantManager::class)->set($defaultTenant);
                    Log::warning('⚠️ Connection_id sin tenant asignado, usando default', [
                        'connection_id' => $connectionId,
                        'tenant_default' => $defaultTenant->nombre,
                    ]);
                }
            }
        }

        if ($fromMe) {
            Log::info('↩️ Mensaje propio ignorado', ['message_id' => $messageId, 'from' => $from]);
            return response()->json(['status' => 'self_message_ignored']);
        }

        // Deduplicación por messageId — debe ir ANTES de cualquier persist
        // (usuario interno, modo humano, etc.) para evitar duplicados por retries.
        if ($messageId) {
            $alreadyProcessedKey = "processed_whatsapp_msg_{$messageId}";
            $processingKey       = "processing_whatsapp_msg_{$messageId}";

            if (Cache::has($alreadyProcessedKey)) {
                Log::warning('⚠️ Mensaje duplicado ignorado (ya procesado, pre-checks)', compact('messageId', 'from'));
                return response()->json(['status' => 'duplicate_ignored']);
            }

            if (!Cache::add($processingKey, true, now()->addSeconds(30))) {
                Log::warning('⚠️ Mensaje duplicado ignorado (en proceso, pre-checks)', compact('messageId', 'from'));
                return response()->json(['status' => 'duplicate_in_progress']);
            }
        }

        // 👥 Usuario INTERNO del negocio (staff/equipo) — se persiste el mensaje
        // pero el bot NO responde ni ejecuta tool-calls. Solo queda en el chat
        // marcado como conversación interna.
        $telNormCheck = preg_replace('/\D+/', '', (string) $from);
        $esInternoAhora = $telNormCheck && \App\Models\UsuarioInternoWhatsapp::esInterno($telNormCheck);

        // Si el número YA NO es interno (fue removido/desactivado), limpiamos la
        // marca `es_interna` de la conversación para que vuelva al flujo normal.
        if (!$esInternoAhora && $telNormCheck) {
            try {
                \App\Models\ConversacionWhatsapp::where('telefono_normalizado', $telNormCheck)
                    ->where('es_interna', true)
                    ->update(['es_interna' => false]);
            } catch (\Throwable $e) { /* no bloquear */ }
        }

        if ($esInternoAhora) {
            try {
                $usuarioInterno = \App\Models\UsuarioInternoWhatsapp::withoutGlobalScopes()
                    ->where('tenant_id', app(\App\Services\TenantManager::class)->id())
                    ->where('telefono_normalizado', $telNormCheck)
                    ->first();

                $cliente = \App\Models\Cliente::encontrarOCrearPorTelefono($telNormCheck, $usuarioInterno?->nombre ?: $name);
                $conv = app(\App\Services\ConversacionService::class)
                    ->obtenerOCrearActiva($telNormCheck, $cliente->id, null, $connectionId ? (int) $connectionId : null);

                // Marcar la conversación como interna
                if (!$conv->es_interna) $conv->update(['es_interna' => true, 'atendida_por_humano' => true]);

                app(\App\Services\ConversacionService::class)->agregarMensaje(
                    $conv,
                    \App\Models\MensajeWhatsapp::ROL_USER,
                    $message !== '' ? $message : '(media)',
                    $messageId ? ['mensaje_externo_id' => $messageId] : []
                );

                if ($messageId) {
                    Cache::put($alreadyProcessedKey, true, now()->addMinutes(30));
                    Cache::forget($processingKey);
                }
            } catch (\Throwable $e) {
                Log::warning('No se persistió mensaje de usuario interno: ' . $e->getMessage());
            }

            Log::info('👥 Usuario interno — bot NO responde', [
                'phone' => $from,
                'nombre' => $usuarioInterno->nombre ?? null,
            ]);
            return response()->json(['status' => 'internal_user_no_bot']);
        }

        // (El chequeo de dedup ya se hizo arriba, antes de los flujos que persisten.)

        try {
            Log::info('✅ MENSAJE CLIENTE', compact('from', 'name', 'message', 'messageId', 'connectionId'));

            $reply = $this->procesarMensaje($from, $name, $message, $connectionId);

            // Si el reply está vacío, este request perdió el debounce — otro request
            // (el último mensaje del cliente) está procesando todo agrupado. Salir
            // sin enviar nada al cliente para no duplicar respuestas.
            if (trim((string) $reply) === '') {
                Log::info('💬 Request superseded por debounce — no enviar respuesta', [
                    'from'       => $from,
                    'message_id' => $messageId,
                ]);

                if ($messageId) {
                    Cache::put("processed_whatsapp_msg_{$messageId}", true, now()->addMinutes(10));
                }

                return response()->json(['status' => 'superseded_by_newer_message']);
            }

            // 🛡️ GUARD ANTI-ALUCINACIÓN: pedidos fuera de horario
            $reply = $this->aplicarGuardPedidosProgramados($reply);

            // 🛡️ GUARD CRÍTICO: el bot dice "pedido confirmado" SIN haber
            // llamado la tool confirmar_pedido en este turno. Esto es
            // alucinación pura, basada en historial viejo.
            // Si detectamos esto, REEMPLAZAMOS por un mensaje seguro.
            $reply = $this->aplicarGuardPedidoFalsoConfirmado($reply, $toolCalls ?? []);

            // 🛡️ VALIDADOR ANTI-ALUCINACIÓN POST-LLM (capa profesional):
            //    Detecta precios/productos/horarios/promesas inventadas y
            //    reescribe con respuestas seguras del catálogo real.
            $replyAntesValidador = $reply;
            try {
                $reply = app(\App\Services\Bots\ValidadorRespuestaLLM::class)->validar($reply);
            } catch (\Throwable $e) {
                Log::warning('Validador respuesta LLM falló (continúa con reply original): ' . $e->getMessage());
            }

            // 🛡️ Si el validador modificó el reply, actualizar el ÚLTIMO mensaje
            // assistant persistido en BD (procesarConIA lo guardó con la versión
            // original). Así la plataforma muestra lo mismo que recibe el cliente.
            if ($reply !== $replyAntesValidador) {
                try {
                    $convForUpdate = isset($conversacion) ? $conversacion : null;
                    if (!$convForUpdate) {
                        $telNorm = $this->normalizarTelefono($from);
                        $convForUpdate = \App\Models\ConversacionWhatsapp::where('telefono_normalizado', $telNorm)
                            ->orderByDesc('id')->first();
                    }
                    if ($convForUpdate) {
                        $ultMsg = \App\Models\MensajeWhatsapp::where('conversacion_id', $convForUpdate->id)
                            ->where('rol', \App\Models\MensajeWhatsapp::ROL_ASSISTANT)
                            ->orderByDesc('id')
                            ->first();
                        if ($ultMsg && $ultMsg->contenido === $replyAntesValidador) {
                            $ultMsg->contenido = $reply;
                            $ultMsg->save();
                            Log::info('🛡️ Último mensaje assistant actualizado con reply post-validador', [
                                'mensaje_id' => $ultMsg->id,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('No se pudo actualizar último mensaje post-validador: ' . $e->getMessage());
                }
            }

            Log::info('💬 RESPUESTA GENERADA', compact('reply', 'from', 'messageId', 'connectionId'));

            $sent = $this->enviarRespuestaWhatsapp($from, $reply, $connectionId);

            if ($messageId && $sent) {
                Cache::put("processed_whatsapp_msg_{$messageId}", true, now()->addMinutes(10));
            }

            if (!$sent) {
                Log::warning('⚠️ La respuesta fue generada pero no se pudo enviar a WhatsApp', [
                    'from'         => $from,
                    'message_id'   => $messageId,
                    'connectionId' => $connectionId,
                ]);

                return response()->json([
                    'status'            => 'error',
                    'message_processed' => false,
                    'message'           => 'No se pudo enviar la respuesta a WhatsApp.'
                ], 500);
            }

            return response()->json(['status' => 'ok', 'message_processed' => true]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR PROCESANDO MENSAJE', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->notificarFallaWhatsapp(
                'ERROR EN WEBHOOK DE PEDIDOS',
                'Ocurrió un error procesando un mensaje entrante de WhatsApp.',
                [
                    'error' => $e->getMessage(),
                    'from' => $from ?? null,
                    'messageId' => $messageId ?? null,
                    'connectionId' => $connectionId ?? null,
                ]
            );

            return response()->json(['status' => 'error', 'message' => 'No se pudo procesar'], 500);
        } finally {
            if (!empty($messageId)) {
                Cache::forget("processing_whatsapp_msg_{$messageId}");
            }
        }
    }

    public function searchOrders(Request $request)
    {
        try {
            $request->validate([
                'pedido_id' => 'nullable|integer',
                'telefono'  => 'nullable|string|max:30',
                'cliente'   => 'nullable|string|max:255',
            ]);

            if (
                !$request->filled('pedido_id') &&
                !$request->filled('telefono') &&
                !$request->filled('cliente')
            ) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Debes enviar al menos uno de: pedido_id, telefono o cliente.',
                ], 422);
            }

            $query = Pedido::with(['sede', 'detalles']);

            if ($request->filled('pedido_id')) {
                $query->where('id', $request->pedido_id);
            }

            if ($request->filled('telefono')) {
                $tel      = $this->normalizarTelefono($request->telefono);
                $telLocal = $this->obtenerTelefonoLocal($tel);

                $query->where(function ($q) use ($telLocal) {
                    $q->where('telefono_whatsapp', 'LIKE', "%{$telLocal}%")
                        ->orWhere('telefono_contacto', 'LIKE', "%{$telLocal}%")
                        ->orWhere('telefono', 'LIKE', "%{$telLocal}%");
                });
            }

            if ($request->filled('cliente')) {
                $query->where('cliente_nombre', 'LIKE', '%' . trim($request->cliente) . '%');
            }

            $pedidos = $query->orderByDesc('fecha_pedido')->orderByDesc('id')->get();

            if ($pedidos->isEmpty()) {
                return response()->json([
                    'status'  => 'not_found',
                    'message' => 'No se encontraron pedidos con los filtros enviados.',
                    'filters' => $request->only(['pedido_id', 'telefono', 'cliente']),
                ], 404);
            }

            return response()->json([
                'status'       => 'success',
                'total_orders' => $pedidos->count(),
                'filters'      => $request->only(['pedido_id', 'telefono', 'cliente']),
                'orders'       => $pedidos->map(fn($p) => $this->formatearPedidoParaApi($p))->values(),
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR SEARCH ORDERS', [
                'error'   => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Error al consultar pedidos.'], 500);
        }
    }

    public function showOrder($id)
    {
        try {
            $pedido = Pedido::with(['sede', 'detalles'])->find($id);

            if (!$pedido) {
                return response()->json([
                    'status'  => 'not_found',
                    'message' => 'Pedido no encontrado.',
                    'id'      => $id
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'order'  => $this->formatearPedidoParaApi($pedido)
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR SHOW ORDER', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json(['status' => 'error', 'message' => 'Error al consultar el pedido.'], 500);
        }
    }

    /*
    |==========================================================================
    | FLUJO PRINCIPAL
    |==========================================================================
    */

    private function procesarMensaje(string $from, string $name, string $message, ?string $connectionId): string
    {
        // ── CAPA -2: Kill switch global del bot ──────────────────────────────
        // Si el operador apagó el bot desde /configuracion/bot, NO responde a nadie.
        // Aún persistimos los mensajes del cliente en BD para que aparezcan en /chat
        // y el operador pueda atenderlos manualmente.
        $configBot = \App\Models\ConfiguracionBot::actual();
        if (!$configBot->activo) {
            try {
                $telefonoNorm = $this->normalizarTelefono($from);
                $cliente      = \App\Models\Cliente::encontrarOCrearPorTelefono($telefonoNorm, $name);
                $convService  = app(\App\Services\ConversacionService::class);
                $conv         = $convService->obtenerOCrearActiva($telefonoNorm, $cliente->id);
                $convService->agregarMensaje($conv, \App\Models\MensajeWhatsapp::ROL_USER, $message);
            } catch (\Throwable $e) {
                Log::warning('No se persistió mensaje (bot OFF): ' . $e->getMessage());
            }

            Log::info('🔌 Bot DESACTIVADO globalmente — sin respuesta', ['phone' => $from]);
            return '';   // sin respuesta
        }

        // ── CAPA -1: Modo intervención humana ─────────────────────────────────
        // Si un operador tomó control de la conversación, el bot NO responde.
        // El mensaje sí se persiste (ya se hace en procesarConIA), pero no se
        // genera respuesta automática. El humano responderá manualmente.
        $telefonoNorm = $this->normalizarTelefono($from);
        $convActiva   = \App\Models\ConversacionWhatsapp::where('telefono_normalizado', $telefonoNorm)
            ->where('estado', \App\Models\ConversacionWhatsapp::ESTADO_ACTIVA)
            ->orderByDesc('id')
            ->first();

        if ($convActiva && $convActiva->atendida_por_humano) {
            // Persistir mensaje del cliente para que el operador lo vea
            try {
                $cliente = \App\Models\Cliente::encontrarOCrearPorTelefono($telefonoNorm, $name);
                $convActiva->update(['cliente_id' => $convActiva->cliente_id ?? $cliente->id]);
                app(\App\Services\ConversacionService::class)->agregarMensaje(
                    $convActiva,
                    \App\Models\MensajeWhatsapp::ROL_USER,
                    $message
                );
            } catch (\Throwable $e) {
                Log::warning('No se persistió mensaje en modo humano: ' . $e->getMessage());
            }

            Log::info('🧍 Modo humano activo — bot NO responde', ['phone' => $from]);
            return '';   // sin respuesta automática
        }

        // NOTA: la derivación por keywords fue REMOVIDA — ahora es 100% decisión
        // de la IA a través de la tool `derivar_a_departamento`. Esto permite
        // que detecte enojo, frustración y matices que las keywords no capturan.

        // ── CAPA 0: Buffer + debounce — agrupar mensajes seguidos del mismo cliente ──
        // Si el cliente manda 3 mensajes en 4 segundos, esperamos a que termine de
        // escribir y respondemos UNA sola vez con todo el contexto.
        $config = \App\Models\ConfiguracionBot::actual();
        $mensajesYaPersistidos = false;

        if ($config->agrupar_mensajes_activo && (int) $config->agrupar_mensajes_segundos > 0) {
            $resultadoAgrupado = $this->agruparOEsperarMensajes(
                $from,
                $name,
                $message,
                $connectionId,
                (int) $config->agrupar_mensajes_segundos
            );

            // Si retorna null, este request no es el "ganador" — otro lo procesará
            if ($resultadoAgrupado === null) {
                return '';   // string vacío → el llamador no envía nada al cliente
            }

            // Sustituir el mensaje único por el agrupado (ya persistido al instante antes del sleep)
            $message = $resultadoAgrupado;
            $mensajesYaPersistidos = true;
        }

        if ($this->tieneAccionPendiente($from)) {
            $reply = $this->resolverAccionPendiente($from, $name, $message);
            if ($reply) {
                Log::info('🧠 CAPA 1: Respuesta por acción pendiente', compact('from', 'message', 'reply'));
                return $reply;
            }
        }

        if ($this->esSolicitudModificarPedido($message)) {
            $reply = $this->resolverSolicitudModificacionPedido($from, $name, $message);
            Log::info('🛠️ CAPA 2a: Modificación de pedido', compact('from', 'message', 'reply'));
            return $reply;
        }

        if ($this->esConsultaEstadoPedido($message)) {
            $reply = $this->resolverConsultaEstadoPedido($from, $name, $message);
            Log::info('📦 CAPA 2b: Consulta de estado', compact('from', 'message', 'reply'));
            return $reply;
        }

        return $this->procesarConIA($from, $name, $message, $connectionId, $mensajesYaPersistidos);
    }

    /**
     * Sistema buffer + debounce.
     *
     * Cada llamada agrega el mensaje al buffer del cliente y espera N segundos.
     * Si durante esa espera llega otro mensaje del MISMO cliente, este request se
     * "rinde" (devuelve null) y deja que el más nuevo procese todos los mensajes
     * acumulados. Solo el último mensaje del cliente "gana" y procesa todo junto.
     *
     * Resultado:
     *   - string  → este request es el ganador, debe procesar el mensaje agrupado
     *   - null    → otro mensaje más nuevo está procesando, este sale silencioso
     */
    private function agruparOEsperarMensajes(
        string $from,
        string $name,
        string $message,
        ?string $connectionId,
        int $segundosEspera
    ): ?string {
        $tenantId  = app(\App\Services\TenantManager::class)->id() ?? 'none';
        $bufferKey = "wa_buffer_t{$tenantId}_{$from}";
        $myTimestamp = (string) round(microtime(true) * 1000);   // millis como ID único

        // Añadir mi mensaje al buffer
        $buffer = Cache::get($bufferKey, ['mensajes' => [], 'last_ts' => '0']);
        $buffer['mensajes'][] = ['ts' => $myTimestamp, 'texto' => $message];
        $buffer['last_ts']    = $myTimestamp;

        Cache::put($bufferKey, $buffer, now()->addMinutes(2));

        Log::info('💬 Buffer: mensaje agregado, esperando', [
            'phone'    => $from,
            'mi_ts'    => $myTimestamp,
            'esperar'  => $segundosEspera . 's',
            'mensajes' => count($buffer['mensajes']),
        ]);

        // ⚡ Persistir el mensaje del cliente AL INSTANTE (antes del sleep) para
        // que aparezca ya en el Chat en vivo. La respuesta del bot sí sigue
        // esperando el buffer, pero el usuario ve el mensaje del cliente sin demora.
        try {
            $cliente = \App\Models\Cliente::encontrarOCrearPorTelefono($from, $name);
            $conv = app(\App\Services\ConversacionService::class)
                ->obtenerOCrearActiva($from, $cliente->id, null, $connectionId ? (int) $connectionId : null);

            app(\App\Services\ConversacionService::class)->agregarMensaje(
                $conv,
                \App\Models\MensajeWhatsapp::ROL_USER,
                $message
            );
        } catch (\Throwable $e) {
            Log::warning('⚡ No pude persistir el mensaje del cliente al instante: ' . $e->getMessage());
        }

        // Esperar a que el cliente termine de escribir (solo afecta la respuesta del bot)
        sleep($segundosEspera);

        // Después del sleep, ¿soy yo el último mensaje del cliente?
        $bufferActual = Cache::get($bufferKey);

        if (!$bufferActual || $bufferActual['last_ts'] !== $myTimestamp) {
            Log::info('💬 Buffer: mensaje obsoleto, otro request procesará', [
                'phone'      => $from,
                'mi_ts'      => $myTimestamp,
                'last_ts'    => $bufferActual['last_ts'] ?? '(null)',
            ]);
            return null;   // No soy el ganador, salgo sin responder
        }

        // ¡Soy el ganador! Junto todos los mensajes pendientes y proceso una vez
        $textoCompleto = collect($bufferActual['mensajes'])
            ->pluck('texto')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->join("\n");

        // Limpio el buffer (no liberar el lock todavía — hasta que termine procesarConIA)
        Cache::forget($bufferKey);

        Log::info('💬 Buffer: GANADOR procesa mensajes agrupados', [
            'phone'        => $from,
            'cantidad'     => count($bufferActual['mensajes']),
            'texto_total'  => mb_substr($textoCompleto, 0, 200),
        ]);

        return $textoCompleto;
    }

    private function procesarConIA(string $from, string $name, string $message, ?string $connectionId = null, bool $yaPersisitido = false): string
    {
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        $cacheKey = "whatsapp_chat_t{$tenantId}_{$from}";

        // ── AUTO-RESET DE CONTEXTO ────────────────────────────────────────
        // Producción: si el cliente saluda con un mensaje corto tipo "hola"
        // y la última actividad fue hace >20 min, asumimos que es una nueva
        // conversación y reseteamos el cache para evitar que el bot responda
        // con contexto viejo (ej: "tu dirección está fuera de cobertura"
        // cuando el cliente solo dijo "hola").
        $telefonoNorm = $this->normalizarTelefono($from);
        $this->autoResetSiCorresponde($cacheKey, $message, $tenantId, $telefonoNorm);

        $pedidosInfo  = $this->buscarPedidosClienteSQL($from, $message);
        $ansInfo      = $this->construirResumenAns();

        // Resolver sede para inyectar catálogo correcto (precios pueden variar por sede)
        $sedeId = $this->obtenerSedeIdDesdeConexion($connectionId);

        // ── CLIENTE: identificar/crear y enriquecer el contexto ──────────────
        $cliente = Cliente::encontrarOCrearPorTelefono($telefonoNorm, $name);

        // ── CONVERSACIÓN: obtener/crear y persistir mensaje del usuario ──────
        /** @var ConversacionService $convService */
        $convService = app(ConversacionService::class);
        $conversacion = $convService->obtenerOCrearActiva(
            $telefonoNorm,
            $cliente->id,
            $sedeId,
            $connectionId ? (int) $connectionId : null
        );

        // Persistir mensaje del cliente (a menos que el buffer ya lo haya hecho al instante)
        if (!$yaPersisitido) {
            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_USER, $message);
        }

        // ── HISTORIAL: reducido a últimos 10 (en vez de 20) para evitar
        // que historial viejo confunda al bot. 10 = ~5 turnos = suficiente.
        $conversationHistory = $conversacion->fresh()->historialParaIA(10);

        // ⏰ AUTO-RESET: si el cliente saluda Y la última actividad fue
        // hace más de 3 horas, reseteamos el historial. Esto evita que
        // pedidos viejos se mezclen con conversaciones nuevas.
        $conversationHistory = $this->autoResetSiSaludoLargoTiempo($conversacion, $message, $conversationHistory);

        // Usar el nombre del cliente guardado si está mejor que el de WhatsApp
        $nombreParaPrompt = $cliente->nombre !== 'Cliente' ? $cliente->nombre : $name;

        // Agregar resumen del cliente al historial textual del prompt
        $resumenCliente = $cliente->resumenParaBot();
        $pedidosInfo = $resumenCliente . "\n\n" . $pedidosInfo;

        // Pasamos el telefono al request para que BotPromptService::reglaCedula
        // pueda saber si el cliente actual ya tiene cedula/correo registrados
        // y no se los pida de nuevo.
        request()->attributes->set('telefono_cliente_actual', $cliente->telefono_normalizado);

        // ════════════════════════════════════════════════════════════════════
        // 🛡️ EARLY GUARD — VALIDACIÓN DE HORARIO ANTES DE TODO
        // ════════════════════════════════════════════════════════════════════
        try {
            $sedesActivasG = \App\Models\Sede::where('activa', true)->get();
            $hayAlgunaAbiertaG = $sedesActivasG->isNotEmpty()
                && $sedesActivasG->contains(fn ($s) => $s->estaAbierta());

            // Cache key para recordar si el cliente ya aceptó programar
            $programadoKey = "wa_programar_aceptado_t{$tenantId}_{$telefonoNorm}";

            // ¿Cliente está afirmando aceptación tras nuestra oferta de programar?
            //    último assistant ofreció programar + user dice "si/ok/dale/listo"
            $afirmoProgramar = $this->detectarAfirmacionProgramar($conversacion, $message);
            if ($afirmoProgramar) {
                Cache::put($programadoKey, true, now()->addHours(2));
                Log::info('🛡️ EARLY GUARD: cliente aceptó programar — flag activado por 2h', [
                    'from' => $from,
                ]);
            }

            $yaAceptoProgramar = (bool) Cache::get($programadoKey, false);

            if (!$hayAlgunaAbiertaG && $sedesActivasG->isNotEmpty()
                && !$yaAceptoProgramar
                && !$this->mensajeEsAgradecimientoODespedida($message)) {

                $sedePrincipal = $sedesActivasG->first();
                $proximaApertura = $sedePrincipal->proximaApertura() ?: 'cuando abramos';

                // 🛡️ Nombre seguro: si cliente.nombre es un email o muy raro,
                // usar el nombre de WhatsApp.
                $nombreSano = trim((string) $nombreParaPrompt);
                if ($nombreSano === '' || str_contains($nombreSano, '@') || $nombreSano === 'Cliente') {
                    $nombreSano = trim((string) $name);
                }
                $primerNombre = explode(' ', $nombreSano)[0] ?? '';
                if ($primerNombre === '' || str_contains($primerNombre, '@')) {
                    $primerNombre = ''; // sin nombre antes que un email
                }

                // Saludo según hora del día
                $hora = (int) now()->setTimezone('America/Bogota')->format('H');
                $saludoHora = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
                $personalizar = $primerNombre !== '' ? " {$primerNombre}" : '';

                $tenantNombre = optional(\App\Models\Tenant::find(app(\App\Services\TenantManager::class)->id()))->nombre ?? 'nuestro punto de venta';
                $respuestaCierre = "{$saludoHora}{$personalizar}, bienvenid@ a *{$tenantNombre}*.\n\n"
                    . "En este momento estamos cerrados. Próxima apertura: {$proximaApertura}.\n\n"
                    . "📅 Puedo dejarte el pedido *PROGRAMADO* para que esté listo apenas abramos. "
                    . "Indícame qué necesitas y lo registro.";

                Log::info('🛡️ EARLY GUARD: respuesta de cierre directa (sin LLM)', [
                    'from'             => $from,
                    'message'          => mb_substr($message, 0, 100),
                    'proxima_apertura' => $proximaApertura,
                    'sedes_cerradas'   => $sedesActivasG->count(),
                ]);

                // Flag para que aplicarGuardPedidosProgramados no reescriba este reply
                request()->attributes->set('early_guard_handled', true);

                try {
                    $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $respuestaCierre);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo persistir respuesta de EARLY GUARD: ' . $e->getMessage());
                }

                return $respuestaCierre;
            }
        } catch (\Throwable $e) {
            Log::warning('⚠️ EARLY GUARD horario falló (siguiendo flujo normal): ' . $e->getMessage());
        }

        // ════════════════════════════════════════════════════════════════════
        // 🤝 HANDOFF AUTOMÁTICO A HUMANO — antes de cualquier procesamiento IA
        // Detecta si el cliente está frustrado, pide humano, o el bot está
        // en bucle. Si sí: marca conversación + notifica equipo + responde
        // mensaje cordial sin gastar tokens.
        // ════════════════════════════════════════════════════════════════════
        try {
            $derivacionMsg = app(\App\Services\Bots\HandoffHumanoService::class)
                ->evaluar($conversacion, $message);
            if ($derivacionMsg !== null) {
                $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $derivacionMsg);
                return $derivacionMsg;
            }
        } catch (\Throwable $e) {
            Log::warning('⚠️ Handoff service falló (continúa flujo normal): ' . $e->getMessage());
        }

        // Si la conversación YA está marcada para humano, no procesar con IA
        if ($conversacion->requiere_humano && !$conversacion->humano_atendido_at) {
            Log::info('🤝 Conversación pendiente de humano — bot no responde', [
                'conv_id' => $conversacion->id,
            ]);
            return ''; // no enviar respuesta automática
        }

        // ════════════════════════════════════════════════════════════════════
        // 🤖 ROUTER DETERMINISTA — decide acción sin LLM cuando es posible
        // ════════════════════════════════════════════════════════════════════
        try {
            $primerNombreRouter = explode(' ', trim($nombreParaPrompt))[0] ?? '';
            if (str_contains($primerNombreRouter, '@')) $primerNombreRouter = '';

            $decision = app(\App\Services\Bots\RouterDeterminista::class)
                ->decidir($conversacion, $message, $primerNombreRouter, $connectionId ? (int) $connectionId : null);

            if ($decision['accion'] === 'reply') {
                $reply = $decision['reply'];
                $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply);
                return $reply;
            }

            if ($decision['accion'] === 'cerrar_pedido') {
                Log::info('🤖 Router: invocando guardarPedidoDesdeToolCall directo', [
                    'from'  => $from,
                    'razon' => $decision['razon'] ?? 'estado_completo',
                ]);
                $cacheKeyEstado = "whatsapp_chat_t{$tenantId}_{$from}";
                return $this->guardarPedidoDesdeToolCall(
                    $decision['orderData'],
                    $from,
                    $nombreParaPrompt,
                    $conversationHistory,
                    $cacheKeyEstado,
                    $connectionId,
                    $conversacion,
                    $convService
                );
            }

            // 'llm' → cae al flujo normal
        } catch (\Throwable $e) {
            Log::warning('⚠️ Router determinista falló (siguiendo a LLM): ' . $e->getMessage());
        }

        $systemPrompt = $this->getSystemPrompt($pedidosInfo, $this->infoEmpresa(), $nombreParaPrompt, $ansInfo, $sedeId);

        // ── NOTA DE RECHAZO RECIENTE DE COBERTURA ────────────────────────
        // Si en los últimos 15 min rechazamos una dirección por cobertura,
        // inyectamos un system con regla dura para que la IA no repita el
        // mismo intento ni el mismo texto literal en bucle.
        $extraSystem = [];
        $tenantIdNota = app(\App\Services\TenantManager::class)->id() ?? 'none';
        $rechazoIndexKey = "wa_rechazo_cobertura_idx_t{$tenantIdNota}_{$telefonoNorm}";
        $ultimoRechazo = Cache::get($rechazoIndexKey);
        if (is_array($ultimoRechazo) && !empty($ultimoRechazo['direccion'])) {
            $extraSystem[] = [
                'role'    => 'system',
                'content' => "🚫 La dirección \"{$ultimoRechazo['direccion']}\" fue rechazada por cobertura hace pocos minutos.\n"
                    . "REGLAS DURAS:\n"
                    . "1) NO llames `confirmar_pedido` mientras la dirección sea esa o equivalente.\n"
                    . "2) Si el cliente insiste sin cambiar dirección, ofrece *recoger en sede* o pídele otra dirección/barrio cercano.\n"
                    . "3) NO repitas literalmente el mismo mensaje de rechazo dos veces seguidas: varía el texto.\n"
                    . "4) Si saluda otra vez (\"hola\", \"buenas\"), respóndele cordial y vuelve a la pregunta de la dirección — NO al rechazo de nuevo.",
            ];
        }

        // ── ALERTA DE SEDE CERRADA ──────────────────────────────────────
        // SOLO inyectamos la alerta DURA si TODAS las sedes activas están
        // cerradas (no podemos atender desde ninguna). Si al menos una está
        // abierta, dejamos que el bot ofrezca ese punto de atención.
        try {
            $sedesActivas = \App\Models\Sede::where('activa', true)->get();
            $hayAlgunaAbierta = $sedesActivas->isNotEmpty()
                && $sedesActivas->contains(fn ($s) => $s->estaAbierta());

            $sedeActual = $sedeId ? \App\Models\Sede::find($sedeId) : $sedesActivas->first();

            if (!$hayAlgunaAbierta && $sedeActual) {
                $promptService = app(BotPromptService::class);
                $contextoCierre = $promptService->construirContexto(
                    $nombreParaPrompt,
                    $sedeId,
                    $this->infoEmpresa(),
                    $pedidosInfo,
                    $ansInfo
                );

                // Variable adicional específica de esta alerta
                $contextoCierre['proxima_apertura'] = $sedeActual->proximaApertura() ?: 'cuando abramos';

                $template = <<<'TXT'
⛔ ALERTA CRÍTICA — SEDE CERRADA AHORA ⛔

Estado de la sede: {sede_estado_actual}
Próxima apertura: {proxima_apertura}

Horarios completos:
{horarios_sedes}

REGLAS OBLIGATORIAS PARA ESTA CONVERSACIÓN (no negociables):

1. Eres {nombre_asesora}. NO inicies toma de pedido. Aunque el cliente diga
   "quiero pedir", "para un pedido", "hola" o cualquier saludo, tu PRIMERA
   respuesta debe avisarle con calidez que estamos cerrados.

2. NUNCA llames la función `confirmar_pedido` mientras estemos cerrados —
   el sistema lo rechaza igual y queda mal con el cliente.

3. NO listes catálogo, NO preguntes "¿qué te gustaría?", NO sigas el flujo
   normal de pedido. Solo informa el cierre y la próxima apertura.

4. Responde con calidez paisa — varía el texto, no copies literal. Ejemplo
   de tono (adáptalo a la conversación, no lo repitas idéntico):

   "Ay {cliente_primer_nombre}, ahorita estamos cerrados 🙏.
    Te atendemos {proxima_apertura} y con gusto te despachamos.
    ¿Te aviso apenas abramos?"

5. Si el cliente insiste en dejar el pedido listo, dile amablemente que
   escriba apenas abramos para confirmárselo bien — no lo registres.

6. Si pregunta el horario completo, usa los datos del bloque "Horarios
   completos" de arriba — NUNCA inventes horarios distintos.
TXT;

                $extraSystem[] = [
                    'role'    => 'system',
                    'content' => $promptService->renderizar($template, $contextoCierre),
                ];
            }

            // ── INFO de DISPONIBILIDAD POR SEDE ─────────────────────────
            // Si hay varias sedes con horarios distintos, le decimos al bot
            // CUÁLES están abiertas para que pueda ofrecer la correcta.
            if ($sedesActivas->count() >= 2) {
                $abiertas = $sedesActivas->filter(fn ($s) => $s->estaAbierta());
                $cerradas = $sedesActivas->reject(fn ($s) => $s->estaAbierta());

                if ($abiertas->isNotEmpty() && $cerradas->isNotEmpty()) {
                    // Caso mixto: algunas abiertas, algunas cerradas
                    $extraSystem[] = [
                        'role'    => 'system',
                        'content' => "📍 ESTADO REAL DE SEDES AHORA:\n\n"
                            . "✅ ABIERTAS y atendiendo:\n"
                            . $abiertas->map(fn ($s) => "  • " . $s->nombre . " (" . $s->direccion . ") — " . $s->horarioHoyTexto())->implode("\n")
                            . "\n\n🔴 CERRADAS ahora:\n"
                            . $cerradas->map(fn ($s) => "  • " . $s->nombre . " — abre: " . ($s->proximaApertura() ?: 'según horario'))->implode("\n")
                            . "\n\nREGLAS:\n"
                            . "1. NUNCA digas 'estamos cerrados' como afirmación general — al menos una sede está atendiendo.\n"
                            . "2. Cuando el cliente pregunte por horario o si están abiertos, responde con la SEDE ABIERTA.\n"
                            . "3. Si el cliente está cerca de una sede cerrada, ofrece la sede abierta más cercana o entrega a domicilio.\n"
                            . "4. Si el cliente no especifica sede, asume que despachamos desde la abierta.",
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('No se pudo inyectar alerta de sede cerrada: ' . $e->getMessage());
        }

        $reinforceProgramado = [];

        // Si MODO AGENTE está activo, añadimos un system message FINAL que sobrescribe
        // cualquier instrucción contradictoria del prompt personalizado (ej: "solo del
        // catálogo de abajo"). Refuerza que SIEMPRE debe usar las tools de catálogo.
        $reinforceAgent = [];
        if (!empty($config->bot_modo_agente)) {
            $reinforceAgent[] = [
                'role'    => 'system',
                'content' => "🚨 FINAL OVERRIDE — INSTRUCCIÓN MÁS IMPORTANTE QUE TODO LO ANTERIOR:\n\n"
                    . "Estás en MODO AGENTE. El catálogo de productos NO está en tu prompt — vive en las tools "
                    . "(buscar_productos, listar_categorias, productos_de_categoria, info_producto, productos_destacados). "
                    . "Cualquier instrucción anterior que diga 'solo productos del catálogo de abajo', 'lista de productos', "
                    . "'NO inventes productos' DEBE interpretarse como: USA LAS TOOLS PARA SABER QUÉ EXISTE.\n\n"
                    . "❌ ABSOLUTAMENTE PROHIBIDO responder 'no tengo X' / 'no manejamos X' / 'solo tengo Y' SIN haber llamado "
                    . "buscar_productos PRIMERO con el texto literal del cliente.\n\n"
                    . "✅ FLUJO: cliente menciona producto → buscar_productos(query) → leer resultado → responder con datos reales.",
            ];
        }

        // 🎯 ESTADO ESTRUCTURADO: inyectar resumen del pedido en BD para que
        // el LLM SIEMPRE sepa qué datos ya tiene, sin depender de leer chat.
        $reinforceEstadoPedido = [];
        $reinforceFlujo        = [];
        $pasoActualOrch        = \App\Models\ConversacionPedidoEstado::PASO_INICIO;
        try {
            $estadoSrv = app(\App\Services\EstadoPedidoService::class);

            // 🔁 AUTO-RESET para nuevo pedido del mismo cliente: si el estado
            // está confirmado y el cliente menciona "otro pedido", "quiero más",
            // etc → resetear estado y arrancar un flujo limpio.
            $estadoVerif = $estadoSrv->obtener($conversacion);
            if (
                $estadoVerif->paso_actual === \App\Models\ConversacionPedidoEstado::PASO_CONFIRMADO &&
                $estadoSrv->detectarIntencionNuevoPedido($message)
            ) {
                Log::info('🔁 Cliente quiere NUEVO pedido — reseteando estado', [
                    'from'           => $from,
                    'pedido_anterior'=> $estadoVerif->pedido_id,
                    'mensaje'        => $message,
                ]);
                $estadoSrv->resetear(
                    $conversacion,
                    "nuevo_pedido_tras_{$estadoVerif->pedido_id}"
                );
            }

            // 🔍 CAPTURA PROACTIVA: detecta cédula/email en el mensaje del cliente
            // y los guarda en el estado ANTES de que el LLM procese. Así no se
            // pierden aunque el bot no llame la tool correcta.
            $estadoSrv->captarDelMensajeUsuario($conversacion, $message);

            $resumenEstado = $estadoSrv->resumenParaPrompt($conversacion);
            if ($resumenEstado !== '') {
                $reinforceEstadoPedido[] = [
                    'role'    => 'system',
                    'content' => $resumenEstado . "\n\n"
                        . "🚨 Esta es la VERDAD ESTRUCTURADA del pedido. Úsala como input para confirmar_pedido. "
                        . "Si dice 'DATOS COMPLETOS' debes invocar `confirmar_pedido` AHORA con estos datos. "
                        . "No vuelvas a pedir lo que ya está aquí.",
                ];
            }

            // 🎯 ORQUESTADOR DETERMINISTA: instrucción + tools restringidas al paso
            $estadoFlujoActual = $estadoSrv->obtener($conversacion);
            $pasoActualOrch    = $estadoFlujoActual->paso_actual;
            $reinforceFlujo[]  = app(\App\Services\FlujoPedidoOrchestrator::class)
                ->systemMessageParaPaso($conversacion, $this->getToolsDefinicion());
        } catch (\Throwable $e) {
            \Log::warning('No se pudo inyectar resumen/orquestador: ' . $e->getMessage());
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $reinforceAgent,
            $extraSystem,
            $reinforceProgramado,    // 📅 pedidos programados
            $reinforceEstadoPedido,  // 🎯 estado estructurado en BD
            $reinforceFlujo,         // 🚦 instrucción del paso actual (orquestador)
            $conversationHistory
        );

        // 🚦 ORQUESTADOR: regla por paso (tools permitidas + tool_choice)
        $orchestrator = app(\App\Services\FlujoPedidoOrchestrator::class);
        $toolsFiltradas = $orchestrator->filtrarTools(
            $this->getToolsDefinicion(),
            $pasoActualOrch
        );
        $toolChoicePorPaso = $orchestrator->toolChoice($pasoActualOrch);

        // 🎯 SHORT-CIRCUITS según intención detectada en el mensaje:
        //   1. Pidió "generar pedido" → forzar confirmar_pedido
        //   2. Preguntó por producto → forzar buscar_productos
        //   3. Dió datos finales → forzar confirmar_pedido si estado completo, sino required
        $forzarConfirmar    = $this->clientePidioGenerarPedido($message);
        $preguntaProducto   = !$forzarConfirmar && $this->clientePreguntaProducto($message);
        $estadoActualBd     = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
        $estadoYaCompleto   = $estadoActualBd && $estadoActualBd->estaCompleto() && !$estadoActualBd->confirmado_at;
        $datosFinalesEnTexto= !$forzarConfirmar && $this->clienteDaDatosFinales($message);

        $toolChoiceInicial  = $toolChoicePorPaso;
        $razonForzado       = null;

        if ($forzarConfirmar) {
            $toolChoiceInicial = ['type' => 'function', 'function' => ['name' => 'confirmar_pedido']];
            $allTools = $this->getToolsDefinicion();
            $confirmarTool = collect($allTools)->first(fn ($t) => ($t['function']['name'] ?? '') === 'confirmar_pedido');
            if ($confirmarTool) $toolsFiltradas = [$confirmarTool];
            $razonForzado = 'cliente_pidio_generar_pedido';
            $messages[] = [
                'role' => 'system',
                'content' => "🚨 OBLIGATORIO: el cliente acaba de pedir explícitamente que generes/confirmes el pedido. INVOCA `confirmar_pedido` AHORA con TODOS los datos recopilados.",
            ];
        } elseif ($estadoYaCompleto && $datosFinalesEnTexto) {
            // Estado completo + cliente dio confirmación final → forzar confirmar_pedido
            $toolChoiceInicial = ['type' => 'function', 'function' => ['name' => 'confirmar_pedido']];
            $allTools = $this->getToolsDefinicion();
            $confirmarTool = collect($allTools)->first(fn ($t) => ($t['function']['name'] ?? '') === 'confirmar_pedido');
            if ($confirmarTool) $toolsFiltradas = [$confirmarTool];
            $razonForzado = 'datos_completos_y_cliente_dio_confirmacion';
            $messages[] = [
                'role' => 'system',
                'content' => "🚨 El estado del pedido está COMPLETO y el cliente acaba de dar la confirmación final. INVOCA `confirmar_pedido` AHORA con los datos del estado.",
            ];
        } elseif ($preguntaProducto) {
            // Forzar buscar_productos cuando el cliente menciona producto/cantidad
            $toolChoiceInicial = 'required'; // que invoque ALGUNA tool, no texto
            $razonForzado = 'cliente_pregunto_producto';
            $messages[] = [
                'role' => 'system',
                'content' => "🚨 El cliente está mencionando un PRODUCTO o una CANTIDAD. ANTES DE RESPONDER, DEBES llamar `buscar_productos` con el texto literal del cliente. NO inventes productos ni precios — verifica en BD.",
            ];
        } elseif (
            $datosFinalesEnTexto &&
            !empty($estadoActualBd?->direccion) &&
            $estadoActualBd?->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_DOMICILIO &&
            !$estadoActualBd?->cobertura_validada
        ) {
            // ⭐ Caso especial: hay dirección + método=domicilio + cobertura NO validada
            // → forzar tool_choice = validar_cobertura para no llamar otras tools
            $toolChoiceInicial = ['type' => 'function', 'function' => ['name' => 'validar_cobertura']];
            $allTools = $this->getToolsDefinicion();
            $valTool = collect($allTools)->first(fn ($t) => ($t['function']['name'] ?? '') === 'validar_cobertura');
            if ($valTool) $toolsFiltradas = [$valTool];
            $razonForzado = 'cliente_dio_direccion_forzar_validar_cobertura';
            $messages[] = [
                'role' => 'system',
                'content' => "🚨 El cliente dio una dirección de despacho ({$estadoActualBd->direccion}). INVOCA `validar_cobertura` con esa dirección AHORA. NO llames otras tools. NO respondas texto.",
            ];
        } elseif ($datosFinalesEnTexto) {
            // Cliente dio datos clave (recoger, dirección, pago) pero estado aún no completo
            // Forzamos required para que llame validar_cobertura/buscar_productos/etc según faltante
            $toolChoiceInicial = 'required';
            $razonForzado = 'cliente_dio_datos_finales_estado_incompleto';
            $messages[] = [
                'role' => 'system',
                'content' => "🚨 El cliente dio datos clave del pedido (entrega/dirección/pago). DEBES llamar la tool apropiada para registrar esos datos en el estado: validar_cobertura si dió dirección, o continúa el flujo. NO respondas en texto sin tool.",
            ];
        }

        Log::info('🚦 Orquestador en acción', [
            'from'       => $from,
            'paso'       => $pasoActualOrch,
            'tool_choice'=> is_string($toolChoiceInicial) ? $toolChoiceInicial : 'function:'.($toolChoiceInicial['function']['name'] ?? '?'),
            'tools_count'=> count($toolsFiltradas),
            'forzado'    => $razonForzado,
            'msg_lower'  => mb_substr(mb_strtolower($message), 0, 100),
        ]);

        $response = $this->llamarOpenAI($messages, $toolChoiceInicial, $toolsFiltradas);

        if (!$response) {
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
            return 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';
        }

        $toolCalls   = $response['choices'][0]['message']['tool_calls'] ?? null;
        $textContent = $response['choices'][0]['message']['content'] ?? null;

        // ── Tool calls DINÁMICAS (consultas guardadas con usar_en_bot=true) ──
        // El nombre de las tools dinámicas siempre empieza con "consulta_".
        if ($toolCalls && str_starts_with($toolCalls[0]['function']['name'] ?? '', 'consulta_')) {
            $consultaSvc = app(\App\Services\ConsultaIntegracionService::class);
            $toolMessages = [];

            foreach ($toolCalls as $tc) {
                $toolName = $tc['function']['name'] ?? '';
                if (!str_starts_with($toolName, 'consulta_')) continue;

                $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];

                $consulta = \App\Models\IntegracionConsulta::query()
                    ->where('usar_en_bot', true)
                    ->where('activa', true)
                    ->get()
                    ->first(fn ($c) => $c->nombreTool() === $toolName);

                $tStart = microtime(true);
                if (!$consulta) {
                    $resultado = ['ok' => false, 'error' => 'Consulta no encontrada o inactiva.'];
                } else {
                    $resultado = $consultaSvc->ejecutar($consulta, $args, 50);
                }
                $latenciaMs = (int) ((microtime(true) - $tStart) * 1000);

                Log::info("🛠️ Tool dinámica {$toolName}", [
                    'args' => $args, 'ok' => $resultado['ok'] ?? false,
                    'total' => $resultado['total'] ?? 0, 'ms' => $latenciaMs,
                ]);

                // Persistir invocación para el monitor
                try {
                    \App\Models\AgenteToolInvocacion::create([
                        'tenant_id'        => $conversacion->tenant_id ?? null,
                        'conversacion_id'  => $conversacion->id ?? null,
                        'tool_name'        => $toolName,
                        'connection_id'    => (string) ($connectionId ?? ''),
                        'telefono_cliente' => $from ?? null,
                        'args'             => $args,
                        'resultado'        => [
                            'ok'    => $resultado['ok'] ?? false,
                            'total' => $resultado['total'] ?? 0,
                            'top'   => collect($resultado['filas'] ?? [])->take(3)->all(),
                        ],
                        'count_resultados' => (int) ($resultado['total'] ?? 0),
                        'exitoso'          => (bool) ($resultado['ok'] ?? false),
                        'error'            => $resultado['error'] ?? null,
                        'latencia_ms'      => $latenciaMs,
                    ]);
                } catch (\Throwable $e) { /* no bloquear si log falla */ }

                $toolMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'] ?? ('call_' . count($toolMessages)),
                    'name'         => $toolName,
                    'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                ];
            }

            $followUpMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $reinforceAgent ?? [],
                $conversationHistory,
                [['role' => 'assistant', 'content' => null, 'tool_calls' => $toolCalls]],
                $toolMessages
            );

            $followUp = $this->llamarOpenAI($followUpMessages);
            $reply = $followUp['choices'][0]['message']['content'] ?? null;

            // 🛡️ Mismo fallback crítico aquí
            if (empty($reply)) {
                $reply = $this->respuestaFallbackDeTools($toolMessages);
                Log::warning('🛡️ LLM falló post-tool dinámica — usando fallback', [
                    'tools' => array_map(fn ($t) => $t['name'] ?? null, $toolMessages),
                ]);
            }

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call_dinamica',
                'meta' => ['tools' => array_map(fn ($t) => $t['name'] ?? null, $toolMessages)],
            ]);

            return $reply;
        }

        // ── Tool calls de CATÁLOGO (modo agente) ──────────────────────────────
        // Si la primera tool call es una de las herramientas de consulta de
        // catálogo, las procesamos en bloque (potencialmente varias en paralelo)
        // y mandamos los results al LLM para que arme la respuesta final.
        $catalogoTools = [
            'buscar_productos', 'listar_categorias', 'productos_de_categoria', 'info_producto', 'productos_destacados',
            // Tools de info estática del tenant (datos de BD)
            'consultar_horarios', 'consultar_zonas_cobertura', 'consultar_promociones',
            'consultar_mis_pedidos',
            'crear_adicion_pedido',
        ];
        if ($toolCalls && in_array($toolCalls[0]['function']['name'] ?? '', $catalogoTools, true)) {
            $catalogoSvc = app(\App\Services\BotCatalogoToolService::class);
            $sedeIdAct   = $this->obtenerSedeIdDesdeConexion($connectionId);

            $toolMessages = [];
            foreach ($toolCalls as $tc) {
                $name = $tc['function']['name'] ?? '';
                if (!in_array($name, $catalogoTools, true)) continue;

                $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                $tStart = microtime(true);
                $exitoso = true;
                $errorMsg = null;
                try {
                    $resultado = match ($name) {
                        'buscar_productos' => $catalogoSvc->buscarProductos(
                            (string) ($args['query'] ?? ''),
                            !empty($args['categoria']) ? (string) $args['categoria'] : null,
                            min(20, max(1, (int) ($args['limite'] ?? 5))),
                            $sedeIdAct
                        ),
                        'listar_categorias' => $catalogoSvc->listarCategorias($sedeIdAct),
                        'productos_de_categoria' => $catalogoSvc->productosDeCategoria(
                            (string) ($args['categoria'] ?? ''),
                            min(50, max(1, (int) ($args['limite'] ?? 20))),
                            $sedeIdAct
                        ),
                        'info_producto' => $catalogoSvc->infoProducto(
                            (string) ($args['codigo'] ?? ''),
                            $sedeIdAct
                        ),
                        'productos_destacados' => $catalogoSvc->productosDestacados(
                            min(20, max(1, (int) ($args['limite'] ?? 8))),
                            $sedeIdAct
                        ),

                        // 🏪 Devuelve horarios REALES desde BD (no de la tabla legacy)
                        'consultar_horarios' => (function () {
                            $sedes = \App\Models\Sede::where('activa', true)->get();
                            return [
                                'sedes' => $sedes->map(fn ($s) => [
                                    'nombre'    => $s->nombre,
                                    'direccion' => $s->direccion,
                                    'estado'    => $s->estaAbierta() ? 'ABIERTA AHORA' : 'CERRADA AHORA',
                                    'hoy'       => $s->horarioHoyTexto(),
                                    'semana'    => $s->horariosCompletos(),
                                    'formato_legible' => $s->horariosFormateadosTexto(),
                                ])->values()->all(),
                                'instruccion_para_bot' => 'Usa el campo formato_legible TEXTUAL al cliente. NO conviertas los rangos a am/pm — están en 24h y así deben quedar. Si la sede está cerrada, dile la hora de apertura del día siguiente.',
                            ];
                        })(),

                        // 🗺️ Zonas de cobertura AGRUPADAS por sede (cada sede tiene sus zonas)
                        'consultar_zonas_cobertura' => (function () {
                            $sedes = \App\Models\Sede::where('activa', true)->get();
                            $zonas = \App\Models\ZonaCobertura::where('activa', true)
                                ->orderBy('orden')->orderBy('nombre')->get();

                            $sedesPayload = $sedes->map(function ($s) use ($zonas) {
                                $zonasSede = $zonas->filter(fn ($z) => $z->sede_id === $s->id || $z->sede_id === null);
                                return [
                                    'sede'   => $s->nombre,
                                    'direccion' => $s->direccion,
                                    // 💰 Datos de cobertura de la SEDE (defaults para todas sus zonas)
                                    'pedido_minimo_sede'    => (float) ($s->cobertura_pedido_minimo ?? 0),
                                    'costo_envio_default_sede' => (float) ($s->cobertura_costo_envio ?? 0),
                                    'tiempo_default_sede_min'  => (int) ($s->cobertura_tiempo_min ?? 0),
                                    'zonas'  => $zonasSede->map(fn ($z) => [
                                        'nombre'  => $z->nombre,
                                        'tiempo_min' => $z->tiempo_estimado_min,
                                        'costo_envio' => (float) ($z->costo_envio ?? 0),
                                        'global' => $z->sede_id === null,
                                    ])->values()->all(),
                                ];
                            })->values()->all();

                            return [
                                'sedes' => $sedesPayload,
                                'instruccion_para_bot' =>
                                    'Las zonas de cobertura se agrupan POR SEDE. Cada sede tiene su propio '
                                    . '`pedido_minimo_sede` (monto mínimo de compra para que esa sede acepte el pedido) '
                                    . 'y `costo_envio_default_sede`. Las zonas pueden tener costos/tiempos propios '
                                    . 'que sobrescriben el default. NUNCA inventes montos mínimos ni costos: usa '
                                    . 'EXACTO los valores numéricos del payload (formatea $X.XXX al cliente).',
                            ];
                        })(),

                        // 🎁 Promociones VIGENTES + AGRUPADAS POR SEDE (multi-tenant automático)
                        'consultar_promociones' => (function () {
                            $now = now();
                            $sedesActivas = \App\Models\Sede::where('activa', true)->get();

                            // Promociones vigentes del tenant actual (BelongsToTenant filtra por tenant_id)
                            $promosVigentes = \App\Models\Promocion::where('activa', true)
                                ->where(function ($q) use ($now) {
                                    $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $now);
                                })
                                ->where(function ($q) use ($now) {
                                    $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $now);
                                })
                                ->orderBy('orden')
                                ->orderByDesc('id')
                                ->limit(20)
                                ->get();

                            if ($promosVigentes->isEmpty()) {
                                return [
                                    'promociones_por_sede' => [],
                                    'total_promociones' => 0,
                                    'mensaje_si_vacio' => 'No hay promociones vigentes en este momento.',
                                ];
                            }

                            // Cargar sedes vinculadas vía tabla pivot promocion_sede
                            $sedesPorPromo = \DB::table('promocion_sede')
                                ->whereIn('promocion_id', $promosVigentes->pluck('id'))
                                ->get(['promocion_id', 'sede_id'])
                                ->groupBy('promocion_id')
                                ->map(fn ($rows) => $rows->pluck('sede_id')->all());

                            // 🛒 Cargar productos vinculados a cada promoción (si no aplica a todos)
                            $productosPorPromo = \DB::table('promocion_producto')
                                ->whereIn('promocion_id', $promosVigentes->pluck('id'))
                                ->get(['promocion_id', 'producto_id'])
                                ->groupBy('promocion_id');

                            $idsProductos = $productosPorPromo->flatten()->pluck('producto_id')->unique()->all();
                            $productosCatalogo = \App\Models\Producto::whereIn('id', $idsProductos)
                                ->get(['id', 'nombre', 'precio_base', 'unidad'])
                                ->keyBy('id');

                            $payloadPromo = function ($p) use ($productosPorPromo, $productosCatalogo) {
                                // Productos que aplican a esta promoción
                                $productosPromo = [];
                                if ($p->aplica_todos_productos) {
                                    $productosPromo = ['_aplica_todos' => true];
                                } else {
                                    $productosVinculados = $productosPorPromo->get($p->id, collect());
                                    foreach ($productosVinculados as $pivot) {
                                        $prod = $productosCatalogo->get($pivot->producto_id);
                                        if (!$prod) continue;
                                        $productosPromo[] = [
                                            'nombre' => $prod->nombre,
                                            'precio' => (float) ($prod->precio_base ?? 0),
                                            'unidad' => $prod->unidad,
                                        ];
                                    }
                                }

                                return [
                                    'nombre'        => $p->nombre,
                                    'descripcion'   => $p->descripcion,
                                    'tipo'          => $p->tipo,
                                    'valor'         => (float) $p->valor,
                                    'codigo_cupon'  => $p->codigo_cupon,
                                    'compra_paga'   => ($p->compra && $p->paga) ? "{$p->compra}x{$p->paga}" : null,
                                    'fecha_inicio'  => $p->fecha_inicio?->format('d/m/Y'),
                                    'fecha_fin'     => $p->fecha_fin?->format('d/m/Y'),
                                    'productos'     => $productosPromo,
                                ];
                            };

                            $sedesPayload = $sedesActivas->map(function ($s) use ($promosVigentes, $sedesPorPromo, $payloadPromo) {
                                $aplicables = $promosVigentes->filter(function ($p) use ($s, $sedesPorPromo) {
                                    if ($p->aplica_todas_sedes) return true;
                                    $vinculadas = $sedesPorPromo->get($p->id, []);
                                    return in_array($s->id, $vinculadas, true);
                                });
                                return [
                                    'sede'        => $s->nombre,
                                    'promociones' => $aplicables->map($payloadPromo)->values()->all(),
                                ];
                            })->values()->all();

                            return [
                                'promociones_por_sede' => $sedesPayload,
                                'total_promociones'    => $promosVigentes->count(),
                                'instruccion_para_bot' =>
                                    'Las promociones se aplican POR SEDE. Si una promo aparece en varias sedes, '
                                    . 'es porque está activa en todas (aplica_todas_sedes=true). NUNCA inventes '
                                    . 'promociones — usa SOLO las que aparecen en este payload. Si el array '
                                    . 'promociones de una sede está vacío, esa sede no tiene promos vigentes.\n\n'
                                    . 'CADA promoción tiene un campo `productos`:\n'
                                    . '  - Si productos = {_aplica_todos: true} → la promo aplica a TODOS los productos.\n'
                                    . '  - Si productos es un array con items → la promo aplica SOLO a esos productos.\n'
                                    . '    Cuando el cliente pregunte "qué productos están en promoción", lista esos '
                                    . 'productos con sus nombres y precios. Si la promo es por monto fijo, dile que '
                                    . 'el descuento se aplica al comprar esos productos.\n'
                                    . '  - Si productos = [] (vacío) y aplica_todos_productos=false → es promo huérfana, '
                                    . 'menciona la promo pero acláralo: "aplica a productos seleccionados, consúltame por uno específico".',
                            ];
                        })(),

                        // 📦 Pedidos del cliente que escribe (telefono whatsapp)
                        'consultar_mis_pedidos' => $this->resultadoMisPedidos($from, (int) ($args['limite'] ?? 5)),

                        // 🛒 Crea una adicion ligada a un pedido existente
                        'crear_adicion_pedido' => app(\App\Services\Bots\AdicionPedidoService::class)
                            ->crear(
                                (int) ($args['pedido_id_origen'] ?? 0),
                                is_array($args['productos'] ?? null) ? $args['productos'] : [],
                                $from
                            ),
                    };
                } catch (\Throwable $e) {
                    $resultado = ['error' => $e->getMessage()];
                    $exitoso = false;
                    $errorMsg = $e->getMessage();
                }
                $latenciaMs = (int) ((microtime(true) - $tStart) * 1000);

                $countResultados = (int) ($resultado['encontrados']
                    ?? $resultado['total_categorias']
                    ?? (isset($resultado['productos']) ? count($resultado['productos']) : 0)
                    ?? (isset($resultado['destacados']) ? count($resultado['destacados']) : 0)
                    ?? 0);

                // Log ligero — solo string args para evitar OOM en Monolog
                $argsBrief = is_array($args) ? array_map(
                    fn ($v) => is_scalar($v) ? mb_substr((string) $v, 0, 100) : '[obj]',
                    $args
                ) : [];
                Log::info("🛠️ Tool call {$name}", [
                    'args' => $argsBrief,
                    'count' => $countResultados,
                    'ms' => $latenciaMs,
                ]);

                // Persistir invocacion para el dashboard de monitoreo
                try {
                    \App\Models\AgenteToolInvocacion::create([
                        'tenant_id'        => $conversacion->tenant_id ?? null,
                        'conversacion_id'  => $conversacion->id ?? null,
                        'tool_name'        => $name,
                        'connection_id'    => (string) ($connectionId ?? ''),
                        'telefono_cliente' => $from ?? null,
                        'args'             => $args,
                        // Solo guardamos un resumen para no llenar la BD con catálogos enteros
                        'resultado'        => $this->resumirResultadoTool($name, $resultado),
                        'count_resultados' => $countResultados,
                        'exitoso'          => $exitoso,
                        'error'            => $errorMsg,
                        'latencia_ms'      => $latenciaMs,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo registrar AgenteToolInvocacion: ' . $e->getMessage());
                }

                $toolMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'] ?? ('call_' . count($toolMessages)),
                    'name'         => $name,
                    'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                ];
            }

            $followUpMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $reinforceAgent ?? [],
                $conversationHistory,
                [[
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => $toolCalls,
                ]],
                $toolMessages
            );

            // 🔄 LOOP DE TOOL CALLS: Claude puede llamar VARIAS tools en
            // secuencia (ej: buscar_productos → validar_cobertura → confirmar_pedido).
            // Iteramos hasta 4 veces o hasta que devuelva contenido de texto.
            $reply = null;
            $maxIter = 4;
            $allToolMessages = $toolMessages; // acumular para fallback

            for ($iter = 0; $iter < $maxIter; $iter++) {
                $followUp = $this->llamarOpenAI($followUpMessages);
                $msg = $followUp['choices'][0]['message'] ?? null;

                if (!$msg) break;

                $reply        = $msg['content'] ?? null;
                $nextToolCalls = $msg['tool_calls'] ?? null;

                // Si Claude respondió texto → terminamos
                if (!empty($reply)) break;

                // Si NO hay nuevas tool_calls → terminamos (caemos al fallback)
                if (empty($nextToolCalls)) break;

                // 🚨 Si Claude pide confirmar_pedido o registrar_datos_cliente,
                // SALIR del loop y dejar que el flujo principal del controller
                // procese esa tool (que sí crea pedido en BD + exporta SGI).
                // El loop solo maneja tools de consulta/lectura.
                $toolsCriticas = ['confirmar_pedido', 'registrar_datos_cliente'];
                $piderToolCritica = collect($nextToolCalls)->first(
                    fn ($tc) => in_array($tc['function']['name'] ?? '', $toolsCriticas, true)
                );

                if ($piderToolCritica) {
                    $nombreCritica = $piderToolCritica['function']['name'];
                    Log::info('🎯 LLM pidió tool crítica — delegando al flujo principal', [
                        'tool' => $nombreCritica,
                    ]);

                    // Procesar la tool crítica como si fuera la PRIMERA del request.
                    // Esto reusa toda la lógica de guardarPedidoDesdeToolCall.
                    if ($nombreCritica === 'confirmar_pedido') {
                        $orderData = json_decode($piderToolCritica['function']['arguments'] ?? '{}', true) ?: [];
                        $nombreCliente = $nombreParaPrompt ?? ($conversacion?->cliente?->nombre ?? 'Cliente');
                        return $this->guardarPedidoDesdeToolCall(
                            $orderData, $from, $nombreCliente, $conversationHistory,
                            $cacheKey, $connectionId, $conversacion, $convService
                        );
                    }
                    // registrar_datos_cliente: pasa al flujo viejo
                    break;
                }

                // Procesar nuevas tool_calls y agregarlas al thread
                Log::info('🔄 LLM pidió otra tool — iterando', [
                    'iter'  => $iter + 1,
                    'tools' => array_map(fn ($tc) => $tc['function']['name'] ?? '?', $nextToolCalls),
                ]);

                $nextToolMessages = $this->ejecutarToolCallsBatch(
                    $nextToolCalls, $conversacion, $connectionId, $from
                );

                // Push del assistant turn + tools results
                $followUpMessages[] = [
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => $nextToolCalls,
                ];
                foreach ($nextToolMessages as $tm) {
                    $followUpMessages[] = $tm;
                    $allToolMessages[]  = $tm;
                }
            }

            // 🛡️ FALLBACK: si ninguna iteración produjo texto, mostrar
            // un resumen de la primera tool (no de todas).
            if (empty($reply)) {
                $reply = $this->respuestaFallbackDeTools($toolMessages);
                Log::warning('🛡️ LLM falló post-tool tras todas las iteraciones', [
                    'from'        => $from,
                    'iteraciones' => $maxIter,
                    'tools'       => array_map(fn ($t) => $t['name'] ?? null, $allToolMessages),
                ]);
            }

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => [
                    'tools' => array_map(fn ($t) => $t['name'] ?? null, $toolMessages),
                ],
            ]);

            return $reply;
        }

        // ── Tool call: registrar_datos_cliente ────────────────────────────────
        if ($toolCalls && ($toolCalls[0]['function']['name'] ?? '') === 'registrar_datos_cliente') {
            $rawArgs = $toolCalls[0]['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];
            $tStart  = microtime(true);

            $cedulaArg = trim((string) ($args['cedula'] ?? ''));
            $emailArg  = trim((string) ($args['email'] ?? ''));

            $cambios = [];
            if ($cedulaArg !== '' && empty($cliente->cedula)) {
                $cambios['cedula'] = preg_replace('/[^0-9]/', '', $cedulaArg);
            }
            if ($emailArg !== '' && filter_var($emailArg, FILTER_VALIDATE_EMAIL) && empty($cliente->email)) {
                $cambios['email'] = strtolower($emailArg);
            }

            $resultado = [
                'ok'              => !empty($cambios),
                'guardados'       => array_keys($cambios),
                'cedula_actual'   => $cliente->cedula,
                'email_actual'    => $cliente->email,
            ];

            if (!empty($cambios)) {
                $cliente->update($cambios);
                $cliente->refresh();
                $resultado['cedula_actual'] = $cliente->cedula;
                $resultado['email_actual']  = $cliente->email;
            }

            $latenciaMs = (int) ((microtime(true) - $tStart) * 1000);
            Log::info('🆔 Tool call registrar_datos_cliente', ['cambios' => $cambios, 'cliente_id' => $cliente->id]);

            // Persistir en el monitor
            try {
                \App\Models\AgenteToolInvocacion::create([
                    'tenant_id'        => $conversacion->tenant_id ?? null,
                    'conversacion_id'  => $conversacion->id ?? null,
                    'tool_name'        => 'registrar_datos_cliente',
                    'connection_id'    => (string) ($connectionId ?? ''),
                    'telefono_cliente' => $from ?? null,
                    'args'             => $args,
                    'resultado'        => $resultado,
                    'count_resultados' => count($cambios),
                    'exitoso'          => true,
                    'latencia_ms'      => $latenciaMs,
                ]);
            } catch (\Throwable $e) { /* ignorar */ }

            $followUpMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $reinforceAgent ?? [],
                $conversationHistory,
                [['role' => 'assistant', 'content' => null, 'tool_calls' => $toolCalls]],
                [[
                    'role'         => 'tool',
                    'tool_call_id' => $toolCalls[0]['id'] ?? 'call_1',
                    'name'         => 'registrar_datos_cliente',
                    'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                ]]
            );

            $followUp = $this->llamarOpenAI($followUpMessages);
            $reply    = $followUp['choices'][0]['message']['content'] ?? 'Listo, ya quedó registrado 🙌';

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => ['tool' => 'registrar_datos_cliente', 'cambios' => $cambios],
            ]);

            return $reply;
        }

        // ── Tool call: validar_cobertura ──────────────────────────────────────
        // El bot pregunta si una dirección está cubierta. NO confirma pedido.
        // Devuelve un "tool result" como mensaje del bot y guarda en el historial
        // para que la siguiente turn de OpenAI incorpore la respuesta.
        if ($toolCalls && ($toolCalls[0]['function']['name'] ?? '') === 'validar_cobertura') {
            $rawArgs = $toolCalls[0]['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];

            $direccion = trim((string) ($args['direccion'] ?? ''));
            $barrio    = trim((string) ($args['barrio'] ?? ''));
            $ciudad    = trim((string) ($args['ciudad'] ?? 'Bello'));

            Log::info('🗺️ Tool call validar_cobertura', compact('from', 'direccion', 'barrio', 'ciudad'));

            $sedeId    = $this->obtenerSedeIdDesdeConexion($connectionId);
            $resultado = $this->validarCoberturaDireccion($direccion, $barrio, $ciudad, $sedeId, $from);

            // 🎯 PERSISTIR resultado de cobertura en estado estructurado
            try {
                $estadoSrv2 = app(\App\Services\EstadoPedidoService::class);
                $estadoActual = $estadoSrv2->obtener($conversacion);
                if ($direccion && empty($estadoActual->direccion)) {
                    $estadoActual->direccion = $direccion;
                }
                if ($barrio && empty($estadoActual->barrio)) {
                    $estadoActual->barrio = $barrio;
                }
                if ($ciudad && empty($estadoActual->ciudad)) {
                    $estadoActual->ciudad = $ciudad;
                }
                $estadoActual->save();
                $estadoSrv2->captarCobertura($conversacion, $resultado);
            } catch (\Throwable $e) {
                Log::warning('No se pudo persistir cobertura: ' . $e->getMessage());
            }

            // 🛡️ INSTRUCCIÓN INTERNA al LLM (NO al cliente): si cobertura OK,
            // el siguiente paso DEBE ser confirmar_pedido. Va como system msg
            // separado, NO dentro del JSON del tool result (porque el LLM
            // tiende a copiar lo que ve en el JSON).
            $instruccionInternaPostCobertura = ($resultado['cubierta'] ?? false)
                ? "🚨 SISTEMA: la cobertura ya quedó validada. Tu siguiente acción OBLIGATORIA es invocar `confirmar_pedido` con todos los datos recopilados. NO repitas la validación de cobertura. NO digas 'te despachamos' en texto. INVOCA LA FUNCIÓN."
                : "🚨 SISTEMA: cobertura NO disponible en esta dirección. Ofrece al cliente recoger en sede o cambiar de dirección. NO confirmes pedido aún.";

            // Respuesta de la tool para OpenAI — formato segunda llamada
            $toolResponseMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $conversationHistory,
                [[
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => $toolCalls,
                ]],
                [[
                    'role'         => 'tool',
                    'tool_call_id' => $toolCalls[0]['id'] ?? 'call_1',
                    'name'         => 'validar_cobertura',
                    'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                ]],
                [['role' => 'system', 'content' => $instruccionInternaPostCobertura]]
            );

            $followUp = $this->llamarOpenAI($toolResponseMessages);
            $reply    = $followUp['choices'][0]['message']['content']
                ?? ($resultado['mensaje_sugerido'] ?? 'Déjame verificar tu dirección un momento 🙌');

            // 🛡️ Si el LLM tras validar_cobertura llamó otra tool (en vez de
            // responder texto), procesar esa tool en cascada — caso típico:
            // valida cobertura → confirmar_pedido en el mismo flujo.
            $followUpToolCalls = $followUp['choices'][0]['message']['tool_calls'] ?? null;
            if ($followUpToolCalls && ($followUpToolCalls[0]['function']['name'] ?? '') === 'confirmar_pedido') {
                $orderDataPost = json_decode($followUpToolCalls[0]['function']['arguments'] ?? '{}', true) ?: [];
                $orderDataPost['products'] = array_values(array_filter($orderDataPost['products'] ?? [], fn ($p) => !empty($p['name'])));

                if (!empty($orderDataPost['products'])) {
                    Log::info('✅ Cascada: validar_cobertura → confirmar_pedido', ['from' => $from]);

                    $faltantesPost = $this->validarDatosObligatoriosPedido($orderDataPost);
                    if (!empty($faltantesPost)) {
                        $listaPost = implode(', ', $faltantesPost);
                        $replyPost = "Para registrar tu pedido necesito estos datos: {$listaPost}. ¿Me los compartes?";
                        $conversationHistory[] = ['role' => 'assistant', 'content' => $replyPost];
                        Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                        $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $replyPost);
                        return $replyPost;
                    }

                    return $this->guardarPedidoDesdeToolCall(
                        $orderDataPost, $from, $name, $conversationHistory,
                        $cacheKey, $connectionId, $conversacion, $convService
                    );
                }
            }

            // 🛡️ Guard: el bot suele decir "Genial te despachamos" después de
            // validar_cobertura sin haber llamado confirmar_pedido. Si lo hace,
            // forzamos el retry con tool_choice.
            $replyConGuard = $this->aplicarGuardFalsaConfirmacion(
                $reply, $toolResponseMessages, $from, $name, $conversationHistory,
                $cacheKey, $connectionId, $conversacion, $convService, 'validar_cobertura'
            );
            if ($replyConGuard !== $reply) {
                return $replyConGuard;
            }

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => ['tool' => 'validar_cobertura', 'resultado' => $resultado],
            ]);

            return $reply;
        }

        // ── Tool call: verificar_cliente_erp ──────────────────────────────────
        // Bot llama esta tool con la cédula del cliente. El sistema busca en
        // TblTerceros del ERP. Si existe, devuelve sus datos (el bot continúa
        // sin pedir más datos). Si no existe, devuelve los campos faltantes
        // (el bot los pide uno por uno y luego llama confirmar_pedido).
        if ($toolCalls && ($toolCalls[0]['function']['name'] ?? '') === 'verificar_cliente_erp') {
            $rawArgs = $toolCalls[0]['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];

            $cedula   = trim((string) ($args['cedula'] ?? ''));
            $telefono = trim((string) ($args['telefono'] ?? $from));

            // 🛡️ ASTUTO: validar que la cédula no sea el teléfono del cliente
            // o un celular Colombia (3XXXXXXXXX). El LLM a veces se confunde
            // cuando el cliente dice "transferencia 3216499744" y manda el
            // número de Nequi como cédula.
            $telCleaned = preg_replace('/[^\d]/', '', (string) $from);
            $cedClean   = preg_replace('/[^\d]/', '', $cedula);
            $esTelefono = $telCleaned !== '' && (
                $cedClean === $telCleaned
                || str_ends_with($telCleaned, $cedClean)
                || str_ends_with($cedClean, $telCleaned)
            );
            $esCelularCol = preg_match('/^3\d{9}$/', $cedClean) === 1
                          || preg_match('/^573\d{9}$/', $cedClean) === 1;

            if ($cedClean !== '' && ($esTelefono || $esCelularCol)) {
                Log::warning('🛡️ verificar_cliente_erp: cédula recibida parece teléfono — usando la del cliente local', [
                    'cedula_llm' => $cedula,
                    'from'       => $from,
                ]);

                // Intentar usar la cédula del cliente local (perfil) si existe
                $clienteLocal = \App\Models\Cliente::where('telefono_normalizado', $telCleaned)->first();
                if ($clienteLocal && !empty($clienteLocal->cedula)) {
                    $cedula = $clienteLocal->cedula;
                    Log::info('🛡️ Cédula corregida a la del cliente local', ['cedula' => $cedula]);
                } else {
                    // Sin cédula real → respuesta directa pidiéndola
                    return "Disculpa, el número que me diste parece ser un celular/cuenta de Nequi. "
                         . "¿Me puedes pasar tu *número de cédula* (sin puntos)? Así te registro bien el pedido.";
                }
            }

            Log::info('🔍 Tool call verificar_cliente_erp', compact('from', 'cedula', 'telefono'));

            $tenantId = app(\App\Services\TenantManager::class)->id();
            $integ = \App\Models\Integracion::where('tenant_id', $tenantId)
                ->where('activo', true)
                ->where('exporta_pedidos', true)
                ->get()
                ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

            $resultado = ['existe' => false, 'datos' => null, 'campos_faltantes' => []];

            if ($integ && $cedula) {
                $clienteErp = app(\App\Services\ClienteErpService::class)
                    ->buscar($integ, $cedula, $telefono);

                if ($clienteErp) {
                    $resultado = [
                        'existe' => true,
                        'datos'  => [
                            'cedula'    => $cedula,
                            'nombre'    => $clienteErp['StrNombre']    ?? null,
                            'telefono'  => $clienteErp['StrCelular']   ?? null,
                            'direccion' => $clienteErp['StrDireccion'] ?? null,
                        ],
                        'mensaje' => "Cliente registrado: {$clienteErp['StrNombre']}. NO pidas más datos personales — continúa con el pedido.",
                    ];
                } else {
                    $req = $integ->config['cliente_lookup']['campos_requeridos'] ?? [];
                    $resultado = [
                        'existe' => false,
                        'campos_faltantes' => array_values(array_diff($req, ['cedula','telefono'])),
                        'mensaje' => "Cliente NO está registrado. Pídele UNO POR UNO los siguientes datos antes de confirmar pedido: " . implode(', ', $req),
                    ];
                }
            } else {
                $resultado['mensaje'] = "Lookup no configurado en este tenant — continúa el flujo normal del pedido.";
            }

            // 🎯 PERSISTIR resultado del lookup ERP en estado estructurado
            try {
                app(\App\Services\EstadoPedidoService::class)
                    ->captarClienteErp($conversacion, $resultado, $cedula);
            } catch (\Throwable $e) {
                Log::warning('No se pudo persistir lookup ERP: ' . $e->getMessage());
            }

            // Respuesta de la tool para OpenAI
            $toolResponseMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $conversationHistory,
                [[
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => $toolCalls,
                ]],
                [[
                    'role'         => 'tool',
                    'tool_call_id' => $toolCalls[0]['id'] ?? 'call_1',
                    'name'         => 'verificar_cliente_erp',
                    'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                ]]
            );

            $followUp = $this->llamarOpenAI($toolResponseMessages);
            $reply    = $followUp['choices'][0]['message']['content'] ?? 'Un momento, verificando tus datos 🙌';

            // 🛡️ Guard: si el cliente existe en ERP, el bot debería pasar de
            // verificar_cliente_erp → confirmar_pedido directamente. A veces
            // intenta cerrar con frase tipo "queda listo". Forzamos retry.
            $replyConGuard = $this->aplicarGuardFalsaConfirmacion(
                $reply, $toolResponseMessages, $from, $name, $conversationHistory,
                $cacheKey, $connectionId, $conversacion, $convService, 'verificar_cliente_erp'
            );
            if ($replyConGuard !== $reply) {
                return $replyConGuard;
            }

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => ['tool' => 'verificar_cliente_erp', 'resultado' => $resultado],
            ]);

            return $reply;
        }

        // ── Tool call: derivar_a_departamento ─────────────────────────────────
        if ($toolCalls && ($toolCalls[0]['function']['name'] ?? '') === 'derivar_a_departamento') {
            $rawArgs = $toolCalls[0]['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];
            $nombreDpto = trim((string) ($args['departamento'] ?? ''));
            $razon      = trim((string) ($args['razon'] ?? ''));
            $urgencia   = strtolower(trim((string) ($args['urgencia'] ?? 'media')));

            Log::info('🎯 Tool call derivar_a_departamento', compact('from', 'nombreDpto', 'razon', 'urgencia'));

            $depto = \App\Models\Departamento::where('activo', true)
                ->where('nombre', $nombreDpto)
                ->first();

            if (!$depto) {
                // Si la IA escribe mal el nombre, caer a flujo normal
                $reply = "Un momento {$name}, estoy verificando eso para ti.";
            } else {
                // Derivar la conversación
                $conversacion->update([
                    'departamento_id'     => $depto->id,
                    'derivada_at'         => now(),
                    'atendida_por_humano' => true,
                ]);

                // Notificar al equipo del departamento con contexto + razón + urgencia
                if ($depto->notificar_internos) {
                    $emoji = match ($urgencia) {
                        'critica' => '🚨🚨🚨',
                        'alta'    => '🚨',
                        'baja'    => '📬',
                        default   => '🔔',
                    };
                    try {
                        $usuarios = \App\Models\UsuarioInternoWhatsapp::withoutGlobalScopes()
                            ->where('tenant_id', $depto->tenant_id)
                            ->where('departamento_id', $depto->id)
                            ->where('activo', true)
                            ->get();

                        $texto = "{$emoji} *Derivación automática a {$depto->nombre}*\n\n"
                               . "👤 *Cliente:* {$name}\n"
                               . "📞 *Teléfono:* {$from}\n"
                               . "🔖 *Urgencia:* " . strtoupper($urgencia) . "\n\n"
                               . "💬 *Razón:* {$razon}\n\n"
                               . "📝 *Mensaje original:*\n" . mb_strimwidth($message, 0, 250, '…') . "\n\n"
                               . "_Revisa la plataforma para responder._";

                        $sender = app(\App\Services\WhatsappSenderService::class);
                        foreach ($usuarios as $u) {
                            $sender->enviarTexto($u->telefono_normalizado, $texto, $conversacion->connection_id);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Fallo notificar derivación IA: ' . $e->getMessage());
                    }
                }

                $reply = $depto->saludo_automatico
                    ?: "Entiendo {$name} 🙏 Voy a pasarte con un asesor de *{$depto->nombre}* que te atenderá en unos minutos.";
            }

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => [
                    'tool'         => 'derivar_a_departamento',
                    'departamento' => $nombreDpto,
                    'razon'        => $razon,
                    'urgencia'     => $urgencia,
                ],
            ]);
            return $reply;
        }

        // ── Tool call: enviar_imagen_producto ─────────────────────────────────
        if ($toolCalls && ($toolCalls[0]['function']['name'] ?? '') === 'enviar_imagen_producto') {
            $rawArgs = $toolCalls[0]['function']['arguments'] ?? '{}';
            $args    = json_decode($rawArgs, true) ?: [];

            $codigos = $args['codigos'] ?? [];
            $msg     = trim((string) ($args['mensaje_acompañante'] ?? ''));

            Log::info('📷 Tool call enviar_imagen_producto', compact('from', 'codigos', 'msg'));

            // Enviar las imágenes (respeta config y max_imagenes_por_mensaje)
            $enviadas = $this->enviarImagenesProductos($from, (array) $codigos, $connectionId);

            // Si la IA mandó un mensaje acompañante, también lo guardamos en historial
            $reply = $msg !== ''
                ? $msg
                : ($enviadas > 0
                    ? "Te mandé {$enviadas} foto(s) 📸"
                    : "No tengo fotos disponibles de eso ahora 😅");

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            // Persistir respuesta del bot en BD
            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => ['tool' => 'enviar_imagen_producto', 'codigos' => $codigos, 'imagenes_enviadas' => $enviadas],
            ]);

            return $reply;
        }

        if ($toolCalls && ($toolCalls[0]['function']['name'] ?? '') === 'confirmar_pedido') {
            $rawArgs   = $toolCalls[0]['function']['arguments'] ?? '{}';
            $orderData = json_decode($rawArgs, true);

            $orderData['products'] = array_values(array_filter($orderData['products'] ?? [], function ($p) {
                return !empty($p['name']);
            }));

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('❌ JSON inválido en tool_call', ['raw' => $rawArgs]);
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                return '⚠️ Hubo un problema al procesar tu pedido. Por favor indícame nuevamente qué deseas pedir.';
            }

            if (empty($orderData['products'])) {
                Log::error('❌ Tool call sin productos válidos', ['raw' => $rawArgs]);
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                return '⚠️ No pude identificar los productos del pedido. Por favor indícame qué deseas pedir.';
            }

            Log::info('🎯 CAPA 3: Function call confirmar_pedido', compact('from', 'orderData'));

            // 🎯 PERSISTIR estado estructurado en BD ANTES de validar.
            // Aunque el guard rechace por datos faltantes, lo que el bot
            // recolectó queda guardado y el siguiente turno lo aprovecha.
            try {
                app(\App\Services\EstadoPedidoService::class)
                    ->captarDeOrderData($conversacion, $orderData);
            } catch (\Throwable $e) {
                Log::warning('No se pudo persistir estado pedido: ' . $e->getMessage());
            }

            // 🛡️ VALIDACIÓN DETERMINISTA: el bot debe enviar TODOS los datos
            // requeridos antes de confirmar. Si falta alguno, rechazamos y le
            // pedimos al bot que los recopile primero.
            // Esto NO depende del LLM seguir reglas — es código duro.
            $faltantes = $this->validarDatosObligatoriosPedido($orderData);
            if (!empty($faltantes)) {
                $listaFaltantes = implode(', ', $faltantes);
                Log::warning('🚨 GUARD: confirmar_pedido sin datos obligatorios — rechazado', [
                    'from' => $from,
                    'faltantes' => $faltantes,
                ]);

                // Mensaje natural al cliente pidiendo los datos faltantes
                $reply = "Para registrar tu pedido necesito estos datos: {$listaFaltantes}. ¿Me los compartes?";
                $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                return $reply;
            }

            // 🛡️ GUARD CRÍTICO ANTES DE GUARDAR: el bot llamó confirmar_pedido
            // pero ¿realmente el cliente pidió algo? Si el último mensaje del
            // cliente es solo un saludo, el bot está alucinando datos viejos.
            //
            // EXCEPCIÓN: si el estado estructurado en BD tiene productos +
            // entrega + identificación coherentes con el orderData, el pedido
            // es LEGÍTIMO aunque los últimos mensajes sean datos finales como
            // email o teléfono. NO bloquear.
            $estadoBd = $conversacion ? app(\App\Services\EstadoPedidoService::class)->obtener($conversacion) : null;
            $estadoCoherente = $estadoBd
                && !empty($estadoBd->productos)
                && !empty($estadoBd->metodo_entrega)
                && (!empty($estadoBd->cedula) || !empty($estadoBd->nombre_cliente))
                && !empty($orderData['products']);

            if (!$estadoCoherente && $this->esIntentoConfirmacionFalsa($conversationHistory)) {
                Log::warning('🚨 GUARD: bot intentó confirmar_pedido sin intención real del cliente', [
                    'from' => $from,
                    'orderData' => $orderData,
                ]);

                $reply = "¡Hola! 👋 Bienvenido. ¿Qué se te antoja hoy? Dime el producto y la cantidad y te armo el pedido.";
                $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                return $reply;
            }

            if ($estadoCoherente) {
                Log::info('✅ Estado coherente — saltando guard de confirmación falsa', [
                    'from'         => $from,
                    'productos'    => count($estadoBd->productos ?: []),
                    'metodo'       => $estadoBd->metodo_entrega,
                    'tiene_cedula' => !empty($estadoBd->cedula),
                ]);
            }

            return $this->guardarPedidoDesdeToolCall(
                $orderData,
                $from,
                $name,
                $conversationHistory,
                $cacheKey,
                $connectionId,
                $conversacion,
                $convService
            );
        }

        $reply = $textContent
            ?? 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';

        // 🎯 DETECTOR DE ALUCINACIÓN DE DERIVACIÓN:
        // Si el bot dice "voy a derivar" / "te paso con..." SIN haber llamado la tool,
        // nosotros derivamos manualmente al departamento_fallback para no dejar al cliente esperando.
        $config = \App\Models\ConfiguracionBot::actual();
        if (($config->derivacion_activa ?? true) && ($config->derivacion_fallback_activa ?? true)) {
            $frasesRaw = trim((string) ($config->derivacion_frases_deteccion
                ?: \App\Models\ConfiguracionBot::DERIVACION_FRASES_DEFAULT));
            $frases = array_values(array_filter(array_map(fn ($f) => mb_strtolower(trim($f)), explode(',', $frasesRaw))));

            $replyLower = mb_strtolower($reply);
            $matched = null;
            foreach ($frases as $f) {
                if ($f !== '' && str_contains($replyLower, $f)) { $matched = $f; break; }
            }

            if ($matched) {
                $deptoFallback = null;
                if ($config->derivacion_departamento_fallback_id) {
                    $deptoFallback = \App\Models\Departamento::where('activo', true)->find($config->derivacion_departamento_fallback_id);
                }
                if (!$deptoFallback) {
                    // Usar el primero activo como fallback
                    $deptoFallback = \App\Models\Departamento::where('activo', true)->orderBy('orden')->first();
                }

                if ($deptoFallback) {
                    Log::warning('🛟 Fallback derivación: bot anunció pero no llamó tool, derivamos al departamento configurado', [
                        'frase_detectada' => $matched,
                        'departamento'    => $deptoFallback->nombre,
                        'reply_original'  => mb_substr($reply, 0, 200),
                    ]);

                    // Marcar la conversación
                    $conversacion->update([
                        'departamento_id'     => $deptoFallback->id,
                        'derivada_at'         => now(),
                        'atendida_por_humano' => true,
                    ]);

                    // Notificar al equipo
                    if ($deptoFallback->notificar_internos) {
                        try {
                            $usuarios = \App\Models\UsuarioInternoWhatsapp::withoutGlobalScopes()
                                ->where('tenant_id', $deptoFallback->tenant_id)
                                ->where('departamento_id', $deptoFallback->id)
                                ->where('activo', true)
                                ->get();
                            $texto = "🔔 *Derivación (fallback) a {$deptoFallback->nombre}*\n\n"
                                   . "👤 *Cliente:* {$name}\n"
                                   . "📞 *Teléfono:* {$from}\n\n"
                                   . "💬 *Mensaje:*\n" . mb_strimwidth($message, 0, 250, '…') . "\n\n"
                                   . "_La IA anunció derivar pero no invocó la función. Revisa la plataforma._";
                            $sender = app(\App\Services\WhatsappSenderService::class);
                            foreach ($usuarios as $u) {
                                $sender->enviarTexto($u->telefono_normalizado, $texto, $conversacion->connection_id);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Fallo notificar fallback: ' . $e->getMessage());
                        }
                    }

                    // Reemplazar el texto del bot por el saludo del departamento
                    $reply = $deptoFallback->saludo_automatico
                        ?: "Entiendo {$name} 🙏 Voy a pasarte con un asesor de *{$deptoFallback->nombre}* que te atenderá en unos minutos.";
                }
            }
        }

        // 🛑 DETECTOR DE ALUCINACIÓN DE CONFIRMACIÓN:
        // Si el bot dice "pedido registrado / confirmado / va en camino"
        // pero NO llamó a confirmar_pedido → es una mentira. Registramos
        // alerta operativa para que el admin lo vea y corrijamos el prompt.
        // 🛡️ Guard: si el bot insinuó que confirmó pero NO llamó la función,
        // forzar retry con tool_choice = confirmar_pedido.
        $replyConGuard = $this->aplicarGuardFalsaConfirmacion(
            $reply, $messages, $from, $name, $conversationHistory,
            $cacheKey, $connectionId, $conversacion, $convService, 'main'
        );
        if ($replyConGuard !== $reply) {
            // Recovery exitoso (o alerta registrada). Devolver inmediatamente.
            return $replyConGuard;
        }

        $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
        Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

        // Persistir respuesta del bot en BD
        $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply);

        Log::info('💬 CAPA 3: Respuesta conversacional IA', compact('from', 'reply'));

        return $reply;
    }

    /**
     * Detecta si el bot dijo que confirmó/registró un pedido SIN haber llamado
     * la función. Si encuentra la frase, retorna la frase detectada; sino, null.
     */
    /**
     * 🛡️ Aplica el guard de "falsa confirmación" sobre $reply.
     * Si detecta que el bot sugirió que confirmó el pedido SIN haber llamado
     * confirmar_pedido, fuerza un retry con tool_choice = confirmar_pedido.
     * Si el retry tiene éxito, retorna el reply nuevo (con pedido creado).
     * Si falla, registra alerta y retorna el reply original.
     *
     * IMPORTANTE: este método ejecuta side effects (guarda pedido en BD,
     * persiste mensajes, actualiza cache) cuando logra recovery.
     */
    private function aplicarGuardFalsaConfirmacion(
        string $reply,
        array $messages,
        string $from,
        string $name,
        array &$conversationHistory,
        string $cacheKey,
        ?int $connectionId,
        $conversacion,
        $convService,
        string $contextoTool = 'general'
    ): string {
        $frase = $this->detectarFalsaConfirmacion($reply);
        if (!$frase) return $reply;

        // 🛡️ WHITELIST CONTEXTUAL: si el estado tiene productos vacíos
        // y el paso es 'inicio' (saludo/sin intención de pedido), las
        // frases tipo "te despachamos mañana" en futuro condicional NO
        // son alucinación — son saludos de cierre legítimos.
        try {
            $estadoBd = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
            $sinProductos = empty($estadoBd->productos);
            $pasoInicial = in_array($estadoBd->paso_actual, [
                \App\Models\ConversacionPedidoEstado::PASO_INICIO,
                \App\Models\ConversacionPedidoEstado::PASO_ABANDONADO,
            ], true);

            if ($sinProductos && $pasoInicial) {
                Log::info('🛡️ Guard de alucinación SUPRIMIDO (saludo sin intención de pedido)', [
                    'from'  => $from,
                    'frase' => $frase,
                    'paso'  => $estadoBd->paso_actual,
                ]);
                return $reply; // dejar pasar — no es alucinación, es futuro condicional
            }
        } catch (\Throwable $e) {
            // si falla la consulta, mantener comportamiento original
        }

        Log::warning('⚠️ ALUCINACIÓN detectada — delegando al BOT CIERRE', [
            'from'     => $from,
            'frase'    => $frase,
            'contexto' => $contextoTool,
            'reply'    => mb_substr($reply, 0, 300),
        ]);

        // 🤖 DELEGAR AL BOT CIERRE: agente especializado que solo cierra pedidos.
        // Si tiene éxito (vía estado BD o LLM mini con tool_choice forzado),
        // procesamos su orderData con guardarPedidoDesdeToolCall.
        try {
            $cierreResult = app(\App\Services\Bots\BotCierreService::class)
                ->intentarCierre($conversacion);

            if ($cierreResult['ok']) {
                Log::info('🤖✅ BotCierre tomó el control y cerró el pedido', [
                    'from' => $from,
                    'via'  => $cierreResult['via'],
                ]);

                return $this->guardarPedidoDesdeToolCall(
                    $cierreResult['orderData'], $from, $name, $conversationHistory,
                    $cacheKey, $connectionId, $conversacion, $convService
                );
            }

            // BotCierre dijo que no puede — registramos por qué
            Log::info('🤖❌ BotCierre no pudo cerrar', [
                'from'  => $from,
                'razon' => $cierreResult['razon'] ?? '?',
                'faltantes' => $cierreResult['faltantes'] ?? null,
                'pedido_id' => $cierreResult['pedido_id'] ?? null,
            ]);

            // 🔁 CASO: pedido anterior YA confirmado. PERO un cliente puede
            // hacer N pedidos al día. Distinguir 2 escenarios:
            //
            //   A) Cliente saluda / dice algo ambiguo → bot alucina con datos
            //      del pedido anterior → BLOQUEAR (es inercia/duplicado).
            //
            //   B) Cliente está pidiendo algo NUEVO (productos distintos a los
            //      del pedido anterior, o claramente expresa que quiere otro)
            //      → RESETEAR estado preservando identidad y dejar continuar
            //      el flujo. NO bloquear.
            if (($cierreResult['razon'] ?? '') === 'ya_confirmado') {
                $pedidoIdAnterior = $cierreResult['pedido_id'] ?? null;

                // Detectar si es nuevo pedido legítimo:
                //   - El estado actual tiene productos distintos a los del pedido anterior
                //   - El último mensaje del cliente expresa intención de continuar/confirmar
                $esNuevoPedido = false;
                try {
                    $estadoBd = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
                    $productosEstado = collect($estadoBd->productos ?: [])
                        ->map(fn ($p) => mb_strtolower(trim((string) ($p['name'] ?? ''))))
                        ->filter()->all();

                    $pedidoAnterior = $pedidoIdAnterior ? \App\Models\Pedido::with('detalles')->find($pedidoIdAnterior) : null;
                    $productosAnterior = $pedidoAnterior
                        ? collect($pedidoAnterior->detalles ?? [])
                            ->map(fn ($d) => mb_strtolower(trim((string) ($d->descripcion ?? $d->nombre_producto ?? ''))))
                            ->filter()->all()
                        : [];

                    // Si los productos NO coinciden → es nuevo pedido legítimo
                    if (!empty($productosEstado) && $productosEstado !== $productosAnterior) {
                        $esNuevoPedido = true;
                    }

                    // O si el último mensaje del cliente es claramente confirmación
                    $ultMsgUser = collect($conversationHistory)
                        ->where('role', 'user')->last()['content'] ?? '';
                    if (mb_strlen(trim($ultMsgUser)) > 0 &&
                        preg_match('/\b(s[ií]|dale|listo|confirmo|confirmado|ok|conf[ií]rmalo|otro|quiero)\b/iu', mb_strtolower($ultMsgUser))
                    ) {
                        $esNuevoPedido = true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('No se pudo evaluar nuevo pedido tras ya_confirmado: ' . $e->getMessage());
                }

                if ($esNuevoPedido && $pedidoIdAnterior) {
                    Log::info('🔁 ya_confirmado pero parece nuevo pedido — reseteando y procesando', [
                        'from' => $from,
                        'pedido_anterior' => $pedidoIdAnterior,
                    ]);

                    // Resetear estado preservando identidad (cédula/nombre/email/teléfono)
                    try {
                        app(\App\Services\EstadoPedidoService::class)
                            ->resetear($conversacion, "nuevo_pedido_tras_{$pedidoIdAnterior}");
                    } catch (\Throwable $e) {
                        Log::warning('Error reset tras ya_confirmado: ' . $e->getMessage());
                    }

                    // Mensaje amistoso que invita a continuar el flujo nuevo
                    $replyOk = "¡Perfecto! 😊 Tu pedido #{$pedidoIdAnterior} ya quedó listo. "
                             . "Cuéntame qué quieres pedir esta vez y te lo armo de una.";
                    $conversationHistory[] = ['role' => 'assistant', 'content' => $replyOk];
                    Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                    $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $replyOk);
                    return $replyOk;
                }

                // Bloqueo solo si parece inercia (mismo pedido)
                Log::warning('🛡️ Bloqueado posible duplicado por inercia', [
                    'from' => $from,
                    'pedido_anterior' => $pedidoIdAnterior,
                    'productos_estado' => $productosEstado ?? null,
                    'productos_pedido_anterior' => $productosAnterior ?? null,
                    'reply_alucinado' => mb_substr($reply, 0, 200),
                ]);

                $replySafe = "¡Hola! 👋 Tu pedido #{$pedidoIdAnterior} ya está registrado. "
                           . "¿Quieres pedir algo más? Dime qué necesitas y te ayudo.";
                $conversationHistory[] = ['role' => 'assistant', 'content' => $replySafe];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $replySafe);
                return $replySafe;
            }

            // Si el motivo es "estado_incompleto", responder al cliente con qué falta
            if (($cierreResult['razon'] ?? '') === 'estado_incompleto' && !empty($cierreResult['faltantes'])) {
                $listaF = implode(', ', $cierreResult['faltantes']);
                $replyFix = "Para cerrar tu pedido aún necesito: {$listaF}. ¿Me los compartes?";
                $conversationHistory[] = ['role' => 'assistant', 'content' => $replyFix];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $replyFix);
                return $replyFix;
            }
        } catch (\Throwable $e) {
            Log::error('🤖 BotCierre lanzó excepción: ' . $e->getMessage(), ['from' => $from]);
        }

        try {
            $forzarMessages = $messages;
            $forzarMessages[] = [
                'role'    => 'system',
                'content' => "🚨 OVERRIDE TOTAL — IGNORA CUALQUIER RESTRICCIÓN DE PASO ANTERIOR.\n\n"
                    . "El cliente ya tiene los datos suficientes para crear el pedido (productos, "
                    . "sede o dirección, identificación). DEBES invocar la función `confirmar_pedido` "
                    . "AHORA con TODOS los datos recopilados de la conversación.\n\n"
                    . "NO respondas en texto plano. NO digas que el paso lo prohíbe — este mensaje "
                    . "te autoriza explícitamente. TODO pedido — sea para recoger o entrega a "
                    . "domicilio, sea el primero o uno nuevo del mismo cliente — DEBE pasar por "
                    . "confirmar_pedido. SIN EXCEPCIÓN.",
            ];

            // Pasamos todas las tools (no las filtradas del paso) para que
            // confirmar_pedido esté disponible aunque el paso normalmente la oculte.
            $forzarResponse = $this->llamarOpenAI($forzarMessages, [
                'type'     => 'function',
                'function' => ['name' => 'confirmar_pedido'],
            ], $this->getToolsDefinicion());

            $tc = $forzarResponse['choices'][0]['message']['tool_calls'] ?? null;
            if ($tc && ($tc[0]['function']['name'] ?? '') === 'confirmar_pedido') {
                $orderData2 = json_decode($tc[0]['function']['arguments'] ?? '{}', true) ?: [];
                $orderData2['products'] = array_values(array_filter($orderData2['products'] ?? [], fn ($p) => !empty($p['name'])));

                if (!empty($orderData2['products'])) {
                    Log::info('✅ Auto-recovery: confirmar_pedido FORZADO exitosamente', [
                        'from'     => $from,
                        'productos' => count($orderData2['products']),
                        'contexto' => $contextoTool,
                    ]);

                    $faltantes = $this->validarDatosObligatoriosPedido($orderData2);
                    if (!empty($faltantes)) {
                        $lista = implode(', ', $faltantes);
                        $nuevo = "Para registrar tu pedido necesito estos datos: {$lista}. ¿Me los compartes?";
                        $conversationHistory[] = ['role' => 'assistant', 'content' => $nuevo];
                        Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                        $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $nuevo);
                        return $nuevo;
                    }

                    return $this->guardarPedidoDesdeToolCall(
                        $orderData2,
                        $from,
                        $name,
                        $conversationHistory,
                        $cacheKey,
                        $connectionId,
                        $conversacion,
                        $convService
                    );
                }
            }

            Log::warning('⚠️ Auto-recovery NO logró extraer pedido del retry forzado', ['from' => $from]);
        } catch (\Throwable $e) {
            Log::error('❌ Auto-recovery falló: ' . $e->getMessage(), ['from' => $from]);
        }

        // Recovery falló → registrar alerta operativa
        try {
            app(\App\Services\BotAlertaService::class)->registrar(
                \App\Models\BotAlerta::TIPO_OTRO,
                '🤥 Bot dijo que confirmó un pedido pero NO lo hizo',
                "El bot respondió \"{$frase}\" al cliente {$from} en contexto {$contextoTool} "
                    . "pero NO invocó confirmar_pedido y el auto-recovery falló. "
                    . "El pedido NO está registrado en BD. Revisa /chat y complétalo manualmente.",
                \App\Models\BotAlerta::SEV_WARNING,
                null,
                ['from' => $from, 'frase' => $frase, 'reply' => mb_substr($reply, 0, 500), 'conversacion_id' => $conversacion->id]
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar alerta: ' . $e->getMessage());
        }

        return $reply;
    }

    /**
     * Detecta si el cliente pidió explícitamente "generar / confirmar el
     * pedido". En ese caso forzamos tool_choice a confirmar_pedido para
     * cortar el ciclo de validar_cobertura → texto → validar_cobertura.
     */
    private function clientePidioGenerarPedido(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));
        if ($m === '') return false;

        $patrones = [
            'genera el pedido',
            'generar el pedido',
            'genera mi pedido',
            'confirma el pedido',
            'confirma mi pedido',
            'confirmar pedido',
            'confirmamos pedido',
            'hagamos el pedido',
            'haz el pedido',
            'crea el pedido',
            'crear pedido',
            'registra el pedido',
            'registra mi pedido',
            'procede con el pedido',
            'cierra el pedido',
            'cerremos el pedido',
            'finaliza el pedido',
            'finalizar pedido',
        ];

        foreach ($patrones as $p) {
            if (str_contains($m, $p)) return true;
        }
        return false;
    }

    /**
     * Detecta si el cliente está preguntando o pidiendo un producto.
     * Si retorna true, forzamos tool_choice a buscar_productos para que el
     * LLM no invente "sí tengo X" sin verificar BD.
     */
    private function clientePreguntaProducto(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));
        if ($m === '') return false;

        // Patrones explícitos: "tienes X?", "tienen X?", "quiero X", "necesito X"
        $patrones = [
            '/\b(tienes|tienen|tendr[áa]s|tendr[áa]n|hay|manejas|venden|vendes)\s+/iu',
            '/\b(quiero|necesito|me das|d[áa]me|puede ser|me regal[áa]s|reg[áa]lame|qu[ií]siera)\s+/iu',
            '/\b(\d+)\s+(libras?|kilos?|kg|gramos?|gr|unidades?|unidad|cajas?|caja|paquetes?|paquete|bolsas?|docenas?|gallinas?|porciones?|libritas?|kilitos?|cucharaditas?|botellas?|latas?)\b/iu',
            '/\b(una?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|media|medio)\s+(libras?|kilos?|kg|unidades?|cajas?|paquetes?|bolsas?|docenas?|porciones?|gallinas?|libritas?|kilitos?)\b/iu',
        ];
        foreach ($patrones as $p) {
            if (preg_match($p, $m)) return true;
        }
        return false;
    }

    /**
     * Detecta si el cliente está dando datos finales para CERRAR el pedido
     * (método entrega, dirección, "ya", "listo", "sí confirmo", etc).
     * Si retorna true Y el estado tiene productos → forzar confirmar_pedido.
     */
    private function clienteDaDatosFinales(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));
        if ($m === '') return false;

        $patrones = [
            // método de entrega
            'yo reclamo', 'lo reclamo', 'paso por', 'paso a recoger', 'voy a recoger',
            'recojo', 'voy yo', 'recogerlo en', 'recogerlo',
            'a domicilio', 'para domicilio', 'env[ií]ame', 'm[áa]ndame',
            // confirmaciones cortas tras tener datos
            'listo', 'dale', 's[ií] confirmo', 'confirmo', 'confirmado', 'as[ií] est[áa]',
            'as[ií] queda', 'perfecto', 'cerremos',
            // método de pago
            'pago contado', 'pago de contado', 'efectivo contra', 'tarjeta', 'transferencia',
            'pse', 'wompi', 'link de pago',
        ];
        foreach ($patrones as $p) {
            // Match flexible: usar str_contains en lower
            if (str_contains($m, $p)) return true;
            // O regex si tiene chars especiales (acentos)
            if (preg_match('/\b' . str_replace(['[ií]', '[áa]'], ['(i|í)', '(a|á)'], $p) . '\b/u', $m)) return true;
        }
        return false;
    }

    /**
     * 🛡️ ¿El mensaje es solo agradecimiento o despedida?
     * En ese caso NO disparamos guard de cierre — dejamos que el LLM
     * responda cordial y termine la conversación.
     */
    private function mensajeEsAgradecimientoODespedida(string $message): bool
    {
        $m = mb_strtolower(\Illuminate\Support\Str::ascii(trim($message)));
        if ($m === '') return true; // mensaje vacío también: no disparar

        $patrones = [
            'gracias', 'muchas gracias', 'mil gracias', 'gracias!',
            'chao', 'adios', 'adiós', 'bye', 'hasta luego', 'hasta mañana',
            'nos vemos', 'cuídate', 'cuidate', 'buena noche', 'buenas',
        ];

        foreach ($patrones as $p) {
            if ($m === $p) return true;
        }

        // Mensajes muy cortos sin verbos/sustantivos típicos: ej "ok", "ya"
        if (mb_strlen($m) <= 4 && in_array($m, ['ok', 'ya', 'mmm', 'hmm', 'aja', 'ajá'], true)) return true;

        return false;
    }

    /**
     * 🛡️ ¿El cliente está afirmando que sí quiere programar el pedido?
     * Mira el último mensaje del assistant (¿ofreció programar?) y el
     * mensaje actual del cliente (¿afirma?).
     */
    private function detectarAfirmacionProgramar($conversacion, string $messageActual): bool
    {
        if (!$conversacion) return false;

        $msgActual = mb_strtolower(\Illuminate\Support\Str::ascii(trim($messageActual)));
        if ($msgActual === '') return false;

        // Patrones de afirmación
        $afirmaciones = [
            'si', 'sí', 'si esta bien', 'sí está bien', 'esta bien', 'está bien',
            'ok', 'okay', 'dale', 'dele', 'listo', 'bueno', 'perfecto', 'claro',
            'hagamoslo', 'haga', 'hagale', 'há gale', 'sip', 'sii', 'siii',
            'si por favor', 'sí por favor', 'si gracias', 'sí gracias',
            'me parece', 'de acuerdo', 'va', 'va pues', 'vamos', 'si vamos',
            'programalo', 'programado', 'programar', 'prográmalo', 'prográmalo',
            'me lo programas', 'progr[áa]mamelo',
        ];

        $matchAfirma = false;
        foreach ($afirmaciones as $a) {
            if ($msgActual === $a || str_contains($msgActual, $a)) { $matchAfirma = true; break; }
        }
        if (!$matchAfirma) return false;

        // Último mensaje del assistant
        $ultAss = MensajeWhatsapp::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('rol', 'assistant')
            ->orderByDesc('id')
            ->limit(1)
            ->value('contenido');
        if (!$ultAss) return false;

        $ultAssN = mb_strtolower(\Illuminate\Support\Str::ascii($ultAss));
        $ofrecioPrograma = str_contains($ultAssN, 'programar')
            || str_contains($ultAssN, 'programad')
            || str_contains($ultAssN, 'programalo')
            || str_contains($ultAssN, 'cola para preparar')
            || str_contains($ultAssN, 'apenas abramos')
            || str_contains($ultAssN, 'te lo dejamos')
            || str_contains($ultAssN, 'dejar el pedido');

        return $ofrecioPrograma;
    }

    /**
     * 🛡️ Detecta si el mensaje del cliente expresa intención de pedido
     * o consulta comercial (productos, precios, domicilios, etc.).
     *
     * Devuelve TRUE si el mensaje merece la respuesta de "estamos cerrados".
     * Devuelve FALSE para saludos puros, agradecimientos, despedidas.
     */
    private function mensajeExpresaIntencionDePedidoOConsulta(string $message): bool
    {
        $m = mb_strtolower(\Illuminate\Support\Str::ascii(trim($message)));
        if ($m === '') return false;

        // Saludo puro / despedida → NO disparar
        $saludosPuros = ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches',
                          'gracias', 'muchas gracias', 'mil gracias', 'chao', 'adios', 'bye',
                          'ok', 'listo', 'dale', 'si', 'no'];
        if (in_array($m, $saludosPuros, true)) return false;

        // Patrones explícitos de intención de pedido / consulta comercial
        $patrones = [
            // Verbos de pedido
            '/\b(quiero|necesito|pideme|p[íi]deme|pedido|domicilio|domicilios|despacho|despachos|comprar|compro|llevo|llevame|llevarme|reservar|encargar|encargo|ordenar|orden)\b/u',
            // Verbos de consulta de catálogo / precio
            '/\b(tienes|tienen|hay|venden|manejan|hacen)\b/u',
            '/\b(valor|vale|cuanto|cuesta|precio|costo|cu[áa]nto)\b/u',
            // Cantidad + unidad
            '/\b\d+\s*(kg|kilo|kilos|lb|libra|libras|gr|gramos|paquete|paquetes|und|unidad|unidades|porcion|porciones|pack|tira|tiras)\b/u',
            // Mención de tipos de carne
            '/\b(carne|res|cerdo|pollo|pescado|salmon|salm[óo]n|trucha|tilapia|camaron|camar[óo]n|filete|pierna|costilla|chuleta|lomo|punta|brazo|asado|chicharron|chicharr[óo]n)\b/u',
            // Frase típica de inicio de pedido
            '/\b(otro pedido|nuevo pedido|para un pedido|hacer pedido|tomar pedido)\b/u',
        ];

        foreach ($patrones as $p) {
            if (preg_match($p, $m)) return true;
        }

        return false;
    }

    private function detectarFalsaConfirmacion(string $reply): ?string
    {
        $frases = [
            // Confirmación explícita
            'pedido quedó registrado',
            'pedido registrado',
            'pedido confirmado',
            'queda confirmado',
            'queda registrado',
            'tu pedido #',
            'quedó en preparación',
            'tu pedido quedó listo',
            // Despacho / domicilio
            'va en camino',
            'salió en camino',
            'sale en camino',
            'lo estamos preparando',
            'te despachamos',
            'te lo despachamos',
            'te lo enviamos',
            'te lo entregamos',
            'lo enviamos a',
            'sale para tu casa',
            // Recoger en sede
            'lo recogerás',
            'la recogerás',
            'puedes recogerlo',
            'puedes pasar a recoger',
            'pasa a recoger',
            'lista tu compra',
            'listo para recoger',
            // Cierre genérico
            'genial, te despachamos',
            'perfecto, queda',
            'tu pedido queda',
            'tu pedido es:',
            'tu pedido será',
            'tu pedido sera',
            'tu pedido es de',
            'pedido queda así',
            'pedido queda asi',
            'pedido queda listo',
            'queda anotado',
            'queda apuntado',
            'queda agendado',
            // ⏳ Promesas vacías — el bot dice "ya lo hago" pero no llama tool
            'un momento, verificando',
            'un momento verificando',
            'verificando tus datos',
            'verificando datos',
            'déjame verificar tus datos',
            'dame un momento',
            'estoy generando tu pedido',
            'estoy creando tu pedido',
            'voy a registrar tu pedido',
            'procediendo a registrar',
            'procederé a registrar',
            'registrando tu pedido',
            'creando el pedido',
            'creando tu pedido',
            'anotando tu pedido',
            'anotando el pedido',
            // ⏳ "voy a validar..." sin invocar validar_cobertura
            'voy a validar',
            'voy a verificar si',
            'déjame validar',
            'permíteme verificar',
            'permiteme verificar',
            'verifico la cobertura',
            'consultando cobertura',
            'revisando cobertura',
            'voy a chequear',
            'voy a consultar',
        ];

        $lower = mb_strtolower($reply);
        foreach ($frases as $f) {
            if (str_contains($lower, $f)) {
                return $f;
            }
        }
        return null;
    }
    /**
     * Obtiene el ID de la sede asociada a la conexión.
     * Estrategia:
     *   1. Buscar una sede que tenga whatsapp_connection_id == connectionId.
     *   2. Si no hay match, usar la primera sede activa del tenant (fallback legacy).
     */
    private function obtenerSedeIdDesdeConexion(?string $connectionId): ?int
    {
        if ($connectionId) {
            $sede = Sede::porConnectionId((int) $connectionId);
            if ($sede) {
                return $sede->id;
            }
        }

        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        return Cache::remember("default_sede_id_t{$tenantId}", now()->addMinutes(10), function () {
            return Sede::query()->orderBy('id')->value('id');
        });
    }

    private function obtenerEmpresaIdDesdeConexion(?string $connectionId): ?int
    {
        if (!$connectionId) {
            return null;
        }

        try {
            $token = $this->obtenerTokenWhatsapp();

            if (!$token) {
                Log::warning('⚠️ No se pudo obtener token para consultar conexión WhatsApp', [
                    'connectionId' => $connectionId,
                ]);
                return null;
            }

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->get('https://wa-api.tecnobyteapp.com:1422/whatsapp/', [
                    'id' => (int) $connectionId,
                ]);

            if ($response->failed()) {
                Log::warning('⚠️ No se pudo consultar la conexión WhatsApp', [
                    'connectionId' => $connectionId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $whatsapps = $response->json('whatsapps', []);

            $conexion = collect($whatsapps)->firstWhere('id', (int) $connectionId);

            return $conexion['ownerId'] ?? null;
        } catch (\Throwable $e) {
            Log::error('❌ Error consultando empresa por conexión WhatsApp', [
                'connectionId' => $connectionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

  private function resolverConexionWhatsapp(?string $connectionId = null): array
{
    // ✅ Si el webhook ya trajo connectionId, se usa ese mismo SIN consultar API
    if (!empty($connectionId)) {
        return [
            'connection_id' => (int) $connectionId,
            'whatsapp_id'   => (int) $connectionId,
            'empresa_id'    => null, // si quieres luego puedes resolver empresa aparte
        ];
    }

    // ✅ Solo si NO viene connectionId, consultar API para sacar una conexión válida
    try {
        $token = $this->obtenerTokenWhatsapp();

        if (!$token) {
            Log::warning('⚠️ No se pudo obtener token para resolver conexión WhatsApp');
            return [
                'connection_id' => null,
                'whatsapp_id'   => null,
                'empresa_id'    => null,
            ];
        }

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->timeout(20)
            ->get('https://wa-api.tecnobyteapp.com:1422/whatsapp/');

        if ($response->failed()) {
            Log::warning('⚠️ No se pudo consultar listado de conexiones WhatsApp', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'connection_id' => null,
                'whatsapp_id'   => null,
                'empresa_id'    => null,
            ];
        }

        $whatsapps = collect($response->json('whatsapps', []));

        if ($whatsapps->isEmpty()) {
            Log::warning('⚠️ La API de WhatsApp no devolvió conexiones');
            return [
                'connection_id' => null,
                'whatsapp_id'   => null,
                'empresa_id'    => null,
            ];
        }

        // 1. Buscar una conexión CONNECTED y default
        $conexion = $whatsapps->first(function ($item) {
            return ($item['status'] ?? null) === 'CONNECTED'
                && (bool) ($item['isDefault'] ?? false) === true;
        });

        // 2. Si no hay default conectada, tomar la primera CONNECTED
        if (!$conexion) {
            $conexion = $whatsapps->first(function ($item) {
                return ($item['status'] ?? null) === 'CONNECTED';
            });
        }

        // 3. Si no hay ninguna conectada, tomar la primera
        if (!$conexion) {
            $conexion = $whatsapps->first();
        }

        if (!$conexion) {
            return [
                'connection_id' => null,
                'whatsapp_id'   => null,
                'empresa_id'    => null,
            ];
        }

        return [
            'connection_id' => $conexion['id'] ?? null,
            'whatsapp_id'   => $conexion['id'] ?? null,
            'empresa_id'    => $conexion['ownerId'] ?? null,
        ];
    } catch (\Throwable $e) {
        Log::error('❌ Error resolviendo conexión WhatsApp', [
            'connectionId_entrada' => $connectionId,
            'error' => $e->getMessage(),
        ]);

        return [
            'connection_id' => null,
            'whatsapp_id'   => null,
            'empresa_id'    => null,
        ];
    }
}



    /**
     * 🛡️ FALLBACK CRÍTICO: si el LLM falla DESPUÉS de ejecutar una tool,
     * generamos una respuesta inmediata con los datos de la tool, en vez de
     * dejar al cliente colgado con "déjame revisar...".
     *
     * Soporta los principales tools: buscar_productos, listar_categorias,
     * info_producto, productos_destacados.
     */
    private function respuestaFallbackDeTools(array $toolMessages): string
    {
        if (empty($toolMessages)) {
            return "Disculpa, tuve un problemita procesando. ¿Puedes repetirme qué necesitas?";
        }

        $lineas = [];

        foreach ($toolMessages as $tm) {
            $tool = $tm['name'] ?? '';
            $contenido = json_decode($tm['content'] ?? '{}', true) ?: [];

            if ($tool === 'buscar_productos') {
                $productos = $contenido['productos'] ?? [];
                if (empty($productos)) {
                    $lineas[] = "Disculpa, no encontré ese producto en este momento. ¿Quieres que te muestre el menú?";
                } else {
                    $lineas[] = "Esto es lo que tenemos:";
                    foreach (array_slice($productos, 0, 5) as $p) {
                        $nombre = $p['nombre'] ?? '?';
                        $precio = isset($p['precio']) ? '$' . number_format($p['precio'], 0, ',', '.') : '';
                        $unidad = $p['unidad'] ? '/' . $p['unidad'] : '';
                        $lineas[] = "• {$nombre} {$precio}{$unidad}";
                    }
                    $lineas[] = "\n¿Cuál te llevas?";
                }
            } elseif ($tool === 'listar_categorias') {
                $cats = $contenido['categorias'] ?? [];
                if (!empty($cats)) {
                    $lineas[] = "Tenemos estas categorías:";
                    foreach (array_slice($cats, 0, 8) as $c) {
                        $nom = is_array($c) ? ($c['nombre'] ?? json_encode($c)) : (string) $c;
                        $lineas[] = "• " . $nom;
                    }
                }
            } elseif ($tool === 'info_producto') {
                $p = $contenido['producto'] ?? null;
                if ($p) {
                    $lineas[] = "📦 *{$p['nombre']}*";
                    if (!empty($p['precio'])) $lineas[] = "Precio: $" . number_format($p['precio'], 0, ',', '.');
                    if (!empty($p['descripcion'])) $lineas[] = $p['descripcion'];
                }
            } elseif ($tool === 'productos_destacados') {
                $dst = $contenido['destacados'] ?? [];
                if (!empty($dst)) {
                    $lineas[] = "⭐ Te recomendamos:";
                    foreach (array_slice($dst, 0, 5) as $p) {
                        $lineas[] = "• {$p['nombre']}" . (isset($p['precio']) ? ' — $' . number_format($p['precio'], 0, ',', '.') : '');
                    }
                }
            }
        }

        if (empty($lineas)) {
            return "Tuve un problemita pero ya estoy listo. Dime qué necesitas y te ayudo 🙌";
        }

        return implode("\n", $lineas);
    }

    /**
     * 🛡️ PRE-FLIGHT GUARD para llamadas a OpenAI.
     * Estima el tamaño total del payload (chars / 4 ≈ tokens). Si excede el
     * presupuesto, recorta agresivamente:
     *  1. Trunca cada mensaje individual a 3000 chars
     *  2. Si aún excede, descarta los mensajes más viejos (mantiene system + últimos)
     *
     * Evita rate_limit_exceeded de OpenAI por requests gigantes.
     */
    private function recortarMessagesParaLLM(array $messages, int $maxCharsTotal): array
    {
        $maxCharsPorMsg = 3000; // ~750 tokens por mensaje individual

        // Paso 1: truncar mensajes individuales gigantes
        foreach ($messages as &$m) {
            $contenido = $m['content'] ?? '';
            if (is_string($contenido) && mb_strlen($contenido) > $maxCharsPorMsg) {
                $m['content'] = mb_substr($contenido, 0, $maxCharsPorMsg) . ' …[truncado]';
            }
        }
        unset($m);

        // Paso 2: si el total aún excede, descartar los más viejos.
        // Mantenemos: 1) PRIMER system message (prompt principal). 2) ÚLTIMOS N mensajes.
        $totalChars = array_sum(array_map(fn ($m) => mb_strlen($m['content'] ?? ''), $messages));
        if ($totalChars <= $maxCharsTotal) {
            return $messages;
        }

        $primerSystem = null;
        $resto = [];
        foreach ($messages as $i => $m) {
            if ($primerSystem === null && ($m['role'] ?? '') === 'system') {
                $primerSystem = $m;
            } else {
                $resto[] = $m;
            }
        }

        // Tomar de atrás hacia adelante hasta llenar el presupuesto
        $primerSystemChars = $primerSystem ? mb_strlen($primerSystem['content'] ?? '') : 0;
        $presupuestoRestante = max(0, $maxCharsTotal - $primerSystemChars);
        $finalReverso = [];
        $usado = 0;
        foreach (array_reverse($resto) as $m) {
            $size = mb_strlen($m['content'] ?? '');
            if ($usado + $size > $presupuestoRestante) break;
            $finalReverso[] = $m;
            $usado += $size;
        }

        $resultado = [];
        if ($primerSystem) $resultado[] = $primerSystem;
        $resultado = array_merge($resultado, array_reverse($finalReverso));

        \Log::info('🛡️ Pre-flight recortó request a OpenAI', [
            'antes' => $totalChars,
            'despues' => array_sum(array_map(fn ($m) => mb_strlen($m['content'] ?? ''), $resultado)),
            'mensajes_antes' => count($messages),
            'mensajes_despues' => count($resultado),
        ]);

        return $resultado;
    }

    /**
     * @param array        $messages
     * @param string|array $toolChoice 'auto' (default), 'none', 'required',
     *                                 o ['type'=>'function','function'=>['name'=>'X']] para forzar
     * @param ?array       $toolsCustom Si se pasa, se usan ESTAS tools en vez de
     *                                  todas las definidas (para filtrar por paso).
     */
    private function llamarOpenAI(array $messages, $toolChoice = 'auto', ?array $toolsCustom = null): ?array
    {
        // 🛡️ PRE-FLIGHT: estimar tokens y recortar si excede el límite seguro.
        $messages = $this->recortarMessagesParaLLM($messages, 30000);

        // 🤖 Delegar al AiClientService — decide entre OpenAI y Anthropic según
        // la configuración del tenant. Mantiene formato OpenAI para no romper
        // el resto del código.
        $tools = $toolsCustom ?? $this->getToolsDefinicion();
        return app(\App\Services\Ai\AiClientService::class)
            ->chat($messages, $toolChoice, $tools);
    }

    /** @deprecated reservado por compatibilidad histórica */
    private function llamarOpenAILegacy(array $messages, $toolChoice = 'auto', ?array $toolsCustom = null): ?array
    {
        $messages = $this->recortarMessagesParaLLM($messages, 30000);
        $intentos = 4;
        $ultimoStatus = null;
        $ultimoBody   = null;
        $ultimaExc    = null;

        $config = \App\Models\ConfiguracionBot::actual();
        $modelo = $config->modelo_openai ?: 'gpt-4o-mini';

        // 🔑 Key del tenant actual (con fallback al .env)
        $openaiKey = \App\Models\Tenant::resolverOpenaiKey();

        // ── Validación temprana: API key falta ────────────────────────────
        if (empty($openaiKey)) {
            $tenantActual = app(\App\Services\TenantManager::class)->current();
            $tenantNombre = $tenantActual?->nombre ?? 'desconocido';

            app(\App\Services\BotAlertaService::class)->registrar(
                \App\Models\BotAlerta::TIPO_OPENAI_KEY,
                "🔑 OpenAI API key no configurada para tenant {$tenantNombre}",
                "Configura la key del tenant en /admin/tenants (campo OpenAI API key) o define OPENAI_API_KEY global en el .env como fallback. Sin ella, el bot no puede responder.",
                \App\Models\BotAlerta::SEV_CRITICA
            );
            Log::error('❌ OpenAI API key no resuelta', ['tenant' => $tenantNombre]);
            return null;
        }

        for ($i = 1; $i <= $intentos; $i++) {
            try {
                $response = Http::withToken($openaiKey)
                    ->timeout(35)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'             => $modelo,
                        'messages'          => $messages,
                        'temperature'       => (float) ($config->temperatura ?? 0.85),
                        'top_p'             => 0.9,
                        'frequency_penalty' => 0.4,
                        'presence_penalty'  => 0.4,
                        'max_tokens'        => (int) ($config->max_tokens ?? 700),
                        'tools'       => $toolsCustom ?? $this->getToolsDefinicion(),
                        'tool_choice' => $toolChoice,
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }

                $ultimoStatus = $response->status();
                $ultimoBody   = $response->body();

                Log::warning("⚠️ OpenAI intento {$i} falló", [
                    'status' => $ultimoStatus,
                    'body'   => $ultimoBody,
                ]);
            } catch (\Throwable $e) {
                $ultimaExc = $e->getMessage();
                Log::warning("⚠️ OpenAI excepción intento {$i}", ['error' => $ultimaExc]);
            }

            if ($i < $intentos) {
                // ⏳ Backoff exponencial: 1, 2, 4, 8 segundos
                // Si es rate limit (429), aplicamos espera más larga.
                $esperaSegs = $ultimoStatus === 429
                    ? min(15, pow(2, $i) * 2) // hasta 15s en rate limit
                    : pow(2, $i - 1);          // backoff normal
                sleep($esperaSegs);
            }
        }

        // ── Falló todos los intentos: registrar alerta clasificada ──────────
        try {
            $alertaService = app(\App\Services\BotAlertaService::class);

            if ($ultimaExc !== null && $ultimoStatus === null) {
                // Excepción de red / timeout
                $alertaService->registrar(
                    \App\Models\BotAlerta::TIPO_OPENAI_TIMEOUT,
                    '⌛ Sin conexión a OpenAI',
                    "No fue posible contactar la API de OpenAI tras {$intentos} intentos.\nÚltimo error: {$ultimaExc}",
                    \App\Models\BotAlerta::SEV_CRITICA,
                    null,
                    ['modelo' => $modelo, 'excepcion' => $ultimaExc]
                );
            } else {
                $alertaService->registrarErrorOpenAI($ultimoStatus, $ultimoBody, [
                    'modelo' => $modelo,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar alerta de OpenAI: ' . $e->getMessage());
        }

        Log::error('❌ OpenAI falló todos los intentos', [
            'status' => $ultimoStatus,
            'modelo' => $modelo,
        ]);

        return null;
    }

    /*
    |==========================================================================
    | REGLAS DETERMINISTAS
    |==========================================================================
    */

    private function esConsultaEstadoPedido(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        $frases = [
            'estado de mi pedido',
            'estado del pedido',
            'estado de pedido',
            'como va mi pedido',
            'cómo va mi pedido',
            'como van mis pedidos',
            'cómo van mis pedidos',
            'mis pedidos',
            'mi pedido',
            'mi orden',
            'mis ordenes',
            'mis órdenes',
            'estado pedido',
            'seguimiento pedido',
            'seguimiento de mi pedido',
            'seguimiento de mis pedidos',
            'ya salió mi pedido',
            'ya salio mi pedido',
            'donde va mi pedido',
            'dónde va mi pedido',
            'consulta de pedido',
            'consultar pedido',
            'consultar mis pedidos',
            'quiero saber mi pedido',
            'quiero saber mis pedidos',
            'numero de pedido',
            'número de pedido',
        ];

        foreach ($frases as $frase) {
            if (str_contains($msg, $frase)) {
                return true;
            }
        }

        return false;
    }

    private function resolverConsultaEstadoPedido(string $from, string $name = 'Cliente', string $message = ''): string
    {
        $pedidos = $this->pedidosDelCliente($from);

        if ($pedidos->isEmpty()) {
            return "Hola {$name} 😊\nNo encontré pedidos registrados con este número.\nSi deseas, puedo ayudarte a realizar un nuevo pedido.";
        }

        $pedidoIdSolicitado = $this->extraerNumeroPedidoDesdeMensaje($message);

        if ($pedidoIdSolicitado) {
            $pedido = $pedidos->firstWhere('id', $pedidoIdSolicitado);

            if (!$pedido) {
                $lineas = [
                    "Hola {$name} 😊",
                    "No encontré el pedido #{$pedidoIdSolicitado} asociado a este número.",
                    "Estos son los pedidos que sí encontré:",
                ];

                foreach ($pedidos->take(10) as $item) {
                    $lineas[] = "• #{$item->id} - " . $this->traducirEstadoPedido($item->estado);
                }

                $lineas[] = "Escríbeme el número del pedido. Ejemplo: pedido #{$pedidos->first()->id}";
                return implode("\n", $lineas);
            }

            return $this->formatearRespuestaPedidoEspecifico($pedido, $name);
        }

        if ($pedidos->count() === 1) {
            return $this->formatearRespuestaPedidoEspecifico($pedidos->first(), $name);
        }

        $lineas = [
            "Hola {$name} 😊",
            "Encontré *{$pedidos->count()} pedidos* asociados a este número:",
            '',
        ];

        foreach ($pedidos->take(10) as $pedido) {
            $lineas[] = "📦 Pedido #{$pedido->id}";
            $lineas[] = "Estado: " . $this->traducirEstadoPedido($pedido->estado);
            $lineas[] = "Fecha: " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i');
            $lineas[] = "Sede: " . ($pedido->sede->nombre ?? 'No especificada');
            $lineas[] = '';
        }

        $lineas[] = "Para consultar uno en detalle, escríbeme: *pedido #{$pedidos->first()->id}*";

        return implode("\n", $lineas);
    }

    private function esSolicitudModificarPedido(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        $palabras = [
            'cancelar',
            'cancela',
            'cancelame',
            'cancelar el',
            'cancelar mi',
            'cancelen',
            'anular',
            'anula',
            'ya no lo quiero',
            'ya no quiero el pedido',
            'quitar el pedido',
            'eliminar pedido',
            'borrar pedido',
            'adicionar',
            'adiciona',
            'agregar',
            'agrega',
            'sumar',
            'añadir',
            'anadir',
            'ponerle',
            'modificar',
            'modifica',
            'editar',
            'edita',
            'cambiar',
            'cambiame',
            'cámbiame',
            'cambiarle',
        ];

        foreach ($palabras as $p) {
            if (str_contains($msg, $p)) {
                return true;
            }
        }

        return false;
    }

    private function resolverSolicitudModificacionPedido(string $from, string $name, string $message): string
    {
        $accion = $this->detectarAccionPedido($message);

        if (!$accion) {
            return "Hola {$name} 😊\nNo logré identificar si deseas cancelar o adicionar un pedido.\nPor favor indícame qué deseas hacer.";
        }

        $pedidos = $this->pedidosDelCliente($from);

        if ($pedidos->isEmpty()) {
            return "Hola {$name} 😊\nNo encontré pedidos asociados a este número para {$accion}.";
        }

        $pedidoIdSolicitado = $this->extraerNumeroPedidoDesdeMensaje($message);

        if ($pedidoIdSolicitado) {
            $pedido = $pedidos->firstWhere('id', $pedidoIdSolicitado);

            if (!$pedido) {
                $lineas = [
                    "Hola {$name} 😊",
                    "No encontré el pedido #{$pedidoIdSolicitado} asociado a este número.",
                    "Estos son los pedidos disponibles:",
                ];

                foreach ($pedidos->take(10) as $item) {
                    $lineas[] = "• Pedido #{$item->id} - " . $this->traducirEstadoPedido($item->estado);
                }

                $lineas[] = "Escríbeme por ejemplo: *{$accion} pedido #{$pedidos->first()->id}*";
                return implode("\n", $lineas);
            }

            return $this->validarAnsYResponder($pedido, $accion, $name);
        }

        if ($pedidos->count() === 1) {
            return $this->validarAnsYResponder($pedidos->first(), $accion, $name);
        }

        $this->guardarAccionPendiente($from, [
            'accion'     => $accion,
            'pedido_ids' => $pedidos->pluck('id')->take(10)->values()->toArray(),
        ]);

        $lineas = [
            "Hola {$name} 😊",
            "Encontré varios pedidos. Para {$accion}, indícame cuál deseas modificar:",
            '',
        ];

        foreach ($pedidos->take(10) as $pedido) {
            $lineas[] = "• Pedido #{$pedido->id} - " . $this->traducirEstadoPedido($pedido->estado)
                . " - " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i');
        }

        $lineas[] = "Ejemplo: *{$accion} pedido #{$pedidos->first()->id}*";
        $lineas[] = "O responde solo con el número: *{$pedidos->first()->id}*";

        return implode("\n", $lineas);
    }

    private function detectarAccionPedido(string $message): ?string
    {
        $msg = mb_strtolower(trim($message));

        $cancelar = [
            'cancelar',
            'cancela',
            'cancelame',
            'cancelen',
            'anular',
            'anula',
            'ya no lo quiero',
            'ya no quiero el pedido',
            'quitar el pedido',
            'eliminar pedido',
            'borrar pedido'
        ];

        foreach ($cancelar as $p) {
            if (str_contains($msg, $p)) {
                return 'cancelar';
            }
        }

        $adicionar = [
            'adicionar',
            'adiciona',
            'agregar',
            'agrega',
            'sumar',
            'añadir',
            'anadir',
            'ponerle',
            'modificar',
            'modifica',
            'editar',
            'edita',
            'cambiar',
            'cambiame',
            'cámbiame',
            'cambiarle'
        ];

        foreach ($adicionar as $p) {
            if (str_contains($msg, $p)) {
                return 'adicionar';
            }
        }

        return null;
    }

    private function validarAnsYResponder(Pedido $pedido, string $accion, string $name): string
    {
        $ansMinutos = $this->obtenerAnsMinutos($accion);

        if (!$ansMinutos) {
            return "Hola {$name} 😊\nNo hay un ANS configurado para {$accion} el pedido #{$pedido->id}.";
        }

        $minutosTranscurridos = (int) round($pedido->fecha_pedido->diffInSeconds(now()) / 60);
        $puede = $minutosTranscurridos <= $ansMinutos;

        $lineas = [
            "Hola {$name} 😊",
            "Pedido #{$pedido->id}",
            "Estado actual: " . $this->traducirEstadoPedido($pedido->estado),
            "Fecha del pedido: " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i'),
            "Tiempo transcurrido: {$minutosTranscurridos} minuto(s)",
            "ANS para {$accion}: {$ansMinutos} minuto(s)",
            '',
        ];

        if (!$puede) {
            $this->limpiarAccionPendiente($pedido->telefono_whatsapp ?? $pedido->telefono ?? '');
            $lineas[] = "❌ Ya no es posible {$accion} este pedido porque el tiempo permitido expiró.";
            return implode("\n", $lineas);
        }

        if ($accion === 'cancelar') {
            $this->guardarAccionPendiente($pedido->telefono_whatsapp ?? $pedido->telefono ?? '', [
                'accion'    => 'cancelar',
                'pedido_id' => $pedido->id,
            ]);

            $lineas[] = "✅ Sí es posible cancelar este pedido.";
            $lineas[] = "Responde *CONFIRMAR CANCELACIÓN* para continuar.";
        } else {
            $lineas[] = "✅ Sí es posible adicionar o modificar este pedido.";
            $lineas[] = "Escríbeme qué producto deseas agregar o cambiar en el pedido #{$pedido->id}.";
        }

        return implode("\n", $lineas);
    }

    /*
    |==========================================================================
    | ACCIÓN PENDIENTE
    |==========================================================================
    */

    private function tieneAccionPendiente(string $from): bool
    {
        return Cache::has($this->claveAccionPendiente($from));
    }

    private function guardarAccionPendiente(string $from, array $data): void
    {
        Cache::put($this->claveAccionPendiente($from), $data, now()->addMinutes(10));
    }

    private function obtenerAccionPendiente(string $from): ?array
    {
        return Cache::get($this->claveAccionPendiente($from));
    }

    private function limpiarAccionPendiente(string $from): void
    {
        Cache::forget($this->claveAccionPendiente($from));
    }

    private function claveAccionPendiente(string $from): string
    {
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        return "whatsapp_pending_action_t{$tenantId}_" . $this->normalizarTelefono($from);
    }

    private function resolverAccionPendiente(string $from, string $name, string $message): ?string
    {
        $pendiente = $this->obtenerAccionPendiente($from);

        if (!$pendiente || empty($pendiente['accion'])) {
            return null;
        }

        $accion = $pendiente['accion'];

        if (
            $accion === 'cancelar' &&
            in_array(mb_strtolower(trim($message)), [
                'confirmar cancelación',
                'confirmar cancelacion',
                'si cancelar',
                'sí cancelar',
                'confirmo cancelación',
                'confirmo cancelacion'
            ])
        ) {
            $pedidoId = $pendiente['pedido_id'] ?? null;

            if (!$pedidoId) {
                $this->limpiarAccionPendiente($from);
                return "Hola {$name} 😊\nNo encontré el pedido pendiente de cancelación.";
            }

            $pedido = Pedido::with(['sede', 'detalles'])->find($pedidoId);

            if (!$pedido) {
                $this->limpiarAccionPendiente($from);
                return "Hola {$name} 😊\nNo encontré el pedido que ibas a cancelar.";
            }

            $this->limpiarAccionPendiente($from);

            return $this->cancelarPedidoAutomaticamente($pedido, $name);
        }

        $pedidoIdsPermitidos = $pendiente['pedido_ids'] ?? [];
        $pedidoId = $this->extraerNumeroPedidoDesdeMensaje($message);

        if (!$pedidoId) {
            $msgNorm = mb_strtolower(trim($message));
            if (in_array($msgNorm, ['ese', 'ese mismo', 'el mismo', 'último', 'ultimo', 'el último', 'el ultimo'])) {
                $pedidoId = $pedidoIdsPermitidos[0] ?? null;
            }
        }

        if (!$pedidoId) {
            return "Hola {$name} 😊\nNo logré identificar el número del pedido.\nResponde solo con el número. Ejemplo: *3*";
        }

        if (!in_array($pedidoId, $pedidoIdsPermitidos)) {
            return "Hola {$name} 😊\nEse pedido no está entre las opciones que te mostré.\nPor favor elige uno de los pedidos listados.";
        }

        $telNorm = $this->normalizarTelefono($from);

        $pedido = Pedido::with(['sede', 'detalles'])
            ->whereIn('id', $pedidoIdsPermitidos)
            ->get()
            ->first(function ($p) use ($telNorm, $pedidoId) {
                $telefonos = array_filter([
                    $this->normalizarTelefono($p->telefono_whatsapp ?? ''),
                    $this->normalizarTelefono($p->telefono_contacto ?? ''),
                    $this->normalizarTelefono($p->telefono ?? ''),
                ]);

                if ((int) $p->id !== (int) $pedidoId) {
                    return false;
                }

                foreach ($telefonos as $pTel) {
                    if (
                        $pTel === $telNorm ||
                        str_contains($pTel, $telNorm) ||
                        str_contains($telNorm, $pTel)
                    ) {
                        return true;
                    }
                }

                return false;
            });

        if (!$pedido) {
            return "Hola {$name} 😊\nNo encontré ese pedido asociado a este número.";
        }

        $this->limpiarAccionPendiente($from);

        return $this->validarAnsYResponder($pedido, $accion, $name);
    }

    /*
    |==========================================================================
    | GUARDAR PEDIDO
    |==========================================================================
    */

  /**
   * Valida la cobertura de una dirección — PRIORIZA el polígono del mapa.
   *
   * Estrategia (en orden):
   *   1. Geocode (Nominatim) de la dirección completa → lat/lng
   *      → point-in-polygon contra los polígonos dibujados en /zonas
   *      Este es el método CORRECTO: el mapa es la verdad.
   *   2. Si el geocode falla o el punto cae fuera de todos los polígonos:
   *      fallback por nombre de barrio (match exacto/parcial).
   *   3. Si todo falla, sin cobertura.
   */
  /**
   * 🛡️ GUARD: si el bot dice 'no llegamos a X / no tenemos cobertura en X'
   * sin haber llamado validar_cobertura, lo detectamos y hacemos que el bot
   * valide ANTES de responder.
   *
   * Detecta patrones de negación de cobertura SIN evidencia.
   * Útil cuando el LLM aluciña que un barrio/ciudad no está cubierto.
   */
  private function detectarNegacionCoberturaSinValidar(string $reply): bool
  {
      $patrones = [
          '/no (tenemos|hay) cobertura (ah[íi]|all[íi]|en)/i',
          '/no llegamos (hasta )?(ah[íi]|all[íi]|hasta esa)/i',
          '/(esa|esta) (zona|ciudad|municipio|barrio).*no est[áa] (cubierta|cubierto|dentro)/i',
          '/no est[áa] (dentro de )?(nuestra )?cobertura/i',
          '/lamentablemente no (cubrimos|llegamos)/i',
      ];

      foreach ($patrones as $p) {
          if (preg_match($p, $reply)) return true;
      }
      return false;
  }

  /**
   * 🧹 AUTO-RESET inteligente: si el cliente saluda Y han pasado más de
   * 3 horas desde su último mensaje, asume que es una conversación NUEVA
   * y descarta el historial viejo (que podría confundir al LLM con
   * pedidos pasados).
   *
   * Esto evita que el bot diga "tu pedido queda listo" basándose en
   * conversaciones de hace días/semanas.
   */
  private function autoResetSiSaludoLargoTiempo($conversacion, string $mensajeActual, array $historial): array
  {
      // Si el historial está vacío, no hay nada que resetear
      if (empty($historial)) return $historial;

      $msgNormalizado = mb_strtolower(trim($mensajeActual));
      $msgNormalizado = strtr($msgNormalizado, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);

      // Detectar saludos puros (no "hola quiero pedir")
      $esSaludoPuro = preg_match(
          '/^\s*(hola|holaa+|buenas|buenos dias|buenas tardes|buenas noches|hi|hey|que tal|que mas|saludos|buen dia|buenos d[ií]as)\s*[\.!?]*\s*$/i',
          $msgNormalizado
      );

      if (!$esSaludoPuro) return $historial;

      // ¿Cuándo fue el último mensaje?
      $ultimoMsg = \App\Models\MensajeWhatsapp::where('conversacion_id', $conversacion->id)
          ->where('rol', \App\Models\MensajeWhatsapp::ROL_USER)
          ->orderByDesc('id')
          ->skip(1) // saltar el mensaje actual que se acaba de guardar
          ->first();

      if (!$ultimoMsg) return $historial;

      $horasInactividad = $ultimoMsg->created_at->diffInHours(now());

      // Umbral configurable desde /configuracion-bot → Mantenimiento.
      // 0 desactiva el auto-reset.
      $horasMin = (int) (\App\Models\ConfiguracionBot::actual()?->auto_reset_horas_inactividad ?? 3);

      if ($horasMin > 0 && $horasInactividad >= $horasMin) {
          \Log::info('🧹 AUTO-RESET activado por saludo + inactividad', [
              'conversacion_id' => $conversacion->id,
              'horas_inactivo'  => $horasInactividad,
              'umbral_horas'    => $horasMin,
              'mensaje'         => $mensajeActual,
          ]);

          // 🎯 También resetear el estado estructurado del pedido
          try {
              app(\App\Services\EstadoPedidoService::class)
                  ->resetear($conversacion, "auto_reset_{$horasInactividad}h_inactividad");
          } catch (\Throwable $e) {
              \Log::warning('No se pudo resetear estado pedido: ' . $e->getMessage());
          }

          // Devolver historial vacío → bot empieza fresco
          return [];
      }

      return $historial;
  }

  /**
   * Busca la cédula del cliente actual (de la conversación que se está
   * procesando). Lee del teléfono guardado en orderData o del context.
   */
  private function cedulaCienteActual(array $orderData): ?string
  {
      try {
          $tel = trim((string) ($orderData['phone'] ?? ''));
          if ($tel === '') return null;

          $telNorm = preg_replace('/\D+/', '', $tel);
          $tenantId = app(\App\Services\TenantManager::class)->id();
          if (!$tenantId) return null;

          $cliente = \App\Models\Cliente::where('tenant_id', $tenantId)
              ->where(function ($q) use ($telNorm, $tel) {
                  $q->where('telefono_normalizado', $telNorm)
                    ->orWhere('telefono', $tel);
              })
              ->first();

          return !empty($cliente?->cedula) ? (string) $cliente->cedula : null;
      } catch (\Throwable $e) {
          return null;
      }
  }

  /**
   * 🛡️ VALIDACIÓN DETERMINISTA: revisa el orderData del bot contra el flujo
   * configurado en flujo_pedido_orden + lookup ERP.
   *
   * Retorna lista de campos faltantes con etiquetas humanas. Si está vacía,
   * el pedido se puede crear. Si trae elementos, hay que pedir esos datos.
   *
   * Mapea los campos del flujo a las claves de orderData:
   *   cedula      → orderData['cedula']
   *   nombre      → orderData['customer_name']
   *   producto    → orderData['products'] (no vacío)
   *   direccion   → orderData['address']
   *   barrio      → orderData['neighborhood']
   *   ciudad      → orderData['location']
   *   telefono    → orderData['phone']
   *   email       → orderData['email']
   *   metodo_pago → orderData['payment_method']
   */
  private function validarDatosObligatoriosPedido(array $orderData): array
  {
      $faltantes = [];

      try {
          $cfg = \App\Models\ConfiguracionBot::actual();
          $flujo = $cfg?->flujo_pedido_orden ?? [];
          $activos = collect($flujo)->filter(fn ($f) => ($f['activo'] ?? false))->pluck('campo')->all();

          // Si no hay flujo configurado, exigir los básicos
          if (empty($activos)) {
              $activos = ['producto', 'nombre', 'direccion'];
          }

          // Si lookup ERP activo, cédula también es obligatoria
          $tenantId = app(\App\Services\TenantManager::class)->id();
          if ($tenantId) {
              $lookupActivo = \App\Models\Integracion::where('tenant_id', $tenantId)
                  ->where('activo', true)->where('exporta_pedidos', true)
                  ->get()
                  ->contains(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

              if ($lookupActivo && !in_array('cedula', $activos, true)) {
                  $activos[] = 'cedula';
              }

              // Si lookup activo + cliente NO existe en ERP, exigir TODOS los
              // campos requeridos por la integración para crear el cliente nuevo.
              if ($lookupActivo) {
                  try {
                      $integErp = \App\Models\Integracion::where('tenant_id', $tenantId)
                          ->where('activo', true)
                          ->where('exporta_pedidos', true)
                          ->get()
                          ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

                      // Buscar al cliente: si existe, no exigimos sus datos
                      // (se usan los del ERP). Si no existe, sí.
                      if ($integErp && !empty(trim((string) ($orderData['cedula'] ?? '')))) {
                          $clienteSrv = app(\App\Services\ClienteErpService::class);
                          $existeEnErp = $clienteSrv->buscar(
                              $integErp,
                              (string) $orderData['cedula'],
                              (string) ($orderData['phone'] ?? '')
                          );

                          if (!$existeEnErp) {
                              // Agregar campos requeridos por SGI a los activos
                              $reqErp = $integErp->config['cliente_lookup']['campos_requeridos'] ?? [];
                              foreach ($reqErp as $campo) {
                                  if (!in_array($campo, $activos, true)) {
                                      $activos[] = $campo;
                                  }
                              }
                          }
                      }
                  } catch (\Throwable $e) {
                      \Log::warning('No se pudo verificar cliente en ERP para validación: ' . $e->getMessage());
                  }
              }
          }

          // Si el cliente local YA tiene cédula registrada, NO se la exigimos
          // al bot — la inyectamos automáticamente desde BD al confirmar.
          $cedulaExistente = $this->cedulaCienteActual($orderData);

          // Mapeo campo → función validación + label
          $validadores = [
              'cedula'    => ['cédula',          fn ($d) => !empty(trim((string) ($d['cedula'] ?? $d['document_id'] ?? ''))) || !empty($cedulaExistente)],
              'nombre'    => ['nombre completo', fn ($d) => !empty(trim((string) ($d['customer_name'] ?? '')))],
              'producto'  => ['producto y cantidad', fn ($d) => !empty($d['products'] ?? [])],
              'direccion' => ['dirección',       fn ($d) => !empty(trim((string) ($d['address'] ?? '')))
                                                          || !empty(trim((string) ($d['payment_method'] ?? ''))) // si es recoger, no necesita address
                                                          || stripos((string) ($d['notes'] ?? ''), 'recog') !== false],
              'barrio'    => ['barrio',          fn ($d) => !empty(trim((string) ($d['neighborhood'] ?? '')))],
              'ciudad'    => ['ciudad',          fn ($d) => !empty(trim((string) ($d['location'] ?? '')))],
              'telefono'  => ['teléfono',        fn ($d) => !empty(trim((string) ($d['phone'] ?? '')))],
              'email'     => ['correo electrónico', fn ($d) => !empty(trim((string) ($d['email'] ?? '')))
                                                              && filter_var(trim((string) ($d['email'] ?? '')), FILTER_VALIDATE_EMAIL)],
              'metodo_pago' => ['método de pago', fn ($d) => !empty(trim((string) ($d['payment_method'] ?? '')))],
          ];

          foreach ($activos as $campo) {
              if (!isset($validadores[$campo])) continue;
              [$label, $validador] = $validadores[$campo];
              if (!$validador($orderData)) {
                  $faltantes[] = $label;
              }
          }
      } catch (\Throwable $e) {
          \Log::warning('Validación obligatorios falló: ' . $e->getMessage());
      }

      return $faltantes;
  }

  /**
   * 🛡️ GUARD CRÍTICO: el bot llamó `confirmar_pedido` PERO el cliente NO
   * dio intención real de pedir nada en sus últimos mensajes.
   *
   * Causa típica: el LLM lee historial viejo y "continúa" un pedido pasado
   * cuando el cliente solo saluda con "hola", "buenas noches", etc.
   *
   * Detección: revisamos los últimos 3 mensajes del usuario:
   *   - Si solo contienen saludos sin mención de productos/cantidad → SOSPECHOSO
   *   - Si NO hay verbos de intención (quiero, deme, necesito, mándame)
   *     → SOSPECHOSO
   *
   * Si es sospechoso, rechazamos la confirmación y respondemos con un saludo.
   */
  private function esIntentoConfirmacionFalsa(array $conversationHistory): bool
  {
      // Mensajes recientes del usuario (últimos 3)
      $usuarioRecientes = collect($conversationHistory)
          ->reverse()
          ->filter(fn ($m) => ($m['role'] ?? '') === 'user')
          ->take(3)
          ->pluck('content')
          ->reverse()
          ->all();

      if (empty($usuarioRecientes)) return true; // sin mensaje del usuario, claramente no pidió

      $textoUnido = mb_strtolower(implode(' | ', $usuarioRecientes));
      $textoUnido = strtr($textoUnido, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);

      // Solo saludos puros sin mención de pedido
      $patronesSoloSaludo = [
          '/^(hola|buenas|buenos dias|buenas tardes|buenas noches|hi|hey|que tal|que mas|saludos)\s*\.?\s*\|?\s*$/i',
          '/^(hola|buenas|hey)( hola| buenas)*\s*$/i',
      ];
      foreach ($patronesSoloSaludo as $p) {
          if (preg_match($p, trim($textoUnido))) {
              return true; // solo saludó, NO pidió
          }
      }

      // ¿Hay verbo/sustantivo de intención de pedir?
      $palabrasIntencion = [
          'quiero', 'queremos', 'pedir', 'pido', 'pidieramos',
          'mandar', 'mandame', 'enviar', 'envia', 'envien', 'enviame',
          'necesito', 'necesitamos', 'me gustaria', 'gustaria',
          'comprar', 'compro', 'compra', 'comprame',
          'dame', 'deme', 'me da', 'me das', 'me regalas', 'regalame',
          'llevame', 'llevarme',
          'libra', 'libras', 'kilo', 'kilos', 'kg', 'gramos', 'paquete',
          'caja', 'unidad', 'unidades', 'docena',
          'pierna', 'chuleta', 'chicharron', 'res', 'cerdo', 'pollo',
          'cafe', 'lomo', 'costilla', 'producto',
      ];

      $tieneIntencion = false;
      foreach ($palabrasIntencion as $w) {
          if (str_contains($textoUnido, $w)) {
              $tieneIntencion = true;
              break;
          }
      }

      return !$tieneIntencion; // si NO hay intención → es sospechoso
  }

  /**
   * 🛡️ GUARD CRÍTICO: detecta cuando el bot afirma haber confirmado un pedido
   * SIN que se haya creado uno realmente en BD en este request.
   *
   * Causa típica: el LLM lee el historial de conversaciones viejas y
   * "continúa" un pedido pasado como si fuera actual. Si decimos al cliente
   * que tiene un pedido confirmado que no existe, es CATASTRÓFICO.
   *
   * Detección: el reply contiene frases tipo "ya te confirmé el pedido /
   * pedido registrado / confirmé tu pedido" y NO hay un pedido creado
   * en los últimos 30 segundos para ese teléfono.
   *
   * Acción: reemplazar la respuesta por un mensaje neutro que invita a
   * empezar el pedido desde cero.
   */
  private function aplicarGuardPedidoFalsoConfirmado(string $reply, array $toolCalls = []): string
  {
      // Si el LLM llamó confirmar_pedido en este turno, NO hay alucinación
      foreach ($toolCalls as $tc) {
          $name = $tc['function']['name'] ?? '';
          if ($name === 'confirmar_pedido') return $reply;
      }

      $patrones = [
          '/ya te confirm[ée] el pedido/i',
          '/te confirm[ée] el pedido/i',
          '/pedido confirmado/i',
          '/queda registrado/i',
          '/qued[óo] registrado/i',
          '/pedido registrado/i',
          '/✨\s*[¡!]?pedido confirmado/i',
          // Frases que indican alucinación de pedido
          '/(tu )?pedido (de|del|por).*qued[óoa]?/i',          // "tu pedido de X queda/quedó/queda"
          '/qued[óoa]?\s*listo/i',                              // "queda listo", "quedó listo"
          '/lis[at]o para (recoger|recoja|recojas|recojan|entrega|entregar)/i',
          '/lis[at]o para que (lo|la|los|las) (recoja|recojas|recojan|recoger)/i',
          '/te esperamos\s*(con\s*gusto|en|para)/i',           // "te esperamos con gusto"
          '/(ya )?(est[áa]|qued[óoa]?)\s*(listo|preparado|registrado|reservado|agendado)/i',
          '/te reservo.*(libras|kilos|productos|cantidad|kg|gramos|unidades)/i',
          '/voy a (registrar|reservar|preparar|agendar) tu pedido/i',
          '/(¡|!)?[hH]asta pronto.*(pedido|recoger|entrega)/i',
      ];

      $coincide = false;
      foreach ($patrones as $p) {
          if (preg_match($p, $reply)) { $coincide = true; break; }
      }
      if (!$coincide) return $reply;

      // Verificar si HAY pedido creado en los últimos 60s para este tenant
      try {
          $tenantId = app(\App\Services\TenantManager::class)->id();
          $reciente = \App\Models\Pedido::where('tenant_id', $tenantId)
              ->where('created_at', '>=', now()->subMinute())
              ->exists();
          if ($reciente) return $reply; // hubo pedido real, no es alucinación
      } catch (\Throwable $e) { /* sigue al fix */ }

      // ALUCINACIÓN: reescribir
      $original = $reply;
      $reply = "Disculpa, hubo un detalle al registrar el pedido 🙏. "
             . "¿Me confirmas que sí lo deseas con un 'sí'? "
             . "Para finalizar también necesito tu cédula (es obligatoria para registrarte en el sistema).";

      \Log::warning('🚨 GUARD: bot alucinó pedido confirmado sin haberlo creado', [
          'original'  => $original,
          'reescrita' => $reply,
      ]);

      return $reply;
  }

  /**
   * 🛡️ GUARD: si el bot generó una respuesta diciendo "no puedo registrar"
   * cuando estamos cerrados Y el tenant tiene activo el toggle de aceptar
   * pedidos fuera de horario, la reescribe por una respuesta correcta.
   *
   * Última línea de defensa contra alucinaciones del LLM.
   */
  private function aplicarGuardPedidosProgramados(string $reply): string
  {
      try {
          // 🛡️ Si el EARLY GUARD ya respondió en este turno, NO reescribir.
          // El EARLY GUARD ya genera un mensaje correcto (saludo + bienvenida
          // + cierre + ofrecer programar) y este guard de fallback no debe
          // mutilarlo a una versión más corta.
          if (request()->attributes->get('early_guard_handled') === true) {
              return $reply;
          }

          // 🛡️ Si el reply ya tiene formato "PROGRAMADO" + 📅, está bien — no tocar.
          if (mb_stripos($reply, 'PROGRAMADO') !== false && str_contains($reply, '📅')) {
              return $reply;
          }

          $cfgBot = \App\Models\ConfiguracionBot::actual();
          if (!$cfgBot?->aceptar_pedidos_fuera_horario) return $reply;

          // Verificar que TODAS las sedes activas estén cerradas
          $sedes = \App\Models\Sede::where('activa', true)->get();
          if ($sedes->isEmpty()) return $reply;

          $todasCerradas = $sedes->every(fn ($s) => !$s->estaAbierta());
          if (!$todasCerradas) return $reply;

          // Frases prohibidas que indican alucinación del LLM
          $patronesProhibidos = [
              '/no puedo registrar/i',
              '/no podr[ée] registrar/i',
              '/no puedo tomar (el|tu) pedido/i',
              '/te aviso (apenas|cuando) abramos/i',
              '/te (atender[ée]|espero) mañana/i',
              '/te atendemos mañana/i',
              '/te atender[ée] mañana/i',
              '/vuelve mañana/i',
              '/escr[íi]beme mañana/i',
              '/cont[áa]ctame mañana/i',
              '/regresa mañana/i',
              '/ahorita estamos cerrados/i', // si va seguido de "te aviso/atiendo"
          ];

          $coincide = false;
          foreach ($patronesProhibidos as $patron) {
              if (preg_match($patron, $reply)) {
                  $coincide = true;
                  break;
              }
          }

          if (!$coincide) return $reply;

          // Calcular próxima apertura
          $proxima = $sedes->first()?->proximaApertura() ?: 'mañana 8:00 am';

          $replyOriginal = $reply;
          $reply = "Estamos cerrados ahora pero te puedo dejar el pedido *PROGRAMADO* "
                 . "para {$proxima} 📅\n\n"
                 . "¿Te parece bien? Si me confirmas, sigo con tu pedido y queda en cola "
                 . "para preparar apenas abramos.";

          \Log::warning('🛡️ Guard activado — respuesta del LLM reescrita por alucinación', [
              'original' => $replyOriginal,
              'reescrita' => $reply,
          ]);
      } catch (\Throwable $e) {
          \Log::warning('Guard pedidos programados falló: ' . $e->getMessage());
      }

      return $reply;
  }

  /**
   * Detecta el nombre de una ciudad colombiana mencionada en un texto libre.
   * Busca coincidencias case/tilde insensibles para evitar pasar 'Bello' por
   * default cuando la dirección habla de Bogotá, Cali, etc.
   */
  private function detectarCiudadDesdeDireccion(?string $texto): ?string
  {
      if (empty($texto)) return null;
      $t = mb_strtolower($texto);
      $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);

      // Ordenado por longitud descendente para que "santa marta" gane sobre "santa".
      $ciudades = [
          'bogota d.c.' => 'Bogotá',
          'santa marta' => 'Santa Marta',
          'la estrella' => 'La Estrella',
          'barranquilla' => 'Barranquilla', 'bucaramanga' => 'Bucaramanga',
          'floridablanca' => 'Floridablanca', 'piedecuesta' => 'Piedecuesta',
          'villavicencio' => 'Villavicencio', 'dosquebradas' => 'Dosquebradas',
          'valledupar' => 'Valledupar', 'sincelejo' => 'Sincelejo',
          'cartagena' => 'Cartagena', 'medellin' => 'Medellín',
          'envigado' => 'Envigado', 'sabaneta' => 'Sabaneta',
          'copacabana' => 'Copacabana', 'girardota' => 'Girardota',
          'rionegro' => 'Rionegro', 'concordia' => 'Concordia',
          'manizales' => 'Manizales', 'pereira' => 'Pereira',
          'palmira' => 'Palmira', 'jamundi' => 'Jamundí',
          'monteria' => 'Montería', 'riohacha' => 'Riohacha',
          'maicao' => 'Maicao', 'popayan' => 'Popayán',
          'pasto' => 'Pasto', 'tumaco' => 'Tumaco',
          'tunja' => 'Tunja', 'duitama' => 'Duitama', 'sogamoso' => 'Sogamoso',
          'cucuta' => 'Cúcuta', 'armenia' => 'Armenia', 'ibague' => 'Ibagué',
          'neiva' => 'Neiva', 'quibdo' => 'Quibdó', 'leticia' => 'Leticia',
          'soacha' => 'Soacha', 'chia' => 'Chía', 'zipaquira' => 'Zipaquirá',
          'mosquera' => 'Mosquera', 'funza' => 'Funza', 'cajica' => 'Cajicá',
          'soledad' => 'Soledad', 'malambo' => 'Malambo',
          'buenaventura' => 'Buenaventura', 'tulua' => 'Tuluá',
          'yumbo' => 'Yumbo', 'itagui' => 'Itagüí', 'caldas' => 'Caldas',
          'barbosa' => 'Barbosa', 'giron' => 'Girón', 'cienaga' => 'Ciénaga',
          'bogota' => 'Bogotá', 'cali' => 'Cali', 'bello' => 'Bello',
      ];

      foreach ($ciudades as $needle => $nombre) {
          if (strpos($t, $needle) !== false) {
              return $nombre;
          }
      }
      return null;
  }

  /**
   * 🔄 Ejecuta un batch de tool_calls del LLM en una iteración del loop.
   * Reusa la lógica de las tools existentes pero las invoca directamente
   * según el nombre, sin volver a entrar al pipeline completo del webhook.
   */
  private function ejecutarToolCallsBatch(array $toolCalls, $conversacion, $connectionId, string $from): array
  {
      $resultados = [];
      foreach ($toolCalls as $tc) {
          $name    = $tc['function']['name']      ?? '';
          $rawArgs = $tc['function']['arguments'] ?? '{}';
          $args    = json_decode($rawArgs, true) ?: [];
          $tcId    = $tc['id'] ?? ('call_' . uniqid());

          $resultado = $this->ejecutarToolPorNombre($name, $args, $conversacion, $connectionId, $from);

          $resultados[] = [
              'role'         => 'tool',
              'tool_call_id' => $tcId,
              'name'         => $name,
              'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
          ];

          try {
              \App\Models\AgenteToolInvocacion::create([
                  'tenant_id'        => $conversacion->tenant_id ?? null,
                  'conversacion_id'  => $conversacion->id ?? null,
                  'tool_name'        => $name,
                  'connection_id'    => (string) ($connectionId ?? ''),
                  'telefono_cliente' => $from ?? null,
                  'args'             => $args,
                  'resultado'        => $this->resumirResultadoTool($name, $resultado),
                  'count_resultados' => (int) ($resultado['encontrados']
                      ?? $resultado['total_categorias']
                      ?? (isset($resultado['productos']) ? count($resultado['productos']) : 0)
                      ?? 0),
                  'exitoso'          => true,
                  'latencia_ms'      => 0,
              ]);
          } catch (\Throwable $e) {
              // ignore
          }
      }
      return $resultados;
  }

  /**
   * Ejecuta una tool por nombre y devuelve el resultado.
   * Conoce todas las tools del bot principal.
   */
  private function ejecutarToolPorNombre(string $name, array $args, $conversacion, $connectionId, string $from): array
  {
      try {
          $sedeId = $this->obtenerSedeIdDesdeConexion($connectionId);

          return match ($name) {
              'buscar_productos' => app(\App\Services\BotCatalogoToolService::class)
                  ->buscarProductos(
                      (string) ($args['query'] ?? ''),
                      $args['categoria'] ?? null,
                      (int) ($args['limite'] ?? 5),
                      $sedeId
                  ),

              'productos_de_categoria' => app(\App\Services\BotCatalogoToolService::class)
                  ->productosDeCategoria(
                      (string) ($args['categoria'] ?? ''),
                      (int) ($args['limite'] ?? 20),
                      $sedeId
                  ),

              'listar_categorias' => app(\App\Services\BotCatalogoToolService::class)
                  ->listarCategorias(),

              'info_producto' => app(\App\Services\BotCatalogoToolService::class)
                  ->infoProducto((string) ($args['producto'] ?? ''), $sedeId),

              'productos_destacados' => app(\App\Services\BotCatalogoToolService::class)
                  ->productosDestacados((int) ($args['limite'] ?? 5), $sedeId),

              'consultar_horarios' => $this->resultadoHorarios(),

              'consultar_zonas_cobertura' => $this->resultadoZonas(),

              'consultar_promociones' => $this->resultadoPromociones(),

              'consultar_mis_pedidos' => $this->resultadoMisPedidos($from, (int) ($args['limite'] ?? 5)),

              'crear_adicion_pedido' => app(\App\Services\Bots\AdicionPedidoService::class)
                  ->crear(
                      (int) ($args['pedido_id_origen'] ?? 0),
                      is_array($args['productos'] ?? null) ? $args['productos'] : [],
                      $from
                  ),

              'validar_cobertura' => $this->validarCoberturaDireccion(
                  (string) ($args['direccion'] ?? ''),
                  $args['barrio'] ?? null,
                  $args['ciudad'] ?? 'Bello',
                  $sedeId,
                  $from
              ),

              'verificar_cliente_erp' => $this->resultadoVerificarClienteErp(
                  (string) ($args['cedula'] ?? ''),
                  (string) ($args['telefono'] ?? $from)
              ),

              default => ['error' => "Tool '{$name}' no implementada en loop"],
          };
      } catch (\Throwable $e) {
          Log::warning("Tool {$name} excepción en loop: " . $e->getMessage());
          return ['error' => $e->getMessage()];
      }
  }

  private function resultadoHorarios(): array
  {
      $sedes = \App\Models\Sede::where('activa', true)->get();
      return [
          'sedes' => $sedes->map(fn ($s) => [
              'nombre'   => $s->nombre,
              'abierta'  => $s->estaAbierta(),
              'proxima'  => $s->proximaApertura(),
              'horarios' => $s->horarios ?? null,
          ])->all(),
      ];
  }

  private function resultadoZonas(): array
  {
      $zonas = \App\Models\ZonaCobertura::where('activa', true)->get();
      return [
          'zonas' => $zonas->map(fn ($z) => [
              'nombre'        => $z->nombre,
              'descripcion'   => $z->descripcion ?? null,
              'costo_envio'   => (float) $z->costo_envio,
              'pedido_minimo' => (float) $z->pedido_minimo,
              'tiempo'        => $z->tiempo_entrega_estimado ?? null,
          ])->all(),
      ];
  }

  private function resultadoPromociones(): array
  {
      $promos = \App\Models\Promocion::where('activa', true)
          ->where('fecha_inicio', '<=', now())
          ->where('fecha_fin', '>=', now())
          ->get();
      return [
          'total' => $promos->count(),
          'promociones' => $promos->map(fn ($p) => [
              'nombre'      => $p->nombre,
              'descripcion' => $p->descripcion,
              'tipo'        => $p->tipo,
              'valor'       => (float) $p->valor,
              'codigo'      => $p->codigo_cupon,
          ])->all(),
      ];
  }

  private function resultadoVerificarClienteErp(string $cedula, string $telefono): array
  {
      $tenantId = app(\App\Services\TenantManager::class)->id();
      $integ = \App\Models\Integracion::where('tenant_id', $tenantId)
          ->where('activo', true)
          ->where('exporta_pedidos', true)
          ->get()
          ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

      if (!$integ || !$cedula) {
          return ['existe' => false, 'mensaje' => 'Lookup no disponible'];
      }

      $clienteErp = app(\App\Services\ClienteErpService::class)->buscar($integ, $cedula, $telefono);
      if ($clienteErp) {
          return [
              'existe' => true,
              'datos'  => [
                  'cedula'    => $cedula,
                  'nombre'    => $clienteErp['StrNombre']    ?? null,
                  'telefono'  => $clienteErp['StrCelular']   ?? null,
                  'direccion' => $clienteErp['StrDireccion'] ?? null,
              ],
          ];
      }

      $req = $integ->config['cliente_lookup']['campos_requeridos'] ?? [];
      return [
          'existe' => false,
          'campos_faltantes' => array_values(array_diff($req, ['cedula','telefono'])),
      ];
  }

  /**
   * 🛡️ Limpia una dirección para que el geocoder (Google/Nominatim) la
   * resuelva mejor. Quita partes que NO ayudan a localizar en el mapa:
   *   - Apto/Apartamento/Torre/Bloque/Casa/Piso/Interior/Local
   *   - Nombres de conjuntos residenciales ("Reserva de Búcaros")
   * Conserva solo: <vía> <número> <barrio>
   *
   * Ej: "Calle 41 #59bb 35, Apto 1214, Reserva de Bucaros, Bello"
   *  → "Calle 41 #59bb 35, Bello"
   */
  private function limpiarDireccionParaGeocoding(string $direccion): string
  {
      if ($direccion === '') return '';

      $partes = preg_split('/\s*,\s*/u', $direccion);
      $limpio = [];

      $patronesEliminar = [
          '/^(apto|apartamento|apt|apartment)\s*\.?\s*\d+\w*$/iu',
          '/^(torre|bloque|blq)\s*\.?\s*\w+$/iu',
          '/^(casa|cs)\s*\.?\s*\d+\w*$/iu',
          '/^(piso|p)\s*\.?\s*\d+\w*$/iu',
          '/^(interior|int)\s*\.?\s*\d+\w*$/iu',
          '/^(local|loc)\s*\.?\s*\d+\w*$/iu',
          '/^(oficina|of)\s*\.?\s*\d+\w*$/iu',
          // Nombres de conjuntos: típicamente palabras con mayúsculas sin números
          '/^(conjunto|conj|urbanizacion|urb|reserva|villa|villas|portal|portales|parque|parques)\s+[a-z\sñáéíóú]+$/iu',
      ];

      foreach ($partes as $p) {
          $p = trim($p);
          if ($p === '') continue;
          $skip = false;
          foreach ($patronesEliminar as $pat) {
              if (preg_match($pat, $p)) { $skip = true; break; }
          }
          if (!$skip) $limpio[] = $p;
      }

      return implode(', ', $limpio);
  }

  private function validarCoberturaDireccion(
      string $direccion,
      ?string $barrio = null,
      ?string $ciudad = 'Bello',
      ?int $sedeId = null,
      ?string $telefonoCliente = null
  ): array {
      $zonaResolver = app(ZonaResolverService::class);
      $sedeResolver = app(\App\Services\SedeResolverService::class);

      $zona   = null;
      $metodo = null;
      $coord  = null;

      // 🌟 ESTRATEGIA NUEVA (PREFERIDA): cobertura DIRECTA en sedes
      // Cada sede tiene su propio polígono. SedeResolverService elige la
      // mejor sede (cercanía + abierto) automáticamente.
      if (!empty($direccion) || !empty($barrio)) {
          // 🛡️ Limpiar dirección para geocoding: quitar Apto, Torre, etc.
          // que confunden a los geocoders. Mantener solo vía + número + barrio.
          $direccionLimpia = $this->limpiarDireccionParaGeocoding($direccion);

          $geocode = app(GeocodingService::class)->geocodificar(
              $direccionLimpia ?: $direccion ?: '',
              $barrio,
              $ciudad ?: 'Bello'
          );

          if ($geocode) {
              $coord = $geocode;
              $tenantId = app(\App\Services\TenantManager::class)->id();
              $resultado = $sedeResolver->resolverParaPunto($geocode['lat'], $geocode['lng'], $tenantId);

              if ($resultado['cubierta'] && $resultado['sede']) {
                  $sede = $resultado['sede'];
                  $sedeAlt = $resultado['sede_alternativa'];

                  Log::info('✅ Cobertura por sede (smart resolver)', [
                      'sede'           => $sede->nombre,
                      'distancia_km'   => $resultado['distancia_km'],
                      'sede_cerrada'   => $sedeAlt ? true : false,
                      'coord'          => $geocode,
                  ]);

                  $costoOriginal = (float) ($sede->cobertura_costo_envio ?? 0);
                  $beneficioInfo = null;
                  $costoEfectivo = $costoOriginal;

                  // Beneficio activo (envío gratis por cumple, etc)
                  if (!empty($telefonoCliente)) {
                      $telNorm = $this->normalizarTelefono($telefonoCliente);
                      $clientePosible = Cliente::where('telefono_normalizado', $telNorm)->first();
                      if ($clientePosible) {
                          $ben = $clientePosible->beneficioVigente(\App\Models\BeneficioCliente::TIPO_ENVIO_GRATIS);
                          if ($ben) {
                              $beneficioInfo = [
                                  'tipo'            => 'envio_gratis',
                                  'origen'          => $ben->origen,
                                  'vigente_hasta'   => $ben->vigente_hasta?->format('d/m/Y'),
                                  'descripcion'     => $ben->descripcion,
                                  'ahorro_original' => $costoOriginal,
                              ];
                              $costoEfectivo = 0;
                          }
                      }
                  }

                  // ⚠️ Texto neutro: NO insinúa pedido confirmado, NO contiene
                  // instrucciones internas (el bot a veces las copia al cliente).
                  $mensajeSugerido = "Cobertura confirmada desde *{$sede->nombre}* (a {$resultado['distancia_km']} km, ~{$sede->cobertura_tiempo_min} min).";
                  if ($sedeAlt) {
                      $mensajeSugerido = "Atendiendo desde *{$sede->nombre}* (~{$sede->cobertura_tiempo_min} min) — la sede más cercana está cerrada ahora.";
                  }

                  return [
                      'cubierta'         => true,
                      'zona'             => $sede->nombre,
                      'sede_sugerida'    => $sede->nombre,
                      'sede_id'          => $sede->id,
                      'distancia_km'     => $resultado['distancia_km'],
                      'costo_envio'      => $costoEfectivo,
                      'costo_original'   => $costoOriginal,
                      'tiempo_estimado'  => $sede->cobertura_tiempo_min,
                      'pedido_minimo'    => (float) ($sede->cobertura_pedido_minimo ?? 0),
                      'beneficio_activo' => $beneficioInfo,
                      'coordenadas'      => $coord,
                      'mensaje_sugerido' => $mensajeSugerido,
                      'metodo_usado'     => 'sede_poligono_smart',
                      'aviso_alternativa' => $sedeAlt ? "Sede más cercana cerrada — atendiendo desde {$sede->nombre}" : null,
                  ];
              }

              // Si no cubierta pero hay sede más cercana para recoger
              if (!$resultado['cubierta'] && $resultado['recoger_en_sede']) {
                  $sedeRecoger = $resultado['recoger_en_sede'];
                  $distancia = $resultado['distancia_km'];
                  Log::info('ℹ️ Sin cobertura — sugerir recoger', [
                      'sede_mas_cercana' => $sedeRecoger->nombre,
                      'distancia_km' => $distancia,
                  ]);
                  // Caemos al return de "sin cobertura" pero con sugerencia rica
              }
          }
      }

      // ── Estrategia LEGACY: Geocode + polígono de ZonaCobertura (compat) ──
      if (!empty($direccion) || !empty($barrio)) {
          if (!isset($geocode) || !$geocode) {
              $geocode = app(GeocodingService::class)->geocodificar(
                  $direccion ?: '',
                  $barrio,
                  $ciudad ?: 'Bello'
              );
          }

          if ($geocode) {
              $coord = $geocode;
              $zona = $zonaResolver->porCoordenadas($geocode['lat'], $geocode['lng'], $sedeId);
              if ($zona) {
                  $metodo = 'poligono_mapa';
                  Log::info('✅ Cobertura por polígono (legacy zonas)', [
                      'zona'  => $zona->nombre,
                      'coord' => $geocode,
                  ]);
              } else {
                  Log::info('⚠️ Dirección geocodificada pero fuera de todos los polígonos', [
                      'coord' => $geocode,
                  ]);
              }
          }
      }

      // ── Estrategia 2: Fallback por nombre de barrio ──────────────────
      // Solo aplica si el geocode NO encontró polígono. Es menos preciso
      // pero cubre casos donde Nominatim no resuelve direcciones colombianas.
      if (!$zona && !empty($barrio)) {
          $zona = \App\Models\ZonaCobertura::resolverPorBarrio($barrio, $sedeId);
          if ($zona) {
              $metodo = 'barrio_nombre_fallback';
              Log::info('⚠️ Cobertura por nombre de barrio (geocode falló)', [
                  'barrio' => $barrio,
                  'zona'   => $zona->nombre,
              ]);
          }
      }

      if (!$zona) {
          return [
              'cubierta'         => false,
              'zona'             => null,
              'costo_envio'      => null,
              'tiempo_estimado'  => null,
              'coordenadas'      => $coord,
              'mensaje_sugerido' => "Verifiqué tu dirección en el mapa y me queda fuera de cobertura 😔. "
                  . "Puedo dejarte el pedido listo para que lo recojas en sede — "
                  . "o si me pasas otra dirección más cercana, lo valido otra vez.",
              'metodo_usado'     => null,
          ];
      }

      $costoOriginal = (float) $zona->costo_envio;

      // 🎁 Detectar beneficio vigente ANTES de construir el mensaje
      // para que el costo mostrado sea YA el final (con descuento aplicado).
      $beneficioInfo = null;
      $costoEfectivo = $costoOriginal;

      if (!empty($telefonoCliente)) {
          $telNorm = $this->normalizarTelefono($telefonoCliente);
          $clientePosible = Cliente::where('telefono_normalizado', $telNorm)->first();
          if ($clientePosible) {
              $ben = $clientePosible->beneficioVigente(\App\Models\BeneficioCliente::TIPO_ENVIO_GRATIS);
              if ($ben) {
                  $beneficioInfo = [
                      'tipo'            => 'envio_gratis',
                      'origen'          => $ben->origen,
                      'vigente_hasta'   => $ben->vigente_hasta?->format('d/m/Y'),
                      'descripcion'     => $ben->descripcion,
                      'ahorro_original' => $costoOriginal,
                  ];
                  $costoEfectivo = 0;   // ← el cliente NO paga envío
              }
          }
      }

      $costoStr = $costoEfectivo > 0
          ? '$' . number_format($costoEfectivo, 0, ',', '.')
          : 'GRATIS';

      $tiempoMin = $zona->tiempo_estimado_min ?? null;
      $tiempoStr = $tiempoMin
          ? "{$tiempoMin} min"
          : '~30-45 min';

      $pedidoMinimo = (float) $zona->pedido_minimo;
      $pedidoMinimoStr = $pedidoMinimo > 0
          ? '$' . number_format($pedidoMinimo, 0, ',', '.')
          : null;

      $mensajeBase = "Sí llegamos a tu dirección ✅ Zona *{$zona->nombre}* — envío *{$costoStr}*, {$tiempoStr}.";
      if ($beneficioInfo) {
          $mensajeBase .= " 🎁 *Envío GRATIS aplicado por {$beneficioInfo['origen']}* "
              . "(hasta {$beneficioInfo['vigente_hasta']}). Normalmente sería \$"
              . number_format($costoOriginal, 0, ',', '.') . ".";
      }
      if ($pedidoMinimoStr) {
          $mensajeBase .= " Pedido mínimo para domicilio en esta zona: *{$pedidoMinimoStr}*.";
      }

      // ── Sede más cercana (si tenemos coordenadas del cliente) ─────────
      $sedeCercana = null;
      $sedeCercanaNombre = null;
      $distanciaKm = null;
      if ($coord && isset($coord['lat'], $coord['lng'])) {
          $sedeCercana = Sede::masCercanaA((float) $coord['lat'], (float) $coord['lng']);
          if ($sedeCercana) {
              $sedeCercanaNombre = $sedeCercana->nombre;
              $distanciaKm = $sedeCercana->_distancia_km ?? null;
              if ($distanciaKm) {
                  $mensajeBase .= " Te despacharemos desde *{$sedeCercanaNombre}* (a " . number_format($distanciaKm, 1) . " km).";
              }
          }
      }

      return [
          'cubierta'            => true,
          'zona'                => $zona->nombre,
          'zona_id'             => $zona->id,
          // costo_envio ya refleja el descuento aplicado
          'costo_envio'         => $costoEfectivo,
          'costo_envio_str'     => $costoStr,
          'costo_envio_original'=> $costoOriginal,
          'pedido_minimo'       => $pedidoMinimo,
          'pedido_minimo_str'   => $pedidoMinimoStr,
          'tiempo_estimado'     => $tiempoStr,
          'coordenadas'         => $coord,
          'sede_sugerida'       => $sedeCercanaNombre,
          'sede_sugerida_id'    => $sedeCercana?->id,
          'distancia_km'        => $distanciaKm ? round($distanciaKm, 2) : null,
          'beneficio_activo'    => $beneficioInfo,
          'mensaje_sugerido'    => $mensajeBase,
          'metodo_usado'        => $metodo,
      ];
  }

  public function guardarPedidoDesdeToolCall(
    array $orderData,
    string $from,
    string $name,
    array $conversationHistory,
    string $cacheKey,
    ?string $connectionId = null,
    ?\App\Models\ConversacionWhatsapp $conversacion = null,
    ?\App\Services\ConversacionService $convService = null
): string {
    try {
        $telNorm = $this->normalizarTelefono($from);

        // 🛡️ MULTI-TENANT: si TenantManager NO tiene tenant seteado pero la
        // conversación SÍ, hidratamos el contexto. Sin esto los pedidos
        // creados desde tinker o jobs sin contexto quedan tenant_id=NULL
        // y NO aparecen en /pedidos por el global scope BelongsToTenant.
        $tm = app(\App\Services\TenantManager::class);
        if (!$tm->id() && $conversacion?->tenant_id) {
            $t = \App\Models\Tenant::find($conversacion->tenant_id);
            if ($t) $tm->set($t);
        }

        $tenantId = $tm->id() ?? 'none';
        $confirmKey = "pedido_confirmado_t{$tenantId}_" . $telNorm;

        // 🚨 GUARD CRÍTICO: CÉDULA OBLIGATORIA si hay lookup ERP activo.
        // Sin cédula NO se puede crear el pedido (cliente no se puede
        // registrar en SGI, no se puede trackear el pedido).
        if ($conversacion) {
            $integLookupActivo = \App\Models\Integracion::where('tenant_id', $conversacion->tenant_id)
                ->where('activo', true)
                ->where('exporta_pedidos', true)
                ->get()
                ->contains(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

            if ($integLookupActivo) {
                $cedulaOrder = trim((string) ($orderData['cedula'] ?? ''));
                if ($cedulaOrder === '' || !preg_match('/^\d{6,12}$/', $cedulaOrder)) {
                    Log::warning('🚨 GUARD: pedido bloqueado — cédula NO presente o inválida', [
                        'from'   => $from,
                        'cedula' => $cedulaOrder,
                    ]);

                    $primerNombre = explode(' ', trim((string) $name))[0] ?? '';
                    $saludo = $primerNombre !== '' && !str_contains($primerNombre, '@')
                        ? " {$primerNombre}" : '';

                    return "Antes de cerrar tu pedido{$saludo}, necesito tu *número de cédula* (sin puntos). "
                         . "Es obligatorio para registrarte en el sistema. 🪪\n\n"
                         . "Pásamela por favor.";
                }
            }
        }

        // 🛡️ CORTAFUEGO ANTI-PEDIDO-FANTASMA:
        // Si los productos del orderData NO aparecen mencionados en los
        // últimos 8 mensajes del cliente Y TAMPOCO están en el estado
        // persistente, RECHAZAR el pedido.
        //
        // El estado persistente es prueba de que en algún momento el
        // captador detectó al cliente pidiendo ese producto. Es seguro.
        if ($conversacion) {
            $productos = $orderData['products'] ?? [];
            if (!empty($productos)) {
                // PASO 1: ¿Los productos coinciden con el estado persistente?
                $estado = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
                $productosEstado = collect($estado->productos ?? [])
                    ->map(fn ($p) => mb_strtolower(\Illuminate\Support\Str::ascii(
                        (string) ($p['name'] ?? $p['code'] ?? '')
                    )))
                    ->filter()
                    ->all();

                $coincideConEstado = false;
                foreach ($productos as $p) {
                    $nombreP = mb_strtolower(\Illuminate\Support\Str::ascii((string) ($p['name'] ?? '')));
                    foreach ($productosEstado as $pe) {
                        // Match si comparten algún token significativo (>=4 chars)
                        $tokensP = collect(preg_split('/\s+/', $nombreP))
                            ->filter(fn ($t) => mb_strlen($t) >= 4)
                            ->all();
                        foreach ($tokensP as $t) {
                            if (str_contains($pe, $t) || str_contains($nombreP, $pe)) {
                                $coincideConEstado = true;
                                break 3;
                            }
                        }
                    }
                }

                // Si los productos del orderData coinciden con el estado,
                // confiamos: el captador los detectó en algún momento real.
                if ($coincideConEstado) {
                    Log::info('🛡️ Cortafuego: pedido validado por estado persistente', [
                        'productos' => array_map(fn ($p) => $p['name'] ?? '?', $productos),
                    ]);
                } else {
                    // PASO 2: ¿Aparecen en los últimos 8 mensajes del cliente?
                    $ultimosUser = \App\Models\MensajeWhatsapp::query()
                        ->where('conversacion_id', $conversacion->id)
                        ->where('rol', 'user')
                        ->orderByDesc('id')
                        ->limit(15)
                        ->pluck('contenido')
                        ->all();
                    $textoUser = mb_strtolower(\Illuminate\Support\Str::ascii(implode(' ', $ultimosUser)));

                    $algunoMencionado = false;
                    foreach ($productos as $p) {
                        $nombreP = mb_strtolower(\Illuminate\Support\Str::ascii((string) ($p['name'] ?? '')));
                        $tokens = collect(preg_split('/\s+/', $nombreP))
                            ->filter(fn ($t) => mb_strlen($t) >= 4)
                            ->values();
                        foreach ($tokens as $t) {
                            if (str_contains($textoUser, $t)) { $algunoMencionado = true; break 2; }
                        }
                    }

                    if (!$algunoMencionado) {
                        Log::warning('🛡️ CORTAFUEGO: pedido bloqueado — productos NO en estado NI mencionados por cliente', [
                            'from' => $from,
                            'productos_orderData' => array_map(fn ($p) => $p['name'] ?? '?', $productos),
                            'productos_estado'    => $productosEstado,
                        ]);
                        return "Disculpa {$name} 🙏 hubo un problema interpretando tu pedido. "
                             . "¿Me puedes decir exactamente qué productos quieres y en qué cantidad? "
                             . "Así te lo registro bien.";
                    }
                }
            }
        }

        // 🆔 PASO PRE-PEDIDO: asegurar cliente en SGI/ERP antes de crear el pedido.
        // Si ERP tiene lookup activo y el cliente NO existe, lo creamos con los
        // datos del orderData. Si la creación falla o faltan datos requeridos,
        // abortamos el pedido y avisamos.
        if ($conversacion && !empty($orderData['cedula'])) {
            try {
                $integErp = \App\Models\Integracion::where('tenant_id', $conversacion->tenant_id)
                    ->where('activo', true)
                    ->where('exporta_pedidos', true)
                    ->get()
                    ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

                if ($integErp) {
                    $clienteSrv = app(\App\Services\ClienteErpService::class);

                    // 1. Verificar si existe
                    $clienteEnSgi = $clienteSrv->buscar(
                        $integErp,
                        (string) ($orderData['cedula'] ?? ''),
                        (string) ($orderData['phone'] ?? $from)
                    );

                    if (!$clienteEnSgi) {
                        // 2. NO existe → crear con los datos del orderData
                        $datosCrear = [
                            'cedula'    => $orderData['cedula'] ?? '',
                            'nombre'    => $orderData['customer_name'] ?? '',
                            'telefono'  => $orderData['phone'] ?? $from,
                            'email'     => $orderData['email'] ?? '',
                            'direccion' => $orderData['address'] ?? '',
                        ];

                        // Validar que tenemos todo lo requerido por ERP
                        $reqErp = $integErp->config['cliente_lookup']['campos_requeridos'] ?? [];
                        $faltantesErp = [];
                        foreach ($reqErp as $campo) {
                            if (empty(trim((string) ($datosCrear[$campo] ?? '')))) {
                                $faltantesErp[] = $campo;
                            }
                        }

                        if (!empty($faltantesErp)) {
                            Log::warning('🚨 No se puede crear cliente en ERP — faltan datos', [
                                'cedula' => $orderData['cedula'],
                                'faltan' => $faltantesErp,
                            ]);

                            // 🛡️ Pedir TODOS los faltantes en un solo mensaje claro
                            $etiquetas = [
                                'cedula'    => 'Número de cédula (sin puntos)',
                                'nombre'    => 'Nombre completo',
                                'telefono'  => 'Teléfono de contacto',
                                'email'     => 'Correo electrónico',
                                'direccion' => 'Dirección de entrega',
                            ];

                            $lineas = ["Para crear tu cuenta y procesar el pedido, necesito estos datos:\n"];
                            $i = 1;
                            foreach ($faltantesErp as $campo) {
                                $etiqueta = $etiquetas[$campo] ?? $campo;
                                $lineas[] = "{$i}. *{$etiqueta}*";
                                $i++;
                            }
                            $lineas[] = "\nPuedes pasármelos todos en un solo mensaje. 🙏";

                            return implode("\n", $lineas);
                        }

                        // Crear cliente en SGI
                        $okCrear = $clienteSrv->crear($integErp, $datosCrear);
                        if (!$okCrear) {
                            Log::error('❌ Falló creación de cliente en ERP', ['datos' => $datosCrear]);
                            return "⚠️ Tuve un problema al registrarte en nuestro sistema. Intenta de nuevo en un momento o llama a la sede.";
                        }

                        Log::info('✅ Cliente creado en SGI antes del pedido', [
                            'cedula' => $orderData['cedula'],
                        ]);
                    } else {
                        Log::info('✅ Cliente ya existía en SGI', [
                            'cedula' => $orderData['cedula'],
                        ]);
                    }
                }
            } catch (\Throwable $eClienteSgi) {
                Log::error('❌ Error asegurando cliente en SGI: ' . $eClienteSgi->getMessage());
                // No abortamos el pedido por esto — el bot puede continuar y
                // los logs revelarán el problema al admin.
            }
        }

        if (Cache::has($confirmKey)) {
            // El cliente acaba de confirmar un pedido. Traemos el último pedido
            // para darle una respuesta útil y no un mensaje genérico.
            Log::warning('⚠️ Bot intentó confirmar de nuevo un pedido ya registrado', compact('from'));

            $ultimoPedido = Pedido::where('telefono_whatsapp', $telNorm)
                ->orderByDesc('id')
                ->first();

            if ($ultimoPedido) {
                $total = '$' . number_format((float) $ultimoPedido->total, 0, ',', '.');
                $beneficio = \App\Models\BeneficioCliente::where('pedido_id', $ultimoPedido->id)->first();

                $msg = "Tu pedido #{$ultimoPedido->id} ya quedó registrado ✅\n\n"
                    . "💵 Total: {$total}\n";

                if ($beneficio) {
                    $msg .= "🎁 Incluye envío gratis por " . $beneficio->origen . ".\n";
                }

                // El enlace de seguimiento ya fue enviado en la confirmación inicial,
                // no lo repetimos para no saturar al cliente.
                $msg .= "\nSi necesitas algo distinto al pedido #{$ultimoPedido->id}, cuéntame qué es y te ayudo 🙌";

                return $msg;
            }

            return "Tu pedido ya fue registrado 😊 Cuéntame qué necesitas ahora y te ayudo.";
        }

        Cache::put($confirmKey, true, now()->addMinutes(2));

        DB::beginTransaction();

        $conexionData = $this->resolverConexionWhatsapp($connectionId);

        $empresaId = $conexionData['empresa_id'];
        $connectionId = $conexionData['connection_id'];
        $whatsappId = $conexionData['whatsapp_id'];

        $sede = Sede::find($this->obtenerSedeIdDesdeConexion($connectionId)) ?? Sede::first();

        $partes = array_filter([
            $orderData['notes'] ?? null,
            isset($orderData['address']) ? "Dirección: {$orderData['address']}" : null,
            isset($orderData['neighborhood']) ? "Barrio: {$orderData['neighborhood']}" : null,
            isset($orderData['payment_method']) ? "Pago: {$orderData['payment_method']}" : null,
            isset($orderData['coupon_code']) ? "Cupón: {$orderData['coupon_code']}" : null,
        ]);

        $notas = implode(' | ', $partes) ?: 'Solicitud vía WhatsApp';
        $pickupTime = !empty($orderData['pickup_time']) ? $orderData['pickup_time'] : null;
        $telefonoWhatsapp = $this->normalizarTelefono($from);
        $telefonoContacto = $this->normalizarTelefono($orderData['phone'] ?? $from);

        // Resolver dirección y barrio desde la respuesta del bot
        $direccion = trim((string) ($orderData['address'] ?? ''));
        $barrio    = trim((string) ($orderData['neighborhood'] ?? ''));

        // 🧠 Detectar ciudad: priorizar campos explícitos del bot.
        // El bot LLM puede mandar la ciudad en cualquiera de estos campos:
        //   - 'city'      (estándar)
        //   - 'location'  (lo que está mandando OpenAI con el schema actual)
        //   - 'ciudad'    (por si en español)
        // Si nada viene, la INFIERE desde el texto de la dirección.
        $ciudadOrden = trim((string) (
            $orderData['city']
            ?? $orderData['location']
            ?? $orderData['ciudad']
            ?? ''
        ));
        if ($ciudadOrden === '') {
            $ciudadOrden = $this->detectarCiudadDesdeDireccion($direccion)
                ?? $this->detectarCiudadDesdeDireccion($barrio)
                ?? 'Bello'; // fallback final
        }

        // 🐛 LOG DIAGNÓSTICO (temporal): ver qué viene del bot exactamente
        Log::info('🔎 [confirmar_pedido] datos para validación cobertura', [
            'address_raw'   => $orderData['address'] ?? null,
            'neighborhood'  => $orderData['neighborhood'] ?? null,
            'city_raw'      => $orderData['city'] ?? null,
            'location_raw'  => $orderData['location'] ?? null,
            'direccion_usada' => $direccion,
            'barrio_usado'    => $barrio,
            'ciudad_resuelta' => $ciudadOrden,
            'orderData_keys'  => array_keys($orderData),
        ]);

        // Resolver zona de cobertura — primero por barrio, si falla intenta geocode
        $validacion = $this->validarCoberturaDireccion(
            $direccion,
            $barrio,
            $ciudadOrden,
            $sede?->id,
            $from
        );

        $zonaCobertura = null;
        if (!empty($validacion['zona_id'])) {
            $zonaCobertura = ZonaCobertura::find($validacion['zona_id']);
        }

        // Si la validación sugirió una sede más cercana, la usamos.
        // Esto permite que una cadena con varias sedes despache desde la más próxima.
        if (!empty($validacion['sede_sugerida_id'])) {
            $sedeSugerida = Sede::find($validacion['sede_sugerida_id']);
            if ($sedeSugerida && $sedeSugerida->activa) {
                Log::info('📍 Despachando desde sede más cercana', [
                    'sede_original' => $sede?->nombre,
                    'sede_cercana'  => $sedeSugerida->nombre,
                    'distancia_km'  => $validacion['distancia_km'] ?? null,
                ]);
                $sede = $sedeSugerida;
            }
        }

        // ── VALIDACIÓN DE HORARIO DE LA SEDE ──────────────────────────────
        // Si la sede está cerrada, hay 2 caminos según configuración:
        //   A. sede.aceptar_pedidos_cerrada = false (default) → rechazar
        //   B. sede.aceptar_pedidos_cerrada = true → registrar como
        //      pedido programado para la próxima apertura
        $programadoPara = null; // se setea si entramos al camino B

        if ($sede && !$sede->estaAbierta()) {
            // Toggle global del bot por tenant (configurable desde /configuracion-bot
            // → Despachos / Domiciliarios → "Pedidos fuera de horario")
            $cfgBot = \App\Models\ConfiguracionBot::actual();
            $aceptarFueraHorario = (bool) ($cfgBot?->aceptar_pedidos_fuera_horario
                ?? $sede->aceptar_pedidos_cerrada);

            if ($aceptarFueraHorario) {
                // Camino B: programar pedido para la próxima apertura
                $programadoPara = $sede->proximaAperturaTimestamp();

                Log::info('📅 Pedido FUERA DE HORARIO — programando para próxima apertura', [
                    'sede'             => $sede->nombre,
                    'programado_para'  => $programadoPara?->toDateTimeString(),
                ]);
                // No retornamos, seguimos creando el pedido pero con programado_para
            } else {
                // Camino A: rechazar (comportamiento original)
                Cache::forget($confirmKey);
                DB::rollBack();

                $promptService = app(BotPromptService::class);
                $contexto = $promptService->construirContexto(
                    $name,
                    $sede->id,
                    $this->infoEmpresa(),
                    '',
                    ''
                );
                $contexto['proxima_apertura'] = $sede->proximaApertura() ?: 'cuando abramos';
                $contexto['mensaje_cerrado_sede'] = trim((string) $sede->mensaje_cerrado);

                $tieneMsgPersonalizado = $contexto['mensaje_cerrado_sede'] !== '';
                $template = $tieneMsgPersonalizado
                    ? "Ay {cliente_primer_nombre}, en este momento estamos cerrados 🙏\n\n"
                    . "🕐 {sede_estado_actual}\n"
                    . "👉 Te atendemos {proxima_apertura}.\n\n"
                    . "{mensaje_cerrado_sede}"
                    : "Ay {cliente_primer_nombre}, en este momento estamos cerrados 🙏\n\n"
                    . "🕐 {sede_estado_actual}\n"
                    . "👉 Te atendemos {proxima_apertura}.";

                Log::info('⛔ Pedido rechazado por sede cerrada', [
                    'sede'   => $sede->nombre,
                    'pedido' => $orderData,
                ]);

                return $promptService->renderizar($template, $contexto);
            }
        }

        // ── VALIDACIÓN ESTRICTA de cobertura ───────────────────────────────
        // Regla: si el cliente dio dirección/barrio para domicilio pero no
        // coincide con ninguna zona activa → se rechaza el pedido.
        // Excepción: si NO dio dirección ni barrio (es pedido para recoger
        // en sede), se permite crearlo sin zona.
        $indicoDomicilio = (!empty($direccion) || !empty($barrio));

        // 🌟 La cobertura es válida si:
        //   (a) tenemos una ZonaCobertura legacy (sistema viejo), O
        //   (b) el smart resolver de sedes dijo cubierta=true (sistema nuevo)
        // Antes solo se chequeaba (a), por eso pedidos a Bogotá se rechazaban
        // aunque la sede tuviera Colombia entera como zona.
        $coberturaValida = !empty($zonaCobertura) || !empty($validacion['cubierta']);

        if ($indicoDomicilio && !$coberturaValida) {
            Cache::forget($confirmKey);   // liberar el lock de deduplicación
            DB::rollBack();

            // ── ANTI-FLOOD ───────────────────────────────────────────────
            // Si ya rechazamos esta misma dirección hace poco, no repetimos
            // el mismo texto: variamos el mensaje y NO volvemos a registrar
            // el mismo assistant en el historial. Esto rompe el bucle donde
            // la IA reintenta confirmar_pedido con la misma dirección.
            $tenantIdFlood = app(\App\Services\TenantManager::class)->id() ?? 'none';
            $direccionKey  = mb_strtolower(trim($direccion . '|' . $barrio));
            $rechazoKey    = "wa_rechazo_cobertura_t{$tenantIdFlood}_" . md5($telNorm . '|' . $direccionKey);
            $yaRechazada   = Cache::has($rechazoKey);

            if ($yaRechazada) {
                $mensaje = "Sigo sin cobertura para esa dirección 😅. Pásame *otra dirección o barrio cercano*, "
                    . "o dime si prefieres *recoger en la sede*. Sin una dirección válida no puedo cerrar el pedido.";
            } else {
                $mensaje = "Uy, esa dirección me queda fuera de la zona de cobertura 😔\n\n"
                    . "Pero el pedido te lo puedo dejar listo para que lo recojas en la sede, o "
                    . "si tienes otra dirección cercana me la pasas y vuelvo a revisar 🙌";
            }

            Cache::put($rechazoKey, true, now()->addMinutes(5));

            // Index para que el siguiente turno de la IA reciba la nota
            // de rechazo en el system message (rompe el bucle de reintento).
            $rechazoIndexKey = "wa_rechazo_cobertura_idx_t{$tenantIdFlood}_{$telNorm}";
            Cache::put($rechazoIndexKey, [
                'direccion' => trim($direccion . ($barrio ? " ({$barrio})" : '')),
                'ts'        => now()->timestamp,
            ], now()->addMinutes(5));

            Log::warning('🚫 Pedido rechazado — fuera de cobertura', [
                'from'           => $from,
                'direccion'      => $direccion,
                'barrio'         => $barrio,
                'ya_rechazada'   => $yaRechazada,
            ]);

            // ── INSTRUCCIÓN AL MODELO PARA ROMPER EL BUCLE ───────────────
            // Inyectamos un mensaje de sistema con regla dura: NO volver a
            // llamar confirmar_pedido hasta que el cliente envíe una
            // dirección/barrio distinto al que acabamos de rechazar.
            $direccionRechazada = trim($direccion . ($barrio ? " ({$barrio})" : ''));
            $conversationHistory[] = [
                'role'    => 'system',
                'content' => "🚫 DIRECCIÓN RECHAZADA POR COBERTURA: \"{$direccionRechazada}\".\n"
                    . "REGLA DURA: NO vuelvas a llamar la función `confirmar_pedido` hasta que el cliente "
                    . "envíe una dirección o barrio DIFERENTE al rechazado. Si insiste, repite la opción "
                    . "*recoger en sede* o pide nueva dirección. NO repitas literalmente el mismo mensaje "
                    . "de rechazo dos veces seguidas.",
            ];
            $conversationHistory[] = ['role' => 'assistant', 'content' => $mensaje];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            // ── PERSISTIR EN BD para que aparezca en Chat en vivo ────────
            if ($conversacion && $convService) {
                try {
                    $convService->agregarMensaje(
                        $conversacion,
                        \App\Models\MensajeWhatsapp::ROL_ASSISTANT,
                        $mensaje,
                        [
                            'tipo' => 'tool_call',
                            'meta' => [
                                'tool'      => 'confirmar_pedido',
                                'resultado' => 'rechazado_cobertura',
                                'direccion' => $direccion,
                                'barrio'    => $barrio,
                            ],
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('No se pudo persistir mensaje de rechazo: ' . $e->getMessage());
                }
            }

            return $mensaje;
        }

        // ── Validar y resolver productos contra el catálogo ──
        /** @var BotCatalogoService $catalogo */
        $catalogo = app(BotCatalogoService::class);

        $productosValidados = [];
        $productosNoEncontrados = [];
        $subtotalProductos = 0;

        foreach (($orderData['products'] ?? []) as $product) {
            $codeRaw = trim((string) ($product['code'] ?? ''));
            $nameRaw = trim((string) ($product['name'] ?? ''));

            // 🛡️ ESTRATEGIA ANTI-ALUCINACIÓN:
            // 1) Si el LLM proveyó `code`, intentamos resolverlo. Si NO existe,
            //    NO hacemos fallback al name (porque "Pierna de cerdo" → SUPERCOCO).
            //    En su lugar, intentamos resolver SOLO por name con guard de token.
            // 2) Validamos que el producto resuelto comparta al menos un token
            //    significativo (>=4 chars) con el name solicitado.
            $producto = null;
            $resueltoVia = null;

            if ($codeRaw !== '') {
                // 🛡️ Validar PRIMERO que el código existe en BD del tenant.
                // Si el LLM inventó un code que no está en `productos.codigo`,
                // ni siquiera intentamos el resolver — vamos directo al name.
                $existeCodigo = \App\Models\Producto::where('codigo', $codeRaw)->exists();
                if (!$existeCodigo) {
                    Log::warning('🚫 Código inventado por LLM (no existe en BD)', [
                        'code_inventado' => $codeRaw,
                        'name'           => $nameRaw,
                    ]);
                } else {
                    $producto = $catalogo->resolverProducto($codeRaw, $sede?->id);
                    if ($producto) {
                        $codigoResuelto = (string) ($producto->codigo ?? '');
                        if (strcasecmp(trim($codigoResuelto), $codeRaw) === 0) {
                            $resueltoVia = 'codigo_exacto';
                        } else {
                            $producto = null;
                        }
                    }
                }
            }
            if (!$producto && $nameRaw !== '') {
                $producto = $catalogo->resolverProducto($nameRaw, $sede?->id);
                if ($producto) $resueltoVia = 'nombre';
            }

            // 🛡️ GUARD POST-RESOLVE: el nombre resuelto debe compartir
            // al menos un token significativo (>=4 chars) con el name solicitado.
            if ($producto && $nameRaw !== '') {
                $tokensSolicitados = collect(preg_split('/\s+/', mb_strtolower(\Illuminate\Support\Str::ascii($nameRaw))))
                    ->filter(fn ($t) => mb_strlen($t) >= 4)
                    ->values();
                if ($tokensSolicitados->isNotEmpty()) {
                    $nombreResuelto = mb_strtolower(\Illuminate\Support\Str::ascii((string) ($producto->nombre ?? '')));
                    $compartido = $tokensSolicitados->first(fn ($t) => str_contains($nombreResuelto, $t));
                    if (!$compartido) {
                        Log::warning('🛡️ Resolver matcheó producto sin tokens compartidos — descartado', [
                            'solicitado' => $nameRaw,
                            'resuelto'   => $producto->nombre ?? null,
                            'codigo'     => $producto->codigo ?? null,
                            'via'        => $resueltoVia,
                        ]);
                        $producto = null;
                    }
                }
            }

            $cantidad = (float) ($product['quantity'] ?? 1);

            if ($producto) {
                $precio = method_exists($producto, 'precioParaSede')
                    ? $producto->precioParaSede($sede?->id)
                    : (float) ($producto->precio_base ?? $producto->precio ?? 0);

                $sub = $precio * $cantidad;
                $subtotalProductos += $sub;

                $productosValidados[] = [
                    'producto_id'     => $producto->id ?? null,
                    'codigo_producto' => $producto->codigo ?? null,
                    'producto'        => $producto->nombre ?? '',
                    'cantidad'        => $cantidad,
                    'unidad'          => $product['unit'] ?? ($producto->unidad ?? 'unidad'),
                    'precio_unitario' => $precio,
                    'subtotal'        => $sub,
                ];

                Log::info('✅ Producto resuelto', [
                    'solicitado_code' => $codeRaw,
                    'solicitado_name' => $nameRaw,
                    'resuelto_id'     => $producto->id ?? null,
                    'resuelto_codigo' => $producto->codigo ?? null,
                    'resuelto_nombre' => $producto->nombre ?? null,
                    'precio'          => $precio,
                    'cantidad'        => $cantidad,
                    'via'             => $resueltoVia,
                ]);
            } else {
                Log::warning('⚠️ Producto del bot no está en catálogo — ABORTANDO pedido', [
                    'code' => $codeRaw,
                    'name' => $nameRaw,
                    'producto_data' => $product,
                ]);
                $productosNoEncontrados[] = $nameRaw !== '' ? $nameRaw : $codeRaw;
            }
        }

        // 🚫 Si el bot intentó pedir productos que NO existen en el catálogo,
        // NO registramos el pedido. Devolvemos un mensaje pidiendo al cliente
        // que ajuste con productos reales.
        if (!empty($productosNoEncontrados)) {
            $lista = implode('", "', array_unique($productosNoEncontrados));
            Log::warning('🚫 Pedido rechazado por productos inexistentes', [
                'from'                  => $from,
                'no_encontrados'        => $productosNoEncontrados,
            ]);
            return "Ups, {$name} 🙏 no manejamos \"{$lista}\" en el catálogo. "
                 . "¿Me confirmas qué productos *sí* llevas de los que te he mostrado? "
                 . "Así te registro el pedido bien 💪";
        }

        // Costo de envío de la zona (0 si no se resolvió)
        $costoEnvio = $zonaCobertura?->costo_envio ?? 0;

        // ── CLIENTE: lo resolvemos acá arriba para poder consultar beneficios ──
        // (antes se hacía más abajo, pero necesitamos el $cliente antes)
        $cliente = Cliente::encontrarOCrearPorTelefono(
            $telefonoWhatsapp,
            $orderData['customer_name'] ?? $name
        );

        // 🎁 ¿Tiene beneficio de envío gratis vigente? (ej. por cumpleaños)
        $beneficioAplicado = null;
        if ($zonaCobertura && (float) $costoEnvio > 0) {
            $beneficioAplicado = $cliente->beneficioVigente(
                \App\Models\BeneficioCliente::TIPO_ENVIO_GRATIS
            );
            if ($beneficioAplicado) {
                Log::info('🎁 Beneficio envío gratis aplicado', [
                    'cliente_id'   => $cliente->id,
                    'beneficio_id' => $beneficioAplicado->id,
                    'ahorro'       => $costoEnvio,
                ]);
                $costoEnvio = 0;
            }
        }

        $totalCalculado = $subtotalProductos + $costoEnvio;

        // ── VALIDACIÓN: pedido mínimo por zona ──────────────────────────────
        // Solo aplica si hay zona (es domicilio) y tiene mínimo configurado.
        if ($zonaCobertura && (float) $zonaCobertura->pedido_minimo > 0) {
            $minimo = (float) $zonaCobertura->pedido_minimo;
            if ($subtotalProductos < $minimo) {
                Cache::forget($confirmKey);
                DB::rollBack();

                $faltaStr  = '$' . number_format($minimo - $subtotalProductos, 0, ',', '.');
                $minimoStr = '$' . number_format($minimo, 0, ',', '.');

                $mensaje = "Uy, para domicilio en *{$zonaCobertura->nombre}* el pedido mínimo es de {$minimoStr} 😔\n\n"
                    . "Te faltan {$faltaStr} para completar. ¿Agregamos algo más?";

                Log::warning('🚫 Pedido rechazado — no alcanza mínimo de zona', [
                    'from'     => $from,
                    'zona'     => $zonaCobertura->nombre,
                    'minimo'   => $minimo,
                    'subtotal' => $subtotalProductos,
                ]);

                $conversationHistory[] = ['role' => 'assistant', 'content' => $mensaje];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

                return $mensaje;
            }
        }

        // ── CLIENTE: actualizar datos (ya lo resolvimos arriba) ────────────
        // 🛡️ Sanitizar customer_name: el LLM a veces mete emails, teléfonos o
        // strings raros como customer_name. Solo aceptamos si parece un nombre.
        $customerNameRaw = trim((string) ($orderData['customer_name'] ?? ''));
        $nombreSeguro = $cliente->nombre; // default: mantener el actual
        if ($customerNameRaw !== '') {
            $esEmail   = filter_var($customerNameRaw, FILTER_VALIDATE_EMAIL) !== false || str_contains($customerNameRaw, '@');
            $esTel     = preg_match('/^\+?\d[\d\s\-]{6,}$/', $customerNameRaw) === 1;
            $esCedula  = preg_match('/^\d{6,12}$/', $customerNameRaw) === 1;
            $tieneLetras = preg_match('/[a-záéíóúñ]/iu', $customerNameRaw) === 1;
            $largoOk = mb_strlen($customerNameRaw) >= 2 && mb_strlen($customerNameRaw) <= 80;

            if (!$esEmail && !$esTel && !$esCedula && $tieneLetras && $largoOk) {
                $nombreSeguro = $customerNameRaw;
            } else {
                Log::warning('🛡️ customer_name del orderData rechazado (no parece un nombre)', [
                    'customer_name' => $customerNameRaw,
                    'cliente_id'    => $cliente->id,
                ]);
            }
        }

        $datosClienteActualizar = [
            'nombre'              => $nombreSeguro,
            'direccion_principal' => $direccion ?: $cliente->direccion_principal,
            'barrio'              => $barrio ?: $cliente->barrio,
            'zona_cobertura_id'   => $zonaCobertura?->id ?? $cliente->zona_cobertura_id,
        ];

        // 🪪 Guardar cédula si vino en el orderData (desde 'cedula' o 'document_id')
        $cedulaNueva = trim((string) ($orderData['cedula'] ?? $orderData['document_id'] ?? ''));
        if ($cedulaNueva !== '' && empty($cliente->cedula)) {
            $datosClienteActualizar['cedula'] = $cedulaNueva;
        }

        // 🔄 Si el bot NO pasó cédula pero el cliente local SÍ la tiene,
        // usar la guardada para que llegue al ERP (no perder la asociación).
        if ($cedulaNueva === '' && !empty($cliente->cedula)) {
            $orderData['cedula'] = (string) $cliente->cedula;
        }

        // 📧 Guardar correo si vino en el orderData
        $correoNuevo = trim((string) ($orderData['email'] ?? $orderData['correo'] ?? ''));
        if ($correoNuevo !== '' && filter_var($correoNuevo, FILTER_VALIDATE_EMAIL) && empty($cliente->correo)) {
            $datosClienteActualizar['correo'] = $correoNuevo;
        }

        $cliente->update($datosClienteActualizar);

        // Coordenadas del cliente (si la validación las encontró vía geocoding)
        $pedidoLat = $validacion['coordenadas']['lat'] ?? null;
        $pedidoLng = $validacion['coordenadas']['lng'] ?? null;

        $pedido = Pedido::create([
            'sede_id'               => $sede?->id,
            'cliente_id'            => $cliente->id,
            'empresa_id'            => $empresaId,
            'fecha_pedido'          => now(),
            'hora_entrega'          => $pickupTime,
            'estado'                => 'nuevo',
            'fecha_estado'          => now(),
            'programado_para'       => $programadoPara, // null si está abierto, timestamp si está cerrado y acepta programados
            'observacion_estado'    => $programadoPara
                ? "Pedido programado para preparación: " . $programadoPara->format('d/m/Y H:i')
                : 'Pedido creado automáticamente desde WhatsApp',
            'total'                 => $totalCalculado,
            'notas'                 => $notas,
            'cliente_nombre'        => $orderData['customer_name'] ?? $name,
            'direccion'             => $direccion ?: null,
            'barrio'                => $barrio ?: null,
            'lat'                   => $pedidoLat,
            'lng'                   => $pedidoLng,
            'zona_cobertura_id'     => $zonaCobertura?->id,
            'telefono_whatsapp'     => $telefonoWhatsapp,
            'telefono_contacto'     => $telefonoContacto,
            'telefono'              => $telefonoWhatsapp,
            'canal'                 => 'whatsapp',
            'connection_id'         => $connectionId,
            'whatsapp_id'           => $whatsappId,
            'conversacion_completa' => json_encode($conversationHistory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'resumen_conversacion'  => $orderData['notes'] ?? '',
        ]);

        foreach ($productosValidados as $linea) {
            DetallePedido::create(array_merge(['pedido_id' => $pedido->id], $linea));
        }

        Log::info('📦 PEDIDO REGISTRADO con catálogo', [
            'pedido_id'       => $pedido->id,
            'subtotal'        => $subtotalProductos,
            'envio'           => $costoEnvio,
            'total'           => $totalCalculado,
            'zona'            => $zonaCobertura?->nombre,
            'beneficio'       => $beneficioAplicado?->id,
            'no_encontrados'  => $productosNoEncontrados,
        ]);

        // Marcar beneficio como usado si fue aplicado
        if ($beneficioAplicado) {
            $beneficioAplicado->update([
                'usado_at'  => now(),
                'pedido_id' => $pedido->id,
            ]);
        }

        DB::commit();

        // 👤 ASEGURAR CLIENTE EN ERP — antes de exportar el pedido
        // Si la integración tiene cliente_lookup activo, verifica que la
        // cédula esté en TblTerceros. Si no, la crea automáticamente con
        // los datos que el cliente dio. Esto evita el FK_TblDocumentos_TblTerceros.
        try {
            $integraciones = \App\Models\Integracion::where('tenant_id', $pedido->tenant_id)
                ->where('activo', true)
                ->where('exporta_pedidos', true)
                ->get();

            foreach ($integraciones as $integracion) {
                if (!($integracion->config['cliente_lookup']['activo'] ?? false)) continue;

                app(\App\Services\ClienteErpService::class)->asegurarCliente($integracion, [
                    'cedula'    => $pedido->cliente?->cedula ?? '',
                    'nombre'    => $pedido->cliente_nombre ?? $pedido->cliente?->nombre ?? '',
                    'telefono'  => $pedido->telefono_whatsapp ?? $pedido->telefono ?? '',
                    'email'     => $pedido->cliente?->correo ?? '',
                    'direccion' => $pedido->direccion ?? '',
                    'ciudad'    => $pedido->cliente?->ciudad ?? '',
                    'barrio'    => $pedido->barrio ?? '',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Asegurar cliente en ERP falló (no crítico): ' . $e->getMessage());
        }

        // 🚀 EXPORTAR pedido al ERP del cliente (si tiene integración configurada)
        // Ejecuta DESPUÉS del commit para no quedar atrapado en la transacción.
        // Si falla, NO afecta el registro del pedido — solo se loguea el error
        // en integracion_export_logs para que el operador lo vea y reintente.
        try {
            $exportService = app(\App\Services\IntegracionExportService::class);
            $resExport = $exportService->exportarPedido($pedido);
            if ($resExport['exportadas'] > 0) {
                Log::info('🔄 Pedido exportado a integraciones', [
                    'pedido_id'   => $pedido->id,
                    'exportadas'  => $resExport['exportadas'],
                    'resultados'  => $resExport['resultados'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Export pedido al ERP falló (no crítico): ' . $e->getMessage());
        }

        // Recalcular métricas del cliente (total_pedidos, total_gastado, etc.)
        try {
            $cliente->refresh()->recalcularMetricas();
        } catch (\Throwable $e) {
            Log::warning('No se pudo recalcular métricas del cliente: ' . $e->getMessage());
        }

        // Vincular el pedido a la conversación activa (si existe)
        try {
            $convActiva = \App\Models\ConversacionWhatsapp::where('telefono_normalizado', $telefonoWhatsapp)
                ->where('estado', \App\Models\ConversacionWhatsapp::ESTADO_ACTIVA)
                ->orderByDesc('id')
                ->first();
            if ($convActiva) {
                app(\App\Services\ConversacionService::class)->vincularPedido($convActiva, $pedido->id);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo vincular pedido a conversación: ' . $e->getMessage());
        }

        $pedido->load(['sede', 'detalles', 'historialEstados']);

        broadcast(new PedidoConfirmado($pedido));
        broadcast(new PedidoActualizado($pedido, 'nuevo'));

        Cache::forget($cacheKey);

        Log::info('✅ PEDIDO GUARDADO', [
            'pedido_id' => $pedido->id,
            'empresa_id' => $empresaId,
            'connection_id' => $connectionId,
            'whatsapp_id' => $whatsappId,
            'from' => $from,
        ]);

        // 🎯 Marcar el estado como CONFIRMADO en BD para que el siguiente
        // mensaje del cliente arranque limpio (no intentará re-confirmar).
        try {
            app(\App\Services\EstadoPedidoService::class)
                ->marcarConfirmado($conversacion, $pedido->id);
        } catch (\Throwable $e) {
            Log::warning('No se pudo marcar estado pedido confirmado: ' . $e->getMessage());
        }

        return $this->construirMensajeConfirmacionPedido($pedido, $orderData, $name, $beneficioAplicado);
    } catch (\Throwable $e) {
        DB::rollBack();
        $tenantId = app(\App\Services\TenantManager::class)->id() ?? 'none';
        Cache::forget("pedido_confirmado_t{$tenantId}_" . $this->normalizarTelefono($from));

        Log::error('❌ ERROR CRÍTICO AL GUARDAR PEDIDO', [
            'error' => $e->getMessage(),
            'order_data' => $orderData,
            'connectionId' => $connectionId,
        ]);

        $this->notificarFallaWhatsapp(
            'ERROR GUARDANDO PEDIDO',
            'Ocurrió un error guardando un pedido generado desde WhatsApp.',
            [
                'from' => $from,
                'name' => $name,
                'error' => $e->getMessage(),
                'orderData' => $orderData,
                'connectionId' => $connectionId,
            ]
        );

        return '⚠️ Tu pedido no se pudo registrar en este momento. Ya lo estamos revisando, te contactamos en breve.';
    }
}
    private function construirMensajeConfirmacionPedido(
        Pedido $pedido,
        array $orderData,
        string $name,
        ?\App\Models\BeneficioCliente $beneficioAplicado = null
    ): string {
        $cfgBot = \App\Models\ConfiguracionBot::actual();

        // Construir lista de productos como string multilínea
        $productosTxt = [];
        foreach (($orderData['products'] ?? []) as $prod) {
            $cant = $this->formatearCantidadPedido((float) ($prod['quantity'] ?? 1));
            $unidad = $prod['unit'] ?? 'unidad';
            $productosTxt[] = "• {$prod['name']} — {$cant} {$unidad}";
        }
        $productosStr = implode("\n", $productosTxt);

        // Beneficio aplicado (línea opcional)
        $beneficioTxt = '';
        if ($beneficioAplicado) {
            $beneficioTxt = "🎁 *Envío GRATIS aplicado* (beneficio por {$beneficioAplicado->origen}) — no pagaste costo de envío.\n";
        }

        // Bloque de pago (opcional, solo si Wompi está activo)
        $bloquePago = '';
        if ($cfgBot->enviar_link_pago ?? true) {
            try {
                $linkPago = $pedido->urlPagoWompi();
                if ($linkPago) {
                    $bloquePago = "\n💳 *Paga ahora con tarjeta, Nequi o PSE:*\n{$linkPago}\n(También puedes pagar contra entrega)\n";
                }
            } catch (\Throwable $e) { /* ignorar */ }
        }

        // Plantilla configurable o default
        $plantilla = trim((string) ($cfgBot->notif_pedido_confirmado_mensaje ?? ''))
            ?: \App\Models\ConfiguracionBot::NOTIF_DEFAULTS['pedido_confirmado'];

        // 📅 Si el pedido es PROGRAMADO (sede cerrada, lo despachamos mañana),
        // anteponer un aviso claro al cliente.
        $avisoProgramado = '';
        if ($pedido->programado_para) {
            $cuando = $pedido->programado_para->locale('es')->isoFormat('dddd D [de] MMMM [a las] h:mm a');
            $avisoProgramado = "📅 *Pedido programado* — estamos cerrados ahora, pero ya te lo dejamos en cola para preparar el {$cuando}.\n\n";
        }

        // Renderizar con variables — usa el helper del modelo + extras específicos
        $mensaje = $pedido->renderizarPlantilla($plantilla, [
            'productos'         => $productosStr,
            'direccion'         => $orderData['address'] ?? $pedido->direccion ?? '',
            'barrio'            => $orderData['neighborhood'] ?? $pedido->barrio ?? '',
            'telefono_contacto' => $pedido->telefono_contacto ?? '',
            'hora_entrega'      => $pedido->hora_entrega ?? '',
            'beneficio'         => $beneficioTxt,
            'bloque_pago'       => $bloquePago,
            'link_seguimiento'  => $pedido->url_seguimiento,
        ]);

        return $avisoProgramado . $mensaje;
    }

    /*
    |==========================================================================
    | ANS
    |==========================================================================
    */

    private function obtenerAnsMinutos(string $accion): ?int
    {
        return AnsPedido::where('accion', $accion)
            ->where('activo', true)
            ->value('tiempo_minutos');
    }

    private function construirResumenAns(): string
    {
        // Lee TODAS las reglas activas de ans_pedidos para el tenant actual
        // (gestionadas desde /ans). Cada regla incluye:
        //   - acción (crear, adicionar, cancelar, cambiar_direccion, etc.)
        //   - tiempo_minutos (ventana en la que se permite la acción)
        //   - descripcion (texto rico que el bot puede usar literal con el cliente)
        try {
            $reglas = \App\Models\AnsPedido::where('activo', true)
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            \Log::warning('No se pudieron leer ANS para el bot: ' . $e->getMessage());
            return '(Sin reglas ANS configuradas — pregunta al equipo si dudas)';
        }

        if ($reglas->isEmpty()) {
            return "REGLAS ANS:\n"
                . "(No hay reglas configuradas. Si el cliente pide cancelar/modificar/agregar a un pedido existente, "
                . "explícale que un asesor lo revisará y deriva al departamento correspondiente.)";
        }

        $lineas = ["📋 REGLAS ANS — TIEMPOS Y CONDICIONES PARA ACCIONES SOBRE PEDIDOS:"];
        $lineas[] = "";
        $lineas[] = "Estas son las reglas EXACTAS que debes respetar y comunicar al cliente:";
        $lineas[] = "";

        foreach ($reglas as $r) {
            $accionTitulo = ucfirst(str_replace('_', ' ', $r->accion));
            $minutos = $r->tiempo_minutos ?? null;
            $alerta  = $r->tiempo_alerta ?? null;
            $descripcion = trim((string) $r->descripcion);

            $lineas[] = "▸ **{$accionTitulo}** — ventana: " . ($minutos !== null ? "{$minutos} minutos" : 'sin definir');
            if ($alerta !== null && $alerta > 0) {
                $lineas[] = "   (avisar al cliente cuando queden ≤ {$alerta} min)";
            }
            if ($descripcion !== '') {
                $lineas[] = "   Detalle: {$descripcion}";
            }
            $lineas[] = "";
        }

        $lineas[] = "INSTRUCCIONES PARA TI (el bot):";
        $lineas[] = "1) Si el cliente pide una acción que está en la lista, verifica el tiempo transcurrido desde fecha_pedido del pedido en cuestión.";
        $lineas[] = "2) Si está DENTRO de la ventana → procede con la herramienta correspondiente.";
        $lineas[] = "3) Si está FUERA de la ventana → explica con cariño que el tiempo expiró y por qué (lee la descripción de la regla).";
        $lineas[] = "4) Si el cliente insiste → deriva al departamento correspondiente (no inventes excepciones).";

        return implode("\n", $lineas);
    }

    /*
    |==========================================================================
    | CONSULTAS DB
    |==========================================================================
    */

    private function pedidosDelCliente(string $from, int $limite = 10): \Illuminate\Support\Collection
    {
        $tel      = $this->normalizarTelefono($from);
        $telLocal = $this->obtenerTelefonoLocal($tel);

        return Pedido::with(['sede', 'detalles'])
            ->where(function ($q) use ($telLocal) {
                $q->where('telefono_whatsapp', 'LIKE', "%{$telLocal}%")
                    ->orWhere('telefono_contacto', 'LIKE', "%{$telLocal}%")
                    ->orWhere('telefono', 'LIKE', "%{$telLocal}%");
            })
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->take($limite)
            ->get();
    }

    /**
     * Tool: consultar_mis_pedidos. Devuelve los pedidos del cliente que escribe,
     * formateados como payload para el LLM.
     */
    private function resultadoMisPedidos(string $from, int $limite = 5): array
    {
        $limite  = max(1, min(10, $limite));
        $pedidos = $this->pedidosDelCliente($from, $limite);

        if ($pedidos->isEmpty()) {
            return [
                'pedidos'              => [],
                'total_pedidos'        => 0,
                'mensaje_si_vacio'     => 'No encontré pedidos asociados a este número.',
                'instruccion_para_bot' => 'No hay pedidos. Dile al cliente que no encontraste pedidos a su nombre y ofrece ayudarlo a crear uno nuevo.',
            ];
        }

        $base = rtrim((string) config('app.url', ''), '/');

        $items = $pedidos->map(function ($p) use ($base) {
            $detalles = ($p->detalles ?? collect())->map(fn ($d) => [
                'producto' => $d->producto ?? $d->nombre_producto ?? '?',
                'cantidad' => (float) ($d->cantidad ?? 0),
                'unidad'   => $d->unidad ?? null,
            ])->values()->all();

            return [
                'id'              => $p->id,
                'estado'          => $p->estado,
                'estado_pago'     => $p->estado_pago ?? null,
                'fecha_pedido'    => optional($p->fecha_pedido)->format('Y-m-d H:i'),
                'programado_para' => optional($p->programado_para)->format('Y-m-d H:i'),
                'total'           => (float) ($p->total ?? 0),
                'sede'            => $p->sede->nombre ?? null,
                'productos'       => $detalles,
                'seguimiento_url' => $p->seguimiento_token
                    ? ($base ? "{$base}/seguimiento-pedido/{$p->seguimiento_token}" : null)
                    : null,
            ];
        })->values()->all();

        return [
            'pedidos'              => $items,
            'total_pedidos'        => count($items),
            'instruccion_para_bot' =>
                'Lista los pedidos al cliente de forma clara: número de pedido, estado, fecha, total y link de seguimiento si existe. '
                . 'Si hay pedidos programados (programado_para) menciónalo. '
                . 'NUNCA inventes datos: solo usa los del payload. '
                . 'Si el cliente pregunta por "el último pedido", responde con el primero del array (vienen ordenados del más reciente al más antiguo).',
        ];
    }

    private function buscarPedidosClienteSQL(string $from, string $message): string
    {
        $msg = mb_strtolower($message);

        $keywords = [
            'pedido',
            'domicilio',
            'orden',
            'estado',
            'seguimiento',
            'compra',
            'comprar',
            'dirección',
            'direccion',
            'barrio',
            'pago',
            'contra entrega',
            'cancelar',
            'anular',
            'adicionar',
            'agregar',
            'modificar',
            'editar',
            'cambiar',
        ];

        $esConsulta = false;
        foreach ($keywords as $k) {
            if (str_contains($msg, $k)) {
                $esConsulta = true;
                break;
            }
        }

        if (!$esConsulta) {
            return '';
        }

        $pedidos = $this->pedidosDelCliente($from, 3);

        if ($pedidos->isEmpty()) {
            return "ℹ️ No se encontraron pedidos recientes para este número.\n";
        }

        $texto = "📦 HISTORIAL DEL CLIENTE:\n\n";
        foreach ($pedidos as $p) {
            $texto .= "Pedido #{$p->id}\n"
                . "Estado: {$p->estado}\n"
                . "Fecha: {$p->fecha_pedido->format('d/m/Y H:i')}\n"
                . "Barrio/Sede: " . ($p->sede->nombre ?? 'No especificada') . "\n\n";
        }

        return $texto;
    }

    /*
    |==========================================================================
    | PROMPT
    |==========================================================================
    */

    private function infoEmpresa(): string
    {
        $config = \App\Models\ConfiguracionBot::actual();
        $info = trim((string) $config->info_empresa);

        if ($info !== '') {
            return $info;
        }

        // Fallback si no se ha configurado
        return "Alimentos La Hacienda\n"
            . "- Más de 25 años de experiencia.\n"
            . "- Ubicada en Bello, Antioquia.\n"
            . "- Calidad, frescura y servicio al cliente.\n"
            . "- Opera con domicilios, sedes físicas y atención directa.\n"
            . "- Sistema de pedidos integrado.";
    }

    private function getSystemPrompt(
        string $pedidosInfo = '',
        string $infoEmpresa = '',
        string $name = 'Cliente',
        string $ansInfo = '',
        ?int $sedeId = null
    ): string {
        /** @var BotPromptService $promptService */
        $promptService = app(BotPromptService::class);

        // Construir contexto con todas las variables resueltas
        $contexto = $promptService->construirContexto(
            $name,
            $sedeId,
            $infoEmpresa,
            $pedidosInfo,
            $ansInfo
        );

        $config = \App\Models\ConfiguracionBot::actual();

        // Si el usuario activó "prompt personalizado" y guardó algo, usarlo.
        // Si no, usar la plantilla GENÉRICA dinámica (con variables {tenant_nombre},
        // {ciudad}, etc.) en lugar de la legacy con "La Hacienda" hardcoded.
        // Así cada tenant funciona out-of-the-box sin que tengan que personalizar.
        $base = ($config->usar_prompt_personalizado && !empty(trim($config->system_prompt ?? '')))
            ? $config->system_prompt
            : BotPromptService::plantillaGenerica();

        $prompt = $promptService->renderizar($base, $contexto);

        // Si hay INSTRUCCIONES EXTRA definidas por el usuario, las APPENDEAMOS al final.
        // No reemplazan nada — se suman. Útiles para reglas específicas del negocio
        // sin tocar la plantilla base.
        $extra = trim((string) ($config->instrucciones_extra ?? ''));
        if ($extra !== '') {
            $extraRendered = $promptService->renderizar($extra, $contexto);
            $prompt .= "\n\n═══════════════════════════════════════════════════════════════════════════════\n"
                     . "# 🔧 REGLAS ADICIONALES DE ESTE NEGOCIO\n\n"
                     . $extraRendered . "\n";
        }

        // 🎯 REGLA: ORDEN DEL FLUJO DEL PEDIDO (configurable desde panel)
        try {
            $cfgOrden = \App\Models\ConfiguracionBot::actual();
            $flujo = $cfgOrden?->flujo_pedido_orden ?? [];
            $activos = collect($flujo)->filter(fn ($f) => ($f['activo'] ?? false))->values();

            if ($activos->count() > 0) {
                $labels = [
                    'cedula'    => '🪪 Cédula / NIT',
                    'nombre'    => '👤 Nombre completo',
                    'producto'  => '🛒 Producto y cantidad',
                    'direccion' => '📍 Dirección',
                    'barrio'    => '🏘️ Barrio',
                    'ciudad'    => '🏙️ Ciudad',
                    'telefono'  => '📞 Teléfono',
                    'email'     => '📧 Correo',
                    'metodo_pago' => '💳 Método de pago',
                    'notas'     => '📝 Notas',
                ];

                $listaOrdenada = $activos->map(fn ($f, $i) => ($i + 1) . '. ' . ($labels[$f['campo']] ?? $f['campo']))
                    ->implode(', ');

                $prompt .= "\n\n📝 Para tomar un pedido pide en este orden, uno por uno: {$listaOrdenada}.\n";
            }
        } catch (\Throwable $e) {
            // ignorar
        }

        // 📅 REGLA: PEDIDOS FUERA DE HORARIO (PROGRAMADOS) - solo si activo
        $cfgBotProgramados = \App\Models\ConfiguracionBot::actual();
        $tenantAceptaFueraHorario = (bool) ($cfgBotProgramados?->aceptar_pedidos_fuera_horario ?? false);
        $sedesConProgramados = $tenantAceptaFueraHorario || \App\Models\Sede::where('tenant_id', app(\App\Services\TenantManager::class)->id() ?? 0)
            ->where('aceptar_pedidos_cerrada', true)
            ->where('activa', true)
            ->exists();

        if ($sedesConProgramados) {
            $prompt .= "\n\n📅 Si estamos cerrados, NO digas 'no puedo registrar'. Ofrece dejar "
                     . "el pedido programado para la próxima apertura.\n";
        }

        // 👤 Si lookup ERP activo → cédula obligatoria (regla corta)
        try {
            $tenantIdLookup = app(\App\Services\TenantManager::class)->id();
            if ($tenantIdLookup) {
                $integLookupActivo = \App\Models\Integracion::where('tenant_id', $tenantIdLookup)
                    ->where('activo', true)
                    ->where('exporta_pedidos', true)
                    ->get()
                    ->contains(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false);

                if ($integLookupActivo) {
                    // Cargar el cliente del teléfono actual (si lo conocemos)
                    $clienteActual = null;
                    try {
                        $telActual = isset($telefonoFrom) ? $telefonoFrom : ($from ?? null);
                        if ($telActual) {
                            $telNorm = preg_replace('/\D+/', '', (string) $telActual);
                            $clienteActual = \App\Models\Cliente::where('tenant_id', $tenantIdLookup)
                                ->where(function ($q) use ($telNorm, $telActual) {
                                    $q->where('telefono_normalizado', $telNorm)
                                      ->orWhere('telefono', $telActual);
                                })
                                ->first();
                        }
                    } catch (\Throwable $e) { /* ignorar */ }

                    if ($clienteActual && !empty($clienteActual->cedula)) {
                        // Cliente YA tiene cédula registrada → no pedirla
                        $prompt .= "\n\n🪪 CLIENTE YA REGISTRADO:\n"
                                 . "- Cédula del cliente: {$clienteActual->cedula}\n"
                                 . "- Nombre: " . ($clienteActual->nombre ?? '—') . "\n"
                                 . "- NO le pidas la cédula otra vez — ya la tienes.\n"
                                 . "- Pásala automáticamente en orderData['cedula'] cuando llames confirmar_pedido.\n";
                    } else {
                        // Cliente NO tiene cédula → pedírsela
                        $prompt .= "\n\n🪪 CÉDULA REQUERIDA (cliente nuevo):\n"
                                 . "- Este cliente NO tiene cédula registrada todavía.\n"
                                 . "- Antes de confirmar pedido, pídela: '¿Me regalas tu número de cédula? "
                                 . "Es para registrarte en el sistema.'\n"
                                 . "- Cuando el cliente la dé, pásala en orderData['cedula'] al llamar confirmar_pedido.\n";
                    }
                }
            }
        } catch (\Throwable $e) { /* ignorar */ }

        // ⚠️ Regla CRÍTICA pero corta sobre confirmar_pedido
        $prompt .= "\n\n⚠️ Para registrar un pedido OBLIGATORIO llamar la herramienta `confirmar_pedido`. "
                 . "NUNCA digas 'queda listo', 'te esperamos', 'pedido registrado' SIN haber llamado la herramienta. "
                 . "Si el cliente confirma con 'sí', 'confirmar', 'dale' → llama `confirmar_pedido` antes de responder.\n";

        // 🔒 INYECCIÓN OBLIGATORIA DE COBERTURA REAL (anti-alucinación)
        // Sin importar lo que diga el prompt maestro, agregamos al final la cobertura
        // real configurada en sedes + reglas duras. El LLM da más peso a las
        // últimas instrucciones, así la cobertura SIEMPRE refleja la configuración
        // real (no lo que el prompt maestro hardcoded prometa: 'envíos
        // internacionales con FedEx', etc.).
        $zonasReales = $contexto['zonas'] ?? '';
        if (!empty($zonasReales)) {
            $prompt .= "\n\n═══════════════════════════════════════════════════════════════════════════════\n"
                     . "# 🌍 COBERTURA REAL DEL NEGOCIO (FUENTE DE VERDAD — IGNORA OTRAS SECCIONES)\n\n"
                     . $zonasReales . "\n\n"
                     . "Este bloque es la verdad operacional del momento. Si secciones anteriores del\n"
                     . "prompt mencionan envíos a otros países, ciudades o regiones, esas son SOLO\n"
                     . "INFORMACIONALES y deben ser ignoradas si no aparecen aquí. Para validar\n"
                     . "cualquier dirección concreta, llama `validar_cobertura`.\n";
        }

        // ════════════════════════════════════════════════════════════════════
        // 🛡️ REGLAS PROFESIONALES ANTI-ALUCINACIÓN (la última palabra)
        // El LLM da MÁS peso a las últimas instrucciones. Estas son LEY.
        // ════════════════════════════════════════════════════════════════════
        $prompt .= "\n\n═══════════════════════════════════════════════════════════════════════════════\n"
                 . "# 🛡️ REGLAS DE ORO — ANTI-ALUCINACIÓN PROFESIONAL\n\n"

                 . "Eres un *asistente comercial profesional*. Comportamiento esperado:\n\n"

                 . "## 1. NUNCA INVENTES INFORMACIÓN\n"
                 . "  - NO inventes precios. Si necesitas un precio, llama `buscar_productos` o `info_producto`.\n"
                 . "  - NO inventes códigos de productos. Usa el código EXACTO del catálogo.\n"
                 . "  - NO inventes horarios. Si preguntan, llama `consultar_horarios` o di que vas a verificar.\n"
                 . "  - NO inventes promociones, descuentos, ni 'ofertas especiales para ti'.\n"
                 . "  - NO inventes tiempos de entrega (ej. '30 minutos'). El tiempo lo da el sistema al despachar.\n"
                 . "  - NO inventes nombres de empleados, sedes, métodos de pago no configurados.\n"
                 . "  - NO inventes dirección de la sede, NIT, datos del negocio.\n\n"

                 . "## 2. SI NO SABES ALGO, DILO\n"
                 . "  Cuando el cliente pregunte algo que NO está en tus tools ni en este contexto:\n"
                 . "    > 'Esa información no la tengo a mano. ¿Quieres que un asesor te confirme?'\n"
                 . "  NUNCA inventes una respuesta para parecer útil.\n\n"

                 . "## 3. PROMESAS PROHIBIDAS\n"
                 . "  - 'El más fresco', '100% garantizado', 'el mejor precio', 'precio especial'.\n"
                 . "  - 'Envío gratis siempre', 'sin costo adicional' (a menos que el sistema lo confirme).\n"
                 . "  - 'En menos de X minutos', 'antes de X hora'.\n"
                 . "  - 'Te lo regalamos', 'oferta exclusiva'.\n\n"

                 . "## 4. SOLO USA DATOS DE TU CONTEXTO\n"
                 . "  Tienes en este prompt:\n"
                 . "    • Catálogo (vía buscar_productos)\n"
                 . "    • Horarios y sedes configurados\n"
                 . "    • Zonas de cobertura\n"
                 . "    • Promociones activas (si las hay)\n"
                 . "    • Métodos de pago configurados\n"
                 . "  Cualquier dato fuera de esto → 'Voy a verificarlo' o 'No tengo esa info, te paso con un asesor'.\n\n"

                 . "## 5. ESTILO PROFESIONAL\n"
                 . "  - Tono cordial pero profesional. Evita frases excesivamente coloquiales (\"ay parce\", \"qué chimba\").\n"
                 . "  - Emojis con moderación: máximo 1-2 por mensaje.\n"
                 . "  - Mensajes claros y al punto. NO sobreexplicar.\n"
                 . "  - Si dudas, mejor pregunta antes que adivinar.\n\n"

                 . "## 6. TOOLS COMO ÚNICA FUENTE DE DATOS\n"
                 . "  - Para precios → `buscar_productos` o `info_producto`\n"
                 . "  - Para cobertura → `validar_cobertura` o `consultar_zonas_cobertura`\n"
                 . "  - Para horarios → `consultar_horarios`\n"
                 . "  - Para promos → `consultar_promociones`\n"
                 . "  - Para pedidos del cliente (¿cuántos tengo? mis pedidos, último pedido, estado) → `consultar_mis_pedidos`\n"
                 . "  - Para ADICIONAR productos a un pedido existente (cliente dice 'agrégale X al pedido N', 'adiciono Y al 95') → "
                 . "primero `consultar_mis_pedidos` si no sabes el ID, luego `buscar_productos` para validar precio/código real, "
                 . "y finalmente `crear_adicion_pedido` con pedido_id_origen + productos. NO confirmes la adición antes de la tool: "
                 . "el sistema valida la ventana ANS automáticamente y devuelve ok=false si expiró.\n"
                 . "  - Para registrar cliente nuevo en SGI → `verificar_cliente_erp`\n"
                 . "  - Para registrar pedido → `confirmar_pedido` (OBLIGATORIO al cerrar)\n"
                 . "  Si no encuentras la respuesta en tus tools, NO la inventes.\n\n"

                 . "## 7. RESPETO AL FLUJO\n"
                 . "  Sigue el orden del orquestador. NO saltes pasos. NO confirmes pedidos sin todos los datos.\n";

        return $prompt;
    }

    /**
     * @deprecated — código legacy del prompt hardcoded. NO se llama, queda solo
     * por compatibilidad si algún test viejo lo invoca. El prompt real lo construye
     * BotPromptService::plantillaPorDefecto() o el editor del usuario.
     */
    private function _getSystemPromptHardcodedDEPRECATED(): string
    {
        $config = \App\Models\ConfiguracionBot::actual();
        $catalogo = app(BotCatalogoService::class);
        $catalogoTexto = '';
        $promosTexto   = '';
        $zonasTexto    = '';
        $name = '';
        $sedeId = null;
        $infoEmpresa = '';
        $pedidosInfo = '';
        $ansInfo = '';
        $nombreAsesora = $config->nombre_asesora ?: 'Sofía';

        // Nota sobre imágenes (solo si está activo)
        $notaImagenes = $config->enviar_imagenes_productos
            ? "\n# 📸 IMÁGENES (FUNCIÓN enviar_imagen_producto)\n" .
              "Tienes la función `enviar_imagen_producto` disponible. Úsala con criterio:\n" .
              "- Cuando el cliente diga \"muéstrame\", \"tienes foto\", \"cómo se ve\".\n" .
              "- Cuando el cliente dude entre 2 productos similares.\n" .
              "- Para destacar 1-2 productos al inicio de la conversación si son destacados.\n" .
              "- Máximo {$config->max_imagenes_por_mensaje} imágenes por llamada.\n" .
              "- Usa los códigos SKU del catálogo (ej: POL-PEC, RES-MOL).\n" .
              "- NO la uses en cada mensaje — solo cuando aporte valor."
            : '';

        $hora = (int) now()->format('H');
        $saludoHora = $hora < 12 ? 'buenos días' : ($hora < 19 ? 'buenas tardes' : 'buenas noches');

        return <<<PROMPT
Eres {$nombreAsesora}, asesora de Alimentos La Hacienda en Bello, Antioquia. Atiendes pedidos por WhatsApp.

# Quién eres
Eres una persona real, paisa, cálida, ágil y servicial. Hablas natural, como alguien
del barrio que conoce a sus clientes. NO eres formal ni acartonada. Suenas como una
amiga que trabaja en el local.

Si el cliente pregunta si eres bot o humano, responde:
"Soy {$nombreAsesora}, del equipo de La Hacienda. Estoy aquí pa ayudarte con tu pedido 😊"
{$notaImagenes}

# Cómo hablas
- Cercana y natural. Usa expresiones como "claro que sí", "listo", "dale", "a la orden", "con gusto", "perfecto".
- Frases cortas, como en WhatsApp real. NUNCA párrafos largos.
- A veces solo 1 línea. Máximo 3-4 líneas por mensaje.
- Tutea siempre. Nada de "usted" salvo que el cliente lo prefiera.
- Usa *negrita* WhatsApp solo para precios y datos clave (no abuses).
- Emojis con criterio: 😊 🔥 🍗 🥩 🚚 🙌 👍 — máximo 1 o 2 por mensaje.
- Saludas según la hora actual ({$saludoHora}) si es el primer mensaje.
- Si el cliente es recurrente, salúdalo por su nombre y haz referencia a su última compra.
- NUNCA repitas la misma frase de bienvenida o cierre. Varía siempre.
- Reacciona a lo que dice el cliente: "uy qué rica esa pechuga 🍗", "tranquila, te ayudo", "hermana, eso queda divino con...".

# Lo que sabes (úsalo para responder)
Empresa: {$infoEmpresa}

Cliente actual: {$name}
Historial de este cliente:
{$pedidosInfo}

Catálogo disponible HOY (precios oficiales — NO inventes nada fuera de aquí):
{$catalogoTexto}

Promociones vigentes:
{$promosTexto}

Zonas donde entregamos:
{$zonasTexto}

Tiempos para cancelar/adicionar pedidos:
{$ansInfo}

# Reglas innegociables
1. NUNCA inventes productos ni precios. Solo los del catálogo de arriba.
2. Si te piden algo que no tienes, dilo de forma natural y sugiere lo más parecido:
   "Uy hermana, manejo *muslos* a \$9.800 y *pechuga* a \$14.500 ¿cuál te tinca?"
3. Si el barrio NO está en zonas de cobertura, dilo claro pero amable:
   "Mami, a ese barrio aún no llegamos 😔 ¿puedes recoger en la sede?"
4. Solo llama confirmar_pedido cuando el cliente diga: sí / dale / listo / ok / confirmo.
5. Necesitas: nombre, dirección, barrio (cubierto), teléfono y ≥1 producto del catálogo.
6. Nunca confirmes dos veces en la misma conversación.

# Cómo presentar el resumen antes de confirmar
Hazlo tipo charla, no como una factura. Ejemplo natural:

"Listo {$name}, te lo dejo así:

🍗 *2 lb Pechuga deshuesada* — \$29.000
🥓 *1 paquete Tocineta* — \$22.000

📍 Cra 50 #45-12, *Niquía*
👤 {$name} · 📞 3001234567

🚚 Envío *gratis* (zona Norte)
💵 *Total: \$51.000* — pago contra entrega

¿Le damos? 🙌"

# Few-shot — así suenan tus mensajes (varía SIEMPRE, no copies literal)

Cliente: "hola buenas"
Tú: "¡Hola! 👋 Bienvenida a La Hacienda. ¿Qué te provoca hoy?"

Cliente: "qué tienen?"
Tú: "Hoy tenemos carnes frescas, pollo, cerdo y embutidos 🥩🍗 ¿Buscas algo en especial o te paso la lista?"

Cliente: "tienen pollo?"
Tú: "Claro 🍗 Manejo *pechuga deshuesada* a \$14.500/lb, *muslos* a \$9.800/lb y *pollo entero* a \$28.000. ¿Cuál te llevo?"

Cliente: "1 kilo pechuga"
Tú: "Perfecto, *2 libras de pechuga* serían \$29.000 (ese kilo manejémoslo en libras 😉). ¿Para qué barrio?"

Cliente: "Niquia"
Tú: "Genial, Niquía nos queda cerquita y el envío te sale *gratis* 🚚 ¿Algo más o cerramos pedido?"

Cliente: "no, ya"
Tú: "Listo. ¿Me das tu nombre, dirección y teléfono pa cuadrar la entrega?"

Cliente: "Andrés, calle 50 #20-15, 3001234567"
Tú: [muestra resumen tipo el ejemplo de arriba con todos los datos]

Cliente: "dale, confirmo"
Tú: [llamas confirmar_pedido]

Cliente: "tienen camarones?"
Tú: "Mira, camarones no manejo 😅 pero si quieres algo del mar te queda mejor por otro lado. Lo que sí tengo y vuela es *carne molida* y *pechuga* — ¿te muestro?"

Cliente: "vivo en Caldas"
Tú: "Uy hermano, hasta Caldas aún no llegamos 😔 pero si pasas por el local en Bello te lo tenemos listo. ¿Te late?"

Cliente: "muy caro"
Tú: "Te entiendo 🙏 Si quieres algo más económico, los *muslos a \$9.800/lb* salen muy bien y son riquísimos para sudado. ¿Probamos con eso?"
PROMPT;
    }

    public function getToolsDefinicion(): array
    {
        $config = \App\Models\ConfiguracionBot::actual();
        $tools = [];

        // Tool 1: confirmar_pedido — siempre disponible
        $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'confirmar_pedido',
                    'description' => 'Registra el pedido en el sistema. LLAMA SIEMPRE QUE NECESITES confirmar un pedido — '
                        . 'no basta con responderle al cliente que su pedido quedó registrado, DEBES llamar esta función '
                        . 'o el pedido NO existe. '
                        . 'Condiciones previas obligatorias: '
                        . '(1) el cliente confirmó explícitamente con "sí/dale/listo/confirmo/ok confirmo" — NUNCA con un simple "gracias"; '
                        . '(2) los productos son del catálogo; '
                        . '(3) el barrio está cubierto (ya llamaste validar_cobertura); '
                        . '(4) tienes nombre, dirección y teléfono del cliente. '
                        . 'DESPUÉS de llamar esta función, el sistema te devuelve un mensaje — ese sí le puedes decir al cliente "tu pedido quedó registrado #N".',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'products' => [
                                'type'        => 'array',
                                'description' => 'Productos del pedido — DEBEN ser del catálogo. Usa el código SKU si lo conoces.',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'code'     => ['type' => 'string', 'description' => 'Código SKU del producto (recomendado, ej: POL-PEC).'],
                                        'name'     => ['type' => 'string', 'description' => 'Nombre exacto del producto en el catálogo.'],
                                        'quantity' => ['type' => 'number', 'description' => 'Cantidad numérica.'],
                                        'unit'     => ['type' => 'string', 'description' => 'Unidad del catálogo (libra, kg, unidad, paquete...).'],
                                    ],
                                    'required' => ['name', 'quantity', 'unit'],
                                ],
                            ],
                            'customer_name'  => ['type' => 'string', 'description' => 'Nombre completo del cliente'],
                            'cedula'         => ['type' => 'string', 'description' => 'Número de cédula o NIT del cliente — OBLIGATORIO si el negocio tiene lookup ERP activo. Solo dígitos sin puntos.'],
                            'phone'          => ['type' => 'string', 'description' => 'Teléfono del cliente'],
                            'email'          => ['type' => 'string', 'description' => 'Correo electrónico del cliente (si lo dio)'],
                            'address'        => ['type' => 'string', 'description' => 'Dirección de entrega exacta'],
                            'neighborhood'   => ['type' => 'string', 'description' => 'Barrio (debe estar en alguna zona de cobertura del catálogo)'],
                            'location'       => ['type' => 'string', 'description' => 'Ciudad o zona'],
                            'payment_method' => ['type' => 'string', 'description' => 'Método de pago (default: contra entrega)'],
                            'pickup_time'    => ['type' => 'string', 'description' => 'Hora estimada de entrega'],
                            'coupon_code'    => ['type' => 'string', 'description' => 'Código de cupón si el cliente lo mencionó'],
                            'notes'          => ['type' => 'string', 'description' => 'Notas adicionales del pedido'],
                        ],
                        'required' => ['products', 'customer_name', 'phone', 'address', 'neighborhood'],
                    ],
                ],
        ];

        // Tool: validar_cobertura — siempre disponible
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => 'validar_cobertura',
                'description' => 'Verifica si una dirección está dentro de una zona de cobertura antes de confirmar un pedido. '
                    . 'DEBES llamarla SIEMPRE que el cliente te dé su dirección, ANTES de pedir el resto de datos o confirmar. '
                    . 'Si la dirección no está cubierta, NO confirmes el pedido y ofrece recoger en sede. '
                    . 'Retorna: cubierta (bool), zona, costo_envio, tiempo_estimado, pedido_minimo (0=sin mínimo), '
                    . 'sede_sugerida (la sede más cercana que despachará), distancia_km, mensaje_sugerido. '
                    . 'IMPORTANTE: si pedido_minimo > 0, avísale al cliente el mínimo ANTES de que siga pidiendo. '
                    . 'Si sede_sugerida viene, menciónala al cliente: "Te despachamos desde [sede_sugerida] (a X km)".',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'direccion' => [
                            'type'        => 'string',
                            'description' => 'Dirección tal cual la dio el cliente (ej: "Calle 50 #23-45"). Obligatoria.',
                        ],
                        'barrio' => [
                            'type'        => 'string',
                            'description' => 'Barrio mencionado por el cliente (ej: "Niquía", "Paris"). Opcional pero recomendado.',
                        ],
                        'ciudad' => [
                            'type'        => 'string',
                            'description' => 'Ciudad o municipio (default: Bello).',
                        ],
                    ],
                    'required' => ['direccion'],
                ],
            ],
        ];

        // 👤 Tool: verificar_cliente_erp — solo si alguna integración tiene cliente_lookup activo
        $tenantIdLkp = app(\App\Services\TenantManager::class)->id();
        $integLookup = $tenantIdLkp ? \App\Models\Integracion::where('tenant_id', $tenantIdLkp)
            ->where('activo', true)
            ->where('exporta_pedidos', true)
            ->get()
            ->first(fn ($i) => $i->config['cliente_lookup']['activo'] ?? false) : null;

        if ($integLookup) {
            $camposReq = $integLookup->config['cliente_lookup']['campos_requeridos'] ?? [];
            $listaCampos = collect($camposReq)->map(fn ($c) => match ($c) {
                'cedula'    => 'cédula',
                'nombre'    => 'nombre completo',
                'direccion' => 'dirección',
                'telefono'  => 'teléfono',
                'email'     => 'correo',
                'ciudad'    => 'ciudad',
                default     => $c,
            })->implode(', ');

            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'verificar_cliente_erp',
                    'description' => "Verifica si un cliente ya está registrado en el ERP de este negocio. "
                        . "DEBES llamar esta función SIEMPRE al INICIO del flujo de pedido, apenas el cliente te dé su cédula. "
                        . "Retorna: existe (bool), datos del cliente si existe (nombre, dirección, teléfono), "
                        . "campos_faltantes (lista de datos que debes pedir si NO existe). "
                        . "Si existe → continúa con el pedido sin pedir más datos personales. "
                        . "Si NO existe → pide UNO POR UNO los campos: {$listaCampos}, después llama confirmar_pedido normalmente.",
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'cedula' => [
                                'type'        => 'string',
                                'description' => 'Número de cédula o NIT del cliente, sin puntos ni guiones (ej: "1007767612").',
                            ],
                            'telefono' => [
                                'type'        => 'string',
                                'description' => 'Teléfono del cliente (opcional, si lo conoces). Se usa para buscar también por celular en el ERP.',
                            ],
                        ],
                        'required' => ['cedula'],
                    ],
                ],
            ];
        }

        // Tool: derivar_a_departamento — solo si está activada en config y hay departamentos.
        $deptos = ($config->derivacion_activa ?? true)
            ? \App\Models\Departamento::where('activo', true)->orderBy('orden')->get(['id','nombre'])
            : collect();
        if ($deptos->isNotEmpty()) {
            $nombresDeptos = $deptos->pluck('nombre')->all();
            $instruccionesIA = trim((string) ($config->derivacion_instrucciones_ia
                ?: \App\Models\ConfiguracionBot::DERIVACION_INSTRUCCIONES_DEFAULT));

            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'derivar_a_departamento',
                    'description' => $instruccionesIA,
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'departamento' => [
                                'type'        => 'string',
                                'description' => 'Nombre exacto del departamento a donde derivar. Opciones disponibles: ' . implode(', ', $nombresDeptos),
                                'enum'        => $nombresDeptos,
                            ],
                            'razon' => [
                                'type'        => 'string',
                                'description' => 'Resumen breve (1 frase) de POR QUÉ estás derivando. Ej: "Cliente muy molesto por producto dañado" / "Pide precio mayorista" / "Reclamo por cobro doble".',
                            ],
                            'urgencia' => [
                                'type'        => 'string',
                                'description' => 'Nivel de urgencia. Úsalo para priorizar en la notificación al equipo.',
                                'enum'        => ['baja', 'media', 'alta', 'critica'],
                            ],
                        ],
                        'required' => ['departamento', 'razon'],
                    ],
                ],
            ];
        }

        // ── Tool: registrar_datos_cliente — si el tenant pide cedula o correo ──
        if (!empty($config->pedir_cedula) || !empty($config->pedir_correo)) {
            $props = [];
            $required = [];
            if (!empty($config->pedir_cedula)) {
                $props['cedula'] = ['type' => 'string', 'description' => 'Número de cédula que dio el cliente.'];
            }
            if (!empty($config->pedir_correo)) {
                $props['email'] = ['type' => 'string', 'description' => 'Correo electrónico que dio el cliente.'];
            }
            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'registrar_datos_cliente',
                    'description' => '🆔 OBLIGATORIA: cuando el cliente te dé su cédula y/o correo electrónico, '
                        . 'DEBES llamar esta función para registrarlos en su perfil. '
                        . 'Llámala UNA SOLA VEZ cuando tengas los datos. Después puedes seguir con el pedido normalmente.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => empty($props) ? new \stdClass() : $props,
                        'required'   => [],
                    ],
                ],
            ];
        }

        // ── Tools de CONSULTA DE CATÁLOGO — solo si bot_modo_agente=true ──
        if (!empty($config->bot_modo_agente)) {
            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'buscar_productos',
                    'description' => '🚨 OBLIGATORIA. ANTES DE NEGAR la existencia de cualquier producto al cliente, '
                        . 'DEBES llamar esta función con el texto LITERAL que escribió el cliente. '
                        . 'NUNCA respondas "no tengo X" o "solo tengo Y" sin haberla llamado primero. '
                        . 'NUNCA recortes la query — si el cliente dice "pierna a la parrilla", query="pierna a la parrilla", '
                        . 'NO query="pierna". '
                        . 'Retorna top N productos con código, nombre, categoría, precio y unidad.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Texto a buscar (ej: "pierna a la parrilla", "pollo campesino", "queso").',
                            ],
                            'categoria' => [
                                'type'        => 'string',
                                'description' => 'Categoría opcional para acotar la búsqueda (ej: "RES", "ASADERO"). Omitir para buscar en todas.',
                            ],
                            'limite' => [
                                'type'        => 'integer',
                                'description' => 'Cantidad máxima de resultados (default 5, máx 20).',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ];

            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'listar_categorias',
                    'description' => 'Lista todas las categorías del catálogo con cantidad de productos en cada una. '
                        . 'Úsala cuando el cliente pregunte "qué tienen", "qué venden", "muéstrame el menú", o esté indeciso.',
                    'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ];

            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'productos_de_categoria',
                    'description' => 'Lista productos de una categoría específica. Útil cuando el cliente pide "muéstrame las carnes de res", "qué pescados tienen", etc.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'categoria' => ['type' => 'string', 'description' => 'Nombre de la categoría exacto o parcial.'],
                            'limite'    => ['type' => 'integer', 'description' => 'Cantidad máxima (default 20, máx 50).'],
                        ],
                        'required' => ['categoria'],
                    ],
                ],
            ];

            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'info_producto',
                    'description' => 'Detalle de un producto por código: descripción completa, cortes disponibles, foto, destacado.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'codigo' => ['type' => 'string', 'description' => 'Código SKU del producto.'],
                        ],
                        'required' => ['codigo'],
                    ],
                ],
            ];

            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'productos_destacados',
                    'description' => 'Top destacados + promociones vigentes. Úsala al saludar o cuando el cliente esté perdido.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'limite' => ['type' => 'integer', 'description' => 'Cantidad de destacados (default 8).'],
                        ],
                    ],
                ],
            ];
        }

        // ── Tools DINÁMICAS desde IntegracionConsulta (usar_en_bot=true) ──
        // Cada consulta guardada que el usuario marque como "disponible para el
        // bot" se expone aquí como tool. Esto permite construir agentes
        // personalizados sin tocar código.
        try {
            $consultas = \App\Models\IntegracionConsulta::query()
                ->where('usar_en_bot', true)
                ->where('activa', true)
                ->whereHas('integracion', fn ($q) => $q->where('activo', true))
                ->get();

            foreach ($consultas as $cons) {
                $properties = [];
                $required   = [];
                foreach ((array) ($cons->parametros ?? []) as $p) {
                    if (empty($p['nombre'])) continue;
                    $jsonType = match ($p['tipo'] ?? 'string') {
                        'number'  => 'number',
                        'boolean' => 'boolean',
                        default   => 'string',
                    };
                    $properties[$p['nombre']] = [
                        'type'        => $jsonType,
                        'description' => (string) ($p['descripcion'] ?? $p['nombre']),
                    ];
                    if (!empty($p['requerido'])) {
                        $required[] = $p['nombre'];
                    }
                }

                $tools[] = [
                    'type'     => 'function',
                    'function' => [
                        'name'        => $cons->nombreTool(),
                        'description' => trim(($cons->descripcion ?: $cons->nombre_publico) . ' (consulta personalizada del tenant).'),
                        'parameters'  => [
                            'type'       => 'object',
                            'properties' => empty($properties) ? new \stdClass() : $properties,
                            'required'   => $required,
                        ],
                    ],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron cargar consultas dinamicas: ' . $e->getMessage());
        }

        // Tool 2: enviar_imagen_producto — SOLO si está activado en config
        if ($config->enviar_imagenes_productos) {
            $tools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => 'enviar_imagen_producto',
                    'description' => 'Envía al cliente las fotos de uno o varios productos del catálogo (máx ' . $config->max_imagenes_por_mensaje . ' por llamada). Úsala cuando el cliente pida ver el producto, dude entre opciones, o quieras mostrarle algo apetitoso. NO la uses para todos los mensajes — solo cuando aporte valor.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'codigos' => [
                                'type'        => 'array',
                                'description' => 'Lista de códigos SKU del catálogo (máx ' . $config->max_imagenes_por_mensaje . '). Ej: ["POL-PEC", "RES-MOL"]',
                                'items'       => ['type' => 'string'],
                            ],
                            'mensaje_acompañante' => [
                                'type'        => 'string',
                                'description' => 'Texto natural breve que se enviará junto con las fotos. Ej: "Mira qué frescas 😍"',
                            ],
                        ],
                        'required' => ['codigos'],
                    ],
                ],
            ];
        }

        // 🏪 Tool: consultar_horarios — devuelve los horarios REALES de las
        // sedes activas del tenant. Evita que el LLM invente horarios.
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => 'consultar_horarios',
                'description' => 'Devuelve los horarios REALES de atención de todas las sedes activas del tenant, '
                    . 'desde la BD. ÚSALA SIEMPRE que el cliente pregunte "¿a qué horas?", "¿están abiertos?", '
                    . '"horarios", "cuándo abren", "cuándo cierran", "cuándo atienden". '
                    . 'NUNCA inventes horarios — siempre llama esta tool primero.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
        ];

        // 🗺️ Tool: consultar_zonas_cobertura — zonas + montos mínimos + costos por sede
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => 'consultar_zonas_cobertura',
                'description' => 'Devuelve zonas de domicilio AGRUPADAS POR SEDE, con costos de envío, tiempos '
                    . 'estimados y MONTO MÍNIMO de pedido por cada sede. ÚSALA cuando el cliente pregunte: '
                    . '"¿hacen domicilios?", "¿llegan a X?", "¿qué zonas cubren?", "¿cuánto cobran de domicilio?", '
                    . '"¿cuál es el pedido mínimo?", "¿cuánto cuesta el envío?". '
                    . 'NUNCA inventes montos: usa exactos los valores del payload. Para validar UNA dirección '
                    . 'concreta usa `validar_cobertura` (más precisa).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
        ];

        // 📦 Tool: consultar_mis_pedidos — pedidos del cliente que escribe
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => 'consultar_mis_pedidos',
                'description' => 'Devuelve los pedidos del cliente que escribe por WhatsApp (identificado por su número). '
                    . 'ÚSALA cuando el cliente pregunte "¿cuántos pedidos tengo?", "mis pedidos", "mi último pedido", '
                    . '"estado de mi pedido", "qué pasó con mi pedido", "ya llegó mi pedido". '
                    . 'Devuelve estado, total, fecha y link de seguimiento. NUNCA inventes pedidos: usa solo los del payload. '
                    . 'Si el array está vacío, dile al cliente que no encontraste pedidos asociados a su número.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'limite' => [
                            'type'        => 'integer',
                            'description' => 'Cuántos pedidos devolver (máx 10, default 5)',
                        ],
                    ],
                    'required'   => [],
                ],
            ],
        ];

        // 🛒 Tool: crear_adicion_pedido — adiciona productos a un pedido existente
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => 'crear_adicion_pedido',
                'description' => 'Crea una ADICIÓN al pedido indicado: un pedido nuevo en BD ligado al original (pedido_origen_id) '
                    . 'que se exporta a SGI como documento separado. ÚSALA cuando el cliente confirma productos para sumar a un '
                    . 'pedido anterior (ej. "adiciona 2 libras de posta al pedido #95"). Antes de invocar: '
                    . '1) confirma con el cliente a cuál pedido (consultar_mis_pedidos si no sabes), '
                    . '2) confirma productos y cantidades exactas, '
                    . '3) llama esta tool. El sistema valida ANS automáticamente (rechaza si pasaron más minutos de los permitidos). '
                    . 'Usa SIEMPRE el campo `codigo` y `name` exactos que devolvió `buscar_productos` — NO inventes.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'pedido_id_origen' => [
                            'type'        => 'integer',
                            'description' => 'ID del pedido al que se adicionan productos (ej. 95)',
                        ],
                        'productos' => [
                            'type'  => 'array',
                            'description' => 'Productos a adicionar. Cada item: {code, name, qty, unit}',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'code' => ['type' => 'string', 'description' => 'Código del catálogo'],
                                    'name' => ['type' => 'string', 'description' => 'Nombre EXACTO del catálogo'],
                                    'qty'  => ['type' => 'number', 'description' => 'Cantidad'],
                                    'unit' => ['type' => 'string', 'description' => 'Unidad (Kl, Lb, Und)'],
                                ],
                                'required' => ['name', 'qty'],
                            ],
                        ],
                    ],
                    'required' => ['pedido_id_origen', 'productos'],
                ],
            ],
        ];

        // 🎁 Tool: consultar_promociones — promociones vigentes del tenant
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => 'consultar_promociones',
                'description' => 'Devuelve las promociones vigentes del tenant. ÚSALA cuando el cliente pregunte '
                    . '"¿qué promociones tienen?", "¿hay descuentos hoy?", "ofertas".',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
        ];

        return $tools;
    }

    /*
    |==========================================================================
    | INTERVENCIÓN HUMANA — endpoints para que un operador chatee manualmente
    |==========================================================================
    */

    /**
     * Envía un mensaje manual desde el admin al cliente vía WhatsApp.
     * También lo persiste en la conversación.
     */
    public function enviarMensajeManual(Request $request)
    {
        $data = $request->validate([
            'conversacion_id' => 'required|integer|exists:conversaciones_whatsapp,id',
            'mensaje'         => 'required|string|max:4000',
        ]);

        $conversacion = \App\Models\ConversacionWhatsapp::findOrFail($data['conversacion_id']);
        $telefono     = $conversacion->telefono_normalizado;

        // Enviar a WhatsApp
        $sent = $this->enviarRespuestaWhatsapp(
            $telefono,
            $data['mensaje'],
            $conversacion->connection_id
        );

        if (!$sent) {
            return response()->json(['status' => 'error', 'message' => 'No se pudo enviar a WhatsApp'], 500);
        }

        // Persistir como mensaje del bot (pero marcado como humano en meta)
        app(\App\Services\ConversacionService::class)->agregarMensaje(
            $conversacion,
            \App\Models\MensajeWhatsapp::ROL_ASSISTANT,
            $data['mensaje'],
            ['meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id()]]
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Toma control de la conversación — el bot deja de responder a este cliente.
     */
    public function tomarControl(Request $request)
    {
        $data = $request->validate(['conversacion_id' => 'required|integer|exists:conversaciones_whatsapp,id']);
        $conv = \App\Models\ConversacionWhatsapp::findOrFail($data['conversacion_id']);
        $conv->update(['atendida_por_humano' => true]);
        return response()->json(['status' => 'ok', 'atendida_por_humano' => true]);
    }

    /**
     * Devuelve el control al bot.
     */
    public function devolverAlBot(Request $request)
    {
        $data = $request->validate(['conversacion_id' => 'required|integer|exists:conversaciones_whatsapp,id']);
        $conv = \App\Models\ConversacionWhatsapp::findOrFail($data['conversacion_id']);
        $conv->update(['atendida_por_humano' => false]);
        return response()->json(['status' => 'ok', 'atendida_por_humano' => false]);
    }

    /*
    |==========================================================================
    | WHATSAPP API
    |==========================================================================
    */

    private function enviarRespuestaWhatsapp(string $from, string $reply, $connectionId = null): bool
    {
        try {
            $payload = [
                'number' => $from,
                'body'   => $reply,
            ];

            if (!empty($connectionId)) {
                $payload['whatsappId']   = (int) $connectionId;
                $payload['connectionId'] = (int) $connectionId;
            }

            Log::info('📤 ENVIANDO A WHATSAPP', ['payload' => $payload]);

            $token = $this->obtenerTokenWhatsapp();

            if (!$token) {
                Log::error('❌ No se pudo obtener token de WhatsApp');

                $this->notificarFallaWhatsapp(
                    'TOKEN WHATSAPP NO DISPONIBLE',
                    'No se pudo obtener token para enviar mensajes de WhatsApp.',
                    [
                        'from' => $from,
                        'connectionId' => $connectionId,
                        'payload' => $payload,
                    ]
                );

                return false;
            }

            $response = $this->postWhatsappSend($token, $payload);

            if ($response->successful()) {
                Log::info('✅ RESPUESTA ENVIADA', [
                    'status' => $response->status(),
                    'phone'  => $from,
                ]);
                return true;
            }

            $body    = $response->json();
            $rawBody = $response->body();

            Log::warning('⚠️ Primer intento de envío falló', [
                'status' => $response->status(),
                'body'   => $rawBody,
                'phone'  => $from,
            ]);

            if ($response->status() === 401 && $this->esSesionExpiradaWhatsapp($body, $rawBody)) {
                Log::warning('🔄 Sesión expirada. Intentando refresh_token...', [
                    'phone' => $from,
                ]);

                $newToken = $this->refrescarTokenWhatsapp();

                if (!$newToken) {
                    Log::warning('⚠️ Refresh falló. Intentando login completo...', [
                        'phone' => $from,
                    ]);

                    $newToken = $this->loginWhatsapp(true);
                }

                if (!$newToken) {
                    Log::error('❌ No se pudo renovar el token de WhatsApp');

                    $this->notificarFallaWhatsapp(
                        'SESIÓN WHATSAPP EXPIRADA',
                        'La sesión de WhatsApp expiró y no fue posible renovarla automáticamente.',
                        [
                            'from' => $from,
                            'connectionId' => $connectionId,
                            'status' => $response->status(),
                            'body' => $rawBody,
                        ]
                    );

                    return false;
                }

                $retryResponse = $this->postWhatsappSend($newToken, $payload);

                if ($retryResponse->successful()) {
                    Log::info('✅ RESPUESTA ENVIADA EN REINTENTO', [
                        'status' => $retryResponse->status(),
                        'phone'  => $from,
                    ]);
                    return true;
                }

                $retryBody = $retryResponse->body();

                Log::error('❌ Falló el reintento de envío a WhatsApp', [
                    'status' => $retryResponse->status(),
                    'body'   => $retryBody,
                    'phone'  => $from,
                ]);

                $this->notificarFallaWhatsapp(
                    'FALLO REINTENTO WHATSAPP',
                    'Se intentó reenviar un mensaje después de refrescar la sesión, pero falló.',
                    [
                        'from' => $from,
                        'connectionId' => $connectionId,
                        'status' => $retryResponse->status(),
                        'body' => $retryBody,
                        'payload' => $payload,
                    ]
                );

                return false;
            }

            if ($this->esWhatsappNoConectado($body, $rawBody)) {
                Log::error('⚠️ WHATSAPP NO CONECTADO', [
                    'status' => $response->status(),
                    'body'   => $rawBody,
                    'phone'  => $from,
                    'connectionId' => $connectionId,
                ]);

                $this->notificarFallaWhatsapp(
                    'WHATSAPP DESCONECTADO',
                    'La conexión de WhatsApp no está conectada o está en proceso de emparejamiento.',
                    [
                        'from' => $from,
                        'connectionId' => $connectionId,
                        'status' => $response->status(),
                        'body' => $rawBody,
                        'payload' => $payload,
                    ]
                );

                return false;
            }

            Log::error('⚠️ WHATSAPP API ERROR', [
                'status' => $response->status(),
                'body'   => $rawBody,
                'phone'  => $from,
            ]);

            $this->notificarFallaWhatsapp(
                'ERROR ENVÍO WHATSAPP',
                'Ocurrió un error al enviar un mensaje de WhatsApp.',
                [
                    'from' => $from,
                    'connectionId' => $connectionId,
                    'status' => $response->status(),
                    'body' => $rawBody,
                    'payload' => $payload,
                ]
            );

            return false;
        } catch (\Throwable $e) {
            Log::error('❌ ERROR ENVIANDO A WHATSAPP', [
                'error' => $e->getMessage(),
                'phone' => $from,
            ]);

            $this->notificarFallaWhatsapp(
                'EXCEPCIÓN ENVÍO WHATSAPP',
                'Se produjo una excepción enviando un mensaje de WhatsApp.',
                [
                    'from' => $from,
                    'connectionId' => $connectionId,
                    'error' => $e->getMessage(),
                    'payload' => $payload ?? [],
                ]
            );

            return false;
        }
    }

    private function postWhatsappSend(string $token, array $payload)
    {
        return Http::withoutVerifying()
            ->withToken($token)
            ->timeout(20)
            ->post('https://wa-api.tecnobyteapp.com:1422/api/messages/send', $payload);
    }

    /**
     * Envía una imagen al cliente vía TecnoByteApp WhatsApp.
     * Usa el endpoint /api/messages/send con `mediaUrl` y `caption`.
     */
    /**
     * Procesa un evento message_status enviado por TecnoByteApp cuando
     * cambia el ACK de un mensaje (sent / delivered / read).
     */
    private function procesarStatusUpdate(array $data)
    {
        $mensajeExternoId = $data['mensaje']['id'] ?? null;
        $ack              = (int) ($data['mensaje']['ack'] ?? 0);

        if (!$mensajeExternoId) {
            return response()->json(['status' => 'ignored']);
        }

        try {
            $msg = \App\Models\MensajeWhatsapp::where('mensaje_externo_id', $mensajeExternoId)->first();
            if ($msg && $msg->ack < $ack) {
                $msg->update(['ack' => $ack]);
                // Broadcast el cambio para que el Chat en vivo actualice los ticks
                try {
                    broadcast(new \App\Events\MensajeWhatsappNuevo($msg->load('conversacion.cliente')));
                } catch (\Throwable $e) { /* no bloquear */ }
            }
        } catch (\Throwable $e) {
            Log::warning('⚠️ No se pudo actualizar ack de mensaje: ' . $e->getMessage());
        }

        return response()->json(['status' => 'ack_updated']);
    }

    /**
     * Descarga la imagen recibida de TecnoByteApp y la guarda en storage/public/imagenes-in.
     * Devuelve la URL pública local (o null si falla).
     */
    private function descargarYGuardarImagen(string $urlRemota): ?string
    {
        try {
            $resp = Http::withoutVerifying()->timeout(30)->get($urlRemota);
            if (!$resp->successful()) {
                Log::warning('🖼️ No se pudo descargar la imagen', ['url' => $urlRemota, 'status' => $resp->status()]);
                return null;
            }

            $bytes = $resp->body();
            if (strlen($bytes) < 50 || strlen($bytes) > 15 * 1024 * 1024) {
                Log::warning('🖼️ Imagen fuera de rango de tamaño', ['bytes' => strlen($bytes)]);
                return null;
            }

            $ext = strtolower(pathinfo(parse_url($urlRemota, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
                $ext = 'jpg';
            }

            $filename = 'imagenes-in/img_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $bytes);

            return rtrim(config('app.url'), '/') . \Illuminate\Support\Facades\Storage::url($filename);
        } catch (\Throwable $e) {
            Log::error('🖼️ Excepción descargando imagen: ' . $e->getMessage());
            return null;
        }
    }

    private function enviarImagenWhatsapp(string $from, string $imagenUrl, string $caption = '', $connectionId = null): bool
    {
        try {
            $payload = [
                'number'   => $from,
                'mediaUrl' => $imagenUrl,
                'caption'  => $caption,
                'body'     => $caption,   // por compat
            ];

            if (!empty($connectionId)) {
                $payload['whatsappId']   = (int) $connectionId;
                $payload['connectionId'] = (int) $connectionId;
            }

            Log::info('📷 ENVIANDO IMAGEN WHATSAPP', [
                'phone'  => $from,
                'imagen' => $imagenUrl,
            ]);

            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('❌ Token WhatsApp no disponible para imagen');
                return false;
            }

            $response = $this->postWhatsappSend($token, $payload);

            if ($response->successful()) {
                Log::info('✅ Imagen enviada', ['phone' => $from]);
                return true;
            }

            // Reintento con refresh de token si vence sesión
            if ($response->status() === 401) {
                $newToken = $this->refrescarTokenWhatsapp() ?: $this->loginWhatsapp(true);
                if ($newToken) {
                    $retry = $this->postWhatsappSend($newToken, $payload);
                    if ($retry->successful()) {
                        Log::info('✅ Imagen enviada (tras refresh)', ['phone' => $from]);
                        return true;
                    }
                }
            }

            Log::warning('⚠️ No se pudo enviar imagen', [
                'phone'  => $from,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('❌ Excepción enviando imagen WhatsApp', [
                'phone' => $from,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envía hasta N imágenes de productos respetando la configuración del bot.
     * Retorna cuántas se enviaron.
     */
    private function enviarImagenesProductos(string $from, array $productosCodigos, $connectionId = null): int
    {
        $config = \App\Models\ConfiguracionBot::actual();
        if (!$config->enviar_imagenes_productos) {
            return 0;
        }

        $max = $config->max_imagenes_por_mensaje ?: 3;
        $codigos = array_slice($productosCodigos, 0, $max);
        $enviadas = 0;

        foreach ($codigos as $codigo) {
            $producto = \App\Models\Producto::where('codigo', $codigo)
                ->orWhere('id', is_numeric($codigo) ? (int) $codigo : null)
                ->first();

            $url = $producto?->urlImagen();

            if (!$producto || empty($url)) {
                Log::info('⚠️ Producto sin imagen o no encontrado', ['codigo' => $codigo]);
                continue;
            }

            $caption = sprintf(
                "*%s*\n%s\n💵 $%s/%s",
                $producto->nombre,
                $producto->descripcion_corta ?? '',
                number_format((float) $producto->precio_base, 0, ',', '.'),
                $producto->unidad
            );

            if ($this->enviarImagenWhatsapp($from, $url, $caption, $connectionId)) {
                $enviadas++;
            }
        }

        return $enviadas;
    }

    private function obtenerTokenWhatsapp(): ?string
    {
        $cacheKey = app(\App\Services\WhatsappResolverService::class)->tokenCacheKey();
        return Cache::get($cacheKey) ?: $this->loginWhatsapp();
    }

    private function loginWhatsapp(bool $force = false): ?string
    {
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $cacheKey = $resolver->tokenCacheKey();

        if ($force) {
            Cache::forget($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        if (empty($cred['email']) || empty($cred['password'])) {
            Log::error('❌ Tenant sin credenciales WhatsApp configuradas', [
                'tenant_id' => app(\App\Services\TenantManager::class)->id(),
            ]);
            return null;
        }

        try {
            $endpointLogin = rtrim($cred['api_base_url'], '/') . '/auth/login';
            $response = Http::withoutVerifying()
                ->timeout(20)
                ->post($endpointLogin, [
                    'email'    => $cred['email'],
                    'password' => $cred['password'],
                ]);

            if ($response->failed()) {
                Log::error('❌ ERROR LOGIN WHATSAPP', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                $this->notificarFallaWhatsapp(
                    'ERROR LOGIN WHATSAPP',
                    'Falló el login contra la plataforma de WhatsApp.',
                    [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'force' => $force,
                    ]
                );

                return null;
            }

            $token = $response->json('token');

            if (!$token) {
                Log::error('❌ LOGIN WHATSAPP SIN TOKEN', [
                    'body' => $response->body(),
                ]);

                $this->notificarFallaWhatsapp(
                    'LOGIN WHATSAPP SIN TOKEN',
                    'El login de WhatsApp respondió sin token.',
                    [
                        'body' => $response->body(),
                        'force' => $force,
                    ]
                );

                return null;
            }

            Cache::put($cacheKey, $token, now()->addMinutes(20));

            Log::info('🔐 Token WhatsApp obtenido y cacheado', [
                'force' => $force,
            ]);

            return $token;
        } catch (\Throwable $e) {
            Log::error('❌ EXCEPCIÓN LOGIN WHATSAPP', [
                'error' => $e->getMessage(),
            ]);

            $this->notificarFallaWhatsapp(
                'EXCEPCIÓN LOGIN WHATSAPP',
                'Se produjo una excepción al iniciar sesión en la plataforma de WhatsApp.',
                [
                    'error' => $e->getMessage(),
                    'force' => $force,
                ]
            );

            return null;
        }
    }

    private function refrescarTokenWhatsapp(): ?string
    {
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $cacheKey = $resolver->tokenCacheKey();
        $token    = Cache::get($cacheKey);

        if (!$token) {
            Log::warning('⚠️ No hay token en cache para refrescar');
            return null;
        }

        try {
            $endpointRefresh = rtrim($cred['api_base_url'], '/') . '/auth/refresh_token';
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post($endpointRefresh);

            if ($response->failed()) {
                Log::warning('⚠️ ERROR REFRESH TOKEN WHATSAPP', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                $this->notificarFallaWhatsapp(
                    'ERROR REFRESH TOKEN WHATSAPP',
                    'Falló el refresh token de la plataforma de WhatsApp.',
                    [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]
                );

                Cache::forget($cacheKey);
                return null;
            }

            $newToken = $response->json('token');

            if (!$newToken) {
                Log::warning('⚠️ REFRESH TOKEN SIN TOKEN NUEVO', [
                    'body' => $response->body(),
                ]);

                $this->notificarFallaWhatsapp(
                    'REFRESH TOKEN SIN TOKEN NUEVO',
                    'El refresh token respondió sin token nuevo.',
                    [
                        'body' => $response->body(),
                    ]
                );

                Cache::forget($cacheKey);
                return null;
            }

            Cache::put($cacheKey, $newToken, now()->addMinutes(20));

            Log::info('🔄 Token WhatsApp refrescado correctamente');

            return $newToken;
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);

            Log::error('❌ EXCEPCIÓN REFRESH TOKEN WHATSAPP', [
                'error' => $e->getMessage(),
            ]);

            $this->notificarFallaWhatsapp(
                'EXCEPCIÓN REFRESH TOKEN WHATSAPP',
                'Se produjo una excepción refrescando el token de WhatsApp.',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return null;
        }
    }

    private function cancelarPedidoAutomaticamente(Pedido $pedido, string $name): string
    {
        try {
            if ($pedido->estado === 'cancelado') {
                return "Hola {$name} 😊\nEl pedido #{$pedido->id} ya se encuentra cancelado.";
            }

            $pedido->cambiarEstado(
                'cancelado',
                'Cancelación confirmada por el cliente desde WhatsApp.',
                'Pedido cancelado'
            );

            $pedido->load(['sede', 'detalles', 'historialEstados']);

            broadcast(new PedidoActualizado($pedido, 'cancelado'));

            Log::info('✅ PEDIDO CANCELADO AUTOMÁTICAMENTE', [
                'pedido_id' => $pedido->id,
                'estado' => $pedido->estado,
                'url_seguimiento' => $pedido->url_seguimiento,
            ]);

            return "Hola {$name} 😊\nTu pedido #{$pedido->id} fue cancelado correctamente ❌\n\nPuedes ver el detalle aquí:\n{$pedido->url_seguimiento}";
        } catch (\Throwable $e) {
            Log::error('❌ ERROR CANCELANDO PEDIDO', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);

            $this->notificarFallaWhatsapp(
                'ERROR CANCELANDO PEDIDO',
                'Ocurrió un error al cancelar automáticamente un pedido.',
                [
                    'pedido_id' => $pedido->id,
                    'error' => $e->getMessage(),
                ]
            );

            return "Hola {$name} 😊\nNo pude cancelar el pedido #{$pedido->id} en este momento.";
        }
    }

    private function esSesionExpiradaWhatsapp(?array $body, string $rawBody = ''): bool
    {
        $error = strtoupper((string) data_get($body, 'error', ''));

        if ($error === 'ERR_SESSION_EXPIRED') {
            return true;
        }

        return str_contains(strtoupper($rawBody), 'ERR_SESSION_EXPIRED');
    }

    /*
    |==========================================================================
    | HELPERS
    |==========================================================================
    */

    private function formatearRespuestaPedidoEspecifico(Pedido $pedido, string $name = 'Cliente'): string
    {
        $lineas = [
            "Hola {$name} 😊",
            "Tu pedido #{$pedido->id} está: *" . $this->traducirEstadoPedido($pedido->estado) . "*",
            "📅 Fecha: " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i'),
            "📍 Sede: " . ($pedido->sede->nombre ?? 'No especificada'),
        ];

        if (!empty($pedido->hora_entrega)) {
            $lineas[] = "🕒 Hora estimada: {$pedido->hora_entrega}";
        }

        if ($pedido->detalles && $pedido->detalles->count()) {
            $lineas[] = '';
            $lineas[] = "🛒 Detalle:";
            foreach ($pedido->detalles as $det) {
                $cant = $this->formatearCantidadPedido((float) $det->cantidad);
                $lineas[] = "• {$det->producto} — {$cant} {$det->unidad}";
            }
        }

        $lineas[] = '';
        $lineas[] = "💰 Total: $" . number_format((float) $pedido->total, 0, ',', '.');

        if (!empty($pedido->telefono_contacto)) {
            $lineas[] = "📞 Contacto: {$pedido->telefono_contacto}";
        }

        return implode("\n", $lineas);
    }

    private function formatearPedidoParaApi(Pedido $pedido): array
    {
        $pedido->loadMissing(['sede', 'detalles', 'historialEstados']);

        return [
            'id'                   => $pedido->id,
            'codigo_seguimiento'   => $pedido->codigo_seguimiento,
            'url_seguimiento'      => $pedido->url_seguimiento,
            'fecha'                => optional($pedido->fecha_pedido)?->format('d/m/Y H:i'),
            'estado'               => $pedido->estado,
            'hora_entrega'         => $pedido->hora_entrega ?? 'Por confirmar',
            'sede'                 => $pedido->sede->nombre ?? 'No especificada',
            'cliente'              => $pedido->cliente_nombre,
            'telefono_whatsapp'    => $pedido->telefono_whatsapp ?? $pedido->telefono,
            'telefono_contacto'    => $pedido->telefono_contacto ?? $pedido->telefono,
            'telefono'             => $pedido->telefono,
            'total'                => (float) $pedido->total,
            'total_formateado'     => number_format((float) $pedido->total, 0, ',', '.'),
            'notas'                => $pedido->notas,
            'resumen_conversacion' => $pedido->resumen_conversacion,
            'productos'            => $pedido->detalles->map(fn($d) => [
                'producto'        => $d->producto,
                'cantidad'        => $this->formatearCantidadPedido((float) $d->cantidad),
                'unidad'          => $d->unidad,
                'precio_unitario' => $d->precio_unitario,
                'subtotal'        => $d->subtotal,
            ])->values(),
            'historial' => $pedido->historialEstados->map(fn($h) => [
                'estado_anterior' => $h->estado_anterior,
                'estado_nuevo' => $h->estado_nuevo,
                'titulo' => $h->titulo,
                'descripcion' => $h->descripcion,
                'fecha_evento' => optional($h->fecha_evento)->format('d/m/Y H:i'),
            ])->values(),
        ];
    }
    private function extraerNumeroPedidoDesdeMensaje(string $message): ?int
    {
        $msg = mb_strtolower(trim($message));

        $patrones = [
            '/pedido\s*#\s*(\d+)/i',
            '/pedido\s+numero\s+(\d+)/i',
            '/pedido\s+número\s+(\d+)/i',
            '/pedido\s+(\d+)/i',
            '/orden\s*#\s*(\d+)/i',
            '/orden\s+numero\s+(\d+)/i',
            '/orden\s+número\s+(\d+)/i',
            '/orden\s+(\d+)/i',
            '/el\s+(\d+)/i',
            '/#\s*(\d+)/i',
            '/^(\d+)$/i',
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $msg, $matches)) {
                return isset($matches[1]) ? (int) $matches[1] : null;
            }
        }

        return null;
    }

    private function traducirEstadoPedido(?string $estado): string
    {
        return match ($estado) {
            'nuevo'          => 'Nuevo 🔔',
            'confirmado'     => 'Confirmado ✅',
            'en_proceso'     => 'En proceso 🍳',
            'en_preparacion' => 'En preparación 👨‍🍳',
            'despachado'     => 'Despachado 🛵',
            'listo'          => 'Listo para entrega 🚚',
            'entregado'      => 'Entregado 📦',
            'cancelado'      => 'Cancelado ❌',
            default          => ucfirst((string) $estado),
        };
    }

    private function normalizarTelefono(?string $telefono): string
    {
        return preg_replace('/\D+/', '', (string) $telefono);
    }

    private function obtenerTelefonoLocal(?string $telefono): string
    {
        $tel = $this->normalizarTelefono($telefono);
        return strlen($tel) > 10 ? substr($tel, -10) : $tel;
    }

    private function formatearCantidadPedido(float $cantidad): string
    {
        return fmod($cantidad, 1.0) == 0.0
            ? number_format($cantidad, 0, ',', '.')
            : number_format($cantidad, 2, ',', '.');
    }

    private function notificarFallaWhatsapp(
        string $tipo,
        string $mensaje,
        array $contexto = [],
        int $cooldownMinutes = 10
    ): void {
        // ── 1) Registrar en el panel de alertas del bot ──
        try {
            $tipoUpper = strtoupper($tipo);
            $esToken = str_contains($tipoUpper, 'TOKEN') || str_contains($tipoUpper, 'SESIÓN') || str_contains($tipoUpper, 'SESION');
            $esDesconectado = str_contains($tipoUpper, 'DESCONECTADO') || str_contains($tipoUpper, 'NO CONECTADO');

            if ($esToken) {
                $tipoAlerta = \App\Models\BotAlerta::TIPO_WHATSAPP_TOKEN;
                $severidad  = \App\Models\BotAlerta::SEV_CRITICA;
                $titulo     = '📱 Problema con el token de WhatsApp';
            } elseif ($esDesconectado) {
                $tipoAlerta = \App\Models\BotAlerta::TIPO_WHATSAPP_ENVIO;
                $severidad  = \App\Models\BotAlerta::SEV_CRITICA;
                $titulo     = '📤 WhatsApp desconectado';
            } else {
                $tipoAlerta = \App\Models\BotAlerta::TIPO_WHATSAPP_ENVIO;
                $severidad  = \App\Models\BotAlerta::SEV_WARNING;
                $titulo     = '📤 ' . ucfirst(strtolower($tipo));
            }

            $codigoHttp = isset($contexto['status']) && is_numeric($contexto['status'])
                ? (int) $contexto['status']
                : null;

            app(\App\Services\BotAlertaService::class)->registrar(
                $tipoAlerta,
                $titulo,
                $mensaje,
                $severidad,
                $codigoHttp,
                $contexto
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar BotAlerta desde notificarFallaWhatsapp: ' . $e->getMessage());
        }

        // ── 2) Enviar correo (comportamiento original) ──
        try {
            $destinatarios = collect(explode(',', (string) env('ALERTAS_TECNICAS_EMAILS', '')))
                ->map(fn($email) => trim($email))
                ->filter()
                ->values()
                ->all();

            if (empty($destinatarios)) {
                Log::warning('⚠️ No hay correos configurados para alertas técnicas.');
                return;
            }

            $cacheKey = 'alerta_tecnica_' . md5($tipo . '|' . ($contexto['connectionId'] ?? 'sin_conexion'));

            if (Cache::has($cacheKey)) {
                Log::info('📭 Alerta técnica omitida por cooldown', [
                    'tipo' => $tipo,
                    'cache_key' => $cacheKey,
                ]);
                return;
            }

            Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));

            $appNombre = env('APP_NOMBRE_ALERTAS', config('app.name', 'Plataforma'));
            $asunto = "[ALERTA] {$appNombre} - {$tipo}";

            $contenido = [];
            $contenido[] = "Se ha detectado una novedad en la plataforma de pedidos.";
            $contenido[] = "";
            $contenido[] = "Tipo de alerta: {$tipo}";
            $contenido[] = "Mensaje: {$mensaje}";
            $contenido[] = "Fecha: " . now()->format('d/m/Y H:i:s');
            $contenido[] = "Aplicación: {$appNombre}";
            $contenido[] = "";

            if (!empty($contexto)) {
                $contenido[] = "Contexto:";
                foreach ($contexto as $clave => $valor) {
                    if (is_array($valor) || is_object($valor)) {
                        $valor = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                    $contenido[] = "- {$clave}: {$valor}";
                }
                $contenido[] = "";
            }

            $contenido[] = "Por favor revisar la plataforma.";

            $body = implode("\n", $contenido);

            Mail::raw($body, function ($message) use ($destinatarios, $asunto) {
                $message->to($destinatarios)->subject($asunto);
            });

            Log::info('📧 Alerta técnica enviada por correo', [
                'tipo' => $tipo,
                'destinatarios' => $destinatarios,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ No se pudo enviar la alerta técnica por correo', [
                'tipo' => $tipo,
                'error' => $e->getMessage(),
                'contexto' => $contexto,
            ]);
        }
    }

    private function esWhatsappNoConectado(?array $body, string $rawBody = ''): bool
    {
        $error = strtoupper((string) data_get($body, 'error', ''));

        if ($error === 'ERR_WAPP_NOT_CONNECTED') {
            return true;
        }

        return str_contains(strtoupper($rawBody), 'ERR_WAPP_NOT_CONNECTED')
            || str_contains(strtoupper($rawBody), 'NOT_CONNECTED');
    }

    /**
     * Auto-resetea la conversación si:
     *  - El mensaje es un saludo simple ("hola", "buenas", "hey", etc).
     *  - La última actividad del cliente fue hace >20 minutos.
     *
     * Esto evita que el bot en producción responda con contexto viejo
     * cuando un cliente vuelve después de un rato. También limpia las
     * reglas de "rechazo de cobertura reciente" si están guardadas.
     */
    private function autoResetSiCorresponde(string $cacheKey, string $mensaje, string|int $tenantId, string $telefonoNorm): void
    {
        $msgLimpio = mb_strtolower(trim($mensaje));
        // Saludos típicos en español (cortos, sin contexto adicional)
        $patronesSaludo = [
            '/^(hola|holi|holaa|holaaaa|buenas|buenas tardes|buenas noches|buenos d[ií]as|hey|holaa+|qu[eé] m[aá]s|q m[aá]s|saludos|menor|hello|hi)[\s\.\!\,\?¿¡]*$/u',
        ];

        $esSaludo = false;
        foreach ($patronesSaludo as $patron) {
            if (preg_match($patron, $msgLimpio)) {
                $esSaludo = true;
                break;
            }
        }

        if (!$esSaludo) return;

        // ¿Cuándo fue el último mensaje? Si hay historial reciente, vemos su timestamp.
        $ultimoMsg = \App\Models\MensajeWhatsapp::query()
            ->whereHas('conversacion', fn ($q) => $q
                ->where('tenant_id', is_int($tenantId) ? $tenantId : null)
                ->where('telefono_normalizado', $telefonoNorm))
            ->orderByDesc('id')
            ->first();

        $minutosDesdeUltimo = $ultimoMsg
            ? now()->diffInMinutes($ultimoMsg->created_at)
            : 999;

        if ($minutosDesdeUltimo < 20) {
            // Conversación todavía fresca, no resetear
            return;
        }

        // Reset: borrar cache de historial + cache de rechazo cobertura
        Cache::forget($cacheKey);
        Cache::forget("wa_rechazo_cobertura_idx_t{$tenantId}_{$telefonoNorm}");

        \Illuminate\Support\Facades\Log::info('🔄 Auto-reset de conversación', [
            'telefono'        => $telefonoNorm,
            'mensaje'         => $msgLimpio,
            'minutos_silencio' => $minutosDesdeUltimo,
        ]);
    }

    /**
     * Resume el resultado de una tool a algo compacto para guardar en BD.
     * Evita persistir catalogos enteros — solo metadatos clave.
     */
    private function resumirResultadoTool(string $tool, $resultado): array
    {
        if (!is_array($resultado)) return ['raw' => (string) $resultado];

        return match ($tool) {
            'buscar_productos' => [
                'query'       => $resultado['query'] ?? null,
                'categoria'   => $resultado['categoria'] ?? null,
                'encontrados' => $resultado['encontrados'] ?? 0,
                'top'         => collect($resultado['productos'] ?? [])->take(3)->map(fn ($p) => [
                    'codigo' => $p['codigo'] ?? null,
                    'nombre' => $p['nombre'] ?? null,
                    'precio' => $p['precio'] ?? null,
                    'score'  => $p['_score'] ?? null,
                ])->all(),
            ],
            'listar_categorias' => [
                'total' => $resultado['total_categorias'] ?? 0,
                'top5'  => collect($resultado['categorias'] ?? [])->take(5)->map(fn ($c) => [
                    'categoria' => $c['categoria'] ?? null,
                    'cantidad'  => $c['cantidad'] ?? 0,
                ])->all(),
            ],
            'productos_de_categoria' => [
                'categoria'   => $resultado['categoria'] ?? null,
                'encontrados' => $resultado['encontrados'] ?? 0,
                'top'         => collect($resultado['productos'] ?? [])->take(3)->map(fn ($p) => [
                    'codigo' => $p['codigo'] ?? null,
                    'nombre' => $p['nombre'] ?? null,
                ])->all(),
            ],
            'info_producto' => [
                'encontrado' => $resultado['encontrado'] ?? false,
                'codigo'     => $resultado['producto']['codigo'] ?? null,
                'nombre'     => $resultado['producto']['nombre'] ?? null,
            ],
            'productos_destacados' => [
                'destacados'  => count($resultado['destacados'] ?? []),
                'promociones' => count($resultado['promociones'] ?? []),
            ],
            default => $resultado,
        };
    }
}
