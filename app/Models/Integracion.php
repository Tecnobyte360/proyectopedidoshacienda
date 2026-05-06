<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integracion extends Model
{
    use BelongsToTenant;

    protected $table = 'integraciones';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'tipo',
        'entidad',
        'config',
        'activo',
        'exporta_pedidos',
        'ultima_sincronizacion_at',
        'ultima_sincronizacion_estado',
        'ultima_sincronizacion_log',
        'total_registros_ultima_sync',
    ];

    protected $casts = [
        'config' => 'array',
        'activo' => 'boolean',
        'exporta_pedidos' => 'boolean',
        'ultima_sincronizacion_at' => 'datetime',
        'total_registros_ultima_sync' => 'integer',
    ];

    public const TIPO_MYSQL  = 'mysql';
    public const TIPO_PGSQL  = 'pgsql';
    public const TIPO_SQLSRV = 'sqlsrv';
    public const TIPO_REST   = 'rest';

    public const ENTIDAD_PRODUCTOS  = 'productos';
    public const ENTIDAD_CATEGORIAS = 'categorias';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
