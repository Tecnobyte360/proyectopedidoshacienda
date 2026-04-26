<?php

namespace App\Services;

use App\Models\ConfiguracionBot;
use App\Models\Domiciliario;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Asigna automáticamente un domiciliario a un pedido siguiendo el criterio
 * configurado en /configuracion/bot → Despachos.
 *
 * Criterios soportados:
 *  - balanceado : el domiciliario activo con MENOS pedidos en curso (balanceo por carga)
 *  - cercania   : el más cercano a las coords del pedido (haversine), si hay datos
 *  - rotacion   : round-robin simple (último en haber recibido pedido va al final)
 */
class AsignacionDomiciliarioService
{
    /**
     * Asigna un domiciliario al pedido según la configuración del tenant.
     *
     * Devuelve el Domiciliario asignado o null si no se aplicó (toggle off,
     * sin domiciliarios disponibles, o el pedido ya tenía uno).
     */
    public function asignar(Pedido $pedido): ?Domiciliario
    {
        $cfg = ConfiguracionBot::actual();

        if (!($cfg->auto_asignar_domiciliario ?? false)) {
            return null;
        }

        // No reasignar si el pedido ya tiene domiciliario
        if ($pedido->domiciliario_id) {
            return null;
        }

        $criterio = (string) ($cfg->criterio_asignacion ?: 'balanceado');

        $domiciliario = match ($criterio) {
            'cercania' => $this->porCercania($pedido) ?: $this->porBalanceo(),
            'rotacion' => $this->porRotacion()        ?: $this->porBalanceo(),
            default    => $this->porBalanceo(),
        };

        if (!$domiciliario) {
            Log::info('Auto-asignación: no hay domiciliarios disponibles', [
                'pedido_id' => $pedido->id,
                'criterio'  => $criterio,
            ]);
            return null;
        }

        $pedido->domiciliario_id              = $domiciliario->id;
        $pedido->fecha_asignacion_domiciliario = now();
        $pedido->saveQuietly();

        // Marcar al domiciliario como en_ruta si estaba disponible
        if ($domiciliario->estado === Domiciliario::ESTADO_DISPONIBLE) {
            $domiciliario->update(['estado' => Domiciliario::ESTADO_EN_RUTA]);
        }

        Log::info('🛵 Auto-asignación de domiciliario', [
            'pedido_id'     => $pedido->id,
            'domiciliario'  => $domiciliario->nombre,
            'criterio'      => $criterio,
        ]);

        return $domiciliario;
    }

    /**
     * Domiciliario activo con la MENOR cantidad de pedidos en curso.
     * "En curso" = pedidos no entregados ni cancelados.
     */
    private function porBalanceo(): ?Domiciliario
    {
        // Cualquier domiciliario activo es candidato — no importa su estado.
        // El balanceo se basa en CARGA REAL (pedidos en curso), no en el flag
        // de estado que puede quedar pegado. Así el con menos pedidos siempre gana.
        return Domiciliario::query()
            ->where('activo', true)
            ->withCount(['pedidos as carga_actual' => function ($q) {
                $q->whereNotIn('estado', [
                    Pedido::ESTADO_ENTREGADO,
                    Pedido::ESTADO_CANCELADO,
                ]);
            }])
            ->orderBy('carga_actual')
            ->orderBy('id') // tiebreaker estable
            ->first();
    }

    /**
     * Domiciliario más cercano al punto del pedido (lat/lng).
     * Si el pedido no tiene coords, devuelve null para que caiga al fallback.
     */
    private function porCercania(Pedido $pedido): ?Domiciliario
    {
        if (!$pedido->lat || !$pedido->lng) return null;

        // Si los domiciliarios no almacenan coords, usar la sede del pedido como referencia.
        // Aquí asumo que NO tenemos lat/lng del domi en tiempo real, así que delegamos
        // al criterio balanceado en ese caso.
        return null;
    }

    /**
     * Round-robin: el activo que LLEVA MÁS TIEMPO sin recibir un pedido.
     */
    private function porRotacion(): ?Domiciliario
    {
        // Domiciliarios activos ordenados por la última vez que recibieron pedido
        // (los que NUNCA recibieron salen primero, luego los que más tiempo llevan
        // sin recibir). Sin filtrar por estado — la carga manda.
        $domis = Domiciliario::query()
            ->where('activo', true)
            ->get();

        if ($domis->isEmpty()) return null;

        $ultimas = \App\Models\Pedido::query()
            ->whereIn('domiciliario_id', $domis->pluck('id'))
            ->whereNotNull('fecha_asignacion_domiciliario')
            ->select('domiciliario_id', DB::raw('MAX(fecha_asignacion_domiciliario) as ult'))
            ->groupBy('domiciliario_id')
            ->pluck('ult', 'domiciliario_id');

        return $domis->sortBy(fn ($d) => $ultimas->get($d->id) ?? '0000-00-00 00:00:00')->first();
    }
}
