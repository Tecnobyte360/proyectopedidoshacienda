<?php

namespace App\Console\Commands;

use App\Models\Corte;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Crea los cortes tipicos de carnicería (res/cerdo) por tenant.
 * Basado en el documento RES.docx del cliente.
 */
class SeedCortesDefault extends Command
{
    protected $signature = 'cortes:seed {--tenant= : ID de tenant específico}';
    protected $description = 'Crea cortes típicos de carnicería para cada tenant activo.';

    public function handle(TenantManager $tm): int
    {
        $cortes = [
            ['nombre' => 'Entero',            'emoji' => '🥩', 'orden' => 1,  'desc' => 'El producto sin cortar'],
            ['nombre' => 'Mariposa',          'emoji' => '🦋', 'orden' => 2,  'desc' => 'Mínimo 100gr por corte'],
            ['nombre' => 'Medallones',        'emoji' => '⚪', 'orden' => 3,  'desc' => 'Mínimo 100gr por corte'],
            ['nombre' => 'Sin cordón',        'emoji' => '✂️', 'orden' => 4,  'desc' => 'Retira el cordón de grasa'],
            ['nombre' => 'Goulash',           'emoji' => '🔶', 'orden' => 5,  'desc' => 'Cubos pequeños para guiso'],
            ['nombre' => 'En cuadros',        'emoji' => '🟫', 'orden' => 6,  'desc' => 'Cubos medianos'],
            ['nombre' => 'Molida',            'emoji' => '🍖', 'orden' => 7,  'desc' => 'Carne molida'],
            ['nombre' => 'Corte argentino',   'emoji' => '🇦🇷', 'orden' => 8,  'desc' => 'Corte grueso estilo asado'],
            ['nombre' => 'Churrasco',         'emoji' => '🔥', 'orden' => 9,  'desc' => 'Filetes delgados'],
            ['nombre' => 'Troncos',           'emoji' => '🪵', 'orden' => 10, 'desc' => 'Piezas gruesas'],
            ['nombre' => 'En tiras',          'emoji' => '📏', 'orden' => 11, 'desc' => 'Tiras delgadas'],
            ['nombre' => 'Para barril',       'emoji' => '🛢️', 'orden' => 12, 'desc' => 'Corte para chicharrón'],
            ['nombre' => 'Porcionado a hueso','emoji' => '🦴', 'orden' => 13, 'desc' => 'Cortado por hueso'],
            ['nombre' => 'Picado en sierra',  'emoji' => '🪚', 'orden' => 14, 'desc' => 'Corte con sierra'],
            ['nombre' => 'Picado en cuadros', 'emoji' => '🟦', 'orden' => 15, 'desc' => 'Cubos pequeños'],
            ['nombre' => 'En tajadas',        'emoji' => '🍽️', 'orden' => 16, 'desc' => 'Tajadas (chuletas)'],
        ];

        $tenants = $this->option('tenant')
            ? Tenant::withoutGlobalScopes()->where('id', $this->option('tenant'))->get()
            : Tenant::withoutGlobalScopes()->where('activo', true)->get();

        $total = 0;
        foreach ($tenants as $t) {
            $this->info("━━━ {$t->nombre} (id={$t->id}) ━━━");
            $tm->set($t);

            foreach ($cortes as $c) {
                $existe = Corte::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)
                    ->where('nombre', $c['nombre'])
                    ->exists();

                if ($existe) {
                    $this->line("  • {$c['nombre']} ya existe, saltando.");
                    continue;
                }
                Corte::create([
                    'tenant_id'   => $t->id,
                    'nombre'      => $c['nombre'],
                    'descripcion' => $c['desc'],
                    'icono_emoji' => $c['emoji'],
                    'orden'       => $c['orden'],
                    'activo'      => true,
                ]);
                $this->info("  ✓ {$c['emoji']} {$c['nombre']}");
                $total++;
            }
        }

        $tm->set(null);
        $this->info("✅ {$total} cortes creados.");
        return self::SUCCESS;
    }
}
