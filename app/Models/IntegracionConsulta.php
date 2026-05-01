<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegracionConsulta extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $table = 'integracion_consultas';

    public const TIPOS = [
        'clientes'  => '👤 Clientes',
        'productos' => '📦 Productos',
        'ventas'    => '💰 Ventas / Pedidos',
        'stock'     => '🏷️ Inventario / Stock',
        'cuentas'   => '💳 Cuentas / Cartera',
        'reportes'  => '📊 Reportes / Estadísticas',
        'otros'     => '🔧 Otros',
    ];

    protected $fillable = [
        'integracion_id',
        'tenant_id',
        'nombre',
        'nombre_publico',
        'descripcion',
        'tipo',
        'query_sql',
        'parametros',
        'mapeo',
        'usar_en_bot',
        'activa',
        'ultima_ejecucion_at',
        'total_ejecuciones',
    ];

    protected $casts = [
        'parametros'         => 'array',
        'mapeo'              => 'array',
        'usar_en_bot'        => 'boolean',
        'activa'             => 'boolean',
        'ultima_ejecucion_at' => 'datetime',
        'total_ejecuciones'  => 'integer',
    ];

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(Integracion::class);
    }

    /**
     * Genera el nombre de la tool para el bot — limpio, snake_case, prefijo consulta_.
     */
    public function nombreTool(): string
    {
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($this->nombre));
        $slug = preg_replace('/_+/', '_', $slug);
        return 'consulta_' . trim($slug, '_');
    }
}
