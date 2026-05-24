<?php

namespace App\Services\SaasBilling;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 💳 Wompi del DUEÑO de Kivox (TecnoByte360) para cobrar mensualidades
 * a los tenants. Análogo a App\Services\WompiService pero apuntando a
 * config('saas.wompi') en lugar de tenant->wompi_config.
 *
 * Web Checkout docs: https://docs.wompi.co/docs/colombia/widget-checkout-web/
 * Eventos docs:      https://docs.wompi.co/docs/colombia/eventos/
 */
class SaasBillingWompiService
{
    private const CHECKOUT_URL = 'https://checkout.wompi.co/p/';

    /**
     * Genera (o reutiliza) la URL de pago para una mensualidad SaaS.
     * Si el pago aún no tiene wompi_reference se le asigna una nueva.
     *
     * Devuelve null si no hay credenciales SaaS configuradas o el pago ya está confirmado.
     */
    public function generarLinkPago(Pago $pago, bool $forzarRotacion = false): ?string
    {
        $cred = $this->credenciales();
        if (!$cred) {
            Log::warning('💳 SaasBillingWompi: faltan credenciales en config(saas.wompi)');
            return null;
        }

        if ($pago->estado === Pago::ESTADO_CONFIRMADO) {
            return null; // ya pagado, no rotamos
        }

        $necesitaRotar = $forzarRotacion
            || empty($pago->wompi_reference)
            || !empty($pago->wompi_transaction_id)
            || $pago->wompi_status === 'DECLINED'
            || $pago->wompi_status === 'ERROR';

        if ($necesitaRotar) {
            $pago->wompi_reference       = $this->generarReferencia($pago);
            $pago->wompi_transaction_id  = null;
            $pago->wompi_status          = null;
            $pago->link_pago_generado_at = now();
        }

        $amountInCents = (int) round(((float) $pago->monto) * 100);
        $currency      = $pago->moneda ?: 'COP';

        $signature = hash('sha256',
            $pago->wompi_reference . $amountInCents . $currency . $cred['integrity_secret']
        );

        $tenant = $pago->tenant;
        $params = [
            'public-key'          => $cred['public_key'],
            'currency'            => $currency,
            'amount-in-cents'     => $amountInCents,
            'reference'           => $pago->wompi_reference,
            'signature:integrity' => $signature,
            'redirect-url'        => $cred['redirect_url'] ?? url('/billing/gracias'),
        ];

        if ($tenant) {
            if (!empty($tenant->nombre)) {
                $params['customer-data:full-name'] = mb_substr($tenant->nombre, 0, 50);
            }
            if (!empty($tenant->email)) {
                $params['customer-data:email'] = $tenant->email;
            }
        }

        $url = self::CHECKOUT_URL . '?' . http_build_query($params);
        $pago->link_pago_url = $url;
        $pago->save();

        return $url;
    }

    /**
     * Valida la firma de un webhook Wompi.
     */
    public function validarEvento(array $payload): bool
    {
        $cred = $this->credenciales();
        if (!$cred || empty($cred['events_secret'])) return false;

        $sig = $payload['signature'] ?? null;
        if (!is_array($sig)) return false;

        $properties = $sig['properties'] ?? [];
        $checksum   = (string) ($sig['checksum'] ?? '');
        $timestamp  = (string) ($payload['timestamp'] ?? '');
        if (empty($properties) || $checksum === '' || $timestamp === '') return false;

        $data = $payload['data'] ?? [];
        $concat = '';
        foreach ($properties as $path) {
            $concat .= (string) data_get($data, $path);
        }
        $concat .= $timestamp . $cred['events_secret'];

        return hash_equals(strtoupper($checksum), strtoupper(hash('sha256', $concat)));
    }

