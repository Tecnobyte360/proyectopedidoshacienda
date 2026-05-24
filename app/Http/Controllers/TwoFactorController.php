<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $svc) {}

    /** 📱 GET /two-factor-challenge — Form para meter código tras login */
    public function showChallenge(Request $request)
    {
        if (!$request->session()->has('2fa.user_id')) {
            return redirect('/login');
        }
        return view('auth.two-factor-challenge');
    }

    /** POST /two-factor-challenge — Verifica código + completa login */
    public function verifyChallenge(Request $request)
    {
        $request->validate(['code' => 'required|string|max:20']);

        $userId = $request->session()->get('2fa.user_id');
        if (!$userId) return redirect('/login');

        $user = \App\Models\User::find($userId);
        if (!$user || !$user->tieneDosFactor()) {
            return redirect('/login');
        }

        $code = trim($request->code);
        $valido = false;

        // Detectar si es código TOTP (6 dígitos) o de respaldo (XXXX-YYYY)
        if (preg_match('/^\d{6}$/', $code)) {
            $valido = $this->svc->verificarCodigo($user->two_factor_secret, $code);
        } elseif (preg_match('/^[A-Z0-9]{4}-?[A-Z0-9]{4}$/i', $code)) {
            $codeNorm = strtoupper(str_replace(' ', '', $code));
            if (!str_contains($codeNorm, '-')) {
                $codeNorm = substr($codeNorm, 0, 4) . '-' . substr($codeNorm, 4);
            }
            $valido = $this->svc->consumirCodigoRespaldo($user, $codeNorm);
        }

        if (!$valido) {
            return back()->withErrors(['code' => 'Código inválido. Verifica e intenta de nuevo.']);
        }

        // Login real
        Auth::login($user, $request->session()->get('2fa.remember', false));
        $request->session()->regenerate();
        $request->session()->forget(['2fa.user_id', '2fa.remember']);

        $user->update(['ultimo_login_at' => now()]);

        return redirect()->intended('/pedidos');
    }

    /** GET /perfil/seguridad — Página de configuración 2FA del usuario logueado */
    public function showSettings()
    {
        $user = Auth::user();
        return view('auth.security-settings', ['user' => $user]);
    }

    /** POST /perfil/seguridad/iniciar-2fa — Genera secret + QR (no activa todavía) */
    public function startEnroll(Request $request)
    {
        $user = Auth::user();
        if ($user->tieneDosFactor()) {
            return back()->with('status', 'Ya tienes 2FA activado.');
        }

        $secret = $this->svc->generarSecreto();
        $request->session()->put('2fa.enroll_secret', $secret);

        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        $issuer = $cfg->nombre ?: 'Kivox';
        $otpauth = $this->svc->urlOtpauth($secret, $user->email, $issuer);

        return view('auth.security-settings', [
            'user'    => $user,
            'secret'  => $secret,
            'otpauth' => $otpauth,
            'qrUrl'   => $this->svc->urlQrCode($otpauth, 220),
        ]);
    }

    /** POST /perfil/seguridad/confirmar-2fa — Verifica primer código + activa */
    public function confirmEnroll(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $secret = $request->session()->get('2fa.enroll_secret');
        if (!$secret) {
            return back()->withErrors(['code' => 'Sesión expirada. Reinicia el proceso.']);
        }

        if (!$this->svc->verificarCodigo($secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto. Verifica el reloj de tu dispositivo y vuelve a intentar.']);
        }

        $codes = $this->svc->habilitar(Auth::user(), $secret);
        $request->session()->forget('2fa.enroll_secret');
        $request->session()->flash('2fa.recovery_codes', $codes);

        return redirect()->route('perfil.seguridad')->with('status', '✓ 2FA activado correctamente. Guarda tus códigos de respaldo.');
    }

    /** POST /perfil/seguridad/desactivar-2fa — Desactiva 2FA (requiere password) */
    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $user = Auth::user();
        if (!\Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Contraseña incorrecta.']);
        }

        $this->svc->deshabilitar($user);
        return back()->with('status', '✓ 2FA desactivado.');
    }
}
