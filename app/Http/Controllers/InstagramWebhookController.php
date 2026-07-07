<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\InstagramMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 📷 Webhook receiver para Instagram Direct Messages
 *
 * Meta envía aquí los eventos cuando un usuario:
 *   - Envía un DM
 *   - Reacciona a un mensaje nuestro
 *   - Lee un mensaje (read)
 *   - Comienza a escribir (typing)
 *
 * Estructura del payload similar a Messenger/WhatsApp pero con object='instagram'.
 * Docs: https://developers.facebook.com/docs/messenger-platform/instagram/webhook
 */
class InstagramWebhookController extends Controller
{
    public function __construct(private InstagramMessagingService $ig) {}

    /**
     * GET — verificación inicial del webhook (handshake con Meta)
     */
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = env('META_WEBHOOK_VERIFY_TOKEN', 'kivox-meta-verify');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * POST — recibe eventos de Instagram
     */
    public function receive(Request $request)
    {
        $payload = $request->all();

        // Solo procesamos eventos de Instagram
        if (($payload['object'] ?? '') !== 'instagram') {
            return response()->json(['ignored' => true]);
        }

        Log::info('📷 IG webhook recibido', ['payload' => $payload]);

        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = $entry['id'] ?? null;

            // Resolver tenant por page_id (API clásica) o por IG account id (API nueva).
            // En el API de Instagram (Instagram Login) el entry.id ES el id de la
            // cuenta de Instagram, no el de la página.
            $tenant = $this->ig->tenantPorPageId($pageId)
                ?? \App\Models\Tenant::withoutGlobalScopes()
                    ->where('instagram_business_account_id', $pageId)
                    ->where('instagram_activo', true)
                    ->first();
            if (!$tenant) {
                Log::warning('📷 IG webhook: tenant no encontrado', ['id' => $pageId]);
                continue;
            }

            // Formato 1: messaging[] (Messenger-like, IG Business Login viejo)
            foreach ($entry['messaging'] ?? [] as $event) {
                $this->procesarEvento($event, $tenant);
            }

            // Formato 2: changes[] (Instagram Graph API moderno)
            foreach ($entry['changes'] ?? [] as $cambio) {
                if (($cambio['field'] ?? '') !== 'messages') continue;
                $v = $cambio['value'] ?? [];

                // Re-estructurar al formato messaging
                $event = [
                    'sender'    => $v['sender']    ?? [],
                    'recipient' => $v['recipient'] ?? [],
                    'timestamp' => $v['timestamp'] ?? (time() * 1000),
                    'message'   => $v['message']   ?? [],
                ];
                $this->procesarEvento($event, $tenant);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Procesa un evento individual (mensaje, reacción, lectura, etc.)
     */
    private function procesarEvento(array $event, $tenant): void
    {
        $igsidUser = $event['sender']['id']  ?? null;
        $pageId    = $event['recipient']['id'] ?? null;

        if (!$igsidUser) return;

        // 0. EVENTOS DE ESTADO (entrega / lectura) — actualizan los ✓✓ de
        //    NUESTROS mensajes salientes. El cliente confirma que los vio.
        if (isset($event['read']) || isset($event['delivery'])) {
            $this->actualizarEstado($event, $tenant, $igsidUser);
            return;
        }

        // 0.b REACCIONES del cliente (❤️👍😂...) sobre un mensaje.
        if (isset($event['reaction'])) {
            $this->procesarReaccion($event, $tenant, $igsidUser);
            return;
        }

        // 1. Solo procesamos MENSAJES entrantes (no echoes propios, no reads, etc.)
        $msg = $event['message'] ?? null;
        if (!$msg || !empty($msg['is_echo'])) return;

        $texto = trim($msg['text'] ?? '');
        $mid   = $msg['mid'] ?? null;
        $tsMs  = $event['timestamp'] ?? (time() * 1000);

        if ($texto === '' && empty($msg['attachments'])) {
            Log::info('📷 IG msg vacío, ignorado', ['mid' => $mid]);
            return;
        }

        // 2. Encontrar/crear cliente (por IGSID en cliente.metadata)
        $perfil = $this->ig->obtenerPerfilUsuario($tenant, $igsidUser);
        $nombreCliente = $perfil['name'] ?? $perfil['username'] ?? "IG-{$igsidUser}";

        $cliente = Cliente::where('tenant_id', $tenant->id)
            ->where('telefono_normalizado', $igsidUser)  // usamos IGSID como "phone"
            ->first();

        if (!$cliente) {
            $cliente = Cliente::create([
                'tenant_id'            => $tenant->id,
                'nombre'               => $nombreCliente,
                'telefono'             => $igsidUser,
                'telefono_normalizado' => $igsidUser,
                'origen'               => 'instagram',
                'foto_url'             => $perfil['profile_pic'] ?? null,
            ]);
            Log::info('📷 Cliente IG creado', ['cliente_id' => $cliente->id, 'name' => $nombreCliente]);
        }

        // 3. Encontrar/crear conversación
        $conv = ConversacionWhatsapp::where('canal', 'instagram')
            ->where('igsid', $igsidUser)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$conv) {
            $conv = ConversacionWhatsapp::create([
                'tenant_id'            => $tenant->id,
                'cliente_id'           => $cliente->id,
                'canal'                => 'instagram',
                'igsid'                => $igsidUser,
                'telefono_normalizado' => $igsidUser,
                'estado'               => 'activa',
                'ultimo_mensaje_at'    => now(),
            ]);
            Log::info('📷 Conversación IG creada', ['conv_id' => $conv->id]);
        } else {
            $conv->update(['ultimo_mensaje_at' => now(), 'estado' => 'activa']);
        }

        // 4. Procesar adjuntos entrantes (imagen/audio/video/etc.).
        //    Descargamos el archivo del CDN de Meta (URL temporal) y lo
        //    rehospedamos público para que se vea siempre en el chat.
        $tipoMsg   = 'text';
        $mediaUrl  = null;
        $mediaMime = null;
        $adjuntos  = $msg['attachments'] ?? [];
        if (!empty($adjuntos)) {
            $att      = $adjuntos[0];
            $attType  = $att['type'] ?? 'file';           // image|audio|video|file|share|story_mention...
            $srcUrl   = $att['payload']['url'] ?? null;
            $tipoMsg  = in_array($attType, ['image', 'audio', 'video'], true) ? $attType : 'document';

            if ($srcUrl) {
                try {
                    $bin = \Illuminate\Support\Facades\Http::timeout(20)->get($srcUrl);
                    if ($bin->successful()) {
                        $mediaMime = $bin->header('Content-Type') ?: null;
                        $ext = match ($attType) {
                            'image' => 'jpg', 'audio' => 'mp4', 'video' => 'mp4', default => 'bin',
                        };
                        $path = "instagram-in/ig_" . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
                        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $bin->body());
                        $mediaUrl = rtrim(config('app.url'), '/') . \Illuminate\Support\Facades\Storage::url($path);
                    }
                } catch (\Throwable $e) {
                    Log::warning('📷 IG no se pudo descargar adjunto: ' . $e->getMessage());
                    $mediaUrl = $srcUrl; // fallback: URL temporal de Meta
                }
                if (!$mediaUrl) $mediaUrl = $srcUrl;
            }
        }

        // 4.b ¿El cliente respondió CITANDO un mensaje? Vinculamos la cita.
        $respondiendoAId = null;
        $replyToMid = $msg['reply_to']['mid'] ?? null;
        if ($replyToMid) {
            $citado = MensajeWhatsapp::where('mensaje_externo_id', $replyToMid)->first();
            if ($citado) $respondiendoAId = $citado->id;
        }

        // 5. Guardar mensaje entrante
        MensajeWhatsapp::create([
            'conversacion_id'    => $conv->id,
            'rol'                => MensajeWhatsapp::ROL_USER,
            'tipo'               => $tipoMsg,
            'contenido'          => $texto !== '' ? $texto : ($mediaUrl ? '' : ''),
            'mensaje_externo_id' => $mid,
            'respondiendo_a_mensaje_id' => $respondiendoAId,
            'meta'               => [
                'canal'       => 'instagram',
                'desde'       => $igsidUser,
                'hacia'       => $pageId,
                'media_url'   => $mediaUrl,
                'mime'        => $mediaMime,
                'attachments' => $adjuntos,
            ],
        ]);

        Log::info('📷 IG msg guardado', [
            'conv_id' => $conv->id,
            'texto'   => mb_substr($texto, 0, 80),
        ]);

        // 5. Marcar como LEÍDO en Instagram (el cliente verá que lo vimos) +
        //    actualizar los ✓✓ de la conversación.
        try {
            $this->ig->marcarLeido($tenant, $igsidUser, $mid);
        } catch (\Throwable $e) { /* no crítico */ }

        // 6. Disparar bot IA si la conversación está en modo bot (no humano)
        if (!$conv->atendida_por_humano) {
            try {
                // TODO: integrar con el BotResponderService existente
                // app(\App\Services\Bots\BotResponderService::class)->responder($conv, $texto);
                Log::info('📷 IG mensaje listo para bot (integración pendiente)', ['conv_id' => $conv->id]);
            } catch (\Throwable $e) {
                Log::error('📷 IG bot error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Procesa una reacción del cliente (❤️👍…) sobre un mensaje que le enviamos.
     * Payload IG: reaction => ['mid'=>..., 'action'=>'react'|'unreact', 'emoji'=>'❤️', 'reaction'=>'love']
     */
    private function procesarReaccion(array $event, $tenant, string $igsidUser): void
    {
        $r      = $event['reaction'] ?? [];
        $mid    = $r['mid'] ?? null;
        $accion = $r['action'] ?? 'react';
        $emoji  = $r['emoji'] ?? ($r['reaction'] ?? '❤️');
        if (!$mid) return;

        $mensaje = MensajeWhatsapp::where('mensaje_externo_id', $mid)->first();
        if (!$mensaje) {
            Log::info('📷 IG reacción: mensaje no encontrado', ['mid' => $mid]);
            return;
        }

        if ($accion === 'unreact') {
            $mensaje->update(['reaccion_cliente' => null, 'reaccion_cliente_at' => null]);
            Log::info('📷 IG reacción quitada', ['msg_id' => $mensaje->id]);
        } else {
            $mensaje->update(['reaccion_cliente' => $emoji, 'reaccion_cliente_at' => now()]);
            Log::info('📷 IG reacción recibida', ['msg_id' => $mensaje->id, 'emoji' => $emoji]);
        }
    }

    /**
     * Actualiza los ✓✓ de nuestros mensajes salientes cuando Instagram reporta
     * entrega (delivery) o lectura (read) por parte del cliente.
     */
    private function actualizarEstado(array $event, $tenant, string $igsidUser): void
    {
        $conv = ConversacionWhatsapp::where('canal', 'instagram')
            ->where('igsid', $igsidUser)
            ->where('tenant_id', $tenant->id)
            ->first();
        if (!$conv) return;

        // LECTURA → ACK_READ (✓✓ azul). Instagram manda read.mid del último visto.
        if (isset($event['read'])) {
            $mid = $event['read']['mid'] ?? null;
            $q = MensajeWhatsapp::where('conversacion_id', $conv->id)
                ->where('rol', MensajeWhatsapp::ROL_ASSISTANT)
                ->where('ack', '<', MensajeWhatsapp::ACK_READ);
            if ($mid) {
                // Marcar ese mensaje y todos los anteriores nuestros como leídos.
                $ref = MensajeWhatsapp::where('mensaje_externo_id', $mid)->first();
                if ($ref) $q->where('id', '<=', $ref->id);
            }
            $n = $q->update(['ack' => MensajeWhatsapp::ACK_READ]);
            if ($n) Log::info('📷 IG mensajes marcados LEÍDOS', ['conv_id' => $conv->id, 'n' => $n]);
            return;
        }

        // ENTREGA → ACK_DELIVERED (✓✓ gris).
        if (isset($event['delivery'])) {
            $mids = $event['delivery']['mids'] ?? [];
            $q = MensajeWhatsapp::where('conversacion_id', $conv->id)
                ->where('rol', MensajeWhatsapp::ROL_ASSISTANT)
                ->where('ack', '<', MensajeWhatsapp::ACK_DELIVERED);
            if (!empty($mids)) $q->whereIn('mensaje_externo_id', $mids);
            $q->update(['ack' => MensajeWhatsapp::ACK_DELIVERED]);
        }
    }
}
