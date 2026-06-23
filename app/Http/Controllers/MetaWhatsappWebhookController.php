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

        // 👍 REACCIÓN: el cliente reaccionó a un mensaje nuestro.
        // No va al pipeline del bot — solo actualiza la BD.
        if ($tipo === 'reaction') {
            $reactedToWamid = $msg['reaction']['message_id'] ?? null;
            $emoji          = $msg['reaction']['emoji'] ?? '';
            if (!$reactedToWamid) return;

            try {
                $mensaje = \App\Models\MensajeWhatsapp::withoutGlobalScopes()
                    ->where('mensaje_externo_id', $reactedToWamid)
                    ->first();

                if ($mensaje) {
                    $mensaje->update([
                        'reaccion_cliente'    => $emoji ?: null,
                        'reaccion_cliente_at' => $emoji ? now() : null,
                    ]);
                    \Log::info('👍 Reacción de cliente persistida', [
                        'wamid' => $reactedToWamid,
                        'emoji' => $emoji ?: '(quitada)',
                        'mensaje_id' => $mensaje->id,
                    ]);
                }

                // 📊 Tracking campaña: si reaccionaron al mensaje de una campaña
                \App\Models\CampanaDestinatario::query()
                    ->withoutGlobalScopes()
                    ->where('mensaje_externo_id', $reactedToWamid)
                    ->update([
                        'reaccion'    => $emoji ?: null,
                        'reaccion_at' => $emoji ? now() : null,
                    ]);

                if (!$mensaje) {
                    \Log::info('👍 Reacción recibida pero mensaje no encontrado', [
                        'wamid' => $reactedToWamid,
                        'emoji' => $emoji,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::error('Error procesando reacción Meta: ' . $e->getMessage());
            }
            return; // no continuar al pipeline normal
        }

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
                // Botón / lista (interactive — NO viene de plantillas, sino de menús creados al vuelo)
                $body = $msg['interactive']['button_reply']['title']
                     ?? $msg['interactive']['list_reply']['title']
                     ?? '';
                break;
            case 'button':
                // Quick Reply de PLANTILLA. Meta envía el texto del botón en button.text.
                $body = $msg['button']['text'] ?? $msg['button']['payload'] ?? '';
                break;
            case 'location':
                $lat = $msg['location']['latitude'] ?? null;
                $lng = $msg['location']['longitude'] ?? null;
                $nom = $msg['location']['name'] ?? $msg['location']['address'] ?? '';
                $body = '📍 Ubicación' . ($nom ? ": {$nom}" : '')
                     . ($lat && $lng ? " (https://maps.google.com/?q={$lat},{$lng})" : '');
                break;
            case 'contacts':
                $nom = $msg['contacts'][0]['name']['formatted_name'] ?? 'contacto';
                $tel = $msg['contacts'][0]['phones'][0]['phone'] ?? '';
                $body = "👤 Contacto compartido: {$nom}" . ($tel ? " ({$tel})" : '');
                break;
            case 'sticker':
                $body = '[sticker]';
                break;
            case 'unsupported':
                $body = '⚠️ El cliente envió un mensaje no compatible con WhatsApp (ej. encuesta, ubicación en vivo o un tipo nuevo). Pídele que lo reenvíe como texto, foto o documento.';
                break;
            default:
                $body = "⚠️ Mensaje no soportado por ahora (tipo: {$tipo}).";
        }

        // 💬 Si el cliente está respondiendo a un mensaje específico, Meta envía
        // context.id con el wamid del mensaje original. Buscamos ese mensaje
        // localmente y guardamos el link para renderizar la cita en /chat.
        $respondiendoAId = null;
        $contextWamid = $msg['context']['id'] ?? null;
        if ($contextWamid) {
            try {
                $msgCitado = \App\Models\MensajeWhatsapp::withoutGlobalScopes()
                    ->where('mensaje_externo_id', $contextWamid)
                    ->first();
                $respondiendoAId = $msgCitado?->id;
            } catch (\Throwable $e) { /* no es crítico */ }
        }

        // 📊 Tracking campaña: si esta respuesta apunta a un mensaje de campaña,
        // marcamos respondio_at + (si es click de botón) boton_click.
        if ($contextWamid) {
            try {
                $destinatario = \App\Models\CampanaDestinatario::query()
                    ->withoutGlobalScopes()
                    ->where('mensaje_externo_id', $contextWamid)
                    ->first();

                if ($destinatario) {
                    $update = [
                        'respondio_at'     => $destinatario->respondio_at ?? now(),
                        'respuestas_count' => (int) $destinatario->respuestas_count + 1,
                    ];

                    // Si fue click de Quick Reply / List → guardar texto del botón
                    $botonTexto = $msg['interactive']['button_reply']['title']
                               ?? $msg['interactive']['list_reply']['title']
                               ?? ($tipo === 'button' ? ($msg['button']['text'] ?? null) : null);

                    if ($botonTexto) {
                        $botonTexto = mb_substr($botonTexto, 0, 60);

                        // Primer click: marcar boton_click + boton_click_at
                        if (!$destinatario->boton_click) {
                            $update['boton_click']    = $botonTexto;
                            $update['boton_click_at'] = now();
                        }

                        // Historial: agregar este click al array JSON
                        $historial = is_array($destinatario->botones_clicks) ? $destinatario->botones_clicks : [];
                        $historial[] = ['boton' => $botonTexto, 'at' => now()->toDateTimeString()];
                        $update['botones_clicks'] = $historial;
                    }

                    $destinatario->update($update);
                }
            } catch (\Throwable $e) {
                Log::warning('Tracking campaña respuesta falló: ' . $e->getMessage());
            }
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
                // 💬 Si es respuesta a otro mensaje, lo pasamos para que el legacy
                // lo persista en respondiendo_a_mensaje_id
                'respondiendoAMensajeId' => $respondiendoAId,
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
                // Solo subir el ack, nunca bajarlo (read > delivered > sent)
                \App\Models\MensajeWhatsapp::query()
                    ->withoutGlobalScopes()
                    ->where('mensaje_externo_id', $waId)
                    ->where('ack', '<', $ack)
                    ->update(['ack' => $ack]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo actualizar ack Meta: ' . $e->getMessage());
            }

            // 📊 Tracking de campañas: marcar entregado_at / leido_at en destinatarios
            try {
                if ($ack === 2) {
                    \App\Models\CampanaDestinatario::query()
                        ->withoutGlobalScopes()
                        ->where('mensaje_externo_id', $waId)
                        ->whereNull('entregado_at')
                        ->update(['entregado_at' => now()]);
                } elseif ($ack === 3) {
                    \App\Models\CampanaDestinatario::query()
                        ->withoutGlobalScopes()
                        ->where('mensaje_externo_id', $waId)
                        ->update([
                            'entregado_at' => \DB::raw('COALESCE(entregado_at, NOW())'),
                            'leido_at'     => \DB::raw('COALESCE(leido_at, NOW())'),
                        ]);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo actualizar tracking campaña: ' . $e->getMessage());
            }
        }

        // 💰 Persistir evento de billing — TODAS las conversaciones (billable o no).
        // Las gratis (billable=false) también nos sirven para ver volumen por categoría.
        $pricing      = $status['pricing'] ?? null;
        $conversation = $status['conversation'] ?? null;
        $convId       = $conversation['id'] ?? null;

        // Fallback: Meta a veces no manda conversation.id (sobre todo para free service).
        // Sintetizamos uno por (recipient_id + día + categoría) para deduplicar
        // razonablemente bien y aún ver el volumen real.
        if ($pricing && !$convId) {
            $recipient = $status['recipient_id'] ?? 'unknown';
            $cat       = $pricing['category'] ?? 'service';
            $day       = isset($status['timestamp'])
                ? date('Ymd', (int) $status['timestamp'])
                : now()->format('Ymd');
            $convId    = "synth:{$recipient}:{$day}:{$cat}";
        }

        if ($pricing && $convId) {
            $billable  = (bool) ($pricing['billable'] ?? false);
            $categoria = $pricing['category']
                ?? ($conversation['origin']['type'] ?? null)
                ?? 'service';

            // Tabla de precios Colombia (USD por conversación). 0 si no es billable.
            $tarifaCo = [
                'service'              => 0.000,
                'utility'              => 0.0080,
                'authentication'       => 0.0080,
                'marketing'            => 0.0265,
                'referral_conversion'  => 0.000,
                'free_customer_service'=> 0.000,
                'free_entry_point'     => 0.000,
            ];
            $cost = $billable ? ($tarifaCo[$categoria] ?? 0) : 0;

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
                            'billable'      => $billable,
                            'cost_usd'      => $cost,
                            'moneda'        => 'USD',
                            'origin_type'   => $conversation['origin']['type'] ?? ($pricing['type'] ?? null),
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
