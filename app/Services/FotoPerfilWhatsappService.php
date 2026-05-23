<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 📸 FOTO PERFIL WHATSAPP
 *
 * Obtiene la foto de perfil de un cliente desde la API de WhatsApp
 * (TecnoByteApp / EstradaHub) y la guarda en storage local.
 *
 * Flujo:
 *   1. Resuelve credenciales del tenant via WhatsappResolverService
 *   2. POST /api/contact con {name, number} → recibe profilePicUrl
 *   3. Descarga la imagen (la URL de Meta caduca, NO la guardamos)
 *   4. Guarda en storage/app/public/tenants/{slug}/avatars/{telefono}.jpg
 *   5. Actualiza cliente.foto_url + foto_actualizada_at
 *
 * Re-sincroniza si la foto tiene >7 días (cliente pudo cambiar avatar).
 */
class FotoPerfilWhatsappService
{
    /**
     * Días tras los cuales re-sincronizar la foto.
     */
    private const REFRESH_DAYS = 7;

    /**
     * Sincroniza la foto de UN cliente. Retorna la ruta local o null si falla.
     */
    public function sincronizar(Cliente $cliente, bool $forzar = false): ?string
    {
        // Skip si ya tenemos foto reciente y no se forzó
        if (!$forzar
            && !empty($cliente->foto_url)
            && $cliente->foto_actualizada_at
            && $cliente->foto_actualizada_at->isAfter(now()->subDays(self::REFRESH_DAYS))
        ) {
            return $cliente->foto_url;
        }

        $tenant = $cliente->tenant_id ? Tenant::find($cliente->tenant_id) : null;
        if (!$tenant) {
            Log::warning('📸 FotoPerfil: cliente sin tenant', ['cliente_id' => $cliente->id]);
            return null;
        }

        // Setear contexto de tenant para que WhatsappResolverService lea bien
        app(TenantManager::class)->set($tenant);

        // Resolver número limpio (solo dígitos)
        $numero = preg_replace('/\D+/', '', $cliente->telefono_normalizado ?: $cliente->telefono);
        if (empty($numero) || strlen($numero) < 8) {
            Log::warning('📸 FotoPerfil: número inválido', [
                'cliente_id' => $cliente->id,
                'telefono'   => $cliente->telefono,
            ]);
            return null;
        }

        // 🚀 RUTA RÁPIDA: si llegó profilePicUrl en el webhook reciente,
        // está en cache — saltamos el API call.
        $cacheKey = 'wa_profilepic_' . $numero;
        $profilePicUrl = \Cache::get($cacheKey);

        if (empty($profilePicUrl)) {
            // RUTA LENTA: consultar API. Solo si no llegó en webhook.
            try {
                $resolver = app(WhatsappResolverService::class);
                $cred = $resolver->credenciales();
            } catch (\Throwable $e) {
                Log::warning('📸 FotoPerfil: no se pudieron resolver credenciales: ' . $e->getMessage());
                return null;
            }

            if (empty($cred['api_base_url'])) {
                Log::warning('📸 FotoPerfil: tenant sin api_base_url', ['tenant_id' => $tenant->id]);
                return null;
            }

            $token = $this->obtenerToken($cred, $resolver);
            if (!$token) {
                Log::warning('📸 FotoPerfil: sin token WhatsApp', ['tenant_id' => $tenant->id]);
                return null;
            }

            $profilePicUrl = $this->consultarContacto($cred['api_base_url'], $token, $cliente->nombre ?: 'Cliente', $numero);
        } else {
            Log::info('📸 FotoPerfil: usando URL de cache (webhook reciente)', ['numero' => $numero]);
        }

        if (empty($profilePicUrl)) {
            Log::info('📸 FotoPerfil: contacto sin foto', [
                'tenant_id' => $tenant->id,
                'numero'    => $numero,
            ]);
            // Marcar timestamp para no re-intentar en cada mensaje
            $cliente->update(['foto_actualizada_at' => now()]);
            return null;
        }

        // 4) Descargar la imagen
        $rutaLocal = $this->descargarYGuardar($profilePicUrl, $tenant, $numero);
        if (!$rutaLocal) {
            return null;
        }

        // 5) Actualizar cliente
        $cliente->update([
            'foto_url'             => $rutaLocal,
            'foto_actualizada_at'  => now(),
            'foto_origen'          => 'wa',
        ]);

        Log::info('📸 FotoPerfil: sincronizada', [
            'cliente_id' => $cliente->id,
            'tenant_id'  => $tenant->id,
            'ruta'       => $rutaLocal,
        ]);

        return $rutaLocal;
    }

