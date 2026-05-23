<?php

namespace App\Services;

use App\Models\ErpPedidoPendiente;
use App\Models\Integracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 ERP RETRY QUEUE
 *
 * Encola sincronizaciones con ERP que fallaron (típicamente porque el
 * SQL Server / API del ERP estaba caído) y las reintenta en background
 * con backoff exponencial hasta que el ERP vuelva o se agote el max.
 *
 * Punto clave: el cliente NO sufre el outage. Su pedido queda con
 * número en BD local; la sincronización con el ERP es un detalle de
 * integración que se resuelve después.
 */
class ErpRetryQueueService
{
    /**
     * Encola la creación de un cliente en ERP.
     * Llamado desde el flujo del bot cuando ClienteErpService::crear falló.
     */
    public function encolarCrearCliente(
        int $tenantId,
        int $integracionId,
        array $datosCliente,
        ?int $conversacionId = null,
        ?int $pedidoId = null,
        ?string $telefono = null,
        ?string $errorOriginal = null
    ): ErpPedidoPendiente {
        $row = ErpPedidoPendiente::withoutGlobalScopes()->create([
            'tenant_id'          => $tenantId,
            'integracion_id'     => $integracionId,
            'conversacion_id'    => $conversacionId,
            'pedido_id'          => $pedidoId,
            'tipo'               => ErpPedidoPendiente::TIPO_CLIENTE_CREAR,
            'telefono'           => $telefono,
            'payload'            => $datosCliente,
            'estado'             => ErpPedidoPendiente::ESTADO_PENDIENTE,
            'intentos'           => 1, // ya tuvo 1 intento que falló
            'max_intentos'       => 20,
            'ultimo_error'       => $errorOriginal ? mb_substr($errorOriginal, 0, 1000) : null,
            'ultimo_intento_at'  => now(),
            'proximo_intento_at' => now()->addMinutes(5),
        ]);

        Log::warning('🔄 ErpRetryQueue: cliente encolado para reintento', [
            'queue_id'    => $row->id,
            'tenant_id'   => $tenantId,
            'cedula'      => $datosCliente['cedula'] ?? null,
            'pedido_id'   => $pedidoId,
            'error'       => $errorOriginal ? mb_substr($errorOriginal, 0, 200) : null,
        ]);

        return $row;
    }

    /**
     * Encola la exportación de un pedido al ERP.
     */
    public function encolarExportarPedido(
        int $tenantId,
        int $integracionId,
        int $pedidoId,
        ?int $conversacionId = null,
        ?string $errorOriginal = null
    ): ErpPedidoPendiente {
        $row = ErpPedidoPendiente::withoutGlobalScopes()->create([
            'tenant_id'          => $tenantId,
            'integracion_id'     => $integracionId,
            'conversacion_id'    => $conversacionId,
            'pedido_id'          => $pedidoId,
            'tipo'               => ErpPedidoPendiente::TIPO_PEDIDO_EXPORT,
            'payload'            => ['pedido_id' => $pedidoId],
            'estado'             => ErpPedidoPendiente::ESTADO_PENDIENTE,
            'intentos'           => 1,
            'max_intentos'       => 20,
            'ultimo_error'       => $errorOriginal ? mb_substr($errorOriginal, 0, 1000) : null,
            'ultimo_intento_at'  => now(),
            'proximo_intento_at' => now()->addMinutes(5),
        ]);

        Log::warning('🔄 ErpRetryQueue: pedido encolado para exportar', [
            'queue_id'  => $row->id,
            'pedido_id' => $pedidoId,
            'error'     => $errorOriginal ? mb_substr($errorOriginal, 0, 200) : null,
        ]);

        return $row;
    }

