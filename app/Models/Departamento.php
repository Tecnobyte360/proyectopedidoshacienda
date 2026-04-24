<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use BelongsToTenant;

    protected $table = 'departamentos';

    protected $fillable = [
        'tenant_id', 'nombre', 'icono_emoji', 'color',
        'keywords', 'saludo_automatico', 'notificar_internos',
        'orden', 'activo',
    ];

    protected $casts = [
        'keywords'           => 'array',
        'notificar_internos' => 'boolean',
        'activo'             => 'boolean',
        'orden'              => 'integer',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(UsuarioInternoWhatsapp::class, 'departamento_id');
    }

    /**
     * Encuentra el departamento cuyas keywords matchean el mensaje.
     * Cacheada por tenant para no golpear BD en cada webhook.
     */
    public static function detectarPorMensaje(string $mensaje): ?self
    {
        $tenantId = app(\App\Services\TenantManager::class)->id();
        if (!$tenantId || trim($mensaje) === '') return null;

        $key = "deptos_keywords_t{$tenantId}";
        $deptos = \Cache::remember($key, 300, function () use ($tenantId) {
            return self::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'nombre', 'keywords'])
                ->map(fn ($d) => [
                    'id'       => $d->id,
                    'nombre'   => $d->nombre,
                    'keywords' => array_map('mb_strtolower', $d->keywords ?? []),
                ])
                ->all();
        });

        $msgLower = mb_strtolower(trim($mensaje));

        foreach ($deptos as $d) {
            foreach ($d['keywords'] as $kw) {
                if ($kw === '') continue;
                if (str_contains($msgLower, $kw)) {
                    return self::withoutGlobalScopes()->find($d['id']);
                }
            }
        }
        return null;
    }

    protected static function booted(): void
    {
        $flush = function ($m) {
            $tid = $m->tenant_id ?? app(\App\Services\TenantManager::class)->id();
            if ($tid) \Cache::forget("deptos_keywords_t{$tid}");
        };
        static::saved($flush);
        static::deleted($flush);
    }
}
