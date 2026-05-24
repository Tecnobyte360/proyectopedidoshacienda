<?php

namespace App\Console\Commands;

use App\Models\MetaWhatsappDisparador;
use App\Models\MetaWhatsappPlantilla;
use Illuminate\Console\Command;

/**
 * Crea los disparadores base que conectan eventos del sistema con plantillas
 * de Meta. Cuando un pedido cambia de estado, el sistema busca el disparador
 * por evento y envía la plantilla correspondiente con sus variables llenas.
 *
 * Uso:
 *   php artisan meta:crear-disparadores-base {tenant_id}
 *
 * Requiere que las plantillas ya existan (creadas con
 * meta:crear-plantillas-base). No requiere que estén aprobadas — el
 * disparador queda configurado y empieza a funcionar apenas Meta apruebe.
 */
class MetaCrearDisparadoresBase extends Command
{
    protected $signature = 'meta:crear-disparadores-base {tenant_id}';
    protected $description = 'Crea los disparadores base (evento → plantilla) para el tenant';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');

        $disparadores = $this->disparadores();
        $this->info("Configurando " . count($disparadores) . " disparadores para tenant_id={$tenantId}");

        $resumen = ['ok' => 0, 'omitido' => 0];

        foreach ($disparadores as $d) {
            $tpl = MetaWhatsappPlantilla::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('nombre', $d['plantilla'])
                ->where('idioma', $d['idioma'])
                ->first();

            if (!$tpl) {
                $this->warn("  ⚠ omitido evento='{$d['evento']}' — plantilla '{$d['plantilla']}' no existe localmente");
                $resumen['omitido']++;
                continue;
            }

            MetaWhatsappDisparador::updateOrCreate(
                ['tenant_id' => $tenantId, 'evento' => $d['evento']],
                [
                    'plantilla_id'  => $tpl->id,
                    'variables_map' => $d['variables_map'],
                    'activo'        => true,
                    'descripcion'   => $d['descripcion'],
                ]
            );
            $this->info("  ✓ {$d['evento']} → {$tpl->nombre}");
            $resumen['ok']++;
        }

        $this->newLine();
        $this->info("RESUMEN: ✓ {$resumen['ok']} configurados | ⚠ {$resumen['omitido']} omitidos");
        $this->info("Los disparadores se activarán automáticamente cuando un pedido cambie de estado.");

        return 0;
    }

    private function disparadores(): array
    {
        return [
            [
                'evento'        => 'pedido_confirmado',
                'plantilla'     => 'pedido_confirmado',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'numero', '3' => 'total'],
                'descripcion'   => 'Cliente recibe confirmación al crearse el pedido',
            ],
            [
                'evento'        => 'pedido_en_proceso',
                'plantilla'     => 'pedido_en_proceso',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'numero'],
                'descripcion'   => 'Cliente recibe aviso cuando entra a preparación',
            ],
            [
                'evento'        => 'pedido_en_camino',
                'plantilla'     => 'pedido_en_camino',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'numero', '3' => 'domiciliario', '4' => 'eta_minutos'],
                'descripcion'   => 'Cliente recibe aviso cuando el domiciliario sale',
            ],
            [
                'evento'        => 'pedido_entregado',
                'plantilla'     => 'pedido_entregado',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'numero'],
                'descripcion'   => 'Cliente recibe confirmación de entrega',
            ],
            [
                'evento'        => 'pedido_cancelado',
                'plantilla'     => 'pedido_cancelado',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'numero', '3' => 'observacion'],
                'descripcion'   => 'Cliente recibe aviso de cancelación',
            ],
            [
                'evento'        => 'bienvenida',
                'plantilla'     => 'bienvenida_cliente',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'negocio'],
                'descripcion'   => 'Primer contacto con cliente nuevo',
            ],
            [
                'evento'        => 'encuesta_entrega',
                'plantilla'     => 'encuesta_post_entrega',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'numero'],
                'descripcion'   => 'Encuesta enviada después de entregar',
            ],
            [
                'evento'        => 'recordatorio_pago',
                'plantilla'     => 'recordatorio_pago',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'numero', '3' => 'total'],
                'descripcion'   => 'Recordatorio cuando hay pago pendiente',
            ],
            [
                'evento'        => 'cumpleanos',
                'plantilla'     => 'felicitacion_cumpleanos',
                'idioma'        => 'es',
                'variables_map' => ['1' => 'nombre', '2' => 'beneficio', '3' => 'vigencia'],
                'descripcion'   => 'Felicitación automática en cumpleaños',
            ],
        ];
    }
}
