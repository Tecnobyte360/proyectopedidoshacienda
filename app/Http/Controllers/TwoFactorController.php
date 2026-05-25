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

    /**
     * 🆕 GET /two-factor-enroll — Pantalla forzada de enrollment ANTES de
     * completar el login. El user vino redirigido desde AuthController@login
     * con '2fa.enroll_user_id' en sesión. Aquí ve el QR y debe verificar
     * el primer código para que activemos su 2FA y completemos el login.
     */
    public function showForcedEnroll(Request $request)
    {
        $userId = $request->session()->get('2fa.enroll_user_id');
        if (!$userId) return redirect('/login');

        $user = \App\Models\User::find($userId);
        if (!$user) return redirect('/login');

        // Reutilizar secret si ya fue generado en este flujo (refresh)
        $secret = $request->session()->get('2fa.enroll_forced_secret');
        if (!$secret) {
            $secret = $this->svc->generarSecreto();
            $request->session()->put('2fa.enroll_forced_secret', $secret);
        }

        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        $issuer = $cfg->nombre ?: 'Kivox';
        $otpauth = $this->svc->urlOtpauth($secret, $user->email, $issuer);

        return view('auth.two-factor-enroll', [
            'user'    => $user,
            'secret'  => $secret,
            'otpauth' => $otpauth,
            'qrUrl'   => $this->svc->urlQrCode($otpauth, 220),
        ]);
    }

    /**
     * POST /two-factor-enroll — Verifica primer código + activa 2FA +
     * completa el login del usuario que estaba en sesión.
     */
    public function confirmForcedEnroll(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $userId = $request->session()->get('2fa.enroll_user_id');
        $secret = $request->session()->get('2fa.enroll_forced_secret');
        if (!$userId || !$secret) return redirect('/login');

        $user = \App\Models\User::find($userId);
        if (!$user) return redirect('/login');

        if (!$this->svc->verificarCodigo($secret, $request->code)) {
            return back()->withErrors(['code' => 'Código incorrecto. Verifica el reloj de tu dispositivo y vuelve a intentar.']);
        }

        // Activar 2FA + generar códigos de respaldo
        $codes = $this->svc->habilitar($user, $secret);

        // Quitar la bandera de "requiere_2fa" — ya la activó
        $user->update([
            'requiere_2fa'       => false,
            'requiere_2fa_desde' => null,
        ]);

        // Limpiar sesión de enrollment
        $remember = (bool) $request->session()->get('2fa.enroll_remember', false);
        $request->session()->forget([
            '2fa.enroll_user_id',
            '2fa.enroll_remember',
            '2fa.enroll_forced_secret',
        ]);

        // Completar login
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->update(['ultimo_login_at' => now()]);

        // Mostrar códigos de respaldo en flash
        $request->session()->flash('2fa.recovery_codes', $codes);
        $request->session()->flash('status', '✓ 2FA activado correctamente. Guarda tus códigos de respaldo en un lugar seguro.');

        return redirect()->intended('/perfil/seguridad');
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
