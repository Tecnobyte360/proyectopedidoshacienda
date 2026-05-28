<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 💳 Servicio Bold por tenant.
 *
 * Genera links de pago via Bold Payment Links API y valida firmas de webhooks.
 * Toda la configuración (api_key, secret, modo) vive en el tenant.
 *
 * Docs: https://developers.bold.co/payments/payment-link
 */
class BoldService
{
    private const API_BASE_PROD = 'https://payments.api.bold.co';
    private const API_BASE_TEST = 'https://payments.api.bold.co'; // Bold no separa hosts; el modo se distingue por la api_key

    public function __construct(private ?Tenant $tenant = null) {}

    public function paraTenant(Tenant $tenant): self
    {
        $this->tenant = $tenant;
        return $this;
    }

    private function tenantActual(): ?Tenant
    {
        return $this->tenant;
    }

    private function apiBase(): string
    {
        return ($this->tenantActual()?->bold_modo === 'production')
            ? self::API_BASE_PROD
            : self::API_BASE_TEST;
    }

    public function configurado(): bool
    {
        $t = $this->tenantActual();
        return $t && $t->bold_activo && !empty($t->bold_api_key);
    }

    /**
     * Genera (o reutiliza) link de pago de Bold para un pedido.
     */
    public function urlPago(Pedido $pedido, bool $forzarRotacion = false): ?string
    {
        $tenant = $this->tenantActual();
        if (!$this->configurado()) return null;

        // Si ya pagado → no rotar
        if ($pedido->estado_pago === 'aprobado') {
            return null;
        }

        // Si ya hay link y no se fuerza rotación → devolverlo
        if (!$forzarRotacion
            && !empty($pedido->bold_payment_link)
            && empty($pedido->bold_transaction_id)
            && !in_array($pedido->estado_pago, ['rechazado','fallido'], true)
        ) {
            return $pedido->bold_payment_link;
        }

        // Crear nuevo Payment Link en Bold
        $resp = Http::withHeaders([
            'Authorization' => 'x-api-key ' . $tenant->bold_api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])
            ->timeout(15)
            ->post($this->apiBase() . '/v2/payment-link', [
                'amount_type'  => 'CLOSE',  // monto cerrado (el cliente NO puede modificarlo)
                'amount' => [
                    'currency'     => 'COP',
                    'total_amount' => (int) $pedido->total,
                    'tip_amount'   => 0,
                    'tax_total'    => 0,
                ],
                'description'  => 'Pedido #' . $pedido->id . ' - ' . ($tenant->nombre ?? 'Comercio'),
                'callback_url' => url('/pedidos/' . $pedido->id . '/gracias'),
                'expiration_date' => now()->addDays(7)->getTimestampMs(),
                'payment_methods' => ['CREDIT_CARD','PSE','NEQUI','BANCOLOMBIA','BTN_BANCOLOMBIA'],
            ]);

        if ($resp->failed()) {
            Log::error('💳 Bold link de pago falló', [
                'tenant_id' => $tenant->id,
                'pedido_id' => $pedido->id,
                'status'    => $resp->status(),
                'body'      => substr($resp->body(), 0, 500),
            ]);
            return null;
        }

        $data = $resp->json();
        $url   = $data['url']        ?? $data['payment_link'] ?? null;
        $payId = $data['payment_link'] ?? $data['id']           ?? null;

        if (!$url) return null;

        $pedido->bold_payment_link    = $url;
        $pedido->bold_payment_id      = $payId;
        $pedido->bold_transaction_id  = null;
        $pedido->pasarela_usada       = 'bold';
        if ($pedido->estado_pago === null || in_array($pedido->estado_pago, ['rechazado','fallido'], true)) {
            $pedido->estado_pago = 'pendiente';
        }
        $pedido->save();

        Log::info('💳 Bold link generado', [
            'tenant_id' => $tenant->id,
            'pedido_id' => $pedido->id,
            'payment_id'=> $payId,
        ]);

        return $url;
    }

    /**
     * Valida la firma HMAC-SHA256 del webhook de Bold.
     *
     * Bold envía header: x-bold-signature
     * Firma = HMAC_SHA256(webhook_secret, raw_body)
     */
    public function validarFirma(string $rawBody, string $firmaRecibida): bool
    {
        $tenant = $this->tenantActual();
        if (!$tenant || empty($tenant->bold_webhook_secret)) {
            return false;
        }

        $firmaCalculada = hash_hmac('sha256', $rawBody, $tenant->bold_webhook_secret);
        return hash_equals($firmaCalculada, $firmaRecibida);
    }

    /**
     * Procesa un evento de webhook de Bold y actualiza el pedido.
     *
     * Tipos de evento:
     *  - SALE_APPROVED  → pago aprobado
     *  - SALE_REJECTED  → pago rechazado
     *  - SALE_FAILED    → fallo técnico
     *  - VOID_APPROVED  → reversión
     */
    public function procesarEvento(array $payload): array
    {
        $tenant = $this->tenantActual();
        $tipo   = $payload['type']        ?? '';
        $meta   = $payload['metadata']    ?? [];
        $data   = $payload['data']        ?? $payload['subject'] ?? [];

        $payId  = $data['payment_id']     ?? $data['merchant_id'] ?? $meta['payment_link'] ?? null;
        $txId   = $data['transaction_id'] ?? $data['id']          ?? null;

        if (!$payId) {
            return ['ok' => false, 'error' => 'No payment_id en payload'];
        }

        $pedido = Pedido::where('tenant_id', $tenant?->id)
            ->where('bold_payment_id', $payId)
            ->first();

        if (!$pedido) {
            return ['ok' => false, 'error' => "Pedido no encontrado para payment_id={$payId}"];
        }

        $estadoNuevo = match($tipo) {
            'SALE_APPROVED'  => 'aprobado',
            'SALE_REJECTED'  => 'rechazado',
            'SALE_FAILED'    => 'fallido',
            'VOID_APPROVED'  => 'reversado',
            default          => $pedido->estado_pago,
        };

        $pedido->update([
            'estado_pago'         => $estadoNuevo,
            'bold_transaction_id' => $txId,
            'pago_metodo'         => $data['payment_method'] ?? $pedido->pago_metodo,
            'pago_at'             => $estadoNuevo === 'aprobado' ? now() : $pedido->pago_at,
        ]);

        Log::info('💳 Bold evento procesado', [
            'tenant_id' => $tenant?->id,
            'pedido_id' => $pedido->id,
            'tipo'      => $tipo,
            'estado_nuevo' => $estadoNuevo,
        ]);

        return [
            'ok'        => true,
            'pedido_id' => $pedido->id,
            'estado'    => $estadoNuevo,
        ];
    }
}
