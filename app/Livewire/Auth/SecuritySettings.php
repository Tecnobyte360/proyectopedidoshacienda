<?php

namespace App\Livewire\Auth;

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SecuritySettings extends Component
{
    public ?string $secret = null;
    public ?string $otpauth = null;
    public ?string $qrUrl = null;
    public ?array $recoveryCodes = null;

    public function mount(): void
    {
        if (session()->has('2fa.recovery_codes')) {
            $this->recoveryCodes = session('2fa.recovery_codes');
        }
        // Si hay enrolamiento en curso (después de redirect), rehidratar el QR
        $sec = session('2fa.enroll_secret_view');
        if ($sec && !Auth::user()->tieneDosFactor()) {
            $this->secret = $sec;
            $this->regenerarQr();
        }
    }

    public function iniciarEnroll(): void
    {
        $svc = app(TwoFactorService::class);
        $this->secret = $svc->generarSecreto();
        session(['2fa.enroll_secret_view' => $this->secret]);
        $this->regenerarQr();
    }

    private function regenerarQr(): void
    {
        $svc = app(TwoFactorService::class);
        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        $issuer = $cfg->nombre ?: 'Kivox';
        $this->otpauth = $svc->urlOtpauth($this->secret, Auth::user()->email, $issuer);
        $this->qrUrl = $svc->urlQrCode($this->otpauth, 220);
    }

    public function render()
    {
        return view('livewire.auth.security-settings', [
            'user' => Auth::user(),
        ])->layout('layouts.app');
    }
}
