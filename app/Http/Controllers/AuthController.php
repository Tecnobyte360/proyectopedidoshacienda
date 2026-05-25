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

        // Si el tenant está moroso, mostramos un aviso amistoso en el login
        $tenantMoroso = $tenant && $tenant->suspendido_por_mora;

        return view('auth.login', [
            'tenantBranding' => $tenant,
            'tenantMoroso'   => $tenantMoroso,
        ]);
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

        // Buscar tenant sin filtrar por activo — queremos mostrar branding
        // incluso para tenants morosos para que sepan dónde están parados.
        return \App\Models\Tenant::withoutGlobalScopes()
            ->where('slug', $sub)
            ->first();
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        // Validar credenciales SIN loguear (para poder interceptar 2FA primero)
        $user = \App\Models\User::where('email', $data['email'])->where('activo', true)->first();
        if (!$user || !\Hash::check($data['password'], $user->password)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Credenciales inválidas o usuario inactivo.']);
        }

        // 🔐 Si tiene 2FA activado, NO logueamos todavía. Guardamos en sesión
        //    y mandamos a la pantalla challenge para que ingrese el código.
        if ($user->tieneDosFactor()) {
            $request->session()->put([
                '2fa.user_id'  => $user->id,
                '2fa.remember' => $remember,
            ]);
            return redirect()->route('two-factor.challenge');
        }

        // 🆕 Si NO tiene 2FA pero su admin lo exigió (o su tenant lo exige y ya
        // pasó la gracia) → forzar enrollment ANTES de completar el login.
        //    Guarda credenciales en sesión y manda a /two-factor-enroll
        //    donde escanea el QR y verifica el primer código.
        if ($this->debeEnrollar2fa($user)) {
            $request->session()->put([
                '2fa.enroll_user_id'  => $user->id,
                '2fa.enroll_remember' => $remember,
            ]);
            return redirect()->route('two-factor.enroll');
        }

        // Login normal sin 2FA
        Auth::login($user, $remember);
        $request->session()->regenerate();
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

    /**
     * Determina si el usuario debe activar 2FA antes de entrar a la plataforma.
     *
     * Reglas:
     *  - Si el admin marcó individualmente requiere_2fa=true → SÍ (sin gracia)
     *  - Si el tenant exige 2FA y ya pasó el período de gracia → SÍ
     *  - En otro caso → NO (puede entrar normal o tiene gracia)
     */
    private function debeEnrollar2fa(\App\Models\User $user): bool
    {
        // Forzado individual = sin gracia, prioridad máxima
        if (!empty($user->requiere_2fa)) {
            return true;
        }

        // Forzado por política del tenant: respetar gracia
        $tenant = $user->tenant_id ? \App\Models\Tenant::withoutGlobalScopes()->find($user->tenant_id) : null;
        if ($tenant && $tenant->requiere_2fa) {
            $diasGracia = $tenant->gracia_2fa_dias ?? 3;
            $desde = $tenant->requiere_2fa_desde;
            if (!$desde) return true;
            $deadline = $desde->copy()->addDays($diasGracia);
            return now()->gte($deadline);
        }

        return false;
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
