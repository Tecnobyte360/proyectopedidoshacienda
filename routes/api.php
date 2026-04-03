<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Sede;
use App\Events\PedidoConfirmado;

// =========================================================
// 🌐 RUTAS BÁSICAS DEL WEBHOOK Y API
// =========================================================

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Verificación (GET) - Útil para verificar si el webhook está activo
Route::get('whatsapp-webhook', function () {
    return response()->json(['status' => 'webhook active']);
});

// =========================================================
// 📩 RECEPCIÓN DE MENSAJES (POST)
// =========================================================

Route::post('whatsapp-webhook', function (Request $request) {

    // =================================================================
    // 💡 HELPERS LOCALES
    // =================================================================

    /**
     * Formatea la cantidad eliminando decimales innecesarios.
     */
    $formatearCantidad = function (float $cantidad): string {
        if (fmod($cantidad, 1) == 0.0) {
            return number_format($cantidad, 0, ',', '.');
        }
        return number_format($cantidad, 2, ',', '.');
    };

    /**
     * Info empresa (contexto público/operativo). No inventar datos.
     * Puedes mover esto a DB/Cache luego si quieres editar sin despliegue.pero 
     */
    $infoEmpresa = <<<TXT
DOBLAMOS S.A.S. (Colombia)
- Empresa especializada en transformación y venta de acero.
- Líneas: venta de acero, centro de servicios / transformación (corte, doblez, perforado, etc.), estructuras metálicas, fachadas metálicas (Vortex), formaletas metálicas, y soluciones relacionadas.
Reglas:
- No inventar inventario, precios, tiempos de entrega ni sedes exactas si el cliente no las da.
- Si faltan datos, preguntar como asesor: tipo de material, calibre/espesor, medidas, largo, cantidad, ciudad/destino y si es entrega o recoge.
TXT;

    /**
     * Prompt Doblamos (tono humano, sin sonar "bot").
     */
    $getSystemPromptForAIDoblamos = function (string $pedidosInfo = '', string $infoEmpresa = ''): string {
        return <<<PROMPT
Actúas como un **asesor comercial por WhatsApp** de **DOBLAMOS S.A.S.**.

REGLA CLAVE DE TONO (MUY IMPORTANTE):
- Escribe como una persona real: corto, natural, sin formalismos excesivos.
- No uses lenguaje de “bot” (evita: “como IA”, “procesé tu mensaje”, “no puedo”, “soy un bot”).
- NO digas que eres humano.
- NO menciones que eres IA/bot a menos que el cliente lo pregunte directamente.
  - Si lo pregunta: “Soy el asistente virtual de Doblamos, pero te atiendo igual y si necesitas te paso con un asesor.”

OBJETIVO:
1) Ayudar a cotizar y recibir solicitudes/pedidos de productos/servicios de Doblamos.
2) Hacer preguntas inteligentes cuando falte información.
3) No inventar inventario, precios, sedes exactas o tiempos. Si no hay datos: preguntar o decir “te lo confirmamos”.

CONTEXTO OFICIAL (NO INVENTAR):
{$infoEmpresa}

MEMORIA DEL CLIENTE (SI HAY):
{$pedidosInfo}

CÓMO MANEJAR “FALTA INFO”:
- Nunca respondas “no sé”.
- Responde como asesor: propones 2–3 opciones típicas y haces 2–4 preguntas para aterrizar.
- Ejemplo: si piden “2 barras”, pregunta: ¿corrugada o lisa?, ¿diámetro/mm o referencia?, ¿largo (6m o a medida)?, ¿ciudad y entrega/recoge?

FORMATO:
- 1 a 4 líneas normalmente.
- Si hay varias opciones, usa viñetas cortas.
- Emojis: muy pocos (máximo 1 y solo si encaja).

