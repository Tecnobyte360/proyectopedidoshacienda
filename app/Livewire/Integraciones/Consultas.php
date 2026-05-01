<?php

namespace App\Livewire\Integraciones;

use App\Models\Integracion;
use App\Models\IntegracionConsulta;
use App\Services\ConsultaIntegracionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Consultas extends Component
{
    public Integracion $integracion;

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public string $nombre         = '';
    public string $nombre_publico = '';
    public string $descripcion    = '';
    public string $tipo           = 'otros';
    public string $query_sql      = '';
    public array  $parametros     = [];   // [{nombre, tipo, descripcion, requerido}]
    public bool   $usar_en_bot    = false;
    public bool   $activa         = true;

    // Test runner
    public ?int   $probandoId      = null;
    public array  $paramsPrueba    = [];
    public ?array $resultadoPrueba = null;

    protected function rules(): array
    {
        return [
            'nombre'         => 'required|string|max:80|regex:/^[a-z0-9_]+$/',
            'nombre_publico' => 'required|string|max:150',
            'descripcion'    => 'nullable|string|max:1000',
            'tipo'           => 'required|in:' . implode(',', array_keys(IntegracionConsulta::TIPOS)),
            'query_sql'      => 'required|string',
            'usar_en_bot'    => 'boolean',
            'activa'         => 'boolean',
        ];
    }

    public function mount(int $integracion): void
    {
        $this->integracion = Integracion::findOrFail($integracion);
    }

    public function abrirCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $c = IntegracionConsulta::findOrFail($id);
        $this->editandoId     = $c->id;
        $this->nombre         = $c->nombre;
        $this->nombre_publico = $c->nombre_publico;
        $this->descripcion    = (string) $c->descripcion;
        $this->tipo           = $c->tipo;
        $this->query_sql      = $c->query_sql;
        $this->parametros     = $c->parametros ?? [];
        $this->usar_en_bot    = (bool) $c->usar_en_bot;
        $this->activa         = (bool) $c->activa;
        $this->modalAbierto   = true;
    }

    public function agregarParametro(): void
    {
        $this->parametros[] = [
            'nombre'      => '',
            'tipo'        => 'string',
            'descripcion' => '',
            'requerido'   => true,
        ];
    }

    public function eliminarParametro(int $idx): void
    {
        unset($this->parametros[$idx]);
        $this->parametros = array_values($this->parametros);
    }

    public function guardar(): void
    {
        $this->validate();

        $params = collect($this->parametros)
            ->filter(fn ($p) => !empty($p['nombre']))
            ->values()
            ->all();

        $data = [
            'integracion_id' => $this->integracion->id,
            'tenant_id'      => $this->integracion->tenant_id,
            'nombre'         => $this->nombre,
            'nombre_publico' => $this->nombre_publico,
            'descripcion'    => $this->descripcion,
            'tipo'           => $this->tipo,
            'query_sql'      => $this->query_sql,
            'parametros'     => $params,
            'usar_en_bot'    => $this->usar_en_bot,
            'activa'         => $this->activa,
        ];

        if ($this->editandoId) {
            IntegracionConsulta::findOrFail($this->editandoId)->update($data);
            $msg = '✓ Consulta actualizada';
        } else {
            IntegracionConsulta::create($data);
            $msg = '✓ Consulta creada';
        }

        $this->modalAbierto = false;
        $this->resetCampos();
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    public function eliminar(int $id): void
    {
        IntegracionConsulta::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Consulta eliminada']);
    }

    public function toggleBot(int $id): void
    {
        $c = IntegracionConsulta::findOrFail($id);
        $c->update(['usar_en_bot' => !$c->usar_en_bot]);
    }

    public function toggleActiva(int $id): void
    {
        $c = IntegracionConsulta::findOrFail($id);
        $c->update(['activa' => !$c->activa]);
    }

    /**
     * Modal de prueba: ejecuta la consulta con params de prueba.
     */
    public function abrirProbar(int $id): void
    {
        $this->probandoId       = $id;
        $this->paramsPrueba     = [];
        $this->resultadoPrueba  = null;

        $c = IntegracionConsulta::find($id);
        foreach ((array) ($c->parametros ?? []) as $p) {
            $this->paramsPrueba[$p['nombre']] = '';
        }
    }

    public function ejecutarPrueba(): void
    {
        if (!$this->probandoId) return;
        $c = IntegracionConsulta::findOrFail($this->probandoId);
        $this->resultadoPrueba = app(ConsultaIntegracionService::class)
            ->ejecutar($c, $this->paramsPrueba, 50);
    }

    public function cerrarProbar(): void
    {
        $this->probandoId = null;
        $this->paramsPrueba = [];
        $this->resultadoPrueba = null;
    }

    private function resetCampos(): void
    {
        $this->editandoId     = null;
        $this->nombre         = '';
        $this->nombre_publico = '';
        $this->descripcion    = '';
        $this->tipo           = 'otros';
        $this->query_sql      = '';
        $this->parametros     = [];
        $this->usar_en_bot    = false;
        $this->activa         = true;
    }

    public function render()
    {
        $consultas = IntegracionConsulta::where('integracion_id', $this->integracion->id)
            ->orderByDesc('usar_en_bot')
            ->orderBy('tipo')
            ->orderBy('nombre_publico')
            ->get();

        $consultaProbando = $this->probandoId
            ? IntegracionConsulta::find($this->probandoId)
            : null;

        return view('livewire.integraciones.consultas', compact('consultas', 'consultaProbando'));
    }
}
