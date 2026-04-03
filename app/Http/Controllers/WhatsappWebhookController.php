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
            return response()->json([
                'status' => 'error',
                'message' => 'Payload vacío',
            ], 400);
        }

        // ===============================
        // NORMALIZAR DATOS DEL WEBHOOK
        // ===============================
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
            'from' => $from,
            'name' => $name,
            'message' => $message,
            'message_id' => $messageId,
            'from_me' => $fromMe,
            'connection_id' => $connectionId,
            'connection_name' => $connectionName,
        ]);

        if (!$from || !$message) {
            Log::warning('⚠️ Mensaje ignorado por falta de datos', [
                'from' => $from,
                'name' => $name,
                'message' => $message,
                'payload' => $data,
            ]);

            return response()->json([
                'status' => 'ignored'
            ]);
        }

        // Ignorar mensajes propios
        if ($fromMe === true) {
            Log::info('↩️ MENSAJE PROPIO IGNORADO', [
                'message_id' => $messageId,
                'from' => $from,
            ]);

            return response()->json([
                'status' => 'self_message_ignored'
            ]);
        }

        // Evitar procesar el mismo mensaje dos veces
        if ($messageId) {
            $dedupeKey = "whatsapp_msg_{$messageId}";

            if (Cache::has($dedupeKey)) {
                Log::warning('⚠️ MENSAJE DUPLICADO IGNORADO', [
                    'message_id' => $messageId,
                    'from' => $from,
                    'message' => $message,
                ]);

                return response()->json([
                    'status' => 'duplicate_ignored'
                ]);
            }

            Cache::put($dedupeKey, true, now()->addMinutes(10));
        }

        Log::info('✅ MENSAJE CLIENTE', [
            'from' => $from,
            'name' => $name,
            'message' => $message,
            'message_id' => $messageId,
            'connection_id' => $connectionId,
        ]);

        // ===============================
        // HISTORIAL DE CONVERSACIÓN
        // ===============================
        $cacheKey = "whatsapp_chat_{$from}";
        $conversationHistory = Cache::get($cacheKey, []);

        $conversationHistory[] = [
            'role' => 'user',
            'content' => $message,
        ];

        if (count($conversationHistory) > 20) {
            $conversationHistory = array_slice($conversationHistory, -20);
        }

        $pedidosInfo = $this->buscarPedidosCliente($from, $message);

        $systemPrompt = $this->getSystemPromptForAIEmpresa(
            $pedidosInfo,
            $this->infoEmpresa(),
            $name
        );

        try {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];

            foreach ($conversationHistory as $msg) {
                $messages[] = $msg;
            }

            $responseIA = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(35)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'temperature' => 0.5,
                    'max_tokens' => 500,
                ]);

            if ($responseIA->failed()) {
                throw new \Exception(
                    'OpenAI error: ' . $responseIA->status() . ' ' . $responseIA->body()
                );
            }

            $reply = $responseIA->json('choices.0.message.content')
                ?? 'En este momento no logré procesar tu mensaje. ¿Me lo repites con un poquito más de detalle?';

            $conversationHistory[] = [
                'role' => 'assistant',
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
                'reply' => $reply,
                'phone' => $from,
                'message_id' => $messageId,
                'connection_id' => $connectionId,
            ]);

            if ($messageId) {
                $responseKey = "whatsapp_response_{$messageId}";

                if (Cache::has($responseKey)) {
                    Log::warning('⚠️ RESPUESTA YA ENVIADA', [
                        'message_id' => $messageId,
                        'from' => $from,
                    ]);

                    return response()->json([
                        'status' => 'response_already_sent'
                    ]);
                }

                Cache::put($responseKey, true, now()->addMinutes(10));
            }

            $this->enviarRespuestaWhatsapp($from, $reply, $connectionId);

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
                'body' => $reply,
            ];

            if ($connectionId) {
                $payload['whatsappId'] = $connectionId;
                $payload['connectionId'] = $connectionId;
            }

            Log::info('📤 ENVIANDO RESPUESTA A WHATSAPP', [
                'url' => 'https://wa-api.tecnobyteapp.com:1422/api/messages/send',
                'payload' => $payload,
            ]);

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post('https://wa-api.tecnobyteapp.com:1422/api/messages/send', $payload);

            if ($response->successful()) {
                Log::info('✅ RESPUESTA ENVIADA A WHATSAPP', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'phone' => $from,
                    'connection_id' => $connectionId,
                ]);
            } else {
                Log::error('⚠️ WHATSAPP API RESPONDIÓ CON ERROR', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'phone' => $from,
                    'connection_id' => $connectionId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('❌ ERROR ENVIANDO A WHATSAPP', [
                'error' => $e->getMessage(),
                'phone' => $from,
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
                    'email' => env('WHATSAPP_API_EMAIL'),
                    'password' => env('WHATSAPP_API_PASSWORD'),
                ]);

            if ($response->failed()) {
                Log::error('❌ ERROR LOGIN WHATSAPP', [
                    'status' => $response->status(),
                    'body' => $response->body(),
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
        string $name = 'Cliente'
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
Ejemplo:
Buenos días 😊
Gracias por comunicarse con Alimentos La Hacienda, {$name}
¿En qué puedo ayudarte?

FLUJO:
1. Entender qué necesita
2. Validar ubicación preguntando: ¿En qué barrio se encuentra?
3. Validar cobertura
4. Construir pedido
5. Pedir datos solo cuando ya decidió
6. Confirmar
7. Cerrar

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
  "pickup_time":"Horario requerido",
  "total":0,
  "notes":"Resumen corto del pedido"
}[/JSON_ORDER][PEDIDO_CONFIRMADO]

IMPORTANTE:
- No generes [PEDIDO_CONFIRMADO] si aún falta nombre, dirección, barrio o teléfono.
- No metas datos importantes solo en notes.
- address, neighborhood, customer_name y phone deben venir separados.
- No inventes productos ni cantidades.
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
            'contra entrega'
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
                'sede_id' => $sede?->id,
                'fecha_pedido' => now(),
                'hora_entrega' => $orderData['pickup_time'] ?? 'Por confirmar',
                'estado' => 'confirmado',
                'total' => $orderData['total'] ?? 0,
                'notas' => $notas ?: 'Solicitud realizada vía WhatsApp',
                'cliente_nombre' => $orderData['customer_name'] ?? $name,
                'telefono' => $orderData['phone'] ?? $from,
                'canal' => 'whatsapp',
                'conversacion_completa' => json_encode($conversationHistory, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'resumen_conversacion' => $orderData['notes'] ?? '',
            ]);

            foreach (($orderData['products'] ?? []) as $product) {
                DetallePedido::create([
                    'pedido_id' => $pedido->id,
                    'producto' => $product['name'] ?? 'Producto/Servicio',
                    'cantidad' => (float)($product['quantity'] ?? 1),
                    'unidad' => $product['unit'] ?? 'unidad',
                    'precio_unitario' => (float)($product['price'] ?? 0),
                    'subtotal' => (float)($product['subtotal'] ?? 0),
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