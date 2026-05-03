<?php

namespace App\Livewire\Sedes;

use App\Models\Sede;
use App\Services\GeocodingService;
use Livewire\Component;

class Index extends Component
{
    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string  $nombre          = '';
    public string  $direccion       = '';
    public ?float  $latitud         = null;
    public ?float  $longitud        = null;
    public bool    $activa          = true;
    public string  $mensaje_cerrado = '';
    public ?int    $whatsapp_connection_id = null;
    public ?int    $whatsapp_id            = null;
    public string  $whatsapp_telefono      = '';

    // Cobertura de la sede (refactor: cada sede maneja su zona)
    public ?array  $cobertura_poligono       = null;
    public float   $cobertura_costo_envio    = 0;
    public int     $cobertura_tiempo_min     = 45;
    public float   $cobertura_pedido_minimo  = 0;
    public string  $cobertura_color          = '#d68643';
    public string  $cobertura_descripcion    = '';
    public bool    $cobertura_activa         = true;
    public ?float  $cobertura_centro_lat     = null;
    public ?float  $cobertura_centro_lng     = null;

    /** Array editable: [dia_key => ['abierto'=>bool, 'abre'=>'HH:MM', 'cierra'=>'HH:MM']] */
    public array $horarios = [];

    protected function rules(): array
    {
        return [
            'nombre'          => 'required|string|max:120',
            'direccion'       => 'nullable|string|max:255',
            'latitud'         => 'nullable|numeric|between:-90,90',
            'longitud'        => 'nullable|numeric|between:-180,180',
            'activa'          => 'boolean',
            'mensaje_cerrado' => 'nullable|string|max:500',
            'horarios'        => 'array',
            'whatsapp_connection_id' => 'nullable|integer',
            'whatsapp_id'            => 'nullable|integer',
            'whatsapp_telefono'      => 'nullable|string|max:32',
            'cobertura_costo_envio'   => 'numeric|min:0',
            'cobertura_tiempo_min'    => 'integer|min:1|max:480',
            'cobertura_pedido_minimo' => 'numeric|min:0',
            'cobertura_color'         => 'nullable|string|max:10',
            'cobertura_descripcion'   => 'nullable|string|max:500',
            'cobertura_activa'        => 'boolean',
            'cobertura_centro_lat'    => 'nullable|numeric|between:-90,90',
            'cobertura_centro_lng'    => 'nullable|numeric|between:-180,180',
        ];
    }

    public function mount(): void
    {
        $this->resetCampos();
    }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $sede = Sede::findOrFail($id);

        $this->editandoId      = $sede->id;
        $this->nombre          = $sede->nombre;
        $this->direccion       = (string) $sede->direccion;
        $this->latitud         = $sede->latitud;
        $this->longitud        = $sede->longitud;
        $this->activa          = (bool) $sede->activa;
        $this->mensaje_cerrado = (string) $sede->mensaje_cerrado;
        $this->whatsapp_connection_id = $sede->whatsapp_connection_id;
        $this->whatsapp_id            = $sede->whatsapp_id;
        $this->whatsapp_telefono      = (string) $sede->whatsapp_telefono;

        // Cobertura de la sede
        $this->cobertura_poligono      = $sede->cobertura_poligono;
        $this->cobertura_costo_envio   = (float) ($sede->cobertura_costo_envio ?? 0);
        $this->cobertura_tiempo_min    = (int) ($sede->cobertura_tiempo_min ?? 45);
        $this->cobertura_pedido_minimo = (float) ($sede->cobertura_pedido_minimo ?? 0);
        $this->cobertura_color         = (string) ($sede->cobertura_color ?: '#d68643');
        $this->cobertura_descripcion   = (string) ($sede->cobertura_descripcion ?? '');
        $this->cobertura_activa        = (bool) ($sede->cobertura_activa ?? true);
        $this->cobertura_centro_lat    = $sede->cobertura_centro_lat;
        $this->cobertura_centro_lng    = $sede->cobertura_centro_lng;

        // Cargar horarios existentes o defaults
        $existentes = $sede->horarios ?? [];
        $this->horarios = [];
        foreach (Sede::DIAS_SEMANA as $key => $label) {
            $this->horarios[$key] = [
                'abierto' => $existentes[$key]['abierto'] ?? ($key === 'domingo' ? false : true),
                'abre'    => $existentes[$key]['abre']    ?? '08:00',
                'cierra'  => $existentes[$key]['cierra']  ?? ($key === 'sabado' ? '16:00' : '20:00'),
            ];
        }

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    public function geocodificarDireccion(): void
    {
        if (empty(trim($this->direccion))) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Escribe primero una dirección.']);
            return;
        }

