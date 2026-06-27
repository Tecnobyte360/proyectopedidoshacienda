<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domiciliario;
use App\Models\Pedido;
use App\Models\Tenant;
use App\Services\RutaOptimizadaService;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API para la app móvil de domiciliarios (Kivox Repartidores).
 *
 * Autenticación simple por TOKEN del domiciliario (el mismo token_acceso del
 * portal web /d/{token}). Se envía como header: Authorization: Bearer {token}.
 * El token es único por domiciliario en toda la plataforma, así que también
 * resuelve el tenant.
 */
class DomiciliarioApiController extends Controller
{
    /** Resuelve el domiciliario por token y fija el contexto del tenant. */
    private function domiciliarioDesde(Request $r): ?Domiciliario
    {
        $token = $r->bearerToken() ?: $r->input('token');
        if (!$token) return null;

        $dom = Domiciliario::withoutGlobalScopes()
            ->where('token_acceso', $token)
            ->where('activo', true)
            ->first();

        if ($dom) {
            $tenant = Tenant::withoutGlobalScopes()->find($dom->tenant_id);
            if ($tenant) app(TenantManager::class)->set($tenant);
        }
        return $dom;
    }

    private function serializarDomi(Domiciliario $d): array
    {
        $tenant = Tenant::withoutGlobalScopes()->find($d->tenant_id);
        return [
            'id'       => $d->id,
            'nombre'   => $d->nombre,
            'vehiculo' => $d->vehiculo,
            'placa'    => $d->placa,
            'estado'   => $d->estado,
            'negocio'  => $tenant?->nombre,
            'logo'     => $tenant?->logo_url ?? null,
        ];
    }

    private function serializarPedido(Pedido $p): array
    {
        return [
            'id'            => $p->id,
            'estado'        => $p->estado,
            'cliente'       => $p->cliente_nombre ?: ($p->cliente->nombre ?? 'Cliente'),
            'telefono'      => $p->telefono ?: ($p->telefono_contacto ?: ($p->cliente->telefono ?? null)),
            'direccion'     => $p->direccion ?: null,
            'barrio'        => $p->barrio ?: null,
            'ciudad'        => null,
            'total'         => (float) ($p->total ?? 0),
            'metodo_pago'   => $p->pago_metodo ?? null,
            'notas'         => $p->notas ?: null,
            'token_entrega' => $p->token_entrega,
            'lat'           => $p->lat !== null ? (float) $p->lat : null,
            'lng'           => $p->lng !== null ? (float) $p->lng : null,
        ];
    }

    /**
     * POST /api/domiciliario/login
     * Modo credenciales: { email, password }  → valida usuario y devuelve token.
     * Modo token (compatibilidad): Authorization: Bearer {token}.
     * Siempre devuelve `token` para que la app lo use en las siguientes llamadas.
     */
    public function login(Request $r)
    {
        $email = trim((string) $r->input('email'));
        $pass  = (string) $r->input('password');

        if ($email !== '') {
            $u = \App\Models\User::withoutGlobalScopes()->where('email', $email)->first();
            if (!$u || !\Illuminate\Support\Facades\Hash::check($pass, $u->password)) {
                return response()->json(['ok' => false, 'message' => 'Usuario o clave incorrectos.'], 401);
            }
            $dom = Domiciliario::withoutGlobalScopes()
                ->where('user_id', $u->id)->where('activo', true)->first();
            if (!$dom) {
                return response()->json(['ok' => false, 'message' => 'Tu usuario no está vinculado a un domiciliario activo.'], 403);
            }
            $tenant = Tenant::withoutGlobalScopes()->find($dom->tenant_id);
            if ($tenant) app(TenantManager::class)->set($tenant);
            return response()->json(['ok' => true, 'token' => $dom->token_acceso, 'domiciliario' => $this->serializarDomi($dom)]);
        }

        // Compatibilidad: login por token (Bearer)
        $dom = $this->domiciliarioDesde($r);
        if (!$dom) {
            return response()->json(['ok' => false, 'message' => 'Credenciales o token inválidos.'], 401);
        }
        return response()->json(['ok' => true, 'token' => $dom->token_acceso, 'domiciliario' => $this->serializarDomi($dom)]);
    }

