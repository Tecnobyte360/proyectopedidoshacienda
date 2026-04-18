<?php

namespace App\Livewire;

use App\Models\Domiciliario;
use App\Models\ZonaCobertura;
use Livewire\Component;

class Domiciliarios extends Component
{
    public ?int $domiciliarioId = null;

    public string $nombre       = '';
    public string $pais_codigo  = '+57';
    public string $telefono     = '';
    public string $vehiculo     = '';
    public string $placa        = '';
    public string $estado       = 'disponible';
    public bool   $activo       = true;
    public array  $zonasIds     = [];

    public string $buscar = '';

    public bool $modalAbierto = false;
    public bool $modoEdicion = false;

    /* ==========================
     * VALIDACIONES
     * ==========================*/

    protected function rules(): array
    {
        return [
            'nombre'      => ['required', 'string', 'max:255'],
            'pais_codigo' => ['required', 'string', 'max:6'],
            'telefono'    => ['nullable', 'string', 'max:30'],
            'vehiculo'    => ['nullable', 'string', 'max:100'],
            'placa'       => ['nullable', 'string', 'max:20'],
            'estado'      => ['required', 'in:disponible,ocupado,inactivo'],
            'activo'      => ['boolean'],
            'zonasIds'    => ['array'],
            'zonasIds.*'  => ['integer', 'exists:zonas_cobertura,id'],
        ];
    }

    protected array $messages = [
        'nombre.required' => 'El nombre es obligatorio.',
        'estado.required' => 'El estado es obligatorio.',
        'estado.in'       => 'El estado seleccionado no es válido.',
    ];

    /* ==========================
     * RENDER
     * ==========================*/

    public function render()
    {
        try {
            $domiciliarios = Domiciliario::query()
                ->with('zonas')
                ->when($this->buscar !== '', function ($query) {
                    $query->where(function ($q) {
                        $q->where('nombre', 'like', '%' . $this->buscar . '%')
                            ->orWhere('telefono', 'like', '%' . $this->buscar . '%')
                            ->orWhere('placa', 'like', '%' . $this->buscar . '%')
                            ->orWhere('vehiculo', 'like', '%' . $this->buscar . '%');
                    });
                })
                ->orderBy('nombre')
                ->get();

            return view('livewire.domiciliarios', [
                'domiciliarios' => $domiciliarios,
                'zonasDisponibles' => ZonaCobertura::activas()->orderBy('nombre')->get(),
                'paises'           => Domiciliario::PAISES,
            ])->layout('layouts.app');

        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error al cargar los domiciliarios.',
            ]);

            return view('livewire.domiciliarios', [
                'domiciliarios'    => collect(),
                'zonasDisponibles' => collect(),
                'paises'           => Domiciliario::PAISES,
            ])->layout('layouts.app');
        }
    }

    /* ==========================
     * MODALES
     * ==========================*/

    public function abrirModalCrear(): void
    {
        $this->resetFormulario();
        $this->modoEdicion = false;
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        try {
            $domiciliario = Domiciliario::with('zonas')->findOrFail($id);

            $this->domiciliarioId = $domiciliario->id;
            $this->nombre         = $domiciliario->nombre ?? '';
            $this->pais_codigo    = $domiciliario->pais_codigo ?? '+57';
            $this->telefono       = $domiciliario->telefono ?? '';
            $this->vehiculo       = $domiciliario->vehiculo ?? '';
            $this->placa          = $domiciliario->placa ?? '';
            $this->estado         = $domiciliario->estado ?? 'disponible';
            $this->activo         = (bool) $domiciliario->activo;
            $this->zonasIds       = $domiciliario->zonas->pluck('id')->toArray();

            $this->modoEdicion = true;
            $this->modalAbierto = true;

        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No se pudo cargar el domiciliario.',
            ]);
        }
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
        $this->resetValidation();
    }

    /* ==========================
     * CRUD
     * ==========================*/

    public function guardar(): void
    {
        try {
            $this->validate();

            $datos = [
                'nombre'      => $this->nombre,
                'pais_codigo' => $this->pais_codigo,
                'telefono'    => $this->telefono,
                'vehiculo'    => $this->vehiculo,
                'placa'       => $this->placa,
                'estado'      => $this->estado,
                'activo'      => $this->activo,
            ];

            if ($this->modoEdicion && $this->domiciliarioId) {
                $domiciliario = Domiciliario::findOrFail($this->domiciliarioId);
                $domiciliario->update($datos);

                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Domiciliario actualizado correctamente.',
                ]);
            } else {
                $domiciliario = Domiciliario::create($datos);

                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Domiciliario creado correctamente.',
                ]);
            }

            $domiciliario->zonas()->sync($this->zonasIds);

            $this->cerrarModal();

        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ocurrió un error al guardar el domiciliario.',
            ]);
        }
    }

    public function cambiarActivo(int $id): void
    {
        try {
            $domiciliario = Domiciliario::findOrFail($id);

            $domiciliario->activo = !$domiciliario->activo;

            if (!$domiciliario->activo && $domiciliario->estado !== 'ocupado') {
                $domiciliario->estado = 'inactivo';
            }

            if ($domiciliario->activo && $domiciliario->estado === 'inactivo') {
                $domiciliario->estado = 'disponible';
            }

            $domiciliario->save();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Estado del domiciliario actualizado.',
            ]);

        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No se pudo cambiar el estado.',
            ]);
        }
    }

    /**
     * Libera a todos los domiciliarios activos (pone estado=disponible).
     * Útil cuando quedaron "ocupados" pegados por un bug o reinicio.
     */
    public function liberarTodos(): void
    {
        try {
            $n = Domiciliario::where('activo', true)
                ->whereIn('estado', ['ocupado', 'en_ruta'])
                ->orWhereNull('estado')
                ->update(['estado' => 'disponible']);

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "🔓 {$n} domiciliario(s) liberados. Ahora aparecen disponibles en /pedidos.",
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'Error al liberar: ' . $e->getMessage(),
            ]);
        }
    }

    /* ==========================
     * HELPERS
     * ==========================*/

    private function resetFormulario(): void
    {
        $this->domiciliarioId = null;
        $this->nombre         = '';
        $this->pais_codigo    = '+57';
        $this->telefono       = '';
        $this->vehiculo       = '';
        $this->placa          = '';
        $this->estado         = 'disponible';
        $this->activo         = true;
        $this->zonasIds       = [];
    }
}