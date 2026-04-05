<?php

namespace App\Http\Controllers;

use App\Events\PedidoActualizado;
use App\Events\PedidoConfirmado;
use App\Models\AnsPedido;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Sede;
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

    private function procesarConIA(string $from, string $name, string $message, ?string $connectionId = null): string
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

        $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];
        Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));

        Log::info('💬 CAPA 3: Respuesta conversacional IA', compact('from', 'reply'));

        return $reply;
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

        for ($i = 1; $i <= $intentos; $i++) {
            try {
                $response = Http::withToken(env('OPENAI_API_KEY'))
                    ->timeout(35)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'       => 'gpt-4o-mini',
                        'messages'    => $messages,
                        'temperature' => 0.4,
                        'max_tokens'  => 700,
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

  private function guardarPedidoDesdeToolCall(
    array $orderData,
    string $from,
    string $name,
    array $conversationHistory,
    string $cacheKey,
    ?string $connectionId = null
): string {
    try {
        $confirmKey = "pedido_confirmado_" . $this->normalizarTelefono($from);

        if (Cache::has($confirmKey)) {
            Log::warning('⚠️ Pedido ya confirmado recientemente', compact('from'));
            return "Tu pedido ya fue registrado 😊 Escríbeme el número de pedido para consultarlo.";
        }

        Cache::put($confirmKey, true, now()->addMinutes(2));

        DB::beginTransaction();

        $conexionData = $this->resolverConexionWhatsapp($connectionId);

        $empresaId = $conexionData['empresa_id'];
        $connectionId = $conexionData['connection_id'];
        $whatsappId = $conexionData['whatsapp_id'];

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
            'empresa_id'            => $empresaId,
            'fecha_pedido'          => now(),
            'hora_entrega'          => $pickupTime,
            'estado'                => 'nuevo',
            'fecha_estado'          => now(),
            'observacion_estado'    => 'Pedido creado automáticamente desde WhatsApp',
            'total'                 => (float) ($orderData['total'] ?? 0),
            'notas'                 => $notas,
            'cliente_nombre'        => $orderData['customer_name'] ?? $name,
            'telefono_whatsapp'     => $telefonoWhatsapp,
            'telefono_contacto'     => $telefonoContacto,
            'telefono'              => $telefonoWhatsapp,
            'canal'                 => 'whatsapp',
            'connection_id'         => $connectionId,
            'whatsapp_id'           => $whatsappId,
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

        return $this->construirMensajeConfirmacionPedido($pedido, $orderData, $name);
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
    private function construirMensajeConfirmacionPedido(Pedido $pedido, array $orderData, string $name): string
    {
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
3. Preguntar barrio.
4. Validar cobertura.
5. Construir el pedido.
6. Pedir datos solo cuando el cliente ya decidió.
7. Mostrar resumen y pedir confirmación.
8. Confirmar solo cuando el cliente diga: sí, correcto, listo, ok, confirmado.

⚠️ REGLA CRÍTICA:
NUNCA llames la función confirmar_pedido si no hay al menos 1 producto válido.
NUNCA envíes products vacío.
ANTES de confirmar debes tener producto, cantidad, barrio, dirección, teléfono y nombre.

━━━ DATOS NECESARIOS ━━━━━━━━━━━━━━━━━━━━━━━━
✅ Nombre
✅ Dirección completa
✅ Barrio
✅ Teléfono
✅ Al menos 1 producto con cantidad

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
- Ya tienes: nombre, dirección, barrio, teléfono y al menos un producto válido

Ejemplo válido de products:
[
  {
    "name": "Producto",
    "quantity": 2,
    "unit": "libras"
  }
]

NO la llames si falta algún dato.
NO inventes datos.
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
                            'pickup_time'    => ['type' => 'string', 'description' => 'Hora estimada de entrega'],
                            'total'          => ['type' => 'number', 'description' => 'Total del pedido'],
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
