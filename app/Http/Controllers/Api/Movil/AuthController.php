<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Models\Domiciliario;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Login unificado para la app móvil Kivox.
 * Devuelve un token Sanctum + los permisos del usuario, para que la app
 * muestre solo los módulos a los que tiene acceso (chat, pedidos, etc.).
 */
class AuthController extends Controller
{
    public function login(Request $r)
    {
        $r->validate(['email' => 'required|string', 'password' => 'required|string']);

        $u = User::withoutGlobalScopes()->where('email', trim($r->email))->first();
        if (!$u || !Hash::check($r->password, $u->password)) {
            return response()->json(['ok' => false, 'message' => 'Usuario o clave incorrectos.'], 401);
        }
        if (isset($u->activo) && !$u->activo) {
            return response()->json(['ok' => false, 'message' => 'Usuario inactivo.'], 403);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($u->tenant_id);
        if ($tenant) app(TenantManager::class)->set($tenant);

        $token = $u->createToken('app-movil')->plainTextToken;
        $dom   = Domiciliario::withoutGlobalScopes()->where('user_id', $u->id)->where('activo', true)->first();

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'user'  => [
                'id'      => $u->id,
                'nombre'  => $u->name,
                'email'   => $u->email,
                'negocio' => $tenant?->nombre,
                'logo'    => $tenant?->logo_url ?? null,
                'permisos' => [
                    'chat'    => (bool) $u->can('chat.usar'),
                    'pedidos' => (bool) $u->can('pedidos.ver'),
                ],
                'es_domiciliario'    => (bool) $dom,
                'domiciliario_token' => $dom?->token_acceso,
            ],
        ]);
    }

    public function logout(Request $r)
    {
        $r->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    public function yo(Request $r)
    {
        $u = $r->user();
        return response()->json(['ok' => true, 'user' => ['id' => $u->id, 'nombre' => $u->name, 'email' => $u->email]]);
    }

    /** Registra/actualiza el token FCM del dispositivo del usuario (para push). */
    public function registrarToken(Request $r)
    {
        $r->validate(['token' => 'required|string']);
        $u = $r->user();
        $dom = Domiciliario::withoutGlobalScopes()->where('user_id', $u->id)->first();
        \App\Models\DeviceToken::updateOrCreate(
            ['token' => $r->token],
            [
                'tenant_id'       => $u->tenant_id,
                'user_id'         => $u->id,
                'domiciliario_id' => $dom?->id,
                'plataforma'      => $r->input('plataforma', 'android'),
            ]
        );
        return response()->json(['ok' => true]);
    }
}
