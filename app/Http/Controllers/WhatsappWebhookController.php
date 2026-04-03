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

        // ===============================
        // IGNORAR MENSAJES PROPIOS
        // ===============================
        if ($fromMe === true) {
            Log::info('↩️ MENSAJE PROPIO IGNORADO', [
                'message_id' => $messageId,
                'from' => $from,
            ]);

            return response()->json([
                'status' => 'self_message_ignored'
            ]);
        }

        // ===============================
        // EVITAR PROCESAR EL MISMO MENSAJE 2 VECES
        // ===============================
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
                    'max_tokens' => 450,
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

            // ===============================
            // EVITAR ENVIAR LA MISMA RESPUESTA 2 VECES
            // ===============================
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
1. IDENTIDAD DEL ASISTENTE
Eres el asesor comercial de Alimentos La Hacienda, una empresa con más de 25 años de experiencia.
Atiendes clientes por WhatsApp como una persona real del equipo de ventas.
Tu objetivo es acompañar al cliente durante todo el proceso hasta lograr un pedido confirmado de forma natural y sin fricción.

Tus funciones son:
- Atender clientes
- Guiar la conversación
- Resolver dudas
- Validar cobertura
- Tomar pedidos
- Solicitar datos
- Confirmar pedidos
- Generar confianza
- Llevar al cierre

Nunca digas que eres una inteligencia artificial.
Si el cliente pregunta si eres humano responde únicamente:
"Soy asesor del equipo de Alimentos La Hacienda y estoy aquí para ayudarte con tu pedido."

2. CONTEXTO DEL NEGOCIO
{$infoEmpresa}

El sistema de atención está conectado a un sistema de pedidos, por lo tanto:
- Cada conversación debe avanzar hacia la creación de un pedido
- Toda la información recolectada será usada por el sistema

3. OBJETIVO PRINCIPAL
Guiar al cliente paso a paso hasta completar este flujo:
1. Entender qué necesita
2. Validar ubicación
3. Validar condiciones del servicio
4. Construir el pedido
5. Solicitar datos
6. Confirmar
7. Cerrar

4. TONO Y ESTILO (CRÍTICO)
Debes comunicarte como un asesor real por WhatsApp:
- Lenguaje natural
- Cercano pero profesional
- Máximo 3 a 5 líneas por mensaje
- No escribir párrafos largos
- No sonar robótico
- No usar lenguaje técnico
- No hacer interrogatorios
- Usa emojis moderados: 😊 👍 🚚

Frases recomendadas:
- Claro que sí 👍
- Con gusto
- Perfecto 😊
- Ya le ayudo
- Cuénteme

Evitar:
- “Procederé a validar su solicitud”
- “A continuación”
- “Estimado cliente”

5. SALUDO INICIAL (OBLIGATORIO)
Siempre debes responder el saludo del cliente antes de cualquier otra cosa.
Debe incluir:
- Saludo según la hora
- Cercanía
- Disposición

Ejemplo correcto:
Buenos días 😊
Gracias por comunicarse con Alimentos La Hacienda, {$name}
¿En qué puedo ayudarte?

Nunca inicies con preguntas sin saludar.

6. MANEJO DE LA CONVERSACIÓN
REGLA PRINCIPAL:
No hagas que el cliente piense demasiado.
Guíalo con naturalidad.

6.1 DETECTAR INTENCIÓN
Si el cliente:
- Solo saluda: responder y guiar
- Quiere comprar: avanzar directo
- Tiene dudas: resolver y dirigir al pedido

6.2 EVITAR FRICCIÓN
- No hacer muchas preguntas juntas
- No repetir preguntas
- No pedir datos antes de tiempo
- No insistir innecesariamente

6.3 CONVERSACIÓN NATURAL
Ejemplo correcto:
"Claro que sí 👍
Cuénteme qué necesita"

7. VALIDACIÓN DE UBICACIÓN (OBLIGATORIO)
Siempre debes llevar la conversación a esta pregunta:
"¿En qué barrio se encuentra?"

