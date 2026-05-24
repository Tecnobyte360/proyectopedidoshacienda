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

                // 📞 Procesar eventos de Calling API (calls + call_permission_updates)
                if (!empty($value['calls']) || !empty($value['call_permission_updates'])) {
                    try {
                        app(\App\Services\Meta\MetaCallingService::class)
                            ->procesarWebhook($value, $config->tenant_id);
                    } catch (\Throwable $e) {
                        Log::error('📞 Error procesando webhook calls', [
                            'error' => $e->getMessage(),
                        ]);
                    }
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
                $mediaId = $msg['audio']['id'] ?? null;
                $body = '[Audio]';
                $mediaUrl = $this->descargarMediaMeta($mediaId, $config, 'ogg') ?? $mediaId;
                break;
            case 'image':
                $mediaType = 'image';
                $body = $msg['image']['caption'] ?? '[imagen]';
                $mediaId = $msg['image']['id'] ?? null;
                $mediaUrl = $this->descargarMediaMeta($mediaId, $config, 'jpg') ?? $mediaId;
                break;
            case 'video':
                $mediaType = 'video';
                $body = $msg['video']['caption'] ?? '[video]';
                $mediaId = $msg['video']['id'] ?? null;
                $mediaUrl = $this->descargarMediaMeta($mediaId, $config, 'mp4') ?? $mediaId;
                break;
            case 'document':
                $mediaType = 'document';
                $body = $msg['document']['caption'] ?? $msg['document']['filename'] ?? '[documento]';
                $mediaId = $msg['document']['id'] ?? null;
                $mediaUrl = $this->descargarMediaMeta($mediaId, $config, 'bin') ?? $mediaId;
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
        if ($ack !== null) {
            try {
                \App\Models\MensajeWhatsapp::query()
                    ->withoutGlobalScopes()
                    ->where('mensaje_externo_id', $waId)
                    ->update(['ack' => $ack]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo actualizar ack Meta: ' . $e->getMessage());
            }
        }

        // 💰 Persistir evento de billing si Meta lo incluyó
        $pricing      = $status['pricing'] ?? null;
        $conversation = $status['conversation'] ?? null;
        $convId       = $conversation['id'] ?? null;
        if ($pricing && $convId && (($pricing['billable'] ?? true) === true)) {
            $categoria = $pricing['category']
                ?? ($conversation['origin']['type'] ?? null)
                ?? 'service';

            // Tabla de precios aproximada Colombia (USD por conversación)
            $tarifaCo = [
                'service'        => 0.000,
                'utility'        => 0.0080,
                'authentication' => 0.0080,
                'marketing'      => 0.0265,
                'referral_conversion' => 0.000,
            ];
            $cost = $tarifaCo[$categoria] ?? 0;

            try {
                \App\Models\WhatsappBillingEvent::query()
                    ->withoutGlobalScopes()
                    ->updateOrCreate(
                        [
                            'tenant_id'       => $config->tenant_id,
                            'conversation_id' => $convId,
                        ],
                        [
                            'message_id'    => $waId,
                            'telefono'      => $status['recipient_id'] ?? null,
                            'categoria'     => $categoria,
                            'pricing_model' => $pricing['pricing_model'] ?? null,
                            'billable'      => (bool) ($pricing['billable'] ?? true),
                            'cost_usd'      => $cost,
                            'moneda'        => 'USD',
                            'origin_type'   => $conversation['origin']['type'] ?? null,
                            'raw_payload'   => ['pricing' => $pricing, 'conversation' => $conversation],
                            'ocurrido_at'   => isset($status['timestamp'])
                                ? \Carbon\Carbon::createFromTimestamp((int) $status['timestamp'])
                                : now(),
                        ]
                    );
            } catch (\Throwable $e) {
                Log::warning('No se pudo persistir billing Meta: ' . $e->getMessage());
            }
        }
    }

    /**
     * Descarga un media entrante de Meta (image/audio/video/document) y lo
     * guarda en storage público. Meta requiere 2 llamadas autenticadas:
     *   1. GET /{media_id} → devuelve { url: "https://lookaside.fbsbx.com/..." }
     *   2. GET de esa URL con Bearer token → binario
     *
     * @return string|null URL pública local del archivo (o null si falla)
     */
    private function descargarMediaMeta(?string $mediaId, \App\Models\MetaWhatsappConfig $config, string $extDefault = 'bin'): ?string
    {
        if (!$mediaId) return null;

        try {
            // 1. Obtener URL real autenticada
            $resp = \Illuminate\Support\Facades\Http::withToken($config->access_token)
                ->timeout(15)
                ->get(sprintf('https://graph.facebook.com/%s/%s', $config->api_version ?: 'v25.0', $mediaId));

            if (!$resp->successful()) {
                Log::warning('Meta media: falló GET metadata', [
                    'media_id' => $mediaId, 'status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 200),
                ]);
                return null;
            }

            $url = $resp->json('url');
            $mime = $resp->json('mime_type') ?: '';
            if (!$url) return null;

            // 2. Descargar el binario CON token
            $bin = \Illuminate\Support\Facades\Http::withToken($config->access_token)
                ->withOptions(['stream' => false])
                ->timeout(60)
                ->get($url);

            if (!$bin->successful() || strlen($bin->body()) < 50) {
                Log::warning('Meta media: falló descarga binaria', [
                    'media_id' => $mediaId, 'status' => $bin->status(),
                ]);
                return null;
            }

            // Determinar extensión por mime real (preferible al default)
            $ext = match (true) {
                str_contains($mime, 'jpeg')    => 'jpg',
                str_contains($mime, 'png')     => 'png',
                str_contains($mime, 'webp')    => 'webp',
                str_contains($mime, 'gif')     => 'gif',
                str_contains($mime, 'ogg')     => 'ogg',
                str_contains($mime, 'mpeg')    => 'mp3',
                str_contains($mime, 'mp4')     => 'mp4',
                str_contains($mime, 'amr')     => 'amr',
                str_contains($mime, 'pdf')     => 'pdf',
                default                        => $extDefault,
            };

            // Guardar en storage público (por tenant)
            $tenant = \App\Models\Tenant::find($config->tenant_id);
            $slug = $tenant?->slug ?: 'tenant-' . $config->tenant_id;
            $filename = "tenants/{$slug}/meta-inbound/{$mediaId}.{$ext}";
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $bin->body());

            $publicUrl = rtrim(config('app.url'), '/') . \Illuminate\Support\Facades\Storage::url($filename);

            Log::info('📥 Meta media descargado', [
                'media_id' => $mediaId, 'mime' => $mime, 'bytes' => strlen($bin->body()), 'url' => $publicUrl,
            ]);

            return $publicUrl;
        } catch (\Throwable $e) {
            Log::error('Meta media: excepción descarga: ' . $e->getMessage(), ['media_id' => $mediaId]);
            return null;
        }
    }
}
