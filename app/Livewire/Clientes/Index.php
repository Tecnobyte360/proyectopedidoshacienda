<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Models\ZonaCobertura;
use App\Services\WhatsappContactosService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $filtroEstado  = 'todos';   // todos | activos | inactivos | recurrentes | nuevos
    public string $orden         = 'recientes'; // recientes | mayor_gasto | mas_pedidos | nombre

    public bool $modalAbierto = false;
    public ?int $clienteVerId = null;
    public ?int $editandoId   = null;

    public string  $nombre              = '';
    public string  $pais_codigo         = '+57';
    public string  $telefono            = '';
    public string  $email               = '';
    public ?string $fecha_nacimiento    = null;
    public string  $direccion_principal = '';
    public string  $barrio              = '';
    public ?int    $zona_cobertura_id   = null;
    public string  $notas_internas      = '';
    public bool    $activo              = true;

    // Importación WhatsApp
    public bool $modalImportarWa     = false;
    public bool $importandoWa        = false;
    public bool $actualizarExistentes = false;
    public ?array $resultadoImportWa = null;

    public array $paises = [
        ['codigo' => '+57',  'nombre' => 'Colombia',       'flag' => '🇨🇴'],
        ['codigo' => '+1',   'nombre' => 'Estados Unidos', 'flag' => '🇺🇸'],
        ['codigo' => '+52',  'nombre' => 'México',         'flag' => '🇲🇽'],
        ['codigo' => '+34',  'nombre' => 'España',         'flag' => '🇪🇸'],
        ['codigo' => '+51',  'nombre' => 'Perú',           'flag' => '🇵🇪'],
        ['codigo' => '+593', 'nombre' => 'Ecuador',        'flag' => '🇪🇨'],
        ['codigo' => '+58',  'nombre' => 'Venezuela',      'flag' => '🇻🇪'],
    ];

    protected function rules(): array
    {
        return [
            'nombre'              => 'required|string|max:120',
            'pais_codigo'         => 'required|string|max:6',
            'telefono'            => 'required|string|max:30',
            'email'               => 'nullable|email|max:120',
            'fecha_nacimiento'    => 'nullable|date|before:today',
            'direccion_principal' => 'nullable|string|max:255',
            'barrio'              => 'nullable|string|max:120',
            'zona_cobertura_id'   => 'nullable|exists:zonas_cobertura,id',
            'notas_internas'      => 'nullable|string|max:1000',
            'activo'              => 'boolean',
        ];
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }
    public function updatingOrden(): void { $this->resetPage(); }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $cli = Cliente::findOrFail($id);

        $this->editandoId          = $cli->id;
        $this->nombre              = $cli->nombre;
        $this->pais_codigo         = $cli->pais_codigo ?? '+57';
        $this->telefono            = $cli->telefono;
        $this->email               = (string) $cli->email;
        $this->fecha_nacimiento    = $cli->fecha_nacimiento?->format('Y-m-d');
        $this->direccion_principal = (string) $cli->direccion_principal;
        $this->barrio              = (string) $cli->barrio;
        $this->zona_cobertura_id   = $cli->zona_cobertura_id;
        $this->notas_internas      = (string) $cli->notas_internas;
        $this->activo              = (bool) $cli->activo;

        $this->modalAbierto = true;
    }

    public function verCliente(int $id): void
    {
        $this->clienteVerId = $id;
    }

    public function cerrarVer(): void
    {
        $this->clienteVerId = null;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetCampos();
    }

    public function guardar(): void
    {
        $data = $this->validate();
        $data['telefono_normalizado'] = Cliente::normalizarTelefono(
            $this->pais_codigo,
            $this->telefono
        );
        $data['canal_origen'] = 'manual';

        Cliente::updateOrCreate(['id' => $this->editandoId], $data);

        $this->cerrarModal();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Cliente actualizado.' : 'Cliente creado.',
        ]);
    }

    public function eliminar(int $id): void
    {
        $cli = Cliente::withCount('pedidos')->findOrFail($id);

        if ($cli->pedidos_count > 0) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => "No se puede eliminar — tiene {$cli->pedidos_count} pedido(s).",
            ]);
            return;
        }

        $cli->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Cliente eliminado.',
        ]);
    }

    public function abrirModalImportarWa(): void
    {
        $this->resultadoImportWa    = null;
        $this->actualizarExistentes = false;
        $this->modalImportarWa      = true;
    }

    public function cerrarModalImportarWa(): void
    {
        $this->modalImportarWa = false;
        $this->importandoWa    = false;
    }

    public function importarContactosWhatsapp(): void
    {
        $this->importandoWa     = true;
        $this->resultadoImportWa = null;

        try {
            $resumen = app(WhatsappContactosService::class)
                ->importar(null, $this->actualizarExistentes);

            $this->resultadoImportWa = $resumen;

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "✓ Importación: {$resumen['creados']} creados, {$resumen['actualizados']} actualizados, {$resumen['omitidos']} omitidos.",
            ]);
        } catch (\Throwable $e) {
            $this->resultadoImportWa = ['error' => $e->getMessage()];
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '❌ ' . $e->getMessage(),
            ]);
        } finally {
            $this->importandoWa = false;
        }
    }

    public function recalcular(int $id): void
    {
        Cliente::findOrFail($id)->recalcularMetricas();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Métricas recalculadas.',
        ]);
    }

    private function resetCampos(): void
    {
        $this->editandoId          = null;
        $this->nombre              = '';
        $this->pais_codigo         = '+57';
        $this->telefono            = '';
        $this->email               = '';
        $this->fecha_nacimiento    = null;
        $this->direccion_principal = '';
        $this->barrio              = '';
        $this->zona_cobertura_id   = null;
        $this->notas_internas      = '';
        $this->activo              = true;
        $this->resetValidation();
    }

    public function render()
    {
        $clientes = Cliente::query()
            ->with(['zonaCobertura'])
            ->withCount('pedidos')
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('nombre', 'like', "%{$this->search}%")
                       ->orWhere('telefono', 'like', "%{$this->search}%")
                       ->orWhere('telefono_normalizado', 'like', "%{$this->search}%")
                       ->orWhere('email', 'like', "%{$this->search}%")
                       ->orWhere('barrio', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filtroEstado === 'activos',     fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivos',   fn ($q) => $q->where('activo', false))
            ->when($this->filtroEstado === 'recurrentes', fn ($q) => $q->where('total_pedidos', '>=', 2))
            ->when($this->filtroEstado === 'nuevos',      fn ($q) => $q->where('total_pedidos', '<=', 1));

        $clientes = match ($this->orden) {
            'mayor_gasto'  => $clientes->orderByDesc('total_gastado'),
            'mas_pedidos'  => $clientes->orderByDesc('total_pedidos'),
            'nombre'       => $clientes->orderBy('nombre'),
            default        => $clientes->orderByDesc('fecha_ultimo_pedido')->orderByDesc('id'),
        };

        $clientes = $clientes->paginate(15);

        $clienteVer = $this->clienteVerId
            ? Cliente::with(['zonaCobertura', 'pedidos.detalles'])->find($this->clienteVerId)
            : null;

        // KPIs globales
        $totales = [
            'total'       => Cliente::count(),
            'activos'     => Cliente::where('activo', true)->count(),
            'recurrentes' => Cliente::where('total_pedidos', '>=', 2)->count(),
            'gastoTotal'  => (float) Cliente::sum('total_gastado'),
        ];

        $zonas = ZonaCobertura::orderBy('nombre')->get();

        return view('livewire.clientes.index', compact('clientes', 'clienteVer', 'totales', 'zonas'))
            ->layout('layouts.app');
    }
}
