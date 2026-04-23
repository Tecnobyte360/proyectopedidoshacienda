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

        $from    = $data['chat']['phone'] ?? $data['from'] ?? $data['phoneNumber'] ?? null;
        $name    = $data['chat']['name'] ?? $data['name'] ?? 'Cliente';
        $message = trim(
            $data['mensaje']['body'] ?? $data['body'] ?? $data['message'] ?? $data['text'] ?? ''
        );
        $messageId    = $data['mensaje']['id'] ?? $data['message']['id'] ?? $data['id'] ?? null;
        $fromMe       = (bool) ($data['mensaje']['fromMe'] ?? $data['fromMe'] ?? false);
        $connectionId = $data['conexion']['id'] ?? $data['connectionId'] ?? $data['whatsappId'] ?? null;

        // 🎤 AUDIO: si llega un audio (sin texto), lo descargamos y transcribimos
        //    Soporta múltiples formatos de payload que TecnoByteApp puede enviar.
        $tipoMensaje = strtolower(
            $data['mensaje']['type'] ?? $data['type'] ?? $data['messageType'] ?? ''
        );
        $audioUrl = $data['mensaje']['audio']['url']
            ?? $data['mensaje']['mediaUrl']
            ?? $data['audio']['url']
            ?? $data['audioUrl']
            ?? $data['mediaUrl']
            ?? null;

        $esAudio = !empty($audioUrl)
            && (empty($message) || in_array($tipoMensaje, ['audio', 'voice', 'ptt'], true));

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

        if ($messageId) {
            $alreadyProcessedKey = "processed_whatsapp_msg_{$messageId}";
            $processingKey       = "processing_whatsapp_msg_{$messageId}";

            if (Cache::has($alreadyProcessedKey)) {
                Log::warning('⚠️ Mensaje duplicado ignorado (ya procesado)', compact('messageId', 'from'));
                return response()->json(['status' => 'duplicate_ignored']);
            }

            if (!Cache::add($processingKey, true, now()->addSeconds(30))) {
                Log::warning('⚠️ Mensaje duplicado ignorado (en proceso)', compact('messageId', 'from'));
                return response()->json(['status' => 'duplicate_in_progress']);
            }
        }

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

        // ── CAPA 0: Buffer + debounce — agrupar mensajes seguidos del mismo cliente ──
        // Si el cliente manda 3 mensajes en 4 segundos, esperamos a que termine de
        // escribir y respondemos UNA sola vez con todo el contexto.
        $config = \App\Models\ConfiguracionBot::actual();

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

            // Sustituir el mensaje único por el agrupado
            $message = $resultadoAgrupado;
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

        return $this->procesarConIA($from, $name, $message, $connectionId);
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
        $bufferKey = "wa_buffer_{$from}";
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

        // Esperar a que el cliente termine de escribir
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

    private function procesarConIA(string $from, string $name, string $message, ?string $connectionId = null): string
    {
        $cacheKey = "whatsapp_chat_{$from}";

        $pedidosInfo  = $this->buscarPedidosClienteSQL($from, $message);
        $ansInfo      = $this->construirResumenAns();

        // Resolver sede para inyectar catálogo correcto (precios pueden variar por sede)
        $sedeId = $this->obtenerSedeIdDesdeConexion($connectionId);

        // ── CLIENTE: identificar/crear y enriquecer el contexto ──────────────
        $telefonoNorm = $this->normalizarTelefono($from);
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

        // Persistir mensaje del cliente
        $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_USER, $message);

        // ── HISTORIAL: leer de BD (últimos 20 mensajes user/assistant) ───────
        $conversationHistory = $conversacion->fresh()->historialParaIA(20);

        // Usar el nombre del cliente guardado si está mejor que el de WhatsApp
        $nombreParaPrompt = $cliente->nombre !== 'Cliente' ? $cliente->nombre : $name;

        // Agregar resumen del cliente al historial textual del prompt
        $resumenCliente = $cliente->resumenParaBot();
        $pedidosInfo = $resumenCliente . "\n\n" . $pedidosInfo;

        $systemPrompt = $this->getSystemPrompt($pedidosInfo, $this->infoEmpresa(), $nombreParaPrompt, $ansInfo, $sedeId);

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $conversationHistory
        );

        $response = $this->llamarOpenAI($messages);

        if (!$response) {
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
            return 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';
        }

        $toolCalls   = $response['choices'][0]['message']['tool_calls'] ?? null;
        $textContent = $response['choices'][0]['message']['content'] ?? null;

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
                ]]
            );

            $followUp = $this->llamarOpenAI($toolResponseMessages);
            $reply    = $followUp['choices'][0]['message']['content']
                ?? ($resultado['mensaje_sugerido'] ?? 'Déjame verificar tu dirección un momento 🙌');

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            $convService->agregarMensaje($conversacion, MensajeWhatsapp::ROL_ASSISTANT, $reply, [
                'tipo' => 'tool_call',
                'meta' => ['tool' => 'validar_cobertura', 'resultado' => $resultado],
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

            return $this->guardarPedidoDesdeToolCall(
                $orderData,
                $from,
                $name,
                $conversationHistory,
                $cacheKey,
                $connectionId
            );
        }

        $reply = $textContent
            ?? 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';

        // 🛑 DETECTOR DE ALUCINACIÓN DE CONFIRMACIÓN:
        // Si el bot dice "pedido registrado / confirmado / va en camino"
        // pero NO llamó a confirmar_pedido → es una mentira. Registramos
        // alerta operativa para que el admin lo vea y corrijamos el prompt.
        $fraseFalsaConfirmacion = $this->detectarFalsaConfirmacion($reply);
        if ($fraseFalsaConfirmacion) {
            Log::warning('⚠️ ALUCINACIÓN: Bot dijo que confirmó pero NO llamó la función', [
                'from'  => $from,
                'frase' => $fraseFalsaConfirmacion,
                'reply' => mb_substr($reply, 0, 300),
            ]);

            try {
                app(\App\Services\BotAlertaService::class)->registrar(
                    \App\Models\BotAlerta::TIPO_OTRO,
                    '🤥 Bot dijo que confirmó un pedido pero NO lo hizo',
                    "El bot respondió \"{$fraseFalsaConfirmacion}\" al cliente {$from} "
                        . "pero NO invocó la función confirmar_pedido. El pedido NO está registrado en BD. "
                        . "Revisa /chat para ver la conversación y completarlo manualmente si es necesario.",
                    \App\Models\BotAlerta::SEV_WARNING,
                    null,
                    [
                        'from'         => $from,
                        'frase'        => $fraseFalsaConfirmacion,
                        'reply'        => mb_substr($reply, 0, 500),
                        'conversacion_id' => $conversacion->id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('No se pudo registrar alerta de falsa confirmación: ' . $e->getMessage());
            }
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
    private function detectarFalsaConfirmacion(string $reply): ?string
    {
        $frases = [
            'pedido quedó registrado',
            'pedido registrado',
            'pedido confirmado',
            'va en camino',
            'salió en camino',
            'sale en camino',
            'lo estamos preparando',
            'tu pedido #',
            'quedó en preparación',
            'tu pedido quedó listo',
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
     * Por ahora usa la primera sede activa como fallback. Si más adelante
     * cada conexión tiene su sede, basta con agregar la lógica aquí.
     */
    private function obtenerSedeIdDesdeConexion(?string $connectionId): ?int
    {
        return Cache::remember('default_sede_id', now()->addMinutes(10), function () {
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



    private function llamarOpenAI(array $messages): ?array
    {
        $intentos = 2;
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
                        'tools'       => $this->getToolsDefinicion(),
                        'tool_choice' => 'auto',
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
                sleep(1);
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
        return 'whatsapp_pending_action_' . $this->normalizarTelefono($from);
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
  private function validarCoberturaDireccion(
      string $direccion,
      ?string $barrio = null,
      ?string $ciudad = 'Bello',
      ?int $sedeId = null,
      ?string $telefonoCliente = null
  ): array {
      $zonaResolver = app(ZonaResolverService::class);

      $zona   = null;
      $metodo = null;
      $coord  = null;

      // ── Estrategia 1: Geocode + polígono (el mapa manda) ──────────────
      if (!empty($direccion) || !empty($barrio)) {
          $geocode = app(GeocodingService::class)->geocodificar(
              $direccion ?: '',
              $barrio,
              $ciudad ?: 'Bello'
          );

          if ($geocode) {
              $coord = $geocode;
              $zona = $zonaResolver->porCoordenadas($geocode['lat'], $geocode['lng'], $sedeId);
              if ($zona) {
                  $metodo = 'poligono_mapa';
                  Log::info('✅ Cobertura por polígono', [
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

  private function guardarPedidoDesdeToolCall(
    array $orderData,
    string $from,
    string $name,
    array $conversationHistory,
    string $cacheKey,
    ?string $connectionId = null
): string {
    try {
        $telNorm = $this->normalizarTelefono($from);
        $confirmKey = "pedido_confirmado_" . $telNorm;

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

        // Resolver zona de cobertura — primero por barrio, si falla intenta geocode
        $validacion = $this->validarCoberturaDireccion(
            $direccion,
            $barrio,
            'Bello',
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

        // ── VALIDACIÓN ESTRICTA de cobertura ───────────────────────────────
        // Regla: si el cliente dio dirección/barrio para domicilio pero no
        // coincide con ninguna zona activa → se rechaza el pedido.
        // Excepción: si NO dio dirección ni barrio (es pedido para recoger
        // en sede), se permite crearlo sin zona.
        $indicoDomicilio = (!empty($direccion) || !empty($barrio));

        if ($indicoDomicilio && !$zonaCobertura) {
            Cache::forget($confirmKey);   // liberar el lock de deduplicación
            DB::rollBack();

            $mensaje = "Uy, esa dirección me queda fuera de la zona de cobertura 😔\n\n"
                . "Pero el pedido te lo puedo dejar listo para que lo recojas en la sede, o "
                . "si tienes otra dirección cercana me la pasas y vuelvo a revisar 🙌";

            Log::warning('🚫 Pedido rechazado — fuera de cobertura', [
                'from'      => $from,
                'direccion' => $direccion,
                'barrio'    => $barrio,
            ]);

            $conversationHistory[] = ['role' => 'assistant', 'content' => $mensaje];
            Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

            return $mensaje;
        }

        // ── Validar y resolver productos contra el catálogo ──
        /** @var BotCatalogoService $catalogo */
        $catalogo = app(BotCatalogoService::class);

        $productosValidados = [];
        $productosNoEncontrados = [];
        $subtotalProductos = 0;

        foreach (($orderData['products'] ?? []) as $product) {
            $entrada = $product['code'] ?? $product['name'] ?? '';
            $producto = $catalogo->resolverProducto($entrada, $sede?->id);

            $cantidad = (float) ($product['quantity'] ?? 1);

            if ($producto) {
                $precio = $producto->precioParaSede($sede?->id);
                $sub = $precio * $cantidad;
                $subtotalProductos += $sub;

                $productosValidados[] = [
                    'producto_id'     => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'producto'        => $producto->nombre,
                    'cantidad'        => $cantidad,
                    'unidad'          => $product['unit'] ?? $producto->unidad,
                    'precio_unitario' => $precio,
                    'subtotal'        => $sub,
                ];
            } else {
                Log::warning('⚠️ Producto del bot no está en catálogo', [
                    'entrada' => $entrada,
                    'producto_data' => $product,
                ]);
                $productosNoEncontrados[] = $entrada;

                // Lo guardamos sin producto_id para no perder el registro
                $productosValidados[] = [
                    'producto_id'     => null,
                    'codigo_producto' => null,
                    'producto'        => $product['name'] ?? 'Producto desconocido',
                    'cantidad'        => $cantidad,
                    'unidad'          => $product['unit'] ?? 'unidad',
                    'precio_unitario' => 0,
                    'subtotal'        => 0,
                ];
            }
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
        $datosClienteActualizar = [
            'nombre'              => $orderData['customer_name'] ?? $cliente->nombre,
            'direccion_principal' => $direccion ?: $cliente->direccion_principal,
            'barrio'              => $barrio ?: $cliente->barrio,
            'zona_cobertura_id'   => $zonaCobertura?->id ?? $cliente->zona_cobertura_id,
        ];
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
            'observacion_estado'    => 'Pedido creado automáticamente desde WhatsApp',
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

        return $this->construirMensajeConfirmacionPedido($pedido, $orderData, $name, $beneficioAplicado);
    } catch (\Throwable $e) {
        DB::rollBack();
        Cache::forget("pedido_confirmado_" . $this->normalizarTelefono($from));

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
        $lineas = [
            "¡Listo {$name}! Tu pedido quedó confirmado ✅",
            '',
            "📋 *Pedido #{$pedido->id}*",
        ];

        foreach (($orderData['products'] ?? []) as $prod) {
            $cant = $this->formatearCantidadPedido((float) ($prod['quantity'] ?? 1));
            $unidad = $prod['unit'] ?? 'unidad';
            $lineas[] = "• {$prod['name']} — {$cant} {$unidad}";
        }

        $lineas[] = '';

        if (!empty($orderData['address'])) {
            $lineas[] = "📍 *Dirección:* {$orderData['address']}";
        }

        if (!empty($orderData['neighborhood'])) {
            $lineas[] = "🏘️ *Barrio:* {$orderData['neighborhood']}";
        }

        if (!empty($pedido->hora_entrega)) {
            $lineas[] = "🕒 *Entrega estimada:* {$pedido->hora_entrega}";
        }

        if (!empty($pedido->telefono_contacto)) {
            $lineas[] = "📞 *Contacto:* {$pedido->telefono_contacto}";
        }

        // 🎁 Si se aplicó un beneficio, avisarle al cliente para que sepa
        // que ya lo usamos automáticamente (evita que pregunte después).
        if ($beneficioAplicado) {
            $lineas[] = '';
            $lineas[] = "🎁 *Envío GRATIS aplicado* (beneficio por "
                . $beneficioAplicado->origen . ") — no pagaste costo de envío.";
        }

        $total = (float) $pedido->total;
        if ($total > 0) {
            $lineas[] = "💵 *Total:* $" . number_format($total, 0, ',', '.');
        }

        $lineas[] = '';
        $lineas[] = "🔎 Puedes seguir tu pedido aquí:";
        $lineas[] = $pedido->url_seguimiento;
        $lineas[] = '';
        $lineas[] = "Guarda también tu número de pedido *#{$pedido->id}* para futuras consultas 😊";

        return implode("\n", $lineas);
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
        $crear     = $this->obtenerAnsMinutos('crear') ?? 'No definido';
        $adicionar = $this->obtenerAnsMinutos('adicionar') ?? 'No definido';
        $cancelar  = $this->obtenerAnsMinutos('cancelar') ?? 'No definido';

        return "ANS DEL SISTEMA:\n"
            . "- Crear pedido: {$crear} minuto(s)\n"
            . "- Adicionar pedido: {$adicionar} minuto(s)\n"
            . "- Cancelar pedido: {$cancelar} minuto(s)";
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

        // Si el usuario activó "prompt personalizado" y guardó algo, usarlo
        if ($config->usar_prompt_personalizado && !empty(trim($config->system_prompt ?? ''))) {
            return $promptService->renderizar($config->system_prompt, $contexto);
        }

        // Sino, usar la plantilla por defecto (también renderizando variables)
        return $promptService->renderizar(BotPromptService::plantillaPorDefecto(), $contexto);
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

    private function getToolsDefinicion(): array
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
                            'phone'          => ['type' => 'string', 'description' => 'Teléfono del cliente'],
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
}