CHECKLIST PARA CERRAR UNA SOLICITUD:
1) Producto exacto (barra, lámina, tubo, perfil, viga, etc. / o servicio de transformación).
2) Especificaciones: diámetro / calibre / espesor, medidas, material/acabado si aplica.
3) Largo o formato (6m, a medida, lámina completa, etc.).
4) Cantidad y unidad (unidades, kg, toneladas, metros).
5) Ciudad/destino y si requiere entrega o recoge.
6) Si requiere transformación: corte/doblez/perforado y observaciones/tolerancias.
7) Fecha esperada.

CONSULTAS DE PEDIDOS:
- Si hay historial en {$pedidosInfo}, responde estado/fecha/resumen y siguiente paso.
- Si no hay registros, dilo y ofrece crear una nueva solicitud.

🚨 CONFIRMACIÓN Y GUARDADO (CRÍTICO):
Cuando el cliente confirme (“sí”, “confirmo”, “listo”, “dale”, etc.) y ya tengas lo mínimo, debes:
1) Confirmar en mensaje corto.
2) Incluir JSON válido entre [JSON_ORDER] y [/JSON_ORDER]
3) Terminar EXACTAMENTE con [PEDIDO_CONFIRMADO]

JSON OBLIGATORIO:
[JSON_ORDER]{
  "products":[
    {
      "name":"Producto/Servicio",
      "quantity":1,
      "unit":"unidad",
      "price":0,
      "subtotal":0,
      "specs":{
        "tipo":"",
        "medidas":"",
        "calibre_espesor":"",
        "largo":"",
        "acabado":"",
        "observaciones":""
      }
    }
  ],
  "location":"Ciudad / destino",
  "pickup_time":"Fecha u horario requerido",
  "customer_name":"Nombre del contacto",
  "total":0,
  "notes":"Resumen: uso, transformación requerida, entrega/recoge y datos clave"
}[/JSON_ORDER][PEDIDO_CONFIRMADO]
PROMPT;
    };

    /**
     * Detecta si el mensaje es una consulta de pedidos o intención comercial (Doblamos).
     */
    $detectarConsultaPedido = function (string $message): bool {
        $message = strtolower($message);

        $palabrasClave = [
            // seguimiento / estado
            'pedido', 'solicitud', 'cotizacion', 'cotización', 'orden', 'factura', 'remision', 'remisión',
            'estado', 'seguimiento', 'como va', 'cómo va', 'numero', 'número', 'guia', 'guía',
            'envio', 'envío', 'entrega', 'despacho', 'confirmado', 'listo', 'cancelado',

            // acero / transformación (genérico)
            'acero', 'barra', 'barras', 'varilla', 'varillas',
            'lamina', 'lámina', 'tubo', 'tuberia', 'tubería', 'perfil', 'viga', 'angulo', 'ángulo',
            'calibre', 'espesor', 'diametro', 'diámetro', 'mm', 'pulg', 'metros', 'kg', 'kilos', 'tonelada', 'toneladas',
            'corte', 'doblez', 'doblado', 'perforado', 'cnc', 'punzonado', 'pantografo', 'pantógrafo',
            'estructura', 'estructuras', 'fachada', 'vortex', 'formaleta', 'formaletas', 'rejilla', 'rejillas', 'cubierta', 'cubiertas',
        ];

        foreach ($palabrasClave as $palabra) {
            if (str_contains($message, $palabra)) {
                return true;
            }
        }
        return false;
    };

    // =================================================================
    // 📥 PROCESAR WEBHOOK
    // =================================================================

    $rawBody = file_get_contents('php://input');

    Log::info('📩 WEBHOOK RECIBIDO', [
        'raw_body'    => $rawBody,
        'parsed_data' => $request->all(),
        'ip'          => $request->ip(),
        'time'        => now()->toDateTimeString(),
    ]);

    $data = $request->all();

    // Intentar decodificar si el body no se parseó automáticamente
    if (empty($data) && $rawBody) {
        $data = json_decode($rawBody, true);
    }

    if (empty($data)) {
        return response()->json(['status' => 'error']);
    }

    // Extraer datos del remitente y mensaje
    $from    = $data['from'] ?? $data['phoneNumber'] ?? null;
    $message = trim($data['body'] ?? $data['message'] ?? $data['text'] ?? '');

    if (!$from || !$message) {
        Log::warning('⚠️ Mensaje ignorado por falta de datos', compact('from', 'message'));
        return response()->json(['status' => 'ignored']);
    }

    Log::info('✅ MENSAJE CLIENTE', [
        'from'    => $from,
        'message' => $message,
    ]);

    // --- RECUPERAR CONVERSACIÓN PREVIA ---
    $cacheKey            = "whatsapp_chat_{$from}";
    $conversationHistory = Cache::get($cacheKey, []);

    // Agregar mensaje del usuario al historial
    $conversationHistory[] = ['role' => 'user', 'content' => $message];

    // Limitar historial (últimos 20 mensajes)
    if (count($conversationHistory) > 20) {
        $conversationHistory = array_slice($conversationHistory, -20);
    }

    // ===============================
    // 🔍 BUSCAR PEDIDOS DEL CLIENTE (CONTEXTO)
    // ===============================

    $pedidosInfo      = '';
    $esConsultaPedido = $detectarConsultaPedido($message);

    if ($esConsultaPedido) {
        $totalPedidosCliente = Pedido::where('telefono', $from)->count();

        $pedidos = Pedido::where('telefono', $from)
            ->with(['sede', 'detalles'])
            ->orderBy('fecha_pedido', 'desc')
            ->limit(3)
            ->get();

        if ($pedidos->isNotEmpty()) {
            $pedidosInfo  = "📦 HISTORIAL DEL CLIENTE:\n\n";
            $pedidosInfo .= "• Teléfono: {$from}\n";
            $pedidosInfo .= "• Total solicitudes: {$totalPedidosCliente}\n";
            $pedidosInfo .= "• Últimas 3:\n\n";

            foreach ($pedidos as $pedido) {
                $estadoEmoji = match ($pedido->estado) {
                    'confirmado'     => '🟡',
                    'en_preparacion' => '🔵',
                    'listo'          => '🟢',
                    'entregado'      => '✅',
                    'cancelado'      => '🔴',
                    default          => '⚪',
                };

                $pedidosInfo .= "Solicitud #{$pedido->id} {$estadoEmoji}\n";
                $pedidosInfo .= "Estado: {$pedido->estado}\n";
                $pedidosInfo .= "Fecha: {$pedido->fecha_pedido->format('d/m/Y H:i')}\n";
                $pedidosInfo .= "Entrega/Programación: {$pedido->hora_entrega}\n";
                $pedidosInfo .= "Ciudad/Sede: " . ($pedido->sede->nombre ?? 'No especificada') . "\n";
                $pedidosInfo .= "Contacto: {$pedido->cliente_nombre}\n";
                $pedidosInfo .= "Total estimado: $" . number_format($pedido->total, 0, ',', '.') . "\n";

                if ($pedido->detalles->isNotEmpty()) {
                    $pedidosInfo .= "Detalle:\n";
                    foreach ($pedido->detalles as $detalle) {
                        $cantidadFormateada = $formatearCantidad($detalle->cantidad);
                        $pedidosInfo       .= "  - {$cantidadFormateada} {$detalle->unidad} de {$detalle->producto}\n";
                    }
                }

                if ($pedido->notas) {
                    $pedidosInfo .= "Notas: {$pedido->notas}\n";
                }

                $pedidosInfo .= "\n";
            }
        } else {
            $pedidosInfo  = "ℹ️ No se encontraron solicitudes recientes para este número.\n";
            $pedidosInfo .= "• Teléfono: {$from}\n\n";
        }
    }

    // ===============================
    // 🤖 CONSULTA IA (OPENAI)
    // ===============================

    $systemPrompt = $getSystemPromptForAIDoblamos($pedidosInfo, $infoEmpresa);
    $reply        = '';

    try {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($conversationHistory as $msg) {
            $messages[] = $msg;
        }

        $responseIA = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(35)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o-mini',
                'messages'    => $messages,
                'temperature' => 0.5,
                'max_tokens'  => 450,
            ]);

        if ($responseIA->failed()) {
            throw new \Exception(
                'API OpenAI no respondió correctamente: ' .
                $responseIA->status() .
                ' Body: ' .
                $responseIA->body()
            );
        }

        $reply = $responseIA->json('choices.0.message.content')
            ?? 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';

        // Guardar respuesta del asistente en el historial
        $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];

        // ===============================
        // 🎯 DETECTAR CONFIRMACIÓN Y GUARDAR SOLICITUD
        // ===============================

        if (str_contains($reply, '[PEDIDO_CONFIRMADO]')) {
            Log::info('🎉 SOLICITUD CONFIRMADA DETECTADA', ['phone' => $from]);

            try {
                DB::beginTransaction();

                $jsonString         = '';
                $orderData          = [];
                $resumenParaUsuario = 'Error al generar resumen legible.';

                // 1) EXTRAER Y DECODIFICAR JSON
                if (preg_match('/\[JSON_ORDER\](.*?)\[\/JSON_ORDER\]/s', $reply, $jsonMatches)) {
                    $jsonString = trim($jsonMatches[1]);
                    $orderData  = json_decode($jsonString, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Error al decodificar JSON de la IA: ' . json_last_error_msg());
                    }

                    // Resumen legible
                    $productosResumen = collect($orderData['products'] ?? [])->map(function ($p) use ($formatearCantidad) {
                        $cantidad = $formatearCantidad((float)($p['quantity'] ?? 1));
                        $unidad   = $p['unit'] ?? 'ud';
                        $nombre   = $p['name'] ?? 'Producto/Servicio';

                        $specs = $p['specs'] ?? [];
                        $extra = [];

                        foreach (['tipo','medidas','calibre_espesor','largo','acabado'] as $k) {
                            if (!empty($specs[$k])) $extra[] = "{$k}: {$specs[$k]}";
                        }

                        $extraTxt = $extra ? (" (" . implode(', ', $extra) . ")") : '';
                        return "{$cantidad} {$unidad} de {$nombre}{$extraTxt}";
                    })->implode(', ');

                    $resumenParaUsuario  = "PRODUCTO(S)/SERVICIO(S): {$productosResumen}\n";
                    $resumenParaUsuario .= "CIUDAD/DESTINO: " . ($orderData['location'] ?? 'No especificada') . "\n";
                    $resumenParaUsuario .= "FECHA/HORARIO: " . ($orderData['pickup_time'] ?? 'Por confirmar') . "\n";
                    $resumenParaUsuario .= "CONTACTO: " . ($orderData['customer_name'] ?? 'Cliente WhatsApp') . "\n";
                    $resumenParaUsuario .= "NOTAS: " . ($orderData['notes'] ?? 'Ninguna');
                } else {
                    throw new \Exception('La IA confirmó, pero no se encontró el bloque [JSON_ORDER]. Abortando guardado.');
                }

                // 2) Limpiar mensaje para el cliente
                $mensajeCliente = trim(preg_replace('/\[JSON_ORDER\].*?\[\/JSON_ORDER\]/s', '', $reply));
                $mensajeCliente = trim(str_replace('[PEDIDO_CONFIRMADO]', '', $mensajeCliente));

                // 3) Normalizar sede (si no cuadra, usa primera)
                $sedeNombre = $orderData['location'] ?? 'No especificada';
                $sede       = Sede::where('nombre', 'LIKE', "%{$sedeNombre}%")->first() ?? Sede::first();

                // 4) Crear pedido
                $pedido = Pedido::create([
                    'sede_id'               => $sede?->id,
                    'fecha_pedido'          => now(),
                    'hora_entrega'          => $orderData['pickup_time'] ?? 'Por confirmar',
                    'estado'                => 'confirmado',
                    'total'                 => $orderData['total'] ?? 0.00,
                    'notas'                 => $orderData['notes'] ?? 'Solicitud realizada vía WhatsApp',
                    'cliente_nombre'        => $orderData['customer_name'] ?? 'Cliente WhatsApp',
                    'telefono'              => $from,
                    'canal'                 => 'whatsapp',
                    'conversacion_completa' => json_encode($conversationHistory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'resumen_conversacion'  => $resumenParaUsuario,
                ]);

                // 5) Detalles
                $productos = $orderData['products'] ?? [];
                foreach ($productos as $product) {
                    DetallePedido::create([
                        'pedido_id'       => $pedido->id,
                        'producto'        => $product['name'] ?? 'Producto/Servicio',
                        'cantidad'        => (float) ($product['quantity'] ?? 1.0),
                        'unidad'          => $product['unit'] ?? 'unidad',
                        'precio_unitario' => (float) ($product['price'] ?? 0.00),
                        'subtotal'        => (float) ($product['subtotal'] ?? 0.00),
                    ]);
                }

                DB::commit();

                // 6) Notificar y limpiar caché
                broadcast(new PedidoConfirmado($pedido));
                Cache::forget($cacheKey);

                $reply = $mensajeCliente .
                    "\n\n📋 Número de solicitud: #{$pedido->id}\nGuárdalo para consultar el estado.";
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('❌ ERROR CRÍTICO AL GUARDAR SOLICITUD', [
                    'error'             => $e->getMessage(),
                    'reply_ia_completo' => $reply,
                    'trace'             => $e->getTraceAsString(),
                ]);
                $reply = '⚠️ Hubo un problema registrando la solicitud. Por favor intenta de nuevo o envíanos un mensaje con los datos y te ayudamos.';
            }
        }
    } catch (\Throwable $e) {
        Log::error('❌ ERROR IA o HTTP', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $reply = '⚠️ En este momento tengo una novedad técnica. Intenta de nuevo en unos minutos, porfa.';
    }

    // Guardar conversación en caché (45 minutos) si NO fue confirmación guardada
    if (!str_contains($reply, '[PEDIDO_CONFIRMADO]')) {
        Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
    }

    Log::info('💬 RESPUESTA', [
        'reply'                 => $reply,
        'conversation_messages' => count($conversationHistory),
        'phone'                 => $from,
    ]);

    // ===============================
    // 📤 ENVIAR RESPUESTA AL BOT NODE
    // ===============================

    try {
        $botResponse = Http::withToken(env('WHATSAPP_BOT_TOKEN'))
            ->timeout(10)
            ->post('http://localhost:4002/api/send', [
                'phoneNumber' => $from,
                'message'     => $reply,
            ]);

        if ($botResponse->successful()) {
            Log::info('✅ RESPUESTA ENVIADA AL BOT NODE', [
                'status' => $botResponse->status(),
                'phone'  => $from,
            ]);
        } else {
            Log::error('⚠️ BOT NODE RESPONDIÓ CON ERROR', [
                'status' => $botResponse->status(),
                'body'   => $botResponse->body(),
            ]);
        }
    } catch (\Throwable $e) {
        Log::error('❌ ERROR ENVIANDO A WHATSAPP BOT', [
            'error' => $e->getMessage(),
            'phone' => $from,
        ]);
    }

    return response()->json([
        'status'            => 'ok',
        'message_processed' => true,
    ]);
});

