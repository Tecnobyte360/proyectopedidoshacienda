<?php

namespace App\Console\Commands;

use App\Models\Suscripcion;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SuspenderTenantsVencidos extends Command
{
    protected $signature = 'tenants:suspender-vencidos {--dry-run : Solo simular sin hacer cambios}';
    protected $description = 'Suspende tenants cuya suscripción ha expirado.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tm = app(TenantManager::class);

        $resultado = $tm->withoutTenant(function () use ($dryRun) {
            $vencidas = Suscripcion::with('tenant')
                ->where('estado', Suscripcion::ESTADO_ACTIVA)
                ->where('fecha_fin', '<', now()->toDateString())
                ->get();

            $afectados = 0;

            foreach ($vencidas as $sus) {
                $this->line(sprintf(
                    '  → %s (suscripción #%d vencida el %s)',
                    $sus->tenant?->nombre ?? '?',
                    $sus->id,
                    $sus->fecha_fin->format('d/m/Y')
                ));

                if (!$dryRun) {
                    $sus->update(['estado' => Suscripcion::ESTADO_EXPIRADA]);
                    Tenant::where('id', $sus->tenant_id)->update(['activo' => false]);
                    Log::warning('🚫 Tenant suspendido por suscripción vencida', [
                        'tenant_id'      => $sus->tenant_id,
                        'tenant'         => $sus->tenant?->nombre,
                        'suscripcion_id' => $sus->id,
                        'fecha_fin'      => $sus->fecha_fin->toDateString(),
                    ]);
                }

                $afectados++;
            }

            return $afectados;
        });

        if ($resultado === 0) {
            $this->info('✓ No hay tenants vencidos.');
        } else {
            $this->info(($dryRun ? '[DRY-RUN] ' : '') . "🚫 {$resultado} tenant(s) " . ($dryRun ? 'serían' : 'fueron') . ' suspendidos.');
        }

        return Command::SUCCESS;
    }
}
