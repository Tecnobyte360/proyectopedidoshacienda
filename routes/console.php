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

// 🧹 Limpieza diaria del historial WhatsApp para evitar bloat que confunda al bot
// Hora y parámetros se leen de ConfiguracionBot (UI: /configuracion-bot → Mantenimiento)
Schedule::command('bot:limpiar-historial')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->when(function () {
        try {
            $config = ConfiguracionBot::actual();
            if (!$config?->auto_limpieza_activa) return false;
            $hora = trim($config->auto_limpieza_hora ?: '03:30');
            return now('America/Bogota')->format('H:i') === $hora;
        } catch (\Throwable $e) {
            return false;
        }
    })
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

// 🔍 Auditoría diaria del bot — corre cada noche 23:55
Schedule::command('bot:auditoria-diaria')
    ->dailyAt('23:55')
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->runInBackground();

// 🧹 Liberar conversaciones huérfanas (modo humano sin atención >2h)
// Evita que clientes queden colgados sin respuesta del bot ni del equipo.
Schedule::command('bot:liberar-conversaciones-huerfanas --horas=2')
    ->everyThirtyMinutes()
    ->timezone('America/Bogota')
    ->withoutOverlapping()
    ->runInBackground();

// 🐕 Watchdog — rescata conversaciones donde el bot dijo "un momento" y no
// continuó la respuesta (ej. Anthropic falló, tool_use huérfano).
// Corre cada minuto, busca convs con últimos 20s-10min de espera del bot
// y dispara un retry virtual para forzar al bot a responder.
Schedule::command('bot:watchdog-estancadas')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->withoutOverlapping(5)
    ->runInBackground();

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
