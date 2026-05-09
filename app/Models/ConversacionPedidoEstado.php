<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 🎯 Estado estructurado del pedido por conversación.
 *
 * NO usa LLM — es solo CRUD. La verdad sobre qué quiere el cliente
 * vive aquí, no en el chat.
 *
 * Una fila por conversacion_id (relación 1-1).
 */
class ConversacionPedidoEstado extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'conversacion_pedido_estado';

    public const PASO_INICIO         = 'inicio';
    public const PASO_PRODUCTO       = 'producto';
    public const PASO_ENTREGA        = 'entrega';
    public const PASO_IDENTIFICACION = 'identificacion';
    public const PASO_CONFIRMACION   = 'confirmacion';
    public const PASO_CONFIRMADO     = 'confirmado';
    public const PASO_ABANDONADO     = 'abandonado';

    public const METODO_DOMICILIO = 'domicilio';
    public const METODO_RECOGER   = 'recoger';

    protected $fillable = [
        'tenant_id',
        'conversacion_id',
        'paso_actual',
        'productos',
        'metodo_entrega',
        'sede_id',
        'direccion',
        'barrio',
        'ciudad',
        'cobertura_validada',
        'distancia_km',
        'costo_envio',
        'cedula',
        'nombre_cliente',
        'telefono',
        'email',
        'cliente_existe_erp',
        'datos_erp',
        'metodo_pago',
        'cupon_code',
        'notas',
        'validaciones',
        'pedido_id',
        'confirmado_at',
        'abandonado_at',
        'motivo_abandono',
    ];

    protected $casts = [
        'productos'           => 'array',
        'datos_erp'           => 'array',
        'validaciones'        => 'array',
        'cobertura_validada'  => 'boolean',
        'cliente_existe_erp'  => 'boolean',
        'distancia_km'        => 'decimal:2',
        'costo_envio'         => 'decimal:2',
        'confirmado_at'       => 'datetime',
        'abandonado_at'       => 'datetime',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWhatsapp::class, 'conversacion_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * ¿Está completo para llamar confirmar_pedido?
     * No depende del LLM — son chequeos PHP duros.
     */
    public function estaCompleto(): bool
    {
        if (empty($this->productos)) return false;
        if (empty($this->metodo_entrega)) return false;

        if ($this->metodo_entrega === self::METODO_DOMICILIO) {
            if (empty($this->direccion)) return false;
            if (!$this->cobertura_validada) return false;
        } elseif ($this->metodo_entrega === self::METODO_RECOGER) {
            if (empty($this->sede_id)) return false;
        }

        // Identificación: al menos cédula O nombre
        if (empty($this->cedula) && empty($this->nombre_cliente)) return false;

        return true;
    }

    /**
     * Devuelve los campos que faltan para confirmar el pedido,
     * en lenguaje legible para mostrarle al cliente.
     */
    public function camposFaltantes(): array
    {
        $f = [];
        if (empty($this->productos))         $f[] = 'producto y cantidad';
        if (empty($this->metodo_entrega))    $f[] = 'método de entrega (domicilio o recoger)';

        if ($this->metodo_entrega === self::METODO_DOMICILIO) {
            if (empty($this->direccion))     $f[] = 'dirección';
            if (!$this->cobertura_validada)  $f[] = 'validar cobertura';
        }
        if ($this->metodo_entrega === self::METODO_RECOGER && empty($this->sede_id)) {
            $f[] = 'sede de recogida';
        }

        if (empty($this->cedula))            $f[] = 'cédula';
        if (empty($this->nombre_cliente))    $f[] = 'nombre completo';

        return $f;
    }

    /**
     * Genera el orderData para invocar confirmar_pedido (formato legacy
     * compatible con guardarPedidoDesdeToolCall).
     */
    public function aOrderData(): array
    {
        $data = [
            'products'      => $this->productos ?: [],
            'customer_name' => $this->nombre_cliente ?: '',
            'phone'         => $this->telefono ?: '',
            'cedula'        => $this->cedula ?: '',
            'email'         => $this->email ?: '',
            'payment_method'=> $this->metodo_pago ?: '',
            'coupon_code'   => $this->cupon_code ?: '',
            'notes'         => $this->notas ?: '',
        ];

        if ($this->metodo_entrega === self::METODO_DOMICILIO) {
            $data['address']      = $this->direccion ?: '';
            $data['neighborhood'] = $this->barrio ?: '';
            $data['location']     = $this->ciudad ?: '';
        } else {
            // Recoger: address vacío, location = nombre sede
            $data['address']      = '';
            $data['neighborhood'] = '';
            $data['location']     = $this->sede?->nombre ?: '';
            $data['pickup']       = true;
            $data['sede_id']      = $this->sede_id;
        }

        return $data;
    }

    /**
     * Marca una validación como ya hecha para no repetir tools.
     */
    public function marcarValidacion(string $clave, $valor = true): void
    {
        $val = $this->validaciones ?: [];
        $val[$clave] = $valor;
        $this->validaciones = $val;
        $this->save();
    }

    public function yaValidado(string $clave): bool
    {
        return (bool) ($this->validaciones[$clave] ?? false);
    }
}
