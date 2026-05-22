<?php

namespace App\Services;

use App\Models\CampanaDestinatario;
use App\Models\CampanaWhatsapp;
use App\Models\Cliente;
use App\Models\Sede;
use App\Models\ZonaCobertura;
use Illuminate\Support\Facades\Log;

/**
 * Procesa campañas de WhatsApp con throttling para no saturar la API
 * de TecnoByteApp (whatsapp-web.js no oficial — riesgo de baneo si se
 * envían en masa rápido).
 *
 * Estrategia:
 *   - Pausa aleatoria entre intervalo_min_seg y intervalo_max_seg.
 *   - Después de lote_tamano mensajes, descansa descanso_lote_min minutos.
 *   - Solo envía si está dentro de la ventana_desde / ventana_hasta.
 */
class CampanaSenderService
{
    public function __construct(
        private WhatsappSenderService $sender,
        private TenantManager $tenantManager
    ) {}

    /**
     * Construye la lista de destinatarios según los filtros de audiencia.
     */
    public function generarAudiencia(CampanaWhatsapp $c): int
    {
        $filtros = $c->audiencia_filtros ?? [];
        $clientes = collect();

        switch ($c->audiencia_tipo) {
            case 'todos':
                $clientes = Cliente::where('activo', true)
                    ->whereNotNull('telefono_normalizado')
                    ->get();
                break;

            case 'zona':
                $zonaId = $filtros['zona_id'] ?? null;
                if ($zonaId) {
                    $clientes = Cliente::where('activo', true)
                        ->whereNotNull('telefono_normalizado')
                        ->where('zona_cobertura_id', $zonaId)
                        ->get();
                }
                break;

            case 'sede':
                $sedeId = $filtros['sede_id'] ?? null;
                if ($sedeId) {
                    $clientes = Cliente::where('activo', true)
                        ->whereNotNull('telefono_normalizado')
                        ->whereHas('pedidos', fn ($q) => $q->where('sede_id', $sedeId))
                        ->get();
                }
                break;

            case 'con_pedidos':
                $minPedidos = (int) ($filtros['min_pedidos'] ?? 1);
                $clientes = Cliente::where('activo', true)
                    ->whereNotNull('telefono_normalizado')
                    ->where('total_pedidos', '>=', $minPedidos)
                    ->get();
                break;

            case 'sin_pedidos':
                $clientes = Cliente::where('activo', true)
                    ->whereNotNull('telefono_normalizado')
                    ->where(fn ($q) => $q->whereNull('total_pedidos')->orWhere('total_pedidos', 0))
                    ->get();
                break;

            case 'manual':
                $lista = collect($filtros['telefonos'] ?? []);
                foreach ($lista as $tel) {
                    $tel = preg_replace('/\D+/', '', $tel);
                    if ($tel === '') continue;
                    $cl = Cliente::where('telefono_normalizado', $tel)->first();
                    $clientes->push($cl ?: (object) [
                        'telefono_normalizado' => $tel,
                        'nombre'               => 'Cliente',
                        'id'                   => null,
                    ]);
                }
                break;
        }

        // Limpiar destinatarios previos de la campaña y volver a poblarlos
        CampanaDestinatario::where('campana_id', $c->id)->delete();

        $count = 0;
        foreach ($clientes as $cli) {
            $tel = $cli->telefono_normalizado ?? null;
            if (!$tel) continue;

            try {
                CampanaDestinatario::create([
                    'campana_id'  => $c->id,
                    'tenant_id'   => $c->tenant_id,
                    'cliente_id'  => $cli->id ?? null,
                    'nombre'      => $cli->nombre ?? 'Cliente',
                    'telefono'    => $tel,
                    'estado'      => CampanaDestinatario::ESTADO_PENDIENTE,
                ]);
                $count++;
            } catch (\Throwable $e) {
                // Duplicados: ignora
            }
        }

        $c->update([
            'total_destinatarios' => $count,
            'total_pendientes'    => $count,
            'total_enviados'      => 0,
            'total_fallidos'      => 0,
        ]);

        return $count;
    }

    /**
     * Procesa un lote de pendientes para una campaña en estado 'corriendo'.
     * Llamado desde un comando que corre cada minuto.
     */
    public function procesarLote(CampanaWhatsapp $c): array
    {
        if ($c->estado !== CampanaWhatsapp::ESTADO_CORRIENDO) {
            return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0, 'razon' => 'no_corriendo'];
        }

