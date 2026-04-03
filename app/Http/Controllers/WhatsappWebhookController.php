<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Sede;
use App\Models\AnsPedido;
use App\Events\PedidoConfirmado;

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
        $name    = $data['chat']['name']  ?? $data['name'] ?? 'Cliente';
        $message = trim(
            $data['mensaje']['body'] ?? $data['body'] ?? $data['message'] ?? $data['text'] ?? ''
        );
        $messageId    = $data['mensaje']['id'] ?? $data['message']['id'] ?? $data['id'] ?? null;
        $fromMe       = (bool) ($data['mensaje']['fromMe'] ?? $data['fromMe'] ?? false);
        $connectionId = $data['conexion']['id'] ?? $data['connectionId'] ?? $data['whatsappId'] ?? null;

        Log::info('📥 DATOS NORMALIZADOS', compact('from', 'name', 'message', 'messageId', 'fromMe', 'connectionId'));

        if (!$from || !$message) {
            Log::warning('⚠️ Mensaje ignorado: faltan datos', compact('from', 'message'));
            return response()->json(['status' => 'ignored']);
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
    | FLUJO PRINCIPAL DE PROCESAMIENTO
    |==========================================================================
    */

    private function procesarMensaje(string $from, string $name, string $message, ?string $connectionId): string
    {
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

        return $this->procesarConIA($from, $name, $message);
    }

    private function procesarConIA(string $from, string $name, string $message): string
    {
        $cacheKey = "whatsapp_chat_{$from}";
        $conversationHistory = Cache::get($cacheKey, []);

        $conversationHistory[] = ['role' => 'user', 'content' => $message];

        if (count($conversationHistory) > 20) {
            $conversationHistory = array_slice($conversationHistory, -20);
        }

        $pedidosInfo  = $this->buscarPedidosClienteSQL($from, $message);
        $ansInfo      = $this->construirResumenAns();
        $systemPrompt = $this->getSystemPrompt($pedidosInfo, $this->infoEmpresa(), $name, $ansInfo);

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

        if ($toolCalls && ($toolCalls[0]['function']['name'] ?? '') === 'confirmar_pedido') {
            $rawArgs   = $toolCalls[0]['function']['arguments'] ?? '{}';
            $orderData = json_decode($rawArgs, true);

            if (json_last_error() !== JSON_ERROR_NONE || empty($orderData['products'])) {
                Log::error('❌ JSON inválido en tool_call', ['raw' => $rawArgs]);
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
                return '⚠️ Hubo un problema al registrar tu pedido. Por favor, confírmame los datos de nuevo.';
            }

            Log::info('🎯 CAPA 3: Function call confirmar_pedido', compact('from', 'orderData'));

            return $this->guardarPedidoDesdeToolCall($orderData, $from, $name, $conversationHistory, $cacheKey);
        }

        $reply = $textContent
            ?? 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';

        $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
        Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

        Log::info('💬 CAPA 3: Respuesta conversacional IA', compact('from', 'reply'));

        return $reply;
    }

    private function llamarOpenAI(array $messages): ?array
    {
        $intentos = 2;

        for ($i = 1; $i <= $intentos; $i++) {
            try {
                $response = Http::withToken(env('OPENAI_API_KEY'))
                    ->timeout(35)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'       => 'gpt-4o-mini',
                        'messages'    => $messages,
                        'temperature' => 0.4,
                        'max_tokens'  => 600,
                        'tools'       => $this->getToolsDefinicion(),
                        'tool_choice' => 'auto',
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::warning("⚠️ OpenAI intento {$i} falló", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

            } catch (\Throwable $e) {
                Log::warning("⚠️ OpenAI excepción intento {$i}", ['error' => $e->getMessage()]);
            }

            if ($i < $intentos) {
                sleep(1);
            }
        }

        Log::error('❌ OpenAI falló todos los intentos');
        return null;
    }

    /*
    |==========================================================================
    | CAPA 2 — REGLAS DETERMINISTAS
    |==========================================================================
    */

    private function esConsultaEstadoPedido(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        $frases = [
            'estado de mi pedido', 'estado del pedido', 'estado de pedido',
            'como va mi pedido', 'cómo va mi pedido',
            'como van mis pedidos', 'cómo van mis pedidos',
            'mis pedidos', 'mi pedido', 'mi orden', 'mis ordenes', 'mis órdenes',
            'estado pedido', 'seguimiento pedido',
            'seguimiento de mi pedido', 'seguimiento de mis pedidos',
            'ya salió mi pedido', 'ya salio mi pedido',
            'donde va mi pedido', 'dónde va mi pedido',
            'consulta de pedido', 'consultar pedido', 'consultar mis pedidos',
            'quiero saber mi pedido', 'quiero saber mis pedidos',
            'numero de pedido', 'número de pedido',
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
            'cancelar', 'cancela', 'cancelame', 'cancelar el', 'cancelar mi',
            'cancelen', 'anular', 'anula', 'ya no lo quiero', 'ya no quiero el pedido',
            'quitar el pedido', 'eliminar pedido', 'borrar pedido',
            'adicionar', 'adiciona', 'agregar', 'agrega', 'sumar',
            'añadir', 'anadir', 'ponerle',
            'modificar', 'modifica', 'editar', 'edita',
            'cambiar', 'cambiame', 'cámbiame', 'cambiarle',
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
            'cancelar', 'cancela', 'cancelame', 'cancelen', 'anular', 'anula',
            'ya no lo quiero', 'ya no quiero el pedido', 'quitar el pedido',
            'eliminar pedido', 'borrar pedido'
        ];

        foreach ($cancelar as $p) {
            if (str_contains($msg, $p)) {
                return 'cancelar';
            }
        }

        $adicionar = [
            'adicionar', 'adiciona', 'agregar', 'agrega', 'sumar', 'añadir', 'anadir',
            'ponerle', 'modificar', 'modifica', 'editar', 'edita',
            'cambiar', 'cambiame', 'cámbiame', 'cambiarle'
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

        $accion              = $pendiente['accion'];
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

    private function guardarPedidoDesdeToolCall(
        array $orderData,
        string $from,
        string $name,
        array $conversationHistory,
        string $cacheKey
    ): string {
        try {
            $confirmKey = "pedido_confirmado_" . $this->normalizarTelefono($from);

            if (Cache::has($confirmKey)) {
                Log::warning('⚠️ Pedido ya confirmado recientemente', compact('from'));
                return "Tu pedido ya fue registrado 😊 Escríbeme el número de pedido para consultarlo.";
            }

            Cache::put($confirmKey, true, now()->addMinutes(2));

            DB::beginTransaction();

            $sedeNombre = $orderData['location'] ?? $orderData['neighborhood'] ?? null;

            $sede = $sedeNombre
                ? Sede::where('nombre', 'LIKE', "%{$sedeNombre}%")->first() ?? Sede::first()
                : Sede::first();

            $partes = array_filter([
                $orderData['notes'] ?? null,
                isset($orderData['address']) ? "Dirección: {$orderData['address']}" : null,
                isset($orderData['neighborhood']) ? "Barrio: {$orderData['neighborhood']}" : null,
                isset($orderData['payment_method']) ? "Pago: {$orderData['payment_method']}" : null,
            ]);

            $notas = implode(' | ', $partes) ?: 'Solicitud vía WhatsApp';

            $pickupTime = !empty($orderData['pickup_time']) ? $orderData['pickup_time'] : null;

            $telefonoWhatsapp = $this->normalizarTelefono($from);
            $telefonoContacto = $this->normalizarTelefono($orderData['phone'] ?? $from);

            $pedido = Pedido::create([
                'sede_id'               => $sede?->id,
                'fecha_pedido'          => now(),
                'hora_entrega'          => $pickupTime,
                'estado'                => 'confirmado',
                'total'                 => (float) ($orderData['total'] ?? 0),
                'notas'                 => $notas,
                'cliente_nombre'        => $orderData['customer_name'] ?? $name,
                'telefono_whatsapp'     => $telefonoWhatsapp,
                'telefono_contacto'     => $telefonoContacto,
                'telefono'              => $telefonoWhatsapp,
                'canal'                 => 'whatsapp',
                'conversacion_completa' => json_encode($conversationHistory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'resumen_conversacion'  => $orderData['notes'] ?? '',
            ]);

            foreach (($orderData['products'] ?? []) as $product) {
                DetallePedido::create([
                    'pedido_id'       => $pedido->id,
                    'producto'        => $product['name'] ?? 'Producto',
                    'cantidad'        => (float) ($product['quantity'] ?? 1),
                    'unidad'          => $product['unit'] ?? 'unidad',
                    'precio_unitario' => (float) ($product['price'] ?? 0),
                    'subtotal'        => (float) ($product['subtotal'] ?? 0),
                ]);
            }

            DB::commit();

            broadcast(new PedidoConfirmado($pedido));

            Cache::forget($cacheKey);

            Log::info('✅ PEDIDO GUARDADO', [
                'pedido_id'          => $pedido->id,
                'from'               => $from,
                'telefono_whatsapp'  => $telefonoWhatsapp,
                'telefono_contacto'  => $telefonoContacto,
            ]);

            return $this->construirMensajeConfirmacionPedido($pedido, $orderData, $name);

        } catch (\Throwable $e) {
            DB::rollBack();
            Cache::forget("pedido_confirmado_" . $this->normalizarTelefono($from));

            Log::error('❌ ERROR CRÍTICO AL GUARDAR PEDIDO', [
                'error'      => $e->getMessage(),
                'order_data' => $orderData,
            ]);

            return '⚠️ Tu pedido no se pudo registrar en este momento. Ya lo estamos revisando, te contactamos en breve.';
        }
    }

    private function construirMensajeConfirmacionPedido(Pedido $pedido, array $orderData, string $name): string
    {
        $lineas = [
            "¡Listo {$name}! Tu pedido quedó confirmado ✅",
            '',
            "📋 *Pedido #{$pedido->id}*",
        ];

        foreach (($orderData['products'] ?? []) as $prod) {
            $cant = $this->formatearCantidadPedido((float) ($prod['quantity'] ?? 1));
            $lineas[] = "• {$prod['name']} — {$cant} {$prod['unit']}";
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

        $total = (float) $pedido->total;
        if ($total > 0) {
            $lineas[] = "💵 *Total:* $" . number_format($total, 0, ',', '.');
        }

        $lineas[] = '';
        $lineas[] = "Guarda el número *#{$pedido->id}* para consultar el estado de tu pedido. 😊";

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
            'pedido', 'domicilio', 'orden', 'estado', 'seguimiento', 'compra', 'comprar',
            'dirección', 'direccion', 'barrio', 'pago', 'contra entrega',
            'cancelar', 'anular', 'adicionar', 'agregar', 'modificar', 'editar', 'cambiar',
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
    | SYSTEM PROMPT E INFO EMPRESA
    |==========================================================================
    */

    private function infoEmpresa(): string
    {
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
        string $ansInfo = ''
    ): string {
        return <<<PROMPT
Eres el asesor comercial de Alimentos La Hacienda.

Hablas como una persona real del equipo de ventas.
Nunca digas que eres una inteligencia artificial.
Si el cliente pregunta si eres humano responde solo:
"Soy asesor del equipo de Alimentos La Hacienda y estoy aquí para ayudarte con tu pedido."

━━━ CONTEXTO ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$infoEmpresa}

HISTORIAL DEL CLIENTE:
{$pedidosInfo}

{$ansInfo}

━━━ ESTILO ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- Lenguaje natural, cercano pero profesional.
- Máximo 3 a 5 líneas por mensaje.
- Sin párrafos largos. Sin sonar robótico.
- Sin lenguaje técnico. Sin muchas preguntas juntas.
- Emojis moderados: 😊 👍 🚚

━━━ FLUJO ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Saluda siempre primero.
2. Entender qué necesita.
3. Preguntar barrio: ¿En qué barrio se encuentra?
4. Validar cobertura.
5. Construir el pedido.
6. Pedir datos SOLO cuando el cliente ya decidió.
7. Mostrar resumen y pedir confirmación.
8. Confirmar SOLO cuando el cliente diga: sí, correcto, listo, ok, confirmado.

━━━ DATOS NECESARIOS ━━━━━━━━━━━━━━━━━━━━━━━━
✅ Nombre
✅ Dirección completa
✅ Barrio
✅ Teléfono

━━━ CONFIRMACIÓN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Antes de confirmar SIEMPRE muestra el resumen así:

"Por favor revisa tu pedido 👇"

📦 Pedido
[productos]

📍 Dirección
[dirección]

👤 Recibe
[nombre]

📞 Teléfono
[teléfono]

💵 Pago
Contra entrega

Termina SIEMPRE con: "¿Todo correcto para confirmar?"

━━━ FUNCIÓN confirmar_pedido ━━━━━━━━━━━━━━━━━
Llama a la función confirmar_pedido ÚNICAMENTE cuando:
- El cliente confirmó con sí / correcto / listo / ok / confirmado
- Ya tienes: nombre, dirección, barrio, teléfono y al menos un producto

NO la llames si falta algún dato. NO inventes datos.
NO la llames más de una vez por conversación.

━━━ ANS ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- Si el cliente pregunta por cancelar o adicionar, respeta la validación que ya hizo el sistema.
- No prometas cancelar o adicionar si el tiempo ANS ya expiró.
PROMPT;
    }

    private function getToolsDefinicion(): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'confirmar_pedido',
                    'description' => 'Confirma y registra el pedido. Llama SOLO cuando el cliente aceptó explícitamente y tienes todos los datos requeridos.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'products' => [
                                'type'        => 'array',
                                'description' => 'Productos del pedido',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'name'     => ['type' => 'string', 'description' => 'Nombre del producto'],
                                        'quantity' => ['type' => 'number', 'description' => 'Cantidad'],
                                        'unit'     => ['type' => 'string', 'description' => 'Unidad (kg, libra, unidad, etc.)'],
                                        'price'    => ['type' => 'number', 'description' => 'Precio unitario, 0 si no se conoce'],
                                        'subtotal' => ['type' => 'number', 'description' => 'Subtotal, 0 si no se conoce'],
                                    ],
                                    'required' => ['name', 'quantity', 'unit'],
                                ],
                            ],
                            'customer_name'  => ['type' => 'string', 'description' => 'Nombre completo del cliente'],
                            'phone'          => ['type' => 'string', 'description' => 'Teléfono del cliente'],
                            'address'        => ['type' => 'string', 'description' => 'Dirección de entrega'],
                            'neighborhood'   => ['type' => 'string', 'description' => 'Barrio'],
                            'location'       => ['type' => 'string', 'description' => 'Ciudad o zona'],
                            'payment_method' => ['type' => 'string', 'description' => 'Método de pago'],
                            'pickup_time'    => ['type' => 'string', 'description' => 'Hora estimada de entrega, vacío si no se sabe'],
                            'total'          => ['type' => 'number', 'description' => 'Total del pedido, 0 si no aplica'],
                            'notes'          => ['type' => 'string', 'description' => 'Notas adicionales del pedido'],
                        ],
                        'required' => ['products', 'customer_name', 'phone', 'address', 'neighborhood'],
                    ],
                ],
            ],
        ];
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

                Log::error('❌ Falló el reintento de envío a WhatsApp', [
                    'status' => $retryResponse->status(),
                    'body'   => $retryResponse->body(),
                    'phone'  => $from,
                ]);

                return false;
            }

            Log::error('⚠️ WHATSAPP API ERROR', [
                'status' => $response->status(),
                'body'   => $rawBody,
                'phone'  => $from,
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('❌ ERROR ENVIANDO A WHATSAPP', [
                'error' => $e->getMessage(),
                'phone' => $from,
            ]);

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

    private function obtenerTokenWhatsapp(): ?string
    {
        $cacheKey = 'whatsapp_api_token';
        return Cache::get($cacheKey) ?: $this->loginWhatsapp();
    }

    private function loginWhatsapp(bool $force = false): ?string
    {
        $cacheKey = 'whatsapp_api_token';

        if ($force) {
            Cache::forget($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(20)
                ->post('https://wa-api.tecnobyteapp.com:1422/auth/login', [
                    'email'    => env('WHATSAPP_API_EMAIL'),
                    'password' => env('WHATSAPP_API_PASSWORD'),
                ]);

            if ($response->failed()) {
                Log::error('❌ ERROR LOGIN WHATSAPP', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $token = $response->json('token');

            if (!$token) {
                Log::error('❌ LOGIN WHATSAPP SIN TOKEN', [
                    'body' => $response->body(),
                ]);
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
            return null;
        }
    }

    private function refrescarTokenWhatsapp(): ?string
    {
        $cacheKey = 'whatsapp_api_token';
        $token    = Cache::get($cacheKey);

        if (!$token) {
            Log::warning('⚠️ No hay token en cache para refrescar');
            return null;
        }

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post('https://wa-api.tecnobyteapp.com:1422/auth/refresh_token');

            if ($response->failed()) {
                Log::warning('⚠️ ERROR REFRESH TOKEN WHATSAPP', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                Cache::forget($cacheKey);
                return null;
            }

            $newToken = $response->json('token');

            if (!$newToken) {
                Log::warning('⚠️ REFRESH TOKEN SIN TOKEN NUEVO', [
                    'body' => $response->body(),
                ]);

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

            return null;
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
    | HELPERS DE FORMATO
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
        return [
            'id'                   => $pedido->id,
            'fecha'                => optional($pedido->fecha_pedido)?->format('d/m/Y H:i'),
            'estado'               => $pedido->estado,
            'hora_entrega'         => $pedido->hora_entrega ?? 'Por confirmar',
            'sede'                 => $pedido->sede->nombre ?? 'No especificada',
            'cliente'              => $pedido->cliente_nombre,
            'telefono_whatsapp'    => $pedido->telefono_whatsapp ?? $pedido->telefono,
            'telefono_contacto'    => $pedido->telefono_contacto ?? $pedido->telefono,
            'telefono'             => $pedido->telefono,
            'total'                => $pedido->total,
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
            'confirmado'     => 'Confirmado ✅',
            'en_preparacion' => 'En preparación 👨‍🍳',
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
}