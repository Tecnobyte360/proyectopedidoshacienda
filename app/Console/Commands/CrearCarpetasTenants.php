<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Crea las carpetas dedicadas de storage para todos los tenants que aún no las tengan.
 * Útil después de un deploy que introduce nuevas subcarpetas o para tenants antiguos.
 *
 *   php artisan tenants:crear-carpetas
 */
class CrearCarpetasTenants extends Command
{
    protected $signature = 'tenants:crear-carpetas';

    protected $description = 'Crea las carpetas de storage para todos los tenants (tenants/{slug}/campanas, productos, etc.)';

    public function handle(): int
    {
        $tenants = Tenant::withoutGlobalScopes()->whereNotNull('slug')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants en la base de datos.');
            return self::SUCCESS;
        }

        $this->info("📁 Creando carpetas para {$tenants->count()} tenant(s)...");

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $exitos = 0;
        $errores = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->crearCarpetasStorage();
                $exitos++;
            } catch (\Throwable $e) {
                $errores++;
                $this->newLine();
                $this->error("✗ {$tenant->slug}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ {$exitos} tenant(s) procesados correctamente.");
        if ($errores > 0) {
            $this->warn("⚠ {$errores} tenant(s) tuvieron error.");
        }

        return self::SUCCESS;
    }
}
