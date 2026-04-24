<?php

namespace App\Console\Commands;

use App\Models\Departamento;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Crea un set de departamentos tipicos si aun no existen para cada tenant activo.
 * Se puede correr cuando se crea un tenant nuevo o on-demand para rellenar.
 */
class SeedDepartamentosDefault extends Command
{
    protected $signature = 'departamentos:seed {--tenant= : ID específico de tenant (si se omite, todos los activos)}';

    protected $description = 'Crea departamentos típicos (SAC, Comercial, RH, Logística, Facturación) para cada tenant activo.';

    public function handle(TenantManager $tm): int
    {
        $defaults = [
            [
                'nombre'            => 'Servicio al Cliente',
                'icono_emoji'       => '🎧',
                'color'             => '#6366f1',
                'orden'             => 1,
                'saludo_automatico' => '¡Hola! 🙌 Un asesor de *Servicio al Cliente* te atenderá en un momento para resolver lo que necesitas.',
                'keywords'          => ['reclamo', 'queja', 'pqr', 'devolución', 'servicio al cliente', 'no llegó', 'mal estado', 'dañado'],
            ],
            [
                'nombre'            => 'Comercial',
                'icono_emoji'       => '💼',
                'color'             => '#10b981',
                'orden'             => 2,
                'saludo_automatico' => '¡Hola! 💼 Un asesor del *área Comercial* te va a atender enseguida para cotizar lo que necesitas.',
                'keywords'          => ['cotización', 'cotizar', 'precio mayorista', 'empresa', 'negocio', 'descuento por volumen', 'corporativo', 'factura empresa', 'b2b'],
            ],
            [
                'nombre'            => 'Recursos Humanos',
                'icono_emoji'       => '👥',
                'color'             => '#f59e0b',
                'orden'             => 3,
                'saludo_automatico' => '¡Hola! 👥 Un asesor de *Recursos Humanos* te responderá en breve.',
                'keywords'          => ['hoja de vida', 'trabajar con ustedes', 'vacante', 'empleo', 'aplicar', 'cv', 'curriculum', 'rrhh', 'recursos humanos'],
            ],
            [
                'nombre'            => 'Logística',
                'icono_emoji'       => '🚚',
                'color'             => '#0ea5e9',
                'orden'             => 4,
                'saludo_automatico' => '¡Hola! 🚚 Un asesor de *Logística* te atenderá con tu consulta de envío.',
                'keywords'          => ['envío', 'rastrear', 'guía', 'domicilio', 'entrega', 'dónde está mi pedido', 'tiempo de entrega'],
            ],
            [
                'nombre'            => 'Facturación',
                'icono_emoji'       => '🧾',
                'color'             => '#8b5cf6',
                'orden'             => 5,
                'saludo_automatico' => '¡Hola! 🧾 Un asesor de *Facturación* te atenderá ahora mismo.',
                'keywords'          => ['factura', 'pagar', 'cobro doble', 'cobro duplicado', 'pago', 'cuenta de cobro', 'retención', 'iva'],
            ],
        ];

        $tenants = $this->option('tenant')
            ? Tenant::withoutGlobalScopes()->where('id', $this->option('tenant'))->get()
            : Tenant::withoutGlobalScopes()->where('activo', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants a procesar.');
            return self::SUCCESS;
        }

        $totalCreados = 0;
        foreach ($tenants as $t) {
            $this->info("━━━ {$t->nombre} (id={$t->id}) ━━━");
            $tm->set($t);

            foreach ($defaults as $dep) {
                $existe = Departamento::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)
                    ->where('nombre', $dep['nombre'])
                    ->exists();

                if ($existe) {
                    $this->line("  • {$dep['nombre']} ya existe, saltando.");
                    continue;
                }

                Departamento::create([
                    'tenant_id'          => $t->id,
                    'nombre'             => $dep['nombre'],
                    'icono_emoji'        => $dep['icono_emoji'],
                    'color'              => $dep['color'],
                    'orden'              => $dep['orden'],
                    'saludo_automatico'  => $dep['saludo_automatico'],
                    'keywords'           => $dep['keywords'],
                    'notificar_internos' => true,
                    'activo'             => true,
                ]);
                $this->info("  ✓ {$dep['icono_emoji']} {$dep['nombre']} creado");
                $totalCreados++;
            }
        }

        $tm->set(null);
        $this->info("✅ Total creados: {$totalCreados}");
        return self::SUCCESS;
    }
}
