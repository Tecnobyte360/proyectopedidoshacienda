<?php

namespace App\Livewire\Chat;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\ConversacionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public ?int $conversacionActivaId = null;

    public string $nuevoMensaje = '';
    public string $busqueda     = '';
    public string $filtroEstado = 'todas';   // todas | activa | humano | bot

    public function seleccionar(int $id): void
    {
        $this->conversacionActivaId = $id;
        $this->nuevoMensaje = '';
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

        // Decodificar data URL
        if (!preg_match('/^data:(audio\/[a-z0-9.+-]+);base64,(.+)$/i', $dataUrl, $m)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Formato de audio no reconocido.']);
            return;
        }

        $mime  = strtolower($m[1]);
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

        $mediaUrl = rtrim(config('app.url'), '/') . Storage::url($filename);

        $sent = $this->enviarAudioAWhatsapp($conv->telefono_normalizado, $mediaUrl, $conv->connection_id);

        if (!$sent) {
            $this->dispatch('notify', ['type' => 'error', 'message' => '⚠️ No se pudo enviar el audio a WhatsApp.']);
            return;
        }

        // Persistir como mensaje del operador con tipo audio y URL
        try {
            app(ConversacionService::class)->agregarMensaje(
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
        } catch (\Throwable $e) {
            Log::warning('No se persistió audio manual: ' . $e->getMessage());
        }

        $this->dispatch('mensaje-enviado');
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Audio enviado']);
    }

    /**
     * Envía un audio a WhatsApp vía TecnoByteApp usando mediaUrl.
     */
    private function enviarAudioAWhatsapp(string $telefono, string $mediaUrl, $connectionId = null): bool
    {
        try {
            $token = $this->obtenerTokenWhatsapp();
            if (!$token) {
                Log::error('Token WhatsApp no disponible para audio manual');
                return false;
            }

            $payload = [
                'number'   => $telefono,
                'mediaUrl' => $mediaUrl,
                'mediaType' => 'audio',
                'body'     => '',
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
                ->timeout(30)
                ->post($endpointSend, $payload);

            if ($response->successful()) return true;

            if ($response->status() === 401) {
                Cache::forget($resolver->tokenCacheKey());
                $newToken = $this->obtenerTokenWhatsapp();
                if ($newToken) {
                    $retry = Http::withoutVerifying()
                        ->withToken($newToken)
                        ->timeout(30)
                        ->post($endpointSend, $payload);
                    return $retry->successful();
                }
            }

            Log::warning('Envío audio WhatsApp falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando audio WhatsApp: ' . $e->getMessage());
            return false;
        }
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

        // Enviar a WhatsApp DIRECTO (sin pasar por controller)
        $sent = $this->enviarAWhatsapp($conv->telefono_normalizado, $texto, $conv->connection_id);

        if (!$sent) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⚠️ No se pudo enviar el mensaje a WhatsApp. Revisa el token.',
            ]);
            return;
        }

        // Persistir como mensaje del operador
        try {
            app(ConversacionService::class)->agregarMensaje(
                $conv,
                MensajeWhatsapp::ROL_ASSISTANT,
                $texto,
                ['meta' => ['enviado_por_humano' => true, 'usuario_id' => auth()->id()]]
            );
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
    private function enviarAWhatsapp(string $telefono, string $mensaje, $connectionId = null): bool
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
                return false;
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
                return true;
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
                    return $retry->successful();
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

            return false;
        } catch (\Throwable $e) {
            Log::error('Excepción enviando WA manual: ' . $e->getMessage());
            return false;
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
            ->when($this->filtroEstado === 'activa', fn ($q) => $q->where('estado', 'activa'))
            ->when($this->filtroEstado === 'humano', fn ($q) => $q->where('atendida_por_humano', true))
            ->when($this->filtroEstado === 'bot',    fn ($q) => $q->where('atendida_por_humano', false))
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
