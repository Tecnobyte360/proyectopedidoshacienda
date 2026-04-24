<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\FelicitacionCumpleanos;
use Illuminate\Console\Command;

/**
 * Arregla registros de felicitaciones creados con tenant_id=null
 * (bug de CLI sin tenant activo). Los reasigna al tenant del cliente vinculado.
 */
class ReasignarFelicitacionesHuerfanas extends Command
{
    protected $signature = 'felicitaciones:reasignar-huerfanas {--dry-run}';

    protected $description = 'Reasigna felicitaciones con tenant_id=null al tenant del cliente vinculado.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $huerfanas = FelicitacionCumpleanos::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->get();

        if ($huerfanas->isEmpty()) {
            $this->info('✓ No hay felicitaciones huérfanas.');
            return self::SUCCESS;
        }

        $this->info("Encontradas {$huerfanas->count()} felicitaciones sin tenant.");
        $asignadas = 0;
        $sinCliente = 0;

        foreach ($huerfanas as $f) {
            $cliente = Cliente::withoutGlobalScopes()->find($f->cliente_id);
            if (!$cliente || !$cliente->tenant_id) {
                $sinCliente++;
                continue;
            }

            $this->line("  #{$f->id} → tenant {$cliente->tenant_id}");
            if (!$dry) {
                FelicitacionCumpleanos::withoutGlobalScopes()
                    ->where('id', $f->id)
                    ->update(['tenant_id' => $cliente->tenant_id]);
            }
            $asignadas++;
        }

        $this->info("✅ {$asignadas} asignadas, {$sinCliente} sin cliente válido.");
        if ($dry) $this->warn('(DRY-RUN — no se escribió nada)');

        return self::SUCCESS;
    }
}