        if (!$c->enHorario()) {
            return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0, 'razon' => 'fuera_de_ventana'];
        }

        // 🛡️ Anti-baneo: respetar descanso entre lotes (intersticial sin perder estado)
        $ultimoLoteKey = "campana_{$c->id}_ultimo_lote_ts";
        $ultimoLoteTs = \Illuminate\Support\Facades\Cache::get($ultimoLoteKey);
        if ($ultimoLoteTs && $c->descanso_lote_min > 0) {
            $segundosRequeridos = $c->descanso_lote_min * 60;
            $transcurridos = now()->timestamp - (int) $ultimoLoteTs;
            if ($transcurridos < $segundosRequeridos) {
                $faltan = $segundosRequeridos - $transcurridos;
                return [
                    'enviados' => 0, 'fallidos' => 0, 'omitidos' => 0,
                    'razon'    => "descanso_lote (faltan {$faltan}s)",
                ];
            }
        }

        $tenant = \App\Models\Tenant::find($c->tenant_id);
        if (!$tenant) {
            $c->update(['estado' => CampanaWhatsapp::ESTADO_PAUSADA, 'notas' => 'Tenant no encontrado']);
            return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0, 'razon' => 'sin_tenant'];
        }
        $this->tenantManager->set($tenant);

        // 🔌 Resolver connection_id correcto del tenant si la campaña no tiene uno asignado.
        // Si connection_id es null, TecnoByteApp elige su sesión default que puede ser
        // de OTRO tenant → siempre forzamos el primer connection_id del tenant.
        $connectionId = $c->connection_id;
        if (!$connectionId) {
            $resolver = app(\App\Services\WhatsappResolverService::class);
            $ids = $resolver->connectionIdsDelTenant($tenant);
            $connectionId = $ids[0] ?? null;
            if ($connectionId) {
                Log::info("📱 Campaña #{$c->id}: connection_id no definido, usando default del tenant: {$connectionId}");
            }
        }

        $pendientes = CampanaDestinatario::where('campana_id', $c->id)
            ->where('estado', CampanaDestinatario::ESTADO_PENDIENTE)
            ->limit($c->lote_tamano)
            ->get();

        if ($pendientes->isEmpty()) {
            $c->update([
                'estado'         => CampanaWhatsapp::ESTADO_COMPLETADA,
                'completada_at'  => now(),
            ]);
            return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0, 'razon' => 'sin_pendientes'];
        }

        $enviados = 0; $fallidos = 0;

        foreach ($pendientes as $d) {
            // Respetar ventana en cada iteración (puede cerrarse a media tanda)
            if (!$c->enHorario()) {
                Log::info("📭 Campaña #{$c->id} fuera de ventana — pausando lote.");
                break;
            }

            $mensaje = $this->renderizar($c->mensaje, $d);

            try {
                // Si hay imagen adjunta → enviarImagen con caption; si no → texto
                $ok = false;
                $usoFallback = false;
                if (!empty($c->media_url)) {
                    $ok = $this->sender->enviarImagen(
                        $d->telefono,
                        $c->media_url,
                        $mensaje,
                        $connectionId
                    );

                    // 🛟 FALLBACK: si TecnoByteApp no soporta media (404 endpoint),
                    // enviar solo el caption como texto. Mejor enviar el mensaje
                    // sin imagen que perder al destinatario.
                    if (!$ok) {
                        Log::info("📨 Campaña #{$c->id}: imagen falló, fallback a texto para {$d->telefono}");
                        $ok = $this->sender->enviarTexto($d->telefono, $mensaje, $connectionId);
                        $usoFallback = true;
                    }
                } else {
                    $ok = $this->sender->enviarTexto($d->telefono, $mensaje, $connectionId);
                }
                if ($ok) {
                    $d->update([
                        'estado'              => CampanaDestinatario::ESTADO_ENVIADO,
                        'mensaje_renderizado' => $mensaje,
                        'enviado_at'          => now(),
                        'intentos'            => $d->intentos + 1,
                        'error_detalle'       => $usoFallback
                            ? '⚠ Enviado solo como texto (API no soporta imagen)'
                            : null,
                    ]);
                    $enviados++;
                } else {
                    $d->update([
                        'estado'        => CampanaDestinatario::ESTADO_FALLIDO,
                        'error_detalle' => 'TecnoByteApp respondió error',
                        'intentos'      => $d->intentos + 1,
                    ]);
                    $fallidos++;
                }
            } catch (\Throwable $e) {
                $d->update([
                    'estado'        => CampanaDestinatario::ESTADO_FALLIDO,
                    'error_detalle' => mb_substr($e->getMessage(), 0, 500),
                    'intentos'      => $d->intentos + 1,
                ]);
                $fallidos++;
            }

            // Pausa anti-baneo entre mensajes
            $sleep = random_int(
                max(1, (int) $c->intervalo_min_seg),
                max((int) $c->intervalo_min_seg, (int) $c->intervalo_max_seg)
            );
            sleep($sleep);
        }

        // Actualizar contadores agregados de la campaña
        $c->refresh();
        $c->update([
            'total_enviados'   => $c->destinatarios()->where('estado', CampanaDestinatario::ESTADO_ENVIADO)->count(),
            'total_fallidos'   => $c->destinatarios()->where('estado', CampanaDestinatario::ESTADO_FALLIDO)->count(),
            'total_pendientes' => $c->destinatarios()->where('estado', CampanaDestinatario::ESTADO_PENDIENTE)->count(),
        ]);

        // Si ya no hay pendientes, marcar como completada
        if ($c->total_pendientes === 0) {
            $c->update(['estado' => CampanaWhatsapp::ESTADO_COMPLETADA, 'completada_at' => now()]);
        }

        // Marcar timestamp del lote para que el descanso_lote_min surta efecto
        if ($enviados > 0 || $fallidos > 0) {
            \Illuminate\Support\Facades\Cache::put(
                "campana_{$c->id}_ultimo_lote_ts",
                now()->timestamp,
                now()->addHours(24)
            );
        }

        Log::info("📨 Campaña #{$c->id} lote: {$enviados} enviados, {$fallidos} fallidos.");
        return ['enviados' => $enviados, 'fallidos' => $fallidos, 'omitidos' => 0, 'razon' => 'ok'];
    }

    /**
     * Reemplaza placeholders en el mensaje con datos del destinatario.
     */
    private function renderizar(string $mensaje, CampanaDestinatario $d): string
    {
        $primerNombre = explode(' ', trim((string) $d->nombre))[0] ?? 'crack';
        return strtr($mensaje, [
            '{nombre}'        => $d->nombre ?: 'crack',
            '{primer_nombre}' => $primerNombre,
            '{telefono}'      => $d->telefono,
        ]);
    }
}
