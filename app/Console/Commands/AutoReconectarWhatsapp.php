<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WhatsappResolverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 🔁 Auto-reconectar conexiones WhatsApp que estén en PAIRING/DISCONNECTED.
 *
 * Estrategia:
 *   1. Por cada tenant con credenciales TecnoByteApp configuradas, listar conexiones.
 *   2. Si alguna está en PAIRING o DISCONNECTED por >2 min, intentar:
 *      a) POST /whatsappsession/{id}  → reinicia sesión (regenera QR sin borrar)
 *      b) PUT  /whatsappsession/{id}  → fallback (vacía session y reinicia)
 *   3. Si tras 3 intentos sigue caída, enviar alerta admin (cada 30 min máx por conexión).
 *
 * Se programa cada 3 minutos en routes/console.php.
 */
class AutoReconectarWhatsapp extends Command
{
    protected $signature = 'bot:auto-reconectar-whatsapp';
    protected $description = 'Reconecta automáticamente conexiones WhatsApp en PAIRING/DISCONNECTED';

    public function handle(): int
    {
        // Tenants activos con configuración WhatsApp
        $tenants = Tenant::query()
            ->where('activo', true)
            ->whereNotNull('whatsapp_config')
            ->get();

        $totalIntentos = 0;
        $exitos = 0;

        foreach ($tenants as $tenant) {
            try {
                app(\App\Services\TenantManager::class)->set($tenant);
                $resultado = $this->procesarTenant($tenant);
                $totalIntentos += $resultado['intentos'];
                $exitos += $resultado['exitos'];
            } catch (\Throwable $e) {
                Log::warning('🔁 Auto-reconectar: error en tenant', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if ($totalIntentos > 0) {
            $this->info("🔁 {$totalIntentos} intentos de reconexión, {$exitos} exitosos.");
        }

        return self::SUCCESS;
    }

    private function procesarTenant(Tenant $tenant): array
    {
        $resolver = app(WhatsappResolverService::class);
        $cred = $resolver->credenciales($tenant);
        $apiBase = rtrim($cred['api_base_url'] ?? 'https://wa-api.tecnobyteapp.com:1422', '/');

        $token = $resolver->token($tenant, false);
        if (!$token) {
            return ['intentos' => 0, 'exitos' => 0];
        }

        // Listar conexiones del tenant
        $resp = Http::withoutVerifying()->withToken($token)->timeout(15)->get("{$apiBase}/whatsapp/");
        if (!$resp->successful()) {
            Log::warning('🔁 Auto-reconectar: listado WA falló', [
                'tenant_id' => $tenant->id,
                'status'    => $resp->status(),
            ]);
            return ['intentos' => 0, 'exitos' => 0];
        }

        // 🛡️ La API devuelve {whatsapps: [...], count, hasMore} en respuestas nuevas
        //    o un array plano en versiones legacy. Normalizamos ambos casos.
        $body = $resp->json() ?: [];
        if (isset($body['whatsapps']) && is_array($body['whatsapps'])) {
            $whatsapps = collect($body['whatsapps']);
        } elseif (is_array($body) && array_is_list($body)) {
            $whatsapps = collect($body);
        } else {
            // Último recurso: buscar la primera lista de objetos con 'id'
            $whatsapps = collect();
            foreach ($body as $v) {
                if (is_array($v) && isset($v[0]['id'])) {
                    $whatsapps = collect($v);
                    break;
                }
            }
        }

        Log::info('🔁 Auto-reconectar: listado WA', [
            'tenant_id' => $tenant->id,
            'total_conexiones' => $whatsapps->count(),
            'estados' => $whatsapps->pluck('status')->all(),
        ]);

        // Filtrar a las conexiones que pertenecen a este tenant
        // (connection_ids vive dentro de whatsapp_config como JSON)
        $waConfig = (array) ($tenant->whatsapp_config ?? []);
        $idsTenant = $waConfig['connection_ids'] ?? null;
        if (is_array($idsTenant) && !empty($idsTenant)) {
            $idsTenant = array_map('intval', $idsTenant);
            $whatsapps = $whatsapps->filter(fn ($w) => in_array((int) ($w['id'] ?? 0), $idsTenant, true))->values();
        }

        $intentos = 0;
        $exitos   = 0;

        foreach ($whatsapps as $conn) {
            $id = (int) ($conn['id'] ?? 0);
            $status = strtoupper((string) ($conn['status'] ?? ''));
            $needsReconnect = in_array($status, ['PAIRING', 'DISCONNECTED', 'TIMEOUT', 'NOT_CONNECTED'], true);
            if (!$needsReconnect) continue;

            // Throttle: máximo 1 intento por minuto por conexión (evita martillar
            // la API si la sesión necesita varios segundos para regenerar)
            $throttleKey = "auto_reconnect_tenant{$tenant->id}_conn{$id}";
            if (Cache::has($throttleKey)) continue;
            Cache::put($throttleKey, true, now()->addMinute());

            $intentos++;
            Log::info('🔁 Auto-reconectar: intentando', [
                'tenant_id'    => $tenant->id,
                'connection_id'=> $id,
                'status_actual'=> $status,
            ]);

            $ok = $this->reintentarConexion($apiBase, $token, $id);
            if ($ok) {
                $exitos++;
                Log::info('✅ Auto-reconectar: comando enviado OK (esperar nuevo estado)', [
                    'connection_id' => $id,
                ]);
                // Registrar para alerta solo si tras N intentos sigue caída
                $intentosKey = "auto_reconnect_intentos_conn{$id}";
                Cache::forget($intentosKey);
            } else {
                $intentosKey = "auto_reconnect_intentos_conn{$id}";
                $n = (int) Cache::get($intentosKey, 0) + 1;
                Cache::put($intentosKey, $n, now()->addHours(2));

                if ($n >= 3) {
                    $this->enviarAlerta($tenant, $id, $status, $n);
                    Cache::put($intentosKey, 0, now()->addMinutes(30)); // reset cooldown 30 min
                }
            }
        }

        return ['intentos' => $intentos, 'exitos' => $exitos];
    }

    private function reintentarConexion(string $apiBase, string $token, int $connId): bool
    {
        $base = "{$apiBase}/whatsappsession/{$connId}";
        $verbosOrden = ['POST', 'PUT'];

        foreach ($verbosOrden as $verb) {
            try {
                $req = Http::withoutVerifying()->withToken($token)->timeout(20);
                $resp = $verb === 'POST'
                    ? $req->post($base, [])
                    : $req->put($base, []);

                if ($resp->successful()) return true;
            } catch (\Throwable $e) {
                Log::warning("🔁 Reintento {$verb} falló", [
                    'conn_id' => $connId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
        return false;
    }

    private function enviarAlerta(Tenant $tenant, int $connId, string $status, int $intentos): void
    {
        $msgKey = "auto_reconnect_alerta_conn{$connId}";
        if (Cache::has($msgKey)) return;
        Cache::put($msgKey, true, now()->addMinutes(30));

        try {
            $waConfig = (array) ($tenant->whatsapp_config ?? []);
            $admin = $tenant->contacto_email
                ?: ($waConfig['api_email'] ?? null)
                ?: env('WHATSAPP_API_EMAIL');
            $mensaje = "🔴 Conexión WhatsApp #{$connId} del tenant '{$tenant->nombre}' "
                . "está en estado {$status} y NO logró reconectar tras {$intentos} intentos automáticos. "
                . "Acción requerida: entrar al panel de WhatsApp y escanear el QR manualmente.";

            Log::error('🔴 Alerta: WhatsApp no se reconecta automáticamente', [
                'tenant_id'      => $tenant->id,
                'connection_id'  => $connId,
                'status'         => $status,
                'intentos'       => $intentos,
            ]);

            // Enviar email si está configurado
            if (!empty($admin) && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
                \Mail::raw($mensaje, function ($m) use ($admin, $tenant) {
                    $m->to($admin)
                      ->subject("🔴 WhatsApp desconectado — {$tenant->nombre}");
                });
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar alerta auto-reconnect: ' . $e->getMessage());
        }
    }
}
