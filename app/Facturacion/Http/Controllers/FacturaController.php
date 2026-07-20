<?php

namespace App\Facturacion\Http\Controllers;

use App\Facturacion\Http\Requests\FacturaStoreRequest;
use App\Facturacion\Models\FeConfiguracion;
use App\Facturacion\Models\FeDocumento;
use App\Facturacion\Services\NumeradorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API pública de facturación electrónica. La consume cualquier software externo
 * (el emisor se autentica por API key en el middleware `FacturadorApiKey`).
 *
 * Responsabilidades del SERVIDOR (nunca del cliente que llama):
 *   - Reservar el consecutivo (lockForUpdate).
 *   - Calcular subtotales/impuestos/total (no se confía en cifras del caller).
 *   - Idempotencia (un reintento no genera doble factura).
 *   - Encolar la emisión a la DIAN (firma + CUFE + transmisión → siguiente fase).
 */
class FacturaController extends Controller
{
    public function __construct(private NumeradorService $numerador) {}

    /** POST /api/facturacion/v1/facturas */
    public function store(FacturaStoreRequest $request): JsonResponse
    {
        /** @var FeConfiguracion $config */
        $config   = $request->attributes->get('fe_config');
        $tenantId = (int) $request->attributes->get('fe_tenant_id');
        $data     = $request->validated();

        // ── Idempotencia: mismo Idempotency-Key ⇒ devolver el documento ya creado
        $idemKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idemKey !== '') {
            $existente = FeDocumento::where('tenant_id', $tenantId)
                ->where('idempotency_key', $idemKey)->first();
            if ($existente) {
                return $this->respuestaDocumento($existente, 200);
            }
        }

        // ── Totales calculados EN EL SERVIDOR (fuente de verdad) ──────────
        $tot = $this->calcularTotales($data['items']);

        try {
            // ── Reservar consecutivo (transaccional, sin saltos ni duplicados)
            $num = $this->numerador->siguiente($tenantId, 'factura');

            $doc = FeDocumento::create([
                'tenant_id'         => $tenantId,
                'fe_resolucion_id'  => $num['resolucion']->id,
                'origen'            => 'api',
                'origen_ref'        => $data['origen_ref'] ?? null,
                'tipo_documento'    => 'factura',
                'prefijo'           => $num['prefijo'],
                'numero'            => $num['numero'],
                'numero_completo'   => $num['numero_completo'],
                'estado'            => FeDocumento::PENDING,
                'idempotency_key'   => $idemKey !== '' ? $idemKey : null,
                'cliente_documento' => $data['cliente']['numero_documento'],
                'cliente_nombre'    => $data['cliente']['nombre'],
                'total'             => $tot['total'],
                'moneda'            => $data['moneda'] ?? 'COP',
                'request_payload'   => ['input' => $data, 'totales' => $tot],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FE: no se pudo crear la factura', ['tenant' => $tenantId, 'error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'unprocessable',
                'message' => $e->getMessage(), // ej. resolución agotada / no vigente
            ], 422);
        }

        // ── Encolar la emisión a la DIAN (motor: firma + CUFE + SOAP) ─────
        //    Se implementa en la siguiente fase (ElectronicInvoiceProvider).
        //    SendElectronicInvoice::dispatch($doc->id);

        return $this->respuestaDocumento($doc, 202);
    }

    /** GET /api/facturacion/v1/facturas/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('fe_tenant_id');
        $doc = FeDocumento::where('tenant_id', $tenantId)->find($id);
        if (!$doc) {
            return response()->json(['error' => 'not_found', 'message' => 'Documento no encontrado.'], 404);
        }
        return $this->respuestaDocumento($doc, 200);
    }

    /** Calcula subtotal, IVA y total por línea y del documento (server-authoritative). */
    private function calcularTotales(array $items): array
    {
        $subtotal = 0.0; $impuestos = 0.0; $lineas = [];
        foreach ($items as $it) {
            $cant  = (float) $it['cantidad'];
            $pu    = (float) $it['precio_unitario'];
            $desc  = (float) ($it['descuento'] ?? 0);
            $pct   = (float) ($it['porcentaje_impuesto'] ?? 0);

            $base  = round(($cant * $pu) - $desc, 2);
            $iva   = round($base * $pct / 100, 2);

            $subtotal  += $base;
            $impuestos += $iva;
            $lineas[]   = ['base' => $base, 'impuesto' => $iva, 'porcentaje' => $pct];
        }
        return [
            'subtotal'  => round($subtotal, 2),
            'impuestos' => round($impuestos, 2),
            'total'     => round($subtotal + $impuestos, 2),
            'lineas'    => $lineas,
        ];
    }

    private function respuestaDocumento(FeDocumento $doc, int $status): JsonResponse
    {
        return response()->json([
            'id'              => $doc->id,
            'tipo_documento'  => $doc->tipo_documento,
            'numero'          => $doc->numero_completo,
            'estado'          => $doc->estado,      // pending | accepted | rejected...
            'cufe'            => $doc->cufe,
            'total'           => $doc->total,
            'dian_mensaje'    => $doc->dian_mensaje,
            'errores'         => $doc->dian_errores ?? [],
            'pdf_url'         => $doc->pdf_path ? url("/api/facturacion/v1/facturas/{$doc->id}/pdf") : null,
            'creado_at'       => optional($doc->created_at)->toIso8601String(),
        ], $status);
    }
}
