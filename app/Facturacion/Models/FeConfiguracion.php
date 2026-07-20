<?php

namespace App\Facturacion\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración fiscal + DIAN de un TENANT emisor (1:1 con el tenant).
 * El "emisor" es el tenant; aquí vive su identidad tributaria y sus
 * credenciales DIAN. Datos sensibles cifrados.
 */
class FeConfiguracion extends Model
{
    protected $table = 'fe_configuraciones';
    protected $guarded = ['id'];

    protected $hidden = ['software_pin', 'certificado_password', 'api_key'];

    protected $casts = [
        'responsabilidades_fiscales' => 'array',
        'software_pin'               => 'encrypted',
        'certificado_password'       => 'encrypted',
        'certificado_vence_at'       => 'datetime',
        'activa'                     => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function esProduccion(): bool
    {
        return $this->ambiente === 'produccion';
    }

    /** Resolución activa (por tenant) para un tipo de documento. */
    public function resolucionActiva(string $tipo = 'factura'): ?FeResolucion
    {
        return FeResolucion::where('tenant_id', $this->tenant_id)
            ->where('tipo_documento', $tipo)
            ->where('activa', true)
            ->latest('id')
            ->first();
    }

    /** Busca la config del emisor por su API key (auth de software externo). */
    public static function porApiKey(string $apiKey): ?self
    {
        if ($apiKey === '') return null;
        return static::where('api_key', $apiKey)->where('activa', true)->first();
    }
}
