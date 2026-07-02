<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Renueva los tokens de Instagram (API de Instagram / graph.instagram.com)
 * ANTES de que expiren, para que en la práctica nunca caduquen.
 *
 * Los tokens de larga duración de Instagram viven 60 días y se pueden
 * refrescar (extienden otros 60) mientras tengan >24h de vida y no hayan
 * expirado. Este comando corre a diario y refresca los que estén a <15 días
 * de expirar (o sin fecha registrada).
 */
class RefrescarTokensInstagram extends Command
{
    protected $signature = 'instagram:refrescar-tokens {--force : Refresca aunque no estén por vencer}';

    protected $description = 'Renueva los tokens de Instagram (auto-refresh, para que no expiren)';

    public function handle(): int
    {
        $tenants = Tenant::withoutGlobalScopes()
            ->where('instagram_activo', true)
            ->whereNotNull('instagram_access_token')
            ->get();

        $refrescados = 0; $errores = 0; $omitidos = 0;

        foreach ($tenants as $t) {
            // ¿Necesita refrescar? (<15 días para expirar, o sin fecha, o --force)
            $porVencer = $this->option('force')
                || !$t->instagram_token_expira_at
                || $t->instagram_token_expira_at->lte(now()->addDays(15));

            if (!$porVencer) { $omitidos++; continue; }

            try {
                $r = Http::get('https://graph.instagram.com/refresh_access_token', [
                    'grant_type'   => 'ig_refresh_token',
                    'access_token' => $t->instagram_access_token,
                ]);
                $j = $r->json();

                if ($r->ok() && !empty($j['access_token'])) {
                    $t->instagram_access_token  = $j['access_token'];
                    $t->instagram_token_expira_at = now()->addSeconds((int) ($j['expires_in'] ?? 5184000));
                    $t->save();
                    $refrescados++;
                    Log::info('📸 IG token renovado', [
                        'tenant_id' => $t->id,
                        'expira'    => $t->instagram_token_expira_at->toDateTimeString(),
                    ]);
                } else {
                    $errores++;
                    Log::warning('📸 IG token: fallo al renovar', [
                        'tenant_id' => $t->id,
                        'resp'      => mb_substr($r->body(), 0, 300),
                    ]);
                }
            } catch (\Throwable $e) {
                $errores++;
                Log::error('📸 IG token: excepción al renovar: ' . $e->getMessage(), ['tenant_id' => $t->id]);
            }
        }

        $this->info("Instagram tokens → refrescados: {$refrescados}, omitidos: {$omitidos}, errores: {$errores}");
        return self::SUCCESS;
    }
}
