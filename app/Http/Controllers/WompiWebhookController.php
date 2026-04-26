<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Tenant;
use App\Services\WompiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receptor de eventos de Wompi.
 *
 * URL pública para configurar en https://comercios.wompi.co/ → Eventos:
 *   POST {APP_URL}/wompi/webhook
 *
 * Wompi envía eventos como:
 *   { event: "transaction.updated", data: { transaction: {...} },
 *     timestamp: ..., signature: { properties:[...], checksum:"..." } }
 *
 * Validamos la firma con events_secret del tenant dueño del pedido,
 * actualizamos estado_pago y fecha pagado_at.
 */
class WompiWebhookController extends Controller
{
    public function recibir(Request $request, ?string $slug = null)
    {
        $payload = $request->all();

        $event = (string) ($payload['event'] ?? '');
        $tx    = $payload['data']['transaction'] ?? null;

        if ($event === '' || !is_array($tx)) {
            Log::warning('Wompi webhook: payload inválido', ['raw' => $request->getContent()]);
            return response()->json(['ok' => false, 'reason' => 'payload_invalido'], 200);
        }

        $reference = (string) ($tx['reference'] ?? '');
        $status    = (string) ($tx['status'] ?? '');
        $txId      = (string) ($tx['id'] ?? '');
        $metodo    = (string) ($tx['payment_method_type'] ?? '');

        // Resolver tenant: 1° por slug en URL, 2° fallback por wompi_reference del pedido.
        $tenant = null;
        if ($slug) {
            $tenant = Tenant::withoutGlobalScopes()->where('slug', $slug)->first();
            if (!$tenant) {
                Log::warning('Wompi webhook: tenant slug no encontrado', compact('slug'));
                return response()->json(['ok' => false, 'reason' => 'tenant_slug_no_encontrado'], 200);
            }
        }

        if ($reference === '') {
            Log::warning('Wompi webhook: sin reference', ['tx' => $tx]);
            return response()->json(['ok' => false, 'reason' => 'sin_reference'], 200);
        }

        // Buscar el pedido por la referencia actual o por historial
        $query = Pedido::withoutGlobalScopes()
            ->where(function ($q) use ($reference) {
                $q->where('wompi_reference', $reference)
                  ->orWhereJsonContains('wompi_referencias_historial', $reference);
            });
        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }
        $pedido = $query->first();

        // Fallback: si la reference no matchea (porque rotó después del pago),
        // extraer el pedido_id de la reference. Formato: PED-{tenant}-{pedido_id}-{ts}-{rand}
        if (!$pedido && preg_match('/^PED-\d+-(\d+)-/', $reference, $m)) {
            $pedidoId = (int) $m[1];
            $fb = Pedido::withoutGlobalScopes()->where('id', $pedidoId);
            if ($tenant) $fb->where('tenant_id', $tenant->id);
            $pedido = $fb->first();

            if ($pedido) {
                Log::info('Wompi webhook: pedido recuperado via parse de reference', [
                    'reference'        => $reference,
                    'pedido_id'        => $pedido->id,
                    'reference_actual' => $pedido->wompi_reference,
                ]);
                // Restaurar la reference recibida en el pedido para que el webhook
                // posterior (si lo hay) pueda matchear y para tener trazabilidad.
                $pedido->wompi_reference = $reference;
                $pedido->saveQuietly();
            }
        }

        if (!$pedido) {
            Log::warning('Wompi webhook: pedido no encontrado', compact('reference', 'slug'));
            return response()->json(['ok' => false, 'reason' => 'pedido_no_encontrado'], 200);
        }

        // Si entró por la ruta legacy sin slug, ahora resolvemos el tenant via pedido
        if (!$tenant) {
            $tenant = Tenant::withoutGlobalScopes()->find($pedido->tenant_id);
        }

        if (!$tenant) {
            Log::warning('Wompi webhook: tenant no encontrado', ['pedido_id' => $pedido->id]);
            return response()->json(['ok' => false, 'reason' => 'tenant_no_encontrado'], 200);
        }

        $cred = $tenant->wompiCredenciales();
        if (!$cred || empty($cred['events_secret'])) {
            Log::warning('Wompi webhook: tenant sin events_secret', ['tenant_id' => $tenant->id]);
            return response()->json(['ok' => false, 'reason' => 'sin_secret'], 200);
        }

        $wompi = app(WompiService::class)->paraTenant($tenant);

