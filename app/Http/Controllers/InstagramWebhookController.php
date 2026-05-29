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

            // Resolver tenant por page_id / IG business account id
            $tenant = $this->ig->tenantPorPageId($pageId);
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

        // 4. Guardar mensaje entrante
        MensajeWhatsapp::create([
            'conversacion_id'      => $conv->id,
            'tenant_id'            => $tenant->id,
            'mid'                  => $mid,
            'desde'                => $igsidUser,
            'hacia'                => $pageId,
            'tipo'                 => empty($msg['attachments']) ? 'text' : 'media',
            'texto'                => $texto,
            'direccion'            => 'entrante',
            'created_at'           => \Carbon\Carbon::createFromTimestampMs($tsMs),
            'metadatos'            => json_encode([
                'canal' => 'instagram',
                'attachments' => $msg['attachments'] ?? [],
            ]),
        ]);

        Log::info('📷 IG msg guardado', [
            'conv_id' => $conv->id,
            'texto'   => mb_substr($texto, 0, 80),
        ]);

        // 5. Disparar bot IA si la conversación está en modo bot (no humano)
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
}
