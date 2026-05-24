<?php

namespace App\Console\Commands;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Services\TenantManager;
use App\Services\WhatsappSenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 🚫 Gestión de morosidad SaaS.
 *
 * Flujo escalonado:
 *   - Día -3 antes de vencer  → recordatorio "vence en 3 días"
 *   - Día 0 (vence hoy)        → recordatorio urgente
 *   - Día +1                   → "venció ayer"
 *   - Día +3                   → "tu acceso será suspendido en breve"
 *   - Día +N (default 7)       → suspende tenant.activo = false
 *
 * N (días de gracia) es configurable via opción --gracia.
 * Solo envía recordatorios si hay un Pago pendiente con link Wompi.
 */
class SuspenderTenantsVencidos extends Command
{
    protected $signature = 'tenants:suspender-vencidos
                            {--gracia=7 : Días de gracia tras vencimiento antes de suspender}
                            {--enviar : Enviar recordatorios por WhatsApp}
                            {--dry-run : Solo simular sin hacer cambios}';
    protected $description = 'Gestión de morosidad: recordatorios escalonados + suspensión tras X días.';

    public function handle(WhatsappSenderService $sender): int
    {
        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        if (!$cfg->saas_billing_activo) {
            $this->warn('⏸ Gestión morosidad SaaS desactivada en Configuración Plataforma.');
            return Command::SUCCESS;
        }

        $gracia = (int) ($this->option('gracia') ?: $cfg->saas_dias_gracia ?: 7);
        $enviar = (bool) $this->option('enviar');
        $dryRun = (bool) $this->option('dry-run');
        $tm = app(TenantManager::class);

        // Toggles de etapas activas
        $stagesActivas = [
            'preaviso'    => (bool) $cfg->saas_aviso_preaviso,
            'vence_hoy'   => (bool) $cfg->saas_aviso_vence_hoy,
            'vencio_ayer' => (bool) $cfg->saas_aviso_vencio_ayer,
            'urgencia'    => (bool) $cfg->saas_aviso_urgencia,
        ];

        $resultado = $tm->withoutTenant(function () use ($sender, $gracia, $enviar, $dryRun, $stagesActivas) {
            // Considerar suscripciones activas/trial dentro de la ventana [-3, +gracia+5] días
            $hoy = now()->startOfDay();
            $desde = $hoy->copy()->subDays(3);
            $hasta = $hoy->copy()->addDays(3);

            $candidatas = Suscripcion::with('tenant')
                ->whereIn('estado', [
                    Suscripcion::ESTADO_ACTIVA,
                    Suscripcion::ESTADO_TRIAL,
                    Suscripcion::ESTADO_SUSPENDIDA, // ya marcada para revisar
                ])
                ->whereNotNull('fecha_fin')
                ->where(function ($q) use ($desde, $gracia, $hoy) {
                    $q->whereBetween('fecha_fin', [$desde, $hoy->copy()->addDays($gracia)]);
                })
                ->get();

            $recordatorios = 0;
            $suspendidos   = 0;

            foreach ($candidatas as $sus) {
                if (!$sus->tenant) continue;
                $diasParaVencer = (int) $hoy->diffInDays($sus->fecha_fin->startOfDay(), false);

                // ¿Tiene pago pendiente con link Wompi?
                $pagoPendiente = Pago::where('tenant_id', $sus->tenant_id)
                    ->where('suscripcion_id', $sus->id)
                    ->where('estado', Pago::ESTADO_PENDIENTE)
                    ->orderByDesc('id')
                    ->first();

                $stage = match (true) {
                    $diasParaVencer ===  3 => 'preaviso',
                    $diasParaVencer ===  0 => 'vence_hoy',
                    $diasParaVencer === -1 => 'vencio_ayer',
                    $diasParaVencer === -3 => 'urgencia',
                    $diasParaVencer <= -$gracia => 'suspender',
                    default => null,
                };

                if (!$stage) continue;

                // Respetar toggles del admin (excepto 'suspender' que es ineludible)
                if ($stage !== 'suspender' && !($stagesActivas[$stage] ?? true)) {
                    continue;
                }

                $tag = "{$sus->tenant->nombre} (suscripción #{$sus->id}, días {$diasParaVencer})";

                if ($stage === 'suspender') {
                    $this->error("  🚫 SUSPENDER (soft) → {$tag}");
                    if (!$dryRun) {
                        $sus->update(['estado' => Suscripcion::ESTADO_EXPIRADA]);
                        // ⚠️ NO bloqueamos login (activo=true sigue), solo activamos
                        //    suspendido_por_mora=true → middleware redirige a /billing/expirado.
                        Tenant::where('id', $sus->tenant_id)->update([
                            'suspendido_por_mora' => true,
                            'suspendido_at'       => now(),
                        ]);
                        Log::warning('🚫 Tenant suspendido por mora (soft block)', [
                            'tenant_id'      => $sus->tenant_id,
                            'tenant'         => $sus->tenant?->nombre,
                            'suscripcion_id' => $sus->id,
                            'fecha_fin'      => $sus->fecha_fin->toDateString(),
                            'dias_mora'      => abs($diasParaVencer),
                        ]);
                        $this->enviarMensaje($sender, $sus, $pagoPendiente, 'suspendido', $enviar);
                    }
                    $suspendidos++;
                    continue;
                }

                $this->line("  📨 {$stage} → {$tag}");
                if (!$dryRun) {
                    $this->enviarMensaje($sender, $sus, $pagoPendiente, $stage, $enviar);
                    $recordatorios++;
                }
            }

            return compact('recordatorios', 'suspendidos');
        });

        $this->info('');
        $this->info('📊 Resumen morosidad:');
        $this->info("   • Recordatorios procesados: {$resultado['recordatorios']}");
        $this->info("   • Tenants suspendidos: {$resultado['suspendidos']}");

        return Command::SUCCESS;
    }

