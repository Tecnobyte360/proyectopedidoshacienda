<?php

namespace App\Livewire\Chat;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\ConversacionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public ?int $conversacionActivaId = null;

    public string $nuevoMensaje = '';
    public string $busqueda     = '';
    public string $filtroEstado = 'todas';   // todas | activa | humano | bot | internos
    public bool   $mostrarInternas = false;  // si es false, ocultas conversaciones internas

    // Nueva conversación
    public bool   $nuevoChatModal   = false;
    public string $nuevoChatTel     = '';
    public string $nuevoChatNombre  = '';
    public string $nuevoChatMensaje = '';

    public function seleccionar(int $id): void
    {
        $this->conversacionActivaId = $id;
        $this->nuevoMensaje = '';

        // Marcar como leída
        ConversacionWhatsapp::where('id', $id)->update([
            'no_leidos'       => 0,
            'ultima_vista_at' => now(),
        ]);

        $this->dispatch('chat-cambiado', conversacionId: $id);
    }

    #[On('refrescar-chat')]
    public function refrescar(): void
    {
        // Solo dispara render
    }

    public function tomarControl(): void
    {
        if (!$this->conversacionActivaId) return;

        ConversacionWhatsapp::find($this->conversacionActivaId)
            ?->update(['atendida_por_humano' => true]);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✋ Tomaste el control de la conversación. El bot ya no responde.',
        ]);
    }

    public function devolverAlBot(): void
    {
        if (!$this->conversacionActivaId) return;

        ConversacionWhatsapp::find($this->conversacionActivaId)
            ?->update(['atendida_por_humano' => false]);

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => '🤖 El bot retoma la conversación.',
        ]);
    }

    /**
     * Recibe audio grabado en el navegador (data URL base64), lo guarda en
     * storage público, y lo envía al cliente por WhatsApp con mediaUrl.
     */
    public function enviarAudio(string $dataUrl): void
    {
        if (!$this->conversacionActivaId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        // Decodificar data URL — aceptamos parámetros del mime (ej. "audio/webm;codecs=opus")
        if (!preg_match('/^data:(audio\/[^;,]+(?:;[^,]*)?);base64,(.+)$/i', $dataUrl, $m)) {
            Log::warning('Audio data URL no reconocida', ['preview' => substr($dataUrl, 0, 80)]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de audio no reconocido.']);
            return;
        }

        $mime  = strtolower(explode(';', $m[1])[0]);   // solo el tipo base, sin codecs
        $bytes = base64_decode($m[2], true);

        if ($bytes === false || strlen($bytes) < 100) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Audio inválido o vacío.']);
            return;
        }

        if (strlen($bytes) > 16 * 1024 * 1024) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Audio demasiado grande (máx 16 MB).']);
            return;
        }

        $ext = match (true) {
            str_contains($mime, 'ogg')  => 'ogg',
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'mpeg') => 'mp3',
            str_contains($mime, 'mp4')  => 'm4a',
            str_contains($mime, 'wav')  => 'wav',
            default                     => 'webm',
        };

        $filename = 'audios-out/audio_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        Storage::disk('public')->put($filename, $bytes);

        // URL pública para nuestro propio chat web (para reproducir en el Chat en vivo).
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($filename);

        // Convertir a .ogg/opus — WhatsApp solo reproduce nota de voz en ese formato.
        // Si no está ffmpeg o la conversión falla, enviamos el archivo tal cual.
        [$bytesEnvio, $extEnvio] = $this->convertirAOggOpus($bytes, $ext);

        $nombreEnvio = 'voice_' . uniqid() . '.' . $extEnvio;
        $connectionId = $this->resolverConnectionId($conv->connection_id);
        if (!$connectionId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada.']);
            return;
        }
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);
        $messageId = $this->enviarAudioAWhatsapp($conv->telefono_normalizado, $bytesEnvio, $nombreEnvio, $connectionId);

        if (!$messageId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar el audio a WhatsApp.']);
            return;
        }

        // Persistir como mensaje del operador con tipo audio y URL
        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                '🎤 Nota de voz',
                [
                    'tipo' => 'audio',
                    'meta' => [
                        'enviado_por_humano' => true,
                        'usuario_id'         => auth()->id(),
                        'media_url'          => $mediaUrl,
                        'mime'               => $mime,
                        'bytes'              => strlen($bytes),
                    ],
                ]
            );
            // Marcar como enviado + guardar ID externo para que los ticks actualicen
            $mensaje->update([
                'ack' => MensajeWhatsapp::ACK_SENT,
                'mensaje_externo_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió audio manual: ' . $e->getMessage());
        }

        $this->dispatch('mensaje-enviado');
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Audio enviado']);
    }

    /**
     * Convierte el audio grabado en el navegador a MP3 mono 44.1 kHz 64kbps.
     * MP3 se reproduce correctamente en WhatsApp móvil y desktop (como audio
     * adjunto, no voice note, pero reproducible 100% del tiempo).
     * El formato OGG/Opus como voice note causa "audio no disponible" en
     * WhatsApp móvil cuando no está generado con parámetros exactos.
     *
     * @return array{0: string, 1: string} [bytesConvertidos, extension]
     */
    private function convertirAOggOpus(string $bytes, string $extOriginal): array
    {
        $tmpIn  = tempnam(sys_get_temp_dir(), 'wa_in_') . '.' . $extOriginal;
        $tmpOut = tempnam(sys_get_temp_dir(), 'wa_out_') . '.mp3';

        try {
            file_put_contents($tmpIn, $bytes);

            $process = new Process([
                'ffmpeg', '-y',
                '-i', $tmpIn,
                '-vn',
                '-c:a', 'libmp3lame',
                '-b:a', '64k',
                '-ar', '44100',
                '-ac', '1',
                $tmpOut,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful() || !file_exists($tmpOut) || filesize($tmpOut) < 100) {
                Log::warning('🎤 ffmpeg falló al convertir a mp3, enviando original', [
                    'stderr' => substr($process->getErrorOutput(), 0, 500),
                ]);
                return [$bytes, $extOriginal];
            }

            $converted = file_get_contents($tmpOut);
            return [$converted, 'mp3'];
        } catch (\Throwable $e) {
            Log::warning('🎤 Excepción al convertir audio: ' . $e->getMessage());
            return [$bytes, $extOriginal];
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }

    /**
     * Envía un audio a WhatsApp vía TecnoByteApp.
     * TecnoByteApp espera el archivo como multipart/form-data en el campo `medias`
     * (no acepta mediaUrl). El mimetype del archivo hace que se envíe como voice note.
     */
    private function enviarAudioAWhatsapp(string $telefono, string $bytes, string $filename, $connectionId = null): ?string
    {
        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('Token WhatsApp no disponible para audio manual');
                return null;
            }

            Log::info('🎤 ENVIANDO AUDIO WHATSAPP', [
                'phone'    => $telefono,
                'filename' => $filename,
                'bytes'    => strlen($bytes),
            ]);

            $resolver = app(\App\Services\WhatsappResolverService::class);
            $cred = $resolver->credenciales();
            $endpointSend = rtrim($cred['api_base_url'], '/') . '/api/messages/send';

            $makeRequest = function (string $useToken) use ($endpointSend, $telefono, $bytes, $filename, $connectionId) {
                $req = Http::withoutVerifying()
                    ->withToken($useToken)
                    ->timeout(60)
                    ->asMultipart()
                    ->attach('medias', $bytes, $filename);

                $form = [
                    ['name' => 'number', 'contents' => $telefono],
                ];
                if ($connectionId) {
                    $form[] = ['name' => 'whatsappId', 'contents' => (string) $connectionId];
                }

                // Laravel's Http::attach+post con array: usa la API más simple:
                return Http::withoutVerifying()
                    ->withToken($useToken)
                    ->timeout(60)
                    ->attach('medias', $bytes, $filename)
                    ->post($endpointSend, $connectionId ? [
                        'number'     => $telefono,
                        'whatsappId' => (int) $connectionId,
                    ] : [
                        'number' => $telefono,
                    ]);
            };

            $response = $makeRequest($token);

            if ($response->successful()) {
                return $response->json('messageId') ?: 'sent-' . uniqid();
            }

            if ($response->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $retry = $makeRequest($newToken);
                    if ($retry->successful()) {
                        return $retry->json('messageId') ?: 'sent-' . uniqid();
                    }
                }
            }

            Log::warning('Envío audio WhatsApp falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando audio WhatsApp: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Envía una imagen desde el chat al cliente por WhatsApp.
     * Recibe la imagen como data URL base64 (enviada desde JS) para
     * evitar el endpoint /livewire/upload-file (que tira 401 en producción).
     */
    public function enviarImagen(string $dataUrl = '', string $caption = ''): void
    {
        if (!$this->conversacionActivaId) return;
        if ($dataUrl === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecciona una imagen primero.']);
            return;
        }

        if (!preg_match('/^data:(image\/[a-z0-9.+-]+);base64,(.+)$/i', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de imagen no reconocido.']);
            return;
        }

        $mime  = strtolower($m[1]);
        $bytes = base64_decode($m[2], true);

        if ($bytes === false || strlen($bytes) < 100) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Imagen inválida o vacía.']);
            return;
        }

        if (strlen($bytes) > 15 * 1024 * 1024) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Imagen demasiado grande (máx 15 MB).']);
            return;
        }

        $extOrig = match (true) {
            str_contains($mime, 'png')  => 'png',
            str_contains($mime, 'gif')  => 'gif',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'bmp')  => 'bmp',
            default                     => 'jpg',
        };

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        $caption = trim($caption);

        // Guardar copia pública para mostrar en el chat web
        $filename = 'imagenes-out/img_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $extOrig;
        Storage::disk('public')->put($filename, $bytes);
        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($filename);

        $nombreEnvio = 'image_' . uniqid() . '.' . $extOrig;
        $connectionId = $this->resolverConnectionId($conv->connection_id);
        if (!$connectionId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada.']);
            return;
        }
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);
        $messageId = $this->enviarImagenAWhatsapp($conv->telefono_normalizado, $bytes, $nombreEnvio, $caption, $connectionId);

        if (!$messageId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar la imagen a WhatsApp.']);
            return;
        }

        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $caption !== '' ? $caption : '🖼️ Imagen',
                [
                    'tipo' => 'image',
                    'meta' => [
                        'enviado_por_humano' => true,
                        'usuario_id'         => auth()->id(),
                        'media_url'          => $mediaUrl,
                        'caption'            => $caption,
                    ],
                ]
            );
            $mensaje->update([
                'ack' => MensajeWhatsapp::ACK_SENT,
                'mensaje_externo_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió imagen manual: ' . $e->getMessage());
        }

        $this->dispatch('mensaje-enviado');
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Imagen enviada']);
    }

    /**
     * Envía una imagen a WhatsApp vía TecnoByteApp (multipart 'medias').
     */
    private function enviarImagenAWhatsapp(string $telefono, string $bytes, string $filename, string $caption, $connectionId = null): ?string
    {
        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('Token WhatsApp no disponible para imagen manual');
                return null;
            }

            $resolver = app(\App\Services\WhatsappResolverService::class);
            $cred = $resolver->credenciales();
            $endpointSend = rtrim($cred['api_base_url'], '/') . '/api/messages/send';

            Log::info('🖼️ ENVIANDO IMAGEN WHATSAPP', [
                'phone'    => $telefono,
                'filename' => $filename,
                'bytes'    => strlen($bytes),
                'caption'  => $caption,
            ]);

            $payload = ['number' => $telefono];
            if ($caption !== '')       $payload['body']       = $caption;
            if ($connectionId)         $payload['whatsappId'] = (int) $connectionId;

            $makeRequest = function (string $useToken) use ($endpointSend, $bytes, $filename, $payload) {
                return Http::withoutVerifying()
                    ->withToken($useToken)
                    ->timeout(60)
                    ->attach('medias', $bytes, $filename)
                    ->post($endpointSend, $payload);
            };

            $response = $makeRequest($token);
            if ($response->successful()) {
                return $response->json('messageId') ?: 'sent-' . uniqid();
            }

            if ($response->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $retry = $makeRequest($newToken);
                    if ($retry->successful()) {
                        return $retry->json('messageId') ?: 'sent-' . uniqid();
                    }
                }
            }

            Log::warning('Envío imagen WhatsApp falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando imagen WhatsApp: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resuelve la connection_id a usar para enviar un mensaje.
     * Prioridad:
     *   1. La que ya tiene la conversación.
     *   2. La primera connection_id registrada para el tenant actual.
     * Si no hay ninguna, devuelve null y TecnoByteApp fallará con mensaje claro
     * (mejor que usar la default de OTRO tenant).
     */
    private function resolverConnectionId($connectionIdActual = null): ?int
    {
        if ($connectionIdActual) return (int) $connectionIdActual;

        $ids = app(\App\Services\WhatsappResolverService::class)->connectionIdsDelTenant();
        return !empty($ids) ? (int) $ids[0] : null;
    }

    public function abrirNuevoChat(): void
    {
        $this->nuevoChatModal   = true;
        $this->nuevoChatTel     = '';
        $this->nuevoChatNombre  = '';
        $this->nuevoChatMensaje = '';
    }

    public function cerrarNuevoChat(): void
    {
        $this->nuevoChatModal = false;
    }

    /**
     * Crea (o abre si ya existe) una conversación para un número nuevo y envía
     * el primer mensaje. Si el número nunca ha escrito, lo creamos desde cero.
     */
    public function crearNuevoChat(): void
    {
        $telefono = preg_replace('/\D+/', '', (string) $this->nuevoChatTel);
        $texto    = trim($this->nuevoChatMensaje);

        if ($telefono === '' || strlen($telefono) < 8) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ingresa un teléfono válido (ej. 573001234567).']);
            return;
        }
        if ($texto === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Escribe el primer mensaje.']);
            return;
        }

        // Resolver connection_id del tenant actual ANTES de crear la conversación
        $connectionId = $this->resolverConnectionId();
        if (!$connectionId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada.']);
            return;
        }

        // Resolver/crear cliente + conversación (con connection_id del tenant)
        $cliente = \App\Models\Cliente::encontrarOCrearPorTelefono($telefono, trim($this->nuevoChatNombre) ?: 'Cliente');
        $conv = app(ConversacionService::class)->obtenerOCrearActiva($telefono, $cliente->id, null, $connectionId);

        // Si la conversación ya existía pero sin connection_id, la actualizamos
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);

        $messageId = $this->enviarAWhatsapp($telefono, $texto, $connectionId);

        if (!$messageId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar el mensaje. Verifica que el número sí use WhatsApp.']);
            return;
        }

        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $texto,
                ['meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id()]]
            );
            $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT, 'mensaje_externo_id' => $messageId]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió mensaje manual (nuevo chat): ' . $e->getMessage());
        }

        $this->nuevoChatModal        = false;
        $this->conversacionActivaId  = $conv->id;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Chat iniciado']);
        $this->dispatch('chat-cambiado', conversacionId: $conv->id);
    }

    public function enviar(): void
    {
        $texto = trim($this->nuevoMensaje);
        if ($texto === '' || !$this->conversacionActivaId) return;

        $conv = ConversacionWhatsapp::find($this->conversacionActivaId);
        if (!$conv) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Conversación no encontrada.']);
            return;
        }

        // ⚠️ NO se auto-activa modo humano. El bot SIGUE respondiendo al cliente.
        // Si quieres silenciar al bot, usa el botón "Tomar control" explícitamente.

        // Si la conversación es de un WIDGET WEB (no WhatsApp), no usamos TecnoByteApp.
        // El widget pollea los mensajes del operador y los muestra.
        if ($conv->canal === 'widget') {
            try {
                $mensaje = app(ConversacionService::class)->agregarMensaje(
                    $conv,
                    MensajeWhatsapp::ROL_ASSISTANT,
                    $texto,
                    ['meta' => [
                        'enviado_por_humano'  => true,
                        'usuario_id'          => auth()->id(),
                        'canal_widget'        => true,
                    ]]
                );
                $mensaje->update(['ack' => MensajeWhatsapp::ACK_SENT]);
            } catch (\Throwable $e) {
                Log::warning('No se persistió mensaje widget: ' . $e->getMessage());
            }

            $this->nuevoMensaje = '';
            $this->dispatch('mensaje-enviado');
            $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Enviado al widget web']);
            return;
        }

        // Resolver connection_id del tenant actual (evita enviar desde el número de otro tenant)
        $connectionId = $this->resolverConnectionId($conv->connection_id);
        if (!$connectionId) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⚠️ Este tenant no tiene conexión WhatsApp configurada. Configúrala primero.',
            ]);
            return;
        }
        if (!$conv->connection_id) $conv->update(['connection_id' => $connectionId]);

        // Enviar a WhatsApp DIRECTO (sin pasar por controller)
        $messageId = $this->enviarAWhatsapp($conv->telefono_normalizado, $texto, $connectionId);

        if (!$messageId) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⚠️ No se pudo enviar el mensaje a WhatsApp. Revisa el token.',
            ]);
            return;
        }

        // Persistir como mensaje del operador con ack=sent y ID externo
        try {
            $mensaje = app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $texto,
                ['meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id()]]
            );
            $mensaje->update([
                'ack' => MensajeWhatsapp::ACK_SENT,
                'mensaje_externo_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se persistió mensaje manual: ' . $e->getMessage());
        }

        $this->nuevoMensaje = '';
        $this->dispatch('mensaje-enviado');

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✓ Mensaje enviado',
        ]);
    }

    /**
     * Envía un mensaje a WhatsApp directamente vía TecnoByteApp.
     */
    private function enviarAWhatsapp(string $telefono, string $mensaje, $connectionId = null): ?string
    {
        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('Token WhatsApp no disponible para chat manual');
                try {
                    app(\App\Services\BotAlertaService::class)->registrar(
                        \App\Models\BotAlerta::TIPO_WHATSAPP_TOKEN,
                        '📱 Token de WhatsApp no disponible (chat manual)',
                        'No se pudo obtener el token de WhatsApp al intentar enviar un mensaje manual desde el chat interno. Verifica WHATSAPP_API_EMAIL / WHATSAPP_API_PASSWORD en .env.',
                        \App\Models\BotAlerta::SEV_CRITICA,
                        null,
                        ['telefono' => $telefono]
                    );
                } catch (\Throwable $e) { /* no bloquear */ }
                return null;
            }

            $payload = [
                'number' => $telefono,
                'body'   => $mensaje,
            ];
            if ($connectionId) {
                $payload['whatsappId']   = (int) $connectionId;
                $payload['connectionId'] = (int) $connectionId;
            }

            $resolver = app(\App\Services\WhatsappResolverService::class);
            $cred = $resolver->credenciales();
            $endpointSend = rtrim($cred['api_base_url'], '/') . '/api/messages/send';

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post($endpointSend, $payload);

            if ($response->successful()) {
                return $response->json('messageId') ?: 'sent-' . uniqid();
            }

            // Reintento con refresh de token
            if ($response->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $retry = Http::withoutVerifying()
                        ->withToken($newToken)
                        ->timeout(20)
                        ->post($endpointSend, $payload);
                    if ($retry->successful()) {
                        return $retry->json('messageId') ?: 'sent-' . uniqid();
                    }
                }
            }

            Log::warning('Envío WhatsApp manual falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            try {
                app(\App\Services\BotAlertaService::class)->registrar(
                    \App\Models\BotAlerta::TIPO_WHATSAPP_ENVIO,
                    '📤 Falló envío manual de WhatsApp',
                    'Un mensaje enviado desde el chat interno no pudo entregarse. Status ' . $response->status() . '.',
                    \App\Models\BotAlerta::SEV_WARNING,
                    $response->status(),
                    [
                        'telefono' => $telefono,
                        'body'     => mb_substr((string) $response->body(), 0, 500),
                    ]
                );
            } catch (\Throwable $e) { /* no bloquear */ }

            return null;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando WA manual: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el token cacheado o hace login a TecnoByteApp.
     */
    private function obtenerTokenWhatsapp(): ?string
    {
        $resolver = app(\App\Services\WhatsappResolverService::class);
        $cred = $resolver->credenciales();
        $cacheKey = $resolver->tokenCacheKey();

        if (empty($cred['email']) || empty($cred['password'])) {
            return null;
        }

        return Cache::remember($cacheKey, 1200, function () use ($cred) {
            try {
                $endpoint = rtrim($cred['api_base_url'], '/') . '/auth/login';
                $resp = Http::withoutVerifying()
                    ->timeout(15)
                    ->post($endpoint, [
                        'email'    => $cred['email'],
                        'password' => $cred['password'],
                    ]);

                if ($resp->successful()) {
                    return $resp->json('token');
                }
                return null;
            } catch (\Throwable $e) {
                Log::error('Login WA falló: ' . $e->getMessage());
                return null;
            }
        });
    }

    public function render()
    {
        $conversaciones = ConversacionWhatsapp::query()
            ->with('cliente')
            ->where('estado', '!=', 'archivada')
            ->when($this->busqueda, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('telefono_normalizado', 'like', "%{$this->busqueda}%")
                       ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$this->busqueda}%"));
                });
            })
            ->when($this->filtroEstado === 'activa',   fn ($q) => $q->where('estado', 'activa'))
            ->when($this->filtroEstado === 'humano',   fn ($q) => $q->where('atendida_por_humano', true))
            ->when($this->filtroEstado === 'bot',      fn ($q) => $q->where('atendida_por_humano', false))
            ->when($this->filtroEstado === 'internos', fn ($q) => $q->where('es_interna', true))
            ->when($this->filtroEstado !== 'internos' && !$this->mostrarInternas,
                   fn ($q) => $q->where(fn ($qq) => $qq->where('es_interna', false)->orWhereNull('es_interna')))
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(60)
            ->get();

        $conversacionActiva = $this->conversacionActivaId
            ? ConversacionWhatsapp::with(['cliente', 'mensajes', 'pedido'])->find($this->conversacionActivaId)
            : null;

        return view('livewire.chat.index', compact('conversaciones', 'conversacionActiva'))
            ->layout('layouts.app');
    }
}
