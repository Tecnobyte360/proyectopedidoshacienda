<?php

namespace App\Console\Commands;

use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 🐕 WATCHDOG — Rescata conversaciones donde el bot dijo "un momento, verificando…"
 *           o frases similares pero NO siguió la respuesta.
 *
 * Estrategia:
 *   1. Buscar conversaciones donde el último mensaje del bot (rol=assistant) contenga
 *      frases de espera ("un momento", "verificando", "déjame revisar"…)
 *   2. Que ese mensaje tenga >=20 segundos y <=10 minutos sin nueva respuesta del bot.
 *   3. Inyectar un mensaje virtual del usuario tipo "¿sigues ahí?" para forzar al bot
 *      a continuar con los datos que ya tiene en el estado_pedido.
 *
 * Se programa cada 30 segundos en Console/Kernel.php.
 */
class WatchdogConversacionesEstancadas extends Command
{
    protected $signature = 'bot:watchdog-estancadas';
    protected $description = 'Rescata conversaciones donde el bot prometió respuesta y no continuó';

    private const FRASES_ESPERA_REGEX = '/\b(?:un\s+momento|d[eé]jame\s+(?:verificar|revisar|consultar)|verificando|consultando|revisando|ya\s+(?:te|le)\s+(?:respondo|aviso|consulto)|enseguida\s+(?:te|le)|en\s+un\s+momento)\b/iu';

    public function handle(): int
    {
        // Recolectar todas las conversaciones donde el ÚLTIMO mensaje sea del usuario
        // y haya pasado >=15s sin respuesta del bot (en las últimas 24h).
        // Eso cubre dos casos:
        //   A) Bot dijo "un momento" y no siguió (típico).
        //   B) Bot pidió "¿Confirmas?", cliente respondió, y por un error el bot no respondió.
        //   C) Cualquier otro caso donde el cliente envió mensaje y el bot quedó mudo.
        $candidatas = ConversacionWhatsapp::query()
            ->where('updated_at', '>=', now()->subDay())
            ->whereHas('mensajes', function ($q) {
                $q->where('rol', MensajeWhatsapp::ROL_USER)
                  ->where('created_at', '<=', now()->subSeconds(15))
                  ->where('created_at', '>=', now()->subHours(2));
            })
            ->limit(30)
            ->get();

        $rescatadas = 0;
        foreach ($candidatas as $conv) {
            $ultimoMsg = $conv->mensajes()
                ->orderByDesc('id')
                ->first();

            if (!$ultimoMsg) continue;
            // Solo rescatar si el último mensaje fue del USUARIO (no del bot).
            if ($ultimoMsg->rol !== MensajeWhatsapp::ROL_USER) continue;

            $segundosDesde = abs((int) now()->diffInSeconds($ultimoMsg->created_at));
            if ($segundosDesde < 15 || $segundosDesde > 7200) continue; // hasta 2h

            // Excepción: si es un mensaje watchdog previo, no entrar en loop.
            if (str_starts_with((string) ($ultimoMsg->mensaje_externo_id ?? ''), 'watchdog_')) continue;

            // Evitar rescatar la misma conversación múltiples veces seguidas
            $yaRescatadaKey = "watchdog_rescate_conv_{$conv->id}_msg_{$ultimoMsg->id}";
            if (\Cache::has($yaRescatadaKey)) continue;
            \Cache::put($yaRescatadaKey, true, now()->addMinutes(5));

            Log::info('🐕 Watchdog: rescatando conversación estancada', [
                'conversacion_id'  => $conv->id,
                'telefono'         => $conv->telefono_normalizado,
                'segundos_desde'   => $segundosDesde,
                'ultimo_mensaje'   => mb_substr((string) $ultimoMsg->contenido, 0, 100),
                'mensaje_id'       => $ultimoMsg->id,
            ]);

            try {
                // Re-enviar al webhook el ÚLTIMO mensaje del usuario para que el bot
                // lo procese (ahora que el código ya está arreglado o que las condiciones
                // que causaron el silencio ya pasaron).
                $payload = [
                    'usuario'  => ['id' => 0, 'name' => 'Watchdog', 'email' => ''],
                    'conexion' => ['id' => $conv->connection_id ?? 0, 'name' => 'WATCHDOG', 'status' => 'CONNECTED'],
                    'chat'     => [
                        'id'             => $conv->id,
                        'name'           => $conv->cliente?->nombre ?? 'Cliente',
                        'phone'          => $conv->telefono_normalizado,
                        'status'         => 'open',
                        'isGroup'        => false,
                        'unreadMessages' => 1,
                    ],
                    'mensaje'  => [
                        'id'        => 'watchdog_retry_' . $ultimoMsg->id,
                        'body'      => (string) $ultimoMsg->contenido,
                        'fromMe'    => false,
                        'read'      => false,
                        'mediaType' => 'chat',
                        'createdAt' => now()->toIso8601String(),
                    ],
                ];

                $url = config('app.url') . '/api/whatsapp-webhook';
                \Http::timeout(5)->post($url, $payload);

                $rescatadas++;
            } catch (\Throwable $e) {
                Log::warning('🐕 Watchdog: error rescatando', [
                    'conversacion_id' => $conv->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        if ($rescatadas > 0) {
            $this->info("🐕 Watchdog: {$rescatadas} conversación(es) rescatada(s)");
        }

        return self::SUCCESS;
    }
}