    /**
     * Login WhatsApp y cachear token.
     */
    private function obtenerToken(array $cred, WhatsappResolverService $resolver): ?string
    {
        $cacheKey = $resolver->tokenCacheKey();
        $cached = \Cache::get($cacheKey);
        if ($cached) return $cached;

        try {
            $resp = Http::withoutVerifying()->timeout(15)
                ->post(rtrim($cred['api_base_url'], '/') . '/auth/login', [
                    'email'    => $cred['email'],
                    'password' => $cred['password'],
                ]);

            if (!$resp->successful()) return null;

            $token = $resp->json('token') ?? $resp->json('accessToken');
            if ($token) {
                \Cache::put($cacheKey, $token, now()->addHours(8));
                return $token;
            }
        } catch (\Throwable $e) {
            Log::warning('📸 FotoPerfil: login falló: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Llama a POST /api/contact para obtener el contacto con profilePicUrl.
     */
    private function consultarContacto(string $baseUrl, string $token, string $name, string $number): ?string
    {
        try {
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(15)
                ->post(rtrim($baseUrl, '/') . '/api/contact', [
                    'name'   => $name,
                    'number' => $number,
                ]);

            if (!$resp->successful()) {
                Log::info('📸 FotoPerfil: /api/contact respondió ' . $resp->status(), [
                    'numero' => $number,
                    'body'   => mb_substr($resp->body(), 0, 200),
                ]);
                return null;
            }

            $data = $resp->json();
            return $data['profilePicUrl'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('📸 FotoPerfil: consulta contacto falló: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Descarga la imagen y la guarda en storage local del tenant.
     * Retorna la URL pública relativa (ej: /storage/tenants/la-hacienda/avatars/573216499744.jpg).
     */
    private function descargarYGuardar(string $url, Tenant $tenant, string $numero): ?string
    {
        try {
            $resp = Http::withoutVerifying()
                ->timeout(20)
                ->withOptions(['stream' => false])
                ->get($url);

            if (!$resp->successful()) {
                Log::info('📸 FotoPerfil: descarga falló ' . $resp->status(), ['url' => mb_substr($url, 0, 100)]);
                return null;
            }

            $bytes = $resp->body();
            if (strlen($bytes) < 500) {
                // Muy chico para ser una imagen válida
                Log::info('📸 FotoPerfil: archivo muy chico (' . strlen($bytes) . ' bytes)');
                return null;
            }

            // Detectar extensión por mime
            $contentType = $resp->header('Content-Type') ?: 'image/jpeg';
            $ext = match (true) {
                str_contains($contentType, 'png')  => 'png',
                str_contains($contentType, 'webp') => 'webp',
                default                            => 'jpg',
            };

            $slug = $tenant->slug ?: ('tenant-' . $tenant->id);
            $relativaStorage = "public/tenants/{$slug}/avatars/{$numero}.{$ext}";
            $publica = "/storage/tenants/{$slug}/avatars/{$numero}.{$ext}";

            // Asegurar carpeta
            $directorio = "public/tenants/{$slug}/avatars";
            if (!Storage::exists($directorio)) {
                Storage::makeDirectory($directorio);
            }

            Storage::put($relativaStorage, $bytes);

            return $publica;
        } catch (\Throwable $e) {
            Log::warning('📸 FotoPerfil: error guardando imagen: ' . $e->getMessage());
            return null;
        }
    }
}
