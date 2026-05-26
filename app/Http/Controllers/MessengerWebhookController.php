<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ConversacionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\MessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 💬 Webhook receiver para Facebook Messenger
 *
 * Estructura del payload: object='page', entry[].messaging[]
 * Casi idéntica a Instagram, solo cambia el object y los IDs.
 *
 * Docs: https://developers.facebook.com/docs/messenger-platform/webhooks
 */
class MessengerWebhookController extends Controller
{
    public function __construct(private MessengerService $msg) {}

    /**
     * GET — handshake con Meta
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
     * POST — recibe eventos de Messenger
     */
    public function receive(Request $request)
    {
        $payload = $request->all();

        if (($payload['object'] ?? '') !== 'page') {
            return response()->json(['ignored' => true]);
        }

        Log::info('💬 Messenger webhook recibido', ['payload' => $payload]);

        foreach ($payload['entry'] ?? [] as $entry) {
            $pageId = $entry['id'] ?? null;

            $tenant = $this->msg->tenantPorPageId($pageId);
            if (!$tenant) {
                Log::warning('💬 Messenger webhook: tenant no encontrado', ['page_id' => $pageId]);
                continue;
            }

            // Si el tenant tiene Messenger desactivado, ignoramos
            if (!$tenant->messenger_activo) {
                continue;
            }

            foreach ($entry['messaging'] ?? [] as $event) {
                $this->procesarEvento($event, $tenant);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function procesarEvento(array $event, $tenant): void
    {
        $psidUser = $event['sender']['id']    ?? null;
        $pageId   = $event['recipient']['id'] ?? null;

        if (!$psidUser) return;

        $msg = $event['message'] ?? null;
        if (!$msg || !empty($msg['is_echo'])) return;

        $texto = trim($msg['text'] ?? '');
        $mid   = $msg['mid'] ?? null;
        $tsMs  = $event['timestamp'] ?? (time() * 1000);

        if ($texto === '' && empty($msg['attachments'])) {
            Log::info('💬 Messenger msg vacío, ignorado', ['mid' => $mid]);
            return;
        }

        // 1. Cliente
        $perfil = $this->msg->obtenerPerfilUsuario($tenant, $psidUser);
        $nombreCliente = $perfil['name'] ?? "FB-{$psidUser}";

        $cliente = Cliente::where('tenant_id', $tenant->id)
            ->where('telefono_normalizado', $psidUser)
            ->first();

        if (!$cliente) {
            $cliente = Cliente::create([
                'tenant_id'            => $tenant->id,
                'nombre'               => $nombreCliente,
                'telefono_normalizado' => $psidUser,
                'origen'               => 'messenger',
                'foto_url'             => $perfil['profile_pic'] ?? null,
            ]);
            Log::info('💬 Cliente Messenger creado', [
                'cliente_id' => $cliente->id,
                'name'       => $nombreCliente,
            ]);
        }

        // 2. Conversación
        $conv = ConversacionWhatsapp::where('canal', 'messenger')
            ->where('psid', $psidUser)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$conv) {
            $conv = ConversacionWhatsapp::create([
                'tenant_id'            => $tenant->id,
                'cliente_id'           => $cliente->id,
                'canal'                => 'messenger',
                'psid'                 => $psidUser,
                'telefono_normalizado' => $psidUser,
                'estado'               => 'activa',
                'ultimo_mensaje_at'    => now(),
            ]);
            Log::info('💬 Conversación Messenger creada', ['conv_id' => $conv->id]);
        } else {
            $conv->update(['ultimo_mensaje_at' => now(), 'estado' => 'activa']);
        }

        // 3. Mensaje
        MensajeWhatsapp::create([
            'conversacion_id' => $conv->id,
            'tenant_id'       => $tenant->id,
            'mid'             => $mid,
            'desde'           => $psidUser,
            'hacia'           => $pageId,
            'tipo'            => empty($msg['attachments']) ? 'text' : 'media',
            'texto'           => $texto,
            'direccion'       => 'entrante',
            'created_at'      => \Carbon\Carbon::createFromTimestampMs($tsMs),
            'metadatos'       => json_encode([
                'canal'       => 'messenger',
                'attachments' => $msg['attachments'] ?? [],
            ]),
        ]);

        Log::info('💬 Messenger msg guardado', [
            'conv_id' => $conv->id,
            'texto'   => mb_substr($texto, 0, 80),
        ]);

        // 4. Bot (futuro)
        if (!$conv->atendida_por_humano) {
            try {
                // TODO: integrar BotResponderService
                Log::info('💬 Messenger msg listo para bot (integración pendiente)', ['conv_id' => $conv->id]);
            } catch (\Throwable $e) {
                Log::error('💬 Messenger bot error: ' . $e->getMessage());
            }
        }
    }
}
