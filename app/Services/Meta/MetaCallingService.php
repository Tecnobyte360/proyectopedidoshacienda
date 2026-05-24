<?php

namespace App\Services\Meta;

use App\Models\Conversacion;
use App\Models\MetaWhatsappConfig;
use App\Models\WhatsappCall;
use App\Models\WhatsappCallPermission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 📞 Cliente HTTP de Meta WhatsApp Business Calling API.
 *
 * Endpoints (v25.0):
 *  - POST /{phone_number_id}/calls               → iniciar (con SDP offer)
 *  - POST /{phone_number_id}/calls (action=hangup) → colgar
 *  - POST /{phone_number_id}/calls (action=accept con sdp) → aceptar entrante
 *  - POST /{phone_number_id}/calls (action=reject)  → rechazar entrante
 *
 * Webhooks que recibimos (field: 'calls'):
 *  - call_status: ringing | connecting | connected | terminated
 *  - call_permission_update: accept | reject
 *
 * REQUISITOS Meta:
 *  - Calling API debe estar habilitado por Meta en la WABA (formulario soporte)
 *  - Para llamadas SALIENTES: cliente debe haber aceptado call_permission_request
 *  - Token con scope whatsapp_business_messaging
 */
class MetaCallingService
{
    /**
     * Inicia una llamada saliente al cliente.
     *
     * @param string  $telefono   Telefono E.164 sin '+'
     * @param string  $sdpOffer   SDP offer generado por el navegador del operador (RTCPeerConnection)
     * @param int     $tenantId
     * @param int     $operadorId user_id del operador que llama
     * @param int|null $conversacionId
     * @return WhatsappCall|null  Registro creado, null si Meta rechazó
     */
    public function iniciar(
        string $telefono,
        string $sdpOffer,
        int $tenantId,
        int $operadorId,
        ?int $conversacionId = null
    ): ?WhatsappCall {
        $config = $this->resolverConfig($tenantId);
        if (!$config) {
            Log::warning('📞 MetaCalling: sin config Meta', ['tenant_id' => $tenantId]);
            return null;
        }

        $telefono = $this->normalizar($telefono);
        $clienteId = null;
        if ($conversacionId) {
            $conv = Conversacion::withoutGlobalScopes()->find($conversacionId);
            $clienteId = $conv?->cliente_id;
        }

        $call = WhatsappCall::create([
            'tenant_id'        => $tenantId,
            'conversacion_id'  => $conversacionId,
            'operador_user_id' => $operadorId,
            'cliente_id'       => $clienteId,
            'telefono'         => $telefono,
            'direccion'        => WhatsappCall::DIR_OUT,
            'phone_number_id'  => $config->phone_number_id,
            'estado'           => WhatsappCall::ESTADO_REQUESTED,
            'sdp_offer'        => $sdpOffer,
            'requested_at'     => now(),
        ]);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $telefono,
            'action'            => 'connect',
            'session' => [
                'sdp_type' => 'offer',
                'sdp'      => $sdpOffer,
            ],
        ];

