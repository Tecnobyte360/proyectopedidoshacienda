<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 💬 Servicio para enviar mensajes de Facebook Messenger via Meta Graph API.
 *
 * Usa el Page Access Token + el Page ID que ya viven en el tenant
 * (instagram_page_id es el mismo FB Page ID cuando IG y FB Page están vinculadas).
 *
 * Docs: https://developers.facebook.com/docs/messenger-platform/send-messages
 */
class MessengerService
{
    private const API_VERSION = 'v21.0';
    private const API_BASE    = 'https://graph.facebook.com';

    /**
     * Envía un mensaje de texto a un PSID (Page-Scoped User ID).
     */
    public function enviarTexto(Tenant $tenant, string $psid, string $mensaje): array
    {
        $token = $this->token($tenant);
        if (empty($token)) {
            return ['ok' => false, 'error' => 'Tenant sin Page Access Token de Messenger'];
        }
        if (empty($tenant->instagram_page_id)) {
            return ['ok' => false, 'error' => 'Tenant sin Page ID configurado'];
        }

        $url = self::API_BASE . '/' . self::API_VERSION . '/me/messages';

        try {
            $resp = Http::withToken($token)
                ->timeout(15)
                ->post($url, [
                    'recipient'      => ['id' => $psid],
                    'message'        => ['text' => $mensaje],
                    'messaging_type' => 'RESPONSE',
                ]);

            if ($resp->successful()) {
                $msgId = $resp->json('message_id');
                Log::info('💬 Messenger msg enviado', [
                    'tenant_id'  => $tenant->id,
                    'psid'       => $psid,
                    'message_id' => $msgId,
                ]);
                return ['ok' => true, 'message_id' => $msgId, 'error' => null];
            }

            $err = $resp->json('error.message') ?: $resp->body();
            Log::warning('💬 Messenger msg falló', [
                'tenant_id' => $tenant->id,
                'psid'      => $psid,
                'status'    => $resp->status(),
                'error'     => substr($err, 0, 400),
            ]);
            return ['ok' => false, 'message_id' => null, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error('💬 Messenger excepción', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtiene info pública del usuario Messenger (nombre, foto).
     */
    public function obtenerPerfilUsuario(Tenant $tenant, string $psid): ?array
    {
        $token = $this->token($tenant);
        if (empty($token)) return null;

        try {
            $resp = Http::withToken($token)
                ->timeout(10)
                ->get(self::API_BASE . '/' . self::API_VERSION . '/' . $psid, [
                    'fields' => 'first_name,last_name,profile_pic',
                ]);

            if ($resp->successful()) {
                $j = $resp->json();
                return [
                    'name'        => trim(($j['first_name'] ?? '') . ' ' . ($j['last_name'] ?? '')),
                    'profile_pic' => $j['profile_pic'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('💬 No se pudo obtener perfil Messenger: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Resuelve el tenant por page_id (mismo que el de IG).
     */
    public function tenantPorPageId(string $pageId): ?Tenant
    {
        return Tenant::where('instagram_page_id', $pageId)->first();
    }

    /**
     * Token preferido: messenger_page_access_token > instagram_access_token.
     * El de IG con scope pages_messaging suele funcionar para Messenger también.
     */
    private function token(Tenant $tenant): ?string
    {
        return $tenant->messenger_page_access_token
            ?: $tenant->instagram_access_token
            ?: null;
    }
}
