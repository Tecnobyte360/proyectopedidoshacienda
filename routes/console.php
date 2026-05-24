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
// 🚫 Gestión de morosidad SaaS — corre CADA MINUTO y se dispara en las horas
// configuradas en /admin/configuracion-plataforma → "Horarios de envío".
// Permite N envíos al día (ej. 09:00, 14:00, 18:00) para insistirle al tenant moroso.
$leerHorasSaas = function (): array {
    try {
        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        $horas = $cfg->saas_horas_envio ?? null;
        if (!is_array($horas) || empty($horas)) return ['10:00'];

        // Detectar formato: por día o flat
        $primerKey = array_keys($horas)[0];
        $dias = ['lun','mar','mie','jue','vie','sab','dom'];
        if (is_string($primerKey) && in_array($primerKey, $dias, true)) {
            // Estructura por día → obtener horarios del día actual
            $diaMap = [
                'Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mie', 'Thu' => 'jue',
                'Fri' => 'vie', 'Sat' => 'sab', 'Sun' => 'dom',
            ];
            $diaActual = $diaMap[now('America/Bogota')->format('D')] ?? 'lun';
            return array_map('trim', $horas[$diaActual] ?? []);
        }
        // Formato flat (legacy)
        return array_map('trim', $horas);
    } catch (\Throwable $e) {
        return [];
    }
};

Schedule::command('tenants:suspender-vencidos --enviar')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->when(function () use ($leerHorasSaas) {
        $ahora = now('America/Bogota')->format('H:i');
        return in_array($ahora, $leerHorasSaas(), true);
    })
    ->withoutOverlapping()
    ->runInBackground();

// 📅 Generar facturas mensuales SaaS — mismas horas que morosidad
Schedule::command('saas:generar-facturas-mensuales --enviar')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->when(function () use ($leerHorasSaas) {
        $ahora = now('America/Bogota')->format('H:i');
        return in_array($ahora, $leerHorasSaas(), true);
    })
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

// 🔁 Auto-reconectar WhatsApp — cada minuto. Si la conexión cae a PAIRING o
// DISCONNECTED, intentamos POST/PUT a /whatsappsession/{id} para regenerar
// sesión. Throttle interno de 1 min por conexión evita martillar la API.
// Tras 3 fallos consecutivos → email al admin del tenant.
Schedule::command('bot:auto-reconectar-whatsapp')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->withoutOverlapping(2)
    ->runInBackground();

// 📬 Reintentar mensajes salientes que fallaron por desconexión.
// Backoff progresivo (15s, 30s, 1m, 2m, 5m, 15m, 1h, 6h). 12 intentos máx.
Schedule::command('bot:reintentar-mensajes-salida')
    ->everyMinute()
    ->timezone('America/Bogota')
    ->withoutOverlapping(2)
    ->runInBackground();

// 🔄 Reintentar pedidos al ERP/SGI que fallaron por errores transitorios.
// (DBPROCESS is dead, connection timeout, deadlock). Cada 5 minutos.
Schedule::command('integraciones:reintentar-export')
    ->everyFiveMinutes()
    ->timezone('America/Bogota')
    ->withoutOverlapping(10)
    ->runInBackground();

// 🔄 ErpRetryQueue — clientes/pedidos encolados cuando el ERP estaba caído.
// Cada 5 min los reintenta con backoff exponencial hasta éxito o max 20 intentos.
// Reemplaza el flujo viejo de "ERP cayó → pedido perdido" por
// "ERP cayó → pedido guardado local → sincroniza después".
Schedule::command('erp:procesar-cola')
    ->everyFiveMinutes()
    ->timezone('America/Bogota')
    ->withoutOverlapping(10)
    ->runInBackground();

// 🚑 ConversationRescueAgent — detecta conversaciones donde el bot quedó
// atorado en error repetido (ej. ERP caído, API timeout) y rescata al
// cliente activando modo humano + notificando al operador.
Schedule::command('bot:detectar-atorados')
    ->everyTwoMinutes()
    ->timezone('America/Bogota')
    ->withoutOverlapping(2)
    ->runInBackground();

// 🛵 Auto-asignar domiciliario a pedidos a domicilio huerfanos (sin asignar).
// Red de seguridad si el hook 'created' de Pedido no disparó. Cada 2 minutos.
Schedule::command('pedidos:asignar-huerfanos')
    ->everyTwoMinutes()
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
