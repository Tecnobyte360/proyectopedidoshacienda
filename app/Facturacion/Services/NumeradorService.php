<?php

namespace App\Facturacion\Services;

use App\Facturacion\Models\FeResolucion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 🔢 Numeración consecutiva SEGURA por tenant (emisor) y tipo de documento.
 *
 * A diferencia del export a HGI (MAX+1 sin bloqueo, que salta/duplica), aquí el
 * consecutivo se reserva SIEMPRE dentro de una transacción con `lockForUpdate`
 * sobre la resolución. Garantiza cero saltos y cero duplicados (dos requests
 * concurrentes se serializan) y respeta el rango autorizado por la DIAN.
 *
 * El número queda consumido aunque el documento falle después en la DIAN: la
 * DIAN NO permite reusar un número ya emitido.
 */
class NumeradorService
{
    /**
     * Reserva el siguiente consecutivo de la resolución activa del tenant.
     *
     * @return array{resolucion:FeResolucion, prefijo:?string, numero:int, numero_completo:string}
     */
    public function siguiente(int $tenantId, string $tipoDocumento = 'factura'): array
    {
        return DB::transaction(function () use ($tenantId, $tipoDocumento) {
            /** @var FeResolucion|null $res */
            $res = FeResolucion::query()
                ->where('tenant_id', $tenantId)
                ->where('tipo_documento', $tipoDocumento)
                ->where('activa', true)
                ->lockForUpdate()               // 🔒 serializa el acceso concurrente
                ->orderByDesc('id')
                ->first();

            if (!$res) {
                throw new RuntimeException("El tenant no tiene resolución activa para '{$tipoDocumento}'.");
            }
            if (!$res->vigente()) {
                throw new RuntimeException('La resolución no está vigente por fecha.');
            }

            $base   = max((int) $res->numero_actual, (int) $res->numero_desde - 1);
            $numero = $base + 1;

            if ($numero > (int) $res->numero_hasta) {
                throw new RuntimeException('La resolución agotó su numeración autorizada.');
            }

            $res->numero_actual = $numero;
            $res->save();

            return [
                'resolucion'      => $res,
                'prefijo'         => $res->prefijo,
                'numero'          => $numero,
                'numero_completo' => ($res->prefijo ?? '') . $numero,
            ];
        });
    }

    /** Números restantes en la resolución activa (para alertas de vencimiento). */
    public function cupoRestante(int $tenantId, string $tipoDocumento = 'factura'): int
    {
        $res = FeResolucion::where('tenant_id', $tenantId)
            ->where('tipo_documento', $tipoDocumento)
            ->where('activa', true)
            ->latest('id')->first();
        if (!$res) return 0;
        return max(0, (int) $res->numero_hasta - (int) $res->numero_actual);
    }
}
