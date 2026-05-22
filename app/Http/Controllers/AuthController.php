<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        // 🔓 ?logout=1 → forzar cierre de sesión antes de mostrar login.
        // Usado cuando entras a un subdominio de tenant desde admin/tenants
        // y queremos que el usuario inicie sesión limpia (no auto-redirect
        // al dashboard usando una sesión cross-subdomain previa).
        $forzarLogout = $request->query('logout') === '1';
        if ($forzarLogout && Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            // No usamos redirect()->route('login') porque genera URL con APP_URL
            // (admin.kivox.co) en vez del host actual. Renderizamos directo.
        }

        // Si ya está logueado (y NO pidió logout), redirigir a su ruta inicial
        if (!$forzarLogout && Auth::check()) {
            return redirect($this->rutaInicialPara(Auth::user()));
        }

        // Resolver tenant por subdominio para branding dinámico
        $tenant = $this->resolverTenantLogin();

        return view('auth.login', ['tenantBranding' => $tenant]);
    }

    /**
     * Devuelve la ruta de inicio más adecuada según los permisos del usuario.
     * Cada usuario aterriza en la página más útil para SU rol.
     *
     * Prioridad:
     *   1. Super-admin → /admin/tenants (panel de plataforma)
     *   2. Domiciliario sin acceso de admin → /rutas (sus pedidos asignados)
     *   3. Operador / cajero / gerente / admin → /pedidos (gestión)
     *   4. Solo chat → /chat
     *   5. Solo reportes → /reportes
     *   6. Fallback → /pedidos
     */
    private function rutaInicialPara(\App\Models\User $user): string
    {
        // 1. Super-admin
        try {
            if ($user->hasRole('super-admin')) {
                return '/admin/tenants';
            }
        } catch (\Throwable $e) { /* ignorar */ }

        // 2. Roles especiales con destino exclusivo
        try {
            $roles = $user->getRoleNames();
            if ($roles->count() === 1) {
                $unicoRol = $roles->first();
                if ($unicoRol === 'domiciliario') return '/despachos'; // /despachos redirige al portal personal
                if ($unicoRol === 'chat-only')   return '/chat';
            }
        } catch (\Throwable $e) {}

        // 3. Lista de rutas en orden de prioridad: la primera que tenga permiso gana
        $rutasPorPermiso = [
            'pedidos.ver'        => '/pedidos',
            'despachos.gestionar'=> '/rutas',
            'chat.usar'          => '/chat',
            'reportes.ver'       => '/reportes',
            'productos.ver'      => '/productos',
            'usuarios.ver'       => '/usuarios',
        ];

        foreach ($rutasPorPermiso as $perm => $ruta) {
            if ($user->can($perm)) {
                return $ruta;
            }
        }

        // 4. Fallback
        return '/pedidos';
    }

    /**
     * Intenta resolver el tenant actual por subdominio para mostrar su branding en login.
     * Devuelve null si es el dominio principal o un subdominio reservado.
     */
    private function resolverTenantLogin(): ?\App\Models\Tenant
    {
        $host = request()->getHost();
        $base = config('app.tenant_base_domain', 'tecnobyte360.com');

        if ($host === $base) return null;
        if (!str_ends_with($host, '.' . $base)) return null;

        $sub = strtolower(substr($host, 0, -strlen('.' . $base)));
        $reservados = ['www', 'api', 'admin', 'app', 'mail', 'pedidosonline'];
        if (in_array($sub, $reservados, true)) return null;

        return \App\Models\Tenant::withoutGlobalScopes()
            ->where('slug', $sub)
            ->where('activo', true)
            ->first();
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'activo' => true], $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Credenciales inválidas o usuario inactivo.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->update(['ultimo_login_at' => now()]);

        // Limpiar la URL "intended"
        $request->session()->forget('url.intended');

        // 🎭 Super-admin logueado desde subdominio de tenant → impersonar
        // automáticamente ese tenant. Sin esto el super-admin sería enviado a
        // /admin/tenants → middleware lo redirige a admin.kivox.co → mala UX.
        try {
            if ($user->hasRole('super-admin')) {
                $tenantSub = $this->resolverTenantLogin();
                if ($tenantSub) {
                    session(['tenant_imitado_id' => $tenantSub->id]);
                    return redirect('/pedidos'); // dashboard del tenant
                }
            }
        } catch (\Throwable $e) { /* ignorar */ }

        return redirect($this->rutaInicialPara($user));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
