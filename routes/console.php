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
// Corre cada hora. El comando internamente chequea si estamos en la hora
// configurada en ConfiguracionBot (cumpleanos_hora, ej: "09:00").
// Así si el admin cambia la hora, no hay que redeployar.
Schedule::command('clientes:felicitar-cumpleanos')
    ->hourly()
    ->timezone('America/Bogota')
    ->when(function () {
        try {
            $config = ConfiguracionBot::actual();
            if (!$config->cumpleanos_activo) return false;

            $horaConfig = $config->cumpleanos_hora ?: '09:00';
            $horaActual = now('America/Bogota')->format('H:i');

            // La hora configurada es HH:MM, comparamos solo con la hora (HH)
            // porque ->hourly() corre al minuto 00 de cada hora.
            return substr($horaActual, 0, 2) === substr($horaConfig, 0, 2);
        } catch (\Throwable $e) {
            return false;
        }
    })
    ->withoutOverlapping()
    ->runInBackground();