        try {
            $resp = Http::withToken($config->access_token)
                ->acceptJson()
                ->timeout(20)
                ->post($this->endpointCalls($config), $payload);

            if ($resp->successful()) {
                $callId = $resp->json('calls.0.id');
                $call->update([
                    'call_id'      => $callId,
                    'estado'       => WhatsappCall::ESTADO_RINGING,
                    'ringing_at'   => now(),
                    'meta_payload' => $resp->json(),
                ]);
                Log::info('📞 Meta Calling: llamada iniciada', [
                    'tenant_id' => $tenantId,
                    'call_id'   => $callId,
                    'to'        => $telefono,
                ]);
                return $call;
            }

            $errMsg = $resp->json('error.message') ?? $resp->body();
            $errCode = $resp->json('error.code');
            $estadoFinal = WhatsappCall::ESTADO_FAILED;

            // Meta devuelve un código específico cuando falta permiso del cliente
            if (str_contains(strtolower((string) $errMsg), 'permission')) {
                $estadoFinal = WhatsappCall::ESTADO_NO_PERMISSION;
            }

            $call->update([
                'estado'    => $estadoFinal,
                'error_msg' => "[{$errCode}] " . mb_substr((string) $errMsg, 0, 500),
                'ended_at'  => now(),
            ]);
            Log::warning('📞 Meta Calling: fallo iniciar', [
                'tenant_id' => $tenantId,
                'status'    => $resp->status(),
                'body'      => mb_substr($resp->body(), 0, 500),
            ]);
            return $call;
        } catch (\Throwable $e) {
            $call->update([
                'estado'    => WhatsappCall::ESTADO_FAILED,
                'error_msg' => mb_substr($e->getMessage(), 0, 500),
                'ended_at'  => now(),
            ]);
            Log::error('📞 Meta Calling exception', ['error' => $e->getMessage()]);
            return $call;
        }
    }

    /**
     * Cuelga una llamada activa.
     */
    public function colgar(WhatsappCall $call): bool
    {
        $config = $this->resolverConfig($call->tenant_id);
        if (!$config || !$call->call_id) return false;

        try {
            $resp = Http::withToken($config->access_token)
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpointCalls($config), [
                    'messaging_product' => 'whatsapp',
                    'call_id' => $call->call_id,
                    'action'  => 'terminate',
                ]);

            if ($resp->successful()) {
                $this->finalizar($call, WhatsappCall::ESTADO_ENDED, 'operador_colgo');
                return true;
            }
            Log::warning('📞 Meta Calling: fallo colgar', [
                'call_id' => $call->call_id,
                'body'    => mb_substr($resp->body(), 0, 300),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('📞 Meta Calling colgar exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Acepta una llamada ENTRANTE respondiendo el SDP answer.
     */
    public function aceptarEntrante(WhatsappCall $call, string $sdpAnswer): bool
    {
        $config = $this->resolverConfig($call->tenant_id);
        if (!$config || !$call->call_id) return false;

        try {
            $resp = Http::withToken($config->access_token)
                ->acceptJson()
                ->timeout(20)
                ->post($this->endpointCalls($config), [
                    'messaging_product' => 'whatsapp',
                    'call_id' => $call->call_id,
                    'action'  => 'pre_accept',
                    'session' => [
                        'sdp_type' => 'answer',
                        'sdp'      => $sdpAnswer,
                    ],
                ]);

            if ($resp->successful()) {
                $call->update([
                    'sdp_answer'  => $sdpAnswer,
                    'estado'      => WhatsappCall::ESTADO_CONNECTING,
                    'connected_at'=> $call->connected_at ?: now(),
                ]);
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            Log::error('📞 Meta Calling aceptar exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Rechaza una llamada entrante.
     */
    public function rechazar(WhatsappCall $call): bool
    {
        $config = $this->resolverConfig($call->tenant_id);
        if (!$config || !$call->call_id) return false;

        try {
            Http::withToken($config->access_token)
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpointCalls($config), [
                    'messaging_product' => 'whatsapp',
                    'call_id' => $call->call_id,
                    'action'  => 'reject',
                ]);
            $this->finalizar($call, WhatsappCall::ESTADO_REJECTED, 'operador_rechazo');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Envía solicitud de permiso de llamada al cliente.
     * El cliente verá un botón "Permitir / Bloquear" en su WhatsApp.
     * La respuesta llega vía webhook call_permission_updates.
     *
     * Endpoint: POST /{phone_id}/call_permission_requests
     */
    public function solicitarPermiso(string $telefono, int $tenantId): bool
    {
        $config = $this->resolverConfig($tenantId);
        if (!$config) return false;

        $telefono = $this->normalizar($telefono);
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/call_permission_requests',
            $config->api_version ?: 'v25.0',
            $config->phone_number_id
        );

        try {
            $resp = Http::withToken($config->access_token)
                ->acceptJson()
                ->timeout(15)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $telefono,
                ]);

            if ($resp->successful()) {
                WhatsappCallPermission::updateOrCreate(
                    ['tenant_id' => $tenantId, 'telefono' => $telefono],
                    [
                        'estado'        => WhatsappCallPermission::PENDING,
                        'solicitado_at' => now(),
                        'payload'       => $resp->json(),
                    ]
                );
                Log::info('📞 Permiso de llamada solicitado', [
                    'tenant_id' => $tenantId,
                    'to'        => $telefono,
                ]);
                return true;
            }
            // Detectar caso típico: Calling API no habilitado en WABA
            $errMsg = (string) $resp->json('error.message', '');
            $errCode = $resp->json('error.code');
            $hint = '';
            if ($errCode === 2500 || str_contains(strtolower($errMsg), 'unknown path')) {
                $hint = ' → Calling API NO está habilitada en esta WABA. Solicítalo a Meta (Tarea #50).';
            }
            Log::warning('📞 Fallo solicitar permiso' . $hint, [
                'status' => $resp->status(),
                'code'   => $errCode,
                'body'   => mb_substr($resp->body(), 0, 400),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('📞 Exception solicitar permiso', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** ¿El cliente tiene permiso vigente para que lo llamemos? */
    public function tienePermiso(string $telefono, int $tenantId): bool
    {
        $perm = WhatsappCallPermission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('telefono', $this->normalizar($telefono))
            ->first();
        return $perm && $perm->vigente();
    }

    /**
     * Procesa webhook de calls que llega de Meta.
     * Estructura: entry[].changes[].value (field='calls').
     */
    public function procesarWebhook(array $value, int $tenantId): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        // 1. Llamada entrante nueva
        foreach ($value['calls'] ?? [] as $callData) {
            $callId = $callData['id'] ?? null;
            if (!$callId) continue;

            $event = $callData['event'] ?? null; // 'connect', 'terminate', etc

            $call = WhatsappCall::withoutGlobalScopes()
                ->where('call_id', $callId)
                ->first();

            // ENTRANTE nueva (no la teníamos en DB)
            if (!$call && $event === 'connect') {
                $call = WhatsappCall::create([
                    'tenant_id'       => $tenantId,
                    'telefono'        => $callData['from'] ?? '',
                    'direccion'       => WhatsappCall::DIR_IN,
                    'call_id'         => $callId,
                    'phone_number_id' => $phoneNumberId,
                    'estado'          => WhatsappCall::ESTADO_RINGING,
                    'sdp_offer'       => $callData['session']['sdp'] ?? null,
                    'requested_at'    => isset($callData['timestamp'])
                        ? Carbon::createFromTimestamp((int)$callData['timestamp'])
                        : now(),
                    'ringing_at'      => now(),
                    'meta_payload'    => $callData,
                ]);
                // TODO: emitir broadcast a chat para notificar operadores
                continue;
            }

            if (!$call) continue;

            // Actualizar estado según evento
            $patch = ['meta_payload' => $callData];
            switch ($event) {
                case 'accept':
                case 'pre_accept':
                    $patch['estado'] = WhatsappCall::ESTADO_CONNECTED;
                    $patch['connected_at'] = $call->connected_at ?: now();
                    break;
                case 'terminate':
                    $patch['estado']   = WhatsappCall::ESTADO_ENDED;
                    $patch['ended_at'] = now();
                    if ($call->connected_at) {
                        $patch['duracion_seg'] = now()->diffInSeconds($call->connected_at);
                    }
                    if ($costo = ($callData['pricing']['cost'] ?? null)) {
                        $patch['costo_usd'] = $costo;
                        $patch['moneda']    = $callData['pricing']['currency'] ?? 'USD';
                    }
                    break;
                case 'reject':
                    $patch['estado']   = WhatsappCall::ESTADO_REJECTED;
                    $patch['ended_at'] = now();
                    break;
            }
            $call->update($patch);

            Log::info('📞 Meta Calling webhook', [
                'call_id' => $callId,
                'event'   => $event,
                'estado'  => $patch['estado'] ?? $call->estado,
            ]);
        }

        // 2. Actualización de permiso de llamada (call_permission_update)
        foreach ($value['call_permission_updates'] ?? [] as $upd) {
            $tel      = $this->normalizar((string) ($upd['from'] ?? ''));
            $response = strtolower((string) ($upd['response'] ?? '')); // accept|reject
            if (!$tel) continue;

            $estado = match ($response) {
                'accept'  => WhatsappCallPermission::ACCEPTED,
                'reject'  => WhatsappCallPermission::REJECTED,
                default   => WhatsappCallPermission::PENDING,
            };

            // Meta envía expiration_timestamp cuando es accept
            $expira = null;
            if (!empty($upd['expiration_timestamp'])) {
                $expira = Carbon::createFromTimestamp((int) $upd['expiration_timestamp']);
            } elseif ($estado === WhatsappCallPermission::ACCEPTED) {
                $expira = now()->addDays(7); // fallback razonable
            }

            WhatsappCallPermission::updateOrCreate(
                ['tenant_id' => $tenantId, 'telefono' => $tel],
                [
                    'estado'        => $estado,
                    'respondido_at' => now(),
                    'expira_at'     => $expira,
                    'payload'       => $upd,
                ]
            );

            Log::info('📞 Call permission update', [
                'tenant_id' => $tenantId,
                'from'      => $tel,
                'response'  => $response,
                'expira_at' => $expira?->toDateTimeString(),
            ]);
        }
    }

    private function finalizar(WhatsappCall $call, string $estado, string $motivo): void
    {
        $patch = [
            'estado'         => $estado,
            'motivo_termino' => $motivo,
            'ended_at'       => now(),
        ];
        if ($call->connected_at) {
            $patch['duracion_seg'] = now()->diffInSeconds($call->connected_at);
        }
        $call->update($patch);
    }

    private function endpointCalls(MetaWhatsappConfig $config): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/calls',
            $config->api_version ?: 'v25.0',
            $config->phone_number_id
        );
    }

    private function resolverConfig(?int $tenantId): ?MetaWhatsappConfig
    {
        if (!$tenantId) return MetaWhatsappConfig::activaActual();
        return MetaWhatsappConfig::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('activo', true)
            ->first();
    }

    private function normalizar(string $tel): string
    {
        return preg_replace('/\D+/', '', $tel) ?: $tel;
    }
}
