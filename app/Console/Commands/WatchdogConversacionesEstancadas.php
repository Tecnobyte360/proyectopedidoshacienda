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
        // 🎛️ Configuración desde BD — editable en /configuracion-bot
        $cfgGlobal = \App\Models\ConfiguracionBot::actual();
        $activo    = (bool) ($cfgGlobal->watchdog_activo ?? true);
        if (!$activo) {
            Log::info('🐕 Watchdog: desactivado por configuración');
            return self::SUCCESS;
        }

        $minSegs     = max(10, min(300, (int) ($cfgGlobal->watchdog_min_segundos ?? 30)));
        $maxMins     = max(1,  min(120, (int) ($cfgGlobal->watchdog_max_minutos ?? 5)));
        $skipPedMin  = max(0,  min(180, (int) ($cfgGlobal->watchdog_skip_pedido_min ?? 30)));
        $cooldownMin = max(1,  min(180, (int) ($cfgGlobal->watchdog_cooldown_conv_min ?? 30)));

        // Ventana configurable. Mensajes >X min YA NO se rescatan (evita spam).
        $candidatas = ConversacionWhatsapp::query()
            ->where('updated_at', '>=', now()->subMinutes($maxMins + 5))
            ->where('updated_at', '<=', now()->subSeconds(25))
            ->whereHas('mensajes', function ($q) use ($minSegs, $maxMins) {
                $q->where('rol', MensajeWhatsapp::ROL_USER)
                  ->where('created_at', '<=', now()->subSeconds($minSegs))
                  ->where('created_at', '>=', now()->subMinutes($maxMins));
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
            $maxSegs       = $maxMins * 60;
            if ($segundosDesde < $minSegs || $segundosDesde > $maxSegs) continue;

            // Excepción: si es un mensaje watchdog previo, no entrar en loop.
            if (str_starts_with((string) ($ultimoMsg->mensaje_externo_id ?? ''), 'watchdog_')) continue;

            // 🛡️ NO rescatar si la conv YA generó un pedido reciente (configurable).
            $tienePedidoReciente = $skipPedMin > 0 && \App\Models\Pedido::where('telefono_whatsapp', $conv->telefono_normalizado)
                ->where('created_at', '>=', now()->subMinutes($skipPedMin))
                ->whereNotIn('estado', [\App\Models\Pedido::ESTADO_CANCELADO])
                ->exists();
            if ($tienePedidoReciente) {
                Log::info('🐕 Watchdog: skipea conv con pedido reciente (<30min)', [
                    'conversacion_id' => $conv->id,
                    'telefono'        => $conv->telefono_normalizado,
                ]);
                continue;
            }

            // Evitar rescatar la misma conversación múltiples veces seguidas:
            // - Cooldown POR CONVERSACIÓN (no por mensaje) de 30 minutos.
            // - Cooldown POR MENSAJE de 24 horas (un mismo mensaje del cliente
            //   solo se intenta rescatar UNA vez en el día).
            $cooldownConv = "watchdog_rescate_conv_{$conv->id}";
            $cooldownMsg  = "watchdog_rescate_msg_{$ultimoMsg->id}";
            if (\Cache::has($cooldownConv) || \Cache::has($cooldownMsg)) continue;
            \Cache::put($cooldownConv, true, now()->addMinutes($cooldownMin));
            \Cache::put($cooldownMsg,  true, now()->addHours(24));

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
                // Timeout 30s — el webhook puede tardar 10-20s en procesar (LLM call,
                // tool calls, validar cobertura, etc.). 5s era muy poco.
                $resp = \Http::timeout(30)->post($url, $payload);
                $exitoso = $resp->successful();

                // Registrar el rescate en BD para el panel de monitoreo
                try {
                    \App\Models\WatchdogRescate::create([
                        'tenant_id'          => $conv->tenant_id ?? null,
                        'conversacion_id'    => $conv->id,
                        'telefono'           => $conv->telefono_normalizado,
                        'mensaje_origen_id'  => $ultimoMsg->id,
                        'mensaje_contenido'  => mb_substr((string) $ultimoMsg->contenido, 0, 500),
                        'segundos_estancada' => $segundosDesde,
                        'exitoso'            => $exitoso,
                        'error_mensaje'      => $exitoso ? null : mb_substr($resp->body(), 0, 500),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('🐕 No se pudo registrar rescate watchdog: ' . $e->getMessage());
                }

                $rescatadas++;
            } catch (\Throwable $e) {
                Log::warning('🐕 Watchdog: error rescatando', [
                    'conversacion_id' => $conv->id,
                    'error'           => $e->getMessage(),
                ]);

                try {
                    \App\Models\WatchdogRescate::create([
                        'tenant_id'          => $conv->tenant_id ?? null,
                        'conversacion_id'    => $conv->id,
                        'telefono'           => $conv->telefono_normalizado,
                        'mensaje_origen_id'  => $ultimoMsg->id ?? null,
                        'mensaje_contenido'  => mb_substr((string) ($ultimoMsg->contenido ?? ''), 0, 500),
                        'segundos_estancada' => $segundosDesde,
                        'exitoso'            => false,
                        'error_mensaje'      => mb_substr($e->getMessage(), 0, 500),
                    ]);
                } catch (\Throwable $e2) {}
            }
        }

        if ($rescatadas > 0) {
            $this->info("🐕 Watchdog: {$rescatadas} conversación(es) rescatada(s)");
        }

        return self::SUCCESS;
    }
}
