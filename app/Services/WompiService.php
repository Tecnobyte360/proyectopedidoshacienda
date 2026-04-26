<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Servicio Wompi por tenant.
 *
 * Genera links de pago via Web Checkout y valida firmas de eventos del webhook.
 * Toda la configuración (llaves, modo) se lee de Tenant->wompi_config.
 *
 * Web Checkout docs: https://docs.wompi.co/docs/colombia/widget-checkout-web/
 * Eventos docs:      https://docs.wompi.co/docs/colombia/eventos/
 */
class WompiService
{
    private const CHECKOUT_URL = 'https://checkout.wompi.co/p/';

    public function __construct(private ?Tenant $tenant = null) {}

    public function paraTenant(Tenant $tenant): self
    {
        $this->tenant = $tenant;
        return $this;
    }

    private function tenantActual(): ?Tenant
    {
        return $this->tenant ?? app(TenantManager::class)->current();
    }

    /**
     * Genera (o reutiliza) la URL de pago para un pedido.
     * Si el pedido aún no tiene wompi_reference, se le asigna una.
     * El monto se toma de pedidos.total y se convierte a centavos.
     *
     * Devuelve null si el tenant no tiene Wompi configurado.
     */
    public function urlPago(Pedido $pedido, bool $forzarRotacion = false): ?string
    {
        $tenant = $this->tenantActual();
        if (!$tenant || !$tenant->tieneWompi()) return null;

        $cred = $tenant->wompiCredenciales();
        if (!$cred) return null;

        // Si el pedido ya está pagado, NO rotar bajo ninguna circunstancia.
        // Devolvemos null para que la UI no muestre botón de pagar ni regenere link.
        if ($pedido->estado_pago === 'aprobado') {
            return null;
        }

        // Decidir si la reference debe rotar:
        //  - Vacía → primera vez.
        //  - Forzado externamente (boton "Nuevo link").
        //  - Ya hubo transacción previa (Wompi rechazaria reusar) → wompi_transaction_id seteado.
        //  - Pago rechazado/fallido → necesita nuevo intento.
        $necesitaRotar = $forzarRotacion
            || empty($pedido->wompi_reference)
            || !empty($pedido->wompi_transaction_id)
            || in_array($pedido->estado_pago, ['rechazado', 'fallido'], true);

        if ($necesitaRotar) {
            $pedido->wompi_reference      = $this->generarReferencia($pedido);
            $pedido->wompi_transaction_id = null;       // limpiar tx anterior
            $pedido->pago_metodo          = null;
            // Solo resetear estado si NO está aprobado (ya validado arriba)
            $pedido->estado_pago          = 'pendiente';
            $pedido->saveQuietly();
        }

        $reference     = (string) $pedido->wompi_reference;
        $amountInCents = (int) round(((float) $pedido->total) * 100);
        $currency      = 'COP';

        $signature = $this->firmaIntegridad(
            $reference,
            $amountInCents,
            $currency,
            $cred['integrity_secret']
        );

        $params = [
            'public-key'           => $cred['public_key'],
            'currency'             => $currency,
            'amount-in-cents'      => $amountInCents,
            'reference'            => $reference,
            'signature:integrity'  => $signature,
            'redirect-url'         => $this->urlRetorno($pedido),
        ];

        // Datos opcionales del cliente (mejoran la experiencia)
        if (!empty($pedido->cliente_nombre)) {
            $params['customer-data:full-name'] = mb_substr($pedido->cliente_nombre, 0, 50);
        }
        if (!empty($pedido->telefono_whatsapp)) {
            $params['customer-data:phone-number'] = $pedido->telefono_whatsapp;
        }

        return self::CHECKOUT_URL . '?' . http_build_query($params);
    }

    /**
     * Hash de integridad: SHA256(reference + amount + currency + integrity_secret).
     */
    public function firmaIntegridad(string $reference, int $amountInCents, string $currency, string $integritySecret): string
    {
        return hash('sha256', $reference . $amountInCents . $currency . $integritySecret);
    }

    /**
     * Validar el evento entrante: Wompi firma con SHA256 de la concatenación
     * de los valores indicados en signature.properties + timestamp + events_secret.
     *
     * @param array $payload Cuerpo JSON parseado del webhook.
     * @return bool true si la firma coincide.
     */
    public function validarEvento(array $payload, ?string $eventsSecretOverride = null): bool
    {
        $eventsSecret = $eventsSecretOverride;
        if (!$eventsSecret) {
            $tenant = $this->tenantActual();
            $cred = $tenant?->wompiCredenciales();
            $eventsSecret = $cred['events_secret'] ?? null;
        }
        if (!$eventsSecret) return false;

        $sig = $payload['signature'] ?? null;
        if (!is_array($sig)) return false;

        $properties = $sig['properties'] ?? [];
        $checksum   = (string) ($sig['checksum'] ?? '');
        $timestamp  = (string) ($payload['timestamp'] ?? '');

        if (empty($properties) || $checksum === '' || $timestamp === '') return false;

        // Construir el string a hashear leyendo los paths con dot notation
        $data = $payload['data'] ?? [];
        $stringConcatenado = '';
        foreach ($properties as $path) {
            $valor = data_get($data, $path);
            $stringConcatenado .= (string) $valor;
        }
        $stringConcatenado .= $timestamp . $eventsSecret;

        $calculado = hash('sha256', $stringConcatenado);

        return hash_equals(strtoupper($checksum), strtoupper($calculado));
    }

    /**
     * Mapea el estado Wompi al estado_pago interno del pedido.
     */
    public function mapearEstadoTransaccion(?string $estadoWompi): string
    {
        return match ($estadoWompi) {
            'APPROVED' => 'aprobado',
            'DECLINED' => 'rechazado',
            'VOIDED'   => 'reembolsado',
            'ERROR'    => 'fallido',
            'PENDING'  => 'pendiente',
            default    => 'pendiente',
        };
    }

    private function generarReferencia(Pedido $pedido): string
    {
        $tenantId = $pedido->tenant_id ?: 'x';
        $ts = now()->format('YmdHis');
        return "PED-{$tenantId}-{$pedido->id}-{$ts}-" . strtoupper(Str::random(4));
    }

    /**
     * URL de retorno tras el pago (Wompi redirige aquí). Usa el subdominio del tenant.
     */
    private function urlRetorno(Pedido $pedido): string
    {
        $token = $pedido->codigo_seguimiento ?? $pedido->id;
        $path  = "/seguimiento-pedido/{$token}";

        $tenant = $this->tenantActual();
        if ($tenant && !empty($tenant->slug)) {
            $base = config('app.tenant_base_domain', 'tecnobyte360.com');
            $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';
            return "{$scheme}://{$tenant->slug}.{$base}{$path}";
        }
        return url($path);
    }
}
