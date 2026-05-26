<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * 📷 Instagram Business Login — OAuth flow para que cada tenant
 *     conecte su cuenta IG con 1 click desde el panel de Kivox.
 *
 * Flujo:
 *   1. Admin del tenant entra a /admin/tenants/{slug}/conectar-instagram
 *   2. Click "Conectar Instagram" → redirige a instagram.com/oauth/authorize
 *      con client_id de Kivox + redirect_uri + scopes + state=tenant_slug
 *   3. Usuario autoriza → Meta redirige a este callback con ?code=...
 *   4. Intercambiamos code → short-lived token → long-lived token (60d)
 *   5. Obtenemos page_id + ig_business_account_id y los guardamos en el tenant
 *
 * Docs: https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login
 */
class InstagramOAuthController extends Controller
{
    /**
     * GET /api/meta/instagram/oauth/callback?code=...&state=tenant_slug
     */
    public function callback(Request $request)
    {
        $code  = $request->query('code');
        $state = $request->query('state');             // slug del tenant
        $error = $request->query('error');

        if ($error) {
            Log::warning('📷 IG OAuth cancelado/error', $request->all());
            return redirect('/admin/tenants')
                ->with('error', "Instagram canceló la conexión: {$error}");
        }

        if (!$code || !$state) {
            return response('Falta code o state', 400);
        }

        $tenant = Tenant::where('slug', $state)->first();
        if (!$tenant) {
            return response('Tenant no encontrado', 404);
        }

        $appId       = env('META_APP_ID');
        $appSecret   = env('META_APP_SECRET');
        $redirectUri = url('/api/meta/instagram/oauth/callback');

        // 1. Intercambiar code → short-lived token
        $shortRes = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);

        if ($shortRes->failed()) {
            Log::error('📷 IG OAuth short-token fallo', ['response' => $shortRes->body()]);
            return redirect('/admin/tenants')
                ->with('error', 'No se pudo obtener token de Instagram');
        }

        $shortToken = $shortRes->json('access_token');
        $userId     = $shortRes->json('user_id');

        // 2. Intercambiar short-lived → long-lived (60 días)
        $longRes = Http::get('https://graph.instagram.com/access_token', [
            'grant_type'    => 'ig_exchange_token',
            'client_secret' => $appSecret,
            'access_token'  => $shortToken,
        ]);

        $longToken = $longRes->json('access_token') ?? $shortToken;
        $expiresIn = $longRes->json('expires_in') ?? 5184000;

        // 3. Obtener datos del IG Business Account
        $meRes = Http::get("https://graph.instagram.com/v21.0/me", [
            'fields'       => 'id,username,account_type',
            'access_token' => $longToken,
        ]);

        $igBusinessAccountId = $meRes->json('id') ?? $userId;
        $username            = $meRes->json('username');

        // 4. Guardar en el tenant
        $tenant->update([
            'instagram_business_account_id' => $igBusinessAccountId,
            'instagram_page_id'             => $igBusinessAccountId, // en IG Login = mismo
            'instagram_activo'              => true,
            'instagram_access_token'        => $longToken,
            'instagram_token_expira_at'     => now()->addSeconds($expiresIn),
            'instagram_username'            => $username,
        ]);

        Log::info('📷 IG conectado para tenant', [
            'tenant'   => $tenant->slug,
            'username' => $username,
            'ig_id'    => $igBusinessAccountId,
            'expira'   => now()->addSeconds($expiresIn)->toDateTimeString(),
        ]);

        return redirect('/admin/tenants')
            ->with('success', "✅ Instagram conectado: @{$username}");
    }

    /**
     * POST /api/meta/instagram/data-deletion
     *
     * Meta lo llama cuando un usuario pide eliminar sus datos desde su cuenta IG.
     * Debemos eliminar conversaciones/mensajes del igsid recibido y devolver
     * un JSON con confirmation_code y url donde el usuario pueda verificar.
     */
    public function dataDeletion(Request $request)
    {
        $signedRequest = $request->input('signed_request');
        $payload       = $this->parseSignedRequest($signedRequest, env('META_APP_SECRET'));

        $igsid = $payload['user_id'] ?? null;
        $confirmationCode = 'KVX-' . substr(md5($igsid . now()->timestamp), 0, 12);

        if ($igsid) {
            try {
                \App\Models\MensajeWhatsapp::whereHas('conversacion', function ($q) use ($igsid) {
                    $q->where('canal', 'instagram')->where('igsid', $igsid);
                })->delete();

                \App\Models\ConversacionWhatsapp::where('canal', 'instagram')
                    ->where('igsid', $igsid)
                    ->delete();

                \App\Models\Cliente::where('telefono_normalizado', $igsid)
                    ->where('origen', 'instagram')
                    ->delete();

                Log::info('📷 IG datos eliminados', ['igsid' => $igsid, 'code' => $confirmationCode]);
            } catch (\Throwable $e) {
                Log::error('📷 IG data-deletion error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'url'               => url("/eliminar-datos?code={$confirmationCode}"),
            'confirmation_code' => $confirmationCode,
        ]);
    }

    /**
     * GET /admin/tenants/{slug}/conectar-instagram
     * Genera URL de autorización y redirige al usuario a Instagram.
     */
    public function iniciar(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        $authUrl = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
            'client_id'     => env('META_APP_ID'),
            'redirect_uri'  => url('/api/meta/instagram/oauth/callback'),
            'response_type' => 'code',
            'scope'         => implode(',', [
                'instagram_business_basic',
                'instagram_business_manage_messages',
                'instagram_business_manage_comments',
                'instagram_business_content_publish',
            ]),
            'state'         => $tenant->slug,
        ]);

        return redirect($authUrl);
    }

    /**
     * Verifica el signed_request de Meta (data deletion / deauthorize)
     */
    private function parseSignedRequest(?string $signedRequest, ?string $secret): array
    {
        if (!$signedRequest || !$secret) return [];

        [$encodedSig, $payload] = explode('.', $signedRequest, 2) + [null, null];
        if (!$encodedSig || !$payload) return [];

        $sig  = $this->base64UrlDecode($encodedSig);
        $data = json_decode($this->base64UrlDecode($payload), true);

        $expectedSig = hash_hmac('sha256', $payload, $secret, true);
        if (!hash_equals($expectedSig, $sig)) {
            Log::warning('📷 IG signed_request firma inválida');
            return [];
        }

        return $data ?: [];
    }

    private function base64UrlDecode(string $input): string
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
