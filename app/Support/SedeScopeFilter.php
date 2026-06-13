<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * 🏢 Filtro centralizado por sede para queries de Pedidos / Conversaciones / etc.
 *
 * Reglas:
 *   - Si el user actual NO tiene sede_id → ve TODO del tenant (admin global)
 *   - Si el user tiene sede_id + tiene rol Admin/Gerente/Super → ve TODO igual
 *   - Si el user tiene sede_id + rol operativo (Cajero/Operador/Domiciliario/etc.)
 *     → SOLO ve los registros que pertenecen a su sede
 *
 * Uso:
 *   $pedidos = SedeScopeFilter::aplicar(Pedido::query())->get();
 *   $convs   = SedeScopeFilter::aplicar(ConversacionWhatsapp::query(), 'sede_id')->get();
 */
class SedeScopeFilter
{
    /** Roles que SIEMPRE ven todo, sin filtro por sede */
    public const ROLES_GLOBALES = ['super-admin', 'admin', 'gerente', 'administrador'];

    /**
     * Aplica el filtro a un query builder según la sede del user actual.
     *
     * @param  Builder $query
     * @param  string  $columnaSede  nombre de la columna que tiene el sede_id (default 'sede_id')
     * @return Builder
     */
    public static function aplicar(Builder $query, string $columnaSede = 'sede_id'): Builder
    {
        $user = auth()->user();

        // Sin usuario logueado → no devolver nada (defensa)
        if (!$user) return $query->whereRaw('1 = 0');

        // Roles globales o usuarios con permiso 'sedes.ver-todas': no filtran, ven todo
        if (self::veTodasLasSedes($user)) {
            return $query;
        }

        // Conjunto de sedes que el usuario puede ver (multi-sede + su sede_id).
        // Permitimos también los registros sin sede (NULL) para no perder datos.
        $ids = self::sedeIdsPermitidos($user);
        if (empty($ids)) {
            return $query->whereRaw('1 = 0'); // sin sedes → no ve nada
        }
        $tabla = $query->getModel()->getTable();
        $col = "{$tabla}.{$columnaSede}";
        return $query->where(function ($q) use ($col, $ids) {
            $q->whereIn($col, $ids)
              ->orWhereNull($col);
        });
    }

    /**
     * IDs de sedes que el usuario puede ver: las de la relación sedesVisibles
     * (multi-sede) y, si no tiene ninguna, su sede_id "principal".
     */
    public static function sedeIdsPermitidos($user): array
    {
        if (!$user) return [];
        $ids = [];
        try { $ids = $user->sedesVisibles()->pluck('sedes.id')->all(); } catch (\Throwable $e) { $ids = []; }
        if (empty($ids) && $user->sede_id) $ids = [(int) $user->sede_id];
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Aplica el filtro ESTRICTO (sin permitir NULL) — útil cuando ya migraste
     * todos los datos a tener sede_id obligatorio.
     */
    public static function aplicarEstricto(Builder $query, string $columnaSede = 'sede_id'): Builder
    {
        $user = auth()->user();
        if (!$user) return $query->whereRaw('1 = 0');
        if (self::veTodasLasSedes($user)) return $query;

        $ids = self::sedeIdsPermitidos($user);
        if (empty($ids)) return $query->whereRaw('1 = 0');
        $tabla = $query->getModel()->getTable();
        return $query->whereIn("{$tabla}.{$columnaSede}", $ids);
    }

    /**
     * ¿El user tiene rol global (admin/gerente/super-admin)?
     */
    /**
     * ¿El user ve TODAS las sedes? (rol global O permiso 'sedes.ver-todas').
     * Permite dar a un usuario operativo acceso a todas las sedes sin hacerlo admin.
     */
    public static function veTodasLasSedes($user): bool
    {
        if (!$user) return false;
        if (self::esRolGlobal($user)) return true;
        try {
            return (bool) $user->can('sedes.ver-todas');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function esRolGlobal($user): bool
    {
        if (!$user) return false;
        try {
            $rolesUser = $user->getRoleNames()->map(fn ($r) => strtolower(trim($r)))->all();
            foreach (self::ROLES_GLOBALES as $rolGlobal) {
                if (in_array($rolGlobal, $rolesUser, true)) return true;
            }
        } catch (\Throwable $e) {}
        return false;
    }

    /**
     * Devuelve el sede_id del user actual (null si es admin global o no tiene sede).
     * Útil para mostrar badges tipo "Sede: Norte" en el header.
     */
    public static function sedeActualId(): ?int
    {
        $user = auth()->user();
        if (!$user || self::esRolGlobal($user)) return null;
        return $user->sede_id ?: null;
    }
}
