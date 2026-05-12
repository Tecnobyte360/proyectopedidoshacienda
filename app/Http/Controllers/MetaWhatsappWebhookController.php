<?php

namespace App\Http\Controllers;

use App\Models\MetaWhatsappConfig;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 📥 Webhook de Meta WhatsApp Cloud API.
 *
 * Una sola URL pública /api/meta/whatsapp/webhook recibe mensajes y status
 * de TODOS los tenants. El routing multi-tenant se hace por phone_number_id
 * del payload (metadata.phone_number_id) contra MetaWhatsappConfig.
 *
 * Después de identificar el tenant, NORMALIZA el payload Meta al formato
 * legacy que ya entiende WhatsappWebhookController::receive() — así
 * reusamos TODO el pipeline del bot (LLM, captadores, tools, etc.) sin
 * duplicar lógica.
 */
class MetaWhatsappWebhookController extends Controller
{
    /**
     * GET — Verificación del webhook (Meta Developer Portal).
     * Meta envía hub.mode=subscribe & hub.verify_token=X & hub.challenge=Y.
     * Si X coincide con CUALQUIER verify_token activo en BD, devolvemos Y.
     */
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $verifyTok = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode !== 'subscribe' || !$verifyTok || !$challenge) {
            return response('Bad request', 400);
        }

        $exists = MetaWhatsappConfig::query()
            ->withoutGlobalScopes()
            ->where('verify_token', $verifyTok)
            ->where('activo', true)
            ->exists();

        if (!$exists) {
            Log::warning('🔐 Meta webhook verify: token no coincide', [
                'token_recibido' => mb_substr($verifyTok, 0, 8) . '...',
            ]);
            return response('Forbidden', 403);
        }

        Log::info('✅ Meta webhook verificado');
        return response($challenge, 200);
    }

    /**
     * POST — Recibe mensajes + status callbacks.
     */
    public function receive(Request $request)
    {
        $rawBody = $request->getContent();
        $data    = $request->all() ?: (json_decode($rawBody, true) ?? []);

        Log::info('📩 META WEBHOOK', [
            'raw_body' => mb_substr($rawBody, 0, 1500),
        ]);

        if (empty($data['entry'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        foreach ($data['entry'] as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (!$phoneNumberId) {
                    Log::warning('⚠️ Meta webhook sin phone_number_id', ['change' => $change]);
                    continue;
                }

                $config = MetaWhatsappConfig::porPhoneNumberId($phoneNumberId);
                if (!$config) {
                    Log::warning('⚠️ Meta webhook: no hay config para phone_number_id', [
                        'phone_number_id' => $phoneNumberId,
                    ]);
                    continue;
                }

                // Setear tenant manager para que el resto del pipeline use sus datos
                try {
                    $tenant = Tenant::find($config->tenant_id);
                    if ($tenant) app(TenantManager::class)->set($tenant);
                } catch (\Throwable $e) { /* ignore */ }

                // Procesar mensajes
                foreach ($value['messages'] ?? [] as $msg) {
                    $this->procesarMensaje($msg, $value, $config, $request);
                }

                // Procesar status callbacks (delivered/read/failed)
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->procesarStatus($status, $config);
                }
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Traduce un mensaje Meta a formato legacy y lo entrega al pipeline
     * existente del bot vía WhatsappWebhookController::receive().
     */
    private function procesarMensaje(array $msg, array $value, MetaWhatsappConfig $config, Request $request): void
    {
        $tipo = $msg['type'] ?? 'text';
        $from = $msg['from'] ?? null;
        if (!$from) return;

        $contactoNombre = $value['contacts'][0]['profile']['name'] ?? 'Cliente';

        // Extraer cuerpo según tipo
        $body = '';
        $mediaUrl = null;
        $mediaType = $tipo;
        switch ($tipo) {
            case 'text':
                $body = $msg['text']['body'] ?? '';
                break;
            case 'audio':
            case 'voice':
                $mediaType = 'audio';
                // Meta entrega audio.id — habría que llamar a /MEDIA_ID para sacar URL temporal
                // Para Fase 1 logueamos y pasamos vacío; se atiende en Fase 2.
                $body = '[audio recibido — descarga pendiente]';
                $mediaUrl = $msg['audio']['id'] ?? null;
                break;
            case 'image':
                $mediaType = 'image';
                $body = $msg['image']['caption'] ?? '[imagen]';
                $mediaUrl = $msg['image']['id'] ?? null;
                break;
            case 'interactive':
                // Botón / lista
                $body = $msg['interactive']['button_reply']['title']
                     ?? $msg['interactive']['list_reply']['title']
                     ?? '';
                break;
            default:
                $body = "[mensaje tipo: {$tipo}]";
        }

        // Connection_id sintético: usamos un namespace meta:{phone_number_id}
        // para que no choque con los IDs numéricos de la API legacy.
        $connectionId = 'meta:' . $config->phone_number_id;

        // Armar payload en formato legacy esperado por WhatsappWebhookController
        $legacyPayload = [
            'usuario' => [
                'id'    => $config->tenant_id,
                'name'  => $config->display_name ?? 'Meta',
                'email' => null,
            ],
            'conexion' => [
                'id'     => $connectionId,
                'name'   => 'Meta Cloud API',
                'status' => 'CONNECTED',
            ],
            'chat' => [
                'id'             => 0,
                'name'           => $contactoNombre,
                'phone'          => $from,
                'status'         => 'open',
                'isGroup'        => false,
                'unreadMessages' => 0,
            ],
            'mensaje' => [
                'id'        => $msg['id'] ?? null,
                'body'      => $body,
                'fromMe'    => false,
                'read'      => false,
                'mediaType' => $mediaType,
                'mediaUrl'  => $mediaUrl,
                'createdAt' => isset($msg['timestamp'])
                    ? date('c', (int) $msg['timestamp'])
                    : now()->toIso8601String(),
            ],
            'provider' => 'meta',
        ];

        // Pasar al pipeline existente — clona el Request actual con el payload legacy
        try {
            $fakeRequest = Request::create(
                $request->fullUrl(),
                'POST',
                [],
                $request->cookies->all(),
                [],
                $request->server->all(),
                json_encode($legacyPayload, JSON_UNESCAPED_UNICODE)
            );
            $fakeRequest->headers->set('Content-Type', 'application/json');
            $fakeRequest->setJson(new \Symfony\Component\HttpFoundation\InputBag($legacyPayload));

            app(WhatsappWebhookController::class)->receive($fakeRequest);
        } catch (\Throwable $e) {
            Log::error('❌ Meta→pipeline error', [
                'tenant_id' => $config->tenant_id,
                'from'      => $from,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Status callbacks: actualiza ack del MensajeWhatsapp por wa_id.
     */
    private function procesarStatus(array $status, MetaWhatsappConfig $config): void
    {
        $waId   = $status['id'] ?? null;
        $estado = $status['status'] ?? null;
        if (!$waId || !$estado) return;

        $mapAck = [
            'sent'      => 1,
            'delivered' => 2,
            'read'      => 3,
            'failed'    => -1,
        ];
        $ack = $mapAck[$estado] ?? null;
        if ($ack === null) return;

        try {
            \App\Models\MensajeWhatsapp::query()
                ->withoutGlobalScopes()
                ->where('mensaje_externo_id', $waId)
                ->update(['ack' => $ack]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo actualizar ack Meta: ' . $e->getMessage());
        }
    }
}