    /**
     * Procesa los pendientes cuyo proximo_intento_at ya llegó.
     * Llamado por el comando artisan erp:procesar-cola.
     *
     * @return array{procesados:int, completados:int, fallidos:int, max_alcanzado:int}
     */
    public function procesarPendientes(int $limit = 50): array
    {
        $pendientes = ErpPedidoPendiente::withoutGlobalScopes()
            ->where('estado', ErpPedidoPendiente::ESTADO_PENDIENTE)
            ->where(function ($q) {
                $q->whereNull('proximo_intento_at')
                  ->orWhere('proximo_intento_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = ['procesados' => 0, 'completados' => 0, 'fallidos' => 0, 'max_alcanzado' => 0];

        foreach ($pendientes as $p) {
            $stats['procesados']++;

            // marcar procesando para evitar dobles ejecuciones
            $p->update(['estado' => ErpPedidoPendiente::ESTADO_PROCESANDO]);

            try {
                $ok = $this->ejecutarUno($p);

                if ($ok) {
                    $p->update([
                        'estado'        => ErpPedidoPendiente::ESTADO_COMPLETADO,
                        'completado_at' => now(),
                        'ultimo_error'  => null,
                    ]);
                    $stats['completados']++;
                    Log::info('✅ ErpRetryQueue: completado', ['queue_id' => $p->id, 'tipo' => $p->tipo]);
                } else {
                    $this->reagendar($p, 'Ejecutor devolvió false sin excepción');
                    $stats['fallidos']++;
                }
            } catch (\Throwable $e) {
                $this->reagendar($p, $e->getMessage());
                $stats['fallidos']++;
            }

            // Si llegó al máximo de intentos, no se reintenta más
            if ($p->intentos >= $p->max_intentos
                && $p->estado === ErpPedidoPendiente::ESTADO_PENDIENTE) {
                $p->update(['estado' => ErpPedidoPendiente::ESTADO_FALLIDO_MAX]);
                $stats['max_alcanzado']++;
                Log::error('💥 ErpRetryQueue: max intentos alcanzado — requiere intervención manual', [
                    'queue_id' => $p->id, 'tipo' => $p->tipo, 'intentos' => $p->intentos,
                ]);
            }
        }

        return $stats;
    }

    /**
     * Ejecuta UN pendiente (cliente o pedido) y devuelve true/false.
     */
    private function ejecutarUno(ErpPedidoPendiente $p): bool
    {
        $integracion = $p->integracion_id ? Integracion::withoutGlobalScopes()->find($p->integracion_id) : null;

        if (!$integracion) {
            Log::warning('ErpRetryQueue: integración no encontrada', ['queue_id' => $p->id]);
            return false;
        }

        // Set tenant context for ERP service
        $tenant = \App\Models\Tenant::find($p->tenant_id);
        if ($tenant) {
            app(TenantManager::class)->set($tenant);
        }

        return match ($p->tipo) {
            ErpPedidoPendiente::TIPO_CLIENTE_CREAR => $this->reintentarCrearCliente($integracion, $p),
            ErpPedidoPendiente::TIPO_PEDIDO_EXPORT => $this->reintentarExportarPedido($integracion, $p),
            default => false,
        };
    }

    private function reintentarCrearCliente(Integracion $integracion, ErpPedidoPendiente $p): bool
    {
        $datos = $p->payload ?? [];
        if (empty($datos['cedula'])) {
            Log::warning('ErpRetryQueue: payload sin cédula', ['queue_id' => $p->id]);
            return false;
        }

        $srv = app(ClienteErpService::class);

        // Antes de crear, ver si ya existe (puede que otro flujo lo haya creado)
        $existe = $srv->buscar($integracion, (string) $datos['cedula'], (string) ($datos['telefono'] ?? ''));
        if ($existe) {
            Log::info('ErpRetryQueue: cliente ya existía — marcando completado', ['queue_id' => $p->id]);
            return true;
        }

        return (bool) $srv->crear($integracion, $datos);
    }

    private function reintentarExportarPedido(Integracion $integracion, ErpPedidoPendiente $p): bool
    {
        if (!$p->pedido_id) {
            Log::warning('ErpRetryQueue: payload sin pedido_id', ['queue_id' => $p->id]);
            return false;
        }

        $pedido = \App\Models\Pedido::withoutGlobalScopes()->find($p->pedido_id);
        if (!$pedido) {
            Log::warning('ErpRetryQueue: pedido no encontrado', ['queue_id' => $p->id, 'pedido_id' => $p->pedido_id]);
            return false;
        }

        $expSrv = app(IntegracionExportService::class);
        $result = $expSrv->exportar($integracion, $pedido);
        return (bool) ($result['ok'] ?? false);
    }

    /**
     * Reagenda con backoff exponencial (5min, 10, 20, 40, 80, ..., max 1h).
     */
    private function reagendar(ErpPedidoPendiente $p, string $error): void
    {
        $nuevoIntentos = $p->intentos + 1;
        $minutos = min(60, 5 * pow(2, $nuevoIntentos - 1));

        $p->update([
            'estado'             => ErpPedidoPendiente::ESTADO_PENDIENTE,
            'intentos'           => $nuevoIntentos,
            'ultimo_error'       => mb_substr($error, 0, 1000),
            'ultimo_intento_at'  => now(),
            'proximo_intento_at' => now()->addMinutes($minutos),
        ]);

        Log::info('🔄 ErpRetryQueue: reagendado', [
            'queue_id'      => $p->id,
            'tipo'          => $p->tipo,
            'intentos'      => $nuevoIntentos,
            'proximo_en'    => "{$minutos}min",
            'error'         => mb_substr($error, 0, 200),
        ]);
    }

    /**
     * Estadísticas para UI/monitoreo.
     */
    public function estadisticas(?int $tenantId = null): array
    {
        $q = ErpPedidoPendiente::withoutGlobalScopes();
        if ($tenantId) $q->where('tenant_id', $tenantId);

        return [
            'pendientes'        => (clone $q)->where('estado', ErpPedidoPendiente::ESTADO_PENDIENTE)->count(),
            'completados_24h'   => (clone $q)->where('estado', ErpPedidoPendiente::ESTADO_COMPLETADO)
                                             ->where('completado_at', '>=', now()->subDay())->count(),
            'fallidos_max'      => (clone $q)->where('estado', ErpPedidoPendiente::ESTADO_FALLIDO_MAX)->count(),
        ];
    }
}
