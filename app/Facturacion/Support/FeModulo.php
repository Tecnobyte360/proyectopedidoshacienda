<?php

namespace App\Facturacion\Support;

use Illuminate\Support\Facades\DB;

/**
 * ¿El tenant tiene habilitado el PAQUETE de facturación electrónica?
 * Se resuelve con el mismo mecanismo de planes existente
 * (planes.feature_facturacion_electronica), vía la suscripción activa o el
 * código de plan del tenant.
 */
class FeModulo
{
    public static function habilitado(int $tenantId): bool
    {
        // 1) Por suscripción activa → plan
        $planId = DB::table('suscripciones')
            ->where('tenant_id', $tenantId)
            ->whereIn('estado', ['activa', 'activo', 'active', 'vigente', 'trial'])
            ->orderByDesc('id')
            ->value('plan_id');

        if ($planId) {
            return (bool) DB::table('planes')->where('id', $planId)
                ->value('feature_facturacion_electronica');
        }

        // 2) Fallback: código de plan denormalizado en tenants.plan
        $planCode = DB::table('tenants')->where('id', $tenantId)->value('plan');
        if ($planCode) {
            return (bool) DB::table('planes')->where('codigo', $planCode)
                ->value('feature_facturacion_electronica');
        }

        return false;
    }
}
