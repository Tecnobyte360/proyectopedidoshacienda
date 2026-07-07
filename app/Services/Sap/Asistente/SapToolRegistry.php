<?php

namespace App\Services\Sap\Asistente;

use App\Services\Sap\Ventas\CotizacionesService;
use App\Services\Sap\Ventas\EstadosPedidosService;

/**
 * Registro MODULAR de herramientas (tools) que la IA puede invocar.
 *
 * Cada módulo (ventas, compras, ...) aporta sus tools. Para agregar un módulo
 * nuevo basta con: activarlo en config('sap.modulos'), sumar sus definiciones
 * en definiciones() y su despacho en ejecutar(). El resto del asistente no
 * cambia.
 *
 * Formato de tools = OpenAI (compatible con App\Services\Ai\AiClientService,
 * que además lo traduce a Anthropic automáticamente).
 */
class SapToolRegistry
{
    /** @return array<string,bool> módulos activos */
    public function modulos(): array
    {
        return array_filter((array) config('sap.modulos', []));
    }

    /** Definiciones de tools de los módulos activos (formato OpenAI). */
    public function definiciones(): array
    {
        $tools = [];

        if ($this->moduloActivo('ventas')) {
            $tools = array_merge($tools, $this->toolsVentas());
        }

        // if ($this->moduloActivo('compras')) { $tools = array_merge($tools, $this->toolsCompras()); }

        return $tools;
    }

    /** Ejecuta una tool por nombre y devuelve el resultado (array serializable). */
    public function ejecutar(string $nombre, array $args): array
    {
        return match ($nombre) {
            'analizar_estados_pedidos' => app(EstadosPedidosService::class)->analizar($args),
            'analizar_cotizaciones'    => app(CotizacionesService::class)->analizar($args),
            default                    => ['ok' => false, 'error' => "tool_desconocida:{$nombre}"],
        };
    }

    private function moduloActivo(string $modulo): bool
    {
        return (bool) (config("sap.modulos.{$modulo}", false));
    }

    /* ───────────────────────── Módulo VENTAS ───────────────────────── */

    private function toolsVentas(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'analizar_cotizaciones',
                    'description' => 'VENTAS 1.0 — Analiza cotizaciones en SAP (abiertas o cerradas) e '
                        . 'indica cuáles se convirtieron en pedido de venta (ganadas) y cuáles no se '
                        . 'ganaron (perdidas). Úsala cuando pregunten por cotizaciones, tasa de cierre, '
                        . 'oportunidades abiertas o cotizaciones perdidas.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias'      => ['type' => 'integer', 'description' => 'Analiza cotizaciones de los últimos N días (por defecto 30).'],
                            'estado'    => ['type' => 'string', 'enum' => ['abierta', 'cerrada', 'todas'], 'description' => 'Filtra por estado. Por defecto "todas".'],
                            'card_code' => ['type' => 'string', 'description' => 'Opcional: código de cliente (CardCode) para filtrar.'],
                            'top'       => ['type' => 'integer', 'description' => 'Máximo de cotizaciones a traer (por defecto 40, máx 100).'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'analizar_estados_pedidos',
                    'description' => 'VENTAS 1.1 — Analiza el estado de las órdenes de venta ABIERTAS en SAP: '
                        . 'si están despachadas/facturadas, valida la fecha de vencimiento y detecta '
                        . 'cantidades retrasadas por despachar o facturar. Úsala cuando pregunten por '
                        . 'pedidos pendientes, vencidos, atrasos de despacho o cartera de pedidos.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'card_code'     => ['type' => 'string', 'description' => 'Opcional: código de cliente (CardCode) para filtrar.'],
                            'solo_vencidos' => ['type' => 'boolean', 'description' => 'Si es true, solo devuelve pedidos con fecha de vencimiento pasada.'],
                            'top'           => ['type' => 'integer', 'description' => 'Máximo de pedidos a traer (por defecto 30, máx 100).'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }
}
