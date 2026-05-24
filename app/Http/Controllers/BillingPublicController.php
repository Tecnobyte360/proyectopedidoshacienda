<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Services\SaasBilling\SaasBillingWompiService;
use Illuminate\Http\Request;

class BillingPublicController extends Controller
{
    /**
     * 🎉 Página pública a donde Wompi redirige al tenant después de pagar.
     * Wompi añade query strings ?id=...&env=... con el id de la transacción.
     *
     * Esta página NO requiere login (el tenant no es usuario del SaaS,
     * solo recibió un link y pagó). Si la tx vino con id, hacemos polling
     * a Wompi para mostrar el estado real (sin esperar el webhook).
     */
    public function gracias(Request $request, SaasBillingWompiService $svc)
    {
        $txId = $request->query('id');
        $reference = null;
        $estado = 'pendiente';
        $pago = null;

        if ($txId) {
            // Polling rápido para confirmar el estado en vivo
            try {
                $cfg = \App\Models\ConfiguracionPlataforma::actual();
                $cred = $cfg->wompiSaasCredenciales();
                if ($cred && !empty($cred['private_key'])) {
                    $base = $cred['modo'] === 'produccion'
                        ? 'https://production.wompi.co/v1'
                        : 'https://sandbox.wompi.co/v1';
                    $resp = \Illuminate\Support\Facades\Http::withToken($cred['private_key'])
                        ->timeout(10)
                        ->get("{$base}/transactions/{$txId}");
                    if ($resp->successful()) {
                        $tx = $resp->json('data', []);
                        $reference = $tx['reference'] ?? null;
                        $statusWompi = $tx['status'] ?? 'PENDING';
                        // Si está aprobada y aún no se procesó (webhook lento), lo procesamos aquí
                        if ($statusWompi === 'APPROVED' && $reference) {
                            $svc->procesarAprobacion($tx);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($reference) {
            $pago = Pago::where('wompi_reference', $reference)->with('tenant', 'suscripcion.plan')->first();
            $estado = $pago?->estado ?? 'pendiente';
        }

        return view('billing.gracias', compact('pago', 'estado', 'txId'));
    }
}
