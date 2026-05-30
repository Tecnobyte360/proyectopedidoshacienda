<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BotAutoEntrenamientoService;
use Illuminate\Console\Command;

class BotEntrenar extends Command
{
    protected $signature = 'bot:entrenar
        {--tenant= : ID de un tenant específico (si se omite, todos los activos)}
        {--full : Analiza TODAS las conversaciones (primer entrenamiento). Sin esto, solo las recientes}
        {--dias=2 : Ventana de días hacia atrás cuando NO es full}';

    protected $description = 'Auto-entrena el bot analizando conversaciones reales y destilando lecciones.';

    public function handle(BotAutoEntrenamientoService $svc): int
    {
        $full = (bool) $this->option('full');
        $dias = (int) $this->option('dias');
        $tenantOpt = $this->option('tenant');

        $tenants = $tenantOpt
            ? Tenant::query()->withoutGlobalScopes()->where('id', $tenantOpt)->get()
            : Tenant::query()->withoutGlobalScopes()->get();

        if ($tenants->isEmpty()) {
            $this->error('No se encontraron tenants.');
            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("🧠 Entrenando bot de: {$tenant->nombre}" . ($full ? ' (FULL)' : " (últimos {$dias}d)"));

            try {
                $r = $svc->entrenar($tenant, $full, $dias);
                $this->line("   Conversaciones analizadas: {$r['convs_analizadas']} en {$r['lotes']} lote(s)");
                $this->line("   Lecciones nuevas:      {$r['lecciones_nuevas']}");
                $this->line("   Lecciones reforzadas:  {$r['lecciones_reforzadas']}");
            } catch (\Throwable $e) {
                $this->error("   ❌ {$tenant->nombre}: " . $e->getMessage());
            }
        }

        $this->info(PHP_EOL . '✅ Entrenamiento completado.');
        return self::SUCCESS;
    }
}
