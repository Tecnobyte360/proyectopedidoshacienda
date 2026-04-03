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

            return response()->json([
                'status'  => 'error',
                'message' => 'Payload vacío',
            ], 400);
        }

        $from = $data['chat']['phone']
            ?? $data['from']
            ?? $data['phoneNumber']
            ?? null;

        $name = $data['chat']['name']
            ?? $data['name']
            ?? 'Cliente';

        $message = trim(
            $data['mensaje']['body']
                ?? $data['body']
                ?? $data['message']
                ?? $data['text']
                ?? ''
        );

        $messageId = $data['mensaje']['id']
            ?? $data['message']['id']
            ?? $data['id']
            ?? null;

        $fromMe = (bool) (
            $data['mensaje']['fromMe']
            ?? $data['fromMe']
            ?? false
        );

        $connectionId = $data['conexion']['id']
            ?? $data['connectionId']
            ?? $data['whatsappId']
            ?? null;

        $connectionName = $data['conexion']['name']
            ?? $data['connectionName']
            ?? null;

        Log::info('📥 DATOS NORMALIZADOS', [
            'from'            => $from,
            'name'            => $name,
            'message'         => $message,
            'message_id'      => $messageId,
            'from_me'         => $fromMe,
            'connection_id'   => $connectionId,
            'connection_name' => $connectionName,
        ]);

        if (!$from || !$message) {
            Log::warning('⚠️ Mensaje ignorado por falta de datos', [
                'from'    => $from,
                'name'    => $name,
                'message' => $message,
                'payload' => $data,
            ]);

            return response()->json([
                'status' => 'ignored'
            ]);
        }

        if ($fromMe === true) {
            Log::info('↩️ MENSAJE PROPIO IGNORADO', [
                'message_id' => $messageId,
                'from'       => $from,
            ]);

            return response()->json([
                'status' => 'self_message_ignored'
            ]);
        }

        if ($messageId) {
            $processingKey = "processing_whatsapp_msg_{$messageId}";
            $alreadyProcessedKey = "processed_whatsapp_msg_{$messageId}";

            if (Cache::has($alreadyProcessedKey)) {
                Log::warning('⚠️ MENSAJE DUPLICADO IGNORADO (YA PROCESADO)', [
                    'message_id' => $messageId,
                    'from'       => $from,
                    'message'    => $message,
                ]);

                return response()->json([
                    'status' => 'duplicate_ignored'
                ]);
            }

            if (!Cache::add($processingKey, true, now()->addSeconds(30))) {
                Log::warning('⚠️ MENSAJE DUPLICADO IGNORADO (EN PROCESO)', [
                    'message_id' => $messageId,
                    'from'       => $from,
                ]);

                return response()->json([
                    'status' => 'duplicate_in_progress'
                ]);
            }
        }

        try {
            Log::info('✅ MENSAJE CLIENTE', [
                'from'          => $from,
                'name'          => $name,
                'message'       => $message,
                'message_id'    => $messageId,
                'connection_id' => $connectionId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 1. CONSULTA DIRECTA DE ESTADO DEL PEDIDO
            |--------------------------------------------------------------------------
            */
            if ($this->esConsultaEstadoPedido($message)) {
                $replyEstado = $this->resolverConsultaEstadoPedido($from, $name, $message);

                Log::info('📦 RESPUESTA AUTOMÁTICA DE ESTADO', [
                    'phone'         => $from,
                    'message'       => $message,
                    'reply'         => $replyEstado,
                    'connection_id' => $connectionId,
                ]);

                $this->enviarRespuestaWhatsapp($from, $replyEstado, $connectionId);

                if ($messageId) {
                    Cache::put("processed_whatsapp_msg_{$messageId}", true, now()->addMinutes(10));
                }

                return response()->json([
                    'status'            => 'ok',
                    'message_processed' => true,
                    'type'              => 'order_status_response',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. VALIDACIÓN DE CANCELACIÓN / ADICIÓN / MODIFICACIÓN
            |--------------------------------------------------------------------------
            */
            if ($this->esSolicitudModificarPedido($message)) {
                $replyModificacion = $this->resolverSolicitudModificacionPedido($from, $name, $message);

                Log::info('🛠️ RESPUESTA AUTOMÁTICA MODIFICACIÓN PEDIDO', [
                    'phone'         => $from,
                    'message'       => $message,
                    'reply'         => $replyModificacion,
                    'connection_id' => $connectionId,
                ]);

                $this->enviarRespuestaWhatsapp($from, $replyModificacion, $connectionId);

                if ($messageId) {
                    Cache::put("processed_whatsapp_msg_{$messageId}", true, now()->addMinutes(10));
                }

                return response()->json([
                    'status'            => 'ok',
                    'message_processed' => true,
                    'type'              => 'order_modification_validation',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. FLUJO NORMAL CON IA
            |--------------------------------------------------------------------------
            */
            $cacheKey = "whatsapp_chat_{$from}";
            $conversationHistory = Cache::get($cacheKey, []);

            $conversationHistory[] = [
                'role'    => 'user',
                'content' => $message,
            ];

            if (count($conversationHistory) > 20) {
                $conversationHistory = array_slice($conversationHistory, -20);
            }

            $pedidosInfo = $this->buscarPedidosCliente($from, $message);
            $ansInfo = $this->construirResumenAns();

            $systemPrompt = $this->getSystemPromptForAIEmpresa(
                $pedidosInfo,
                $this->infoEmpresa(),
                $name,
                $ansInfo
            );

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];

            foreach ($conversationHistory as $msg) {
                $messages[] = $msg;
            }

            $responseIA = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(35)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4o-mini',
                    'messages'    => $messages,
                    'temperature' => 0.4,
                    'max_tokens'  => 500,
                ]);

            if ($responseIA->failed()) {
                throw new \Exception(
                    'OpenAI error: ' . $responseIA->status() . ' ' . $responseIA->body()
                );
            }

            $reply = $responseIA->json('choices.0.message.content')
                ?? 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';

            $conversationHistory[] = [
                'role'    => 'assistant',
                'content' => $reply
            ];

            if (str_contains($reply, '[PEDIDO_CONFIRMADO]')) {
                $reply = $this->guardarPedidoDesdeRespuestaIA(
                    $reply,
                    $from,
                    $name,
                    $conversationHistory,
                    $cacheKey
                );
            } else {
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
            }

            Log::info('💬 RESPUESTA GENERADA', [
                'reply'         => $reply,
                'phone'         => $from,
                'message_id'    => $messageId,
                'connection_id' => $connectionId,
            ]);

            $this->enviarRespuestaWhatsapp($from, $reply, $connectionId);

            if ($messageId) {
                Cache::put("processed_whatsapp_msg_{$messageId}", true, now()->addMinutes(10));
            }

            return response()->json([
                'status'            => 'ok',
                'message_processed' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR IA o HTTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'No se pudo procesar',
            ], 500);
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
                    'message' => 'Debes enviar al menos uno de estos filtros: pedido_id, telefono o cliente.',
                ], 422);
            }

            $formatearCantidad = function (float $cantidad): string {
                if (fmod($cantidad, 1.0) == 0.0) {
                    return number_format($cantidad, 0, ',', '.');
                }

                return number_format($cantidad, 2, ',', '.');
            };

            $query = Pedido::with(['sede', 'detalles']);

            if ($request->filled('pedido_id')) {
                $query->where('id', $request->pedido_id);
            }

            if ($request->filled('telefono')) {
                $telefono = trim($request->telefono);
                $query->where('telefono', 'LIKE', "%{$telefono}%");
            }

            if ($request->filled('cliente')) {
                $cliente = trim($request->cliente);
                $query->where('cliente_nombre', 'LIKE', "%{$cliente}%");
            }

            $pedidos = $query
                ->orderByDesc('fecha_pedido')
                ->orderByDesc('id')
                ->get();

            if ($pedidos->isEmpty()) {
                return response()->json([
                    'status'  => 'not_found',
                    'message' => 'No se encontraron pedidos con los filtros enviados.',
                    'filters' => [
                        'pedido_id' => $request->pedido_id,
                        'telefono'  => $request->telefono,
                        'cliente'   => $request->cliente,
                    ],
                ], 404);
            }

            return response()->json([
                'status'       => 'success',
                'total_orders' => $pedidos->count(),
                'filters'      => [
                    'pedido_id' => $request->pedido_id,
                    'telefono'  => $request->telefono,
                    'cliente'   => $request->cliente,
                ],
                'orders'       => $pedidos->map(function ($pedido) use ($formatearCantidad) {
                    return [
                        'id'                   => $pedido->id,
                        'fecha'                => optional($pedido->fecha_pedido)?->format('d/m/Y H:i'),
                        'estado'               => $pedido->estado,
                        'hora_entrega'         => $pedido->hora_entrega ?? 'Por confirmar',
                        'sede'                 => $pedido->sede->nombre ?? 'No especificada',
                        'cliente'              => $pedido->cliente_nombre,
                        'telefono'             => $pedido->telefono,
                        'total'                => $pedido->total,
                        'total_formateado'     => number_format($pedido->total, 0, ',', '.'),
                        'notas'                => $pedido->notas,
                        'resumen_conversacion' => $pedido->resumen_conversacion,
                        'productos'            => $pedido->detalles->map(function ($detalle) use ($formatearCantidad) {
                            return [
                                'producto'        => $detalle->producto,
                                'cantidad'        => $formatearCantidad((float) $detalle->cantidad),
                                'unidad'          => $detalle->unidad,
                                'precio_unitario' => $detalle->precio_unitario,
                                'subtotal'        => $detalle->subtotal,
                            ];
                        })->values(),
                    ];
                })->values(),
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR SEARCH ORDERS', [
                'error'   => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al consultar pedidos.',
            ], 500);
        }
    }

    public function showOrder($id)
    {
        try {
            $formatearCantidad = function (float $cantidad): string {
                if (fmod($cantidad, 1.0) == 0.0) {
                    return number_format($cantidad, 0, ',', '.');
                }

                return number_format($cantidad, 2, ',', '.');
            };

            $pedido = Pedido::with(['sede', 'detalles'])->find($id);

            if (!$pedido) {
                return response()->json([
                    'status'  => 'not_found',
                    'message' => 'Pedido no encontrado.',
                    'id'      => $id,
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'order'  => [
                    'id'                   => $pedido->id,
                    'fecha'                => optional($pedido->fecha_pedido)?->format('d/m/Y H:i'),
                    'estado'               => $pedido->estado,
                    'hora_entrega'         => $pedido->hora_entrega ?? 'Por confirmar',
                    'sede'                 => $pedido->sede->nombre ?? 'No especificada',
                    'cliente'              => $pedido->cliente_nombre,
                    'telefono'             => $pedido->telefono,
                    'total'                => $pedido->total,
                    'total_formateado'     => number_format($pedido->total, 0, ',', '.'),
                    'notas'                => $pedido->notas,
                    'resumen_conversacion' => $pedido->resumen_conversacion,
                    'productos'            => $pedido->detalles->map(function ($detalle) use ($formatearCantidad) {
                        return [
                            'producto'        => $detalle->producto,
                            'cantidad'        => $formatearCantidad((float) $detalle->cantidad),
                            'unidad'          => $detalle->unidad,
                            'precio_unitario' => $detalle->precio_unitario,
                            'subtotal'        => $detalle->subtotal,
                        ];
                    })->values(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR SHOW ORDER', [
                'error' => $e->getMessage(),
                'id'    => $id,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al consultar el pedido.',
            ], 500);
        }
    }

    private function esConsultaEstadoPedido(string $message): bool
    {
        $message = mb_strtolower(trim($message));

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
            'pedido #',
            'pedido ',
            'orden #',
            'orden ',
        ];

        foreach ($frases as $frase) {
            if (str_contains($message, $frase)) {
                return true;
            }
        }

        return false;
    }

    private function resolverConsultaEstadoPedido(string $from, string $name = 'Cliente', string $message = ''): string
    {
        $telefonoNormalizado = $this->normalizarTelefono($from);

        $pedidos = Pedido::with(['sede', 'detalles'])
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->get()
            ->filter(function ($pedido) use ($telefonoNormalizado) {
                $pedidoTelefono = $this->normalizarTelefono($pedido->telefono);

                return $pedidoTelefono === $telefonoNormalizado
                    || str_contains($pedidoTelefono, $telefonoNormalizado)
                    || str_contains($telefonoNormalizado, $pedidoTelefono);
            })
            ->values();

        if ($pedidos->isEmpty()) {
            return "Hola {$name} 😊\nNo encontré pedidos registrados con este número.\nSi deseas, puedo ayudarte a realizar un nuevo pedido.";
        }

        $pedidoIdSolicitado = $this->extraerNumeroPedidoDesdeMensaje($message);

        if ($pedidoIdSolicitado) {
            $pedido = $pedidos->firstWhere('id', $pedidoIdSolicitado);

            if (!$pedido) {
                $mensaje = [];
                $mensaje[] = "Hola {$name} 😊";
                $mensaje[] = "No encontré el pedido #{$pedidoIdSolicitado} asociado a este número.";
                $mensaje[] = "Estos son los pedidos que sí encontré:";

                foreach ($pedidos->take(10) as $item) {
                    $mensaje[] = "• #{$item->id} - " . $this->traducirEstadoPedido($item->estado);
                }

                $mensaje[] = "Escríbeme el número del pedido que deseas consultar. Ejemplo: pedido #{$pedidos->first()->id}";

                return implode("\n", $mensaje);
            }

            return $this->formatearRespuestaPedidoEspecifico($pedido, $name);
        }

        if ($pedidos->count() === 1) {
            return $this->formatearRespuestaPedidoEspecifico($pedidos->first(), $name);
        }

        $mensaje = [];
        $mensaje[] = "Hola {$name} 😊";
        $mensaje[] = "Encontré *{$pedidos->count()} pedidos* asociados a este número:";
        $mensaje[] = "";

        foreach ($pedidos->take(10) as $pedido) {
            $mensaje[] = "📦 Pedido #{$pedido->id}";
            $mensaje[] = "Estado: " . $this->traducirEstadoPedido($pedido->estado);
            $mensaje[] = "Fecha: " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i');
            $mensaje[] = "Sede: " . ($pedido->sede->nombre ?? 'No especificada');
            $mensaje[] = "";
        }

        $mensaje[] = "Si deseas consultar uno en detalle, escríbeme por ejemplo: *pedido #{$pedidos->first()->id}*";

        return implode("\n", $mensaje);
    }

    private function esSolicitudModificarPedido(string $message): bool
    {
        $message = mb_strtolower(trim($message));

        $frases = [
            'cancelar pedido',
            'cancelar mi pedido',
            'quiero cancelar',
            'quiero cancelar mi pedido',
            'anular pedido',
            'adicionar pedido',
            'adicionar mi pedido',
            'quiero adicionar',
            'agregar al pedido',
            'agregar productos al pedido',
            'modificar pedido',
            'editar pedido',
            'cambiar pedido',
        ];

        foreach ($frases as $frase) {
            if (str_contains($message, $frase)) {
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

        $telefonoNormalizado = $this->normalizarTelefono($from);

        $pedidos = Pedido::with(['sede', 'detalles'])
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->get()
            ->filter(function ($pedido) use ($telefonoNormalizado) {
                $pedidoTelefono = $this->normalizarTelefono($pedido->telefono);

                return $pedidoTelefono === $telefonoNormalizado
                    || str_contains($pedidoTelefono, $telefonoNormalizado)
                    || str_contains($telefonoNormalizado, $pedidoTelefono);
            })
            ->values();

        if ($pedidos->isEmpty()) {
            return "Hola {$name} 😊\nNo encontré pedidos asociados a este número para {$accion}.";
        }

        $pedidoIdSolicitado = $this->extraerNumeroPedidoDesdeMensaje($message);

        if ($pedidoIdSolicitado) {
            $pedido = $pedidos->firstWhere('id', $pedidoIdSolicitado);

            if (!$pedido) {
                $mensaje = [];
                $mensaje[] = "Hola {$name} 😊";
                $mensaje[] = "No encontré el pedido #{$pedidoIdSolicitado} asociado a este número.";
                $mensaje[] = "Estos son los pedidos disponibles:";

                foreach ($pedidos->take(10) as $item) {
                    $mensaje[] = "• Pedido #{$item->id} - " . $this->traducirEstadoPedido($item->estado);
                }

                $mensaje[] = "Escríbeme por ejemplo: *{$accion} pedido #{$pedidos->first()->id}*";

                return implode("\n", $mensaje);
            }

            return $this->validarAnsYResponder($pedido, $accion, $name);
        }

        if ($pedidos->count() === 1) {
            return $this->validarAnsYResponder($pedidos->first(), $accion, $name);
        }

        $mensaje = [];
        $mensaje[] = "Hola {$name} 😊";
        $mensaje[] = "Encontré varios pedidos asociados a este número.";
        $mensaje[] = "Para {$accion}, indícame cuál deseas modificar:";

        foreach ($pedidos->take(10) as $pedido) {
            $mensaje[] = "• Pedido #{$pedido->id} - " . $this->traducirEstadoPedido($pedido->estado) . " - " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i');
        }

        $mensaje[] = "Ejemplo: *{$accion} pedido #{$pedidos->first()->id}*";

        return implode("\n", $mensaje);
    }

    private function detectarAccionPedido(string $message): ?string
    {
        $message = mb_strtolower(trim($message));

        if (
            str_contains($message, 'cancelar')
            || str_contains($message, 'anular')
        ) {
            return 'cancelar';
        }

        if (
            str_contains($message, 'adicionar')
            || str_contains($message, 'agregar')
            || str_contains($message, 'modificar')
            || str_contains($message, 'editar')
            || str_contains($message, 'cambiar')
        ) {
            return 'adicionar';
        }

        return null;
    }

    private function validarAnsYResponder(Pedido $pedido, string $accion, string $name): string
    {
        $ansMinutos = $this->obtenerAnsMinutos($accion);

        if (!$ansMinutos) {
            return "Hola {$name} 😊\nNo hay un ANS configurado para {$accion} el pedido #{$pedido->id}.";
        }

        $minutosTranscurridos = $pedido->fecha_pedido->diffInMinutes(now());
        $puede = $minutosTranscurridos <= $ansMinutos;

        $mensaje = [];
        $mensaje[] = "Hola {$name} 😊";
        $mensaje[] = "Pedido #{$pedido->id}";
        $mensaje[] = "Estado actual: " . $this->traducirEstadoPedido($pedido->estado);
        $mensaje[] = "Fecha del pedido: " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i');
        $mensaje[] = "Tiempo transcurrido: {$minutosTranscurridos} minuto(s)";
        $mensaje[] = "ANS para {$accion}: {$ansMinutos} minuto(s)";
        $mensaje[] = "";

        if (!$puede) {
            $mensaje[] = "❌ Ya no es posible {$accion} este pedido porque el tiempo permitido expiró.";
            return implode("\n", $mensaje);
        }

        if ($accion === 'cancelar') {
            $mensaje[] = "✅ Sí es posible cancelar este pedido.";
            $mensaje[] = "Responde *CONFIRMAR CANCELACIÓN* para continuar.";
        } else {
            $mensaje[] = "✅ Sí es posible adicionar o modificar este pedido.";
            $mensaje[] = "Escríbeme qué producto deseas agregar o cambiar en el pedido #{$pedido->id}.";
        }

        return implode("\n", $mensaje);
    }

    private function obtenerAnsMinutos(string $accion): ?int
    {
        return AnsPedido::where('accion', $accion)
            ->where('activo', true)
            ->value('tiempo_minutos');
    }

    private function construirResumenAns(): string
    {
        $crear = $this->obtenerAnsMinutos('crear') ?? 'No definido';
        $adicionar = $this->obtenerAnsMinutos('adicionar') ?? 'No definido';
        $cancelar = $this->obtenerAnsMinutos('cancelar') ?? 'No definido';

        return <<<TXT
ANS DEL SISTEMA:
- Crear pedido: {$crear} minuto(s)
- Adicionar pedido: {$adicionar} minuto(s)
- Cancelar pedido: {$cancelar} minuto(s)
TXT;
    }

    private function formatearRespuestaPedidoEspecifico(Pedido $pedido, string $name = 'Cliente'): string
    {
        $mensaje = [];
        $mensaje[] = "Hola {$name} 😊";
        $mensaje[] = "Tu pedido #{$pedido->id} está actualmente en: *" . $this->traducirEstadoPedido($pedido->estado) . "*";
        $mensaje[] = "📅 Fecha: " . optional($pedido->fecha_pedido)?->format('d/m/Y H:i');
        $mensaje[] = "📍 Sede: " . ($pedido->sede->nombre ?? 'No especificada');

        if (!empty($pedido->hora_entrega)) {
            $mensaje[] = "🕒 Hora estimada: {$pedido->hora_entrega}";
        }

        if ($pedido->detalles && $pedido->detalles->count()) {
            $mensaje[] = "";
            $mensaje[] = "🛒 Detalle del pedido:";
            foreach ($pedido->detalles as $detalle) {
                $cantidad = $this->formatearCantidadPedido((float) $detalle->cantidad);
                $mensaje[] = "• {$detalle->producto} - {$cantidad} {$detalle->unidad}";
            }
        }

        $mensaje[] = "";
        $mensaje[] = "💰 Total: $" . number_format((float) $pedido->total, 0, ',', '.');

        return implode("\n", $mensaje);
    }

    private function extraerNumeroPedidoDesdeMensaje(string $message): ?int
    {
        $message = mb_strtolower(trim($message));

        $patrones = [
            '/pedido\s*#\s*(\d+)/i',
            '/pedido\s+numero\s+(\d+)/i',
            '/pedido\s+número\s+(\d+)/i',
            '/pedido\s+(\d+)/i',
            '/orden\s*#\s*(\d+)/i',
            '/orden\s+numero\s+(\d+)/i',
            '/orden\s+número\s+(\d+)/i',
            '/orden\s+(\d+)/i',
            '/#\s*(\d+)/i',
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $message, $matches)) {
                return isset($matches[1]) ? (int) $matches[1] : null;
            }
        }

        return null;
    }

    private function formatearCantidadPedido(float $cantidad): string
    {
        if (fmod($cantidad, 1.0) == 0.0) {
            return number_format($cantidad, 0, ',', '.');
        }

        return number_format($cantidad, 2, ',', '.');
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

    private function enviarRespuestaWhatsapp(string $from, string $reply, $connectionId = null): void
    {
        try {
            $token = $this->loginWhatsapp();

            if (!$token) {
                Log::error('❌ No se pudo obtener token de WhatsApp');
                return;
            }

            $payload = [
                'number' => $from,
                'body'   => $reply,
            ];

            if ($connectionId) {
                $payload['whatsappId'] = $connectionId;
                $payload['connectionId'] = $connectionId;
            }

            Log::info('📤 ENVIANDO RESPUESTA A WHATSAPP', [
                'url'     => 'https://wa-api.tecnobyteapp.com:1422/api/messages/send',
                'payload' => $payload,
            ]);

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post('https://wa-api.tecnobyteapp.com:1422/api/messages/send', $payload);

            if ($response->successful()) {
                Log::info('✅ RESPUESTA ENVIADA A WHATSAPP', [
                    'status'        => $response->status(),
                    'body'          => $response->json(),
                    'phone'         => $from,
                    'connection_id' => $connectionId,
                ]);
            } else {
                Log::error('⚠️ WHATSAPP API RESPONDIÓ CON ERROR', [
                    'status'        => $response->status(),
                    'body'          => $response->body(),
                    'phone'         => $from,
                    'connection_id' => $connectionId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('❌ ERROR ENVIANDO A WHATSAPP', [
                'error'         => $e->getMessage(),
                'phone'         => $from,
                'connection_id' => $connectionId,
            ]);
        }
    }

    private function loginWhatsapp(): ?string
    {
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

            Log::info('🔐 LOGIN WHATSAPP OK', [
                'token_prefix' => $token ? substr($token, 0, 20) . '...' : null,
            ]);

            return $token;
        } catch (\Throwable $e) {
            Log::error('❌ EXCEPCIÓN LOGIN WHATSAPP', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function infoEmpresa(): string
    {
        return <<<TXT
Alimentos La Hacienda
- Empresa con más de 25 años de experiencia.
- Ubicada en Bello, Antioquia.
- Se enfoca en calidad, frescura y servicio al cliente.
- Opera con domicilios, sedes físicas y atención directa.
- El sistema de atención está conectado a un sistema de pedidos.
TXT;
    }

    private function getSystemPromptForAIEmpresa(
        string $pedidosInfo = '',
        string $infoEmpresa = '',
        string $name = 'Cliente',
        string $ansInfo = ''
    ): string {
        return <<<PROMPT
Eres el asesor comercial de Alimentos La Hacienda.

Hablas como una persona real del equipo de ventas.
Nunca digas que eres una inteligencia artificial.
Si el cliente pregunta si eres humano responde únicamente:
"Soy asesor del equipo de Alimentos La Hacienda y estoy aquí para ayudarte con tu pedido."

CONTEXTO:
{$infoEmpresa}

HISTORIAL DEL CLIENTE:
{$pedidosInfo}

{$ansInfo}

REGLAS DE ESTILO:
- Lenguaje natural
- Cercano pero profesional
- Máximo 3 a 5 líneas por mensaje
- No párrafos largos
- No sonar robótico
- No usar lenguaje técnico
- No hacer muchas preguntas juntas
- Usa emojis moderados: 😊 👍 🚚

SALUDO:
Siempre saluda primero.

FLUJO:
1. Entender qué necesita
2. Validar ubicación preguntando: ¿En qué barrio se encuentra?
3. Validar cobertura
4. Construir pedido
5. Pedir datos solo cuando ya decidió
6. Confirmar
7. Cerrar

REGLAS DE ANS:
- Si el cliente pregunta por cancelar o adicionar, primero debes respetar la validación que ya hizo el sistema.
- No prometas cancelar o adicionar si el tiempo ANS ya expiró.
- Si el sistema indica que sí es posible, guía al cliente al siguiente paso.

SOLICITUD DE DATOS:
Para continuar necesito:
✅ Nombre
✅ Dirección
✅ Barrio
✅ Teléfono

CONFIRMACIÓN FINAL:
Antes de confirmar el pedido, SIEMPRE pide validación al cliente.

Debes iniciar así:
"Por favor revisa tu pedido 👇"

Luego mostrar:
Perfecto 👍
Te comparto el detalle:

📦 Pedido
[detalle]

📍 Dirección
[dirección]

👤 Recibe
[nombre]

📞 Teléfono
[teléfono]

💵 Pago
Contra entrega

Y SIEMPRE terminar con:
"¿Todo correcto para confirmar?"

SOLO cuando el cliente confirme claramente con mensajes como:
- sí
- si
- correcto
- sí correcto
- confirmado
- si confirmado
- ok
- listo
- confirmar

y ya tengas productos, dirección, barrio, nombre y teléfono, debes:

1. responder natural
2. incluir JSON válido entre [JSON_ORDER] y [/JSON_ORDER]
3. terminar exactamente con [PEDIDO_CONFIRMADO]

JSON OBLIGATORIO:
[JSON_ORDER]{
  "products":[
    {
      "name":"Producto",
      "quantity":1,
      "unit":"unidad",
      "price":0,
      "subtotal":0
    }
  ],
  "address":"Dirección completa",
  "neighborhood":"Barrio",
  "location":"Ciudad o zona",
  "customer_name":"Nombre del cliente",
  "phone":"Teléfono",
  "payment_method":"contra entrega",
  "pickup_time":"",
  "total":0,
  "notes":"Resumen corto del pedido"
}[/JSON_ORDER][PEDIDO_CONFIRMADO]

IMPORTANTE:
- No generes [PEDIDO_CONFIRMADO] si aún falta nombre, dirección, barrio o teléfono.
- No inventes productos ni cantidades.
- Si no sabes pickup_time, envíalo vacío: ""
- No metas datos importantes solo en notes.
- address, neighborhood, customer_name y phone deben venir separados.
PROMPT;
    }

    private function buscarPedidosCliente(string $from, string $message): string
    {
        $message = strtolower($message);

        $palabras = [
            'pedido',
            'domicilio',
            'orden',
            'estado',
            'seguimiento',
            'compra',
            'comprar',
            'direccion',
            'dirección',
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

        foreach ($palabras as $p) {
            if (str_contains($message, $p)) {
                $esConsulta = true;
                break;
            }
        }

        if (!$esConsulta) {
            return '';
        }

        $telefonoNormalizado = $this->normalizarTelefono($from);

        $pedidos = Pedido::with(['sede', 'detalles'])
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id')
            ->get()
            ->filter(function ($pedido) use ($telefonoNormalizado) {
                $pedidoTelefono = $this->normalizarTelefono($pedido->telefono);

                return $pedidoTelefono === $telefonoNormalizado
                    || str_contains($pedidoTelefono, $telefonoNormalizado)
                    || str_contains($telefonoNormalizado, $pedidoTelefono);
            })
            ->take(3);

        if ($pedidos->isEmpty()) {
            return "ℹ️ No se encontraron pedidos recientes para este número.\n";
        }

        $texto = "📦 HISTORIAL DEL CLIENTE:\n\n";

        foreach ($pedidos as $pedido) {
            $texto .= "Pedido #{$pedido->id}\n";
            $texto .= "Estado: {$pedido->estado}\n";
            $texto .= "Fecha: {$pedido->fecha_pedido->format('d/m/Y H:i')}\n";
            $texto .= "Barrio/Sede: " . ($pedido->sede->nombre ?? 'No especificada') . "\n\n";
        }

        return $texto;
    }

    private function guardarPedidoDesdeRespuestaIA(
        string $reply,
        string $from,
        string $name,
        array $conversationHistory,
        string $cacheKey
    ): string {
        try {
            DB::beginTransaction();

            if (!preg_match('/\[JSON_ORDER\](.*?)\[\/JSON_ORDER\]/s', $reply, $jsonMatches)) {
                throw new \Exception('No se encontró [JSON_ORDER]');
            }

            $jsonString = trim($jsonMatches[1]);
            $orderData = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON inválido: ' . json_last_error_msg());
            }

            if (empty($orderData['products']) || !is_array($orderData['products'])) {
                throw new \Exception('Pedido sin productos');
            }

            $confirmKey = "pedido_confirmado_{$from}";
            if (Cache::has($confirmKey)) {
                Log::warning('⚠️ PEDIDO YA CONFIRMADO RECIENTEMENTE', [
                    'phone' => $from
                ]);

                DB::rollBack();
                return "Tu pedido ya fue registrado anteriormente 😊";
            }

            Cache::put($confirmKey, true, now()->addMinutes(2));

            $sedeNombre = $orderData['location']
                ?? $orderData['neighborhood']
                ?? 'No especificada';

            $sede = Sede::where('nombre', 'LIKE', "%{$sedeNombre}%")->first() ?? Sede::first();

            $pickupTime = null;
            if (!empty($orderData['pickup_time'])) {
                $pickupTime = $orderData['pickup_time'];
            }

            $notas = $orderData['notes'] ?? '';
            if (!empty($orderData['address'])) {
                $notas .= ($notas ? ' | ' : '') . 'Dirección: ' . $orderData['address'];
            }
            if (!empty($orderData['neighborhood'])) {
                $notas .= ($notas ? ' | ' : '') . 'Barrio: ' . $orderData['neighborhood'];
            }
            if (!empty($orderData['payment_method'])) {
                $notas .= ($notas ? ' | ' : '') . 'Pago: ' . $orderData['payment_method'];
            }

            $pedido = Pedido::create([
                'sede_id'               => $sede?->id,
                'fecha_pedido'          => now(),
                'hora_entrega'          => $pickupTime,
                'estado'                => 'confirmado',
                'total'                 => $orderData['total'] ?? 0,
                'notas'                 => $notas ?: 'Solicitud realizada vía WhatsApp',
                'cliente_nombre'        => $orderData['customer_name'] ?? $name,
                'telefono'              => $orderData['phone'] ?? $from,
                'canal'                 => 'whatsapp',
                'conversacion_completa' => json_encode($conversationHistory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'resumen_conversacion'  => $orderData['notes'] ?? '',
            ]);

            foreach (($orderData['products'] ?? []) as $product) {
                DetallePedido::create([
                    'pedido_id'       => $pedido->id,
                    'producto'        => $product['name'] ?? 'Producto/Servicio',
                    'cantidad'        => (float) ($product['quantity'] ?? 1),
                    'unidad'          => $product['unit'] ?? 'unidad',
                    'precio_unitario' => (float) ($product['price'] ?? 0),
                    'subtotal'        => (float) ($product['subtotal'] ?? 0),
                ]);
            }

            DB::commit();

            broadcast(new PedidoConfirmado($pedido));
            Cache::forget($cacheKey);

            $mensajeCliente = trim(preg_replace('/\[JSON_ORDER\].*?\[\/JSON_ORDER\]/s', '', $reply));
            $mensajeCliente = trim(str_replace('[PEDIDO_CONFIRMADO]', '', $mensajeCliente));

            return $mensajeCliente . "\n\n📋 Número de solicitud: #{$pedido->id}\nGuárdalo para consultar el estado.";
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('❌ ERROR CRÍTICO AL GUARDAR SOLICITUD', [
                'error'             => $e->getMessage(),
                'reply_ia_completo' => $reply,
            ]);

            return '⚠️ Tu pedido no se pudo registrar en este momento. Ya lo estamos revisando.';
        }
    }
}