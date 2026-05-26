<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 📷 Servicio para enviar DMs de Instagram a través de Meta Graph API.
 *
 * Reutiliza el ACCESS TOKEN de Meta del tenant (mismo que WhatsApp) +
 * el instagram_business_account_id que se configura por tenant.
 *
 * Docs: https://developers.facebook.com/docs/messenger-platform/instagram/get-started
 */
class InstagramMessagingService
{
    private const API_VERSION = 'v21.0';
    private const API_BASE    = 'https://graph.facebook.com';

    /**
     * Envía un mensaje de texto a un IGSID (Instagram-Scoped ID del usuario).
     *
     * @param Tenant $tenant   Tenant origen (debe tener IG configurado)
     * @param string $igsid    ID del usuario destino en IG (lo recibimos en el webhook)
     * @param string $mensaje  Texto del mensaje
     * @return array  ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
     */
    public function enviarTexto(Tenant $tenant, string $igsid, string $mensaje): array
    {
        if (empty(($tenant->instagram_access_token ?? $tenant->meta_access_token ?? null))) {
            return ['ok' => false, 'error' => 'Tenant sin token Meta'];
        }
        if (empty($tenant->instagram_page_id)) {
            return ['ok' => false, 'error' => 'Tenant sin instagram_page_id'];
        }

        $url = self::API_BASE . '/' . self::API_VERSION . '/me/messages';

        // Importante: para IG se usa el page_id como prefijo en algunos casos,
        // pero el endpoint /me/messages con el token de la PAGE funciona bien.
        try {
            $resp = Http::withToken(($tenant->instagram_access_token ?? $tenant->meta_access_token ?? null))
                ->timeout(15)
                ->post($url, [
                    'recipient'      => ['id' => $igsid],
                    'message'        => ['text' => $mensaje],
                    'messaging_type' => 'RESPONSE',  // dentro de ventana 24h
                ]);

            if ($resp->successful()) {
                $msgId = $resp->json('message_id');
                Log::info('📷 IG DM enviado', [
                    'tenant_id'  => $tenant->id,
                    'igsid'      => $igsid,
                    'message_id' => $msgId,
                ]);
                return ['ok' => true, 'message_id' => $msgId, 'error' => null];
            }

            $err = $resp->json('error.message') ?: $resp->body();
            Log::warning('📷 IG DM falló', [
                'tenant_id' => $tenant->id,
                'igsid'     => $igsid,
                'status'    => $resp->status(),
                'error'     => substr($err, 0, 400),
            ]);
            return ['ok' => false, 'message_id' => null, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error('📷 IG DM excepción', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtiene info pública del usuario IG (nombre, foto). Útil al crear cliente nuevo.
     */
    public function obtenerPerfilUsuario(Tenant $tenant, string $igsid): ?array
    {
        if (empty(($tenant->instagram_access_token ?? $tenant->meta_access_token ?? null))) return null;

        try {
            $resp = Http::withToken(($tenant->instagram_access_token ?? $tenant->meta_access_token ?? null))
                ->timeout(10)
                ->get(self::API_BASE . '/' . self::API_VERSION . '/' . $igsid, [
                    'fields' => 'name,profile_pic,username',
                ]);

            if ($resp->successful()) return $resp->json();
        } catch (\Throwable $e) {
            Log::warning('📷 No se pudo obtener perfil IG: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Resuelve el tenant a partir del page_id o instagram_business_account_id
     * (cuando llega un webhook).
     */
    public function tenantPorPageId(string $pageId): ?Tenant
    {
        return Tenant::where('instagram_page_id', $pageId)
            ->orWhere('instagram_business_account_id', $pageId)
            ->first();
    }
}
