<?php

namespace App\Console\Commands;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Services\SaasBilling\SaasBillingWompiService;
use App\Services\TenantManager;
use App\Services\WhatsappSenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 📅 Cron mensual de facturación SaaS.
 *
 * Para cada suscripción ACTIVA cuyo fecha_fin caiga dentro de la ventana
 * configurada (default: próximos 7 días), crea un Pago pendiente, genera
 * el link Wompi y se lo manda al admin del tenant por WhatsApp.
 *
 * Idempotente: si ya existe un Pago pendiente o confirmado que cubra ese
 * período (cubre_desde = fecha_fin actual), NO crea duplicado.
 *
 * Se ejecuta diariamente; el filtro de ventana asegura que se anticipa al
 * vencimiento real para que el tenant tenga tiempo de pagar.
 */
class SaasGenerarFacturasMensuales extends Command
{
    protected $signature = 'saas:generar-facturas-mensuales
                            {--dias=7 : Días de anticipación al vencimiento}
                            {--enviar : Enviar el link al tenant por WhatsApp}
                            {--dry-run : Solo simular sin crear ni enviar}';
    protected $description = 'Genera Pagos pendientes para suscripciones próximas a vencer + link Wompi.';

    public function handle(SaasBillingWompiService $wompi): int
    {
        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        if (!$cfg->saas_billing_activo) {
            $this->warn('⏸ Facturación SaaS desactivada en Configuración Plataforma.');
            return Command::SUCCESS;
        }

        // Si no llega --dias, usar la config; lo mismo con --enviar
        $dias    = (int) ($this->option('dias') ?: $cfg->saas_dias_antes_factura ?: 7);
        $enviar  = (bool) $this->option('enviar');
        $dryRun  = (bool) $this->option('dry-run');
        $hasta   = now()->addDays($dias);

        $tm = app(TenantManager::class);

        $resultado = $tm->withoutTenant(function () use ($wompi, $hasta, $enviar, $dryRun) {
            $proximas = Suscripcion::with('tenant', 'plan')
                ->whereIn('estado', [Suscripcion::ESTADO_ACTIVA, Suscripcion::ESTADO_TRIAL])
                ->whereNotNull('fecha_fin')
                ->where('fecha_fin', '<=', $hasta->toDateString())
                ->where('fecha_fin', '>=', now()->subDays(2)->toDateString()) // ya vencidas ayer también las cubrimos
                ->get();

            $creados = 0;
            $omitidos = 0;
            $enviados = 0;

            foreach ($proximas as $sus) {
                if (!$sus->tenant) { $omitidos++; continue; }

                $cubreDesde = $sus->fecha_fin->copy();
                $cubreHasta = $sus->ciclo === Suscripcion::CICLO_ANUAL
                    ? $cubreDesde->copy()->addYear()
                    : $cubreDesde->copy()->addMonth();

                // Idempotencia: ¿ya existe Pago para este período?
                $existente = Pago::where('tenant_id', $sus->tenant_id)
                    ->where('suscripcion_id', $sus->id)
                    ->where('cubre_desde', $cubreDesde->toDateString())
                    ->first();

                if ($existente) {
                    $this->line(sprintf('  ⊘ %s — ya existe pago #%d para %s', $sus->tenant->nombre, $existente->id, $cubreDesde->format('d/m/Y')));
                    $omitidos++;
                    continue;
                }

                $monto = (float) ($sus->monto ?: $sus->plan?->precio_mensual ?: 0);
                if ($monto <= 0) {
                    $this->warn("  ⚠️ {$sus->tenant->nombre} — suscripción sin monto, saltando");
                    $omitidos++;
                    continue;
                }

                $this->info(sprintf(
                    '  ✓ %s — factura $%s COP por período %s → %s',
                    $sus->tenant->nombre,
                    number_format($monto, 0, ',', '.'),
                    $cubreDesde->format('d/m/Y'),
                    $cubreHasta->format('d/m/Y'),
                ));

                if ($dryRun) { $creados++; continue; }

                $pago = Pago::create([
                    'tenant_id'      => $sus->tenant_id,
                    'suscripcion_id' => $sus->id,
                    'monto'          => $monto,
                    'moneda'         => $sus->moneda ?: 'COP',
                    'metodo'         => Pago::METODO_OTRO,
                    'fecha_pago'     => now()->toDateString(),
                    'cubre_desde'    => $cubreDesde->toDateString(),
                    'cubre_hasta'    => $cubreHasta->toDateString(),
                    'estado'         => Pago::ESTADO_PENDIENTE,
                    'notas'          => 'Factura generada automáticamente por scheduler',
                ]);

                // Generar link Wompi
                $linkUrl = $wompi->generarLinkPago($pago, forzarRotacion: true);
                if (!$linkUrl) {
                    $this->warn("    ⚠️ No se generó link Wompi (config saas faltante?)");
                }

                $creados++;

                // Enviar por WhatsApp si fue solicitado y hay link
                if ($enviar && $linkUrl) {
                    $tel = $sus->tenant->contacto_telefono ?? null;
                    $msg = "Hola {$sus->tenant->nombre} 👋\n\n"
                         . "Tu mensualidad de Kivox por *$" . number_format($monto, 0, ',', '.') . " COP* está disponible.\n"
                         . "Período: {$cubreDesde->format('d M')} → {$cubreHasta->format('d M Y')}\n\n"
                         . "Paga aquí: {$linkUrl}\n\n"
                         . "Vence el {$sus->fecha_fin->format('d/m/Y')}.";

                    $okEnvio = false;
                    $errorMsg = null;

                    if (!$tel) {
                        $errorMsg = 'Tenant sin contacto_telefono registrado';
                        $this->warn("    ⚠️ {$errorMsg}");
                    } else {
                        try {
                            $okEnvio = (bool) app(WhatsappSenderService::class)
                                ->enviarTexto($tel, $msg, (int) $sus->tenant_id);
                            if ($okEnvio) {
                                $pago->update(['link_enviado_at' => now(), 'link_canal_envio' => 'whatsapp']);
                                $enviados++;
                                $this->line("    📱 Link enviado por WhatsApp a {$tel}");
                            } else {
                                $errorMsg = 'enviarTexto devolvió false';
                            }
                        } catch (\Throwable $e) {
                            $errorMsg = mb_substr($e->getMessage(), 0, 400);
                            $this->warn("    ⚠️ Falló envío WhatsApp: " . $errorMsg);
                        }
                    }

                    // 📤 Log para monitoreo
                    \App\Models\SaasBillingEnvio::create([
                        'tenant_id'      => $sus->tenant_id,
                        'pago_id'        => $pago->id,
                        'suscripcion_id' => $sus->id,
                        'tipo'           => \App\Models\SaasBillingEnvio::TIPO_FACTURA,
                        'etapa'          => 'factura',
                        'canal'          => 'whatsapp',
                        'telefono'       => $tel,
                        'monto'          => $monto,
                        'moneda'         => $pago->moneda,
                        'ok'             => $okEnvio,
                        'mensaje'        => $msg,
                        'link_pago'      => $linkUrl,
                        'error'          => $errorMsg,
                    ]);
                }
            }

            return compact('creados', 'omitidos', 'enviados');
        });

        $this->info('');
        $this->info("📊 Resumen:");
        $this->info("   • Facturas creadas: {$resultado['creados']}");
        $this->info("   • Omitidas (ya existían o sin monto): {$resultado['omitidos']}");
        if ($enviar) $this->info("   • Links enviados por WhatsApp: {$resultado['enviados']}");

        Log::info('💰 SaaS facturación mensual ejecutada', $resultado);

        return Command::SUCCESS;
    }
}
