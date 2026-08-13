<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * 🔐 Login de la API (token). El integrador se autentica con email + contraseña
 * de un usuario de SU tenant y recibe un token Bearer. Con ese token hace las
 * peticiones (todo queda scopeado a su tenant). Sin login → no puede llamar nada.
 */
class AuthApiController extends Controller
{
    /** POST /api/v1/login  → { email, password }  → token Bearer */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withoutGlobalScopes()
            ->where('email', trim((string) $request->email))
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['ok' => false, 'message' => 'Credenciales incorrectas.'], 401);
        }

        if (isset($user->activo) && !$user->activo) {
            return response()->json(['ok' => false, 'message' => 'Usuario inactivo.'], 403);
        }

        // La API es POR tenant: el usuario debe pertenecer a un tenant.
        if (empty($user->tenant_id)) {
            return response()->json(['ok' => false, 'message' => 'Este usuario no pertenece a una empresa (tenant).'], 403);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
        if (!$tenant) {
            return response()->json(['ok' => false, 'message' => 'Empresa no encontrada.'], 403);
        }
        app(TenantManager::class)->set($tenant);

        // Token con habilidad "api" (por si luego quieres restringir por scopes).
        $token = $user->createToken('api-integracion', ['api'])->plainTextToken;

        return response()->json([
            'ok'         => true,
            'token'      => $token,
            'token_type' => 'Bearer',
            'tenant'     => ['id' => $tenant->id, 'nombre' => $tenant->nombre, 'slug' => $tenant->slug],
            'usuario'    => ['id' => $user->id, 'nombre' => $user->name ?? $user->nombre ?? null, 'email' => $user->email],
        ]);
    }

    /** POST /api/v1/logout  → revoca el token actual. */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
        return response()->json(['ok' => true, 'message' => 'Sesión cerrada.']);
    }

    /** GET /api/v1/yo  → datos del token/tenant actual (para verificar). */
    public function yo(Request $request): JsonResponse
    {
        $u = $request->user();
        $t = app(TenantManager::class)->current();
        return response()->json([
            'ok'      => true,
            'usuario' => ['id' => $u->id, 'email' => $u->email],
            'tenant'  => $t ? ['id' => $t->id, 'nombre' => $t->nombre] : null,
        ]);
    }
}
