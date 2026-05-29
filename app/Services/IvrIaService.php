<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConversacionIvr;
use App\Models\DetallePedido;
use App\Models\LlamadaIvr;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🎙️ Orquestador del IVR conversacional con IA.
 *
 * Flujo de un turno:
 *  1. Asterisk graba audio del cliente → POST /api/v1/ivr/conversacion/turno
 *  2. Este servicio transcribe (Whisper), llama al LLM con tools, ejecuta tool si toca
 *  3. Genera audio respuesta (OpenAI TTS) y devuelve URL pública
 *  4. Asterisk reproduce el WAV y vuelve a grabar
 */
class IvrIaService
{
    private const MODELO_LLM = 'gpt-4o-mini';   // rápido y barato; sube a gpt-4o si necesitas más
    private const VOZ_TTS    = 'nova';
    private const MODELO_TTS = 'tts-1';         // tts-1-hd para mejor calidad
    private const MAX_TURNOS = 20;              // cap de seguridad

    public function iniciarConversacion(string $uniqueId, string $callerId, ?int $tenantId = null): ConversacionIvr
    {
        $llamada = LlamadaIvr::where('asterisk_uniqueid', $uniqueId)->first();

        $tenant = $tenantId ? Tenant::find($tenantId) : ($llamada?->tenant_id ? Tenant::find($llamada->tenant_id) : Tenant::first());

        $promptSistema = $this->construirPromptSistema($tenant, $callerId);

        return ConversacionIvr::create([
            'llamada_id'        => $llamada?->id,
            'tenant_id'         => $tenant?->id,
            'asterisk_uniqueid' => $uniqueId,
            'caller_id'         => $callerId,
            'estado'            => 'activa',
            'turnos'            => 0,
            'historial'         => [
                ['role' => 'system', 'content' => $promptSistema, 'ts' => now()->toIso8601String()],
            ],
            'acciones_ejecutadas' => [],
        ]);
    }

    /**
     * Procesa un turno: recibe audio del cliente y devuelve audio de respuesta.
     */
    public function procesarTurno(string $uniqueId, string $audioFilePath): array
    {
        $conv = ConversacionIvr::where('asterisk_uniqueid', $uniqueId)
            ->where('estado', 'activa')
            ->latest()
            ->first();

        if (!$conv) {
            return ['error' => 'Conversación no encontrada o cerrada'];
        }

        if ($conv->turnos >= self::MAX_TURNOS) {
            $conv->update(['estado' => 'finalizada']);
            return ['error' => 'Máximo de turnos alcanzado', 'colgar' => true];
        }

        // 1. Whisper STT — transcribir audio del cliente
        $textoUsuario = $this->transcribir($audioFilePath);

        // Si no transcribió o fue silencio/alucinación, responder pidiendo repetir
        // (sin gastar tokens del LLM)
        if (!$textoUsuario || trim($textoUsuario) === '') {
            $audioPath = $this->generarTts('Disculpa, no te escuché bien. ¿Puedes repetir?', $uniqueId, $conv->turnos);
            if (!$audioPath) {
                return ['error' => 'TTS falló'];
            }
            // No incrementamos turnos en este caso
            return [
                'ok' => true,
                'transcripcion' => '(silencio)',
                'respuesta'     => 'Disculpa, no te escuché bien. ¿Puedes repetir?',
                'audio_url'     => url('/api/v1/ivr/audio/' . basename($audioPath)),
                'audio_filename'=> basename($audioPath, '.wav'),
                'accion'        => null,
                'colgar'        => false,
            ];
        }

        Log::info('🎙️ Cliente dijo: ' . $textoUsuario, ['conv' => $conv->id]);

        // 2. Agregar mensaje del usuario al historial
        $historial = $conv->historial;
        $historial[] = ['role' => 'user', 'content' => $textoUsuario, 'ts' => now()->toIso8601String()];

        // 3. Llamar al LLM con tools disponibles
        $respuesta = $this->llamarLlm($historial, $conv);

        if (!$respuesta) {
            return ['error' => 'LLM no respondió'];
        }

        $textoRespuesta = $respuesta['content'];
        $accionEjecutada = $respuesta['action'] ?? null;
        $deboColgar = $respuesta['hangup'] ?? false;

        Log::info('🤖 IA respondió: ' . $textoRespuesta, ['conv' => $conv->id, 'action' => $accionEjecutada]);

        // 4. Agregar respuesta al historial
        $historial[] = ['role' => 'assistant', 'content' => $textoRespuesta, 'ts' => now()->toIso8601String(), 'action' => $accionEjecutada];

        $acciones = $conv->acciones_ejecutadas ?? [];
        if ($accionEjecutada) {
            $acciones[] = ['accion' => $accionEjecutada, 'ts' => now()->toIso8601String()];
        }

        $conv->update([
            'historial'           => $historial,
            'acciones_ejecutadas' => $acciones,
            'turnos'              => $conv->turnos + 1,
            'estado'              => $deboColgar ? 'finalizada' : 'activa',
        ]);

        // 5. Generar audio respuesta con OpenAI TTS
        $audioPath = $this->generarTts($textoRespuesta, $uniqueId, $conv->turnos);
        if (!$audioPath) {
            return ['error' => 'No se pudo generar TTS'];
        }

        return [
            'ok'             => true,
            'transcripcion'  => $textoUsuario,
            'respuesta'      => $textoRespuesta,
            'audio_url'      => url('/api/v1/ivr/audio/' . basename($audioPath)),
            'audio_filename' => basename($audioPath, '.wav'),
            'accion'         => $accionEjecutada,
            'colgar'         => $deboColgar,
        ];
    }

