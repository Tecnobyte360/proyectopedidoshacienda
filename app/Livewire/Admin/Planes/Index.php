<?php

namespace App\Livewire\Admin\Planes;

use App\Models\Plan;
use Livewire\Component;

class Index extends Component
{
    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string $codigo            = '';
    public string $nombre            = '';
    public string $descripcion       = '';
    public float  $precio_mensual    = 0;
    public float  $precio_anual      = 0;
    public string $moneda            = 'COP';

    public ?int $max_pedidos_mes = null;
    public ?int $max_usuarios    = null;
    public ?int $max_sedes       = null;
    public ?int $max_productos   = null;
    public ?int $max_clientes    = null;

    public bool $feature_whatsapp   = true;
    public bool $feature_ia         = true;
    public bool $feature_reportes   = false;
    public bool $feature_multi_sede = false;
    public bool $feature_api        = false;

    public bool $activo  = true;
    public bool $publico = true;
    public int  $orden   = 0;

    public string $caracteristicas_extra_text = '';   // textarea, una por línea

    protected function rules(): array
    {
        return [
            'codigo'         => 'required|string|max:30|alpha_dash|unique:planes,codigo,' . ($this->editandoId ?? 'NULL'),
            'nombre'         => 'required|string|max:80',
            'descripcion'    => 'nullable|string|max:500',
            'precio_mensual' => 'numeric|min:0',
            'precio_anual'   => 'numeric|min:0',
            'moneda'         => 'required|string|max:5',
            'max_pedidos_mes'=> 'nullable|integer|min:0',
            'max_usuarios'   => 'nullable|integer|min:0',
            'max_sedes'      => 'nullable|integer|min:0',
            'max_productos'  => 'nullable|integer|min:0',
            'max_clientes'   => 'nullable|integer|min:0',
            'orden'          => 'integer|min:0',
        ];
    }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $p = Plan::findOrFail($id);

        $this->editandoId          = $p->id;
        $this->codigo              = $p->codigo;
        $this->nombre              = $p->nombre;
        $this->descripcion         = (string) $p->descripcion;
        $this->precio_mensual      = (float) $p->precio_mensual;
        $this->precio_anual        = (float) $p->precio_anual;
        $this->moneda              = $p->moneda;
        $this->max_pedidos_mes     = $p->max_pedidos_mes;
        $this->max_usuarios        = $p->max_usuarios;
        $this->max_sedes           = $p->max_sedes;
        $this->max_productos       = $p->max_productos;
        $this->max_clientes        = $p->max_clientes;
        $this->feature_whatsapp    = (bool) $p->feature_whatsapp;
        $this->feature_ia          = (bool) $p->feature_ia;
        $this->feature_reportes    = (bool) $p->feature_reportes;
        $this->feature_multi_sede  = (bool) $p->feature_multi_sede;
        $this->feature_api         = (bool) $p->feature_api;
        $this->activo              = (bool) $p->activo;
        $this->publico             = (bool) $p->publico;
        $this->orden               = (int) $p->orden;
        $this->caracteristicas_extra_text = collect($p->caracteristicas_extra ?? [])->implode("\n");

        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $data['feature_whatsapp']   = $this->feature_whatsapp;
        $data['feature_ia']         = $this->feature_ia;
        $data['feature_reportes']   = $this->feature_reportes;
        $data['feature_multi_sede'] = $this->feature_multi_sede;
        $data['feature_api']        = $this->feature_api;
        $data['activo']             = $this->activo;
        $data['publico']            = $this->publico;

        $data['caracteristicas_extra'] = collect(preg_split('/\r\n|\r|\n/', $this->caracteristicas_extra_text))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        Plan::updateOrCreate(['id' => $this->editandoId], $data);

        $this->cerrarModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Plan actualizado.' : 'Plan creado.',
        ]);
    }

    public function toggleActivo(int $id): void
    {
        $p = Plan::find($id);
        if (!$p) return;
        $p->activo = !$p->activo;
        $p->save();
    }

    public function eliminar(int $id): void
    {
        $p = Plan::find($id);
        if (!$p) return;

        if ($p->suscripciones()->exists()) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => 'No se puede eliminar: tiene suscripciones asociadas. Desactívalo mejor.',
            ]);
            return;
        }
        $p->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Plan eliminado.']);
    }

    private function resetCampos(): void
    {
        $this->editandoId         = null;
        $this->codigo             = '';
        $this->nombre             = '';
        $this->descripcion        = '';
        $this->precio_mensual     = 0;
        $this->precio_anual       = 0;
        $this->moneda             = 'COP';
        $this->max_pedidos_mes    = null;
        $this->max_usuarios       = null;
        $this->max_sedes          = null;
        $this->max_productos      = null;
        $this->max_clientes       = null;
        $this->feature_whatsapp   = true;
        $this->feature_ia         = true;
        $this->feature_reportes   = false;
        $this->feature_multi_sede = false;
        $this->feature_api        = false;
        $this->activo             = true;
        $this->publico            = true;
        $this->orden              = 0;
        $this->caracteristicas_extra_text = '';
        $this->resetValidation();
    }

    public function render()
    {
        $planes = Plan::withCount(['suscripciones as suscripciones_activas_count' => function ($q) {
            $q->whereIn('estado', ['activa', 'en_trial']);
        }])->orderBy('orden')->orderBy('precio_mensual')->get();

        return view('livewire.admin.planes.index', compact('planes'))
            ->layout('layouts.app');
    }
}
