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
        $candidatas = ConversacionWhatsapp::query()
            ->whereDoesntHave('mensajes', function ($q) {
                // Excluir si ya hay un mensaje del bot DESPUÉS del último mensaje de espera
                $q->where('rol', MensajeWhatsapp::ROL_ASSISTANT)
                  ->where('created_at', '>=', now()->subMinutes(10));
            }, '>=', 2)
            ->whereHas('mensajes', function ($q) {
                $q->where('rol', MensajeWhatsapp::ROL_ASSISTANT)
                  ->where('created_at', '<=', now()->subSeconds(20))
                  ->where('created_at', '>=', now()->subMinutes(10))
                  ->where('contenido', 'REGEXP', '(un momento|verificando|d[eé]jame|consultando|revisando|enseguida|ya te respondo)');
            })
            ->limit(20)
            ->get();

        $rescatadas = 0;
        foreach ($candidatas as $conv) {
            $ultimoMsg = $conv->mensajes()
                ->orderByDesc('id')
                ->first();

            if (!$ultimoMsg || $ultimoMsg->rol !== MensajeWhatsapp::ROL_ASSISTANT) continue;
            if (!preg_match(self::FRASES_ESPERA_REGEX, (string) $ultimoMsg->contenido)) continue;

            $segundosDesde = now()->diffInSeconds($ultimoMsg->created_at);
            if ($segundosDesde < 20 || $segundosDesde > 600) continue;

            // Evitar rescatar la misma conversación múltiples veces seguidas
            $yaRescatadaKey = "watchdog_rescate_conv_{$conv->id}";
            if (\Cache::has($yaRescatadaKey)) continue;
            \Cache::put($yaRescatadaKey, true, now()->addMinutes(3));

            Log::info('🐕 Watchdog: rescatando conversación estancada', [
                'conversacion_id'  => $conv->id,
                'telefono'         => $conv->telefono_normalizado,
                'segundos_desde'   => $segundosDesde,
                'ultima_frase'     => mb_substr((string) $ultimoMsg->contenido, 0, 100),
            ]);

            try {
                // Inyectar mensaje virtual del usuario para forzar continuación.
                // Lo marcamos con meta para diagnosticar después.
                MensajeWhatsapp::create([
                    'conversacion_id'    => $conv->id,
                    'rol'                => MensajeWhatsapp::ROL_USER,
                    'tipo'               => 'text',
                    'contenido'          => '¿Sigues ahí? Por favor continúa con mi pedido.',
                    'meta'               => ['watchdog' => true, 'segundos_estancada' => $segundosDesde],
                    'mensaje_externo_id' => 'watchdog_' . now()->timestamp,
                ]);

                // Disparar webhook simulado para que el flujo normal procese el mensaje
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
                        'id'        => 'watchdog_' . now()->timestamp,
                        'body'      => '¿Sigues ahí? Por favor continúa con mi pedido.',
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
