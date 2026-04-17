<?php

namespace Database\Seeders;

use App\Models\AnsTiempoPedido;
use Illuminate\Database\Seeder;

class AnsTiemposPedidoSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'estado'           => 'nuevo',
                'nombre'           => 'Atención inicial',
                'descripcion'      => 'Tiempo desde que entra el pedido hasta que se inicia la preparación.',
                'minutos_objetivo' => 3,
                'minutos_alerta'   => 5,
                'minutos_critico'  => 8,
                'orden'            => 1,
            ],
            [
                'estado'           => 'en_preparacion',
                'nombre'           => 'Preparación',
                'descripcion'      => 'Tiempo de cocina hasta que se despacha el pedido al domiciliario.',
                'minutos_objetivo' => 15,
                'minutos_alerta'   => 22,
                'minutos_critico'  => 30,
                'orden'            => 2,
            ],
            [
                'estado'           => 'repartidor_en_camino',
                'nombre'           => 'Entrega en ruta',
                'descripcion'      => 'Tiempo desde que sale el domiciliario hasta que se confirma la entrega.',
                'minutos_objetivo' => 20,
                'minutos_alerta'   => 30,
                'minutos_critico'  => 45,
                'orden'            => 3,
            ],
        ];

        foreach ($defaults as $row) {
            AnsTiempoPedido::updateOrCreate(
                ['estado' => $row['estado']],
                $row + ['activo' => true]
            );
        }
    }
}
