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

        // 📸 profilePicUrl ahora viene en chat.profilePicUrl (cambio en EstradaHub
        // mayo 2026). Si está presente, lo guardamos en cache para que el job
        // de sincronización lo use directamente sin re-llamar al API.
        $waProfilePicUrl = $data['chat']['profilePicUrl'] ?? $data['profilePicUrl'] ?? null;
        if ($waProfilePicUrl && !$fromMe && $from) {
            $cacheKey = 'wa_profilepic_' . preg_replace('/\D+/', '', $from);
            Cache::put($cacheKey, $waProfilePicUrl, now()->addMinutes(30));
        }

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

        // 📊 Tracking de campañas: marcar destinatarios que respondieron.
        // Esto no bloquea ni afecta el flujo del bot — solo registra la métrica.
        try {
            $tenantActual = app(\App\Services\TenantManager::class)->current();
            app(\App\Services\CampanaRespuestaTracker::class)
                ->procesarMensajeEntrante($from, $tenantActual?->id);
        } catch (\Throwable $e) {
            Log::warning('No se pudo trackear respuesta de campaña: ' . $e->getMessage());
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

            $reply = $this->procesarMensaje($from, $name, $message, $connectionId, $messageId);

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

            // 🛡️ ANTI-LOOP: si esta misma respuesta ya se envió 2+ veces en los
            // ÚLTIMOS 10 MINUTOS (ventana de sesión activa), intercepta. Antes
            // contaba todos los mensajes históricos y disparaba con saludos
            // legítimos como "Hola" cuando el cliente vuelve días después.
            try {
                $telefonoNorm = $this->normalizarTelefono($from);
                $convLoop = \App\Models\ConversacionWhatsapp::where('telefono_normalizado', $telefonoNorm)
                    ->where('estado', \App\Models\ConversacionWhatsapp::ESTADO_ACTIVA)
                    ->orderByDesc('id')->first();
                if ($convLoop) {
                    $hashReply = md5(mb_substr(mb_strtolower(trim((string) $reply)), 0, 200));
                    $ventanaDesde = now()->subMinutes(10);
                    $ultimosBot = \App\Models\MensajeWhatsapp::where('conversacion_id', $convLoop->id)
                        ->where('rol', \App\Models\MensajeWhatsapp::ROL_ASSISTANT)
                        ->where('created_at', '>=', $ventanaDesde)
                        ->orderByDesc('id')
                        ->limit(3)
                        ->pluck('contenido');

                    // Saludos cortos (≤ 20 chars o solo "Hola") NO cuentan como loop.
                    $replyLimpio = trim(mb_strtolower((string) $reply));
                    $esSaludoBreve = mb_strlen($replyLimpio) <= 20
                        || preg_match('/^(hola|buenas|buenos d[ií]as|buenas tardes|buenas noches)/u', $replyLimpio);

                    if (!$esSaludoBreve) {
                        $repeticiones = 0;
                        foreach ($ultimosBot as $c) {
                            if (md5(mb_substr(mb_strtolower(trim((string) $c)), 0, 200)) === $hashReply) {
                                $repeticiones++;
                            }
                        }
                        if ($repeticiones >= 2) {
                            Log::warning('🔁 ANTI-LOOP: respuesta repetida 3 veces en 10min — sustituyendo', [
                                'conv_id'      => $convLoop->id,
                                'reply_hash'   => $hashReply,
                                'repeticiones' => $repeticiones,
                            ]);
                            $reply = "Disculpa, parece que hay algo que no estoy capturando bien. "
                                   . "¿Me puedes contar con tus palabras qué necesitas y lo retomamos? "
                                   . "Si prefieres, te paso con un asesor humano — solo escribe *asesor*.";
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Anti-loop chequeo falló: ' . $e->getMessage());
            }

            // 🛡️ ANTI-PROMESA-ROTA: si el bot dijo "déjame buscar X" o
            //    "voy a verificar" SIN llamar tool, ejecutar la tool faltante
            //    y reemplazar el reply ANTES de enviarlo. El cliente NO debe
            //    quedar esperando una promesa sin cumplir.
            if ($this->respuestaEsPromesaRota($reply, $toolMessages ?? [])) {
                Log::warning('🛡️ PROMESA ROTA detectada — auto-recuperando', [
                    'from'  => $from,
                    'reply' => mb_substr($reply, 0, 100),
                ]);
                try {
                    // 🛡️ Buscar la conversación por teléfono (scope local, no
                    // depende de variables externas)
                    $convPara = $convLoop ?? \App\Models\ConversacionWhatsapp::where('telefono_normalizado', $this->normalizarTelefono($from))
                        ->orderByDesc('id')->first();
                    if ($convPara) {
                        $replyRecuperado = $this->autoEjecutarToolDePromesa($reply, $message, $convPara, $connectionId, $from);
                        if ($replyRecuperado) {
                            $reply = $replyRecuperado;
                            Log::info('✅ Promesa rota recuperada', ['from' => $from]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('No se pudo recuperar promesa rota: ' . $e->getMessage());
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

    private function procesarMensaje(string $from, string $name, string $message, ?string $connectionId, ?string $messageId = null): string
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
            // 🔄 AUTO-REVERT del handoff: si el cliente retracta la razón
            // del handoff ANTES de que un operador humano haya respondido,
            // devolvemos el control al bot. Evita pedidos perdidos cuando
            // el cliente cambia de opinión tras la derivación automática.
            $retract = $this->clienteRetractaHandoff($convActiva, $message);

            // 🔄 AUTO-REACTIVACIÓN POR TIEMPO: si la conversación lleva más
            // de 2h en modo humano y nadie del equipo ha respondido, el bot
            // retoma para no perder al cliente. Mide tiempo desde la
            // derivación O desde el último mensaje del cliente.
            $reactivarPorAbandono = $this->handoffAbandonado($convActiva);

            if ($retract || $reactivarPorAbandono) {
                $convActiva->update([
                    'atendida_por_humano' => false,
                    'departamento_id'     => null,
                    'derivada_at'         => null,
                ]);
                Log::info('🔄 Bot retoma conversación', [
                    'phone'   => $from,
                    'conv_id' => $convActiva->id,
                    'motivo'  => $retract ? 'cliente_retracto' : 'handoff_abandonado',
                    'mensaje' => mb_substr($message, 0, 100),
                ]);
                // Continuar al flujo normal del bot (no retornar aquí)
            } else {
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
        }

        // NOTA: la derivación por keywords fue REMOVIDA — ahora es 100% decisión
        // de la IA a través de la tool `derivar_a_departamento`. Esto permite
        // que detecte enojo, frustración y matices que las keywords no capturan.

        // ── CAPA 0: Buffer + debounce — agrupar mensajes seguidos del mismo cliente ──
        // Si el cliente manda 3 mensajes en 4 segundos, esperamos a que termine de
        // escribir y respondemos UNA sola vez con todo el contexto.
        $config = \App\Models\ConfiguracionBot::actual();
        $mensajesYaPersistidos = false;

        // 🐕 Mensajes virtuales del watchdog SKIPEAN el buffer/debounce. El
        // watchdog ya garantizó que el cliente esperó ≥30s sin respuesta, no
        // tiene sentido agregar 5s más de espera ni arriesgar perder el msg
        // por debounce de un cliente que no está escribiendo otra cosa.
        $esMensajeVirtualWatchdog = is_string($messageId)
            && str_starts_with($messageId, 'watchdog_');

        if (!$esMensajeVirtualWatchdog
            && $config->agrupar_mensajes_activo
            && (int) $config->agrupar_mensajes_segundos > 0) {
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
        // 🧠 Memoria conversacional ampliada: 50 mensajes (~20k tokens).
        // Esto permite que el bot recuerde TODO lo que se ha hablado en la
        // conversación actual: productos mencionados, dirección, preferencias,
        // negociaciones, cambios de opinión, etc.
        $conversationHistory = $conversacion->fresh()->historialParaIA(50);

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

        $systemPrompt = $this->getSystemPrompt($pedidosInfo, $this->infoEmpresa(), $nombreParaPrompt, $ansInfo, $sedeId, $from);

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
                    $ansInfo,
                    $from
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

            // 🆕 DETECTAR NUEVO FLUJO TRAS PEDIDO CERRADO:
            // Usamos la fuente de verdad MÁS ROBUSTA: la conversación tiene pedido_id
            // o ha generado un pedido previamente. Si llega un saludo o mensaje
            // de inicio, asumimos nuevo pedido.
            $telNormLocal = $this->normalizarTelefono($from);
            $ultimoPedido = \App\Models\Pedido::where('telefono_whatsapp', $telNormLocal)
                ->whereNotIn('estado', [\App\Models\Pedido::ESTADO_CANCELADO])
                ->orderByDesc('id')
                ->first();

            $hayPedidoReciente = $ultimoPedido && $ultimoPedido->created_at >= now()->subDay();
            $minutosDesdePedido = $hayPedidoReciente
                ? abs((int) now()->diffInMinutes($ultimoPedido->created_at))
                : 9999;

            $esSaludoOInicioNuevo = preg_match(
                '/^(?:hola|ola|buen[ao]s?\s*(?:d[ií]as|tardes|noches)?|hey|hi|saludos|qu[eé]\s+tal|ey|holi|hola[!\.]*)\b/iu',
                trim($message)
            ) === 1 || $estadoSrv->detectarIntencionNuevoPedido($message);

            // Si hay pedido cerrado reciente (>=2 min) Y cliente saluda/pide otro:
            // resetear si no estaba ya en producto-vacío.
            $tieneProductosEnEstado = !empty($estadoVerif->productos);
            $debeResetear = $hayPedidoReciente
                && $minutosDesdePedido >= 2
                && $esSaludoOInicioNuevo
                && (
                    $estadoVerif->paso_actual === \App\Models\ConversacionPedidoEstado::PASO_CONFIRMADO
                    || $tieneProductosEnEstado
                );

            if ($debeResetear) {
                Log::info('🔁 Saludo/nuevo pedido tras pedido cerrado — reseteando (preservando cliente)', [
                    'from'             => $from,
                    'pedido_anterior'  => $ultimoPedido->id,
                    'minutos_desde'    => $minutosDesdePedido,
                    'paso_previo'      => $estadoVerif->paso_actual,
                    'mensaje'          => $message,
                ]);
                // 🛡️ Usar reiniciarParaNuevoPedido() que SIEMPRE preserva
                // cédula/nombre/email/teléfono del cliente. La función
                // antigua resetear() borraba esos datos a menos que el
                // motivo fuera 'nuevo_pedido_tras_*'.
                $estadoSrv->reiniciarParaNuevoPedido($conversacion);
            }

            // 🛡️ SIEMPRE que haya un pedido reciente (último 24h) y el estado esté
            // limpio (sin productos), inyectar al LLM la nota de que ese pedido
            // YA cerró. Así el LLM no arrastra el flujo anterior aunque tenga el
            // historial en cache.
            $estadoActualHist = $estadoSrv->obtener($conversacion);
            if ($hayPedidoReciente && empty($estadoActualHist->productos)) {
                $reinforceEstadoPedido[] = [
                    'role'    => 'system',
                    'content' => "🔄 CONTEXTO IMPORTANTE: el cliente YA cerró el pedido #{$ultimoPedido->id} "
                        . "(hace {$minutosDesdePedido} min, total \$" . number_format($ultimoPedido->total, 0, ',', '.') . "). "
                        . "Ese pedido ESTÁ TERMINADO. Si está volviendo a hablarte, es para un PEDIDO NUEVO O para PREGUNTAR algo. "
                        . "NO digas 'antes de cerrar tu pedido', 'método de entrega', 'dirección' u otros pasos "
                        . "del pedido anterior. Empieza fresh: salúdalo y pregúntale qué necesita esta vez.",
                ];
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
        // Forzar avanzar paso antes de filtrar tools — el estado puede haber
        // cambiado tras el captador determinista y debe reflejarse en el flujo.
        $estadoActualParaTools = null;
        try {
            $estadoActualParaTools = $estadoSrv->obtener($conversacion);
            $estadoSrv->avanzarPaso($estadoActualParaTools);
            $pasoActualOrch = $estadoActualParaTools->fresh()->paso_actual ?? $pasoActualOrch;
        } catch (\Throwable $e) { /* keep $pasoActualOrch */ }

        $orchestrator = app(\App\Services\FlujoPedidoOrchestrator::class);
        $toolsFiltradas = $orchestrator->filtrarTools(
            $this->getToolsDefinicion(),
            $pasoActualOrch,
            $estadoActualParaTools  // 🛡️ permite confirmar_pedido si estado completo
        );
        $toolChoicePorPaso = $orchestrator->toolChoice($pasoActualOrch);

        // 🛡️ BLOQUEO ANTI-DUPLICADOS: si el cliente ya tiene un pedido NO cancelado
        // creado en los últimos 30 min, REMOVER `confirmar_pedido` de las tools
        // disponibles. Esto previene que el LLM por inercia confirme dos veces
        // el mismo pedido cuando el cliente solo saluda después.
        if (isset($hayPedidoReciente) && $hayPedidoReciente && $minutosDesdePedido < 30) {
            $toolsFiltradas = array_values(array_filter(
                $toolsFiltradas,
                fn ($t) => ($t['function']['name'] ?? '') !== 'confirmar_pedido'
            ));
            Log::info('🛡️ confirmar_pedido REMOVIDO de tools (pedido reciente)', [
                'pedido_id'  => $ultimoPedido->id,
                'minutos'    => $minutosDesdePedido,
                'tools_left' => count($toolsFiltradas),
            ]);
        }

        // 🎯 SHORT-CIRCUITS según intención detectada en el mensaje:
        //   1. Pidió "generar pedido" → forzar confirmar_pedido
        //   2. Preguntó por cobertura de MUNICIPIO → forzar validar_cobertura
        //   3. Preguntó por producto → forzar buscar_productos
        //   4. Dió datos finales → forzar confirmar_pedido si estado completo, sino required
        $forzarConfirmar    = $this->clientePidioGenerarPedido($message);

        // 🛒 PRIMERO: ¿es pregunta de producto? Si sí, GANA sobre cobertura
        // (evita falsos positivos como "tienes basa" → no es lugar, es
        // producto, aunque "basa" parezca sustantivo propio).
        $preguntaProducto = !$forzarConfirmar && $this->clientePreguntaProducto($message);

        // Obtener estado actual del pedido ANTES de decisiones de orquestación
        $estadoActualBd     = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);

        // 🗺️ Detección dinámica de cobertura — SOLO si NO es pregunta de
        // producto. Si el mensaje menciona un LUGAR y el contexto sugiere
        // consulta de cobertura, FORZAR validar_cobertura.
        // 🛡️ SKIP si la cobertura YA fue validada exitosamente — evita re-forzar
        // cuando el cliente repite la ciudad en sus datos de envío.
        $coberturaYaOk = $estadoActualBd?->cobertura_validada ?? false;
        $lugarEnMsg = $preguntaProducto ? null : $this->extraerLugarDelMensaje($message);
        $contextoEsCobertura = !$preguntaProducto && !$coberturaYaOk && $lugarEnMsg && $this->contextoSugiereCobertura($conversacion, $message);

        // 🛡️ Caso especial: el bot acaba de pedir clarificación de ciudad
        // y el cliente está respondiendo. Si el cliente dice un lugar
        // CUALQUIERA (incluso sin frases típicas), DEBE disparar validación
        // — sino el LLM puede alucinar.
        // Pero SOLO si la cobertura no está ya validada.
        $respondiendoAClarificacionCiudad = !$preguntaProducto && !$coberturaYaOk && $lugarEnMsg && $this->botPidioClarificacionCiudad($conversacion);
        if ($respondiendoAClarificacionCiudad) {
            $contextoEsCobertura = true;
        }
        $estadoYaCompleto   = $estadoActualBd && $estadoActualBd->estaCompleto() && !$estadoActualBd->confirmado_at;
        $datosFinalesEnTexto= !$forzarConfirmar && !$contextoEsCobertura && !$preguntaProducto && $this->clienteDaDatosFinales($message);

        $toolChoiceInicial  = $toolChoicePorPaso;
        $razonForzado       = null;

        // 🎯 DETECCIÓN POR CONTEXTO (no por palabras hardcodeadas):
        // Si el último mensaje del bot pidió confirmación (contenía "¿Confirmas?" o
        // similar + Total + productos), entonces el LLM YA está esperando una
        // respuesta de confirmación. Le damos un nudge para que NO responda en
        // texto plano si la respuesta del cliente es afirmativa.
        $botPidioConfirmacion = $this->ultimoMensajeBotPidioConfirmacion($conversationHistory);
        if ($botPidioConfirmacion && !$forzarConfirmar && !$preguntaProducto) {
            $messages[] = [
                'role'    => 'system',
                'content' => "🎯 CONTEXTO CLAVE: en tu último mensaje le pediste al cliente que confirmara el pedido (mostraste resumen + ¿Confirmas?). "
                    . "La respuesta del cliente que viene es su decisión.\n\n"
                    . "Tú decides semánticamente qué quiso decir:\n"
                    . "  - Si entiendes COMO AFIRMATIVA (cualquier forma: 'sí', 'dale', 'listo', 'todo bien', 'oka', 'perfecto', 'super confirmado', '👍', 'hagale', etc.) → "
                    . "LLAMA `confirmar_pedido` AHORA con los datos del resumen que mostraste. NO respondas con texto plano.\n"
                    . "  - Si pide CAMBIO o tiene una NUEVA pregunta → ajusta el pedido y muestra el nuevo resumen.\n"
                    . "  - Si pide CANCELAR → confírmale que cancelaste, sin llamar tool.\n\n"
                    . "PROHIBIDO decir 'tu pedido quedó registrado' / 'va en camino' / 'queda listo' SIN llamar `confirmar_pedido`. "
                    . "Esa es la diferencia entre confirmar de verdad (con tool) e inventar (con texto, que el sistema bloqueará).",
            ];
            if ($toolChoiceInicial === 'auto' || $toolChoiceInicial === null) {
                $toolChoiceInicial = 'required'; // que invoque ALGUNA tool, no responda solo texto
                $razonForzado = 'bot_pidio_confirmacion_cliente_respondio';
            }
        }

        if ($forzarConfirmar) {
            $toolChoiceInicial = ['type' => 'function', 'function' => ['name' => 'confirmar_pedido']];
            $allTools = $this->getToolsDefinicion();
            $confirmarTool = collect($allTools)->first(fn ($t) => ($t['function']['name'] ?? '') === 'confirmar_pedido');
            if ($confirmarTool) $toolsFiltradas = [$confirmarTool];
            $razonForzado = 'cliente_pidio_generar_pedido';
            $messages[] = [
                'role' => 'system',
                'content' => "🚨 OBLIGATORIO: el cliente acaba de CONFIRMAR el pedido (dijo 'si confirmo' / 'dale' / 'listo' o similar). INVOCA `confirmar_pedido` AHORA.\n\n"
                    . "Si el estado estructurado NO tiene todos los datos, EXTRAE del historial de mensajes:\n"
                    . "  - **products**: los productos exactos que mostraste en el último RESUMEN al cliente (nombre EXACTO, cantidad, unidad).\n"
                    . "  - **address**: dirección que el cliente confirmó, o vacío si es pickup.\n"
                    . "  - **neighborhood**: barrio del cliente.\n"
                    . "  - **pickup**: true si dijo 'recoger en sede'.\n"
                    . "  - **customer_name**: nombre del cliente confirmado.\n"
                    . "  - **cedula**: cédula que el cliente dio.\n"
                    . "  - **email**: email si lo dio.\n\n"
                    . "PROHIBIDO responder con texto. PROHIBIDO decir 'tu pedido quedó registrado' sin llamar la tool. SOLO la tool.",
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
        } elseif ($contextoEsCobertura && $lugarEnMsg) {
            // ⭐ Mensaje menciona un lugar Y el contexto es cobertura/domicilio
            // → forzar validar_cobertura con ese lugar. Sin importar el fraseo.
            $toolChoiceInicial = ['type' => 'function', 'function' => ['name' => 'validar_cobertura']];
            $allTools = $this->getToolsDefinicion();
            $valTool = collect($allTools)->first(fn ($t) => ($t['function']['name'] ?? '') === 'validar_cobertura');
            if ($valTool) $toolsFiltradas = [$valTool];
            $razonForzado = 'lugar_mencionado_en_contexto_cobertura';
            $messages[] = [
                'role' => 'system',
                'content' => "🚨 El cliente mencionó el lugar '{$lugarEnMsg}' en un contexto de "
                    . "cobertura/domicilio. INVOCA `validar_cobertura(direccion='{$lugarEnMsg}', "
                    . "ciudad='{$lugarEnMsg}')` AHORA. Hace test punto-en-polígono real contra "
                    . "los polígonos dibujados. NO respondas texto antes. NO supongas que está/no "
                    . "está cubierto basado en mensajes anteriores.",
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
            Log::warning('🚨 LLM completamente caído — respuesta degradada al cliente', [
                'from' => $from,
            ]);
            // Mensaje más útil cuando el LLM está completamente caído
            return "Estamos teniendo un problema temporal con el sistema. 🙏 "
                 . "Inténtalo en 1 minuto, o escribe *asesor* si necesitas ayuda inmediata.";
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
                        'consultar_zonas_cobertura' => (function () use ($from, $message) {
                            $sedes = \App\Models\Sede::where('activa', true)->get();
                            $zonas = \App\Models\ZonaCobertura::where('activa', true)
                                ->orderBy('orden')->orderBy('nombre')->get();

                            // 🗺️ Dinámico: si el mensaje actual del cliente menciona un lugar
                            //    (ej "cubren Girardota?"), validamos AUTOMÁTICAMENTE ese lugar
                            //    contra los polígonos reales y devolvemos la respuesta lista.
                            //    Así el LLM no tiene que adivinar qué tool usar.
                            $lugarMencionado = $this->extraerLugarDelMensaje($message);
                            $validacionAutomatica = null;
                            if ($lugarMencionado !== null) {
                                try {
                                    $sedeIdAuto = $this->obtenerSedeIdDesdeConexion($connectionId ?? null);
                                    $valM = new \ReflectionMethod($this, 'validarCoberturaDireccion');
                                    $valM->setAccessible(true);
                                    $r = $valM->invoke($this, $lugarMencionado, '', $lugarMencionado, $sedeIdAuto);
                                    $validacionAutomatica = [
                                        'lugar_detectado' => $lugarMencionado,
                                        'cubierto'        => (bool) ($r['cubierta'] ?? false),
                                        'sede'            => $r['sede_sugerida'] ?? null,
                                        'distancia_km'    => $r['distancia_km'] ?? null,
                                        'costo_envio'     => $r['costo_envio'] ?? null,
                                        'tiempo_min'      => $r['tiempo_min'] ?? null,
                                        'mensaje_sugerido'=> $r['mensaje_sugerido'] ?? null,
                                    ];
                                } catch (\Throwable $e) {
                                    Log::warning('Validación auto en consultar_zonas_cobertura falló: ' . $e->getMessage());
                                }
                            }

                            $sedesPayload = $sedes->map(function ($s) use ($zonas) {
                                $zonasSede = $zonas->filter(fn ($z) => $z->sede_id === $s->id || $z->sede_id === null);

                                // 🗺️ Resumen de cobertura por POLÍGONOS reales (no solo legacy
                                //    ZonaCobertura). Si la sede tiene 2 polígonos en
                                //    cobertura_poligono, el bot debe SABERLO para no
                                //    decir "no cubrimos" cuando sí lo hace.
                                $tieneCoberturaPoligono = false;
                                $resumenPoligonos = [];
                                try {
                                    if ($s->cobertura_activa && $s->tieneCobertura()) {
                                        $polys = $s->poligonosNormalizados();
                                        $tieneCoberturaPoligono = count($polys) > 0;
                                        foreach ($polys as $idx => $poly) {
                                            $lats = array_column($poly, 0);
                                            $lngs = array_column($poly, 1);
                                            if (empty($lats)) continue;
                                            $resumenPoligonos[] = [
                                                'zona_num'  => $idx + 1,
                                                'vertices'  => count($poly),
                                                'centro'    => [
                                                    'lat' => round((min($lats) + max($lats)) / 2, 4),
                                                    'lng' => round((min($lngs) + max($lngs)) / 2, 4),
                                                ],
                                                'extension_aprox' => round(max(max($lats) - min($lats), max($lngs) - min($lngs)) * 111, 1) . ' km',
                                            ];
                                        }
                                    }
                                } catch (\Throwable $e) { /* ignore */ }

                                return [
                                    'sede'   => $s->nombre,
                                    'direccion' => $s->direccion,
                                    // 💰 Datos de cobertura de la SEDE (defaults para todas sus zonas)
                                    'pedido_minimo_sede'    => (float) ($s->cobertura_pedido_minimo ?? 0),
                                    'costo_envio_default_sede' => (float) ($s->cobertura_costo_envio ?? 0),
                                    'tiempo_default_sede_min'  => (int) ($s->cobertura_tiempo_min ?? 0),
                                    // 🗺️ Polígonos REALES dibujados en el editor de cobertura
                                    'tiene_poligonos_cobertura' => $tieneCoberturaPoligono,
                                    'poligonos_resumen' => $resumenPoligonos,
                                    // 📍 Zonas legacy (tabla zonas_cobertura por barrios)
                                    'zonas'  => $zonasSede->map(fn ($z) => [
                                        'nombre'  => $z->nombre,
                                        'tiempo_min' => $z->tiempo_estimado_min,
                                        'costo_envio' => (float) ($z->costo_envio ?? 0),
                                        'global' => $z->sede_id === null,
                                    ])->values()->all(),
                                ];
                            })->values()->all();

                            $resp = [
                                'sedes' => $sedesPayload,
                                'instruccion_para_bot' =>
                                    "Esta tool muestra costos/tiempos/mínimos de envío POR SEDE y "
                                    . "barrios con tarifa especial (campo `zonas`).\n\n"
                                    . "🛑 Para '¿cubren X municipio?' / '¿llegan a Y?' siempre usa "
                                    . "`validar_cobertura(direccion='X', ciudad='X')` — esa hace el "
                                    . "test punto-en-polígono real.\n\n"
                                    . "PROHIBIDO concluir 'no cubrimos X' solo porque `zonas` esté vacío. "
                                    . "Si `tiene_poligonos_cobertura=true`, esa sede tiene cobertura "
                                    . "dibujada en el mapa — valida con `validar_cobertura`.",
                            ];

                            // Si detectamos un lugar en el mensaje y lo validamos automáticamente,
                            // incluimos el resultado para que el LLM lo use directamente sin
                            // tener que hacer otra llamada.
                            if ($validacionAutomatica) {
                                $resp['validacion_automatica'] = $validacionAutomatica;
                                $resp['nota_validacion'] = $validacionAutomatica['cubierto']
                                    ? "✓ DETECTAMOS que el cliente preguntó por '{$validacionAutomatica['lugar_detectado']}' "
                                      . "y SÍ está cubierto. Usa estos datos en tu respuesta. NO llames validar_cobertura otra vez."
                                    : "✗ DETECTAMOS que el cliente preguntó por '{$validacionAutomatica['lugar_detectado']}' "
                                      . "y NO está cubierto. Sugiere recoger en sede u otra dirección. NO inventes cobertura.";
                            }

                            return $resp;
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

        // ── 🛒 Tool call: agregar_producto_al_pedido ───────────────────────────
        // Persiste un producto en estado.productos con validación de catálogo
        // y conversión de unidades (libra → kg). Devuelve carrito actualizado.
        // 🛡️ BUG-PARALLEL: procesar TODOS los tool_calls de agregar_producto_al_pedido,
        // no solo el primero. El cliente puede pedir "X y Y" y el LLM emite 2 calls.
        $callsAgregar = $toolCalls
            ? array_filter($toolCalls, fn ($tc) => ($tc['function']['name'] ?? '') === 'agregar_producto_al_pedido')
            : [];
        if (!empty($callsAgregar)) {
            $resultados = [];
            $toolResultBlocks = [];
            $ultimoResultado = null;

            foreach ($callsAgregar as $tc) {
                $rawArgs = $tc['function']['arguments'] ?? '{}';
                $args    = json_decode($rawArgs, true) ?: [];

                $action   = strtolower(trim((string) ($args['action'] ?? 'add')));
                $name     = trim((string) ($args['name'] ?? ''));
                $code     = trim((string) ($args['code'] ?? ''));
                $quantity = (float) ($args['quantity'] ?? 0);
                $unitRaw  = strtolower(trim((string) ($args['unit'] ?? '')));
                $corte    = trim((string) ($args['corte'] ?? '')); // ✂️ Corte solicitado por el cliente

                Log::info('🛒 Tool call agregar_producto_al_pedido', compact('from', 'action', 'name', 'quantity', 'unitRaw', 'code', 'corte'));

                $resultado = $this->procesarAgregarProductoAlPedido(
                    $conversacion,
                    $action,
                    $name,
                    $code,
                    $quantity,
                    $unitRaw,
                    $connectionId,
                    $corte
                );
                $resultados[] = $resultado;
                $ultimoResultado = $resultado;

                $toolResultBlocks[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'] ?? ('call_' . count($toolResultBlocks)),
                    'name'         => 'agregar_producto_al_pedido',
                    'content'      => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                ];
            }

            // El assistant_turn debe incluir SOLO los tool_calls que respondimos
            // (para evitar tool_use huérfanos si hubieran otros tipos mezclados,
            // los demás branches no llegarían aquí porque chequean toolCalls[0]).
            $toolResponseMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $conversationHistory,
                [[
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => array_values($callsAgregar),
                ]],
                $toolResultBlocks
            );

            $followUp = $this->llamarOpenAI($toolResponseMessages);
            $reply    = $followUp['choices'][0]['message']['content']
                ?? ($ultimoResultado['mensaje_sugerido'] ?? 'Listo, agregado a tu pedido ✅');

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => [
                    'tool'        => 'agregar_producto_al_pedido',
                    'count_calls' => count($callsAgregar),
                    'resultados'  => $resultados,
                ],
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

            $direccion = $this->sanitizarPlaceholderLLM((string) ($args['direccion'] ?? ''));
            $barrio    = $this->sanitizarPlaceholderLLM((string) ($args['barrio'] ?? ''));
            $ciudad    = $this->sanitizarPlaceholderLLM((string) ($args['ciudad'] ?? 'Bello'));
            if ($ciudad === '') $ciudad = 'Bello';

            Log::info('🗺️ Tool call validar_cobertura', compact('from', 'direccion', 'barrio', 'ciudad'));

            // 🛡️ Si después de sanitizar la dirección queda vacía, NO ejecutar
            // la validación — el LLM mandó <UNKNOWN> o similar. Pedir clarificación.
            if ($direccion === '') {
                Log::warning('🛡️ validar_cobertura recibió direccion vacía/placeholder — pidiendo al cliente', [
                    'from' => $from,
                    'args_originales' => $args,
                ]);
                $reply = "Necesito que me digas tu dirección exacta (calle, número y barrio) para validar si te llega el domicilio 🏠";
                $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply);
                return $reply;
            }

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
            // 🛡️ Si el LLM hizo MÚLTIPLES tool_calls en este turno, debemos agregar
            // un tool message por CADA uno, no solo el primero — si no, Anthropic
            // rechaza con 400 "tool_use sin tool_result inmediato".
            $toolReplies = [];
            foreach ($toolCalls as $idx => $tc) {
                $nombreTool = $tc['function']['name'] ?? 'tool';
                if ($idx === 0 && $nombreTool === 'verificar_cliente_erp') {
                    $contentTool = json_encode($resultado, JSON_UNESCAPED_UNICODE);
                } else {
                    // Tool no manejada por este branch — placeholder para mantener el contrato
                    $contentTool = json_encode([
                        'omitido' => true,
                        'razon'   => 'Esta tool no se procesó en este turno. Llámala de nuevo si la necesitas.',
                    ], JSON_UNESCAPED_UNICODE);
                }
                $toolReplies[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'] ?? ('call_' . $idx),
                    'name'         => $nombreTool,
                    'content'      => $contentTool,
                ];
            }

            $toolResponseMessages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $conversationHistory,
                [[
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => $toolCalls,
                ]],
                $toolReplies
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

            // 🛡️ GUARD DETERMINISTA: rechazar derivaciones por preguntas de preparación/variante.
            // El LLM tiende a derivar cuando el cliente pregunta cosas como "me lo pueden picar?",
            // "me lo aliñan?", "al estilo guiso?", "deshuesarlo?". Esas son consultas SIMPLES
            // que el bot debe responder con honestidad — NO son casos para humano.
            $razonLower = mb_strtolower(\Illuminate\Support\Str::ascii($razon));
            $msgLower   = mb_strtolower(\Illuminate\Support\Str::ascii($message));
            $patronesPreparacion = [
                'preparacion', 'preparar especial', 'preparacion especial',
                'picar', 'pican', 'pique', 'molerlo', 'molerla', 'moler ', 'mole ',
                'alinarlo', 'alinarla', 'alinear', 'sazon',
                'deshuesarlo', 'deshuesarla', 'deshuesar',
                'porcionar', 'fileterar', 'cortar especial',
                'al estilo', 'estilo guiso', 'estilo sancocho', 'estilo asado',
                'apanarlo', 'apanarla', 'apanar',
                'marinarlo', 'marinarla', 'marinar',
                // Casos genéricos: cliente pregunta si pueden hacer algo extra al producto
                'variante', 'preparacion del', 'preparacion de la',
            ];
            $esConsultaPreparacion = false;
            foreach ($patronesPreparacion as $p) {
                if (str_contains($razonLower, $p) || str_contains($msgLower, $p)) {
                    $esConsultaPreparacion = true; break;
                }
            }

            if ($esConsultaPreparacion) {
                Log::warning('🛡️ DERIVACIÓN BLOQUEADA — es consulta de preparación, NO caso para humano', [
                    'from'   => $from,
                    'razon'  => $razon,
                    'mensaje'=> mb_substr($message, 0, 100),
                ]);

                // 🎯 ESTRATEGIA INTELIGENTE: buscar corte que mapee con la pregunta del cliente.
                // Si el cliente dice "guiso" y existe corte "Goulash" (descripción: "cubos para guiso"),
                // ofrecérselo. Convierte una pregunta que iba a derivar a humano en una venta.
                $reply = $this->buscarCorteRelacionado($msgLower);
                if (!$reply) {
                    // No hay corte que aplique → respuesta honesta + listar cortes generales
                    $reply = $this->respuestaCortesGenericos();
                }

                $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                    'tipo' => 'guard_derivacion_bloqueada',
                    'meta' => ['razon_llm' => $razon, 'patron_detectado' => true],
                ]);
                return $reply;
            }

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

            // 🛡️ GUARD: si el ÚLTIMO mensaje del cliente menciona un producto
            // que NO está en el carrito, BLOQUEAR la confirmación. El bot
            // probablemente alucinó "agregué X" sin llamar la tool.
            try {
                $ultMsgUser = \App\Models\MensajeWhatsapp::where('conversacion_id', $conversacion?->id)
                    ->where('rol', 'user')
                    ->orderByDesc('id')
                    ->limit(1)
                    ->value('contenido');

                if ($ultMsgUser && !empty($estadoBd->productos)) {
                    $productosEnCarrito = collect($estadoBd->productos)->map(fn($p) =>
                        mb_strtolower((string) ($p['name'] ?? ''))
                    )->all();

                    $msgN = mb_strtolower(\Illuminate\Support\Str::ascii($ultMsgUser));
                    $palabrasProducto = [
                        'pierna','solomito','costilla','muslo','pollo','cerdo','res','milanesa',
                        'pechuga','chuleta','posta','punta','lomo','bagre','basa','tilapia',
                        'cañon','tocino','espinazo','tripa','hueso','pescado','carne','filete',
                    ];

                    $mencionadosNoEnCarrito = [];
                    foreach ($palabrasProducto as $pp) {
                        if (preg_match('/\b' . preg_quote($pp, '/') . '/iu', $msgN)) {
                            $estaEnCarrito = false;
                            foreach ($productosEnCarrito as $enCarrito) {
                                if (str_contains($enCarrito, $pp)) { $estaEnCarrito = true; break; }
                            }
                            if (!$estaEnCarrito) $mencionadosNoEnCarrito[] = $pp;
                        }
                    }

                    if (!empty($mencionadosNoEnCarrito) && !$this->mensajeEsConfirmacionPura($ultMsgUser)) {
                        Log::warning('🚨 GUARD: cliente mencionó productos NO agregados al carrito — bloqueando confirmación', [
                            'from' => $from,
                            'productos_mencionados' => $mencionadosNoEnCarrito,
                            'productos_en_carrito' => $productosEnCarrito,
                        ]);
                        // Responder pidiendo agregar los productos faltantes ANTES de confirmar
                        $lista = implode(', ', $mencionadosNoEnCarrito);
                        return "Espera, mencionaste *{$lista}* pero no los veo en tu carrito todavía. "
                             . "¿Cuántos de cada uno quieres? Te los agrego y luego confirmamos. 🙏";
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Guard productos faltantes falló: ' . $e->getMessage());
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

        // 🛡️ FIX: cuando el LLM responde sin texto (solo tool_calls que no
        // procesamos o respuesta vacía), antes caíamos a un mensaje genérico
        // "no logré procesar". Eso es mala UX. Ahora hacemos un retry forzando
        // texto, y si aún así falla, damos un mensaje contextual basado en el
        // último mensaje del cliente.
        $reply = $textContent;
        if (empty(trim((string) $reply))) {
            Log::warning('🛡️ LLM respondió sin texto — retry forzando respuesta', [
                'from'           => $from,
                'tenia_toolcalls'=> !empty($toolCalls),
                'ultimo_msg'     => mb_substr($message, 0, 100),
            ]);

            try {
                // Retry SIN tools, forzando respuesta de texto
                $retryMessages = array_merge(
                    [['role' => 'system', 'content' => $systemPrompt
                        . "\n\n⚠️ INSTRUCCIÓN URGENTE: en este turn responde SOLO con texto al cliente. NO llames ninguna tool. "
                        . "Responde de forma natural y útil al último mensaje del cliente. Máximo 2-3 líneas."]],
                    $conversationHistory
                );
                $retryResponse = $this->llamarOpenAI($retryMessages);
                $retryText = $retryResponse['choices'][0]['message']['content'] ?? null;
                if (!empty(trim((string) $retryText))) {
                    $reply = $retryText;
                }
            } catch (\Throwable $e) {
                Log::warning('Retry sin tools también falló: ' . $e->getMessage());
            }

            // Fallback contextual si el retry tampoco devolvió texto
            if (empty(trim((string) $reply))) {
                $msgLower = mb_strtolower(trim($message));
                if (preg_match('/\b(domicilio|despacho|envi[oa]|env[íi]ame|env[íi]en)\b/iu', $msgLower)) {
                    $reply = "Perfecto, te lo enviamos a domicilio. ¿Qué productos te gustaría pedir y a qué dirección? 😊";
                } elseif (preg_match('/\b(recoger|recojo|recoge|paso|pasa[rs])\b/iu', $msgLower)) {
                    $reply = "Listo, vienes por él. ¿Qué productos quieres? Cuéntame y te lo dejo listo. 👍";
                } elseif (preg_match('/\b(hola|buenas|buenos|saludos|qu[eé]\s*tal)\b/iu', $msgLower)) {
                    $reply = "¡Hola! 😊 ¿En qué te puedo ayudar hoy?";
                } else {
                    $reply = "Te escucho. ¿Me cuentas qué necesitas pedir? Carnes, pollo, cerdo o pescado. 🥩";
                }
            }
        }

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

        // 🛡️ Guard: si el bot dijo "agregué N kilos de X" pero NO llamó la tool,
        // capturar el producto automáticamente para no perderlo.
        try {
            $this->capturarAgregadosImplicitos($conversacion, $reply, $connectionId);
        } catch (\Throwable $e) {
            Log::warning('Guard capturar agregados implícitos falló: ' . $e->getMessage());
        }

        // 🛡️ BUG-C2: Guard contra alucinación de carrito.
        // Detecta cuando el bot dice "quitado/agregado" pero el carrito real NO refleja.
        // Reemplaza la respuesta por una clarificación honesta.
        try {
            $estadoCarrito = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
            $alucinacion = $this->detectarAlucinacionCarrito($reply, $estadoCarrito);

            if ($alucinacion === 'QUITAR_FALSO_VACIO') {
                Log::warning('🛡️ BUG-C2: bot afirmó quitar del carrito pero carrito está vacío', [
                    'from'  => $from,
                    'reply' => mb_substr($reply, 0, 200),
                ]);
                $replyHonesto = "Disculpa, en realidad no tienes productos en tu carrito todavía. ¿Qué te gustaría pedir? 😊";

                // Reemplazar la respuesta alucinada
                array_pop($conversationHistory); // quitar respuesta alucinada
                $conversationHistory[] = ['role' => 'assistant', 'content' => $replyHonesto];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

                return $replyHonesto;
            }

            if ($alucinacion === 'AGREGAR_FALSO') {
                // capturarAgregadosImplicitos ya intentó. Si después de eso el carrito sigue vacío,
                // refrescamos el estado y verificamos.
                $estadoPost = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion->fresh());
                if (empty($estadoPost->productos)) {
                    Log::warning('🛡️ BUG-C2: bot afirmó agregar al carrito pero no se capturó', [
                        'from'  => $from,
                        'reply' => mb_substr($reply, 0, 200),
                    ]);
                    $replyHonesto = "Disculpa, ¿podrías confirmarme qué producto y qué cantidad quieres? Necesito el detalle exacto para agregarlo correctamente al pedido. 🙏";

                    array_pop($conversationHistory);
                    $conversationHistory[] = ['role' => 'assistant', 'content' => $replyHonesto];
                    Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

                    return $replyHonesto;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Guard alucinación carrito falló: ' . $e->getMessage());
        }

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

            // 🛡️ Si el motivo es "sin_intencion_de_pedido", el cliente NO expresó
            // querer pedir nada en sus últimos mensajes. NO se debe forzar la
            // creación de un pedido fantasma con datos del historial viejo.
            // Antes este caso caía al "OVERRIDE TOTAL" de abajo y creaba pedido
            // duplicado por inercia. AHORA cortamos aquí con un saludo amigable.
            if (($cierreResult['razon'] ?? '') === 'sin_intencion_de_pedido') {
                $primerNombre = trim(explode(' ', (string) $name)[0] ?? '');
                $replySafe = $primerNombre
                    ? "¡Hola {$primerNombre}! 😊 ¿En qué te ayudo hoy?"
                    : "¡Hola! 😊 ¿En qué te ayudo hoy?";
                $conversationHistory[] = ['role' => 'assistant', 'content' => $replySafe];
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $replySafe);
                Log::info('🛡️ BotCierre: sin intención de pedido — saludo seguro sin forzar', [
                    'from' => $from,
                ]);
                return $replySafe;
            }
        } catch (\Throwable $e) {
            Log::error('🤖 BotCierre lanzó excepción: ' . $e->getMessage(), ['from' => $from]);
        }

        // ════════════════════════════════════════════════════════════════════
        // 🛡️ POLÍTICA SEGURA — NO crear pedidos fantasma
        // ════════════════════════════════════════════════════════════════════
        // Si llegamos aquí significa:
        //   - El bot dijo una frase tipo "tu pedido está confirmado"
        //   - BotCierre falló o no aplicó (ya_confirmado, estado_incompleto,
        //     sin_intencion_de_pedido). Cada caso retornó arriba con su reply.
        //   - Por excepción inesperada se cayó al catch.
        //
        // ANTES había un "OVERRIDE TOTAL" que forzaba confirmar_pedido aquí
        // con datos del HISTORIAL viejo — creaba pedidos DUPLICADOS por
        // inercia cuando un cliente solo saludaba tras un pedido anterior.
        //
        // Ahora respondemos mensaje neutral seguro y registramos alerta. El
        // operador podrá retomar manualmente si hace falta.
        // ════════════════════════════════════════════════════════════════════
        try {
            app(\App\Services\BotAlertaService::class)->registrar(
                \App\Models\BotAlerta::TIPO_OTRO,
                '🤥 Bot dijo que confirmó un pedido sin hacerlo',
                "El bot respondió \"{$frase}\" al cliente {$from} en contexto {$contextoTool} "
                    . "pero el flujo determinista no detectó intención de pedido válida. "
                    . "Posible alucinación por inercia del historial. "
                    . "Conversación id={$conversacion->id} — revisa /chat si el cliente necesita ayuda manual.",
                \App\Models\BotAlerta::SEV_WARNING,
                null,
                ['from' => $from, 'frase' => $frase, 'reply' => mb_substr($reply, 0, 500), 'conversacion_id' => $conversacion->id]
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar alerta: ' . $e->getMessage());
        }

        // Respuesta segura: pedir al cliente que reformule, NO crear pedido.
        $primerNombre = trim(explode(' ', (string) $name)[0] ?? '');
        $replySafe = $primerNombre
            ? "Disculpa {$primerNombre}, no logré procesar correctamente. ¿Me cuentas con tus palabras qué necesitas?"
            : "Disculpa, no logré procesar correctamente. ¿Me cuentas con tus palabras qué necesitas?";

        $conversationHistory[] = ['role' => 'assistant', 'content' => $replySafe];
        Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
        $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $replySafe);

        Log::info('🛡️ Guard alucinación: respuesta segura sin crear pedido', [
            'from'     => $from,
            'frase'    => $frase,
            'contexto' => $contextoTool,
        ]);

        return $replySafe;
    }

    /**
     * Detecta si el cliente pidió explícitamente "generar / confirmar el
     * pedido". En ese caso forzamos tool_choice a confirmar_pedido para
     * cortar el ciclo de validar_cobertura → texto → validar_cobertura.
     */
    /**
     * 🔍 Detecta si en el último mensaje del bot (assistant) se le pidió al
     * cliente que confirmara el pedido. Indicios típicos:
     *   - Mostró un resumen con "Total:" o "💰" o "$X"
     *   - Pidió "¿Confirmas?", "¿correcto?", "¿está bien?"
     *
     * Usado para reforzar al LLM que la siguiente respuesta del cliente es una
     * decisión de confirmación (no necesita lista hardcodeada de palabras).
     */
    private function ultimoMensajeBotPidioConfirmacion(array $conversationHistory): bool
    {
        for ($i = count($conversationHistory) - 1; $i >= 0; $i--) {
            $msg = $conversationHistory[$i] ?? null;
            if (!is_array($msg)) continue;
            if (($msg['role'] ?? '') !== 'assistant') continue;

            $content = is_string($msg['content'] ?? null) ? $msg['content'] : '';
            if ($content === '') return false;

            $lower = mb_strtolower($content);

            // Señal 1: pregunta de confirmación
            $tienePregunta = preg_match(
                '/(?:¿\s*confirm(?:as|amos|o)\s*\??|confirmamos\?|esta\s+bien\??|esta\s+correcto\??|todo\s+(?:bien|correcto)\??|estamos\??|listo\s+as[ií]\??|de\s+acuerdo\??)/iu',
                $lower
            ) === 1;

            // Señal 2: incluye un resumen con total o precio
            $tieneResumen = preg_match('/\b(?:total|subtotal)\s*:?\s*\$?\s*[\d.,]+/iu', $lower) === 1
                || preg_match('/💰|📋|resumen/u', $content) === 1;

            return $tienePregunta && $tieneResumen;
        }
        return false;
    }

    private function clientePidioGenerarPedido(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));
        // Normalizar: quitar tildes y signos de puntuación al final
        $m = strtr($m, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        $m = trim($m, ".!¡?¿,; ");
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

        // 🛡️ RED DE SEGURIDAD MÍNIMA: solo frases que SIEMPRE significan
        // "registra el pedido ya". La detección semántica principal la hace
        // el LLM con el system message inyectado cuando bot pidió confirmación.
        $cortasInequivocas = [
            'confirmo el pedido', 'confirma el pedido',
            'haz el pedido', 'has el pedido',
            'registra el pedido', 'genera el pedido',
        ];
        foreach ($cortasInequivocas as $c) {
            if (str_contains($m, $c)) return true;
        }

        return false;
    }

    /**
     * Detecta si el cliente está preguntando o pidiendo un producto.
     * Si retorna true, forzamos tool_choice a buscar_productos para que el
     * LLM no invente "sí tengo X" sin verificar BD.
     */
    /**
     * 🛡️ ¿El bot acaba de pedir clarificación de ciudad/municipio?
     * Si sí, cualquier mensaje del cliente con un lugar debe disparar
     * validar_cobertura — no podemos dejar que el LLM alucine.
     */
    private function botPidioClarificacionCiudad($conversacion): bool
    {
        if (!$conversacion) return false;
        try {
            $ultBot = \App\Models\MensajeWhatsapp::where('conversacion_id', $conversacion->id)
                ->where('rol', 'assistant')
                ->orderByDesc('id')
                ->limit(1)
                ->value('contenido');
            if (!$ultBot) return false;

            $n = mb_strtolower(\Illuminate\Support\Str::ascii($ultBot));
            // Patrones típicos del mensaje de clarificación
            $patrones = [
                'municipio o barrio',
                'en qué municipio',
                'en que municipio',
                'en qué barrio',
                'en que barrio',
                'necesito el municipio',
                'cual es el municipio',
                'cuál es el municipio',
                'en que ciudad',
                'en qué ciudad',
            ];
            foreach ($patrones as $p) {
                if (str_contains($n, $p)) return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 🗺️ ¿El contexto de la conversación sugiere que el cliente está
     * preguntando por cobertura/domicilio?
     *
     * True si:
     *   - El mensaje actual contiene palabras de domicilio/envío/cobertura, O
     *   - El último mensaje del bot habló de cobertura/zona/despacho/envío
     *     (continuación natural — ej cliente dice "y a Girardota?" tras
     *      bot diciendo "Sabaneta no está cubierto")
     */
    private function contextoSugiereCobertura($conversacion, string $mensajeActual): bool
    {
        $m = mb_strtolower(\Illuminate\Support\Str::ascii(trim($mensajeActual)));
        if ($m === '') return false;

        // 1) El mensaje actual menciona palabras de cobertura/envío
        $palabrasCobertura = [
            'domicilio', 'domicilios', 'env[ií]o', 'env[ií]os', 'env[ií]a', 'env[ií]an',
            'env[ií]ar', 'cobertura', 'reparto', 'reparten', 'despacho', 'despachan',
            'llegan', 'llegas', 'lleva', 'llevan', 'manda', 'mandan', 'cubren', 'cubre',
            'cubris', 'cubrir', 'zona', 'tiene', 'tienen', 'hay',
        ];
        foreach ($palabrasCobertura as $p) {
            if (preg_match('/\b' . $p . '\b/iu', $m)) return true;
        }

        // 2) El último mensaje del bot habló de cobertura/zona/despacho
        try {
            $ultBot = \App\Models\MensajeWhatsapp::where('conversacion_id', $conversacion->id ?? 0)
                ->where('rol', 'assistant')
                ->orderByDesc('id')
                ->limit(1)
                ->value('contenido');
            if ($ultBot) {
                $ultN = mb_strtolower(\Illuminate\Support\Str::ascii($ultBot));
                $palabrasBotCobertura = [
                    'cubierto', 'cubierta', 'cubrimos', 'cobertura', 'zona',
                    'despacho', 'despachamos', 'despachar', 'env[ií]o', 'env[ií]a',
                    'reparto', 'domicilio', 'fuera de cobertura', 'fuera de zona',
                    'recoger en sede', 'recoger en la sede',
                ];
                foreach ($palabrasBotCobertura as $p) {
                    if (preg_match('/\b' . $p . '\b/iu', $ultN)) return true;
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        return false;
    }

    /**
     * 🛡️ ¿La ciudad dada tiene duplicados conocidos en otros departamentos
     * de Colombia? Devuelve la lista de departamentos donde existe ese
     * nombre, o array vacío si es única.
     *
     * Lista curada de los municipios colombianos más comunes con duplicados.
     */
    private function departamentosDeMunicipioAmbiguo(string $ciudad): array
    {
        $c = mb_strtolower(\Illuminate\Support\Str::ascii(trim($ciudad)));
        if ($c === '') return [];

        // Map ciudad → departamentos. Solo los más comunes/problemáticos.
        // Si el array tiene >1 entrada, es ambigua.
        $ambiguas = [
            'barbosa'        => ['Antioquia', 'Santander'],
            'san antonio'    => ['Antioquia (San Antonio de Prado)', 'Tolima', 'Cundinamarca', 'Huila', 'Valle del Cauca'],
            'san carlos'     => ['Antioquia', 'Córdoba'],
            'san francisco'  => ['Antioquia', 'Cundinamarca', 'Putumayo'],
            'san jose'       => ['Caldas', 'Guaviare', 'Antioquia (varios)'],
            'san juan'       => ['Boyacá', 'Cundinamarca', 'La Guajira', 'Cesar'],
            'san luis'       => ['Antioquia', 'Tolima'],
            'san martin'     => ['Cesar', 'Meta'],
            'san miguel'     => ['Putumayo', 'Santander'],
            'san pedro'      => ['Sucre', 'Valle del Cauca'],
            'san vicente'    => ['Antioquia (de Chucurí)', 'Santander (Ferrer)', 'Caquetá', 'Cauca'],
            'santa rosa'     => ['Risaralda (de Cabal)', 'Antioquia (de Osos)', 'Bolívar', 'Cauca'],
            'santa barbara'  => ['Antioquia', 'Nariño', 'Santander'],
            'santa catalina' => ['Bolívar', 'Antioquia (vereda)'],
            'la estrella'    => ['Antioquia', 'Bolívar'],
            'la union'       => ['Antioquia', 'Nariño', 'Valle del Cauca', 'Sucre'],
            'la victoria'    => ['Boyacá', 'Valle del Cauca', 'Caldas'],
            'la cruz'        => ['Nariño', 'Cauca'],
            'la florida'     => ['Nariño', 'Valle'],
            'la merced'      => ['Caldas', 'Cundinamarca'],
            'la palma'       => ['Cundinamarca'],
            'la plata'       => ['Huila'],
            'belen'          => ['Boyacá', 'Caquetá', 'Nariño', 'Antioquia (de Bajirá)'],
            'buenavista'     => ['Boyacá', 'Córdoba', 'Quindío', 'Sucre'],
            'cabrera'        => ['Cundinamarca', 'Santander'],
            'el carmen'      => ['Norte de Santander', 'Bolívar (de Bolívar)', 'Antioquia (de Viboral)'],
            'el penol'       => ['Antioquia', 'Nariño'],
            'el peñol'       => ['Antioquia', 'Nariño'],
            'el tambo'       => ['Cauca', 'Nariño'],
            'florencia'      => ['Caquetá', 'Cauca'],
            'florida'        => ['Valle del Cauca'],
            'granada'        => ['Antioquia', 'Cundinamarca', 'Meta'],
            'guaduas'        => ['Cundinamarca'],
            'guarne'         => ['Antioquia'],
            'pueblo nuevo'   => ['Córdoba', 'Cundinamarca'],
            'salamina'       => ['Caldas', 'Magdalena'],
            'silvania'       => ['Cundinamarca'],
            'soata'          => ['Boyacá'],
            'sucre'          => ['Cauca', 'Santander', 'Sucre'],
            'tame'           => ['Arauca'],
            'tibu'           => ['Norte de Santander'],
            'turbaco'        => ['Bolívar'],
            'turbo'          => ['Antioquia'],
            'venecia'        => ['Antioquia', 'Cundinamarca'],
            'yacopi'         => ['Cundinamarca'],
            'yopal'          => ['Casanare'],
            'andes'          => ['Antioquia'],
            'apartado'       => ['Antioquia'],
            'armenia'        => ['Quindío', 'Antioquia'],
            'caicedonia'     => ['Valle del Cauca'],
        ];

        // Quitar tildes y normalizar
        $key = preg_replace('/\s+/', ' ', $c);
        if (isset($ambiguas[$key]) && count($ambiguas[$key]) > 1) {
            return $ambiguas[$key];
        }
        return [];
    }

    /**
     * 🛡️ ¿El último mensaje del cliente contiene un departamento explícito?
     * (ej "Barbosa Antioquia", "San Antonio Tolima").
     */
    private function mensajeContieneDepartamento(?string $telefonoCliente): bool
    {
        if (empty($telefonoCliente)) return false;
        try {
            $tenantId = app(\App\Services\TenantManager::class)->id();
            if (!$tenantId) return false;
            $tel = preg_replace('/\D+/', '', $telefonoCliente);
            $conv = \App\Models\ConversacionWhatsapp::where('tenant_id', $tenantId)
                ->where('telefono_normalizado', $tel)
                ->orderByDesc('id')
                ->first();
            if (!$conv) return false;

            $ult = \App\Models\MensajeWhatsapp::where('conversacion_id', $conv->id)
                ->where('rol', 'user')
                ->orderByDesc('id')
                ->value('contenido');
            if (!$ult) return false;

            $n = mb_strtolower(\Illuminate\Support\Str::ascii((string) $ult));
            $deptos = [
                'antioquia', 'cundinamarca', 'tolima', 'huila', 'valle', 'cauca', 'narino', 'nariño',
                'santander', 'norte de santander', 'cordoba', 'córdoba', 'sucre', 'bolivar', 'bolívar',
                'magdalena', 'cesar', 'la guajira', 'guajira', 'atlantico', 'atlántico', 'choco', 'chocó',
                'caldas', 'risaralda', 'quindio', 'quindío', 'meta', 'arauca', 'casanare', 'putumayo',
                'caqueta', 'caquetá', 'amazonas', 'guaviare', 'guainia', 'guainía', 'vaupes', 'vaupés',
                'vichada', 'boyaca', 'boyacá', 'san andres', 'san andrés',
            ];
            foreach ($deptos as $d) {
                if (str_contains($n, $d)) return true;
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 🛡️ ¿La respuesta del bot es una "promesa rota"? Es decir, texto que
     * promete una acción ("déjame buscar X") sin que se haya ejecutado
     * ninguna tool útil que lo respalde.
     */
    private function respuestaEsPromesaRota(string $reply, array $toolMessages): bool
    {
        $n = mb_strtolower(\Illuminate\Support\Str::ascii(trim($reply)));
        if ($n === '') return false;

        // Texto demasiado corto sin verbos de cierre → probable promesa
        $patronesPromesa = [
            'dejame buscar', 'déjame buscar', 'voy a buscar', 'permíteme buscar', 'permiteme buscar',
            'dejame verificar', 'déjame verificar', 'voy a verificar', 'verifico',
            'dejame revisar', 'déjame revisar', 'voy a revisar',
            'un momento por favor', 'un momento por fa', 'dame un momento', 'dame un segundo',
            'espera un momento', 'espera un segundo',
            'te confirmo en un momento', 'te confirmo enseguida',
            'consulto y te aviso', 'consulto y te digo',
            'busco esa información', 'busco esa info',
            'déjame ver', 'dejame ver',
        ];
        $tienePromesa = false;
        foreach ($patronesPromesa as $p) {
            if (str_contains($n, $p)) { $tienePromesa = true; break; }
        }
        if (!$tienePromesa) return false;

        // Si la respuesta tiene MÁS contenido que solo la promesa (>120 chars o
        // contiene listas/precios), entonces ya cumplió. Una promesa "rota"
        // típicamente es CORTA y NO trae info concreta.
        if (mb_strlen($n) > 120) return false;
        if (preg_match('/\$\s?\d|\d+\s*(kg|kl|lb|libra|kilo|unidad)|•|\*\*[a-z]/iu', $reply)) return false;

        // Si se ejecutaron tools útiles, la promesa fue cumplida.
        $toolsUtiles = ['buscar_productos', 'productos_de_categoria', 'productos_destacados',
                        'info_producto', 'consultar_promociones', 'consultar_zonas_cobertura',
                        'validar_cobertura'];
        foreach ($toolMessages as $tm) {
            $name = $tm['name'] ?? '';
            if (in_array($name, $toolsUtiles, true)) {
                $content = (string) ($tm['content'] ?? '');
                if (mb_strlen($content) > 30 && !str_contains($content, '"encontrados":0')) {
                    return false; // tool útil con resultados → promesa cumplida
                }
            }
        }

        return true; // promesa sin tool útil que la respalde
    }

    /**
     * 🛡️ Ejecuta la tool que el bot prometió y devuelve un reply con
     * el resultado real. Si no puede determinar la tool, devuelve null.
     */
    private function autoEjecutarToolDePromesa(string $replyPromesa, string $mensajeCliente, $conversacion, $connectionId, string $from): ?string
    {
        $msgN = mb_strtolower(\Illuminate\Support\Str::ascii(trim($mensajeCliente)));
        if ($msgN === '') return null;

        // Determinar qué buscar según contexto:
        // El último mensaje del cliente + lo que el bot prometió buscar.
        $combinado = $msgN . ' ' . mb_strtolower(\Illuminate\Support\Str::ascii($replyPromesa));

        // Intentar extraer un producto mencionado (palabra clave significativa)
        // Tomamos sustantivos del mensaje del cliente
        $palabras = preg_split('/[\s,.\?!¡¿]+/u', $msgN);
        $stopwords = ['cuanto','cuánto','que','qué','tienes','hay','dame','quiero','necesito',
                      'es','el','la','los','las','un','una','de','del','para','por','con',
                      'el','la','solomito','informacion','información','maximo','máximo'];
        $producto = null;
        foreach ($palabras as $p) {
            $p = trim($p);
            if (mb_strlen($p) >= 4 && !in_array($p, $stopwords, true)) {
                $producto = $producto ? $producto . ' ' . $p : $p;
            }
        }
        // Si no encontramos producto en el mensaje del cliente, buscar en el reply del bot
        if (!$producto || mb_strlen($producto) < 4) {
            if (preg_match('/(?:solomito|pollo|res|cerdo|costilla|milanesa|cañon|caño|pierna|pescado|basa|bagre|hueso|carne|pechuga|muslo|chuleta|posta|punta|lomo|bocadillo|chorizo|filete)\s*\w*/iu', $combinado, $m)) {
                $producto = trim($m[0]);
            }
        }

        if (!$producto) return null;

        try {
            // Ejecutar buscar_productos directamente
            app(\App\Services\TenantManager::class)->set($conversacion->tenant);
            $sedeId = $this->obtenerSedeIdDesdeConexion($connectionId);
            $svc = app(\App\Services\BotCatalogoToolService::class);
            $r = $svc->buscarProductos($producto, null, 5, $sedeId);

            $productos = $r['productos'] ?? [];
            if (empty($productos)) return null;

            // Formatear respuesta con los resultados
            $lineas = ["Esto es lo que tenemos:"];
            foreach (array_slice($productos, 0, 5) as $p) {
                $nombre = $p['nombre'] ?? '?';
                $precio = $p['precio'] ?? 0;
                $unidad = $p['unidad'] ?? 'unidad';
                $lineas[] = "• **{$nombre}** — $" . number_format($precio, 0, ',', '.') . "/{$unidad}";
            }
            $lineas[] = "";
            $lineas[] = "¿Cuál te llevas y cuánto? 😊";
            return implode("\n", $lineas);
        } catch (\Throwable $e) {
            Log::warning('autoEjecutarToolDePromesa falló: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 🛡️ Resuelve el tipo de entrega FINAL del pedido respetando el estado
     * persistente. Prioridad:
     *   1. Estado persistente dice 'recoger' → 'recoger' (gana sobre todo)
     *   2. orderData tiene pickup=true o sede_id → 'recoger'
     *   3. Mensajes con palabras de recoger → 'recoger'
     *   4. Default → 'domicilio'
     */
    private function resolverTipoEntregaFinal(bool $esPickupDetectado, $conversacion, array $orderData): string
    {
        // Prioridad 1: estado persistente del pedido
        try {
            if ($conversacion) {
                $estadoChk = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
                if ($estadoChk->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_RECOGER) {
                    return 'recoger';
                }
                if ($estadoChk->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_DOMICILIO
                    && !empty($estadoChk->direccion)) {
                    // Si el estado dice DOMICILIO con dirección real → es domicilio.
                    // PERO si esPickup detectado por orderData es muy fuerte (notes
                    // dicen 'recoge') → puede ser cambio reciente, dejarlo pickup.
                    if ($esPickupDetectado && (
                        !empty($orderData['pickup']) || !empty($orderData['sede_id'])
                    )) {
                        return 'recoger';
                    }
                    return 'domicilio';
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        return $esPickupDetectado ? 'recoger' : 'domicilio';
    }

    /**
     * 🛡️ Distancia mínima en KM desde un punto a CUALQUIERA de las sedes
     * activas del tenant (usando haversine). Sirve para detectar si Google
     * geocodificó a otra parte del país (ambigüedad de nombres).
     */
    private function distanciaMinimaASedesActivas(float $lat, float $lng, ?int $tenantId): ?float
    {
        if (!$tenantId) return null;
        try {
            $sedes = \App\Models\Sede::where('tenant_id', $tenantId)
                ->where('activa', true)
                ->whereNotNull('latitud')
                ->whereNotNull('longitud')
                ->get(['latitud', 'longitud']);
            if ($sedes->isEmpty()) return null;

            $min = PHP_INT_MAX;
            foreach ($sedes as $s) {
                $d = $this->haversineKm($lat, $lng, (float) $s->latitud, (float) $s->longitud);
                if ($d < $min) $min = $d;
            }
            return $min === PHP_INT_MAX ? null : (float) $min;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371; // radio Tierra km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    /**
     * 🛡️ ¿El mensaje del cliente es una confirmación PURA?
     * "sí", "confirmo", "dale", "listo confirmo", etc. — sin productos nuevos.
     */
    private function mensajeEsConfirmacionPura(string $msg): bool
    {
        $m = mb_strtolower(\Illuminate\Support\Str::ascii(trim($msg)));
        if ($m === '') return false;
        $confirmaciones = [
            'si', 'sí', 'confirmo', 'si confirmo', 'sí confirmo',
            'dale', 'listo', 'ok', 'okay', 'va', 'va pues', 'perfecto',
            'de acuerdo', 'si por favor', 'si gracias',
            'genera el pedido', 'haz el pedido', 'cierra el pedido',
            'confirmo el pedido', 'si confirmo el pedido', 'eso es todo',
        ];
        // Coincidencia exacta o cuasi-exacta (≤ 30 chars)
        if (in_array($m, $confirmaciones, true)) return true;
        if (mb_strlen($m) <= 30) {
            foreach ($confirmaciones as $c) {
                if (str_contains($m, $c)) return true;
            }
        }
        return false;
    }

    /**
     * 🛡️ ¿La dirección es un patrón colombiano genérico (CL/CRA + número)
     * SIN nombre de ciudad/barrio incluido en el texto?
     */
    private function direccionEsGenericaColombiana(string $direccion): bool
    {
        $d = mb_strtolower(\Illuminate\Support\Str::ascii(trim($direccion)));
        if ($d === '') return false;

        // ¿Tiene patrón de vía colombiana + número?
        $patronVia = '/\b(cra|carrera|kr|cr|cl|calle|cll|dg|diagonal|trv|transversal|tv|av|avenida|circular)\s*\.?\s*\d/iu';
        if (!preg_match($patronVia, $d)) return false;

        // ¿Menciona alguna ciudad/barrio conocidos?
        $municipiosBarrios = [
            'bello', 'medellin', 'medellín', 'girardota', 'copacabana', 'sabaneta',
            'envigado', 'itagui', 'itagüí', 'caldas', 'la estrella', 'barbosa',
            'rionegro', 'marinilla', 'guarne', 'la ceja', 'el retiro', 'el carmen',
            'prado', 'niquia', 'niquía', 'fontidueño', 'rincon santo', 'cabañas',
            'paris', 'parís', 'la gabriela', 'altamira', 'la mota', 'suárez', 'suarez',
        ];
        foreach ($municipiosBarrios as $loc) {
            if (str_contains($d, $loc)) return false;
        }
        return true;
    }

    /**
     * 🛡️ ¿La ciudad pasada es solo un default (no confirmada por el cliente
     * EN ESTE TURNO junto con la dirección)?
     *
     * Razón: si el cliente dijo "Bello" hace 5 mensajes preguntando por cobertura
     * GENERAL y AHORA da una dirección NUEVA "Calle 49 #50-05" sin volver a
     * mencionar el municipio, esa dirección sigue siendo ambigua — podría ser
     * en otra ciudad. Confiar en el contexto antiguo lleva a errores.
     *
     * Solo confiamos en la ciudad si el cliente la dijo en su ÚLTIMO mensaje
     * (el que disparó la validación actual) O si la dirección misma la trae.
     */
    private function ciudadEsDefaultNoMencionada(?string $ciudad, ?string $telefonoCliente): bool
    {
        if (empty($ciudad)) return true; // sin ciudad → default
        if (empty($telefonoCliente)) return true; // sin teléfono no podemos verificar

        try {
            $tenantId = app(\App\Services\TenantManager::class)->id();
            if (!$tenantId) return false;

            // Buscar conversación del cliente — solo tabla tiene telefono_normalizado
            $tel = preg_replace('/\D+/', '', $telefonoCliente);
            $conv = \App\Models\ConversacionWhatsapp::where('tenant_id', $tenantId)
                ->where('telefono_normalizado', $tel)
                ->orderByDesc('id')
                ->first();
            if (!$conv) return true; // sin conv → no podemos confirmar, tratar como default

            // SOLO el ÚLTIMO mensaje del usuario (el actual). Los anteriores
            // no cuentan — el cliente puede estar dando una dirección nueva
            // de otra ciudad sin volver a mencionarla.
            $ultimoMsg = \App\Models\MensajeWhatsapp::where('conversacion_id', $conv->id)
                ->where('rol', 'user')
                ->orderByDesc('id')
                ->value('contenido');
            if (!$ultimoMsg) return true;

            $ciudadNorm = mb_strtolower(\Illuminate\Support\Str::ascii(trim($ciudad)));
            $msgN = mb_strtolower(\Illuminate\Support\Str::ascii((string) $ultimoMsg));
            if (str_contains($msgN, $ciudadNorm)) return false; // SÍ la mencionó en ESTE turno
            return true; // NO la mencionó en ESTE turno → es default del LLM
        } catch (\Throwable $e) {
            // Ante cualquier error, mejor pecar de cautelosos: tratar como default
            // (ambiguo) para forzar al bot a preguntar al cliente.
            \Log::warning('ciudadEsDefaultNoMencionada falló: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * 🗺️ Extrae el nombre de un lugar (municipio/barrio/ciudad) mencionado
     * en el mensaje del cliente. Devuelve null si no detecta lugar.
     *
     * Estrategia dinámica (no regex de patrones de pregunta):
     *  1. Quitar palabras funcionales comunes (artículos, verbos, signos).
     *  2. Identificar sustantivos propios (palabras capitalizadas o conocidas).
     *  3. Devolver el candidato más probable.
     *
     * El LLM ya sabe que es pregunta de cobertura — esta función solo
     * extrae el LUGAR para que la tool lo pueda validar.
     */
    private function extraerLugarDelMensaje(string $mensaje): ?string
    {
        $m = trim($mensaje);
        if ($m === '') return null;

        // Quitar signos
        $m = preg_replace('/[¿?¡!.,;:()"\']/u', ' ', $m);
        $m = preg_replace('/\s+/u', ' ', trim($m));

        // Palabras funcionales que NO son lugares (preposiciones, verbos,
        // muletillas). Sin esto "envío" o "domicilio" podrían capturarse.
        $stop = [
            // Artículos / preposiciones
            'a','de','del','la','el','los','las','un','una','y','o','en','por','para','con','si','no','sin','que',
            // Verbos / preguntas comunes
            'cubren','cubres','cubre','llegan','llegas','llega','tienen','tienes','tiene','hay','reparten',
            'envian','enviar','envio','envías','manda','mandan','llevan','lleva','llevas','quiero','necesito',
            'puedes','puede','puedo','dame','dale','listo','vamos','dime','mira','ya','también','tambien',
            // Sustantivos NO-lugar comunes
            'envio','envío','envios','envíos','domicilio','domicilios','cobertura','reparto','despacho',
            'pedido','servicio','zona','area','área','barrio','ciudad','municipio','direccion','dirección',
            'casa','hogar','aqui','aquí','alla','allá','aca','acá','mi','tu','su','nuestro',
            // Saludos
            'hola','holaa','hi','hey','buenos','buenas','dias','días','tardes','noches','dia','día',
            'gracias','muchas','mil','por','favor','ok','okay','dale','perfecto','genial','bueno',
            // Otros funcionales
            'es','soy','son','estoy','está','esta','está','están','están','estamos','vivo','vive','queda',
        ];

        // Tokenizar
        $tokens = preg_split('/\s+/u', $m);
        if (!$tokens) return null;

        // Filtrar tokens que NO son stop-words y tienen ≥3 letras
        $candidatos = [];
        $idx = 0;
        foreach ($tokens as $t) {
            $tNorm = mb_strtolower(\Illuminate\Support\Str::ascii($t));
            $tNorm = trim($tNorm);
            if ($tNorm === '' || in_array($tNorm, $stop, true)) {
                $idx++;
                continue;
            }
            // Descartar números y palabras de <3 caracteres
            if (mb_strlen($tNorm) < 3) { $idx++; continue; }
            if (preg_match('/^\d+$/', $tNorm)) { $idx++; continue; }

            $candidatos[] = ['token' => $t, 'norm' => $tNorm, 'pos' => $idx];
            $idx++;
        }

        if (empty($candidatos)) return null;

        // Heurística: si hay UN solo candidato, ese es el lugar.
        // Si hay varios, preferir el último (típicamente el lugar va al final).
        // Combinar tokens consecutivos para captar "la estrella", "puerto berrio".
        $resultado = null;
        $ultimo = end($candidatos);
        $resultado = $ultimo['token'];

        // ¿El anterior está adyacente y también es candidato? Concatenar (ej "La Estrella")
        $idxFinal = $ultimo['pos'];
        for ($i = count($candidatos) - 2; $i >= 0; $i--) {
            if ($candidatos[$i]['pos'] === $idxFinal - 1) {
                $resultado = $candidatos[$i]['token'] . ' ' . $resultado;
                $idxFinal = $candidatos[$i]['pos'];
            } else {
                break;
            }
        }

        // Capitalizar nombre
        $resultado = mb_convert_case(trim($resultado), MB_CASE_TITLE, 'UTF-8');

        // Sanity check: longitud razonable
        if (mb_strlen($resultado) < 3 || mb_strlen($resultado) > 60) return null;

        return $resultado;
    }

    private function clientePreguntaProducto(string $mensaje): bool
    {
        $m = mb_strtolower(trim($mensaje));
        if ($m === '') return false;

        // Patrones explícitos: "tienes X?", "quiero X", "a como X?", "cuánto vale X?"
        $patrones = [
            // Tener / disponer
            '/\b(tienes|tienen|tendr[áa]s|tendr[áa]n|hay|manejas|venden|vendes|consigues|consiguen)\s+/iu',
            // Querer / pedir
            '/\b(quiero|necesito|me das|d[áa]me|puede ser|me regal[áa]s|reg[áa]lame|qu[ií]siera|busco|tr[áa]eme|me traes|me llevo|me lleva|ll[ée]vame|alc[áa]nzame|me alcanzas)\s+/iu',
            // Preguntas de precio: "a como", "cuánto vale", "qué precio", "precio de"
            '/\b(a\s+c[óo]mo|a\s+cuanto|a\s+cu[áa]nto|cu[áa]nto\s+(vale|cuesta|sale|est[áa]|tiene|tienes)|cu[áa]nto\s+es|precio\s+(de|del)|qu[eé]\s+precio)\b/iu',
            // Cantidades con unidad (números)
            '/\b(\d+)\s+(libras?|lbs?|kilos?|kls?|kg|gramos?|gr|unidades?|unidad|cajas?|caja|paquetes?|paquete|bolsas?|docenas?|gallinas?|porciones?|libritas?|kilitos?|cucharaditas?|botellas?|latas?)\b/iu',
            // Cantidades en letras
            '/\b(una?|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|media|medio)\s+(libras?|kilos?|kg|unidades?|cajas?|paquetes?|bolsas?|docenas?|porciones?|gallinas?|libritas?|kilitos?)\b/iu',
            // Pregunta contextual de continuación: "y hueso?", "y la basa?", "y un kilo de X"
            '/^y\s+(el|la|los|las|un|una|unos|unas)?\s*\w{3,}/iu',
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
    /**
     * 🔄 Detecta si un handoff fue abandonado por el equipo humano.
     * Devuelve true si:
     *   - La conversación está en modo humano
     *   - Han pasado más de HORAS_HANDOFF_ABANDONADO desde la derivación
     *   - Ningún operador (rol=assistant + meta.origen=operador) respondió
     *     desde entonces
     * Cuando esto pasa, el bot retoma para no dejar al cliente colgado.
     */
    private const HORAS_HANDOFF_ABANDONADO = 2;

    private function handoffAbandonado(\App\Models\ConversacionWhatsapp $conv): bool
    {
        if (!$conv->atendida_por_humano) return false;

        $referencia = $conv->derivada_at ?: $conv->updated_at;
        if (!$referencia) return false;

        $horasTranscurridas = now()->diffInMinutes($referencia) / 60;
        if ($horasTranscurridas < self::HORAS_HANDOFF_ABANDONADO) return false;

        // ¿Hubo respuesta de un operador humano desde la derivación?
        $hayMensajeHumano = \App\Models\MensajeWhatsapp::where('conversacion_id', $conv->id)
            ->where('rol', \App\Models\MensajeWhatsapp::ROL_ASSISTANT)
            ->where('created_at', '>', $referencia)
            ->whereJsonContains('meta->origen', 'operador')
            ->exists();
        if ($hayMensajeHumano) return false;

        Log::info('⏰ Handoff abandonado detectado', [
            'conv_id'  => $conv->id,
            'horas'    => round($horasTranscurridas, 1),
            'derivada_at' => optional($conv->derivada_at)?->format('Y-m-d H:i'),
        ]);
        return true;
    }

    /**
     * 🔄 Detecta si el cliente está RETRACTANDO la razón del handoff.
     * Solo aplica si: (a) la conversación está en modo humano,
     * (b) un operador humano NO ha respondido aún desde la derivación.
     * Si ambas se cumplen + el mensaje del cliente sugiere cancelación
     * o cambio de opinión, devolvemos el control al bot.
     */
    private function clienteRetractaHandoff(\App\Models\ConversacionWhatsapp $conv, string $mensaje): bool
    {
        if (!$conv->atendida_por_humano) return false;

        // ¿Hubo un mensaje del operador (rol=assistant + meta.origen=operador)
        // desde la derivación? Si sí, el humano ya está atendiendo y NO revertimos.
        $derivadaAt = $conv->derivada_at;
        if (!$derivadaAt) {
            // Si no hay timestamp, asumimos derivación reciente — chequear contra
            // ultimo mensaje assistant (bot) que la disparó.
            $derivadaAt = $conv->updated_at;
        }
        $hayMensajeHumano = \App\Models\MensajeWhatsapp::where('conversacion_id', $conv->id)
            ->where('rol', \App\Models\MensajeWhatsapp::ROL_ASSISTANT)
            ->where('created_at', '>', $derivadaAt)
            ->whereJsonContains('meta->origen', 'operador')
            ->exists();
        if ($hayMensajeHumano) return false;

        // Patrones que sugieren retractación / cambio de opinión
        $msg = mb_strtolower(\Illuminate\Support\Str::ascii(trim($mensaje)));
        $patrones = [
            'olvidalo', 'olvida', 'olvidate',
            'entonces no', 'no importa', 'no necesito', 'no requiero', 'no quiero',
            'sin eso', 'sin factura', 'sin facturacion',
            'cancela esa', 'cancela eso', 'cancelalo',
            'dejalo asi', 'dejalo as\xc3', 'dejame', 'asi esta bien',
            'cambia', 'cambialo', 'cambiame',
            'mejor no', 'no asi', 'no asesor', 'no humano',
            'sigamos', 'continua', 'seguimos',
            // 🔄 Cliente quiere seguir con SU pedido
            'deseo seguir', 'quiero seguir', 'sigo con', 'continuemos',
            'mi pedido', 'con mi pedido', 'el pedido',
            'agrega', 'agregame', 'agrega tambien', 'agregame tambien',
            'pidir mas', 'pedir mas', 'quiero mas', 'agrega tambien',
            'y tambien', 'tambien quiero', 'tambien pidame', 'tambien pideme',
            'sumale', 'añade', 'anade', 'anademe',
            // Cliente sigue pidiendo productos = quiere seguir con bot
            'quero pedir', 'quiero pedir', 'pideme', 'me das', 'regalame',
        ];
        foreach ($patrones as $p) {
            if (str_contains($msg, $p)) return true;
        }
        return false;
    }

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

        // 🛡️ Saludos puros NUNCA cuentan como afirmación, aunque contengan
        //    palabras como "bueno" ("buenos días") o "si" ("sí, dime"). Si el
        //    cliente está saludando, no está aceptando programar nada.
        $saludosPuros = [
            'hola', 'holaa', 'holaaa', 'hi', 'hey', 'que tal', 'que mas',
            'buenas', 'buenos dias', 'buenas tardes', 'buenas noches',
            'buen dia', 'saludos',
        ];
        if (in_array($msgActual, $saludosPuros, true)) return false;
        if (preg_match('/^\s*(hola|holaa+|buenas|buen[oa]s\s+(dias|tardes|noches)|buen\s+dia)\s*[\.!?]*\s*$/i', $msgActual)) {
            return false;
        }

        // Matching por palabra completa (word boundary), no por subcadena.
        // Antes: str_contains("buenos dias","bueno")==true → falso positivo.
        $matchAfirma = false;
        foreach ($afirmaciones as $a) {
            if ($msgActual === $a) { $matchAfirma = true; break; }
            // Word boundary: la afirmación debe estar como palabra completa
            $patron = '/\b' . preg_quote($a, '/') . '\b/u';
            if (preg_match($patron, $msgActual)) { $matchAfirma = true; break; }
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

    /**
     * 🎯 Cuando el cliente pregunta por una preparación específica (ej: "al estilo guiso",
     * "molerlo", "para chicharrón"), busca si existe un CORTE en el catálogo que
     * mapee con esa solicitud, y devuelve una respuesta que se la ofrezca.
     *
     * Retorna null si no encuentra match — el caller debe usar respuesta genérica.
     */
    private function buscarCorteRelacionado(string $msgLower): ?string
    {
        try {
            $cortes = \App\Models\Corte::where('tenant_id', app(\App\Services\TenantManager::class)->id())
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['nombre', 'descripcion', 'icono_emoji']);

            if ($cortes->isEmpty()) return null;

            // Mapeo: palabra del cliente → palabras clave de cortes que matchean
            $mapeo = [
                'guiso'      => ['guiso', 'cubos', 'goulash', 'cuadros'],
                'sancocho'   => ['guiso', 'cubos', 'goulash', 'porcionado a hueso', 'hueso'],
                'asado'      => ['argentino', 'churrasco', 'troncos', 'parrilla'],
                'parrilla'   => ['argentino', 'churrasco', 'troncos'],
                'moler'      => ['molida'],
                'molido'     => ['molida'],
                'molida'     => ['molida'],
                'chicharron' => ['barril'],
                'chicharrón' => ['barril'],
                'milanesa'   => ['churrasco', 'mariposa', 'tajadas'],
                'churrasco'  => ['churrasco'],
                'chuleta'    => ['tajadas'],
                'tajada'     => ['tajadas'],
                'medallon'   => ['medallones'],
                'medallón'   => ['medallones'],
                'tiras'      => ['tiras'],
                'cuadros'    => ['cuadros'],
                'cubos'      => ['cuadros', 'goulash'],
                'picar'      => ['cuadros', 'goulash', 'molida'],
                'sin grasa'  => ['sin cordón', 'sin cordon'],
                'desgrasado' => ['sin cordón', 'sin cordon'],
                'mariposa'   => ['mariposa'],
            ];

            $cortesEncontrados = collect();
            foreach ($mapeo as $palabraCliente => $keywordsCorte) {
                if (!str_contains($msgLower, \Illuminate\Support\Str::ascii($palabraCliente))) continue;
                foreach ($cortes as $c) {
                    $nombreLower = mb_strtolower(\Illuminate\Support\Str::ascii($c->nombre));
                    $descLower   = mb_strtolower(\Illuminate\Support\Str::ascii($c->descripcion ?? ''));
                    foreach ($keywordsCorte as $kw) {
                        $kw = \Illuminate\Support\Str::ascii($kw);
                        if (str_contains($nombreLower, $kw) || str_contains($descLower, $kw)) {
                            $cortesEncontrados->push($c);
                            break;
                        }
                    }
                }
            }
            $cortesEncontrados = $cortesEncontrados->unique('nombre')->values();

            if ($cortesEncontrados->isEmpty()) return null;

            if ($cortesEncontrados->count() === 1) {
                $c = $cortesEncontrados->first();
                $emoji = $c->icono_emoji ?: '✂️';
                $desc  = $c->descripcion ? " ({$c->descripcion})" : '';
                return "¡Sí podemos! 🙌 Te lo dejo en corte *{$c->nombre}* {$emoji}{$desc}. ¿Cuántas libras o kilos te llevas?";
            }

            $lista = $cortesEncontrados->take(4)->map(function ($c) {
                $emoji = $c->icono_emoji ?: '✂️';
                $desc  = $c->descripcion ? " — {$c->descripcion}" : '';
                return "• *{$c->nombre}* {$emoji}{$desc}";
            })->implode("\n");

            return "Sí podemos! 🙌 Para eso te recomiendo alguno de estos cortes:\n\n{$lista}\n\n¿Cuál te tinca y cuántas libras o kilos te llevas?";
        } catch (\Throwable $e) {
            \Log::warning('buscarCorteRelacionado falló: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Respuesta cuando el cliente pregunta por una preparación que NO mapea con
     * ningún corte del catálogo. Lista los cortes disponibles para que elija.
     */
    private function respuestaCortesGenericos(): string
    {
        try {
            $cortes = \App\Models\Corte::where('tenant_id', app(\App\Services\TenantManager::class)->id())
                ->where('activo', true)
                ->orderBy('orden')
                ->limit(8)
                ->get(['nombre', 'descripcion']);

            if ($cortes->isEmpty()) {
                return "Solo te entregamos el producto como está en el catálogo 😊 ¿Te lo agrego así o miramos otra opción?";
            }

            $lista = $cortes->map(fn ($c) =>
                '• *' . $c->nombre . '*' . ($c->descripcion ? ' — ' . $c->descripcion : '')
            )->implode("\n");

            return "Esa preparación específica no la manejamos, pero te puedo ofrecer estos cortes 🙌:\n\n{$lista}\n\n¿Cuál te queda mejor?";
        } catch (\Throwable $e) {
            return "Solo te entregamos el producto como está en el catálogo 😊 ¿Te lo agrego o miramos otra opción?";
        }
    }

    /**
     * 🛡️ Sanitiza placeholders que el LLM (Claude/GPT) mete por error en strings.
     * Casos típicos:
     *   - "<UNKNOWN>" / "<unknown>" / "<DESCONOCIDO>"
     *   - "<placeholder>", "<TBD>", "<XXX>"
     *   - "null" / "N/A" como string literal
     *   - "" (string vacío después de trim)
     *
     * Si detecta uno, devuelve string vacío. Si no, devuelve el valor trimeado.
     */
    private function sanitizarPlaceholderLLM(?string $valor): string
    {
        $v = trim((string) ($valor ?? ''));
        if ($v === '') return '';

        // Patrones de placeholder: <ALGO> con letras/símbolos dentro
        if (preg_match('/^\s*<[A-Z_\-\s]+>\s*$/i', $v)) return '';

        // Strings literales que indican "no sé"
        $placeholdersLiterales = [
            'null', 'undefined', 'n/a', 'na', '?', '??', '???',
            'desconocido', 'desconocida', 'unknown', 'tbd',
            'sin dato', 'sin datos', 'sin info', 'sin información',
            'no se', 'no sé', 'no aplica', 'placeholder', 'no especificado',
        ];
        if (in_array(mb_strtolower($v), $placeholdersLiterales, true)) return '';

        return $v;
    }

    private function detectarFalsaConfirmacion(string $reply): ?string
    {
        $lower = mb_strtolower($reply);

        // 🛡️ REGEX flexibles que detectan variantes con palabras intermedias
        // ("está", "ya está", "ha sido", "queda", "fue") que el LLM mete entre
        // 'pedido' y 'confirmado/registrado'.
        $regexes = [
            // "pedido confirmado", "pedido está confirmado", "pedido ya quedó confirmado"
            '/\bpedido\s+(?:[a-zñáéíóú]+\s+){0,4}(?:confirmado|registrado|listo|creado|guardado|procesado|recibido)\b/u',
            // "tu pedido está/queda listo", "su pedido fue creado"
            '/\b(?:tu|su)\s+pedido\s+(?:[a-zñáéíóú]+\s+){0,3}(?:está|queda|fue|ha sido|ya|listo|creado)\b/u',
            // Despacho en pasado/presente afirmativo
            '/\b(?:va|sale|salió)\s+(?:en\s+camino|para\s+(?:tu|su)\s+casa|hacia)\b/u',
            // "ya lo despachamos", "ya lo enviamos"
            '/\bya\s+(?:lo|la)\s+(?:despach|envi|entreg|mand)/u',
            // "tu/su PEDIDO queda anotado/apuntado/agendado/registrado/listo"
            // (NO disparar con simples "queda anotado:" que el bot usa como checklist intermedio)
            '/\b(?:tu|su|el)\s+pedido\s+queda\s+(?:anotado|apuntado|agendado|registrado|listo)\b/u',
            '/\bqueda\s+(?:anotado|apuntado|agendado|registrado|listo)\s+(?:tu|su|el)\s+pedido\b/u',
            // "tu pedido #N" (número de pedido)
            '/\btu\s+pedido\s+#\d+/u',
        ];

        foreach ($regexes as $r) {
            if (preg_match($r, $lower, $m)) {
                return $m[0];
            }
        }
        return null;
    }

    /**
     * 🛡️ BUG-C2: Detecta cuando el bot dice "listo, agregué/quité X al carrito"
     * pero el carrito en BD NO refleja esa operación. El bot alucinaría que
     * realizó cambios sin haber llamado las tools correspondientes.
     *
     * Retorna la frase detectada, o null si no hay alucinación.
     */
    private function detectarAlucinacionCarrito(string $reply, ?\App\Models\ConversacionPedidoEstado $estado): ?string
    {
        $lower = mb_strtolower($reply);

        // Regexes que detectan afirmaciones de operación sobre el carrito
        $regexesAgregar = [
            '/\b(?:listo|perfecto|hecho|ya|ok)[\s,]+(?:te\s+)?(?:lo|la|los|las)\s+agregu[eé](?:mos)?\b/u',
            '/\b(?:agregad[oa]|añadid[oa])\s+(?:al\s+)?(?:carrito|pedido)\b/u',
            '/\b(?:te\s+)?(?:lo|la|los|las)\s+(?:añad[íi]|agregu[eé])\s+(?:al\s+)?(?:carrito|pedido)\b/u',
            '/\b(?:queda\s+)?(?:agregad[oa]|añadid[oa])\s+en\s+(?:tu\s+)?(?:carrito|pedido)\b/u',
            '/\bya\s+est[aá]\s+en\s+(?:tu\s+)?(?:carrito|pedido)\b/u',
        ];

        $regexesQuitar = [
            '/\b(?:listo|perfecto|hecho|ya|ok)[\s,]+(?:te\s+)?(?:lo|la|los|las)\s+(?:quit[eé]|elimin[eé]|borr[eé]|remov[íi])(?:mos)?\b/u',
            '/\b(?:quitad[oa]|eliminad[oa]|borrad[oa]|removid[oa])\s+(?:del?\s+)?(?:carrito|pedido)\b/u',
            '/\b(?:te\s+)?(?:lo|la|los|las)\s+(?:quit[eé]|elimin[eé]|borr[eé])\s+(?:del?\s+)?(?:carrito|pedido)\b/u',
            '/\bya\s+(?:no\s+est[aá]|sali[oó])\s+(?:del\s+)?(?:carrito|pedido)\b/u',
        ];

        $afirmaAgregar = false;
        foreach ($regexesAgregar as $r) {
            if (preg_match($r, $lower)) { $afirmaAgregar = true; break; }
        }
        $afirmaQuitar = false;
        foreach ($regexesQuitar as $r) {
            if (preg_match($r, $lower)) { $afirmaQuitar = true; break; }
        }

        if (!$afirmaAgregar && !$afirmaQuitar) {
            return null;
        }

        $productosCarrito = $estado?->productos ?: [];
        $hayProductos = !empty($productosCarrito);

        // Caso 1: dice que AGREGÓ pero carrito vacío → alucinación
        if ($afirmaAgregar && !$hayProductos) {
            return 'AGREGAR_FALSO';
        }

        // Caso 2: dice que QUITÓ pero carrito vacío (nada que quitar) → alucinación
        if ($afirmaQuitar && !$hayProductos) {
            return 'QUITAR_FALSO_VACIO';
        }

        // Caso 3: dice que QUITÓ X pero X sigue en el carrito → no se puede
        // determinar sin info adicional. Por ahora, dejamos pasar.

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
  /**
   * 🌐 API pública: expuesta para que OrderValidatorAgent y otros servicios
   * puedan reutilizar la misma lógica de validación deterministica sin
   * duplicar las reglas (flujo_pedido_orden + ERP lookup + integraciones).
   */
  public function validarOrderDataPublic(array $orderData): array
  {
      return $this->validarDatosObligatoriosPedido($orderData);
  }

  /**
   * Compara dos listas de productos y devuelve true si son IGUALES.
   * Usado para detectar duplicados vs pedidos nuevos.
   */
  private function productosSonIguales(array $existentes, array $nuevos): bool
  {
      $normalizar = function ($lista) {
          return collect($lista)
              ->map(function ($p) {
                  // soporta tanto rows de BD (nombre/cantidad) como orderData (name/quantity)
                  $nombre = mb_strtolower(trim((string) ($p['name'] ?? $p['nombre'] ?? '')));
                  $cantidad = (float) ($p['quantity'] ?? $p['cantidad'] ?? 0);
                  return $nombre . '|' . number_format($cantidad, 2);
              })
              ->filter()
              ->sort()
              ->values()
              ->all();
      };

      $a = $normalizar($existentes);
      $b = $normalizar($nuevos);

      return count($a) === count($b) && $a === $b;
  }

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

                          if ($existeEnErp) {
                              // 🛡️ Cliente YA en SGI — omitir campos opcionales del
                              // perfil personal (email, nombre, telefono). El sistema
                              // los lee del ERP al exportar. NO los exigimos.
                              $activos = array_values(array_diff($activos, ['email', 'nombre', 'telefono']));
                          } else {
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

  /**
   * 🛡️ GUARD ANTI-ALUCINACIÓN DE AGREGADO:
   * Cuando el LLM responde con frases como "agregué X kilos de Y" pero NO llamó
   * la tool agregar_producto_al_pedido, el producto queda fuera del carrito y
   * el pedido se cierra incompleto.
   *
   * Este parser detecta esas frases en la respuesta y compara con estado.productos.
   * Si el producto mencionado NO está en el estado, llama al handler internamente.
   *
   * Retorna el carrito final (puede haber cambiado si hubo capturas).
   */
  private function capturarAgregadosImplicitos(
      \App\Models\ConversacionWhatsapp $conv,
      string $respuestaBot,
      ?int $connectionId
  ): array {
      $estadoSrv  = app(\App\Services\EstadoPedidoService::class);
      $catalogo   = app(\App\Services\BotCatalogoService::class);
      $sedeId     = $this->obtenerSedeIdDesdeConexion($connectionId);
      $estado     = $estadoSrv->obtener($conv);
      $productosEstado = is_array($estado->productos) ? $estado->productos : [];
      $capturados = 0;

      // Patrones que indican que el bot está afirmando que está/va a agregar algo
      // Cubre presente y pasado: agrego/agregué/agregando, añado/añadí, anoto/anoté,
      // sumo/sumé, te agrego, voy a agregar, agregado:, anotado:, listo (con lista)
      // Y también listas con bullet/dash/check: "• 2 kg X", "- 1 kg X", "✅ 3 lb X"
      $patrones = [
          // Verbos en cualquier conjugación
          '/\b(?:agreg(?:u[ée]|o|ando|amos|ado)|a[ñn]ad(?:[íi]|o|iendo|ido|amos)|anot(?:[ée]|o|ando|ado|amos)|sum(?:[ée]|o|ando|amos)|incluy(?:o|endo|ido|amos)|incorpor(?:o|ando|ado|amos)|te\s+(?:agrego|sumo|anoto|a[ñn]ado))\s*:?\s*(?:los?\s+|las?\s+)?(\d+(?:[.,]\d+)?)\s+(libras?|libra|kilos?|kilo|kg|kl|gramos?|gr|unidades?|unidad|und|varas?|paquetes?|paquete|cajas?)\s+(?:de\s+)?([a-zñáéíóúA-ZÑÁÉÍÓÚ][a-zñáéíóúA-ZÑÁÉÍÓÚ\s\-]+?)(?=[\.\,\:\;\!\?\n\*]|\s*(?:a|por|\$|al|—|-)\s|\s+(?:y|tambi[eé]n)\s|$)/iu',
          // Lista con bullets/dashes/check: "• 2 kg Muslo de Pollo — $31.800"
          // o "✅ 1 kg Milanesa de Res — $27.500"
          // Solo se considera "agregado" si está dentro de bloque que diga "te agrego" o "agregado" o "tu pedido"
      ];

      $matches = [];
      foreach ($patrones as $p) {
          preg_match_all($p, $respuestaBot, $m, PREG_SET_ORDER);
          $matches = array_merge($matches, $m);
      }

      // Patrón adicional: si hay bullets en una lista DESPUÉS de un verbo "agregar",
      // capturar todos los items de la lista
      if (preg_match('/\b(?:agreg(?:u[ée]|o|ando|amos)|a[ñn]ad(?:[íi]|o)|anot(?:[ée]|o)|te\s+(?:agrego|sumo|anoto)|tu\s+pedido|carrito|tienes)\b/iu', $respuestaBot)) {
          // Buscar items tipo: • 2 kg Muslo de Pollo  o  - 1 kg Milanesa
          $patronLista = '/(?:^|\n)\s*[•\*\-✅✓·]\s*(\d+(?:[.,]\d+)?)\s+(libras?|libra|kilos?|kilo|kg|kl|gramos?|gr|unidades?|unidad|und|varas?|paquetes?|paquete|cajas?)\s+(?:de\s+)?\*?\*?([a-zñáéíóúA-ZÑÁÉÍÓÚ][a-zñáéíóúA-ZÑÁÉÍÓÚ\s\-]+?)\*?\*?(?=\s*[—\-]|\s*\$|\s*\n|$)/iu';
          preg_match_all($patronLista, $respuestaBot, $mLista, PREG_SET_ORDER);
          $matches = array_merge($matches, $mLista);
      }

      if (empty($matches)) {
          return $productosEstado;
      }

      $procesados = [];
      foreach ($matches as $m) {
          $cant   = (float) str_replace(',', '.', $m[1]);
          $unit   = mb_strtolower(trim($m[2]));
          $nombre = trim($m[3]);

          // Evitar duplicar procesamientos de la misma match
          $key = mb_strtolower($nombre) . '|' . $cant . '|' . $unit;
          if (isset($procesados[$key])) continue;
          $procesados[$key] = true;

          // Limpiar palabras conectoras al final
          $nombre = preg_replace('/\s+(con|para|en|al|del|de\s+los?|de\s+las?)\s*$/iu', '', $nombre);
          $nombre = trim($nombre);
          if (mb_strlen($nombre) < 3) continue;

          // ¿Ya está en el estado?
          $yaEsta = false;
          foreach ($productosEstado as $p) {
              $nameEst = mb_strtolower((string) ($p['name'] ?? ''));
              $needle  = mb_strtolower($nombre);
              if (str_contains($nameEst, $needle) || str_contains($needle, $nameEst)) {
                  $yaEsta = true;
                  break;
              }
          }
          if ($yaEsta) continue;

          // Validar contra catálogo
          $producto = $catalogo->resolverProducto($nombre, $sedeId);
          if (!$producto) continue;

          // Guard tokens compartidos
          $tokensSolicitados = collect(preg_split('/\s+/', mb_strtolower(\Illuminate\Support\Str::ascii($nombre))))
              ->filter(fn ($t) => mb_strlen($t) >= 4)
              ->values();
          if ($tokensSolicitados->isNotEmpty()) {
              $nombreResuelto = mb_strtolower(\Illuminate\Support\Str::ascii((string) ($producto->nombre ?? '')));
              $compartido = $tokensSolicitados->first(fn ($t) => str_contains($nombreResuelto, $t));
              if (!$compartido) continue;
          }

          Log::warning('🛡️ GUARD: bot alucinó agregado — capturando automáticamente', [
              'conv_id'  => $conv->id,
              'frase'    => $m[0],
              'nombre'   => $nombre,
              'cantidad' => $cant,
              'unidad'   => $unit,
              'matcheado'=> $producto->nombre ?? null,
          ]);

          // Persistir vía el handler oficial (con conversión de unidades)
          $this->procesarAgregarProductoAlPedido(
              $conv,
              'add',
              (string) ($producto->nombre ?? $nombre),
              (string) ($producto->codigo ?? ''),
              $cant,
              $unit,
              $connectionId
          );
          $capturados++;
      }

      if ($capturados > 0) {
          $estado = $estadoSrv->obtener($conv);
          $productosEstado = is_array($estado->productos) ? $estado->productos : [];
      }

      return $productosEstado;
  }

  /**
   * 🛒 Procesa la tool agregar_producto_al_pedido — la primitiva del carrito.
   *
   * Acciones soportadas:
   *   - add    : agregar producto (o sumar si ya existe el mismo)
   *   - update : reemplazar la cantidad de un producto ya en el carrito
   *   - remove : quitar un producto del carrito
   *   - clear  : vaciar el carrito
   *
   * Valida que el producto exista en el catálogo (vía BotCatalogoService::resolverProducto),
   * convierte libras→kg si aplica, y persiste en `estado.productos`. Devuelve el
   * carrito actualizado con totales para que el LLM pueda responderle al cliente.
   */
  private function procesarAgregarProductoAlPedido(
      \App\Models\ConversacionWhatsapp $conv,
      string $action,
      string $name,
      string $code,
      float $quantity,
      string $unitRaw,
      ?int $connectionId,
      string $corte = ''
  ): array {
      $estadoSrv  = app(\App\Services\EstadoPedidoService::class);
      $catalogo   = app(\App\Services\BotCatalogoService::class);
      $sedeId     = $this->obtenerSedeIdDesdeConexion($connectionId);
      $estado     = $estadoSrv->obtener($conv);
      $productos  = is_array($estado->productos) ? $estado->productos : [];

      // ── Acción CLEAR: vaciar el carrito ────────────────────────────────
      if ($action === 'clear') {
          $estado->productos = [];
          $estado->save();
          return [
              'ok'              => true,
              'action'          => 'clear',
              'mensaje_sugerido'=> 'Listo, vacié tu carrito. ¿Empezamos de nuevo? 🙌',
              'carrito'         => [],
              'subtotal'        => 0,
              'total_items'     => 0,
          ];
      }

      // ── Validar nombre presente para add/update/remove ─────────────────
      if ($name === '' && $code === '') {
          return [
              'ok'              => false,
              'action'          => $action,
              'error'           => 'Falta el nombre del producto. Llama buscar_productos primero.',
              'mensaje_sugerido'=> 'Necesito que me digas qué producto querías. ¿Me lo repites?',
          ];
      }

      // ── Resolver producto contra catálogo (igual lógica que confirmar_pedido) ──
      $producto = null;
      $resueltoVia = null;

      if ($code !== '') {
          $existeCode = \App\Models\Producto::where('codigo', $code)->exists();
          if ($existeCode) {
              $producto = $catalogo->resolverProducto($code, $sedeId);
              if ($producto && strcasecmp(trim((string) $producto->codigo), $code) === 0) {
                  $resueltoVia = 'codigo';
              } else {
                  $producto = null;
              }
          }
      }
      if (!$producto && $name !== '') {
          $producto = $catalogo->resolverProducto($name, $sedeId);
          if ($producto) $resueltoVia = 'nombre';
      }

      // Guard de tokens compartidos (anti-alucinación)
      if ($producto && $name !== '') {
          $tokensSolicitados = collect(preg_split('/\s+/', mb_strtolower(\Illuminate\Support\Str::ascii($name))))
              ->filter(fn ($t) => mb_strlen($t) >= 4)
              ->values();
          if ($tokensSolicitados->isNotEmpty()) {
              $nombreResuelto = mb_strtolower(\Illuminate\Support\Str::ascii((string) ($producto->nombre ?? '')));
              $compartido = $tokensSolicitados->first(fn ($t) => str_contains($nombreResuelto, $t));
              if (!$compartido) {
                  Log::warning('🛡️ [agregar_producto] resolver matcheó sin tokens compartidos — descartado', [
                      'solicitado' => $name,
                      'resuelto'   => $producto->nombre ?? null,
                  ]);
                  $producto = null;
              }
          }
      }

      if (!$producto) {
          return [
              'ok'              => false,
              'action'          => $action,
              'error'           => "Producto '{$name}' no está en el catálogo. Llama buscar_productos para ver opciones reales.",
              'mensaje_sugerido'=> "Mmm, '{$name}' no lo veo en mi catálogo 🤔. Te muestro qué tengo similar.",
          ];
      }

      // 🛡️ BUG-08: Validación de cantidad máxima por producto.
      // Defensa contra cantidades absurdas (999999 kg, etc) que pasarían
      // por el guard del LLM. Configurable via ConfiguracionBot.
      $maxKgPorProducto = (float) (config('services.whatsapp.max_kg_por_producto', 200.0));
      $maxUnidades = (int) (config('services.whatsapp.max_unidades_por_producto', 500));

      // ── Conversión de unidades (igual lógica que confirmar_pedido) ─────
      $unitNorm = $unitRaw;
      $cantidadFinal = $quantity;

      if (in_array($unitRaw, ['lb', 'libra', 'libras', 'librita', 'libritas'], true)) {
          $cantidadFinal = $quantity * 0.5;
          $unitNorm      = 'kg';
      } elseif (in_array($unitRaw, ['g', 'gr', 'gramo', 'gramos'], true)) {
          $cantidadFinal = $quantity / 1000.0;
          $unitNorm      = 'kg';
      } elseif (in_array($unitRaw, ['kg', 'k', 'kl', 'kilo', 'kilos', 'kilogramo'], true)) {
          $unitNorm = 'kg';
      }

      // 🛡️ BUG-08: Rechazar cantidades absurdas.
      $limiteUsado = ($unitNorm === 'kg') ? $maxKgPorProducto : $maxUnidades;
      if ($cantidadFinal > $limiteUsado) {
          Log::warning('🛡️ BUG-08: cantidad absurda rechazada en agregar_producto_al_pedido', [
              'producto'   => $producto->nombre ?? $name,
              'cantidad'   => $cantidadFinal,
              'unidad'     => $unitNorm,
              'limite'     => $limiteUsado,
          ]);
          return [
              'ok'               => false,
              'action'           => $action,
              'error'            => "Cantidad {$cantidadFinal} {$unitNorm} excede el límite ({$limiteUsado}). Pedidos grandes deben ir por canal comercial.",
              'mensaje_sugerido' => "Esa cantidad ({$cantidadFinal} {$unitNorm}) es demasiado grande para pedido normal 😅. Voy a conectarte con nuestro equipo comercial para que te atiendan ese volumen.",
              'derivar_a_humano' => true,
          ];
      }
      if ($cantidadFinal <= 0) {
          return [
              'ok'               => false,
              'action'           => $action,
              'error'            => "La cantidad debe ser mayor a 0.",
              'mensaje_sugerido' => "¿Qué cantidad necesitas? Dime un número mayor a 0.",
          ];
      }

      $precioKg = method_exists($producto, 'precioParaSede')
          ? $producto->precioParaSede($sedeId)
          : (float) ($producto->precio_base ?? $producto->precio ?? 0);

      // ── Aplicar acción ────────────────────────────────────────────────
      $codigoProd = (string) ($producto->codigo ?? '');
      $nombreProd = (string) ($producto->nombre ?? $name);
      $idxExist   = null;
      foreach ($productos as $i => $p) {
          if ((string) ($p['code'] ?? '') === $codigoProd && $codigoProd !== '') {
              $idxExist = $i;
              break;
          }
          if (mb_strtolower((string) ($p['name'] ?? '')) === mb_strtolower($nombreProd)) {
              $idxExist = $i;
              break;
          }
      }

      // ✂️ Validar corte si el producto tiene cortes configurados
      $corteValidado = '';
      if ($producto && $action !== 'remove') {
          try {
              $cortesProd = $producto->cortes()->where('activo', true)->pluck('nombre')->all();
              if (!empty($cortesProd)) {
                  // El producto SÍ tiene cortes — el cliente debe especificar uno
                  if (empty($corte)) {
                      return [
                          'ok'              => false,
                          'action'          => $action,
                          'error'           => 'Este producto requiere especificar un CORTE. Pregunta al cliente cuál prefiere.',
                          'cortes_disponibles' => $cortesProd,
                          'mensaje_sugerido'=> "Tengo *{$producto->nombre}* pero te lo puedo cortar de varias formas: " . implode(', ', $cortesProd) . ". ¿Cuál te tinca?",
                      ];
                  }
                  // Buscar match case-insensitive contra los cortes disponibles
                  $matchCorte = collect($cortesProd)->first(fn ($c) => mb_strtolower($c) === mb_strtolower($corte));
                  if (!$matchCorte) {
                      return [
                          'ok'              => false,
                          'action'          => $action,
                          'error'           => "Corte '{$corte}' no disponible para este producto.",
                          'cortes_disponibles' => $cortesProd,
                          'mensaje_sugerido'=> "Ese corte no lo manejamos para {$producto->nombre}. Los cortes disponibles son: " . implode(', ', $cortesProd),
                      ];
                  }
                  $corteValidado = $matchCorte;
              }
          } catch (\Throwable $e) {
              // Si falla la consulta de cortes, continuar sin corte
          }
      }

      if ($action === 'remove') {
          if ($idxExist !== null) {
              array_splice($productos, $idxExist, 1);
          }
      } elseif ($action === 'update') {
          if ($idxExist !== null) {
              $productos[$idxExist]['quantity'] = $cantidadFinal;
              $productos[$idxExist]['unit']     = $unitNorm;
              if ($corteValidado !== '') $productos[$idxExist]['corte'] = $corteValidado;
          } else {
              // Si no estaba, lo agregamos
              $linea = $this->armarLineaProducto($producto, $cantidadFinal, $unitNorm, $precioKg);
              if ($corteValidado !== '') $linea['corte'] = $corteValidado;
              $productos[] = $linea;
          }
      } else { // add (default)
          if ($idxExist !== null) {
              // Sumar a la cantidad existente
              $productos[$idxExist]['quantity'] = (float) ($productos[$idxExist]['quantity'] ?? 0) + $cantidadFinal;
              if ($corteValidado !== '' && empty($productos[$idxExist]['corte'])) {
                  $productos[$idxExist]['corte'] = $corteValidado;
              }
          } else {
              $linea = $this->armarLineaProducto($producto, $cantidadFinal, $unitNorm, $precioKg);
              if ($corteValidado !== '') $linea['corte'] = $corteValidado;
              $productos[] = $linea;
          }
      }

      // Recalcular subtotal y guardar
      $subtotal = 0;
      foreach ($productos as $p) {
          $subtotal += (float) ($p['subtotal'] ?? ((float) ($p['quantity'] ?? 0) * (float) ($p['precio_unitario'] ?? 0)));
      }

      $estado->productos = $productos;
      $estado->save();
      $estadoSrv->avanzarPaso($estado);

      Log::info('🛒 Producto procesado en carrito', [
          'conv_id'   => $conv->id,
          'action'    => $action,
          'producto'  => $nombreProd,
          'cantidad'  => $cantidadFinal,
          'unit'      => $unitNorm,
          'subtotal'  => $subtotal,
          'carrito_n' => count($productos),
          'via'       => $resueltoVia,
      ]);

      // Resumen para el LLM
      $resumenCarrito = collect($productos)->map(fn ($p) =>
          ($p['quantity'] ?? 0) . ' ' . ($p['unit'] ?? '') . ' ' . ($p['name'] ?? '')
          . ' — $' . number_format((float) ($p['subtotal'] ?? 0), 0, ',', '.')
      )->implode("\n");

      return [
          'ok'               => true,
          'action'           => $action,
          'producto_agregado' => [
              'name'     => $nombreProd,
              'quantity' => $cantidadFinal,
              'unit'     => $unitNorm,
              'precio_kg'=> $precioKg,
          ],
          'carrito'          => $productos,
          'subtotal'         => (int) round($subtotal),
          'subtotal_fmt'     => '$' . number_format($subtotal, 0, ',', '.'),
          'total_items'      => count($productos),
          'mensaje_sugerido' => count($productos) > 0
              ? "✅ Carrito actualizado:\n{$resumenCarrito}\n\nSubtotal: $" . number_format($subtotal, 0, ',', '.') . "\n¿Algo más?"
              : 'Tu carrito quedó vacío.',
      ];
  }

  /**
   * Arma la estructura de una línea de producto para guardar en estado.productos.
   */
  private function armarLineaProducto($producto, float $cantidad, string $unidad, float $precioKg): array
  {
      // Si la unidad es kg, el subtotal es cantidad × precio_kg.
      // Si es unidad/paquete, asumimos precio_kg como precio por unidad.
      $subtotal = $cantidad * $precioKg;
      return [
          'code'            => (string) ($producto->codigo ?? ''),
          'name'            => (string) ($producto->nombre ?? ''),
          'quantity'        => $cantidad,
          'unit'            => $unidad,
          'precio_unitario' => (float) $precioKg,
          'subtotal'        => $subtotal,
      ];
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

      // 🛡️ ANTI-AMBIGÜEDAD: si la dirección es patrón colombiano genérico
      // (CL/CRA + número) Y la ciudad parece ser default (no la mencionó el
      // cliente), pedimos clarificación ANTES de geocodificar. Misma dirección
      // existe en muchos municipios.
      //
      // 🛡️ EXCEPCIÓN: si la conversación YA validó cobertura previamente con
      // esta misma dirección, NO volver a pedir clarificación. El cliente
      // ya confirmó, no le hagamos perder tiempo.
      $coberturaYaValidada = false;
      try {
          if ($telefonoCliente) {
              $tenantId = app(\App\Services\TenantManager::class)->id();
              if ($tenantId) {
                  $tel = preg_replace('/\D+/', '', $telefonoCliente);
                  $conv = \App\Models\ConversacionWhatsapp::where('tenant_id', $tenantId)
                      ->where('telefono_normalizado', $tel)
                      ->orderByDesc('id')->first();
                  if ($conv) {
                      $estadoChk = app(\App\Services\EstadoPedidoService::class)->obtener($conv);
                      if ($estadoChk->cobertura_validada
                          && !empty($estadoChk->direccion)
                          && mb_stripos($direccion, $estadoChk->direccion) !== false) {
                          $coberturaYaValidada = true;
                      }
                  }
              }
          }
      } catch (\Throwable $e) { /* ignore */ }

      if (!$coberturaYaValidada
          && !empty($direccion) && $this->direccionEsGenericaColombiana($direccion)
          && empty($barrio) && $this->ciudadEsDefaultNoMencionada($ciudad, $telefonoCliente)) {

          Log::info('🛡️ Dirección genérica sin ciudad confirmada — pidiendo clarificación', [
              'direccion' => $direccion,
              'ciudad_llm' => $ciudad,
              'telefono'  => $telefonoCliente,
          ]);

          return [
              'cubierta'              => false,
              'requiere_clarificacion'=> true,
              'mensaje_para_cliente'  => "Necesito el *municipio* o *barrio* exacto para validar `{$direccion}` 🙏. "
                  . "La misma dirección puede existir en Bello, Rionegro, Medellín, etc. "
                  . "¿En qué municipio o barrio queda?",
              'instruccion_para_bot'  => "🛑 NO digas 'cubierto' ni 'fuera de cobertura'. El cliente dio una "
                  . "dirección AMBIGUA sin especificar municipio. PIDE al cliente que confirme el municipio o barrio. "
                  . "Usa el mensaje_para_cliente literal. Después llama validar_cobertura otra vez con la ciudad correcta.",
              'mensaje_sugerido'      => "Necesito el municipio o barrio exacto para validar esa dirección 🙏.",
              'metodo_usado'          => 'pedir_clarificacion_ciudad',
          ];
      }

      // 🌟 ESTRATEGIA NUEVA (PREFERIDA): cobertura DIRECTA en sedes
      // Cada sede tiene su propio polígono. SedeResolverService elige la
      // mejor sede (cercanía + abierto) automáticamente.
      if (!empty($direccion) || !empty($barrio)) {
          // 🛡️ Limpiar dirección para geocoding: quitar Apto, Torre, etc.
          // que confunden a los geocoders. Mantener solo vía + número + barrio.
          $direccionLimpia = $this->limpiarDireccionParaGeocoding($direccion);

          // 🛡️ CHECK PREVIO: si la ciudad pasada es de las que tienen duplicados
          // en varios departamentos de Colombia (Barbosa, San Antonio, Santa Rosa,
          // etc.) Y el cliente no especificó departamento → pedir clarificación
          // ANTES de geocodificar para evitar adivinanzas.
          $deptosDuplicados = $this->departamentosDeMunicipioAmbiguo($ciudad ?: '');
          if (!empty($deptosDuplicados) && !$this->mensajeContieneDepartamento($telefonoCliente)) {
              $listaDeptos = implode(', ', $deptosDuplicados);
              Log::info('🛡️ Municipio con duplicados — pidiendo departamento', [
                  'ciudad'        => $ciudad,
                  'departamentos' => $deptosDuplicados,
              ]);
              return [
                  'cubierta'              => false,
                  'requiere_clarificacion'=> true,
                  'mensaje_para_cliente'  => "🤔 *{$ciudad}* existe en varios departamentos: *{$listaDeptos}*.\n\n"
                      . "¿En qué *departamento* queda exactamente?",
                  'instruccion_para_bot'  => "🛑 La ciudad '{$ciudad}' tiene municipios con el mismo nombre en "
                      . "varios departamentos ({$listaDeptos}). NO afirmes cubierto/fuera. Pide al cliente "
                      . "que indique el departamento. Después llama validar_cobertura otra vez con "
                      . "ciudad='{$ciudad}, [Departamento]'.",
                  'mensaje_sugerido'      => "Hay {$ciudad} en varios departamentos — pedir confirmación.",
                  'metodo_usado'          => 'pedir_clarificacion_departamento_municipio_ambiguo',
              ];
          }

          $geocode = app(GeocodingService::class)->geocodificar(
              $direccionLimpia ?: $direccion ?: '',
              $barrio,
              $ciudad ?: 'Bello'
          );

          if ($geocode) {
              // 🛡️ SANITY CHECK CROSS-DEPARTAMENTO: hay ciudades con el mismo
              // nombre en varios departamentos de Colombia. Si Google
              // geocodificó muy lejos de TODAS nuestras sedes (>80 km), es
              // muy probable que sea la ciudad equivocada — pedir confirmación
              // al cliente en vez de afirmar "fuera de cobertura".
              $tenantId = app(\App\Services\TenantManager::class)->id();
              $distMinSede = $this->distanciaMinimaASedesActivas($geocode['lat'], $geocode['lng'], $tenantId);

              if ($distMinSede !== null && $distMinSede > 80) {
                  $displayName = (string) ($geocode['display'] ?? '');
                  Log::warning('🛡️ Geocoding muy lejano — posible ambigüedad cross-departamento', [
                      'direccion'    => $direccion,
                      'ciudad_input' => $ciudad,
                      'distancia_km' => round($distMinSede, 1),
                      'display'      => $displayName,
                  ]);
                  return [
                      'cubierta'              => false,
                      'requiere_clarificacion'=> true,
                      'distancia_km'          => round($distMinSede, 1),
                      'mensaje_para_cliente'  => "Encontré tu dirección en *{$displayName}*, pero queda a "
                          . round($distMinSede) . " km de nuestras sedes 🤔\n\n"
                          . "Hay ciudades con el mismo nombre en varios departamentos de Colombia. "
                          . "¿Confirmas que es esa ubicación exacta? O dime el *departamento* "
                          . "(Antioquia, Cundinamarca, etc.) si quisiste otra.",
                      'instruccion_para_bot'  => "🛑 Google geocodificó la dirección MUY LEJOS de nuestras sedes "
                          . "({$distMinSede} km). Probable ambigüedad: existe ciudad con el mismo nombre en otro "
                          . "departamento. Usa mensaje_para_cliente literal. Si el cliente confirma → es realmente "
                          . "fuera. Si dice 'no, es en Antioquia' → llama validar_cobertura otra vez con el "
                          . "departamento explícito en la ciudad (ej. 'Girardota, Antioquia').",
                      'mensaje_sugerido'      => 'Posible ambigüedad de departamento — pedir confirmación.',
                      'metodo_usado'          => 'pedir_clarificacion_departamento',
                      'coordenadas'           => $geocode,
                  ];
              }

              $coord = $geocode;
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

        // 🛡️ DEDUPLICACIÓN FUERTE: si este cliente YA tiene un pedido NO cancelado
        // creado en los últimos 30 minutos, NO crear duplicado. Devolver info del
        // pedido existente. Esto cubre los casos donde el watchdog (o el LLM) intenta
        // confirmar dos veces el mismo pedido.
        $pedidoRecienteCliente = \App\Models\Pedido::where('telefono_whatsapp', $telNorm)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->whereNotIn('estado', [\App\Models\Pedido::ESTADO_CANCELADO])
            ->orderByDesc('id')
            ->first();
        if ($pedidoRecienteCliente) {
            $minDesde = (int) abs(now()->diffInMinutes($pedidoRecienteCliente->created_at));
            Log::warning('🛡️ confirmar_pedido bloqueado — cliente ya tiene pedido reciente', [
                'from'               => $from,
                'pedido_existente'   => $pedidoRecienteCliente->id,
                'total_existente'    => $pedidoRecienteCliente->total,
                'minutos_desde'      => $minDesde,
            ]);
            $total = '$' . number_format((float) $pedidoRecienteCliente->total, 0, ',', '.');
            return "Tu pedido #{$pedidoRecienteCliente->id} ya está registrado ✅\n\n"
                . "💵 Total: {$total}\n"
                . "Si necesitas algo distinto, cuéntame qué es y te ayudo 🙌";
        }

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

                // 🔄 Fallback 1: buscar en estado del pedido (ConversacionPedidoEstado)
                if (($cedulaOrder === '' || !preg_match('/^\d{6,12}$/', $cedulaOrder)) && $conversacion) {
                    $estadoFb = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
                    if ($estadoFb && !empty($estadoFb->cedula) && preg_match('/^\d{6,12}$/', $estadoFb->cedula)) {
                        $cedulaOrder = $estadoFb->cedula;
                        $orderData['cedula'] = $cedulaOrder;
                        Log::info('🔄 Cédula recuperada del estado del pedido', ['cedula' => $cedulaOrder, 'from' => $from]);
                    }
                }

                // 🔄 Fallback 2: buscar en el cliente existente por teléfono
                if (($cedulaOrder === '' || !preg_match('/^\d{6,12}$/', $cedulaOrder)) && $from) {
                    $telNorm = preg_replace('/\D+/', '', $from);
                    $clienteFb = \App\Models\Cliente::where('telefono_normalizado', $telNorm)->first();
                    if ($clienteFb && !empty($clienteFb->cedula) && preg_match('/^\d{6,12}$/', $clienteFb->cedula)) {
                        $cedulaOrder = $clienteFb->cedula;
                        $orderData['cedula'] = $cedulaOrder;
                        Log::info('🔄 Cédula recuperada del cliente existente', ['cedula' => $cedulaOrder, 'from' => $from]);
                    }
                }

                // 🔄 Fallback 3: buscar en el historial de mensajes del cliente (regex)
                if (($cedulaOrder === '' || !preg_match('/^\d{6,12}$/', $cedulaOrder)) && $conversacion) {
                    $mensajesCliente = $conversacion->mensajes()
                        ->where('rol', 'user')
                        ->latest()
                        ->limit(10)
                        ->pluck('contenido');
                    foreach ($mensajesCliente as $msg) {
                        if (preg_match('/c[eé]dula\s*(?:es\s*)?(\d{6,12})/iu', $msg, $m)) {
                            $cedulaOrder = $m[1];
                            $orderData['cedula'] = $cedulaOrder;
                            Log::info('🔄 Cédula extraída del historial de mensajes', ['cedula' => $cedulaOrder, 'from' => $from, 'msg' => $msg]);
                            break;
                        }
                    }
                }

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

        // 🚨 GUARD CRÍTICO: PEDIDO MAYOR AL UMBRAL → derivar a humano antes de confirmar.
        // Caso real: bot confirmó $21M sin validación. Para pedidos grandes, SIEMPRE
        // que el operador verifique antes de cerrar.
        try {
            $cfgUmbral = \App\Models\ConfiguracionBot::actual();
            $umbralMax = (int) ($cfgUmbral?->pedido_max_auto ?? 500000); // default $500.000
            if ($umbralMax > 0) {
                $totalEstimado = 0;
                foreach (($orderData['products'] ?? []) as $p) {
                    $qty = (float) ($p['quantity'] ?? 0);
                    $precio = (float) ($p['precio_unitario'] ?? $p['precio'] ?? 0);
                    if ($precio > 0 && $qty > 0) {
                        $totalEstimado += $qty * $precio;
                    } else {
                        $totalEstimado += (float) ($p['subtotal'] ?? 0);
                    }
                }
                if ($totalEstimado > $umbralMax) {
                    Log::warning('🚨 GUARD: pedido SUPERA umbral — derivando a humano', [
                        'from'      => $from,
                        'total'     => $totalEstimado,
                        'umbral'    => $umbralMax,
                        'productos' => array_map(fn ($p) => ($p['quantity'] ?? '?') . ' ' . ($p['unit'] ?? '') . ' ' . ($p['name'] ?? '?'), $orderData['products'] ?? []),
                    ]);

                    // Marcar handoff a humano sin crear el pedido
                    if ($conversacion) {
                        try {
                            $conversacion->update([
                                'requiere_humano'        => true,
                                'humano_motivo'          => 'Pedido grande $' . number_format($totalEstimado, 0, ',', '.') . ' (umbral $' . number_format($umbralMax, 0, ',', '.') . ')',
                                'humano_solicitado_at'   => now(),
                                'derivada_at'            => now(),
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning('No se pudo marcar handoff por pedido grande: ' . $e->getMessage());
                        }
                    }

                    $primerNombre = explode(' ', trim((string) $name))[0] ?? '';
                    $saludo = $primerNombre !== '' && !str_contains($primerNombre, '@') ? " {$primerNombre}" : '';
                    $totalFmt = '$' . number_format($totalEstimado, 0, ',', '.');
                    return "Listo{$saludo}, tu pedido suma *{$totalFmt}* — es una cantidad grande así que voy a pasarte con nuestro equipo *Comercial* 🙏\n\n"
                         . "Ellos te confirman disponibilidad, precio final y forma de entrega para asegurar que todo salga bien. Te contactan en breve.";
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Guard pedido_max_auto falló: ' . $e->getMessage());
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

                        // Crear cliente en SGI (con captura de error para encolar si falla)
                        $errorCrear = null;
                        $okCrear = false;
                        try {
                            $okCrear = $clienteSrv->crear($integErp, $datosCrear);
                        } catch (\Throwable $eCrear) {
                            $errorCrear = $eCrear->getMessage();
                        }

                        if (!$okCrear) {
                            // 🔄 ERP CAÍDO O ERROR — encolar para reintentar en background
                            // y NO bloquear el flujo del cliente. El pedido se crea localmente
                            // y se sincroniza con ERP cuando el ERP vuelva.
                            Log::warning('⚠️ Creación de cliente en ERP falló — encolando para reintento', [
                                'datos' => $datosCrear,
                                'error' => $errorCrear,
                            ]);

                            try {
                                app(\App\Services\ErpRetryQueueService::class)->encolarCrearCliente(
                                    tenantId:        $conversacion->tenant_id,
                                    integracionId:   $integErp->id,
                                    datosCliente:    $datosCrear,
                                    conversacionId:  $conversacion->id,
                                    pedidoId:        null, // pedido se crea más abajo
                                    telefono:        $datosCrear['telefono'] ?? $from,
                                    errorOriginal:   $errorCrear ?: 'crear() devolvió false'
                                );
                            } catch (\Throwable $eEnq) {
                                Log::error('💥 ErpRetryQueue::encolarCrearCliente falló: ' . $eEnq->getMessage());
                            }

                            // Continuar — NO retornar el mensaje genérico de error al cliente.
                            // El pedido se crea localmente y el cliente recibe confirmación normal.
                        } else {
                            Log::info('✅ Cliente creado en SGI antes del pedido', [
                                'cedula' => $orderData['cedula'],
                            ]);
                        }
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

        // 🛡️ GUARD ANTI-DUPLICACIÓN POR BD (red de seguridad)
        //
        // Un cliente PUEDE pedir varias veces al día (legítimo). Solo bloqueamos
        // si es claramente el MISMO pedido reactivado, no un pedido nuevo.
        //
        // Criterios para considerar "duplicado" (TODOS deben cumplirse):
        //   1. Hay pedido del mismo teléfono en últimos 10 minutos
        //   2. En estado 'nuevo' (NO 'confirmado', 'preparando', 'entregado' —
        //      esos ya fueron procesados, cualquier nueva confirmación es PEDIDO NUEVO)
        //   3. Y al menos UNA de:
        //      a) El orderData NO trae productos (solo actualiza datos del cliente)
        //      b) Los productos del orderData son IDÉNTICOS al pedido existente
        //
        // Si NO se cumple → flujo normal de creación.
        if (!Cache::has($confirmKey)) {
            try {
                $pedidoReciente = Pedido::where('telefono_whatsapp', $telNorm)
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->where('estado', 'nuevo')
                    ->orderByDesc('id')
                    ->first();

                $esDuplicadoOActualizacion = false;
                if ($pedidoReciente) {
                    $productosNuevos = $orderData['products'] ?? [];

                    // (a) orderData SIN productos = es claramente actualización de datos
                    if (empty($productosNuevos)) {
                        $esDuplicadoOActualizacion = true;
                    } else {
                        // (b) Comparar productos: si son iguales → mismo pedido
                        try {
                            $productosExistentes = $pedidoReciente->productos()->get(['nombre', 'cantidad'])->toArray();
                            $esDuplicadoOActualizacion = $this->productosSonIguales($productosExistentes, $productosNuevos);
                        } catch (\Throwable $eProd) {
                            // si falla la comparación, ser conservador y dejar crear
                            $esDuplicadoOActualizacion = false;
                        }
                    }
                }

                if ($pedidoReciente && $esDuplicadoOActualizacion) {
                    Log::warning('🛡️ ANTI-DUPLICACIÓN: pedido reciente con MISMOS productos — actualizando datos', [
                        'pedido_id' => $pedidoReciente->id,
                        'from'      => $from,
                        'edad_min'  => abs(\Carbon\Carbon::parse($pedidoReciente->created_at)->diffInMinutes(now())),
                    ]);

                    // Actualizar datos del cliente si vienen nuevos en orderData
                    $cambios = [];
                    $nuevoNombre = trim((string) ($orderData['customer_name'] ?? ''));
                    if ($nuevoNombre !== '' && $nuevoNombre !== $pedidoReciente->customer_name && mb_strtolower($nuevoNombre) !== 'test') {
                        $cambios['customer_name'] = $nuevoNombre;
                    }
                    $nuevoEmail = trim((string) ($orderData['email'] ?? ''));
                    if ($nuevoEmail !== '' && $nuevoEmail !== ($pedidoReciente->email ?? '')) {
                        $cambios['email'] = $nuevoEmail;
                    }

                    if (!empty($cambios)) {
                        $pedidoReciente->update($cambios);
                        Log::info('✅ Pedido actualizado con nuevos datos', [
                            'pedido_id' => $pedidoReciente->id,
                            'cambios'   => array_keys($cambios),
                        ]);
                    }

                    $totalFmt = '$' . number_format((float) $pedidoReciente->total, 0, ',', '.');
                    $saludo = $nuevoNombre !== '' ? " {$nuevoNombre}" : '';

                    $msg = "Listo{$saludo} 🙌 Tu pedido #{$pedidoReciente->id} ya está registrado por *{$totalFmt}*.";
                    if (!empty($cambios)) {
                        $msg .= "\n\nActualicé tus datos correctamente ✅";
                    }
                    $msg .= "\n\nCualquier consulta usa este número: *#{$pedidoReciente->id}*";

                    return $msg;
                }

                // Si pedido reciente existe pero con productos DISTINTOS → log y permitir crear
                if ($pedidoReciente && !$esDuplicadoOActualizacion) {
                    Log::info('📝 Cliente con pedido reciente PERO productos distintos — creando pedido nuevo', [
                        'pedido_anterior_id' => $pedidoReciente->id,
                        'from'               => $from,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Guard anti-duplicación falló (no bloquea creación): ' . $e->getMessage());
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
        // 🛡️ Sanitizar pickup_time — la columna hora_entrega es TIME (HH:MM:SS).
        // El LLM a veces pasa "60 min", "1 hora", "30 minutos" que rompen el INSERT.
        // Si el valor no matchea HH:MM[:SS], lo dejamos null (se guarda igual el pedido).
        $pickupTime = null;
        $rawPickup  = trim((string) ($orderData['pickup_time'] ?? ''));
        if ($rawPickup !== '' && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $rawPickup)) {
            $pickupTime = $rawPickup;
        } elseif ($rawPickup !== '') {
            Log::info('🛡️ pickup_time ignorado (formato no válido)', ['raw' => $rawPickup]);
        }
        $telefonoWhatsapp = $this->normalizarTelefono($from);
        // 🛡️ orderData['phone'] puede ser "" string vacío o "<UNKNOWN>" — sanitizar
        $telContactoRaw = $this->sanitizarPlaceholderLLM((string) ($orderData['phone'] ?? ''));
        if ($telContactoRaw === '') $telContactoRaw = $from;
        $telefonoContacto = $this->normalizarTelefono($telContactoRaw);

        // 🛡️ Sanitizar customer_name de placeholders ANTES de cualquier otra cosa
        if (isset($orderData['customer_name'])) {
            $orderData['customer_name'] = $this->sanitizarPlaceholderLLM((string) $orderData['customer_name']);
        }
        // Última red de seguridad: si quedó vacío, usar el WhatsApp
        if (empty($telefonoContacto)) $telefonoContacto = $telefonoWhatsapp;

        // Resolver dirección y barrio desde la respuesta del bot.
        // 🛡️ Sanitizar placeholders del LLM (<UNKNOWN>, null, N/A, etc.)
        $direccion = $this->sanitizarPlaceholderLLM((string) ($orderData['address'] ?? ''));
        $barrio    = $this->sanitizarPlaceholderLLM((string) ($orderData['neighborhood'] ?? ''));

        // 🧠 Detectar ciudad: priorizar campos explícitos del bot.
        // El bot LLM puede mandar la ciudad en cualquiera de estos campos:
        //   - 'city'      (estándar)
        //   - 'location'  (lo que está mandando OpenAI con el schema actual)
        //   - 'ciudad'    (por si en español)
        // Si nada viene, la INFIERE desde el texto de la dirección.
        $ciudadOrden = $this->sanitizarPlaceholderLLM((string) (
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
                    '',
                    $from
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

        // 🚚 DETECTAR TIPO DE ENTREGA (domicilio vs recoger en sede)
        // ─────────────────────────────────────────────────────────────
        // El LLM no siempre envía `pickup:true`. Detectamos pickup también si:
        //   - viene pickup_time válido (hora de recogida)
        //   - viene sede_id explícita
        //   - las notes o payment_method mencionan "recoger/recogida/sede"
        //   - viene address pero NO viene neighborhood (sede usualmente sin barrio)
        $textoEntrega = mb_strtolower(
            (string) ($orderData['notes'] ?? '') . ' ' .
            (string) ($orderData['payment_method'] ?? '')
        );
        // 🛡️ ADEMÁS de notes/payment_method, detectar pickup en address si contiene
        // "sede" o "recoger" — el LLM a veces pone "Sede Principal" como address
        $addressForDetect = (string) ($orderData['address'] ?? '');
        $esPickup = !empty($orderData['pickup'])
            || !empty($orderData['sede_id'])
            || (isset($pickupTime) && $pickupTime !== null)
            || preg_match('/\b(recog(?:er|erlo|erla|emos|ida|ido)|paso\s+por|pasar\s+por|en\s+sede|recoj[oa]|en\s+la\s+sede|recoge\s+en\s+sede)\b/iu', $textoEntrega) === 1
            || preg_match('/^\s*sede(\s|$|:)/iu', $addressForDetect) === 1; // address comienza con "Sede X"

        // 🛡️ FUENTE DE VERDAD: el estado persistente. Si el captador determinista
        //    detectó "recoger" en la conversación, gana sobre lo que el LLM
        //    haya o no enviado en orderData.
        if ($conversacion) {
            try {
                $estadoCheck = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
                if ($estadoCheck->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_RECOGER) {
                    $esPickup = true;
                } elseif ($estadoCheck->metodo_entrega === \App\Models\ConversacionPedidoEstado::METODO_DOMICILIO
                    && !empty($estadoCheck->direccion)) {
                    // Si el estado dice DOMICILIO con dirección válida, fuerza domicilio
                    // (a menos que orderData explícitamente diga pickup=true)
                    if (empty($orderData['pickup'])) $esPickup = false;
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        if ($esPickup) {
            Log::info('🚶 Pedido detectado como RECOGER EN SEDE', [
                'pickup_flag' => $orderData['pickup'] ?? null,
                'sede_id'     => $orderData['sede_id'] ?? null,
                'pickup_time' => $pickupTime,
                'notes'       => $orderData['notes'] ?? null,
            ]);
        }

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
            $unidadRaw = mb_strtolower(trim((string) ($product['unit'] ?? '')));

            // 🛡️ FIX BUG #1 — Cantidad inflada por "X N UND" del nombre del producto.
            // El LLM a veces lee "TROCITOS X 180 Gr X 10 UND" y pone quantity=10
            // cuando el cliente solo pidió 1 paquete. Si quantity coincide EXACTAMENTE
            // con la N del nombre del producto (y unit es UND/Unidad), asumimos 1.
            if ($producto && $cantidad > 1 && in_array($unidadRaw, ['und', 'unidad', 'unidades', 'u'], true)) {
                $nombreProd = (string) ($producto->nombre ?? '');
                if (preg_match('/x\s*(\d+)\s*und/iu', $nombreProd, $mUnd)) {
                    $nUnd = (int) $mUnd[1];
                    if ($nUnd === (int) $cantidad) {
                        Log::warning('🛡️ Cantidad sospechosa: coincide con "X N UND" del nombre — asumiendo 1 paquete', [
                            'producto'        => $nombreProd,
                            'cantidad_origen' => $cantidad,
                            'n_und_nombre'    => $nUnd,
                        ]);
                        $cantidad = 1;
                    }
                }
            }

            // 🛡️ FIX BUG #3 — Conversión libra → kilo automática.
            // El catálogo guarda precio por kilo. Si el cliente pidió "1 libra" o
            // "10 libras", debemos convertir cantidad (1 libra = 0.5 kg) para que
            // el subtotal sea correcto. Antes guardábamos cantidad=1 con unidad=libra
            // y multiplicábamos como si fuera 1 kg.
            $unidadGuardar = $product['unit'] ?? ($producto->unidad ?? 'unidad');
            if (in_array($unidadRaw, ['lb', 'libra', 'libras', 'librita', 'libritas'], true)) {
                $cantidadKg = $cantidad * 0.5;
                Log::info('🔄 Conversión libra→kg aplicada', [
                    'producto'    => $producto->nombre ?? null,
                    'libras'      => $cantidad,
                    'kilos'       => $cantidadKg,
                ]);
                $cantidad = $cantidadKg;
                $unidadGuardar = 'kg';
            } elseif (in_array($unidadRaw, ['g', 'gr', 'gramo', 'gramos'], true)) {
                // Gramos → kg
                $cantidadKg = $cantidad / 1000.0;
                Log::info('🔄 Conversión gramos→kg aplicada', [
                    'producto'  => $producto->nombre ?? null,
                    'gramos'    => $cantidad,
                    'kilos'     => $cantidadKg,
                ]);
                $cantidad = $cantidadKg;
                $unidadGuardar = 'kg';
            }

            if ($producto) {
                $precio = method_exists($producto, 'precioParaSede')
                    ? $producto->precioParaSede($sede?->id)
                    : (float) ($producto->precio_base ?? $producto->precio ?? 0);

                $sub = $precio * $cantidad;
                $subtotalProductos += $sub;

                // 🛡️ BUG-C3: garantizar que SIEMPRE haya nombre de producto en el detalle.
                // Fallback: nombre del catálogo → nombre solicitado por el cliente → código.
                $nombreProducto = trim((string) ($producto->nombre ?? ''));
                if ($nombreProducto === '') {
                    $nombreProducto = trim((string) $nameRaw) ?: ('Producto ' . ($producto->codigo ?? 'SIN_CODIGO'));
                    Log::warning('🛡️ DetallePedido: producto sin nombre en catálogo, usando fallback', [
                        'producto_id' => $producto->id ?? null,
                        'codigo'      => $producto->codigo ?? null,
                        'fallback'    => $nombreProducto,
                    ]);
                }

                // ✂️ Corte: leerlo del estado del pedido si está guardado allí.
                // El bot lo persiste en estado.productos[].corte cuando procesa
                // agregar_producto_al_pedido con el parámetro corte.
                $corteLinea = trim((string) ($product['corte'] ?? ''));

                $productosValidados[] = [
                    'producto_id'     => $producto->id ?? null,
                    'codigo_producto' => $producto->codigo ?? null,
                    'producto'        => $nombreProducto,
                    'cantidad'        => $cantidad,
                    'unidad'          => $unidadGuardar,
                    'corte_nombre'    => $corteLinea ?: null,
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

            // 🛡️ FIX BUG #4 — liberar lock, romper bucle, y avisar al LLM
            // para que NO siga llamando confirmar_pedido con el mismo nombre.
            Cache::forget($confirmKey);
            DB::rollBack();

            // Resetear el paso del estado del pedido para que el bot vuelva a
            // pedir el producto correcto (en vez de quedarse en confirmacion).
            try {
                if ($conversacion && $convService) {
                    $estadoP = app(\App\Services\EstadoPedidoService::class)->obtener($conversacion);
                    if ($estadoP) {
                        $estadoP->paso_actual = \App\Models\ConversacionPedidoEstado::PASO_PRODUCTO;
                        $estadoP->productos = []; // limpiar productos inválidos
                        $estadoP->save();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo resetear estado tras producto inexistente: ' . $e->getMessage());
            }

            $mensaje = "Ups, {$name} 🙏 no manejamos \"{$lista}\" en el catálogo. "
                     . "¿Me confirmas qué producto *sí* llevas? Te paso opciones si me dices "
                     . "qué tipo de carne necesitas (res, cerdo, pollo, pescado...) 💪";

            // Inyectar regla al historial para que el LLM NO repita el mismo nombre
            $conversationHistory[] = [
                'role'    => 'system',
                'content' => "🚫 PRODUCTO INEXISTENTE: \"{$lista}\".\n"
                    . "REGLA DURA: NO vuelvas a llamar `confirmar_pedido` con ese producto. "
                    . "Llama `buscar_productos` con la palabra que dijo el cliente y muéstrale "
                    . "EL NOMBRE EXACTO que aparece en el catálogo (no inventes variantes).",
            ];
            $conversationHistory[] = ['role' => 'assistant', 'content' => $mensaje];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            // Persistir el mensaje en BD para que aparezca en Chat en vivo
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
                                'resultado' => 'rechazado_producto_inexistente',
                                'productos' => $productosNoEncontrados,
                            ],
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('No se pudo persistir mensaje de rechazo de producto: ' . $e->getMessage());
                }
            }

            return $mensaje;
        }

        // 🚚 Costo de envío:
        //   - Si hay zona resuelta → usar costo de la zona
        //   - Si NO hay zona y es DOMICILIO → fallback al costo default de la sede
        //   - Si es pickup → siempre $0
        $costoEnvio = 0;
        if ($esPickup) {
            $costoEnvio = 0;
        } elseif ($zonaCobertura) {
            $costoEnvio = (float) ($zonaCobertura->costo_envio ?? 0);
        } elseif ($sede && (float) ($sede->cobertura_costo_envio ?? 0) > 0) {
            // Fallback: domicilio sin zona resuelta → cobrar costo default de la sede
            $costoEnvio = (float) $sede->cobertura_costo_envio;
            Log::warning('🚚 Domicilio sin zona resuelta — usando costo default de sede', [
                'from'              => $from,
                'sede'              => $sede->nombre,
                'costo_default'     => $costoEnvio,
                'direccion'         => $direccion,
            ]);
        } else {
            Log::error('🚨 Domicilio sin zona y sede sin costo default — pedido tendrá envío $0', [
                'from'      => $from,
                'sede'      => $sede?->nombre,
                'direccion' => $direccion,
            ]);
        }

        // ── CLIENTE: lo resolvemos acá arriba para poder consultar beneficios ──
        // (antes se hacía más abajo, pero necesitamos el $cliente antes)
        $cliente = Cliente::encontrarOCrearPorTelefono(
            $telefonoWhatsapp,
            $orderData['customer_name'] ?? $name
        );

        // 🎁 ¿Tiene beneficio de envío gratis vigente? (ej. por cumpleaños)
        // Aplica si es DOMICILIO (sin importar si la zona se resolvió o no — usamos
        // el costo de envío calculado arriba, que ya incluye el fallback de la sede).
        $beneficioAplicado = null;
        $ahorroEnvio = 0;
        if (!$esPickup && (float) $costoEnvio > 0) {
            $beneficioAplicado = $cliente->beneficioVigente(
                \App\Models\BeneficioCliente::TIPO_ENVIO_GRATIS
            );
            if ($beneficioAplicado) {
                $ahorroEnvio = (float) $costoEnvio;
                Log::info('🎁 Beneficio envío gratis aplicado', [
                    'cliente_id'   => $cliente->id,
                    'beneficio_id' => $beneficioAplicado->id,
                    'ahorro'       => $ahorroEnvio,
                    'origen'       => $beneficioAplicado->origen,
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
        // 🛡️ Sanitizar customer_name: el LLM a veces mete emails, teléfonos,
        // nombres de producto o strings raros como customer_name. Solo aceptamos
        // si parece un nombre persona REAL.
        $customerNameRaw = trim((string) ($orderData['customer_name'] ?? ''));
        $nombreSeguro = $cliente->nombre; // default: mantener el actual

        // 🛡️ Si el nombre actual del cliente ESTÁ contaminado (parece producto),
        // lo descartamos y usamos un fallback genérico.
        if (!\App\Models\Cliente::nombreNoEsProducto($nombreSeguro)) {
            Log::warning('🛡️ cliente->nombre actual contaminado (parece producto), descartando', [
                'cliente_id'      => $cliente->id,
                'nombre_actual'   => $nombreSeguro,
            ]);
            $nombreSeguro = 'Cliente';
        }

        if ($customerNameRaw !== '') {
            $esEmail   = filter_var($customerNameRaw, FILTER_VALIDATE_EMAIL) !== false || str_contains($customerNameRaw, '@');
            $esTel     = preg_match('/^\+?\d[\d\s\-]{6,}$/', $customerNameRaw) === 1;
            $esCedula  = preg_match('/^\d{6,12}$/', $customerNameRaw) === 1;
            $tieneLetras = preg_match('/[a-záéíóúñ]/iu', $customerNameRaw) === 1;
            $largoOk = mb_strlen($customerNameRaw) >= 2 && mb_strlen($customerNameRaw) <= 80;
            $noEsProducto = \App\Models\Cliente::nombreNoEsProducto($customerNameRaw);

            if (!$esEmail && !$esTel && !$esCedula && $tieneLetras && $largoOk && $noEsProducto) {
                $nombreSeguro = $customerNameRaw;
            } else {
                Log::warning('🛡️ customer_name del orderData rechazado (no parece un nombre)', [
                    'customer_name' => $customerNameRaw,
                    'cliente_id'    => $cliente->id,
                    'es_producto'   => !$noEsProducto,
                ]);
            }
        }

        // 🛡️ Si el estado persistente tiene un nombre validado, preferirlo
        // (es la fuente más confiable, ya pasó por captarDeOrderData con guards).
        try {
            $estadoActual = \App\Models\ConversacionPedidoEstado::where('conversacion_id', $conversacion->id)->first();
            if ($estadoActual && !empty($estadoActual->nombre_cliente)
                && \App\Models\Cliente::nombreNoEsProducto($estadoActual->nombre_cliente)) {
                $nombreSeguro = $estadoActual->nombre_cliente;
            }
        } catch (\Throwable $e) {
            // Ignorar — usar el nombre que tenemos
        }

        $datosClienteActualizar = [
            'nombre'              => $nombreSeguro,
            'direccion_principal' => $direccion ?: $cliente->direccion_principal,
            'barrio'              => $barrio ?: $cliente->barrio,
            'zona_cobertura_id'   => $zonaCobertura?->id ?? $cliente->zona_cobertura_id,
        ];

        // 🪪 Guardar cédula si vino en el orderData (desde 'cedula' o 'document_id')
        $cedulaNueva = trim((string) ($orderData['cedula'] ?? $orderData['document_id'] ?? ''));
        if ($cedulaNueva !== '' && \App\Services\EstadoPedidoService::esCedulaTrivial($cedulaNueva)) {
            Log::warning('🛡️ Cédula trivial en confirmar_pedido — ignorada al actualizar cliente', [
                'cedula' => $cedulaNueva,
            ]);
            $cedulaNueva = '';
        }
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

        // 🚚 Si es pickup en sede, NO guardamos dirección de cliente
        // (la dirección es la de la sede misma — no debemos despachar).
        $direccionGuardar = $esPickup ? null : ($direccion ?: null);
        $barrioGuardar    = $esPickup ? null : ($barrio ?: null);
        $zonaGuardar      = $esPickup ? null : $zonaCobertura?->id;

        $pedido = Pedido::create([
            'sede_id'               => $sede?->id,
            'cliente_id'            => $cliente->id,
            'empresa_id'            => $empresaId,
            'fecha_pedido'          => now(),
            'hora_entrega'          => $pickupTime,
            // 🛡️ tipo_entrega: respetar el estado PERSISTENTE del pedido como
            // fuente de verdad final (captador determinista). Si el cliente dijo
            // 'recoger', SIEMPRE pickup, sin importar otros campos.
            'tipo_entrega'          => $this->resolverTipoEntregaFinal($esPickup, $conversacion, $orderData),
            'estado'                => 'nuevo',
            'fecha_estado'          => now(),
            'programado_para'       => $programadoPara, // null si está abierto, timestamp si está cerrado y acepta programados
            'observacion_estado'    => $programadoPara
                ? "Pedido programado para preparación: " . $programadoPara->format('d/m/Y H:i')
                : 'Pedido creado automáticamente desde WhatsApp',
            'total'                 => $totalCalculado,
            'subtotal'              => $subtotalProductos,
            'costo_envio'           => $esPickup ? 0 : $costoEnvio,
            'beneficio_cliente_id'  => $beneficioAplicado?->id,
            'notas'                 => $notas,
            'cliente_nombre'        => $nombreSeguro,
            'direccion'             => $direccionGuardar,
            'barrio'                => $barrioGuardar,
            'lat'                   => $esPickup ? null : $pedidoLat,
            'lng'                   => $esPickup ? null : $pedidoLng,
            'zona_cobertura_id'     => $zonaGuardar,
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

        // Marcar beneficio como usado si fue aplicado + trazabilidad en el pedido
        if ($beneficioAplicado) {
            $beneficioAplicado->update([
                'usado_at'  => now(),
                'pedido_id' => $pedido->id,
            ]);

            // 🎁 Trazabilidad explícita: nota descriptiva + observación + relación
            $origenTxt = match ($beneficioAplicado->origen) {
                \App\Models\BeneficioCliente::ORIGEN_CUMPLEANOS => 'cumpleaños',
                \App\Models\BeneficioCliente::ORIGEN_PROMO      => 'promoción',
                \App\Models\BeneficioCliente::ORIGEN_MANUAL     => 'beneficio manual',
                default => 'beneficio',
            };
            $ahorroStr = '$' . number_format($ahorroEnvio, 0, ',', '.');
            $notaTraza = "🎁 ENVÍO GRATIS aplicado por {$origenTxt}. "
                       . "Ahorro: {$ahorroStr}. "
                       . "Beneficio ID: #{$beneficioAplicado->id}";

            // Agregar a notas del pedido (concatenar si ya existe)
            $notasActuales = trim((string) $pedido->notas);
            $pedido->update([
                'notas'                => $notasActuales !== ''
                    ? $notasActuales . "\n\n" . $notaTraza
                    : $notaTraza,
                'beneficio_cliente_id' => $beneficioAplicado->id,
                'observacion_estado'   => ($pedido->observacion_estado ? $pedido->observacion_estado . ' | ' : '')
                    . "🎁 Envío gratis ({$origenTxt})",
            ]);

            // Registrar en historial de estados para auditoría completa
            try {
                $pedido->registrarHistorial(
                    estadoNuevo: $pedido->estado,
                    estadoAnterior: $pedido->estado,
                    titulo: '🎁 Envío gratis aplicado',
                    descripcion: "Beneficio de {$origenTxt} aplicado al pedido. Ahorro: {$ahorroStr}.",
                );
            } catch (\Throwable $e) {
                Log::warning('No se pudo registrar historial de beneficio: ' . $e->getMessage());
            }
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

        $mensajeFinal = $this->construirMensajeConfirmacionPedido($pedido, $orderData, $name, $beneficioAplicado);

        // 💾 PERSISTIR el mensaje de confirmación en mensajes_whatsapp.
        // Sin esto el cliente recibe el mensaje por WhatsApp pero NO queda
        // registrado en la conversación interna (Chat en vivo, auditoría, etc.).
        try {
            if ($conversacion && $convService) {
                $convService->agregarMensaje(
                    $conversacion,
                    \App\Models\MensajeWhatsapp::ROL_ASSISTANT,
                    $mensajeFinal,
                    [
                        'tipo' => 'tool_call',
                        'meta' => [
                            'tool'      => 'confirmar_pedido',
                            'resultado' => 'pedido_creado',
                            'pedido_id' => $pedido->id,
                            'total'     => (float) $pedido->total,
                            'wompi_ref' => $pedido->wompi_reference,
                        ],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo persistir mensaje de confirmación: ' . $e->getMessage());
        }

        // También al cache de historial de conversación (para que el LLM no
        // intente re-confirmar en el siguiente turno)
        try {
            if (isset($cacheKey) && $cacheKey) {
                $hist = Cache::get($cacheKey, []);
                $hist[] = ['role' => 'assistant', 'content' => $mensajeFinal];
                Cache::put($cacheKey, $hist, now()->addMinutes(45));
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo actualizar cache historial post-confirmación: ' . $e->getMessage());
        }

        return $mensajeFinal;
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

        // 🎁 Beneficio aplicado (línea opcional con mensaje cálido según origen)
        $beneficioTxt = '';
        if ($beneficioAplicado) {
            $ahorroBeneficio = 0;
            // Calcular el ahorro: si el pedido tiene costo_envio en BD, ese es el ahorro original
            // (porque se restauró a 0 con el beneficio). Si no, leemos del modelo.
            try {
                // El pedido ya fue guardado con costo_envio=0 por el beneficio.
                // Para mostrar el ahorro, leemos del beneficio o del cliente.
                $sede = $pedido->sede;
                $costoOriginal = (float) ($sede?->cobertura_costo_envio ?? 0);
                if ($costoOriginal === 0.0 && $pedido->zona_cobertura_id) {
                    $zona = \App\Models\ZonaCobertura::find($pedido->zona_cobertura_id);
                    $costoOriginal = (float) ($zona?->costo_envio ?? 0);
                }
                $ahorroBeneficio = $costoOriginal;
            } catch (\Throwable $e) { /* ignore */ }

            $ahorroStr = $ahorroBeneficio > 0
                ? ' (ahorraste $' . number_format($ahorroBeneficio, 0, ',', '.') . ')'
                : '';

            $beneficioTxt = match ($beneficioAplicado->origen) {
                \App\Models\BeneficioCliente::ORIGEN_CUMPLEANOS =>
                    "🎂 *¡FELIZ CUMPLEAÑOS!* Tu *envío va GRATIS*{$ahorroStr} — disfruta de tu día con La Hacienda. 🎉\n",
                \App\Models\BeneficioCliente::ORIGEN_PROMO =>
                    "🎁 *Envío GRATIS aplicado* por promoción{$ahorroStr}.\n",
                \App\Models\BeneficioCliente::ORIGEN_MANUAL =>
                    "🎁 *Envío GRATIS aplicado* como regalo de la empresa{$ahorroStr}.\n",
                default =>
                    "🎁 *Envío GRATIS aplicado*{$ahorroStr}.\n",
            };
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
        ?int $sedeId = null,
        ?string $from = null
    ): string {
        /** @var BotPromptService $promptService */
        $promptService = app(BotPromptService::class);

        // Construir contexto con todas las variables resueltas
        $contexto = $promptService->construirContexto(
            $name,
            $sedeId,
            $infoEmpresa,
            $pedidosInfo,
            $ansInfo,
            $from
        );

        $config = \App\Models\ConfiguracionBot::actual();

        // Si el usuario activó "prompt personalizado" y guardó algo, usarlo.
        // Si no, usar la plantilla GENÉRICA dinámica (con variables {tenant_nombre},
        // {ciudad}, etc.) en lugar de la legacy con "La Hacienda" hardcoded.
        // Así cada tenant funciona out-of-the-box sin que tengan que personalizar.
        $base = ($config->usar_prompt_personalizado && !empty(trim($config->system_prompt ?? '')))
            ? $config->system_prompt
            : BotPromptService::plantillaGenerica();

        // 💰 PROMPT CACHING: las vars VOLÁTILES (fecha, hora, estado sede,
        // memoria por turno) cambian cada minuto y si se renderizan inline
        // dentro del prompt, invalidan el cache de Anthropic en cada request.
        // Las sacamos del cuerpo (poniéndolas vacías) y las re-inyectamos
        // al final como footer separado por <<<CACHE_BREAK>>>. Anthropic
        // splittea ahí y cachea solo lo estable.
        $varsVolatiles = [
            'fecha_actual', 'hora_actual', 'saludo_hora',
            'sede_estado_actual',
            'memoria_cliente', 'memoria_conversacion',
            'historial_cliente',
        ];
        $contextoEstable = $contexto;
        foreach ($varsVolatiles as $k) {
            $contextoEstable[$k] = ''; // las anulamos para el render del cuerpo
        }

        $prompt = $promptService->renderizar($base, $contextoEstable);

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

        // 🛡️ REGLAS DURAS DE ENFORCEMENT (siempre activas, NO configurables).
        // El cliente DEBE seguir el flujo del bot — no al revés. El bot guía con asertividad.
        $prompt .= "\n\n═══════════════════════════════════════════════════════════════════════════════\n"
                 . "# 🛡️ REGLAS DURAS DE FLUJO (EL CLIENTE SE ADAPTA AL BOT)\n\n"
                 . "Tú GUÍAS la conversación, NO al revés. El cliente debe cumplir el flujo que tú diriges.\n\n"
                 . "1. **VARIANTES OBLIGATORIAS**: si llamas `buscar_productos` y el resultado tiene 2+\n"
                 . "   variantes, el cliente DEBE elegir una específica antes de seguir. Si el cliente\n"
                 . "   cambia de tema sin elegir, RECUÉRDALE: 'Antes de seguir, ¿cuál variante de [X] te\n"
                 . "   llevas? Te mostré: [opciones]. Necesito que me digas cuál para agregarlo.'\n\n"
                 . "2. **CORTES OBLIGATORIOS**: si el producto tiene `cortes_disponibles`, el cliente\n"
                 . "   DEBE especificar el corte. Si pregunta por una preparación (guiso, asado, molida,\n"
                 . "   chicharrón, milanesa), MAPÉALA con el corte que mejor encaje:\n"
                 . "   - 'guiso/sancocho' → Goulash (cubos para guiso)\n"
                 . "   - 'asado/parrilla' → Corte argentino o Churrasco\n"
                 . "   - 'molerlo/molida' → Molida\n"
                 . "   - 'chicharrón' → Para barril\n"
                 . "   - 'milanesa/filete' → Churrasco o Mariposa\n"
                 . "   - 'chuletas/tajadas' → En tajadas\n"
                 . "   Ofrécelo directo: 'Sí, te lo dejo en corte Goulash que es cubos para guiso 🙌'\n\n"
                 . "3. **CANTIDAD OBLIGATORIA**: si el cliente dice 'quiero pollo' sin cantidad, pídela\n"
                 . "   junto con la variante en el MISMO mensaje. NO esperes 2 turnos.\n\n"
                 . "4. **DATOS OBLIGATORIOS**: cédula, dirección (si domicilio), método de pago.\n"
                 . "   Si el cliente da una dirección ambigua tipo 'por la 50 cerca al parque',\n"
                 . "   PIDE EXACTITUD: 'Necesito la dirección exacta: calle, número y barrio.'\n\n"
                 . "5. **NO DERIVES POR PREGUNTAS DE PREPARACIÓN/CORTE**: aunque parezca complejo,\n"
                 . "   mapéalo con un corte del catálogo o di honestamente que no se hace.\n\n"
                 . "6. **NO ACEPTES INFO VAGA**: 'me das 1 kilo de carne' es vago. Pide:\n"
                 . "   ¿qué tipo? (res, cerdo, pollo) → ¿qué corte específico? → ¿con qué preparación?\n\n"
                 . "REGLA DE ORO: cada turno tuyo debe MOVER al cliente hacia el cierre del pedido.\n"
                 . "Si el cliente divaga, retómalo: 'Volviendo a tu pedido, me faltaba saber [X]'.\n";

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
                 . "  Sigue el orden del orquestador. NO saltes pasos. NO confirmes pedidos sin todos los datos.\n\n"

                 . "## 8. FACTURACIÓN ELECTRÓNICA — NO DERIVAR, CAPTURAR\n"
                 . "  Si el cliente menciona 'factura', 'facturación electrónica', 'factura con NIT', etc:\n"
                 . "    • NO llames `derivar_a_departamento`.\n"
                 . "    • Pídele EN UN SOLO MENSAJE: número de NIT/cédula del facturado + razón social + correo.\n"
                 . "    • Guarda esos datos en las notas del pedido (campo `notes` en confirmar_pedido).\n"
                 . "    • Luego continúa con el flujo normal (cédula del cliente, confirmación, etc.).\n"
                 . "  El equipo de facturación generará la factura desde el sistema admin con esos datos.\n"
                 . "  Si el cliente SE RETRACTA ('sin factura', 'no, déjalo así', 'entonces no'), retoma el flujo de pedido directo.\n\n"

                 . "## 9. TIEMPO DE ENTREGA — VALOR REAL DEL SISTEMA\n"
                 . "  Si el cliente pregunta '¿en cuánto me entregan?', '¿cuánto demora?', '¿tiempo de entrega?':\n"
                 . "    • Si YA validaste cobertura en este flujo → usa el `tiempo_min` que devolvió `validar_cobertura`.\n"
                 . "    • Si NO → llama `consultar_zonas_cobertura` y usa el `tiempo_default_sede_min` o el `tiempo_min`\n"
                 . "      de la zona donde está la dirección del cliente.\n"
                 . "    • NUNCA digas '45 min', '1 hora', '30 minutos' sin haber consultado.\n"
                 . "    • Si no tienes la dirección aún, responde: 'Apenas me compartas la dirección te confirmo el tiempo exacto'.\n\n"

                 . "## 10. 🛒 PRODUCTOS — REGLA CRÍTICA ANTI-CONFUSIÓN\n"
                 . "  SOLO agrega al pedido los productos que el CLIENTE mencionó EXPLÍCITAMENTE por nombre.\n"
                 . "  NUNCA agregues un producto SIMILAR o RELACIONADO si el cliente no lo nombró.\n\n"
                 . "  EJEMPLOS DE LO QUE NUNCA DEBES HACER:\n"
                 . "    ❌ Cliente: '10 chorizos' → tú agregas TROCITOS DE POLLO (porque vienen en paquete de 10)\n"
                 . "    ❌ Cliente: 'pollo' → tú agregas pollo + costillas (porque van juntos en parrilla)\n"
                 . "    ❌ Cliente: 'asado' → tú agregas chorizo, morcilla, papas, sin que los pida\n\n"
                 . "  REGLAS:\n"
                 . "    1. Si '10 chorizos' → busca el producto 'chorizo' y registra **cantidad = 10**\n"
                 . "    2. Si el catálogo solo tiene 'CHORIZO * UND' (unidad), entonces son 10 unidades — NO busques otro\n"
                 . "    3. Si NO encuentras exactamente lo que pidió → dile: 'No tengo X, ¿quieres en su lugar Y?' (sugiere UNA opción)\n"
                 . "    4. NUNCA llames `buscar_productos` con sinónimos creativos ('chuzos', 'trocitos') si el cliente dijo 'chorizos'\n"
                 . "    5. Antes de invocar `confirmar_pedido`, MUESTRA el resumen al cliente y pide CONFIRMACIÓN explícita\n"
                 . "       con las cantidades correctas. Si dice 'no, está mal' → escucha y corrige.\n\n"

                 . "## 11. 🪪 IDENTIDAD DEL CLIENTE — SIEMPRE VERIFICA\n"
                 . "  El `name` del WhatsApp (display name) NO siempre es el cliente real:\n"
                 . "    - Una persona puede usar el celular de otra (familia, amigo, recados)\n"
                 . "    - El cliente local guardado puede ser de otra persona\n"
                 . "  Si en los DATOS YA CAPTURADOS aparece un nombre y al hablar con el cliente este no coincide,\n"
                 . "  o tienes dudas, **pregunta de nuevo nombre + cédula** antes de cerrar pedido.\n"
                 . "  Mejor preguntar 1 vez más que registrar pedido a nombre equivocado.\n";

        // 💰 FOOTER VOLÁTIL — separado por <<<CACHE_BREAK>>> para que TODO lo
        // anterior se cachee en Anthropic. Todo lo que cambia por turno
        // (fecha/hora) o por conversación (memoria/historial) va aquí.
        $fechaActual    = $contexto['fecha_actual']        ?? '';
        $horaActual     = $contexto['hora_actual']         ?? '';
        $saludoHora     = $contexto['saludo_hora']         ?? '';
        $sedeEstadoNow  = $contexto['sede_estado_actual']  ?? '';
        $memCliente     = $contexto['memoria_cliente']     ?? '';
        $memConv        = $contexto['memoria_conversacion'] ?? '';
        $histCli        = $contexto['historial_cliente']   ?? '';

        $prompt .= "\n\n<<<CACHE_BREAK>>>\n\n"
                 . "═══════════════════════════════════════════════════════════════════════════════\n"
                 . "# 📅 CONTEXTO ACTUAL DEL TURNO (volátil — cambia cada mensaje)\n\n"
                 . "Hoy es **{$fechaActual}** ({$horaActual}). Saludo apropiado: {$saludoHora}.\n";

        if ($sedeEstadoNow !== '') {
            $prompt .= "\nEstado de la sede ahora: **{$sedeEstadoNow}**\n";
        }

        if (trim($memCliente) !== '') {
            $prompt .= "\n# 🧠 MEMORIA DEL CLIENTE\n{$memCliente}\n";
        }

        if (trim($memConv) !== '') {
            $prompt .= "\n# 💬 MEMORIA DE LA CONVERSACIÓN\n{$memConv}\n";
        }

        if (trim($histCli) !== '') {
            $prompt .= "\n# 📋 HISTORIAL DE PEDIDOS PREVIOS\n{$histCli}\n";
        }

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

        // 🛒 Tool: agregar_producto_al_pedido — siempre disponible
        // CADA vez que el cliente confirma un producto + cantidad + unidad, el LLM
        // DEBE llamar esta tool para persistir en el carrito. Sin esto el estado
        // queda vacío y el pedido al final falla o crea duplicados.
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => 'agregar_producto_al_pedido',
                'description' => '🛒 Persiste/modifica UN producto en el carrito del cliente. '
                    . '⚠️ CRÍTICO: si el cliente menciona N productos en un mismo mensaje '
                    . '(ej: "4 libras de tocino Y 2 libras de costilla"), DEBES llamar esta tool '
                    . 'N VECES en paralelo (una por cada producto). NUNCA agrupes varios productos '
                    . 'en una sola llamada — un producto = una llamada. '
                    . 'LLAMA SIEMPRE que el cliente confirme un producto O pida QUITARLO/MODIFICAR la cantidad. '
                    . 'NUNCA respondas "listo, agregado/quitado" sin invocar esta tool primero — el sistema valida el resultado real. '
                    . 'Acciones soportadas: '
                    . '`add` = agregar producto al carrito (default), '
                    . '`update` = actualizar la cantidad de un producto ya agregado, '
                    . '`remove` = QUITAR un producto del carrito (llamar SIEMPRE cuando el cliente diga "quita X", "ya no quiero X", "elimina X"), '
                    . '`clear` = vaciar el carrito completo (cuando el cliente diga "cancela todo", "empezar de nuevo"). '
                    . 'El sistema valida que el producto exista en el catálogo, convierte libras→kg si aplica '
                    . 'y devuelve el resumen del carrito actual con total. NO debes decirle al cliente '
                    . '"agregado/quitado" antes de invocar la tool; el sistema te devuelve el subtotal real.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'name' => [
                            'type'        => 'string',
                            'description' => 'Nombre EXACTO del producto del catálogo (devuelto por buscar_productos). Si no llamaste buscar_productos primero, NO inventes el nombre.',
                        ],
                        'quantity' => [
                            'type'        => 'number',
                            'description' => 'Cantidad numérica que pidió el cliente. Para "media libra" → 0.5. Para "3 kilos y medio" → 3.5.',
                        ],
                        'unit' => [
                            'type'        => 'string',
                            'description' => 'Unidad tal cual la dijo el cliente: "libra", "kilo", "kg", "gramo", "unidad", "paquete". El backend convierte a kg si es por peso.',
                        ],
                        'code' => [
                            'type'        => 'string',
                            'description' => 'Código SKU del producto del catálogo (opcional pero recomendado para precisión).',
                        ],
                        'corte' => [
                            'type'        => 'string',
                            'description' => '✂️ CORTE solicitado por el cliente (ej: "Entero", "Mariposa", "Medallones", "Goulash"). '
                                . 'OBLIGATORIO si el producto tiene cortes_disponibles (devueltos por buscar_productos o info_producto). '
                                . 'Si NO sabes el corte y el producto los tiene, NO llames esta tool aún — primero pregunta al cliente. '
                                . 'Si el producto NO tiene cortes (ej: chorizo, salchichas), deja este campo vacío.',
                        ],
                        'action' => [
                            'type'        => 'string',
                            'enum'        => ['add', 'update', 'remove', 'clear'],
                            'description' => 'Acción a realizar. Default: add.',
                        ],
                    ],
                    'required' => ['action'],
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
     * 🏢 También libera la derivación a departamento para que la conversación
     * vuelva al pool general (todos los agentes pueden tomarla de nuevo).
     */
    public function devolverAlBot(Request $request)
    {
        $data = $request->validate(['conversacion_id' => 'required|integer|exists:conversaciones_whatsapp,id']);
        $conv = \App\Models\ConversacionWhatsapp::findOrFail($data['conversacion_id']);
        $conv->update([
            'atendida_por_humano' => false,
            'departamento_id'     => null,
            'derivada_at'         => null,
        ]);
        return response()->json([
            'status'              => 'ok',
            'atendida_por_humano' => false,
            'departamento_id'     => null,
        ]);
    }

    /*
    |==========================================================================
    | WHATSAPP API
    |==========================================================================
    */

    private function enviarRespuestaWhatsapp(string $from, string $reply, $connectionId = null): bool
    {
        // 🛡️ GUARD: solo enviar a números reales de WhatsApp. El espejo del
        // widget de chat web usa "telefono_normalizado" tipo "w<hash>" que
        // NO es número y la API rechaza. Antes esto ensuciaba la cola con
        // 76 mensajes fallidos a "w0c098ed2154a" etc.
        $telNum = preg_replace('/\D+/', '', $from);
        if ($telNum === '' || strlen($telNum) < 8 || str_starts_with($from, 'w')) {
            Log::info('🌐 Conversación de widget — NO enviar por WhatsApp', [
                'from'    => $from,
                'preview' => mb_substr($reply, 0, 80),
            ]);
            return true; // retornamos true para no romper el flujo del bot
        }

        // 🟢 RUTA META: si el connectionId viene con prefijo "meta:" o el tenant
        // actual usa Meta como provider, enviar por Meta Cloud API (texto libre
        // permitido porque el cliente acaba de escribir → ventana 24h abierta).
        $vieneDeMeta = is_string($connectionId) && str_starts_with($connectionId, 'meta:');
        $tenant = app(\App\Services\TenantManager::class)->current();
        $tenantUsaMeta = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;

        if ($vieneDeMeta || $tenantUsaMeta) {
            try {
                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarTexto($from, $reply, $tenant?->id);
                Log::info('📤 BOT → Meta', ['to' => $from, 'ok' => $ok, 'preview' => mb_substr($reply, 0, 60)]);
                return $ok;
            } catch (\Throwable $e) {
                Log::error('Meta bot reply falló: ' . $e->getMessage());
                if (!$vieneDeMeta) return false; // si no hay fallback claro, abortar
            }
        }

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

            // 🛡️ Guardar en cola para reintentar cuando WhatsApp esté CONNECTED.
            // No se pierde el mensaje aunque la sesión esté caída.
            $this->encolarMensajeSalida($from, $connectionId, $payload, "HTTP {$response->status()}: " . mb_substr((string) $rawBody, 0, 500));

            return false;
        } catch (\Throwable $e) {
            Log::error('❌ ERROR ENVIANDO A WHATSAPP', [
                'error' => $e->getMessage(),
                'phone' => $from,
            ]);

            // 🛡️ Excepción de red/timeout → encolar para reintentar
            $this->encolarMensajeSalida($from, $connectionId, $payload ?? [], 'Excepción: ' . $e->getMessage());

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
        // 🟢 RUTA META: si el connectionId viene con prefijo "meta:" o el tenant
        // actual usa Meta, mandar imagen por Meta Cloud API.
        $vieneDeMeta = is_string($connectionId) && str_starts_with($connectionId, 'meta:');
        $tenant = app(\App\Services\TenantManager::class)->current();
        $tenantUsaMeta = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;

        if ($vieneDeMeta || $tenantUsaMeta) {
            try {
                $ok = app(\App\Services\Meta\MetaWhatsappCloudService::class)
                    ->enviarImagen($from, $imagenUrl, $caption ?: null, $tenant?->id);
                Log::info('📷 BOT imagen → Meta', ['to' => $from, 'ok' => $ok, 'url' => $imagenUrl]);
                return $ok;
            } catch (\Throwable $e) {
                Log::error('Meta bot imagen falló: ' . $e->getMessage());
                if (!$vieneDeMeta) return false;
            }
        }

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

    /**
     * 🛡️ Encola un mensaje saliente que falló para reintentarlo después
     * (cuando WhatsApp vuelva a CONNECTED). Evita perder mensajes durante
     * cortes de la sesión.
     */
    private function encolarMensajeSalida(string $telefono, $connectionId, array $payload, string $error): void
    {
        try {
            // 🛡️ GUARD: el "teléfono" debe ser un número real para WhatsApp.
            // El widget de chat genera espejos en conversaciones_whatsapp con
            // telefono_normalizado = "w<hash>" (no es número). Si esto llega
            // acá, NO encolamos — WhatsApp lo rechazará 12 veces y ensucia la cola.
            $telNum = preg_replace('/\D+/', '', $telefono);
            if ($telNum === '' || strlen($telNum) < 8 || str_starts_with($telefono, 'w')) {
                Log::warning('🚫 NO encolando — teléfono inválido (probablemente espejo del widget)', [
                    'telefono'  => $telefono,
                    'preview'   => mb_substr(($payload['body'] ?? ''), 0, 80),
                ]);
                return;
            }

            $tenantId = app(\App\Services\TenantManager::class)->id();

            // Buscar conversación asociada por teléfono (para poder mostrarla en monitoreo)
            $convId = null;
            try {
                $telNorm = preg_replace('/\D+/', '', $telefono);
                $convId = \App\Models\ConversacionWhatsapp::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($telNorm, $telefono) {
                        $q->where('telefono_normalizado', $telNorm)
                          ->orWhere('telefono', $telefono);
                    })
                    ->orderByDesc('id')
                    ->value('id');
            } catch (\Throwable $e) { /* ignore */ }

            \Illuminate\Support\Facades\DB::table('mensajes_salida_pendientes')->insert([
                'tenant_id'         => $tenantId,
                'conversacion_id'   => $convId,
                'telefono'          => $telefono,
                'connection_id'     => is_numeric($connectionId) ? (int) $connectionId : null,
                'whatsapp_id'       => $payload['whatsappId'] ?? null,
                'payload'           => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'intentos'          => 0,
                'ultimo_error'      => mb_substr($error, 0, 1000),
                'proximo_intento_at'=> now()->addSeconds(15), // primer reintento en 15s
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            Log::info('📬 Mensaje saliente encolado para reintento', [
                'telefono' => $telefono,
                'conv_id'  => $convId,
                'error'    => mb_substr($error, 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo encolar mensaje saliente: ' . $e->getMessage());
        }
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
