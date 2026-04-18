<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'pais_codigo',
        'telefono',
        'telefono_normalizado',
        'email',
        'fecha_nacimiento',
        'ultima_felicitacion_anio',
        'direccion_principal',
        'barrio',
        'zona_cobertura_id',
        'lat',
        'lng',
        'notas_internas',
        'preferencias',
        'total_pedidos',
        'total_gastado',
        'ticket_promedio',
        'fecha_primer_pedido',
        'fecha_ultimo_pedido',
        'canal_origen',
        'activo',
        'empresa_id',
    ];

    protected $casts = [
        'preferencias'             => 'array',
        'activo'                   => 'boolean',
        'lat'                      => 'float',
        'lng'                      => 'float',
        'total_pedidos'            => 'integer',
        'total_gastado'            => 'decimal:2',
        'ticket_promedio'          => 'decimal:2',
        'fecha_nacimiento'         => 'date',
        'ultima_felicitacion_anio' => 'integer',
        'fecha_primer_pedido'      => 'datetime',
        'fecha_ultimo_pedido'      => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class)->latest('fecha_pedido');
    }

    public function zonaCobertura(): BelongsTo
    {
        return $this->belongsTo(ZonaCobertura::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeRecientes($query)
    {
        return $query->orderByDesc('fecha_ultimo_pedido');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Normaliza un teléfono a formato internacional sin signos.
     * Ej: "+57 300-123-4567" → "573001234567"
     */
    public static function normalizarTelefono(?string $codigoPais, ?string $telefono): string
    {
        $codigo = preg_replace('/\D/', '', (string) $codigoPais);
        $numero = preg_replace('/\D/', '', (string) $telefono);

        // Si el número ya empieza con el código, no duplicarlo
        if ($codigo !== '' && str_starts_with($numero, $codigo)) {
            return $numero;
        }

        return $codigo . $numero;
    }

    /**
     * Encuentra o crea un cliente a partir de un teléfono que llegó por WhatsApp.
     * El nombre se actualiza solo si está vacío en BD (no sobrescribe nombres confirmados).
     */
    public static function encontrarOCrearPorTelefono(string $telefonoNormalizado, ?string $nombre = null, string $canalOrigen = 'whatsapp'): self
    {
        $cliente = self::where('telefono_normalizado', $telefonoNormalizado)->first();

        if ($cliente) {
            // Si el nombre actual es genérico ("Cliente") y nos llegó uno mejor, lo actualizamos
            if (
                !empty($nombre)
                && in_array(strtolower(trim($cliente->nombre)), ['cliente', 'usuario', ''], true)
            ) {
                $cliente->update(['nombre' => $nombre]);
            }
            return $cliente;
        }

        // Inferir código país por longitud (para Colombia)
        $paisCodigo = '+57';
        $solo = preg_replace('/\D/', '', $telefonoNormalizado);
        if (strlen($solo) > 10 && str_starts_with($solo, '57')) {
            $telefonoCorto = substr($solo, 2);
        } else {
            $telefonoCorto = $solo;
        }

        return self::create([
            'nombre'               => $nombre ?: 'Cliente',
            'pais_codigo'          => $paisCodigo,
            'telefono'             => $telefonoCorto,
            'telefono_normalizado' => $telefonoNormalizado,
            'canal_origen'         => $canalOrigen,
            'activo'               => true,
        ]);
    }

    /**
     * Recalcula y actualiza las métricas del cliente (total_pedidos, total_gastado, etc).
     */
    public function recalcularMetricas(): void
    {
        $pedidos = $this->pedidos()->where('estado', '!=', Pedido::ESTADO_CANCELADO)->get();

        $total       = $pedidos->count();
        $totalDinero = (float) $pedidos->sum('total');
        $ticket      = $total > 0 ? $totalDinero / $total : 0;

        $primero = $pedidos->min('fecha_pedido');
        $ultimo  = $pedidos->max('fecha_pedido');

        $this->update([
            'total_pedidos'       => $total,
            'total_gastado'       => $totalDinero,
            'ticket_promedio'     => $ticket,
            'fecha_primer_pedido' => $primero,
            'fecha_ultimo_pedido' => $ultimo,
        ]);
    }

    /**
     * Devuelve un resumen breve del cliente para inyectar en el prompt del bot.
     */
    public function resumenParaBot(): string
    {
        $totalPedidos = (int) ($this->total_pedidos ?? 0);

        if ($totalPedidos <= 0) {
            return "Cliente nuevo (primera vez que escribe).";
        }

        $partes = [
            "Cliente recurrente — {$totalPedidos} pedido(s) previo(s).",
        ];

        if ($this->fecha_ultimo_pedido) {
            $partes[] = "Último pedido: " . $this->fecha_ultimo_pedido->diffForHumans();
        }

        if ($this->total_gastado > 0) {
            $partes[] = "Total gastado histórico: $" . number_format($this->total_gastado, 0, ',', '.');
        }

        if ($this->direccion_principal) {
            $partes[] = "Dirección habitual: {$this->direccion_principal}" . ($this->barrio ? ", {$this->barrio}" : '');
        }

        if (!empty($this->preferencias)) {
            $partes[] = "Preferencias: " . implode(', ', (array) $this->preferencias);
        }

        if (!empty($this->notas_internas)) {
            $partes[] = "Nota interna: {$this->notas_internas}";
        }

        return implode("\n", $partes);
    }

    public function whatsappUrl(): ?string
    {
        return $this->telefono_normalizado
            ? "https://wa.me/{$this->telefono_normalizado}"
            : null;
    }

    /**
     * Devuelve el connection_id de WhatsApp por el cual debería salir un
     * mensaje dirigido a este cliente.
     *
     * Prioridad:
     *   1. La conexión de su última conversación activa/cerrada (por donde le
     *      escribieron antes — esa es la "línea oficial" con este cliente).
     *   2. La conexión por defecto configurada en el bot (si está configurada).
     *   3. null → WhatsappSenderService usará la conexión genérica.
     */
    public function conexionWhatsappPreferida(): ?int
    {
        $ultimaConv = ConversacionWhatsapp::query()
            ->where('cliente_id', $this->id)
            ->whereNotNull('connection_id')
            ->orderByDesc('id')
            ->first();

        if ($ultimaConv?->connection_id) {
            return (int) $ultimaConv->connection_id;
        }

        $default = ConfiguracionBot::actual()->connection_id_default;
        return $default ? (int) $default : null;
    }

    public function beneficios()
    {
        return $this->hasMany(BeneficioCliente::class);
    }

    /**
     * Devuelve el beneficio vigente del tipo solicitado (o null).
     * Por defecto busca el primero que encuentre de cualquier tipo.
     */
    public function beneficioVigente(?string $tipo = null): ?BeneficioCliente
    {
        $q = $this->beneficios()->vigentes();
        if ($tipo) {
            $q->where('tipo', $tipo);
        }
        return $q->orderBy('vigente_hasta')->first();
    }

    public function tieneEnvioGratisVigente(): bool
    {
        return $this->beneficioVigente(BeneficioCliente::TIPO_ENVIO_GRATIS) !== null;
    }
}
