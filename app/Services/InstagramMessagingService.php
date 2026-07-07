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
    private const API_VERSION = 'v23.0';
    private const API_BASE    = 'https://graph.facebook.com';

    /**
     * Envía un mensaje de texto a un IGSID (Instagram-Scoped ID del usuario).
     *
     * @param Tenant $tenant   Tenant origen (debe tener IG configurado)
     * @param string $igsid    ID del usuario destino en IG (lo recibimos en el webhook)
     * @param string $mensaje  Texto del mensaje
     * @return array  ['ok' => bool, 'message_id' => ?string, 'error' => ?string]
     */
    public function enviarTexto(Tenant $tenant, string $igsid, string $mensaje, ?string $replyToMid = null): array
    {
        $token = $tenant->instagram_access_token ?? $tenant->meta_access_token ?? null;
        if (empty($token)) {
            return ['ok' => false, 'error' => 'Tenant sin token Meta'];
        }

        // 🆕 API de Instagram (Instagram Login): sin page_id, se envía por
        //    graph.instagram.com con el token de la cuenta de Instagram.
        // 🔙 API clásica (página de Facebook): requiere page_id, graph.facebook.com.
        $esApiInstagram = empty($tenant->instagram_page_id) && !empty($tenant->instagram_business_account_id);
        if (!$esApiInstagram && empty($tenant->instagram_page_id)) {
            return ['ok' => false, 'error' => 'Tenant sin instagram_page_id'];
        }

        // API de Instagram Login → graph.instagram.com/<IG_ID>/messages (v23.0).
        // API clásica (página FB) → graph.facebook.com/<PAGE_ID>/messages.
        if ($esApiInstagram) {
            $base  = 'https://graph.instagram.com';
            $verId = $tenant->instagram_business_account_id ?: 'me';
        } else {
            $base  = self::API_BASE;
            $verId = 'me';
        }
        $url = $base . '/' . self::API_VERSION . '/' . $verId . '/messages';

        $mensajePayload = ['text' => $mensaje];
        // 💬 Responder citando un mensaje específico del cliente.
        // Algunas cuentas de la API de Instagram Login no aceptan reply_to →
        // si Meta lo rechaza, reintentamos sin la cita para no perder el mensaje.
        if ($replyToMid) {
            $mensajePayload['reply_to'] = ['mid' => $replyToMid];
        }

        try {
            $resp = Http::withToken($token)
                ->timeout(15)
                ->post($url, [
                    'recipient'      => ['id' => $igsid],
                    'message'        => $mensajePayload,
                    'messaging_type' => 'RESPONSE',  // dentro de ventana 24h
                ]);

            // Reintento sin reply_to si esa es la causa del rechazo.
            if (!$resp->successful() && $replyToMid
                && str_contains(strtolower($resp->body()), 'reply_to')) {
                Log::info('📷 IG reply_to no soportado, reintentando sin cita');
                $resp = Http::withToken($token)
                    ->timeout(15)
                    ->post($url, [
                        'recipient'      => ['id' => $igsid],
                        'message'        => ['text' => $mensaje],
                        'messaging_type' => 'RESPONSE',
                    ]);
            }

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
     * Envía un adjunto (imagen / audio / video) por DM de Instagram vía URL pública.
     *
     * @param string $tipo  'image' | 'audio' | 'video'
     * @return array ['ok'=>bool, 'message_id'=>?string, 'error'=>?string]
     */
    public function enviarMedia(Tenant $tenant, string $igsid, string $url, string $tipo = 'image'): array
    {
        $token = $tenant->instagram_access_token ?? $tenant->meta_access_token ?? null;
        if (empty($token)) {
            return ['ok' => false, 'error' => 'Tenant sin token Meta'];
        }

        $esApiInstagram = empty($tenant->instagram_page_id) && !empty($tenant->instagram_business_account_id);
        if ($esApiInstagram) {
            $base  = 'https://graph.instagram.com';
            $verId = $tenant->instagram_business_account_id ?: 'me';
        } else {
            $base  = self::API_BASE;
            $verId = 'me';
        }

        $tipo = in_array($tipo, ['image', 'audio', 'video'], true) ? $tipo : 'image';
        $endpoint = $base . '/' . self::API_VERSION . '/' . $verId . '/messages';

        try {
            $resp = Http::withToken($token)
                ->timeout(20)
                ->post($endpoint, [
                    'recipient' => ['id' => $igsid],
                    'message'   => [
                        'attachment' => [
                            'type'    => $tipo,
                            'payload' => ['url' => $url],
                        ],
                    ],
                ]);

            if ($resp->successful()) {
                Log::info('📷 IG media enviado', ['tenant_id' => $tenant->id, 'tipo' => $tipo]);
                return ['ok' => true, 'message_id' => $resp->json('message_id'), 'error' => null];
            }

            $err = $resp->json('error.message') ?: $resp->body();
            Log::warning('📷 IG media falló', ['tenant_id' => $tenant->id, 'tipo' => $tipo, 'status' => $resp->status(), 'error' => substr($err, 0, 400)]);
            return ['ok' => false, 'message_id' => null, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error('📷 IG media excepción: ' . $e->getMessage(), ['tenant_id' => $tenant->id]);
            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Marca como "visto" el último mensaje del usuario (el cliente ve el ✓✓ en IG).
     * También sirve para 'typing_on' / 'typing_off'.
     *
     * @param string $accion 'mark_seen' | 'typing_on' | 'typing_off'
     */
    public function marcarLeido(Tenant $tenant, string $igsid, ?string $mid = null, string $accion = 'mark_seen'): bool
    {
        $token = $tenant->instagram_access_token ?? $tenant->meta_access_token ?? null;
        if (empty($token)) return false;

        $esApiInstagram = empty($tenant->instagram_page_id) && !empty($tenant->instagram_business_account_id);
        $base  = $esApiInstagram ? 'https://graph.instagram.com' : self::API_BASE;
        $verId = $esApiInstagram ? ($tenant->instagram_business_account_id ?: 'me') : 'me';
        $url   = $base . '/' . self::API_VERSION . '/' . $verId . '/messages';

        try {
            $resp = Http::withToken($token)->timeout(10)->post($url, [
                'recipient'     => ['id' => $igsid],
                'sender_action' => $accion,
            ]);
            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('📷 IG mark_seen falló: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene info pública del usuario IG (nombre, foto). Útil al crear cliente nuevo.
     */
    public function obtenerPerfilUsuario(Tenant $tenant, string $igsid): ?array
    {
        $token = $tenant->instagram_access_token ?? $tenant->meta_access_token ?? null;
        if (empty($token)) return null;

        // API de Instagram Login → graph.instagram.com/<IGSID>?fields=name,username,profile_pic
        // API clásica (página FB) → graph.facebook.com/<IGSID>?fields=...
        $esApiInstagram = empty($tenant->instagram_page_id) && !empty($tenant->instagram_business_account_id);
        $base = $esApiInstagram ? 'https://graph.instagram.com' : self::API_BASE;

        try {
            $resp = Http::withToken($token)
                ->timeout(10)
                ->get($base . '/' . self::API_VERSION . '/' . $igsid, [
                    'fields' => 'name,username,profile_pic',
                ]);

            if ($resp->successful()) return $resp->json();

            Log::warning('📷 Perfil IG no disponible', [
                'igsid'  => $igsid,
                'status' => $resp->status(),
                'error'  => substr($resp->body(), 0, 200),
            ]);
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
