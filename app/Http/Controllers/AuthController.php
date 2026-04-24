<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->intended('/pedidos');
        }

        // Resolver tenant por subdominio para branding dinámico
        $tenant = $this->resolverTenantLogin();

        return view('auth.login', ['tenantBranding' => $tenant]);
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

        return redirect()->intended('/pedidos');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
