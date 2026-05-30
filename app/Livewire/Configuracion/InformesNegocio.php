<?php

namespace App\Livewire\Configuracion;

use App\Models\Tenant;
use App\Models\TenantInformeConfig;
use App\Services\InformeNegocioService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class InformesNegocio extends Component
{
    public ?int $tenantId = null;
    public bool $activo = false;
    public string $frecuencia = 'semanal';
    public ?int $diaSemana = 1;     // lunes
    public ?int $diaMes = 1;
    public string $horaEnvio = '08:00';
    public array $emails = [];      // array de strings
    public string $nuevoEmail = '';

    // Toggles métricas
    public bool $incVolumen = true;
    public bool $incHorasPico = true;
    public bool $incTiempoRespuesta = true;
    public bool $incReacciones = true;
    public bool $incTopClientes = true;
    public bool $incSinResponder = true;
    public bool $incPalabrasTop = false;

    public function mount(): void
    {
        // Detectar tenant a configurar (TenantManager ya está seteado por middleware ?as_tenant=X)
        $this->tenantId = app(\App\Services\TenantManager::class)->id();
        if (!$this->tenantId) {
            // Si no hay tenant seleccionado → primer tenant disponible para UX
            $first = Tenant::query()->withoutGlobalScopes()->orderBy('nombre')->first();
            $this->tenantId = $first?->id;
        }
        $this->cargarConfig();
    }

    private function cargarConfig(): void
    {
        if (!$this->tenantId) return;

        $cfg = TenantInformeConfig::where('tenant_id', $this->tenantId)->first();
        if (!$cfg) return;

        $this->activo = (bool) $cfg->activo;
        $this->frecuencia = $cfg->frecuencia;
        $this->diaSemana = (int) ($cfg->dia_semana ?? 1);
        $this->diaMes = (int) ($cfg->dia_mes ?? 1);
        $this->horaEnvio = substr($cfg->hora_envio, 0, 5);
        $this->emails = is_array($cfg->emails) ? $cfg->emails : [];
        $this->incVolumen        = (bool) $cfg->inc_volumen;
        $this->incHorasPico      = (bool) $cfg->inc_horas_pico;
        $this->incTiempoRespuesta= (bool) $cfg->inc_tiempo_respuesta;
        $this->incReacciones     = (bool) $cfg->inc_reacciones;
        $this->incTopClientes    = (bool) $cfg->inc_top_clientes;
        $this->incSinResponder   = (bool) $cfg->inc_sin_responder;
        $this->incPalabrasTop    = (bool) $cfg->inc_palabras_top;
    }

    public function agregarEmail(): void
    {
        $email = trim($this->nuevoEmail);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Email inválido']);
            return;
        }
        if (in_array($email, $this->emails, true)) {
            $this->nuevoEmail = '';
            return;
        }
        $this->emails[] = $email;
        $this->nuevoEmail = '';
    }

    public function quitarEmail(int $idx): void
    {
        unset($this->emails[$idx]);
        $this->emails = array_values($this->emails);
    }

    public function guardar(): void
    {
        if (!$this->tenantId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Elegí un tenant primero']);
            return;
        }

        TenantInformeConfig::updateOrCreate(
            ['tenant_id' => $this->tenantId],
            [
                'activo'                 => $this->activo,
                'frecuencia'             => $this->frecuencia,
                'dia_semana'             => $this->frecuencia === 'semanal' ? $this->diaSemana : null,
                'dia_mes'                => $this->frecuencia === 'mensual' ? $this->diaMes : null,
                'hora_envio'             => $this->horaEnvio . ':00',
                'emails'                 => $this->emails,
                'telefonos_whatsapp'     => [],
                'inc_volumen'            => $this->incVolumen,
                'inc_horas_pico'         => $this->incHorasPico,
                'inc_tiempo_respuesta'   => $this->incTiempoRespuesta,
                'inc_reacciones'         => $this->incReacciones,
                'inc_top_clientes'       => $this->incTopClientes,
                'inc_sin_responder'      => $this->incSinResponder,
                'inc_palabras_top'       => $this->incPalabrasTop,
            ]
        );

        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Configuración guardada']);
    }

    public function enviarPrueba(): void
    {
        if (!$this->tenantId || empty($this->emails)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Configurá al menos 1 email primero']);
            return;
        }
        $this->guardar();

        try {
            Artisan::call('informes:enviar', ['--tenant' => $this->tenantId]);
            $this->dispatch('notify', ['type' => 'success', 'message' => '📧 Informe de prueba enviado a ' . count($this->emails) . ' email(s)']);
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        $tenants = Tenant::query()->withoutGlobalScopes()->orderBy('nombre')->get(['id', 'nombre']);
        $tenantActual = $tenants->firstWhere('id', $this->tenantId);
        return view('livewire.configuracion.informes-negocio', [
            'tenants' => $tenants,
            'tenantActual' => $tenantActual,
        ])->layout('layouts.app');
    }
}
