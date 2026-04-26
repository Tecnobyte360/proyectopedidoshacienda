<?php

namespace App\Models;

use App\Events\PedidoActualizado;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pedidos';

    const ESTADO_NUEVO                = 'nuevo';
    const ESTADO_EN_PREPARACION       = 'en_preparacion';
    const ESTADO_REPARTIDOR_EN_CAMINO = 'repartidor_en_camino';
    const ESTADO_RECOGIDO             = 'recogido';
    const ESTADO_ENTREGADO            = 'entregado';
    const ESTADO_CANCELADO            = 'cancelado';

    protected $fillable = [
        'tenant_id',
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
        'token_entrega',
        'fecha_estado',
        'fecha_entregado',
        'fecha_cancelado',
        'observacion_estado',
        'direccion',
        'barrio',
        'lat',
        'lng',
        'zona_cobertura_id',
        'cliente_id',
        'domiciliario_id',
        'fecha_asignacion_domiciliario',
        'fecha_salida_domiciliario',
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
        'lat'             => 'float',
        'lng'             => 'float',
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

    $this->estado             = $nuevoEstado;
    $this->fecha_estado       = now();
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

    $this->load(['sede', 'detalles', 'historialEstados']);

    // Broadcast protegido: no debe romper la operación si Reverb no está corriendo
    try {
        broadcast(new PedidoActualizado($this, 'estado_actualizado'));
    } catch (\Throwable $e) {
        \Log::warning('Broadcast PedidoActualizado falló (Reverb caído?): ' . $e->getMessage());
    }
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

    public function generarTokenEntrega(): string
    {
        $token = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->token_entrega = $token;
        $this->saveQuietly(); // sin disparar observers ni eventos
        return $token;
    }

    public function notificarTokenEntrega(string $token): void
    {
        $telefono = $this->telefono_whatsapp ?: $this->telefono_contacto ?: $this->telefono;

        if (!$telefono || !$this->connection_id) {
            Log::warning('⚠️ No se puede enviar token: falta teléfono o connection_id', [
                'pedido_id' => $this->id,
            ]);
            return;
        }

        $mensaje = "🛵 ¡Hola {$this->cliente_nombre}!\n\n"
            . "Tu pedido #{$this->id} ya va en camino 🚀\n\n"
            . "🔐 *Tu código de verificación de entrega es:*\n\n"
            . "        *{$token}*\n\n"
            . "Dáselo al domiciliario cuando llegue para confirmar la entrega.";

        // TecnoByteApp usa solo 'whatsappId'. NO 'connectionId' (causa
        // ERR_SENDING_WAPP_MSG en algunas versiones del wrapper).
        $payload = [
            'number'     => $this->normalizarTelefono($telefono),
            'body'       => $mensaje,
            'whatsappId' => (int) ($this->whatsapp_id ?: $this->connection_id),
        ];

        try {
            $apiToken = $this->obtenerTokenWhatsapp();

            if (!$apiToken) {
                Log::error('❌ No se pudo obtener token WhatsApp para enviar token de entrega', [
                    'pedido_id' => $this->id,
                ]);
                return;
            }

            $response = $this->postWhatsappSend($apiToken, $payload);

            if ($response->successful()) {
                Log::info('✅ Token de entrega enviado al cliente', [
                    'pedido_id' => $this->id,
                    'token'     => $token,
                ]);
                return;
            }

            // Reintento si sesión expirada
            $body    = $response->json();
            $rawBody = $response->body();

            if ($response->status() === 401 && $this->esSesionExpirada($body, $rawBody)) {
                $newApiToken = $this->refrescarTokenWhatsapp() ?? $this->loginWhatsapp(force: true);

                if ($newApiToken) {
                    $retry = $this->postWhatsappSend($newApiToken, $payload);
                    if ($retry->successful()) {
                        Log::info('✅ Token de entrega enviado en reintento', ['pedido_id' => $this->id]);
                        return;
                    }
                }
            }

            Log::error('❌ No se pudo enviar token de entrega al cliente', [
                'pedido_id' => $this->id,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Excepción enviando token de entrega', [
                'pedido_id' => $this->id,
                'error'     => $e->getMessage(),
            ]);
        }
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

            // 👇 Ya no notifica aquí — lo hace notificarTokenEntrega con el token incluido
            self::ESTADO_REPARTIDOR_EN_CAMINO => null,

            self::ESTADO_ENTREGADO =>
            "✅ ¡Hola {$this->cliente_nombre}!\n\nTu pedido #{$this->id} fue entregado.\nGracias por tu compra 🙌",

            self::ESTADO_CANCELADO =>
            "❌ Hola {$this->cliente_nombre}\n\nTu pedido #{$this->id} fue cancelado.\nSi tienes dudas escríbenos.",

            default =>
            "📦 Hola {$this->cliente_nombre}\n\nTu pedido #{$this->id} ha sido actualizado.",
        };

        // Si el estado no tiene mensaje (como repartidor_en_camino), salir sin enviar
        if ($mensaje === null) {
            return;
        }

        // El link de seguimiento SOLO se envía en la confirmación inicial del pedido
        // (eso lo maneja construirMensajeConfirmacionPedido en el webhook controller).
        // Aquí en las actualizaciones de estado NO lo repetimos para no saturar
        // al cliente con el mismo enlace una y otra vez.
        // Si en algún estado específico quisieras incluirlo (ej. al entregar),
        // puedes concatenarlo condicionalmente.

        // TecnoByteApp usa solo 'whatsappId'. NO 'connectionId'.
        $payload = [
            'number'     => $this->normalizarTelefono($telefono),
            'body'       => $mensaje,
            'whatsappId' => (int) $whatsappId,
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

    /**
     * Obtiene credenciales WhatsApp del tenant DUEÑO de este pedido.
     * Cada pedido tiene tenant_id → usa las credenciales de ese tenant.
     */
    private function whatsappCredencialesDelTenant(): array
    {
        $tenant = $this->tenant_id
            ? app(\App\Services\TenantManager::class)->withoutTenant(
                fn () => \App\Models\Tenant::find($this->tenant_id)
            )
            : null;

        return app(\App\Services\WhatsappResolverService::class)->credenciales($tenant);
    }

    private function whatsappCacheKey(): string
    {
        return 'whatsapp_api_token_t' . ($this->tenant_id ?? 'global');
    }

    private function postWhatsappSend(string $token, array $payload)
    {
        $cred = $this->whatsappCredencialesDelTenant();
        $endpoint = rtrim($cred['api_base_url'], '/') . '/api/messages/messages/send-by-number';
        return Http::withoutVerifying()
            ->withToken($token)
            ->timeout(20)
            ->post($endpoint, $payload);
    }

    private function obtenerTokenWhatsapp(): ?string
    {
        return Cache::get($this->whatsappCacheKey()) ?: $this->loginWhatsapp();
    }

    private function loginWhatsapp(bool $force = false): ?string
    {
        $cacheKey = $this->whatsappCacheKey();
        $cred = $this->whatsappCredencialesDelTenant();

        if ($force) {
            Cache::forget($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        if (empty($cred['email']) || empty($cred['password'])) {
            Log::error('Pedido: tenant sin credenciales WhatsApp', ['tenant_id' => $this->tenant_id]);
            return null;
        }

        try {
            $endpoint = rtrim($cred['api_base_url'], '/') . '/auth/login';
            $response = Http::withoutVerifying()
                ->timeout(20)
                ->post($endpoint, [
                    'email'    => $cred['email'],
                    'password' => $cred['password'],
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
        $cacheKey = $this->whatsappCacheKey();
        $cred = $this->whatsappCredencialesDelTenant();
        $token    = Cache::get($cacheKey);

        if (!$token) {
            Log::warning('⚠️ No hay token en cache para refrescar');
            return null;
        }

        try {
            $endpoint = rtrim($cred['api_base_url'], '/') . '/auth/refresh_token';
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(20)
                ->post($endpoint);

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
        };
    }

     public function domiciliario()
    {
        return $this->belongsTo(Domiciliario::class);
    }

    public function zonaCobertura()
    {
        return $this->belongsTo(ZonaCobertura::class, 'zona_cobertura_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Calcula el semáforo del pedido según el tiempo transcurrido
     * en el estado actual versus el ANS configurado.
     *
     * Retorna ['color', 'minutos_transcurridos', 'minutos_objetivo',
     *          'minutos_alerta', 'minutos_critico', 'porcentaje', 'mensaje', 'ans_nombre']
     * Color: verde | amarillo | rojo | gris (sin ANS o estado final)
     */
    public function semaforoEstado(): array
    {
        $estado = trim((string) $this->estado);

        // Estados finales: gris fijo
        if (in_array($estado, [self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO], true)) {
            return [
                'color'                 => 'gris',
                'minutos_transcurridos' => 0,
                'minutos_objetivo'      => 0,
                'minutos_alerta'        => 0,
                'minutos_critico'       => 0,
                'porcentaje'            => 100,
                'mensaje'               => $estado === self::ESTADO_ENTREGADO ? 'Finalizado' : 'Cancelado',
                'ans_nombre'            => null,
            ];
        }

        // Mapear estado legacy 'confirmado' a 'nuevo'
        $estadoBuscar = $estado === 'confirmado' ? self::ESTADO_NUEVO : $estado;

        $ans = AnsTiempoPedido::paraEstado($estadoBuscar);

        $referencia = $this->fecha_estado ?? $this->created_at ?? now();
        $minutos    = (int) $referencia->diffInMinutes(now());

        if (!$ans) {
            return [
                'color'                 => 'gris',
                'minutos_transcurridos' => $minutos,
                'minutos_objetivo'      => 0,
                'minutos_alerta'        => 0,
                'minutos_critico'       => 0,
                'porcentaje'            => 0,
                'mensaje'               => "{$minutos} min en estado",
                'ans_nombre'            => null,
            ];
        }

        if ($minutos >= $ans->minutos_critico) {
            $color   = 'rojo';
            $mensaje = "Vencido (+{$minutos} min)";
        } elseif ($minutos >= $ans->minutos_alerta) {
            $color   = 'amarillo';
            $mensaje = "Atención: {$minutos} min";
        } else {
            $color   = 'verde';
            $mensaje = "{$minutos} min";
        }

        $porcentaje = $ans->minutos_critico > 0
            ? min(100, (int) round(($minutos / $ans->minutos_critico) * 100))
            : 0;

        return [
            'color'                 => $color,
            'minutos_transcurridos' => $minutos,
            'minutos_objetivo'      => (int) $ans->minutos_objetivo,
            'minutos_alerta'        => (int) $ans->minutos_alerta,
            'minutos_critico'       => (int) $ans->minutos_critico,
            'porcentaje'            => $porcentaje,
            'mensaje'               => $mensaje,
            'ans_nombre'            => $ans->nombre,
        ];
    }
}
