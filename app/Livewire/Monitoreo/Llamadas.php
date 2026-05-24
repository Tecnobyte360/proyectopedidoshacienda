<?php

namespace App\Livewire\Monitoreo;

use App\Models\WhatsappCall;
use App\Models\WhatsappCallPermission;
use App\Services\Meta\MetaCallingService;
use App\Services\TenantManager;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Llamadas extends Component
{
    public int    $horas      = 24;
    public string $filtroDir  = ''; // '' | outbound | inbound
    public string $filtroEst  = ''; // '' | connected | ended | failed | rejected | no_permission
    public string $tab        = 'historial'; // historial | permisos

    /** Form para solicitar permiso manualmente */
    public string $telPermiso = '';

    public function mount(): void {}

    #[Computed]
    public function llamadas()
    {
        return WhatsappCall::query()
            ->where('created_at', '>=', now()->subHours($this->horas))
            ->when($this->filtroDir, fn ($q) => $q->where('direccion', $this->filtroDir))
            ->when($this->filtroEst, fn ($q) => $q->where('estado', $this->filtroEst))
            ->with(['cliente:id,nombre,foto_url', 'operador:id,name', 'conversacion:id,telefono_normalizado'])
            ->orderByDesc('id')
            ->limit(150)
            ->get();
    }

    #[Computed]
    public function permisos()
    {
        return WhatsappCallPermission::query()
            ->orderByDesc('respondido_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get();
    }

    #[Computed]
    public function kpis(): array
    {
        $base = WhatsappCall::query()->where('created_at', '>=', now()->subHours($this->horas));
        $total      = (clone $base)->count();
        $conectadas = (clone $base)->whereIn('estado', [
            WhatsappCall::ESTADO_CONNECTED, WhatsappCall::ESTADO_ENDED,
        ])->count();
        $fallidas   = (clone $base)->whereIn('estado', [
            WhatsappCall::ESTADO_FAILED, WhatsappCall::ESTADO_REJECTED, WhatsappCall::ESTADO_NO_PERMISSION,
        ])->count();
        $segs   = (int) ((clone $base)->sum('duracion_seg') ?: 0);
        $costo  = (float) ((clone $base)->sum('costo_usd') ?: 0);
        $perms  = WhatsappCallPermission::query()
            ->where('estado', WhatsappCallPermission::ACCEPTED)
            ->where(function ($q) {
                $q->whereNull('expira_at')->orWhere('expira_at', '>', now());
            })->count();

        return [
            'total'      => $total,
            'conectadas' => $conectadas,
            'fallidas'   => $fallidas,
            'minutos'    => round($segs / 60, 1),
            'costo_usd'  => round($costo, 4),
            'perms_ok'   => $perms,
        ];
    }

    public function solicitarPermisoManual(): void
    {
        $this->validate(['telPermiso' => 'required|string|min:10']);
        $tenantId = app(TenantManager::class)->id() ?? auth()->user()->tenant_id ?? null;
        if (!$tenantId) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Sin tenant activo. Selecciona un tenant en el header.']);
            return;
        }
        $ok = app(MetaCallingService::class)->solicitarPermiso($this->telPermiso, (int) $tenantId);
        if ($ok) {
            $this->telPermiso = '';
            unset($this->permisos, $this->kpis);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Solicitud de permiso enviada al cliente']);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo enviar (revisar config Meta + Calling API habilitado)']);
        }
    }

    public function render()
    {
        return view('livewire.monitoreo.llamadas')->layout('layouts.app');
    }
}
