<?php

namespace App\Models;

use App\Casts\EncryptedTolerante;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración del Asistente IA + SAP para UN tenant:
 *   - Conexión propia al Service Layer (dinámica por cliente).
 *   - Agentes activos (del catálogo config('sap.agentes')).
 */
class SapTenantConfig extends Model
{
    protected $table = 'sap_tenant_configs';

    protected $fillable = [
        'tenant_id', 'activo',
        'sl_mode', 'sl_url', 'sl_company', 'sl_username', 'sl_password', 'sl_timeout',
        'bridge_url', 'bridge_token',
        'agentes',
    ];

    protected $casts = [
        'activo'       => 'boolean',
        'sl_timeout'   => 'integer',
        'sl_password'  => EncryptedTolerante::class,
        'bridge_token' => EncryptedTolerante::class,
        'agentes'      => 'array',
    ];

    /* ───────────────────────────── Resolución ───────────────────────────── */

    /** Config del tenant activa, o null. */
    public static function paraTenant(?int $tenantId): ?self
    {
        if (!$tenantId) {
            return null;
        }
        return static::where('tenant_id', $tenantId)->where('activo', true)->first();
    }

    /**
     * Array de conexión con la forma que espera SapServiceLayerClient.
     */
    public function conexion(): array
    {
        return [
            'mode'         => $this->sl_mode ?: 'direct',
            'url'          => (string) $this->sl_url,
            'database'     => (string) $this->sl_company,
            'username'     => (string) $this->sl_username,
            'password'     => (string) $this->sl_password,
            'timeout'      => (int) ($this->sl_timeout ?: 30),
            'bridge_url'   => (string) $this->bridge_url,
            'bridge_token' => (string) $this->bridge_token,
        ];
    }

    /* ───────────────────────────── Agentes ──────────────────────────────── */

    public function agenteActivo(string $clave): bool
    {
        return in_array($clave, (array) ($this->agentes ?? []), true);
    }

    /** @return array<int,string> claves de agentes activos */
    public function agentesActivos(): array
    {
        // Solo devolvemos agentes que aún existen en el catálogo.
        $catalogo = array_keys((array) config('sap.agentes', []));
        return array_values(array_intersect((array) ($this->agentes ?? []), $catalogo));
    }

    /** Activa un plan completo (suma sus agentes a los ya activos). */
    public function activarPlan(string $planClave): self
    {
        $plan = config("sap.planes.{$planClave}");
        if ($plan) {
            $this->agentes = array_values(array_unique(array_merge(
                (array) ($this->agentes ?? []),
                (array) ($plan['agentes'] ?? []),
            )));
            $this->save();
        }
        return $this;
    }

    public function activarAgente(string $clave): self
    {
        $act = (array) ($this->agentes ?? []);
        if (!in_array($clave, $act, true)) {
            $act[] = $clave;
            $this->agentes = array_values($act);
            $this->save();
        }
        return $this;
    }

    public function desactivarAgente(string $clave): self
    {
        $this->agentes = array_values(array_filter(
            (array) ($this->agentes ?? []),
            fn ($a) => $a !== $clave,
        ));
        $this->save();
        return $this;
    }
}
