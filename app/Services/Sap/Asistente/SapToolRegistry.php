<?php

namespace App\Services\Sap\Asistente;

use App\Models\SapTenantConfig;
use App\Services\Sap\SapServiceLayerClient;
use App\Services\Sap\Ventas\CotizacionesService;
use App\Services\Sap\Ventas\EstadosPedidosService;

/**
 * Registro MODULAR de herramientas (tools) por TENANT.
 *
 * Las tools que se ofrecen a la IA dependen de los AGENTES activos del tenant
 * (config('sap.agentes') + tabla sap_tenant_configs). Así cada cliente ve solo
 * lo que tiene contratado (p.ej. "Plan Ventas").
 *
 * Formato de tools = OpenAI (compatible con App\Services\Ai\AiClientService).
 */
class SapToolRegistry
{
    /**
     * Claves de agentes activos para el tenant. Si el tenant no tiene config
     * propia (p.ej. pruebas locales), se habilitan todos los del catálogo.
     *
     * @return array<int,string>
     */
    public function agentesActivos(?int $tenantId): array
    {
        $cfg = SapTenantConfig::paraTenant($tenantId);
        if ($cfg) {
            return $cfg->agentesActivos();
        }
        return array_keys((array) config('sap.agentes', []));
    }

    /** Definiciones de tools (formato OpenAI) de los agentes activos del tenant. */
    public function definiciones(?int $tenantId): array
    {
        $toolsPorAgente = $this->toolsDeAgentes($this->agentesActivos($tenantId));

        $mapa = $this->definicionesTools();
        $out  = [];
        foreach ($toolsPorAgente as $toolName) {
            if (isset($mapa[$toolName])) {
                $out[] = $mapa[$toolName];
            }
        }
        return $out;
    }

    /**
     * Ejecuta una tool usando el cliente SAP del TENANT (para que consulte la
     * conexión correcta).
     */
    public function ejecutar(string $nombre, array $args, SapServiceLayerClient $sap): array
    {
        return match ($nombre) {
            'analizar_cotizaciones'    => (new CotizacionesService($sap))->analizar($args),
            'analizar_estados_pedidos' => (new EstadosPedidosService($sap))->analizar($args),
            default                    => ['ok' => false, 'error' => "tool_desconocida:{$nombre}"],
        };
    }

    /** Nombres de tools que aportan los agentes dados (según el catálogo). */
    private function toolsDeAgentes(array $clavesAgente): array
    {
        $catalogo = (array) config('sap.agentes', []);
        $tools    = [];
        foreach ($clavesAgente as $clave) {
            foreach ((array) ($catalogo[$clave]['tools'] ?? []) as $t) {
                $tools[$t] = true;
            }
        }
        return array_keys($tools);
    }

    /** Esquemas OpenAI de todas las tools disponibles, indexados por nombre. */
    private function definicionesTools(): array
    {
        return [
            'analizar_cotizaciones' => [
                'type' => 'function',
                'function' => [
                    'name' => 'analizar_cotizaciones',
                    'description' => 'VENTAS 1.0 — Analiza cotizaciones en SAP (abiertas o cerradas) e '
                        . 'indica cuáles se convirtieron en pedido de venta (ganadas) y cuáles no se '
                        . 'ganaron (perdidas). Úsala para cotizaciones, tasa de cierre, oportunidades '
                        . 'abiertas o cotizaciones perdidas.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias'      => ['type' => 'integer', 'description' => 'Cotizaciones de los últimos N días (por defecto 30).'],
                            'estado'    => ['type' => 'string', 'enum' => ['abierta', 'cerrada', 'todas'], 'description' => 'Filtra por estado. Por defecto "todas".'],
                            'card_code' => ['type' => 'string', 'description' => 'Opcional: código de cliente (CardCode).'],
                            'top'       => ['type' => 'integer', 'description' => 'Máximo a traer (por defecto 40, máx 100).'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            'analizar_estados_pedidos' => [
                'type' => 'function',
                'function' => [
                    'name' => 'analizar_estados_pedidos',
                    'description' => 'VENTAS 1.1 — Analiza el estado de las órdenes de venta ABIERTAS en SAP: '
                        . 'despacho/facturación, fecha de vencimiento y cantidades retrasadas por despachar '
                        . 'o facturar. Úsala para pedidos pendientes, vencidos o atrasos de despacho.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'card_code'     => ['type' => 'string', 'description' => 'Opcional: código de cliente (CardCode).'],
                            'solo_vencidos' => ['type' => 'boolean', 'description' => 'Si es true, solo pedidos con vencimiento pasado.'],
                            'top'           => ['type' => 'integer', 'description' => 'Máximo a traer (por defecto 30, máx 100).'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }
}