// =========================================================
// 🛠️ RUTAS DE ADMINISTRACIÓN Y DEBUGGING
// =========================================================

// Resetear conversación
Route::delete('whatsapp-webhook/reset/{phone}', function ($phone) {
    $cacheKey = "whatsapp_chat_{$phone}";

    if (Cache::has($cacheKey)) {
        Cache::forget($cacheKey);
        return response()->json([
            'status'  => 'success',
            'message' => 'Conversación eliminada',
            'phone'   => $phone,
        ]);
    }

    return response()->json([
        'status'  => 'not_found',
        'message' => 'No hay conversación activa',
        'phone'   => $phone,
    ], 404);
});

// Ver historial de conversación
Route::get('whatsapp-webhook/history/{phone}', function ($phone) {
    $cacheKey = "whatsapp_chat_{$phone}";
    $history  = Cache::get($cacheKey, []);

    return response()->json([
        'phone'          => $phone,
        'messages_count' => count($history),
        'history'        => $history,
    ]);
});

// Consultar pedidos de un cliente
Route::get('whatsapp-webhook/orders/{phone}', function ($phone) {

    $formatearCantidad = function (float $cantidad): string {
        if (fmod($cantidad, 1.0) == 0.0) {
            return number_format($cantidad, 0, ',', '.');
        }
        return number_format($cantidad, 2, ',', '.');
    };

    $pedidos = Pedido::where('telefono', $phone)
        ->with(['sede', 'detalles'])
        ->orderBy('fecha_pedido', 'desc')
        ->get();

    if ($pedidos->isEmpty()) {
        return response()->json([
            'status'  => 'not_found',
            'message' => 'No se encontraron solicitudes',
            'phone'   => $phone,
        ], 404);
    }

    return response()->json([
        'status'       => 'success',
        'phone'        => $phone,
        'total_orders' => $pedidos->count(),
        'orders'       => $pedidos->map(function ($pedido) use ($formatearCantidad) {
            return [
                'id'           => $pedido->id,
                'fecha'        => $pedido->fecha_pedido->format('d/m/Y H:i'),
                'estado'       => $pedido->estado,
                'hora_entrega' => $pedido->hora_entrega,
                'sede'         => $pedido->sede->nombre ?? 'No especificada',
                'cliente'      => $pedido->cliente_nombre,
                'total'        => $pedido->total,
                'productos'    => $pedido->detalles->map(function ($detalle) use ($formatearCantidad) {
                    return [
                        'producto' => $detalle->producto,
                        'cantidad' => $formatearCantidad($detalle->cantidad),
                        'unidad'   => $detalle->unidad,
                    ];
                }),
            ];
        }),
    ]);
});

