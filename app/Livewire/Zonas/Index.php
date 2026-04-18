<?php

namespace App\Livewire\Zonas;

use App\Models\Pedido;
use App\Models\Sede;
use App\Models\ZonaBarrio;
use App\Models\ZonaCobertura;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $filtroEstado  = 'todas';
    public ?int   $filtroSedeId  = null;

    public string $vista = 'lista';   // lista | mapa

    public bool $modalAbierto = false;
    public ?int $editandoId   = null;

    public ?int    $sede_id             = null;
    public string  $nombre              = '';
    public string  $descripcion         = '';
    public string  $color               = '#d68643';
    public float   $costo_envio         = 0;
    public float   $pedido_minimo       = 0;
    public ?int    $tiempo_estimado_min = null;
    public int     $orden               = 0;
    public bool    $activa              = true;

    public string $barriosTexto = '';

    // Geo (vienen del mapa)
    public ?array  $poligono   = null;     // GeoJSON coordinates
    public ?float  $centro_lat = null;
    public ?float  $centro_lng = null;
    public ?float  $area_km2   = null;

    protected function rules(): array
    {
        return [
            'sede_id'             => 'nullable|exists:sedes,id',
            'nombre'              => 'required|string|max:120',
            'descripcion'         => 'nullable|string|max:255',
            'color'               => 'nullable|string|max:16',
            'costo_envio'         => 'numeric|min:0',
            'pedido_minimo'       => 'numeric|min:0',
            'tiempo_estimado_min' => 'nullable|integer|min:1|max:600',
            'orden'               => 'integer|min:0',
            'activa'              => 'boolean',
            'poligono'            => 'nullable|array',
            'centro_lat'          => 'nullable|numeric',
            'centro_lng'          => 'nullable|numeric',
            'area_km2'            => 'nullable|numeric',
        ];
    }

    public function actualizarPoligono(array $data): void
    {
        $this->poligono   = $data['coordinates'] ?? null;
        $this->centro_lat = isset($data['center']['lat']) ? (float) $data['center']['lat'] : null;
        $this->centro_lng = isset($data['center']['lng']) ? (float) $data['center']['lng'] : null;
        $this->area_km2   = isset($data['area_km2']) ? (float) $data['area_km2'] : null;
    }

    /**
     * Consulta OpenStreetMap (Overpass API) para encontrar los barrios
     * que están DENTRO del polígono actual y los carga en el textarea.
     * Se fusiona con los barrios ya escritos, sin duplicados.
     */
    public function autodetectarBarrios(): void
    {
        if (!$this->poligono || count($this->poligono) < 3) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => 'Primero dibuja un polígono en el mapa.',
            ]);
            return;
        }

        $barriosOsm = app(\App\Services\OverpassService::class)
            ->barriosEnPoligono($this->poligono);

        if (empty($barriosOsm)) {
            $this->dispatch('notify', [
                'type'    => 'info',
                'message' => 'OpenStreetMap no encontró barrios en esa zona. Puedes escribirlos a mano.',
            ]);
            return;
        }

        // Barrios ya escritos en el textarea
        $existentes = collect(preg_split('/\r\n|\r|\n|,/', $this->barriosTexto))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->mapWithKeys(fn ($n) => [\App\Models\ZonaCobertura::normalizar($n) => $n]);

        // Merge (priorizando lo ya escrito)
        foreach ($barriosOsm as $b) {
            $key = \App\Models\ZonaCobertura::normalizar($b);
            if (!$existentes->has($key)) {
                $existentes->put($key, $b);
            }
        }

        $this->barriosTexto = $existentes->values()->sort()->join("\n");

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => '✨ ' . count($barriosOsm) . ' barrios detectados desde OpenStreetMap.',
        ]);
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }
    public function updatingFiltroSedeId(): void { $this->resetPage(); }

    public function abrirModalCrear(): void
    {
        $this->resetCampos();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $zona = ZonaCobertura::with('barrios')->findOrFail($id);

        $this->editandoId          = $zona->id;
        $this->sede_id             = $zona->sede_id;
        $this->nombre              = $zona->nombre;
        $this->descripcion         = (string) $zona->descripcion;
        $this->color               = (string) ($zona->color ?? '#d68643');
        $this->costo_envio         = (float) $zona->costo_envio;
        $this->pedido_minimo       = (float) $zona->pedido_minimo;
        $this->tiempo_estimado_min = $zona->tiempo_estimado_min;
        $this->orden               = (int) $zona->orden;
        $this->activa              = (bool) $zona->activa;
        $this->poligono            = $zona->poligono;
        $this->centro_lat          = $zona->centro_lat;
        $this->centro_lng          = $zona->centro_lng;
        $this->area_km2            = $zona->area_km2;

        $this->barriosTexto = $zona->barrios->pluck('nombre')->join("\n");

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

        DB::transaction(function () use ($data) {
            $zona = ZonaCobertura::updateOrCreate(
                ['id' => $this->editandoId],
                $data
            );

            // Procesar barrios desde textarea (uno por línea)
            $nombres = collect(preg_split('/\r\n|\r|\n|,/', $this->barriosTexto))
                ->map(fn ($n) => trim($n))
                ->filter()
                ->unique(fn ($n) => ZonaCobertura::normalizar($n))
                ->values();

            // Limpiar y volver a crear
            $zona->barrios()->delete();

            foreach ($nombres as $nombre) {
                $zona->barrios()->create(['nombre' => $nombre]);
            }
        });

        $this->cerrarModal();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? 'Zona actualizada.' : 'Zona creada.',
        ]);
    }

    public function toggleActiva(int $id): void
    {
        $z = ZonaCobertura::findOrFail($id);
        $z->activa = !$z->activa;
        $z->save();
    }

    public function eliminar(int $id): void
    {
        $zona = ZonaCobertura::withCount('pedidos')->findOrFail($id);

        if ($zona->pedidos_count > 0) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => "No se puede eliminar: tiene {$zona->pedidos_count} pedidos asociados.",
            ]);
            return;
        }

        $zona->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Zona eliminada.',
        ]);
    }

    private function resetCampos(): void
    {
        $this->editandoId          = null;
        $this->sede_id             = null;
        $this->nombre              = '';
        $this->descripcion         = '';
        $this->color               = '#d68643';
        $this->costo_envio         = 0;
        $this->pedido_minimo       = 0;
        $this->tiempo_estimado_min = null;
        $this->orden               = 0;
        $this->activa              = true;
        $this->barriosTexto        = '';
        $this->poligono            = null;
        $this->centro_lat          = null;
        $this->centro_lng          = null;
        $this->area_km2            = null;
        $this->resetValidation();
    }

    public function render()
    {
        $zonas = ZonaCobertura::query()
            ->with(['sede', 'barrios'])
            ->withCount(['barrios', 'domiciliarios', 'pedidos'])
            ->when($this->search, fn ($q) => $q->where('nombre', 'like', "%{$this->search}%"))
            ->when($this->filtroSedeId, fn ($q) => $q->where('sede_id', $this->filtroSedeId))
            ->when($this->filtroEstado === 'activas',   fn ($q) => $q->where('activa', true))
            ->when($this->filtroEstado === 'inactivas', fn ($q) => $q->where('activa', false))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(12);

        // Pedidos con coordenadas para mostrar en el mapa
        $pedidosMapa = $this->vista === 'mapa'
            ? Pedido::query()
                ->whereNotNull('lat')->whereNotNull('lng')
                ->whereIn('estado', [
                    Pedido::ESTADO_NUEVO,
                    Pedido::ESTADO_EN_PREPARACION,
                    Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
                ])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn ($p) => [
                    'id'        => $p->id,
                    'cliente'   => $p->cliente_nombre,
                    'estado'    => $p->estado,
                    'total'     => (float) $p->total,
                    'lat'       => (float) $p->lat,
                    'lng'       => (float) $p->lng,
                    'direccion' => $p->direccion,
                    'barrio'    => $p->barrio,
                ])
            : collect();

        // Para vista mapa: todas las zonas con polígono (sin paginar)
        $zonasMapa = $this->vista === 'mapa'
            ? ZonaCobertura::query()
                ->whereNotNull('poligono')
                ->when($this->filtroSedeId, fn ($q) => $q->where('sede_id', $this->filtroSedeId))
                ->when($this->filtroEstado === 'activas',   fn ($q) => $q->where('activa', true))
                ->when($this->filtroEstado === 'inactivas', fn ($q) => $q->where('activa', false))
                ->withCount('pedidos')
                ->get()
                ->map(fn ($z) => [
                    'id'         => $z->id,
                    'nombre'     => $z->nombre,
                    'color'      => $z->color,
                    'poligono'   => $z->poligono,
                    'centro_lat' => $z->centro_lat,
                    'centro_lng' => $z->centro_lng,
                    'area_km2'   => $z->area_km2,
                    'pedidos'    => $z->pedidos_count,
                    'sede'       => $z->sede?->nombre,
                ])
            : collect();

        $sedes = Sede::orderBy('nombre')->get();

        return view('livewire.zonas.index', compact('zonas', 'zonasMapa', 'pedidosMapa', 'sedes'))
            ->layout('layouts.app');
    }
}
