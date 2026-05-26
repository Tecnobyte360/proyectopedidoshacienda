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

        // Roles globales: no filtran, ven todo
        if (self::esRolGlobal($user)) {
            return $query;
        }

        // User con sede asignada → solo SU sede
        // Permitimos también los registros sin sede asignada (NULL) para evitar pérdida de datos
        // (ej: pedidos antiguos sin sede o conversaciones del bot antes de asignar)
        if ($user->sede_id) {
            $tabla = $query->getModel()->getTable();
            $col = "{$tabla}.{$columnaSede}";
            return $query->where(function ($q) use ($col, $user) {
                $q->where($col, $user->sede_id)
                  ->orWhereNull($col);
            });
        }

        // User sin sede y sin rol global → no ve nada
        return $query->whereRaw('1 = 0');
    }

    /**
     * Aplica el filtro ESTRICTO (sin permitir NULL) — útil cuando ya migraste
     * todos los datos a tener sede_id obligatorio.
     */
    public static function aplicarEstricto(Builder $query, string $columnaSede = 'sede_id'): Builder
    {
        $user = auth()->user();
        if (!$user) return $query->whereRaw('1 = 0');
        if (self::esRolGlobal($user)) return $query;

        if ($user->sede_id) {
            $tabla = $query->getModel()->getTable();
            return $query->where("{$tabla}.{$columnaSede}", $user->sede_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * ¿El user tiene rol global (admin/gerente/super-admin)?
     */
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
