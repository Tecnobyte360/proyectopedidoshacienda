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

        // 🚶 NO asignar domiciliario si el pedido es para RECOGER en sede.
        // Solo se asignan domiciliarios a pedidos de domicilio.
        if (($pedido->tipo_entrega ?? 'domicilio') === 'recoger') {
            Log::info('🚶 Auto-asignación: pedido es para recoger en sede — no se asigna domiciliario', [
                'pedido_id' => $pedido->id,
            ]);
            return null;
        }

        // No reasignar si el pedido ya tiene domiciliario
        if ($pedido->domiciliario_id) {
            return null;
        }

        $criterio = (string) ($cfg->criterio_asignacion ?: 'balanceado');

        // ⚖️ Peso del pedido (kg): solo se sugieren domiciliarios que puedan cargarlo.
        $pesoKg = $this->pesoKgDePedido($pedido);

        $domiciliario = match ($criterio) {
            'cercania' => $this->porCercania($pedido) ?: $this->porBalanceo($pesoKg),
            'rotacion' => $this->porRotacion($pesoKg)  ?: $this->porBalanceo($pesoKg),
            default    => $this->porBalanceo($pesoKg),
        };

        if (!$domiciliario) {
            Log::info('Auto-asignación: no hay domiciliarios disponibles (o ninguno con capacidad para el peso)', [
                'pedido_id' => $pedido->id,
                'criterio'  => $criterio,
                'peso_kg'   => $pesoKg,
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
            'peso_kg'       => $pesoKg,
            'capacidad_kg'  => $domiciliario->capacidad_kg,
        ]);

        return $domiciliario;
    }

    /**
     * 💡 Sugiere el mejor domiciliario SIN guardar nada (modo híbrido).
     * Usa el mismo criterio configurado. El operador decide si lo confirma.
     * Devuelve el Domiciliario sugerido o null.
     */
    public function sugerirSinGuardar(Pedido $pedido, ?float $pesoKg = null): ?Domiciliario
    {
        $cfg = ConfiguracionBot::actual();
        $criterio = (string) ($cfg->criterio_asignacion ?: 'balanceado');

        // ⚖️ Si no nos pasan el peso, lo calculamos del pedido (sus detalles).
        $pesoKg = $pesoKg ?? $this->pesoKgDePedido($pedido);

        return match ($criterio) {
            'cercania' => $this->porCercania($pedido) ?: $this->porBalanceo($pesoKg),
            'rotacion' => $this->porRotacion($pesoKg)  ?: $this->porBalanceo($pesoKg),
            default    => $this->porBalanceo($pesoKg),
        };
    }

    /**
     * Domiciliario activo con la MENOR cantidad de pedidos en curso.
     * "En curso" = pedidos no entregados ni cancelados.
     */
    private function porBalanceo(?float $pesoKg = null): ?Domiciliario
    {
        // Cualquier domiciliario activo es candidato — no importa su estado.
        // El balanceo se basa en CARGA REAL (pedidos en curso), no en el flag
        // de estado que puede quedar pegado. Así el con menos pedidos siempre gana.
        return Domiciliario::query()
            ->where('activo', true)
            ->tap(fn ($q) => $this->filtrarPorCapacidad($q, $pesoKg))
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
    private function porRotacion(?float $pesoKg = null): ?Domiciliario
    {
        // Domiciliarios activos ordenados por la última vez que recibieron pedido
        // (los que NUNCA recibieron salen primero, luego los que más tiempo llevan
        // sin recibir). Sin filtrar por estado — la carga manda.
        $domis = Domiciliario::query()
            ->where('activo', true)
            ->tap(fn ($q) => $this->filtrarPorCapacidad($q, $pesoKg))
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

    /**
     * ⚖️ Filtra el query de domiciliarios a los que PUEDEN cargar $pesoKg.
     * Domiciliarios con capacidad_kg NULL o 0 = sin límite definido → siempre elegibles.
     */
    private function filtrarPorCapacidad($query, ?float $pesoKg): void
    {
        if ($pesoKg === null || $pesoKg <= 0) {
            return;
        }
        $query->where(function ($q) use ($pesoKg) {
            $q->whereNull('capacidad_kg')
              ->orWhere('capacidad_kg', 0)
              ->orWhere('capacidad_kg', '>=', $pesoKg);
        });
    }

    /**
     * ⚖️ Peso del pedido en KG = suma de cantidades de productos vendidos por peso,
     * convertidas a kg (kg/Kl ×1, libra ×0.5, gramos ÷1000). Las unidades "und/unidad"
     * no suman peso (no se venden por peso).
     */
    public function pesoKgDePedido(Pedido $pedido): float
    {
        try {
            $detalles = $pedido->relationLoaded('detalles')
                ? $pedido->detalles
                : ($pedido->exists ? $pedido->detalles()->get() : collect());
        } catch (\Throwable $e) {
            return 0.0;
        }

        $peso = 0.0;
        foreach ($detalles as $d) {
            $peso += $this->cantidadAKg((float) ($d->cantidad ?? 0), (string) ($d->unidad ?? ''));
        }
        return round($peso, 3);
    }

    /**
     * Convierte una cantidad+unidad a kilogramos. Reutilizable desde otros lados
     * (ej. el formulario de pedido manual) para calcular el peso del pedido.
     */
    public function cantidadAKg(float $cantidad, string $unidad): float
    {
        $u = strtolower(trim($unidad));
        return match (true) {
            in_array($u, ['kg', 'kl', 'kilo', 'kilos', 'kilogramo', 'kilogramos'], true) => $cantidad,
            in_array($u, ['lb', 'libra', 'libras', 'librita', 'libritas'], true)         => $cantidad * 0.5,
            in_array($u, ['g', 'gr', 'gramo', 'gramos'], true)                           => $cantidad / 1000.0,
            default                                                                       => 0.0, // unidad/und → no pesa
        };
    }
}
