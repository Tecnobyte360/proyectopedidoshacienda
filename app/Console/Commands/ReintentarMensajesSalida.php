<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 📬 Reintenta mensajes salientes que fallaron por desconexión de WhatsApp.
 *
 * Estrategia:
 *  - Recorre `mensajes_salida_pendientes` WHERE enviado_at IS NULL
 *    AND fallido_permanente_at IS NULL AND proximo_intento_at <= now
 *  - Para cada uno, llama al mismo método de envío del webhook
 *  - Si éxito → marca enviado_at = now()
 *  - Si falla → incrementa intentos, calcula próximo intento con backoff:
 *      intento 1 = +15s
 *      intento 2 = +30s
 *      intento 3 = +60s
 *      intento 4 = +2 min
 *      intento 5 = +5 min
 *      intento 6 = +15 min
 *      intento 7 = +1 hora
 *      intento 8+ = +6 horas
 *  - Tras 12 intentos (≈24 horas), marca fallido_permanente_at
 *
 * Se programa cada minuto.
 */
class ReintentarMensajesSalida extends Command
{
    protected $signature = 'bot:reintentar-mensajes-salida';
    protected $description = 'Reintenta enviar mensajes WhatsApp que fallaron por desconexión';

    private array $backoffSegundos = [
        1 => 15,
        2 => 30,
        3 => 60,
        4 => 120,
        5 => 300,
        6 => 900,
        7 => 3600,
        8 => 21600,
        9 => 21600,
        10 => 21600,
        11 => 21600,
        12 => 21600,
    ];

    public function handle(): int
    {
        $pendientes = DB::table('mensajes_salida_pendientes')
            ->whereNull('enviado_at')
            ->whereNull('fallido_permanente_at')
            ->where(function ($q) {
                $q->whereNull('proximo_intento_at')
                  ->orWhere('proximo_intento_at', '<=', now());
            })
            ->orderBy('proximo_intento_at')
            ->limit(50)
            ->get();

        if ($pendientes->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("📬 Procesando {$pendientes->count()} mensajes pendientes…");
        $exitos = 0;
        $fallos = 0;

        foreach ($pendientes as $msg) {
            try {
                // Cargar el tenant correcto
                if ($msg->tenant_id) {
                    $tenant = Tenant::find($msg->tenant_id);
                    if (!$tenant) {
                        $this->marcarFallidoPermanente($msg->id, 'tenant_id inexistente');
                        $fallos++;
                        continue;
                    }
                    app(TenantManager::class)->set($tenant);
                }

                $payload = is_array($msg->payload) ? $msg->payload : json_decode($msg->payload, true);
                if (!is_array($payload)) {
                    $this->marcarFallidoPermanente($msg->id, 'payload corrupto');
                    $fallos++;
                    continue;
                }

                $ok = $this->reenviar($payload, $msg->telefono, $msg->connection_id);

                if ($ok) {
                    DB::table('mensajes_salida_pendientes')
                        ->where('id', $msg->id)
                        ->update([
                            'enviado_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                    Log::info('✅ Mensaje pendiente reenviado OK', [
                        'id'       => $msg->id,
                        'telefono' => $msg->telefono,
                        'intentos' => $msg->intentos + 1,
                    ]);
                    $exitos++;
                } else {
                    $intentos = (int) $msg->intentos + 1;
                    $backoff  = $this->backoffSegundos[$intentos] ?? null;

                    if ($intentos >= 12 || $backoff === null) {
                        $this->marcarFallidoPermanente($msg->id, "12 intentos fallidos");
                        Log::error('❌ Mensaje pendiente: fallido permanente tras 12 intentos', [
                            'id'       => $msg->id,
                            'telefono' => $msg->telefono,
                        ]);
                    } else {
                        DB::table('mensajes_salida_pendientes')
                            ->where('id', $msg->id)
                            ->update([
                                'intentos'           => $intentos,
                                'proximo_intento_at' => now()->addSeconds($backoff),
                                'updated_at'         => now(),
                            ]);
                    }
                    $fallos++;
                }
            } catch (\Throwable $e) {
                Log::warning('Reintento mensaje salida falló: ' . $e->getMessage(), ['id' => $msg->id]);
                $fallos++;
            }
        }

        $this->info("📬 Exitos: {$exitos} · Fallos: {$fallos}");
        return self::SUCCESS;
    }

    private function reenviar(array $payload, string $telefono, $connectionId): bool
    {
        try {
            $controller = app(\App\Http\Controllers\WhatsappWebhookController::class);
            // Usar el método de envío "raw" sin re-encolar si falla — eso
            // sería un loop infinito. Aquí solo el HTTP call directo.
            $rm = new \ReflectionMethod($controller, 'postWhatsappSend');
            $rm->setAccessible(true);

            $tokenM = new \ReflectionMethod($controller, 'obtenerTokenWhatsapp');
            $tokenM->setAccessible(true);
            $token = $tokenM->invoke($controller);
            if (!$token) return false;

            $resp = $rm->invoke($controller, $token, $payload);
            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('Reintento HTTP falló: ' . $e->getMessage());
            return false;
        }
    }

    private function marcarFallidoPermanente(int $id, string $razon): void
    {
        DB::table('mensajes_salida_pendientes')
            ->where('id', $id)
            ->update([
                'fallido_permanente_at' => now(),
                'ultimo_error'          => mb_substr($razon, 0, 1000),
                'updated_at'            => now(),
            ]);
    }
}
