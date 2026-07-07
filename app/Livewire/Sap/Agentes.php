<?php

namespace App\Livewire\Sap;

use App\Models\SapTenantConfig;
use App\Models\Tenant;
use App\Services\Sap\SapServiceLayerClient;
use Livewire\Component;

/**
 * Panel (super-admin) para activar el Asistente IA + SAP por cliente:
 *   - Conexión Service Layer propia de cada tenant (dinámica).
 *   - Planes y agentes activables.
 */
class Agentes extends Component
{
    public ?int $tenantId = null;

    // Conexión Service Layer del tenant
    public bool   $activo       = true;
    public string $sl_mode      = 'direct';
    public string $sl_url       = '';
    public string $sl_company   = '';
    public string $sl_username  = '';
    public string $sl_password  = '';   // solo se guarda si se escribe algo
    public int    $sl_timeout   = 30;
    public string $bridge_url   = '';
    public string $bridge_token = '';
    public bool   $tienePassword = false;

    // Agentes activos (claves del catálogo)
    public array $agentes = [];

    public ?string $pingResultado = null;

    protected function rules(): array
    {
        return [
            'tenantId'    => 'required|integer|exists:tenants,id',
            'sl_mode'     => 'required|in:direct,bridge',
            'sl_url'      => 'nullable|string|max:255',
            'sl_company'  => 'nullable|string|max:120',
            'sl_username' => 'nullable|string|max:120',
            'sl_timeout'  => 'required|integer|min:5|max:120',
            'bridge_url'  => 'nullable|string|max:255',
        ];
    }

    public function seleccionarTenant(int $id): void
    {
        $this->tenantId = $id;
        $this->pingResultado = null;
        $cfg = SapTenantConfig::where('tenant_id', $id)->first();

        if ($cfg) {
            $this->activo        = (bool) $cfg->activo;
            $this->sl_mode       = $cfg->sl_mode ?: 'direct';
            $this->sl_url        = (string) $cfg->sl_url;
            $this->sl_company    = (string) $cfg->sl_company;
            $this->sl_username   = (string) $cfg->sl_username;
            $this->sl_timeout    = (int) ($cfg->sl_timeout ?: 30);
            $this->bridge_url    = (string) $cfg->bridge_url;
            $this->agentes       = (array) ($cfg->agentes ?? []);
            $this->tienePassword = filled($cfg->sl_password);
        } else {
            $this->reset(['activo', 'sl_mode', 'sl_url', 'sl_company', 'sl_username', 'sl_timeout', 'bridge_url', 'bridge_token', 'agentes', 'tienePassword']);
            $this->activo = true;
            $this->sl_mode = 'direct';
            $this->sl_timeout = 30;
        }
        $this->sl_password = '';
    }

    public function activarPlan(string $plan): void
    {
        $agentesPlan = (array) config("sap.planes.{$plan}.agentes", []);
        $this->agentes = array_values(array_unique(array_merge($this->agentes, $agentesPlan)));
    }

    public function desactivarPlan(string $plan): void
    {
        $agentesPlan = (array) config("sap.planes.{$plan}.agentes", []);
        $this->agentes = array_values(array_diff($this->agentes, $agentesPlan));
    }

    public function toggleAgente(string $clave): void
    {
        if (in_array($clave, $this->agentes, true)) {
            $this->agentes = array_values(array_diff($this->agentes, [$clave]));
        } else {
            $this->agentes[] = $clave;
        }
    }

    public function guardar(): void
    {
        $this->validate();

        $datos = [
            'activo'      => $this->activo,
            'sl_mode'     => $this->sl_mode,
            'sl_url'      => $this->sl_url ?: null,
            'sl_company'  => $this->sl_company ?: null,
            'sl_username' => $this->sl_username ?: null,
            'sl_timeout'  => $this->sl_timeout,
            'bridge_url'  => $this->bridge_url ?: null,
            'agentes'     => array_values($this->agentes),
        ];
        // Solo actualizamos la contraseña / token si se escribió algo nuevo.
        if (filled($this->sl_password))  $datos['sl_password']  = $this->sl_password;
        if (filled($this->bridge_token)) $datos['bridge_token'] = $this->bridge_token;

        SapTenantConfig::updateOrCreate(['tenant_id' => $this->tenantId], $datos);

        $this->sl_password = '';
        $this->bridge_token = '';
        $this->tienePassword = $this->tienePassword || filled($datos['sl_password'] ?? null);
        session()->flash('sap_ok', 'Configuración guardada.');
    }

    public function probarConexion(): void
    {
        // Guardamos primero para probar contra lo configurado.
        $this->guardar();
        $ping = SapServiceLayerClient::paraTenant($this->tenantId)->ping();
        $this->pingResultado = ($ping['conecta'] ?? false)
            ? '✅ Conexión exitosa con SAP (' . ($ping['company'] ?? '') . ')'
            : '❌ No se pudo conectar. Revisa URL/credenciales o la red (puerto 50000).';
    }

    public function render()
    {
        return view('livewire.sap.agentes', [
            'tenants'         => Tenant::orderBy('nombre')->get(['id', 'nombre']),
            'planes'          => (array) config('sap.planes', []),
            'agentesCatalogo' => (array) config('sap.agentes', []),
        ])->layout('layouts.app');
    }
}
