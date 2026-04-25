<?php

namespace App\Console\Commands;

use App\Models\CampanaWhatsapp;
use App\Services\CampanaSenderService;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcesarCampanasWhatsapp extends Command
{
    protected $signature = 'campanas:procesar';
    protected $description = 'Procesa lotes de campañas WhatsApp en estado corriendo o programadas que ya deben iniciar.';

    public function handle(CampanaSenderService $sender, TenantManager $tm): int
    {
        // 1) Activar campañas programadas cuya hora ya llegó
        $programadas = CampanaWhatsapp::withoutGlobalScopes()
            ->where('estado', CampanaWhatsapp::ESTADO_PROGRAMADA)
            ->whereNotNull('programada_para')
            ->where('programada_para', '<=', now())
            ->get();

        foreach ($programadas as $c) {
            $c->update([
                'estado'      => CampanaWhatsapp::ESTADO_CORRIENDO,
                'iniciada_at' => $c->iniciada_at ?: now(),
            ]);
            $this->info("▶️ Campaña #{$c->id} '{$c->nombre}' iniciada.");
        }

        // 2) Procesar campañas corriendo (un lote por cada una)
        $corriendo = CampanaWhatsapp::withoutGlobalScopes()
            ->where('estado', CampanaWhatsapp::ESTADO_CORRIENDO)
            ->get();

        if ($corriendo->isEmpty()) {
            $this->info('✓ No hay campañas en proceso.');
            return self::SUCCESS;
        }

        foreach ($corriendo as $c) {
            $this->info("📨 Procesando campaña #{$c->id} '{$c->nombre}' (tenant {$c->tenant_id})");
            $r = $sender->procesarLote($c);
            $this->line("   → {$r['enviados']} enviados, {$r['fallidos']} fallidos, razón: {$r['razon']}");
        }

        $tm->set(null);
        return self::SUCCESS;
    }
}
