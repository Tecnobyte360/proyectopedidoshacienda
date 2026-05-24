<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * 🔑 Flujo "olvidé mi contraseña":
 *   GET  /forgot-password         → form de email
 *   POST /forgot-password         → genera token + email
 *   GET  /reset-password/{token}  → form nueva contraseña
 *   POST /reset-password          → guarda + login
 */
class PasswordResetController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot-password', ['tenantBranding' => $this->tenantBranding()]);
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email|max:150']);

        $user = User::where('email', $request->email)->first();

        // Por seguridad, mostramos el mismo mensaje aunque no exista el email
        // (evita enumeración de usuarios)
        if (!$user) {
            return back()->with('status', 'Si el correo existe en nuestro sistema, te enviaremos un enlace para restablecer tu contraseña.');
        }

        // Generar token y guardar
        $token = Str::random(64);
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['email' => $request->email, 'token' => Hash::make($token), 'created_at' => now()]
        );

        // Construir URL completa con el host actual (subdominio del tenant si aplica)
        $resetUrl = $request->getSchemeAndHttpHost() . '/reset-password/' . $token
            . '?email=' . urlencode($request->email);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));
        } catch (\Throwable $e) {
            \Log::error('Error enviando reset password: ' . $e->getMessage());
            return back()->withErrors(['email' => 'No pudimos enviar el correo. Intenta más tarde o contacta soporte.']);
        }

        return back()->with('status', "✓ Listo. Revisa tu correo ({$user->email}) para continuar.");
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token'          => $token,
            'email'          => $request->query('email', ''),
            'tenantBranding' => $this->tenantBranding(),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token'                 => 'required|string',
            'email'                 => 'required|email|max:150',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        // Buscar token en la tabla
        $row = \DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        if (!$row || !Hash::check($data['token'], $row->token)) {
            return back()->withErrors(['email' => 'El enlace es inválido o ya fue usado.']);
        }

        // Validar expiración (60 min)
        if (\Carbon\Carbon::parse($row->created_at)->addMinutes(60)->isPast()) {
            \DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            return back()->withErrors(['email' => 'El enlace expiró. Solicita uno nuevo.']);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Usuario no encontrado.']);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        \DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        // Auto-login
        \Auth::login($user);
        $request->session()->regenerate();

        return redirect('/pedidos')->with('status', '✓ Contraseña restablecida. Bienvenido de vuelta.');
    }

    private function tenantBranding(): ?\App\Models\Tenant
    {
        $host = request()->getHost();
        $base = config('app.tenant_base_domain', 'tecnobyte360.com');
        if ($host === $base || !str_ends_with($host, '.' . $base)) return null;
        $sub = strtolower(substr($host, 0, -strlen('.' . $base)));
        if (in_array($sub, ['www','api','admin','app','mail','pedidosonline'], true)) return null;
        return \App\Models\Tenant::withoutGlobalScopes()->where('slug', $sub)->first();
    }
}