        $g = app(GeocodingService::class)->geocodificar($this->direccion, null, 'Bello');
        if ($g) {
            $this->latitud  = $g['lat'];
            $this->longitud = $g['lng'];
            $this->dispatch('notify', ['type' => 'success', 'message' => '✅ Coordenadas obtenidas.']);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => '❌ No se pudo geocodificar esa dirección.']);
        }
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $data['horarios'] = $this->horarios;

        Sede::updateOrCreate(['id' => $this->editandoId], $data);

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? '✅ Sede actualizada.' : '✅ Sede creada.',
        ]);
    }

    public function toggleActiva(int $id): void
    {
        $s = Sede::findOrFail($id);
        $s->activa = !$s->activa;
        $s->save();
    }

    public function eliminar(int $id): void
    {
        $sede = Sede::find($id);
        if (!$sede) return;

        // No eliminar si tiene pedidos
        if (\App\Models\Pedido::where('sede_id', $id)->exists()) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => 'No se puede eliminar — la sede tiene pedidos asociados. Desactívala mejor.',
            ]);
            return;
        }

        $sede->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Sede eliminada.']);
    }

    private function resetCampos(): void
    {
        $this->editandoId      = null;
        $this->nombre          = '';
        $this->direccion       = '';
        $this->latitud         = null;
        $this->longitud        = null;
        $this->activa          = true;
        $this->mensaje_cerrado = '';
        $this->whatsapp_connection_id = null;
        $this->whatsapp_id            = null;
        $this->whatsapp_telefono      = '';

        // Cobertura defaults
        $this->cobertura_poligono       = null;
        $this->cobertura_costo_envio    = 0;
        $this->cobertura_tiempo_min     = 45;
        $this->cobertura_pedido_minimo  = 0;
        $this->cobertura_color          = '#d68643';
        $this->cobertura_descripcion    = '';
        $this->cobertura_activa         = true;
        $this->cobertura_centro_lat     = null;
        $this->cobertura_centro_lng     = null;

        // Defaults: L-V 8a8, S 9a4, D cerrado
        $this->horarios = [];
        foreach (Sede::DIAS_SEMANA as $key => $label) {
            $this->horarios[$key] = [
                'abierto' => $key !== 'domingo',
                'abre'    => '08:00',
                'cierra'  => $key === 'sabado' ? '16:00' : '20:00',
            ];
        }

        $this->resetValidation();
    }

    /**
     * Lista de conexiones WhatsApp disponibles para asignar a una sede.
     * Consulta la API para obtener nombre/teléfono de cada conexión.
     */
    public function conexionesDisponibles(): array
    {
        try {
            $tenant = app(\App\Services\TenantManager::class)->current();
            $resolver = app(\App\Services\WhatsappResolverService::class);
            $token = $resolver->token();

            if (!$token) return [];

            $resp = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withToken($token)
                ->timeout(15)
                ->get(rtrim(config('app.whatsapp_api_base', 'https://wa-api.tecnobyteapp.com:1422'), '/') . '/whatsapp/');

            if ($resp->failed()) return [];

            $idsTenant = $resolver->connectionIdsDelTenant($tenant);
            $whatsapps = collect($resp->json('whatsapps', []));

            return $whatsapps
                ->filter(function ($w) use ($idsTenant) {
                    if (empty($idsTenant)) return true;
                    return in_array((int) ($w['id'] ?? 0), $idsTenant, true);
                })
                ->map(fn ($w) => [
                    'id'       => (int) ($w['id'] ?? 0),
                    'name'     => $w['name'] ?? ('Conexión ' . ($w['id'] ?? '')),
                    'number'   => $w['number'] ?? null,
                    'status'   => $w['status'] ?? 'UNKNOWN',
                ])
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            \Log::warning('No se pudieron cargar conexiones para sedes: ' . $e->getMessage());
            return [];
        }
    }

    public function render()
    {
        $sedes = Sede::orderBy('nombre')->get();
        $conexiones = $this->modalAbierto ? $this->conexionesDisponibles() : [];
        return view('livewire.sedes.index', compact('sedes', 'conexiones'))->layout('layouts.app');
    }
}