    /**
     * Procesa una transacción APPROVED: marca pago confirmado + extiende suscripción.
     */
    public function procesarAprobacion(array $tx): bool
    {
        $reference = (string) ($tx['reference'] ?? '');
        if ($reference === '') return false;

        $pago = Pago::query()->where('wompi_reference', $reference)->first();
        if (!$pago) {
            Log::warning('💳 SaaS: webhook Wompi reference desconocida', ['reference' => $reference]);
            return false;
        }

        if ($pago->estado === Pago::ESTADO_CONFIRMADO) {
            return true; // idempotente
        }

        $pago->update([
            'estado'               => Pago::ESTADO_CONFIRMADO,
            'wompi_transaction_id' => (string) ($tx['id'] ?? null),
            'wompi_status'         => (string) ($tx['status'] ?? 'APPROVED'),
            'metodo'               => $this->mapearMetodo($tx['payment_method_type'] ?? null),
            'fecha_pago'           => now(),
        ]);

        // Extender la suscripción según ciclo
        $susc = $pago->suscripcion;
        if ($susc) {
            $base = max(now(), $susc->fecha_fin ?? now());
            $nuevaFin = $susc->ciclo === Suscripcion::CICLO_ANUAL
                ? $base->copy()->addYear()
                : $base->copy()->addMonth();

            $susc->update([
                'estado'              => Suscripcion::ESTADO_ACTIVA,
                'fecha_fin'           => $nuevaFin,
                'proxima_factura_at'  => $nuevaFin,
            ]);

            // Reactivar tenant si estaba suspendido por mora
            $tenant = $susc->tenant;
            if ($tenant && $tenant->activo === false) {
                $tenant->update(['activo' => true]);
            }
        }

        Log::info('💳 SaaS: pago confirmado por webhook Wompi', [
            'pago_id' => $pago->id, 'reference' => $reference,
        ]);
        return true;
    }

    public function procesarRechazo(array $tx): void
    {
        $reference = (string) ($tx['reference'] ?? '');
        if ($reference === '') return;
        Pago::query()
            ->where('wompi_reference', $reference)
            ->update([
                'wompi_status'         => (string) ($tx['status'] ?? 'DECLINED'),
                'wompi_transaction_id' => (string) ($tx['id'] ?? null),
            ]);
    }

    /**
     * Consulta la última transacción asociada a una reference.
     */
    public function consultarPorReferencia(string $reference): ?array
    {
        $cred = $this->credenciales();
        if (!$cred || empty($cred['private_key'])) return null;

        $base = ($cred['modo'] === 'produccion')
            ? 'https://production.wompi.co/v1'
            : 'https://sandbox.wompi.co/v1';

        try {
            $resp = Http::withToken($cred['private_key'])
                ->timeout(15)
                ->get("{$base}/transactions", ['reference' => $reference]);

            if (!$resp->successful()) return null;
            $items = (array) $resp->json('data', []);
            if (empty($items)) return null;

            usort($items, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
            return $items[0];
        } catch (\Throwable $e) {
            Log::warning('SaasBillingWompi consulta excepción: ' . $e->getMessage());
            return null;
        }
    }

    private function credenciales(): ?array
    {
        $cred = (array) config('saas.wompi');
        if (empty($cred['public_key']) || empty($cred['integrity_secret'])) return null;
        return $cred;
    }

    private function generarReferencia(Pago $pago): string
    {
        $tenantId = $pago->tenant_id ?: 'x';
        $ts = now()->format('YmdHis');
        return "SAAS-T{$tenantId}-P{$pago->id}-{$ts}-" . strtoupper(Str::random(4));
    }

    private function mapearMetodo(?string $wompiMetodo): string
    {
        return match (strtoupper((string) $wompiMetodo)) {
            'CARD', 'CREDIT_CARD' => Pago::METODO_TARJETA,
            'NEQUI'               => Pago::METODO_NEQUI,
            'PSE'                 => Pago::METODO_TRANSFERENCIA,
            'BANCOLOMBIA_TRANSFER'=> Pago::METODO_TRANSFERENCIA,
            'DAVIPLATA'           => Pago::METODO_DAVIPLATA,
            default               => Pago::METODO_OTRO,
        };
    }
}