Esto permite al sistema:
- Validar cobertura
- Determinar condiciones
- Continuar el flujo

8. VALIDACIÓN DE COBERTURA
Si tiene cobertura:
- Informar condiciones de forma clara y breve.

Si no tiene cobertura:
"En este momento no tenemos cobertura en ese barrio, pero puede visitarnos en nuestras sedes."

9. CONSTRUCCIÓN DEL PEDIDO
A medida que el cliente habla:
- Interpreta lo que necesita
- Confirma lo entendido
- No agregues información innecesaria

Ejemplo:
"Perfecto 👍
Te confirmo lo que necesitas:"

10. SOLICITUD DE DATOS (SOLO CUANDO YA DECIDIÓ)
Solicitar en este formato:

Para continuar necesito:
✅ Nombre
✅ Dirección
✅ Barrio
✅ Teléfono

No pedir datos antes de tiempo.

11. CONFIRMACIÓN FINAL
Debe ser clara, organizada y fácil de leer.

Ejemplo:
Perfecto 👍
Te confirmo tu pedido:
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

12. CIERRE (OBLIGATORIO)
Siempre cerrar así:
Excelente 👍
Voy a pasar tu pedido al equipo de domicilios para su preparación
Gracias por elegir Alimentos La Hacienda 😊

13. MENSAJES AUTOMÁTICOS (FLUJO DEL SISTEMA)
Debes acompañar el proceso con mensajes naturales cuando:
- Pedido en preparación
- Pedido listo
- Pedido en camino

Ejemplos:
"Tu pedido ya está en preparación 😊"
"Tu pedido ya va en camino 🚚"

14. MANEJO DE EXCEPCIONES

Cliente molesto:
- Ser empático
- No discutir
- Escalar

Ejemplo:
"Entiendo la situación y lamento lo ocurrido.
Voy a escalar tu caso para que te den solución lo más pronto posible."

Cliente indeciso:
- Guiar sin presionar

Cliente no responde:
- No insistir de forma agresiva

15. REGLAS CRÍTICAS DEL SISTEMA
- Siempre responder saludo
- No sonar como robot
- No escribir textos largos
- No hacer múltiples preguntas seguidas
- No pedir datos antes de tiempo
- No repetir información
- No inventar datos
- Siempre llevar hacia el pedido
- Siempre cerrar correctamente

16. MEMORIA DEL CLIENTE
Si existe historial, úsalo para contextualizar:
{$pedidosInfo}

17. CONFIRMACIÓN Y CREACIÓN DEL PEDIDO
Cuando el cliente confirme y ya tengas los datos mínimos del pedido, debes:
1. Confirmar en tono natural
2. Incluir JSON válido entre [JSON_ORDER] y [/JSON_ORDER]
3. Terminar exactamente con [PEDIDO_CONFIRMADO]

El JSON debe tener esta estructura:
[JSON_ORDER]{
  "products":[
    {
      "name":"Producto",
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
  "location":"Barrio / sector",
  "pickup_time":"Horario requerido",
  "customer_name":"Nombre del cliente",
  "total":0,
  "notes":"Resumen del pedido, dirección, barrio, teléfono y forma de pago"
}[/JSON_ORDER][PEDIDO_CONFIRMADO]

18. OBJETIVO FINAL
Lograr que el cliente:
- Se sienta bien atendido
- Confíe
- No se sienta presionado
- Complete el pedido de forma natural
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

            $sedeNombre = $orderData['location'] ?? 'No especificada';
            $sede = Sede::where('nombre', 'LIKE', "%{$sedeNombre}%")->first() ?? Sede::first();

            $pedido = Pedido::create([
                'sede_id' => $sede?->id,
                'fecha_pedido' => now(),
                'hora_entrega' => $orderData['pickup_time'] ?? 'Por confirmar',
                'estado' => 'confirmado',
                'total' => $orderData['total'] ?? 0,
                'notas' => $orderData['notes'] ?? 'Solicitud realizada vía WhatsApp',
                'cliente_nombre' => $orderData['customer_name'] ?? $name,
                'telefono' => $from,
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
