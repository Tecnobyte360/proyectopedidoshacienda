<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    const ESTADO_NUEVO                = 'nuevo';
    const ESTADO_EN_PREPARACION       = 'en_preparacion';
    const ESTADO_REPARTIDOR_EN_CAMINO = 'repartidor_en_camino';
    const ESTADO_RECOGIDO             = 'recogido';
    const ESTADO_ENTREGADO            = 'entregado';
    const ESTADO_CANCELADO            = 'cancelado';

    protected $fillable = [
        'sede_id',
        'empresa_id',
        'fecha_pedido',
        'hora_entrega',
        'estado',
        'total',
        'notas',
        'cliente_nombre',
        'telefono_whatsapp',
        'telefono_contacto',
        'telefono',
        'canal',
        'connection_id',
        'whatsapp_id',
        'conversacion_completa',
        'resumen_conversacion',
        'codigo_seguimiento',
        'fecha_estado',
        'fecha_entregado',
        'fecha_cancelado',
        'observacion_estado',
    ];

    protected $casts = [
        'fecha_pedido'    => 'datetime',
        'fecha_estado'    => 'datetime',
        'fecha_entregado' => 'datetime',
        'fecha_cancelado' => 'datetime',
        'total'           => 'decimal:2',
        'empresa_id'      => 'integer',
        'connection_id'   => 'integer',
        'whatsapp_id'     => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($pedido) {
            if (empty($pedido->codigo_seguimiento)) {
                $pedido->codigo_seguimiento = (string) Str::uuid();
            }
            if (empty($pedido->estado)) {
                $pedido->estado = self::ESTADO_NUEVO;
            }
            if (empty($pedido->fecha_estado)) {
                $pedido->fecha_estado = now();
            }
        });

        static::created(function ($pedido) {
            $pedido->registrarHistorial(
                estadoNuevo: $pedido->estado,
                estadoAnterior: null,
                titulo: 'Pedido recibido',
                descripcion: 'Tu pedido fue recibido correctamente y está pendiente de gestión.'
            );
        });
    }

    /*
    |==========================================================================
    | RELACIONES
    |==========================================================================
    */

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function historialEstados()
    {
        return $this->hasMany(HistorialEstadoPedido::class)
            ->orderBy('fecha_evento', 'asc');
    }

    /*
    |==========================================================================
    | CAMBIO DE ESTADO
    |==========================================================================
    */

    public function cambiarEstado(
        string $nuevoEstado,
        ?string $descripcion = null,
        ?string $titulo = null,
        ?string $usuario = null,
        ?int $usuarioId = null
    ): void {
        $estadoAnterior = $this->estado;

        if ($estadoAnterior === $nuevoEstado) {
            return;
        }

        $this->estado            = $nuevoEstado;
        $this->fecha_estado      = now();
        $this->observacion_estado = $descripcion;

        if ($nuevoEstado === self::ESTADO_ENTREGADO) {
            $this->fecha_entregado = now();
        }
        if ($nuevoEstado === self::ESTADO_CANCELADO) {
            $this->fecha_cancelado = now();
        }

        $this->save();

        $this->registrarHistorial(
            estadoNuevo: $nuevoEstado,
            estadoAnterior: $estadoAnterior,
            titulo: $titulo,
            descripcion: $descripcion,
            usuario: $usuario,
            usuarioId: $usuarioId
        );

        $this->notificarClienteCambioEstado();
    }

    public function registrarHistorial(
        string $estadoNuevo,
        ?string $estadoAnterior = null,
        ?string $titulo = null,
        ?string $descripcion = null,
        ?string $usuario = null,
        ?int $usuarioId = null
    ): void {
        $this->historialEstados()->create([
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'    => $estadoNuevo,
            'titulo'          => $titulo ?? $this->tituloPorEstado($estadoNuevo),
            'descripcion'     => $descripcion,
            'usuario'         => $usuario,
            'usuario_id'      => $usuarioId,
            'fecha_evento'    => now(),
        ]);
    }

    /*
    |==========================================================================
    | NOTIFICACIÓN WHATSAPP
    |==========================================================================
    */

    public function notificarClienteCambioEstado(): void
    {
        $telefono = $this->telefono_whatsapp ?: $this->telefono_contacto ?: $this->telefono;

        if (!$telefono) {
            Log::warning('⚠️ Pedido sin teléfono para notificar', [
                'pedido_id' => $this->id,
                'estado'    => $this->estado,
            ]);
            return;
        }

        $connectionId = $this->connection_id;
        $whatsappId   = $this->whatsapp_id ?: $this->connection_id;

        if (!$connectionId) {
            Log::warning('⚠️ Pedido sin connection_id para notificar', [
                'pedido_id'  => $this->id,
                'empresa_id' => $this->empresa_id,
                'estado'     => $this->estado,
            ]);
            return;
        }

        $mensaje = match ($this->estado) {
            self::ESTADO_EN_PREPARACION =>
                "👨‍🍳 ¡Hola {$this->cliente_nombre}!\n\nTu pedido #{$this->id} ya está en preparación 🥩🔥",

            self::ESTADO_REPARTIDOR_EN_CAMINO =>
                "🛵 ¡Hola {$this->cliente_nombre}!\n\nTu pedido #{$this->id} ya va en camino 🚀",

            self::ESTADO_ENTREGADO =>
                "✅ ¡Hola {$this->cliente_nombre}!\n\nTu pedido #{$this->id} fue entregado.\nGracias por tu compra 🙌",

            self::ESTADO_CANCELADO =>
                "❌ Hola {$this->cliente_nombre}\n\nTu pedido #{$this->id} fue cancelado.\nSi tienes dudas escríbenos.",

            default =>
                "📦 Hola {$this->cliente_nombre}\n\nTu pedido #{$this->id} ha sido actualizado.",
        };

        $mensaje .= "\n\n🔎 Puedes seguirlo aquí:\n{$this->url_seguimiento}";

        $payload = [
            'number'       => $this->normalizarTelefono($telefono),
            'body'         => $mensaje,
            'connectionId' => (int) $connectionId,
            'whatsappId'   => (int) $whatsappId,
        ];

        Log::info('📤 Enviando notificación de pedido a WhatsApp', [
            'pedido_id'    => $this->id,
            'connection_id' => $connectionId,
            'payload'      => $payload,
        ]);

        try {
            // ── Intento 1: con token cacheado ───────────────────────────────
            $token = $this->obtenerTokenWhatsapp();

            if (!$token) {
                Log::error('❌ No se pudo obtener token de WhatsApp', ['pedido_id' => $this->id]);
                return;
            }

            $response = $this->postWhatsappSend($token, $payload);

            // ── Éxito en el primer intento ──────────────────────────────────
            if ($response->successful()) {
                Log::info('✅ Notificación enviada correctamente', [
                    'pedido_id' => $this->id,
                    'status'    => $response->status(),
                ]);
                return;
            }

            $body    = $response->json();
            $rawBody = $response->body();

            Log::warning('⚠️ Primer intento de notificación falló', [
                'pedido_id' => $this->id,
                'status'    => $response->status(),
                'body'      => $rawBody,
            ]);

            // ── 401 ERR_SESSION_EXPIRED → refresh token ─────────────────────
            if ($response->status() === 401 && $this->esSesionExpirada($body, $rawBody)) {
                Log::warning('🔄 Sesión expirada. Intentando refresh_token...', ['pedido_id' => $this->id]);

                $newToken = $this->refrescarTokenWhatsapp();

                // Si el refresh también falla, hacemos login completo
                if (!$newToken) {
                    Log::warning('⚠️ Refresh falló. Intentando login completo...', ['pedido_id' => $this->id]);
                    $newToken = $this->loginWhatsapp(force: true);
                }

                if (!$newToken) {
                    Log::error('❌ No se pudo renovar el token. Notificación no enviada.', ['pedido_id' => $this->id]);
                    return;
                }

                // ── Intento 2: con token renovado ───────────────────────────
                $retryResponse = $this->postWhatsappSend($newToken, $payload);

                if ($retryResponse->successful()) {
                    Log::info('✅ Notificación enviada en reintento', [
                        'pedido_id' => $this->id,
                        'status'    => $retryResponse->status(),
                    ]);
                    return;
                }

                Log::error('❌ Falló el reintento de notificación', [
                    'pedido_id' => $this->id,
                    'status'    => $retryResponse->status(),
                    'body'      => $retryResponse->body(),
                ]);
                return;
            }

            // ── Otro error (no es sesión expirada) ──────────────────────────
            Log::error('❌ Error enviando notificación WhatsApp', [
                'pedido_id' => $this->id,
                'status'    => $response->status(),
                'body'      => $rawBody,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Excepción enviando WhatsApp pedido', [
                'pedido_id' => $this->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /*
    |==========================================================================
    | WHATSAPP API HELPERS
    |==========================================================================
    */

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
                Log::error('❌ LOGIN WHATSAPP SIN TOKEN', ['body' => $response->body()]);
                return null;
            }

            Cache::put($cacheKey, $token, now()->addMinutes(20));

            Log::info('🔐 Token WhatsApp obtenido y cacheado', ['force' => $force]);

            return $token;
        } catch (\Throwable $e) {
            Log::error('❌ EXCEPCIÓN LOGIN WHATSAPP', ['error' => $e->getMessage()]);
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
                Log::warning('⚠️ REFRESH TOKEN SIN TOKEN NUEVO', ['body' => $response->body()]);
                Cache::forget($cacheKey);
                return null;
            }

            Cache::put($cacheKey, $newToken, now()->addMinutes(20));

            Log::info('🔄 Token WhatsApp refrescado correctamente');

            return $newToken;
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            Log::error('❌ EXCEPCIÓN REFRESH TOKEN WHATSAPP', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function esSesionExpirada(?array $body, string $rawBody = ''): bool
    {
        $error = strtoupper((string) data_get($body, 'error', ''));

        if ($error === 'ERR_SESSION_EXPIRED') {
            return true;
        }

        return str_contains(strtoupper($rawBody), 'ERR_SESSION_EXPIRED');
    }

    private function normalizarTelefono(?string $telefono): string
    {
        return preg_replace('/\D+/', '', (string) $telefono);
    }

    /*
    |==========================================================================
    | HELPERS ESTÁTICOS
    |==========================================================================
    */

    public function getUrlSeguimientoAttribute(): string
    {
        return route('pedidos.seguimiento', $this->codigo_seguimiento);
    }

    public static function estadosDisponibles(): array
    {
        return [
            self::ESTADO_NUEVO                => 'Nuevo / Recibido',
            self::ESTADO_EN_PREPARACION       => 'En preparación',
            self::ESTADO_REPARTIDOR_EN_CAMINO => 'Repartidor en camino',
            self::ESTADO_RECOGIDO             => 'Recogido',
            self::ESTADO_ENTREGADO            => 'Entregado',
            self::ESTADO_CANCELADO            => 'Cancelado',
        ];
    }

    public static function tituloPorEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_NUEVO                => 'Pedido recibido',
            self::ESTADO_EN_PREPARACION       => 'En preparación',
            self::ESTADO_REPARTIDOR_EN_CAMINO => 'En camino',
            self::ESTADO_RECOGIDO             => 'Pedido recogido',
            self::ESTADO_ENTREGADO            => 'Pedido entregado',
            self::ESTADO_CANCELADO            => 'Pedido cancelado',
            default                           => 'Actualización de pedido',
        ];
    }
}