        if (!$wompi->validarEvento($payload, $cred['events_secret'])) {
            Log::warning('Wompi webhook: firma inválida', [
                'pedido_id' => $pedido->id,
                'reference' => $reference,
            ]);
            return response()->json(['ok' => false, 'reason' => 'firma_invalida'], 401);
        }

        // Actualizar pedido
        $estadoInterno = $wompi->mapearEstadoTransaccion($status);
        $pedido->estado_pago         = $estadoInterno;
        $pedido->wompi_transaction_id = $txId ?: $pedido->wompi_transaction_id;
        $pedido->pago_metodo         = $metodo ?: $pedido->pago_metodo;
        if ($estadoInterno === 'aprobado' && empty($pedido->pagado_at)) {
            $pedido->pagado_at = now();
        }
        $pedido->saveQuietly();

        Log::info('💳 Wompi evento procesado', [
            'event'     => $event,
            'pedido_id' => $pedido->id,
            'reference' => $reference,
            'status'    => $status,
            'estado_interno' => $estadoInterno,
        ]);

        // Notificar al cliente vía WhatsApp si fue aprobado
        if ($estadoInterno === 'aprobado') {
            $this->notificarPagoAprobado($pedido, $tenant);
        } elseif (in_array($estadoInterno, ['rechazado', 'fallido'], true)) {
            $this->notificarPagoFallido($pedido, $tenant);
        }

        // Broadcast para que el seguimiento se actualice en vivo
        try {
            broadcast(new \App\Events\PedidoActualizado($pedido->fresh(['sede','detalles','historialEstados']), 'pago_actualizado'));
        } catch (\Throwable $e) { /* no bloquear */ }

        return response()->json(['ok' => true]);
    }

    private function notificarPagoAprobado(Pedido $pedido, Tenant $tenant): void
    {
        // Activar contexto del tenant para que ConfiguracionBot::actual() lo lea
        try { app(\App\Services\TenantManager::class)->set($tenant); } catch (\Throwable $e) {}

        // Delegar a la lógica configurable (toggle + plantilla + delay)
        $pedido->enviarNotificacionConfigurable('pago_aprobado');
        return;

        // Lógica antigua (no se ejecuta)
        $telefono = $pedido->telefono_whatsapp ?: $pedido->telefono_contacto ?: $pedido->telefono;
        if (!$telefono) return;

        $primer = trim(explode(' ', (string) $pedido->cliente_nombre)[0] ?: 'cliente');
        $total  = '$' . number_format((float) $pedido->total, 0, ',', '.');

        $msg = "✅ {$primer}, recibimos tu pago de {$total} 🙌\n\n"
             . "Tu pedido #{$pedido->id} ya quedó *pagado*. Procedemos a prepararlo y te avisamos cuando salga 🛵💨";

        try {
            app(\App\Services\TenantManager::class)->set($tenant);
            app(\App\Services\WhatsappSenderService::class)->enviarTexto(
                $telefono,
                $msg,
                $pedido->connection_id ?? null
            );
        } catch (\Throwable $e) {
            Log::warning('Wompi: fallo notificar pago aprobado: ' . $e->getMessage());
        }
    }

    private function notificarPagoFallido(Pedido $pedido, Tenant $tenant): void
    {
        try { app(\App\Services\TenantManager::class)->set($tenant); } catch (\Throwable $e) {}

        $pedido->enviarNotificacionConfigurable('pago_rechazado');
        return;

        // Lógica antigua (no se ejecuta)
        $telefono = $pedido->telefono_whatsapp ?: $pedido->telefono_contacto ?: $pedido->telefono;
        if (!$telefono) return;

        $primer = trim(explode(' ', (string) $pedido->cliente_nombre)[0] ?: 'cliente');
        $link   = $pedido->urlPagoWompi();

        $msg = "Hola {$primer}, tu pago no se pudo procesar 🙏.\n\n"
             . "Tu pedido #{$pedido->id} sigue activo. Puedes intentar de nuevo aquí:\n{$link}\n\n"
             . "O escríbenos si prefieres pagar contra entrega.";

        try {
            app(\App\Services\TenantManager::class)->set($tenant);
            app(\App\Services\WhatsappSenderService::class)->enviarTexto(
                $telefono,
                $msg,
                $pedido->connection_id ?? null
            );
        } catch (\Throwable $e) {
            Log::warning('Wompi: fallo notificar pago fallido: ' . $e->getMessage());
        }
    }
}
