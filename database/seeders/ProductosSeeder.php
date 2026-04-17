<?php

namespace Database\Seeders;

use App\Models\Producto;
use App\Models\ProductoCategoria;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class ProductosSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Carnes de res',     'icono_emoji' => '🥩', 'color' => '#b91c1c', 'orden' => 1],
            ['nombre' => 'Pollo',             'icono_emoji' => '🍗', 'color' => '#ca8a04', 'orden' => 2],
            ['nombre' => 'Cerdo',             'icono_emoji' => '🥓', 'color' => '#ec4899', 'orden' => 3],
            ['nombre' => 'Embutidos',         'icono_emoji' => '🌭', 'color' => '#f97316', 'orden' => 4],
            ['nombre' => 'Combos y promos',   'icono_emoji' => '🎁', 'color' => '#16a34a', 'orden' => 5],
        ];

        $catModels = [];

        foreach ($categorias as $cat) {
            $catModels[$cat['nombre']] = ProductoCategoria::firstOrCreate(
                ['nombre' => $cat['nombre']],
                $cat
            );
        }

        $productos = [
            ['categoria' => 'Carnes de res', 'codigo' => 'RES-LOM', 'nombre' => 'Lomo de res',          'unidad' => 'libra', 'precio_base' => 22000, 'palabras_clave' => ['lomo','res','filete']],
            ['categoria' => 'Carnes de res', 'codigo' => 'RES-MOL', 'nombre' => 'Carne molida especial','unidad' => 'libra', 'precio_base' => 18000, 'palabras_clave' => ['molida','res','hamburguesa'], 'destacado' => true],
            ['categoria' => 'Carnes de res', 'codigo' => 'RES-CHU', 'nombre' => 'Chuleta de res',       'unidad' => 'libra', 'precio_base' => 20000, 'palabras_clave' => ['chuleta','res']],
            ['categoria' => 'Pollo',         'codigo' => 'POL-PEC', 'nombre' => 'Pechuga deshuesada',   'unidad' => 'libra', 'precio_base' => 14500, 'palabras_clave' => ['pollo','pechuga','deshuesada'], 'destacado' => true],
            ['categoria' => 'Pollo',         'codigo' => 'POL-MUS', 'nombre' => 'Muslos de pollo',      'unidad' => 'libra', 'precio_base' => 9800,  'palabras_clave' => ['pollo','muslo']],
            ['categoria' => 'Pollo',         'codigo' => 'POL-ENT', 'nombre' => 'Pollo entero',         'unidad' => 'unidad','precio_base' => 28000, 'palabras_clave' => ['pollo','entero']],
            ['categoria' => 'Cerdo',         'codigo' => 'CER-COS', 'nombre' => 'Costillas de cerdo',   'unidad' => 'libra', 'precio_base' => 16000, 'palabras_clave' => ['cerdo','costilla']],
            ['categoria' => 'Cerdo',         'codigo' => 'CER-TOC', 'nombre' => 'Tocineta ahumada',     'unidad' => 'libra', 'precio_base' => 22000, 'palabras_clave' => ['cerdo','tocineta','bacon']],
            ['categoria' => 'Embutidos',     'codigo' => 'EMB-CHO', 'nombre' => 'Chorizo santarrosano', 'unidad' => 'paquete','precio_base' => 12500, 'palabras_clave' => ['chorizo','embutido']],
            ['categoria' => 'Embutidos',     'codigo' => 'EMB-SAL', 'nombre' => 'Salchichas premium',   'unidad' => 'paquete','precio_base' => 11000, 'palabras_clave' => ['salchicha','embutido']],
        ];

        $sedes = Sede::all();

        foreach ($productos as $p) {
            $producto = Producto::firstOrCreate(
                ['codigo' => $p['codigo']],
                [
                    'categoria_id'    => $catModels[$p['categoria']]->id,
                    'nombre'          => $p['nombre'],
                    'unidad'          => $p['unidad'],
                    'precio_base'     => $p['precio_base'],
                    'palabras_clave'  => $p['palabras_clave'] ?? [],
                    'destacado'       => $p['destacado'] ?? false,
                    'activo'          => true,
                ]
            );

            // Asociar a todas las sedes con precio base (puede personalizarse luego)
            foreach ($sedes as $sede) {
                $producto->sedes()->syncWithoutDetaching([
                    $sede->id => ['precio' => null, 'disponible' => true],
                ]);
            }
        }
    }
}
