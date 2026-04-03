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
            return response()->json(['status' => 'error', 'message' => 'Payload vacío'], 400);
        }

        // Normalizar estructura real del webhook
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

        Log::info('📥 DATOS NORMALIZADOS', [
            'from' => $from,
            'name' => $name,
            'message' => $message,
        ]);

        if (!$from || !$message) {
            Log::warning('⚠️ Mensaje ignorado por falta de datos', [
                'from' => $from,
                'name' => $name,
                'message' => $message,
                'payload' => $data,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        Log::info('✅ MENSAJE CLIENTE', [
            'from'    => $from,
            'name'    => $name,
            'message' => $message,
        ]);

        $cacheKey = "whatsapp_chat_{$from}";
        $conversationHistory = Cache::get($cacheKey, []);
        $conversationHistory[] = ['role' => 'user', 'content' => $message];

        if (count($conversationHistory) > 20) {
            $conversationHistory = array_slice($conversationHistory, -20);
        }

        $pedidosInfo = $this->buscarPedidosCliente($from, $message);
        $systemPrompt = $this->getSystemPromptForAIDoblamos($pedidosInfo, $this->infoEmpresa(), $name);

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
                throw new \Exception('OpenAI error: ' . $responseIA->status() . ' ' . $responseIA->body());
            }

            $reply = $responseIA->json('choices.0.message.content')
                ?? 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';

            $conversationHistory[] = ['role' => 'assistant', 'content' => $reply];

            if (str_contains($reply, '[PEDIDO_CONFIRMADO]')) {
                $reply = $this->guardarPedidoDesdeRespuestaIA($reply, $from, $name, $conversationHistory, $cacheKey);
            } else {
                Cache::put($cacheKey, $conversationHistory, now()->addMinutes(45));
            }

            Log::info('💬 RESPUESTA', [
                'reply' => $reply,
                'phone' => $from,
            ]);

            $this->enviarRespuestaAlBot($from, $reply);

            return response()->json([
                'status' => 'ok',
                'message_processed' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR IA o HTTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo procesar',
            ], 500);
        }
    }

    private function enviarRespuestaAlBot(string $from, string $reply): void
    {
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
    }

    private function infoEmpresa(): string
    {
        return <<<TXT
DOBLAMOS S.A.S. (Colombia)
- Empresa especializada en transformación y venta de acero.
- Líneas: venta de acero, centro de servicios / transformación (corte, doblez, perforado, etc.), estructuras metálicas, fachadas metálicas (Vortex), formaletas metálicas, y soluciones relacionadas.
Reglas:
- No inventar inventario, precios, tiempos de entrega ni sedes exactas si el cliente no las da.
- Si faltan datos, preguntar como asesor: tipo de material, calibre/espesor, medidas, largo, cantidad, ciudad/destino y si es entrega o recoge.
TXT;
    }

    private function getSystemPromptForAIDoblamos(string $pedidosInfo = '', string $infoEmpresa = '', string $name = 'Cliente'): string
    {
        return <<<PROMPT
Actúas como un asesor comercial por WhatsApp de DOBLAMOS S.A.S.

Tu tono debe ser humano, breve, claro y útil.
No digas que eres un bot.
No inventes inventario, precios, tiempos ni sedes exactas.
Si faltan datos, pregunta lo mínimo necesario para avanzar.

Nombre del cliente:
{$name}

CONTEXTO OFICIAL:
{$infoEmpresa}

MEMORIA DEL CLIENTE:
{$pedidosInfo}

Si el cliente confirma y ya tienes lo mínimo:
1. confirmar en texto natural
2. incluir JSON entre [JSON_ORDER] y [/JSON_ORDER]
3. terminar con [PEDIDO_CONFIRMADO]

JSON esperado:
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
  "notes":"Resumen"
}[/JSON_ORDER][PEDIDO_CONFIRMADO]
PROMPT;
    }

    private function buscarPedidosCliente(string $from, string $message): string
    {
        $message = strtolower($message);

        $palabras = [
            'pedido', 'solicitud', 'cotizacion', 'cotización', 'orden',
            'estado', 'seguimiento', 'acero', 'barra', 'lamina', 'lámina',
            'tubo', 'perfil', 'viga'
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

        $pedidos = Pedido::where('telefono', $from)
            ->with(['sede', 'detalles'])
            ->orderBy('fecha_pedido', 'desc')
            ->limit(3)
            ->get();

        if ($pedidos->isEmpty()) {
            return "ℹ️ No se encontraron solicitudes recientes para este número.\n";
        }

        $texto = "📦 HISTORIAL DEL CLIENTE:\n\n";

        foreach ($pedidos as $pedido) {
            $texto .= "Solicitud #{$pedido->id}\n";
            $texto .= "Estado: {$pedido->estado}\n";
            $texto .= "Fecha: {$pedido->fecha_pedido->format('d/m/Y H:i')}\n";
            $texto .= "Ciudad/Sede: " . ($pedido->sede->nombre ?? 'No especificada') . "\n\n";
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

            $sedeNombre = $orderData['location'] ?? 'No especificada';
            $sede = Sede::where('nombre', 'LIKE', "%{$sedeNombre}%")->first() ?? Sede::first();

            $pedido = Pedido::create([
                'sede_id'               => $sede?->id,
                'fecha_pedido'          => now(),
                'hora_entrega'          => $orderData['pickup_time'] ?? 'Por confirmar',
                'estado'                => 'confirmado',
                'total'                 => $orderData['total'] ?? 0,
                'notas'                 => $orderData['notes'] ?? 'Solicitud realizada vía WhatsApp',
                'cliente_nombre'        => $orderData['customer_name'] ?? $name,
                'telefono'              => $from,
                'canal'                 => 'whatsapp',
                'conversacion_completa' => json_encode($conversationHistory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'resumen_conversacion'  => $orderData['notes'] ?? '',
            ]);

            foreach (($orderData['products'] ?? []) as $product) {
                DetallePedido::create([
                    'pedido_id'       => $pedido->id,
                    'producto'        => $product['name'] ?? 'Producto/Servicio',
                    'cantidad'        => (float)($product['quantity'] ?? 1),
                    'unidad'          => $product['unit'] ?? 'unidad',
                    'precio_unitario' => (float)($product['price'] ?? 0),
                    'subtotal'        => (float)($product['subtotal'] ?? 0),
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
                'error' => $e->getMessage(),
                'reply_ia_completo' => $reply,
            ]);

            return '⚠️ Hubo un problema registrando la solicitud. Por favor intenta de nuevo.';
        }
    }
}