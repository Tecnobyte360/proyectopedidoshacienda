<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UsuarioInternoWhatsapp extends Model
{
    use BelongsToTenant;

    protected $table = 'usuarios_internos_whatsapp';

    protected $fillable = [
        'tenant_id',
        'telefono_normalizado',
        'nombre',
        'cargo',
        'departamento',
        'notas',
        'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    /**
     * ¿El teléfono dado pertenece a un usuario interno activo del tenant actual?
     * Cacheada por tenant para no golpear la BD en cada webhook.
     */
    public static function esInterno(string $telefonoNormalizado): bool
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();
        if (!$tenantId) return false;

        $key = "usuarios_internos_t{$tenantId}";
        $lista = \Cache::remember($key, 300, function () use ($tenantId) {
            return self::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('activo', true)
                ->pluck('telefono_normalizado')
                ->map(fn ($t) => preg_replace('/\D+/', '', (string) $t))
                ->filter()
                ->values()
                ->all();
        });

        $tel = preg_replace('/\D+/', '', $telefonoNormalizado);
        return in_array($tel, $lista, true);
    }

    protected static function booted(): void
    {
        $flush = function ($m) {
            $tid = $m->tenant_id ?? app(\App\Services\TenantManager::class)->id();
            if ($tid) \Cache::forget("usuarios_internos_t{$tid}");
        };
        static::saved($flush);
        static::deleted($flush);
    }
}
