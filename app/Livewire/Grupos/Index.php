<?php

namespace App\Livewire\Grupos;

use App\Models\Cliente;
use App\Models\GrupoCliente;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 👥 Gestión de Grupos de clientes (listas dinámicas para difundir plantillas).
 */
class Index extends Component
{
    use WithPagination;

    // Crear/editar grupo
    public bool   $modalGrupo = false;
    public ?int   $grupoId    = null;
    public string $nombre     = '';
    public string $descripcion = '';
    public string $color      = '#d68643';

    // Gestionar miembros
    public bool   $modalMiembros = false;
    public ?int   $grupoActivo   = null;
    public string $buscarCliente = '';

    protected $rules = [
        'nombre'      => 'required|string|max:120',
        'descripcion' => 'nullable|string|max:255',
        'color'       => 'nullable|string|max:20',
    ];

    /* ───────── CRUD grupo ───────── */

    public function nuevoGrupo(): void
    {
        $this->reset(['grupoId', 'nombre', 'descripcion']);
        $this->color = '#d68643';
        $this->modalGrupo = true;
    }

    public function editarGrupo(int $id): void
    {
        $g = GrupoCliente::findOrFail($id);
        $this->grupoId     = $g->id;
        $this->nombre      = $g->nombre;
        $this->descripcion = (string) $g->descripcion;
        $this->color       = $g->color ?: '#d68643';
        $this->modalGrupo  = true;
    }

    public function guardarGrupo(): void
    {
        $this->validate();
        GrupoCliente::updateOrCreate(
            ['id' => $this->grupoId],
            [
                'tenant_id'   => app(\App\Services\TenantManager::class)->id(),
                'nombre'      => $this->nombre,
                'descripcion' => $this->descripcion ?: null,
                'color'       => $this->color ?: '#d68643',
            ]
        );
        $this->modalGrupo = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Grupo guardado.']);
    }

    public function eliminarGrupo(int $id): void
    {
        GrupoCliente::where('id', $id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Grupo eliminado.']);
    }

    /* ───────── Miembros ───────── */

    public function gestionarMiembros(int $id): void
    {
        $this->grupoActivo  = $id;
        $this->buscarCliente = '';
        $this->modalMiembros = true;
    }

    public function agregarCliente(int $clienteId): void
    {
        $g = GrupoCliente::find($this->grupoActivo);
        if ($g) {
            $g->clientes()->syncWithoutDetaching([$clienteId]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Cliente agregado al grupo.']);
        }
    }

    public function quitarCliente(int $clienteId): void
    {
        $g = GrupoCliente::find($this->grupoActivo);
        if ($g) {
            $g->clientes()->detach($clienteId);
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Cliente quitado del grupo.']);
        }
    }

    /* ───────── Difundir ───────── */

    /**
     * Crea una campaña tipo 'grupo' y redirige al editor de campañas para
     * elegir la plantilla y enviar. Reusa todo el motor de campañas.
     */
    public function difundir(int $id): void
    {
        $g = GrupoCliente::withCount('clientes')->find($id);
        if (!$g || $g->clientes_count === 0) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'El grupo no tiene clientes con teléfono.']);
            return;
        }
        // Redirige a campañas con el grupo preseleccionado (query param).
        $this->redirect(route('campanas.index', ['grupo_id' => $id]), navigate: true);
    }

    /* ───────── Computed ───────── */

    public function getGruposProperty()
    {
        return GrupoCliente::withCount('clientes')->latest()->paginate(12);
    }

    public function getMiembrosProperty()
    {
        if (!$this->grupoActivo) return collect();
        return GrupoCliente::find($this->grupoActivo)?->clientes()->get() ?? collect();
    }

    public function getResultadosBusquedaProperty()
    {
        $q = trim($this->buscarCliente);
        if (mb_strlen($q) < 2) return collect();

        $yaEn = $this->miembros->pluck('id')->all();

        return Cliente::whereNotNull('telefono_normalizado')
            ->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('telefono_normalizado', 'like', "%{$q}%")
                  ->orWhere('cedula', 'like', "%{$q}%");
            })
            ->whereNotIn('id', $yaEn)
            ->limit(8)
            ->get();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.grupos.index', [
            'grupos'     => $this->grupos,
            'miembros'   => $this->miembros,
            'resultados' => $this->resultadosBusqueda,
        ]);
    }
}
