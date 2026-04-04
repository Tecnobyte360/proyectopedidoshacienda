<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

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
        return $this->hasMany(HistorialEstadoPedido::class)->orderBy('fecha_evento', 'asc');
    }

    public function cambiarEstado(string $nuevoEstado, ?string $descripcion = null, ?string $titulo = null, ?string $usuario = null, ?int $usuarioId = null): void
    {
        $estadoAnterior = $this->estado;

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

        $this->registrarHistorial(
            estadoNuevo: $nuevoEstado,
            estadoAnterior: $estadoAnterior,
            titulo: $titulo,
            descripcion: $descripcion,
            usuario: $usuario,
            usuarioId: $usuarioId
        );
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
            'titulo'          => $titulo,
            'descripcion'     => $descripcion,
            'usuario'         => $usuario,
            'usuario_id'      => $usuarioId,
            'fecha_evento'    => now(),
        ]);
    }

    public function getUrlSeguimientoAttribute(): string
    {
        return route('pedidos.seguimiento', $this->codigo_seguimiento);
    }

    public static function estadosDisponibles(): array
    {
        return [
            self::ESTADO_NUEVO => 'Nuevo / Recibido',
            self::ESTADO_EN_PREPARACION => 'En progreso / Preparación',
            self::ESTADO_REPARTIDOR_EN_CAMINO => 'Repartidor en camino',
            self::ESTADO_RECOGIDO => 'Recogido / Código de verificación',
            self::ESTADO_ENTREGADO => 'Finalizado / Entregado',
            self::ESTADO_CANCELADO => 'Cancelado',
        ];
    }
}