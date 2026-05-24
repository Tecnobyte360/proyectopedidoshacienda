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
        $gracia = (int) $this->option('gracia');
        $enviar = (bool) $this->option('enviar');
        $dryRun = (bool) $this->option('dry-run');
        $tm = app(TenantManager::class);

        $resultado = $tm->withoutTenant(function () use ($sender, $gracia, $enviar, $dryRun) {
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

                $tag = "{$sus->tenant->nombre} (suscripción #{$sus->id}, días {$diasParaVencer})";

                if ($stage === 'suspender') {
                    $this->error("  🚫 SUSPENDER → {$tag}");
                    if (!$dryRun) {
                        $sus->update(['estado' => Suscripcion::ESTADO_EXPIRADA]);
                        Tenant::where('id', $sus->tenant_id)->update(['activo' => false]);
                        Log::warning('🚫 Tenant suspendido por mora', [
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
        $tel = $sus->tenant->telefono_contacto ?? $sus->tenant->whatsapp_contacto ?? null;
        if (!$tel) return;

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

        try {
            $sender->enviarTexto($tel, $msg, (int) $sus->tenant_id);
        } catch (\Throwable $e) {
            Log::warning('SaaS morosidad: falló envío WhatsApp', ['error' => $e->getMessage(), 'tenant_id' => $sus->tenant_id]);
        }
    }
}