    private function transcribir(string $audioPath): ?string
    {
        if (!file_exists($audioPath)) return null;

        // 1️⃣ Upscale 8kHz → 16kHz antes de mandar a Whisper (mejora precisión 30%)
        $upscaled = $audioPath . '.16k.wav';
        exec("ffmpeg -y -loglevel error -i {$audioPath} -ar 16000 -ac 1 {$upscaled}");
        $fileToSend = file_exists($upscaled) ? $upscaled : $audioPath;

        // 2️⃣ Prompt para sesgar Whisper hacia el contexto del restaurante
        $promptHint = 'Conversación telefónica en español sobre comida, pedidos de carne, '
                    . 'milanesa, solomito, posta, muchacho relleno, libras, gramos, '
                    . 'dirección de domicilio, total, confirmar.';

        $resp = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(30)
            ->attach('file', file_get_contents($fileToSend), basename($fileToSend))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model'    => 'whisper-1',
                'language' => 'es',
                'prompt'   => $promptHint,
                'response_format' => 'text',
                'temperature' => 0,  // determinista, menos alucinación
            ]);

        @unlink($upscaled);

        if ($resp->failed()) {
            Log::error('Whisper STT falló: ' . $resp->body());
            return null;
        }

        $texto = trim($resp->body());

        // 3️⃣ Filtro de alucinaciones conocidas de Whisper
        $alucinaciones = [
            '/subt[íi]tulos/i', '/amara/i', '/ver el video/i',
            '/música de fondo/i', '/m[úu]sica\.{3}/i',
            '/subscribe/i', '/like and subscribe/i',
            '/^\s*[\.\,\!\?]+\s*$/',  // solo puntuación
        ];
        foreach ($alucinaciones as $pat) {
            if (preg_match($pat, $texto)) {
                Log::info('🔇 Whisper alucinó, ignorado: ' . $texto);
                return '';  // vacío → tratado como silencio
            }
        }

        // Muy corto (< 3 chars) → probablemente ruido
        if (mb_strlen($texto) < 3) return '';

        return $texto;
    }

    private function llamarLlm(array $historial, ConversacionIvr $conv): ?array
    {
        $resp = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => self::MODELO_LLM,
                'messages' => $this->normalizarHistorialParaLlm($historial),
                'tools'    => $this->herramientasDisponibles(),
                'tool_choice' => 'auto',
                'max_tokens'  => 160,        // suficiente para listar 3-4 productos con precio
                'temperature' => 0.4,
            ]);

        if ($resp->failed()) {
            Log::error('LLM falló: ' . $resp->body());
            return null;
        }

        $j = $resp->json();
        $msg = $j['choices'][0]['message'] ?? null;
        if (!$msg) return null;

        // Si el LLM quiere ejecutar una tool
        if (!empty($msg['tool_calls'])) {
            $toolCall = $msg['tool_calls'][0];
            $nombre = $toolCall['function']['name'];
            $args   = json_decode($toolCall['function']['arguments'] ?? '{}', true);

            $resultado = $this->ejecutarHerramienta($nombre, $args, $conv);

            // Segunda llamada al LLM con el resultado de la tool
            $historial2 = $historial;
            $historial2[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [$toolCall],
            ];
            $historial2[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall['id'],
                'content' => json_encode($resultado),
            ];

            $resp2 = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => self::MODELO_LLM,
                    'messages' => $this->normalizarHistorialParaLlm($historial2),
                    'max_tokens' => 200,
                    'temperature' => 0.6,
                ]);

            $j2 = $resp2->json();
            $contenido = $j2['choices'][0]['message']['content'] ?? 'Listo, ¿algo más?';

            return [
                'content' => $contenido,
                'action'  => $nombre,
                'hangup'  => in_array($nombre, ['colgar', 'transferir_humano'], true),
            ];
        }

        return [
            'content' => $msg['content'] ?? 'Disculpa, no entendí.',
            'action'  => null,
            'hangup'  => false,
        ];
    }

    private function normalizarHistorialParaLlm(array $historial): array
    {
        return array_map(function ($m) {
            $out = ['role' => $m['role']];
            if (isset($m['content'])) $out['content'] = $m['content'];
            if (isset($m['tool_calls'])) $out['tool_calls'] = $m['tool_calls'];
            if (isset($m['tool_call_id'])) $out['tool_call_id'] = $m['tool_call_id'];
            return $out;
        }, $historial);
    }

    private function generarTts(string $texto, string $uniqueId, int $turno): ?string
    {
        $safeUid = preg_replace('/[^a-z0-9]/i', '_', $uniqueId);
        $filename = "respuesta_{$safeUid}_t{$turno}.wav";
        $dirHost  = storage_path('app/public/ivr-audio');

        if (!is_dir($dirHost)) {
            @mkdir($dirHost, 0775, true);
        }

        $destWav = "{$dirHost}/{$filename}";
        $tmpMp3  = sys_get_temp_dir() . "/{$filename}.mp3";

        $elevenKey   = env('ELEVENLABS_API_KEY');
        $elevenVoice = env('ELEVENLABS_VOICE_ID');

        if ($elevenKey && $elevenVoice) {
            Log::info('🎙️ TTS: usando ElevenLabs', ['voice_id' => $elevenVoice, 'len' => mb_strlen($texto)]);
            // 🎙️ ElevenLabs (voz clonada — calidad premium)
            $resp = Http::withHeaders([
                'xi-api-key'   => $elevenKey,
                'Accept'       => 'audio/mpeg',
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post("https://api.elevenlabs.io/v1/text-to-speech/{$elevenVoice}", [
                    'text'      => $texto,
                    'model_id'  => 'eleven_flash_v2_5', // ⚡ ~75ms latencia
                    'voice_settings' => [
                        'stability'        => 0.5,
                        'similarity_boost' => 0.75,
                        'style'            => 0.3,
                        'use_speaker_boost'=> true,
                    ],
                ]);
        } else {
            // Fallback OpenAI si no hay ElevenLabs configurado
            $resp = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(30)
                ->post('https://api.openai.com/v1/audio/speech', [
                    'model' => self::MODELO_TTS,
                    'voice' => self::VOZ_TTS,
                    'input' => $texto,
                    'response_format' => 'mp3',
                    'speed' => 1.1,
                ]);
        }

        if ($resp->failed()) {
            Log::error('TTS falló: ' . $resp->body());
            return null;
        }

        file_put_contents($tmpMp3, $resp->body());
        exec("ffmpeg -y -loglevel error -i {$tmpMp3} -ar 8000 -ac 1 -sample_fmt s16 {$destWav}");
        @unlink($tmpMp3);

        return $destWav;
    }

    // ============================================================
    // HERRAMIENTAS QUE LA IA PUEDE INVOCAR
    // ============================================================
    private function herramientasDisponibles(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_productos',
                    'description' => 'Busca productos del catálogo por nombre o palabra clave. Devuelve lista con id, nombre, precio.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Lo que busca el cliente, ej: "hamburguesa", "pollo asado"'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'agregar_al_carrito',
                    'description' => 'Agrega un producto al carrito. Pasa producto_id (preferido) O nombre del producto (se hace fuzzy match).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'producto_id' => ['type' => 'integer', 'description' => 'ID exacto del producto del último buscar_productos (preferido)'],
                            'nombre'      => ['type' => 'string', 'description' => 'Nombre del producto si no recuerdas el ID (ej: "Milanesa")'],
                            'cantidad'    => ['type' => 'number', 'description' => 'Cantidad a agregar'],
                            'notas'       => ['type' => 'string', 'description' => 'Notas opcionales'],
                        ],
                        'required' => ['cantidad'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ver_carrito',
                    'description' => 'Lee el carrito actual del cliente con total',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'quitar_del_carrito',
                    'description' => 'Quita un item del carrito por su número de línea (1, 2, 3...)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'linea' => ['type' => 'integer', 'description' => 'Número de línea del item (empieza en 1)'],
                        ],
                        'required' => ['linea'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'guardar_direccion',
                    'description' => 'Guarda la dirección de entrega del cliente. Pídela ANTES de confirmar el pedido.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'direccion' => ['type' => 'string', 'description' => 'Dirección completa: calle, número, barrio'],
                        ],
                        'required' => ['direccion'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirmar_pedido',
                    'description' => 'CREA el pedido en el sistema. Solo úsalo cuando el cliente diga "sí confirmar" y haya carrito + dirección.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_estado_pedido',
                    'description' => 'Consulta el estado del último pedido del cliente',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_horarios',
                    'description' => 'Devuelve los horarios de atención',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_sedes',
                    'description' => 'Lista las sedes con dirección',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'transferir_humano',
                    'description' => 'Transfiere a un asesor humano si el cliente lo pide o no puedes ayudar.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'colgar',
                    'description' => 'Cuelga al despedirse el cliente o terminar la conversación.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
        ];
    }

    private function ejecutarHerramienta(string $nombre, array $args, ConversacionIvr $conv): array
    {
        return match($nombre) {
            'buscar_productos'        => $this->toolBuscarProductos($conv, $args['query'] ?? ''),
            'agregar_al_carrito'      => $this->toolAgregarCarrito($conv, $args),
            'ver_carrito'             => $this->toolVerCarrito($conv),
            'quitar_del_carrito'      => $this->toolQuitarCarrito($conv, $args['linea'] ?? 0),
            'guardar_direccion'       => $this->toolGuardarDireccion($conv, $args['direccion'] ?? ''),
            'confirmar_pedido'        => $this->toolConfirmarPedido($conv),
            'consultar_estado_pedido' => $this->toolEstadoPedido($conv),
            'consultar_horarios'      => $this->toolHorarios($conv),
            'consultar_sedes'         => $this->toolSedes($conv),
            'transferir_humano'       => ['ok' => true, 'mensaje' => 'Transfiriendo al asesor'],
            'colgar'                  => ['ok' => true, 'mensaje' => 'Despidiendo al cliente'],
            default                   => ['error' => 'tool desconocida'],
        };
    }

    // ============================================================
    // 🛒 TOOLS DE PEDIDO
    // ============================================================

    private function toolBuscarProductos(ConversacionIvr $conv, string $query): array
    {
        $productos = Producto::where('tenant_id', $conv->tenant_id)
            ->where('activo', true)
            ->where(function($q) use ($query) {
                $q->where('nombre', 'like', "%{$query}%")
                  ->orWhere('descripcion', 'like', "%{$query}%")
                  ->orWhere('palabras_clave', 'like', "%{$query}%");
            })
            ->limit(4)              // máximo 4 para mantener respuestas cortas
            ->get(['id', 'nombre', 'descripcion_corta', 'precio_base', 'unidad']);

        if ($productos->isEmpty()) {
            return ['encontrados' => 0, 'mensaje' => "No encontré productos para '{$query}'"];
        }

        return [
            'encontrados' => $productos->count(),
            'productos' => $productos->map(fn($p) => [
                'id'     => $p->id,
                'nombre' => $p->nombre,
                'precio' => (int) $p->precio_base,
                'unidad' => $p->unidad,
            ])->all(),
        ];
    }

    private function toolAgregarCarrito(ConversacionIvr $conv, array $args): array
    {
        $producto = null;

        // 1. Intento por ID si vino
        if (!empty($args['producto_id'])) {
            $producto = Producto::where('tenant_id', $conv->tenant_id)
                ->where('id', $args['producto_id'])
                ->where('activo', true)
                ->first();
        }

        // 2. Si no vino ID o no se encontró, intento por nombre con fuzzy match
        if (!$producto && !empty($args['nombre'])) {
            $nombre = trim($args['nombre']);
            $producto = Producto::where('tenant_id', $conv->tenant_id)
                ->where('activo', true)
                ->where(function($q) use ($nombre) {
                    // Match exacto preferido
                    $q->where('nombre', $nombre)
                      ->orWhere('nombre', 'like', "%{$nombre}%")
                      ->orWhere('palabras_clave', 'like', "%{$nombre}%");
                })
                ->orderByRaw("CASE WHEN nombre = ? THEN 0 ELSE 1 END", [$nombre])
                ->first();
        }

        if (!$producto) {
            return [
                'error' => 'no_encontrado',
                'mensaje' => 'No encontré ese producto. Pídele al cliente que diga el nombre exacto o usa buscar_productos.',
            ];
        }

        $cantidad = max(0.1, (float)($args['cantidad'] ?? 1));
        $subtotal = $producto->precio_base * $cantidad;

        $carrito = $conv->carrito ?? [];
        $carrito[] = [
            'producto_id'     => $producto->id,
            'nombre'          => $producto->nombre,
            'cantidad'        => $cantidad,
            'unidad'          => $producto->unidad,
            'precio_unitario' => (int) $producto->precio_base,
            'subtotal'        => (int) $subtotal,
            'notas'           => $args['notas'] ?? null,
        ];
        $conv->update(['carrito' => $carrito]);

        $total = collect($carrito)->sum('subtotal');
        return [
            'ok'         => true,
            'agregado'   => "{$cantidad}x {$producto->nombre}",
            'subtotal'   => (int) $subtotal,
            'total_carrito' => (int) $total,
        ];
    }

    private function toolVerCarrito(ConversacionIvr $conv): array
    {
        $carrito = $conv->carrito ?? [];
        if (empty($carrito)) return ['vacio' => true, 'mensaje' => 'El carrito está vacío'];

        $items = collect($carrito)->map(fn($i, $idx) => [
            'linea'     => $idx + 1,
            'cantidad'  => $i['cantidad'],
            'producto'  => $i['nombre'],
            'subtotal'  => $i['subtotal'],
        ])->all();

        return [
            'items' => $items,
            'total' => (int) collect($carrito)->sum('subtotal'),
            'cantidad_items' => count($carrito),
        ];
    }

    private function toolQuitarCarrito(ConversacionIvr $conv, int $linea): array
    {
        $carrito = $conv->carrito ?? [];
        $idx = $linea - 1;
        if (!isset($carrito[$idx])) return ['error' => 'Línea no existe'];

        $quitado = $carrito[$idx]['nombre'];
        array_splice($carrito, $idx, 1);
        $conv->update(['carrito' => $carrito]);

        return ['ok' => true, 'quitado' => $quitado, 'total' => (int) collect($carrito)->sum('subtotal')];
    }

    private function toolGuardarDireccion(ConversacionIvr $conv, string $direccion): array
    {
        if (mb_strlen(trim($direccion)) < 5) {
            return ['error' => 'Dirección muy corta, pide más detalles'];
        }
        $conv->update(['direccion_entrega' => trim($direccion)]);
        return ['ok' => true, 'direccion' => trim($direccion)];
    }

    private function toolConfirmarPedido(ConversacionIvr $conv): array
    {
        $tenant = Tenant::find($conv->tenant_id);

        // Feature flag de seguridad
        if ($tenant && isset($tenant->ivr_puede_crear_pedidos) && !$tenant->ivr_puede_crear_pedidos) {
            return [
                'error' => 'creacion_deshabilitada',
                'mensaje' => 'Te transfiero con un asesor para confirmar el pedido',
            ];
        }

        if (empty($conv->carrito)) {
            return ['error' => 'El carrito está vacío'];
        }
        if (empty($conv->direccion_entrega)) {
            return ['error' => 'Falta la dirección de entrega — pídela'];
        }

        $tel = $this->normalizar($conv->caller_id);
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $conv->tenant_id, 'telefono_normalizado' => $tel],
            ['nombre' => 'Cliente IVR', 'origen' => 'ivr']
        );

        $total = collect($conv->carrito)->sum('subtotal');

        $pedido = Pedido::create([
            'tenant_id'         => $conv->tenant_id,
            'fecha_pedido'      => now(),
            'estado'            => 'recibido',
            'total'             => $total,
            'cliente_nombre'    => $cliente->nombre,
            'telefono'          => $tel,
            'telefono_whatsapp' => $tel,
            'canal'             => 'ivr',
            'notas'             => 'Pedido tomado por IVR conversacional. Dirección: ' . $conv->direccion_entrega,
            'resumen_conversacion' => $this->resumirCarrito($conv->carrito),
        ]);

        // Items
        foreach ($conv->carrito as $item) {
            DetallePedido::create([
                'pedido_id'       => $pedido->id,
                'producto_id'     => $item['producto_id'],
                'producto'        => $item['nombre'],
                'cantidad'        => $item['cantidad'],
                'unidad'          => $item['unidad'] ?? null,
                'precio_unitario' => $item['precio_unitario'],
                'subtotal'        => $item['subtotal'],
            ]);
        }

        $conv->update([
            'estado'           => 'finalizada',
            'pedido_creado_id' => $pedido->id,
        ]);

        Log::info('🛒 Pedido creado por IVR IA', [
            'pedido_id' => $pedido->id,
            'total'     => $total,
            'tenant'    => $tenant?->slug,
        ]);

        // Notificar al operador por WhatsApp si está configurado
        if ($tenant && !empty($tenant->ivr_notificar_whatsapp_operador)) {
            $this->notificarOperador($tenant, $pedido, $conv);
        }

        return [
            'ok'         => true,
            'pedido_id'  => $pedido->id,
            'total'      => (int) $total,
            'minutos'    => 30,
            'mensaje'    => "Pedido #{$pedido->id} creado",
        ];
    }

    private function resumirCarrito(array $carrito): string
    {
        return collect($carrito)
            ->map(fn($i) => "{$i['cantidad']}x {$i['nombre']} (\${$i['subtotal']})")
            ->implode(', ');
    }

    private function notificarOperador(Tenant $tenant, Pedido $pedido, ConversacionIvr $conv): void
    {
        try {
            // TODO: integrar con WhatsappSender existente
            Log::info('📲 IVR notif operador (TODO integrar WA)', [
                'tel_operador' => $tenant->ivr_notificar_whatsapp_operador,
                'pedido_id'    => $pedido->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notif operador falló: ' . $e->getMessage());
        }
    }

    private function toolEstadoPedido(ConversacionIvr $conv): array
    {
        $tel = $this->normalizar($conv->caller_id);
        $pedido = Pedido::whereHas('cliente', fn($q) => $q->where('telefono_normalizado', $tel))
            ->whereNotIn('estado', ['cancelado'])
            ->latest()
            ->first();

        if (!$pedido) return ['encontrado' => false];

        return [
            'encontrado'    => true,
            'pedido_id'     => $pedido->id,
            'estado'        => $pedido->estado,
            'total'         => $pedido->total,
            'minutos'       => $this->estimarMinutos($pedido),
            'domiciliario'  => $pedido->domiciliario?->nombre,
        ];
    }

    private function toolHorarios(ConversacionIvr $conv): array
    {
        // TODO: leer de configuración del tenant
        return [
            'horarios' => 'Lunes a sábado de 9 AM a 9 PM. Domingos cerrado.',
        ];
    }

    private function toolSedes(ConversacionIvr $conv): array
    {
        $sedes = Sede::where('tenant_id', $conv->tenant_id)
            ->where('activa', true)
            ->get(['nombre', 'direccion']);

        return ['sedes' => $sedes->map(fn($s) => "{$s->nombre} en {$s->direccion}")->all()];
    }

    private function construirPromptSistema(?Tenant $tenant, string $callerId): string
    {
        $nombreNeg = $tenant?->nombre ?? 'el comercio';
        return <<<PROMPT
Eres la asistente virtual telefónica de {$nombreNeg}. Atiendes llamadas en español colombiano.

REGLAS CRÍTICAS:
- El cliente YA OYÓ el saludo automático "Hola, soy la asistente virtual". NUNCA vuelvas a saludar.
- Respuestas ULTRA cortas: una sola frase, idealmente 6-12 palabras.
- Directa, sin "con todo gusto", "permíteme", "es un placer", "claro que sí".
- Si necesitas datos (pedido, sede, horarios) USA herramientas, no inventes.
- "Asesor", "persona", "humano" → llama transferir_humano y di "Te paso con un asesor".
- "Gracias", "chao", "adiós", "listo" → llama colgar y di "¡Hasta luego!".
- NO digas "soy una IA" ni "permíteme verificar". Responde directo.

EJEMPLOS:
Cliente: "¿A qué hora abren?" → consultar_horarios → "Lunes a sábado, 9 AM a 9 PM."
Cliente: "Mi pedido" → consultar_estado_pedido → "Va en camino, llega en 8 minutos."

🛒 TOMAR PEDIDO (flujo importante):
Cliente: "Quiero pedir hamburguesa"
Tú: → buscar_productos("hamburguesa") → "Tengo Doble en 18 mil y Sencilla en 12 mil. ¿Cuál y cuántas?"
Cliente: "Dos dobles"
Tú: → agregar_al_carrito(id=42, cantidad=2) → "Dos dobles agregadas. ¿Algo más?"
Cliente: "Una papa"
Tú: → buscar_productos("papa") → agregar_al_carrito(...) → "Listo. ¿Algo más?"
Cliente: "Nada más"
Tú: → ver_carrito → "Total 42 mil. ¿A qué dirección?"
Cliente: "Carrera 50 con 12 número 30"
Tú: → guardar_direccion("Carrera 50 # 12-30") → "Confirmo 42 mil a Carrera 50 12-30. ¿Procedo?"
Cliente: "Sí"
Tú: → confirmar_pedido → "Listo, tu pedido número 4521 sale en 30 minutos."

REGLAS DE PEDIDO (CRÍTICAS):
- SIEMPRE buscar_productos antes de agregar.
- 🚨 NUNCA digas "tengo varios" SIN LISTAR. Lista 3-4 con nombre + precio.
  Mal: "Tengo varios cortes, ¿cuál?"
  Bien: "Tengo Solomito en 54 mil, Milanesa en 27 mil, Muchacho Relleno en 29 mil. ¿Cuál?"
- Al llamar agregar_al_carrito SIEMPRE pasa el producto_id del último buscar_productos.
  Si no recuerdas el ID exacto, pasa el "nombre" exactamente como apareció en el search.
- Confirma cada item (cantidad + producto)
- Pide dirección ANTES de confirmar_pedido
- Después de confirmar_pedido → despídete y llama colgar
- Si buscar devuelve 0 → "No tengo eso disponible, ¿algo más?"

🚨 ANTI-ALUCINACIÓN WHISPER:
A veces la transcripción del cliente es absurda: "Subtítulos por Amara.org", "Gracias por ver el video", "Música de fondo", frases sueltas sin contexto. Eso significa que NO HABLÓ — solo había silencio/ruido.
En ese caso responde SOLO: "Disculpa, no te escuché bien. ¿Puedes repetir?"
NO sigas la conversación con esa frase absurda.

Cliente llama desde: {$callerId}
PROMPT;
    }

    private function normalizar(string $tel): string
    {
        $tel = preg_replace('/\D/', '', $tel);
        if (strlen($tel) === 10 && $tel[0] === '3') return '+57' . $tel;
        if (strlen($tel) === 12 && substr($tel, 0, 2) === '57') return '+' . $tel;
        return $tel[0] === '+' ? $tel : '+' . $tel;
    }

    private function estimarMinutos(Pedido $p): ?int
    {
        return match($p->estado) {
            'recibido','confirmado' => 30,
            'en_preparacion'        => 20,
            'listo'                 => 10,
            'en_camino'             => 8,
            default                 => null,
        };
    }
}