// Actualizar estado de un pedido
Route::patch('whatsapp-webhook/orders/{id}/status', function (Request $request, $id) {
    $request->validate([
        'estado' => 'required|in:confirmado,en_preparacion,listo,entregado,cancelado',
    ]);

    $pedido         = Pedido::findOrFail($id);
    $estadoAnterior = $pedido->estado;

    $pedido->estado = $request->estado;
    $pedido->save();

    // Notificar al cliente el cambio de estado (mensaje Doblamos)
    $mensajeEstado = match ($request->estado) {
        'en_preparacion' => "🔵 Tu solicitud #{$id} está en gestión. Te vamos contando cualquier novedad.",
        'listo'          => "🟢 Tu solicitud #{$id} quedó confirmada/lista. Gracias por escribir a Doblamos.",
        'entregado'      => "✅ La solicitud #{$id} ya quedó finalizada. Gracias por contar con Doblamos.",
        'cancelado'      => "🔴 La solicitud #{$id} fue cancelada. Si no fuiste tú, por favor escríbenos.",
        default          => "Tu solicitud #{$id} actualizó su estado.",
    };

    try {
        Http::withToken(env('WHATSAPP_BOT_TOKEN'))
            ->timeout(10)
            ->post('http://localhost:4002/api/send', [
                'phoneNumber' => $pedido->telefono,
                'message'     => $mensajeEstado,
            ]);
    } catch (\Throwable $e) {
        Log::error('Error notificando cambio de estado', [
            'pedido_id' => $id,
            'error'     => $e->getMessage(),
        ]);
    }

    return response()->json([
        'status'  => 'success',
        'message' => 'Estado actualizado',
        'pedido'  => [
            'id'              => $pedido->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'    => $pedido->estado,
        ],
    ]);
});
