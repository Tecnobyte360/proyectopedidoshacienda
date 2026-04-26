<?php

use App\Models\ConfiguracionBot;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| SCHEDULER
|--------------------------------------------------------------------------
*/

// 🎂 Felicitaciones de cumpleaños
// Evaluamos cada minuto y comparamos HH:MM exacto con la hora configurada
// en ConfiguracionBot. Así el admin puede elegir cualquier hora (incluso :22).
// 🚫 Auto-suspender tenants con suscripción vencida (corre 1 vez al día a las 03:00)
Schedule::command('tenants:suspender-vencidos')
    ->dailyAt('03:00')
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->runInBackground();

// 📨 Procesar campañas WhatsApp en lotes con throttle anti-baneo
Schedule::command('campanas:procesar')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->withoutOverlapping(10)
    ->runInBackground();

// La cola la procesa el contenedor pedidos_hacienda_queue (queue:work
// permanente). No duplicar aquí — los jobs los maneja ese worker.

Schedule::command('clientes:felicitar-cumpleanos')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->when(function () {
        try {
            $config = ConfiguracionBot::actual();
            if (!$config->cumpleanos_activo) return false;

            $horaConfig = trim($config->cumpleanos_hora ?: '09:00');
            $horaActual = now('America/Bogota')->format('H:i');

            // Comparación exacta HH:MM (ambos formatos iguales)
            return $horaActual === $horaConfig;
        } catch (\Throwable $e) {
            return false;
        }
    })
    ->withoutOverlapping()
    ->runInBackground();
