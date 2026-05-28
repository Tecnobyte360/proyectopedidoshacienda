<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\Tenant;

/**
 * 💳 Orquestador de pasarelas de pago.
 *
 * Decide qué pasarela usar según la config del tenant:
 *   - Si solo Wompi → Wompi
 *   - Si solo Bold → Bold
 *   - Si ambas + preferencia → la preferida
 *   - Si ambas + "cliente_elige" → devuelve ambas URLs
 */
class PasarelaPagoService
{
    public function __construct(
        private WompiService $wompi,
        private BoldService $bold,
    ) {}

    /**
     * Devuelve array con las URLs disponibles para pagar este pedido:
     *   ['wompi' => 'https://...', 'bold' => 'https://...']
     *
     * Si el cliente debe elegir (ambas activas) → devuelve las 2.
     * Si solo una está activa → devuelve solo esa.
     * Si hay preferencia → devuelve solo la preferida.
     */
    public function urlsPago(Pedido $pedido, bool $forzarRotacion = false): array
    {
        $tenant = Tenant::find($pedido->tenant_id);
        if (!$tenant) return [];

        $tieneWompi = $tenant->tieneWompi();
        $tieneBold  = $tenant->bold_activo && !empty($tenant->bold_api_key);

        $preferida = $tenant->pasarela_preferida ?? 'cliente_elige';

        $urls = [];

        // Solo Wompi
        if ($tieneWompi && !$tieneBold) {
            $url = $this->wompi->paraTenant($tenant)->urlPago($pedido, $forzarRotacion);
            if ($url) $urls['wompi'] = $url;
            return $urls;
        }

        // Solo Bold
        if ($tieneBold && !$tieneWompi) {
            $url = $this->bold->paraTenant($tenant)->urlPago($pedido, $forzarRotacion);
            if ($url) $urls['bold'] = $url;
            return $urls;
        }

        // Ambas activas
        if ($tieneWompi && $tieneBold) {
            if ($preferida === 'wompi') {
                $url = $this->wompi->paraTenant($tenant)->urlPago($pedido, $forzarRotacion);
                if ($url) $urls['wompi'] = $url;
            } elseif ($preferida === 'bold') {
                $url = $this->bold->paraTenant($tenant)->urlPago($pedido, $forzarRotacion);
                if ($url) $urls['bold'] = $url;
            } else {
                // cliente_elige → ambas
                $uW = $this->wompi->paraTenant($tenant)->urlPago($pedido, $forzarRotacion);
                $uB = $this->bold->paraTenant($tenant)->urlPago($pedido, $forzarRotacion);
                if ($uW) $urls['wompi'] = $uW;
                if ($uB) $urls['bold']  = $uB;
            }
        }

        return $urls;
    }

    /**
     * Devuelve UNA URL para enviar al cliente (la preferida o la única disponible).
     * Si hay 2, devuelve la del primer match según preferencia.
     */
    public function urlPagoPrincipal(Pedido $pedido, bool $forzarRotacion = false): ?string
    {
        $urls = $this->urlsPago($pedido, $forzarRotacion);
        if (empty($urls)) return null;

        // Priorizar bold > wompi si ambas (Bold suele tener mejor UX)
        return $urls['bold'] ?? $urls['wompi'] ?? array_values($urls)[0];
    }
}