    private function enviarMensaje(
        WhatsappSenderService $sender,
        Suscripcion $sus,
        ?Pago $pago,
        string $stage,
        bool $enviar
    ): void {
        if (!$enviar) return;
        $tel = $sus->tenant->contacto_telefono ?? null;

        $monto = number_format((float)($pago?->monto ?? $sus->monto ?? 0), 0, ',', '.');
        $link  = $pago?->link_pago_url ? "\n\nPaga aquí: {$pago->link_pago_url}" : '';
        $fecha = $sus->fecha_fin->format('d/m/Y');

        $msg = match ($stage) {
            'preaviso'    => "Hola {$sus->tenant->nombre} 👋\nTu mensualidad de Kivox por *\${$monto} COP* vence el *{$fecha}* (en 3 días).{$link}",
            'vence_hoy'   => "Hola {$sus->tenant->nombre} ⏰\nTu mensualidad de Kivox vence *HOY ({$fecha})*. Monto: *\${$monto} COP*.{$link}",
            'vencio_ayer' => "Hola {$sus->tenant->nombre} ⚠️\nTu mensualidad de Kivox venció el {$fecha}. Para no interrumpir el servicio, paga *\${$monto} COP* lo antes posible.{$link}",
            'urgencia'    => "Hola {$sus->tenant->nombre} 🚨\nTu suscripción Kivox está vencida hace 3 días. Si no se paga en los próximos días *tu acceso será suspendido*. Monto: *\${$monto} COP*.{$link}",
            'suspendido'  => "Hola {$sus->tenant->nombre} 🚫\nTu acceso a Kivox fue *SUSPENDIDO* por falta de pago. Para reactivarlo paga la mensualidad pendiente.{$link}",
            default => null,
        };
        if (!$msg) return;

        $okEnvio = false;
        $errorMsg = null;

        if (!$tel) {
            $errorMsg = 'Tenant sin contacto_telefono registrado. Edita el tenant en /admin/tenants.';
        } else {
            // Validar formato del teléfono
            $telLimpio = preg_replace('/[^0-9]/', '', $tel);
            if (strlen($telLimpio) < 10) {
                $errorMsg = "Teléfono '{$tel}' inválido (solo {$telLimpio}, mínimo 10 dígitos con código país).";
            } else {
                try {
                    $okEnvio = (bool) $sender->enviarTexto($telLimpio, $msg, (int) $sus->tenant_id);
                    if (!$okEnvio) {
                        $proveedor = $sus->tenant->proveedorWhatsappResuelto();
                        $errorMsg = "Envío rechazado por proveedor '{$proveedor}'. "
                            . ($proveedor === 'meta'
                                ? 'Posible: token Meta caducado, fuera de ventana 24h o número no en WABA.'
                                : 'Posible: TecnoByteApp sin sesión activa o credenciales inválidas.');
                    }
                    $tel = $telLimpio;
                } catch (\Throwable $e) {
                    $errorMsg = 'Excepción: ' . mb_substr($e->getMessage(), 0, 350);
                    Log::warning('SaaS morosidad: falló envío WhatsApp', ['error' => $e->getMessage(), 'tenant_id' => $sus->tenant_id]);
                }
            }
        }

        // 📤 Log para monitoreo
        $tipo = $stage === 'suspendido'
            ? \App\Models\SaasBillingEnvio::TIPO_SUSPENDIDO
            : \App\Models\SaasBillingEnvio::TIPO_RECORDATORIO;

        \App\Models\SaasBillingEnvio::create([
            'tenant_id'         => $sus->tenant_id,
            'pago_id'           => $pago?->id,
            'suscripcion_id'    => $sus->id,
            'tipo'              => $tipo,
            'etapa'             => $stage,
            'canal'             => 'whatsapp',
            'telefono'          => $tel,
            'monto'             => $pago?->monto ?? $sus->monto,
            'moneda'            => $sus->moneda ?: 'COP',
            'ok'                => $okEnvio,
            'intentos'          => 1,
            'ultimo_intento_at' => now(),
            'mensaje'           => $msg,
            'link_pago'         => $pago?->link_pago_url,
            'error'             => $errorMsg,
        ]);
    }
}
