<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SedeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
 public function run(): void
    {
        // Limpia sedes anteriores (opcional)
        Sede::truncate();

        Sede::create([
            'nombre'        => 'Bello',
            'direccion'     => 'Bello – Cra 45 #20-30',
            'hora_apertura' => '07:20:00',
            'hora_cierre'   => '17:00:00',
            'activa'        => true,
        ]);

        Sede::create([
            'nombre'        => 'La Estrella',
            'direccion'     => 'La Estrella – Calle 80 #34-10',
            'hora_apertura' => '08:00:00',
            'hora_cierre'   => '17:00:00',
            'activa'        => true,
        ]);

        Sede::create([
            'nombre'        => 'Sabaneta',
            'direccion'     => 'Sabaneta – Av. Principal #22-55',
            'hora_apertura' => '08:00:00',
            'hora_cierre'   => '17:00:00',
            'activa'        => true,
        ]);
    }
}
