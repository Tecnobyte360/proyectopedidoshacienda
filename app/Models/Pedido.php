<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    // ✅ ESTADOS CENTRALIZADOS
    const ESTADO_NUEVO = 'nuevo';
    const ESTADO_EN_PREPARACION = 'en_preparacion';
    const ESTADO_REPARTIDOR_EN_CAMINO = 'repartidor_en_camino';
    const ESTADO_RECOGIDO = 'recogido';
    const ESTADO_ENTREGADO = 'entregado';
    const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'sede_id',
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
        'conversacion_completa',
        'resumen_conversacion',
        'codigo_seguimiento',
        'fecha_estado',
        'fecha_entregado',
        'fecha_cancelado',
        'observacion_estado',
    ];

    protected $casts = [
        'fecha_pedido'     => 'datetime',
        'fecha_estado'     => 'datetime',
        'fecha_entregado'  => 'datetime',
        'fecha_cancelado'  => 'datetime',
        'total'            => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | BOOT
    |--------------------------------------------------------------------------
    */

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
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | LÓGICA DE ESTADOS
    |--------------------------------------------------------------------------
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

        $this->estado = $nuevoEstado;
        $this->fecha_estado = now();
        $this->observacion_estado = $descripcion;

        if ($nuevoEstado === self::ESTADO_ENTREGADO) {
            $this->fecha_entregado = now();
        }

        if ($nuevoEstado === self::ESTADO_CANCELADO) {
            $this->fecha_cancelado = now();
        }

        $this->save();

        // 🔥 HISTORIAL
        $this->registrarHistorial(
            estadoNuevo: $nuevoEstado,
            estadoAnterior: $estadoAnterior,
            titulo: $titulo,
            descripcion: $descripcion,
            usuario: $usuario,
            usuarioId: $usuarioId
        );

        // 🚀 NOTIFICAR CLIENTE
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
    public function notificarClienteCambioEstado(): void
{
    if (!$this->telefono_whatsapp) {
        return;
    }

    $mensaje = match ($this->estado) {
        self::ESTADO_EN_PREPARACION =>
            "👨‍🍳 ¡Hola {$this->cliente_nombre}!\n\nTu pedido ya está en preparación 🥩🔥",

        self::ESTADO_REPARTIDOR_EN_CAMINO =>
            "🛵 ¡Tu pedido va en camino!\n\nPrepárate que ya casi llega 🚀",

        self::ESTADO_ENTREGADO =>
            "✅ Pedido entregado\n\nGracias por tu compra 🙌",

        self::ESTADO_CANCELADO =>
            "❌ Tu pedido fue cancelado\n\nSi tienes dudas escríbenos",

        default =>
            "📦 Tu pedido ha sido actualizado",
    };

    // 🔥 URL de seguimiento
    $mensaje .= "\n\n🔎 Puedes seguirlo aquí:\n{$this->url_seguimiento}";

    // 🚀 ENVÍO A TU API WHATSAPP
    try {
        \Illuminate\Support\Facades\Http::post(env('WHATSAPP_API_URL') . '/send-message', [
            'phone' => $this->telefono_whatsapp,
            'message' => $mensaje,
        ]);
    } catch (\Exception $e) {
        \Log::error('Error enviando WhatsApp pedido: ' . $e->getMessage());
    }
}

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getUrlSeguimientoAttribute(): string
    {
        return route('pedidos.seguimiento', $this->codigo_seguimiento);
    }

    public static function estadosDisponibles(): array
    {
        return [
            self::ESTADO_NUEVO => 'Nuevo / Recibido',
            self::ESTADO_EN_PREPARACION => 'En preparación',
            self::ESTADO_REPARTIDOR_EN_CAMINO => 'Repartidor en camino',
            self::ESTADO_RECOGIDO => 'Recogido',
            self::ESTADO_ENTREGADO => 'Entregado',
            self::ESTADO_CANCELADO => 'Cancelado',
        ];
    }

    public static function tituloPorEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_NUEVO => 'Pedido recibido',
            self::ESTADO_EN_PREPARACION => 'En preparación',
            self::ESTADO_REPARTIDOR_EN_CAMINO => 'En camino',
            self::ESTADO_RECOGIDO => 'Pedido recogido',
            self::ESTADO_ENTREGADO => 'Pedido entregado',
            self::ESTADO_CANCELADO => 'Pedido cancelado',
            default => 'Actualización de pedido',
        };
    }
}
