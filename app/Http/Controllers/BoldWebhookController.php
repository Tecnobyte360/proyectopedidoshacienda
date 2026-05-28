<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\BoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 💳 Receptor de webhooks de Bold (por tenant via slug en URL)
 *
 * Cada tenant configura en su dashboard de Bold:
 *   https://admin.kivox.co/api/bold/webhook/{slug}
 */
class BoldWebhookController extends Controller
{
    public function recibir(string $slug, Request $request)
    {
        $tenant = Tenant::where('slug', $slug)->first();
        if (!$tenant) {
            Log::warning('💳 Bold webhook: tenant no encontrado', ['slug' => $slug]);
            return response('Tenant not found', 404);
        }

        if (!$tenant->bold_activo) {
            Log::warning('💳 Bold webhook: tenant Bold desactivado', ['slug' => $slug]);
            return response('Bold disabled for tenant', 403);
        }

        $service  = (new BoldService())->paraTenant($tenant);
        $rawBody  = $request->getContent();
        $firma    = $request->header('x-bold-signature', '');

        // Validar firma HMAC
        if ($firma && !$service->validarFirma($rawBody, $firma)) {
            Log::warning('💳 Bold webhook: firma inválida', [
                'tenant' => $tenant->slug,
                'firma_recibida' => substr($firma, 0, 16) . '...',
            ]);
            return response('Invalid signature', 401);
        }

        $payload = $request->all();
        Log::info('💳 Bold webhook recibido', [
            'tenant' => $tenant->slug,
            'type'   => $payload['type'] ?? 'unknown',
        ]);

        $resultado = $service->procesarEvento($payload);

        return response()->json([
            'ok' => $resultado['ok'] ?? false,
            'processed' => $resultado,
        ]);
    }
}
