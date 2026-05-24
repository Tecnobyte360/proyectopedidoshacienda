<?php

namespace App\Http\Controllers;

use App\Services\SaasBilling\SaasBillingWompiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaasBillingWompiWebhookController extends Controller
{
    public function __invoke(Request $request, SaasBillingWompiService $svc)
    {
        $payload = $request->all();

        if (!$svc->validarEvento($payload)) {
            Log::warning('💳 SaaS Wompi webhook: firma inválida');
            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        $event = $payload['event'] ?? '';
        $tx    = $payload['data']['transaction'] ?? [];
        $status = (string) ($tx['status'] ?? '');

        Log::info('💳 SaaS Wompi webhook recibido', [
            'event'     => $event,
            'reference' => $tx['reference'] ?? null,
            'status'    => $status,
            'amount'    => $tx['amount_in_cents'] ?? null,
        ]);

        if ($event === 'transaction.updated' || $event === 'nequi_token.updated') {
            if ($status === 'APPROVED') {
                $svc->procesarAprobacion($tx);
            } elseif (in_array($status, ['DECLINED', 'ERROR', 'VOIDED'], true)) {
                $svc->procesarRechazo($tx);
            }
        }

        return response()->json(['ok' => true]);
    }
}
