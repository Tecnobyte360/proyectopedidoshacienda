<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanesSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [
                'codigo'              => 'basico',
                'nombre'              => 'Plan Básico',
                'descripcion'         => 'Ideal para emprendimientos que están empezando.',
                'precio_mensual'      => 99000,
                'precio_anual'        => 990000,
                'moneda'              => 'COP',
                'max_pedidos_mes'     => 200,
                'max_usuarios'        => 3,
                'max_sedes'           => 1,
                'max_productos'       => 50,
                'max_clientes'        => 500,
                'feature_whatsapp'    => true,
                'feature_ia'          => true,
                'feature_reportes'    => false,
                'feature_multi_sede'  => false,
                'feature_api'         => false,
                'orden'               => 1,
                'caracteristicas_extra' => [
                    '1 sede',
                    'Hasta 200 pedidos / mes',
                    'Bot WhatsApp básico',
                    'Soporte por email',
                ],
            ],
            [
                'codigo'              => 'pro',
                'nombre'              => 'Plan Pro',
                'descripcion'         => 'Para negocios en crecimiento que necesitan más músculo.',
                'precio_mensual'      => 249000,
                'precio_anual'        => 2490000,
                'moneda'              => 'COP',
                'max_pedidos_mes'     => 1500,
                'max_usuarios'        => 10,
                'max_sedes'           => 3,
                'max_productos'       => 300,
                'max_clientes'        => 5000,
                'feature_whatsapp'    => true,
                'feature_ia'          => true,
                'feature_reportes'    => true,
                'feature_multi_sede'  => true,
                'feature_api'         => false,
                'orden'               => 2,
                'caracteristicas_extra' => [
                    'Hasta 3 sedes',
                    'Hasta 1.500 pedidos / mes',
                    'Bot WhatsApp avanzado',
                    'Reportes y métricas',
                    'Despacho masivo por zonas',
                    'Soporte por WhatsApp',
                ],
            ],
            [
                'codigo'              => 'empresa',
                'nombre'              => 'Plan Empresa',
                'descripcion'         => 'Sin límites, todo incluido. Para operaciones grandes.',
                'precio_mensual'      => 599000,
                'precio_anual'        => 5990000,
                'moneda'              => 'COP',
                'max_pedidos_mes'     => null,   // ilimitado
                'max_usuarios'        => null,
                'max_sedes'           => null,
                'max_productos'       => null,
                'max_clientes'        => null,
                'feature_whatsapp'    => true,
                'feature_ia'          => true,
                'feature_reportes'    => true,
                'feature_multi_sede'  => true,
                'feature_api'         => true,
                'orden'               => 3,
                'caracteristicas_extra' => [
                    'Sedes ilimitadas',
                    'Pedidos ilimitados',
                    'Bot IA premium (GPT-4)',
                    'API REST completa',
                    'Branding 100% personalizado',
                    'Soporte 24/7 con SLA',
                ],
            ],
        ];

        foreach ($planes as $p) {
            Plan::updateOrCreate(['codigo' => $p['codigo']], $p);
        }

        $this->command->info('✓ ' . count($planes) . ' planes cargados.');
    }
}