    /** GET /api/domiciliario/pedidos */
    public function pedidos(Request $r)
    {
        $dom = $this->domiciliarioDesde($r);
        if (!$dom) return response()->json(['ok' => false, 'message' => 'No autorizado.'], 401);

        $pedidos = Pedido::where('domiciliario_id', $dom->id)
            ->whereNotIn('estado', [Pedido::ESTADO_ENTREGADO, Pedido::ESTADO_CANCELADO])
            ->orderBy('fecha_pedido')
            ->get();

        // Ordenar por cercanía si tenemos ubicación del domiciliario
        try {
            $pedidos = app(RutaOptimizadaService::class)
                ->optimizar($pedidos, $dom->lat_actual, $dom->lng_actual);
        } catch (\Throwable $e) { /* si falla, deja el orden por fecha */ }

        $entregadosHoy = Pedido::where('domiciliario_id', $dom->id)
            ->whereDate('fecha_entregado', now()->toDateString())->count();

        return response()->json([
            'ok'             => true,
            'pendientes'     => $pedidos->count(),
            'entregados_hoy' => $entregadosHoy,
            'pedidos'        => collect($pedidos)->map(fn ($p) => $this->serializarPedido($p))->values(),
        ]);
    }

    /** POST /api/domiciliario/pedidos/{id}/iniciar */
    public function iniciarRuta(Request $r, int $id)
    {
        $dom = $this->domiciliarioDesde($r);
        if (!$dom) return response()->json(['ok' => false, 'message' => 'No autorizado.'], 401);

        $p = Pedido::where('domiciliario_id', $dom->id)->where('id', $id)->first();
        if (!$p) return response()->json(['ok' => false, 'message' => 'Pedido no encontrado.'], 404);

        if ($p->estado !== Pedido::ESTADO_REPARTIDOR_EN_CAMINO) {
            $p->fecha_salida_domiciliario = now();
            $p->save();
            $p->cambiarEstado(Pedido::ESTADO_REPARTIDOR_EN_CAMINO, 'Tu pedido ya va en camino.', 'Pedido en camino');
            try {
                $token = $p->token_entrega ?: $p->generarTokenEntrega();
                $p->notificarTokenEntrega($token);
            } catch (\Throwable $e) {
                Log::warning('API iniciarRuta: no se pudo notificar token: ' . $e->getMessage());
            }
        }
        return response()->json(['ok' => true, 'pedido' => $this->serializarPedido($p->fresh())]);
    }

    /** POST /api/domiciliario/pedidos/{id}/entregar */
    public function entregar(Request $r, int $id)
    {
        $dom = $this->domiciliarioDesde($r);
        if (!$dom) return response()->json(['ok' => false, 'message' => 'No autorizado.'], 401);

        $p = Pedido::where('domiciliario_id', $dom->id)->where('id', $id)->first();
        if (!$p) return response()->json(['ok' => false, 'message' => 'Pedido no encontrado.'], 404);

        if ($p->estado !== Pedido::ESTADO_ENTREGADO) {
            $p->cambiarEstado(Pedido::ESTADO_ENTREGADO, 'Entregado por ' . $dom->nombre, 'Entregado');
        }
        return response()->json(['ok' => true, 'pedido' => $this->serializarPedido($p->fresh())]);
    }

    /** POST /api/domiciliario/ubicacion  { lat, lng } */
    public function ubicacion(Request $r)
    {
        $dom = $this->domiciliarioDesde($r);
        if (!$dom) return response()->json(['ok' => false, 'message' => 'No autorizado.'], 401);

        $r->validate(['lat' => 'required|numeric', 'lng' => 'required|numeric']);
        $dom->forceFill([
            'lat_actual'               => $r->float('lat'),
            'lng_actual'               => $r->float('lng'),
            'ubicacion_actualizada_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }
}
