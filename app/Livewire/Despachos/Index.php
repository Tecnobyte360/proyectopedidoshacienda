<?php

namespace App\Livewire\Despachos;

use App\Models\Domiciliario;
use App\Models\Pedido;
use App\Models\Sede;
use App\Models\ZonaCobertura;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public ?int $sedeId = null;
    public ?int $zonaSeleccionada = null;

    /** Pedidos seleccionados para despachar (id => true) */
    public array $seleccionados = [];

    public bool $modalAbierto = false;
    public ?int $domiciliarioSeleccionado = null;
    public string $notaDespacho = '';

    // Modo masivo por zona: mapa [zona_id => domiciliario_id]
    public array $domiciliariosPorZona = [];
    public bool  $modoMasivoPorZona    = false;

    protected $listeners = [
        'pedidoActualizado' => 'refrescar',
    ];

    public function refrescar(): void
    {
        // Solo dispara render
    }

    public function updatingSedeId(): void
    {
        $this->seleccionados = [];
        $this->zonaSeleccionada = null;
    }

    public function updatingZonaSeleccionada(): void
    {
        $this->seleccionados = [];
    }

    public function toggleZona(?int $zonaId): void
    {
        $this->zonaSeleccionada = $this->zonaSeleccionada === $zonaId ? null : $zonaId;
        $this->seleccionados = [];
    }

    public function seleccionarTodosDeZona(int $zonaId): void
    {
        $pedidos = $this->pedidosEnPreparacion()->where('zona_cobertura_id', $zonaId)->pluck('id');

        foreach ($pedidos as $id) {
            $this->seleccionados[$id] = true;
        }
    }

    public function limpiarSeleccion(): void
    {
        $this->seleccionados = [];
    }

    public function abrirModalDespacho(): void
    {
        $ids = collect($this->seleccionados)->filter()->keys();

        if ($ids->isEmpty()) {
            $this->dispatch('notify', [
                'type'    => 'warning',
                'message' => 'Selecciona al menos un pedido para despachar.',
            ]);
            return;
        }

        $this->modalAbierto = true;
        $this->domiciliarioSeleccionado = null;
        $this->notaDespacho = '';
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->domiciliarioSeleccionado = null;
        $this->notaDespacho = '';
        $this->domiciliariosPorZona = [];
        $this->modoMasivoPorZona    = false;
        $this->resetValidation();
    }

    /**
     * Devuelve los pedidos seleccionados agrupados por zona.
     * Formato: [zona_id => ['zona' => ZonaCobertura|null, 'pedidos' => Collection]]
     */
    public function getSeleccionadosPorZonaProperty()
    {
        $ids = collect($this->seleccionados)->filter()->keys();
        if ($ids->isEmpty()) return collect();

        return Pedido::with('zonaCobertura')
            ->whereIn('id', $ids)
            ->get()
            ->groupBy(fn ($p) => $p->zona_cobertura_id ?? 0)
            ->map(fn ($grupo, $zonaId) => [
                'zona_id' => $zonaId ?: null,
                'zona'    => $grupo->first()->zonaCobertura,
                'pedidos' => $grupo,
                'total'   => $grupo->sum('total'),
            ]);
    }

    /**
     * Despacho MASIVO: asigna un domiciliario DIFERENTE a cada zona
     * en una sola transacción. Ejecuta la misma lógica que confirmarDespacho
     * pero para cada grupo por separado.
     */
    public function confirmarDespachoMasivoPorZona(): void
    {
        $grupos = $this->seleccionadosPorZona;

        if ($grupos->isEmpty()) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No hay pedidos seleccionados.']);
            return;
        }

        // Validar que CADA zona con pedidos tenga un domiciliario asignado
        $sinDomiciliario = [];
        foreach ($grupos as $zonaId => $grupo) {
            $key = $zonaId ?: 0;
            if (empty($this->domiciliariosPorZona[$key])) {
                $sinDomiciliario[] = $grupo['zona']?->nombre ?? 'Sin zona';
            }
        }

        if (!empty($sinDomiciliario)) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => '⚠️ Asigna un domiciliario para: ' . implode(', ', $sinDomiciliario),
            ]);
            return;
        }

        $usuario = Auth::user();
        $totalDespachados = 0;
        $zonasDespachadas = 0;

        DB::transaction(function () use ($grupos, $usuario, &$totalDespachados, &$zonasDespachadas) {
            foreach ($grupos as $zonaId => $grupo) {
                $key = $zonaId ?: 0;
                $domId = $this->domiciliariosPorZona[$key] ?? null;
                if (!$domId) continue;

                $domiciliario = Domiciliario::find($domId);
                if (!$domiciliario) continue;

                $pedidos = $grupo['pedidos']->filter(fn ($p) => $p->estado === Pedido::ESTADO_EN_PREPARACION);

                foreach ($pedidos as $pedido) {
                    if ($pedido->domiciliario_id && $pedido->domiciliario_id !== $domiciliario->id) {
                        $anterior = Domiciliario::find($pedido->domiciliario_id);
                        if ($anterior && $anterior->estado === 'ocupado') {
                            $anterior->estado = 'disponible';
                            $anterior->save();
                        }
                    }

                    $pedido->domiciliario_id = $domiciliario->id;
                    $pedido->fecha_asignacion_domiciliario = now();
                    $pedido->fecha_salida_domiciliario     = now();
                    $pedido->save();

                    $pedido->registrarHistorial(
                        estadoNuevo: $pedido->estado,
                        estadoAnterior: $pedido->estado,
                        titulo: 'Domiciliario asignado',
                        descripcion: "Asignado a {$domiciliario->nombre} (zona: " . ($grupo['zona']?->nombre ?? 'Sin zona') . ")",
                        usuario: $usuario?->name,
                        usuarioId: $usuario?->id
                    );

                    $token = $pedido->generarTokenEntrega();

                    $pedido->cambiarEstado(
                        Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
                        "Tu pedido va en camino con {$domiciliario->nombre}.",
                        'Pedido en camino',
                        $usuario?->name,
                        $usuario?->id
                    );

                    $pedido->notificarTokenEntrega($token);

                    $totalDespachados++;
                }

                if ($pedidos->isNotEmpty()) {
                    $domiciliario->estado = 'ocupado';
                    $domiciliario->save();

                    // Enviar ruta a este domiciliario por WhatsApp
                    $this->enviarRutaADomiciliario(
                        $domiciliario,
                        $pedidos->pluck('id')->all(),
                        $grupo['zona']?->nombre ?? 'Sin zona'
                    );

                    $zonasDespachadas++;
                }
            }
        });

        $this->cerrarModal();
        $this->seleccionados = [];

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "✅ {$totalDespachados} pedido(s) despachados en {$zonasDespachadas} zona(s). Se envió ruta por WhatsApp a cada domiciliario.",
        ]);
    }

    /**
     * Envía ruta específica a UN domiciliario con SUS pedidos.
     */
    private function enviarRutaADomiciliario(Domiciliario $dom, array $pedidoIds, string $nombreZona): void
    {
        if (!$dom->telefonoInternacional()) return;

        $pedidos = Pedido::whereIn('id', $pedidoIds)->get();
        $sede = Sede::where('activa', true)->first();

        $origen = ($sede && $sede->latitud && $sede->longitud)
            ? "{$sede->latitud},{$sede->longitud}"
            : null;

        $paradas = $pedidos->filter(fn ($p) => $p->lat && $p->lng);

        if ($paradas->isEmpty()) return;

        $puntos = $paradas->map(fn ($p) => "{$p->lat},{$p->lng}")->values();
        $destino = $puntos->pop();
        $waypoints = $puntos->implode('|');

        $url = "https://www.google.com/maps/dir/?api=1"
            . ($origen ? "&origin=" . urlencode($origen) : '')
            . "&destination=" . urlencode($destino)
            . "&travelmode=driving"
            . (!empty($waypoints) ? "&waypoints=" . urlencode($waypoints) : '');

        $mensaje = "🛵 *Hola {$dom->nombre}!*\n\n"
            . "Te asigno {$pedidos->count()} pedido(s) en *{$nombreZona}*.\n\n"
            . "🗺️ Ruta en Google Maps:\n{$url}\n\n"
            . "Paradas:\n";

        foreach ($pedidos as $i => $p) {
            $num = $i + 1;
            $mensaje .= "\n{$num}. *{$p->cliente_nombre}* — {$p->direccion}";
            if ($p->telefono_contacto) {
                $mensaje .= "\n   📞 {$p->telefono_contacto}";
            }
        }

        try {
            app(\App\Services\WhatsappSenderService::class)
                ->enviarTexto($dom->telefonoInternacional(), $mensaje, null, false);
        } catch (\Throwable $e) {
            \Log::warning('No se pudo enviar ruta al domiciliario: ' . $e->getMessage());
        }
    }

    public function confirmarDespacho(): void
    {
        $this->validate([
            'domiciliarioSeleccionado' => 'required|exists:domiciliarios,id',
        ], [
            'domiciliarioSeleccionado.required' => 'Selecciona un domiciliario.',
        ]);

        $ids = collect($this->seleccionados)->filter()->keys()->all();

        if (empty($ids)) {
            $this->cerrarModal();
            return;
        }

        $domiciliario = Domiciliario::findOrFail($this->domiciliarioSeleccionado);
        $usuario      = Auth::user();

        $despachados = 0;

        DB::transaction(function () use ($ids, $domiciliario, $usuario, &$despachados) {
            $pedidos = Pedido::whereIn('id', $ids)
                ->where('estado', Pedido::ESTADO_EN_PREPARACION)
                ->get();

            foreach ($pedidos as $pedido) {
                // Liberar domiciliario anterior si lo había
                if ($pedido->domiciliario_id && $pedido->domiciliario_id !== $domiciliario->id) {
                    $anterior = Domiciliario::find($pedido->domiciliario_id);
                    if ($anterior && $anterior->estado === 'ocupado') {
                        $anterior->estado = 'disponible';
                        $anterior->save();
                    }
                }

                $pedido->domiciliario_id = $domiciliario->id;
                $pedido->fecha_asignacion_domiciliario = now();
                $pedido->fecha_salida_domiciliario     = now();
                $pedido->save();

                $pedido->registrarHistorial(
                    estadoNuevo: $pedido->estado,
                    estadoAnterior: $pedido->estado,
                    titulo: 'Domiciliario asignado',
                    descripcion: "Asignado a {$domiciliario->nombre}" . ($this->notaDespacho ? " · {$this->notaDespacho}" : ''),
                    usuario: $usuario?->name,
                    usuarioId: $usuario?->id
                );

                $token = $pedido->generarTokenEntrega();

                $pedido->cambiarEstado(
                    Pedido::ESTADO_REPARTIDOR_EN_CAMINO,
                    "Tu pedido va en camino con {$domiciliario->nombre}.",
                    'Pedido en camino',
                    $usuario?->name,
                    $usuario?->id
                );

                $pedido->notificarTokenEntrega($token);

                $despachados++;
            }

            // Marcar domiciliario como ocupado
            if ($despachados > 0) {
                $domiciliario->estado = 'ocupado';
                $domiciliario->save();
            }
        });

        $this->cerrarModal();
        $this->seleccionados = [];

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "✅ {$despachados} pedido(s) despachados con {$domiciliario->nombre}.",
        ]);
    }

    private function pedidosEnPreparacion()
    {
        return Pedido::query()
            ->with(['detalles', 'zonaCobertura', 'sede', 'domiciliario'])
            ->where('estado', Pedido::ESTADO_EN_PREPARACION)
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId))
            ->orderBy('zona_cobertura_id')
            ->orderBy('fecha_pedido');
    }

    /**
     * Construye la ruta con sede como origen + pedidos seleccionados como paradas.
     * Geocodifica direcciones sin lat/lng al vuelo.
     */
    public function getRutaParaMapaProperty(): array
    {
        $ids = collect($this->seleccionados)->filter()->keys();
        if ($ids->isEmpty()) {
            return ['origen' => null, 'paradas' => [], 'total_km' => 0];
        }

        // Origen: la sede filtrada o la primera activa
        $sede = $this->sedeId
            ? Sede::find($this->sedeId)
            : Sede::where('activa', true)->first();

        // Si la sede tiene dirección pero no coords, intentar geocodificar
        if ($sede && (!$sede->latitud || !$sede->longitud) && !empty($sede->direccion)) {
            try {
                $g = app(\App\Services\GeocodingService::class)
                    ->geocodificar($sede->direccion, null, 'Bello');
                if ($g) {
                    $sede->update(['latitud' => $g['lat'], 'longitud' => $g['lng']]);
                    $sede->refresh();
                }
            } catch (\Throwable $e) {
                // ignorar
            }
        }

        $origen = ($sede && $sede->latitud && $sede->longitud)
            ? [
                'lat'     => (float) $sede->latitud,
                'lng'     => (float) $sede->longitud,
                'nombre'  => $sede->nombre ?: 'Sede',
                'detalle' => $sede->direccion ?: '',
            ]
            : null;

        $pedidos = Pedido::with('domiciliario')
            ->whereIn('id', $ids)
            ->get();

        $paradas = [];
        $geocoder = app(\App\Services\GeocodingService::class);

        foreach ($pedidos as $p) {
            $lat = $p->lat ? (float) $p->lat : null;
            $lng = $p->lng ? (float) $p->lng : null;

            // Geocoding al vuelo si no tiene coordenadas guardadas
            if ((!$lat || !$lng) && !empty($p->direccion)) {
                $g = $geocoder->geocodificar($p->direccion, $p->barrio, 'Bello');
                if ($g) {
                    $lat = $g['lat'];
                    $lng = $g['lng'];
                    // Persistir para siguientes cargas
                    $p->update(['lat' => $lat, 'lng' => $lng]);
                }
            }

            if ($lat && $lng) {
                $paradas[] = [
                    'id'        => $p->id,
                    'lat'       => $lat,
                    'lng'       => $lng,
                    'nombre'    => $p->cliente_nombre,
                    'telefono'  => $p->telefono_contacto ?: $p->telefono,
                    'direccion' => $p->direccion,
                    'barrio'    => $p->barrio,
                    'total'     => (float) $p->total,
                ];
            }
        }

        // Distancia total aproximada (haversine entre puntos sucesivos)
        $totalKm = 0;
        if ($origen && count($paradas) > 0) {
            $prev = $origen;
            foreach ($paradas as $stop) {
                $totalKm += Sede::distanciaKm($prev['lat'], $prev['lng'], $stop['lat'], $stop['lng']);
                $prev = $stop;
            }
        }

        return [
            'origen'   => $origen,
            'paradas'  => $paradas,
            'total_km' => round($totalKm, 2),
        ];
    }

    /**
     * URL de Google Maps con waypoints para compartir con el domiciliario.
     */
    public function getRutaGoogleMapsUrlProperty(): ?string
    {
        $ruta = $this->rutaParaMapa;
        if (!$ruta['origen'] || empty($ruta['paradas'])) {
            return null;
        }

        $origen = $ruta['origen']['lat'] . ',' . $ruta['origen']['lng'];
        $paradas = collect($ruta['paradas'])->pluck('lat', 'lng')->map(fn($lat, $lng) => "{$lat},{$lng}")->values();

        // Google Maps: destino = última parada; waypoints = intermedias
        $todos  = collect($ruta['paradas'])->map(fn($p) => "{$p['lat']},{$p['lng']}")->values();
        $destino = $todos->pop();
        $waypoints = $todos->implode('|');

        $url = "https://www.google.com/maps/dir/?api=1"
            . "&origin=" . urlencode($origen)
            . "&destination=" . urlencode($destino)
            . "&travelmode=driving";

        if (!empty($waypoints)) {
            $url .= "&waypoints=" . urlencode($waypoints);
        }

        return $url;
    }

    /**
     * Envía la ruta por WhatsApp al domiciliario seleccionado.
     */
    public function enviarRutaDomiciliario(): void
    {
        if (!$this->domiciliarioSeleccionado) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Selecciona un domiciliario primero.']);
            return;
        }

        $dom = Domiciliario::find($this->domiciliarioSeleccionado);
        if (!$dom || !$dom->telefonoInternacional()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El domiciliario no tiene teléfono configurado.']);
            return;
        }

        $url = $this->rutaGoogleMapsUrl;
        if (!$url) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No hay pedidos con coordenadas para armar ruta.']);
            return;
        }

        $ruta = $this->rutaParaMapa;
        $cantidad = count($ruta['paradas']);
        $mensaje = "🛵 *Hola {$dom->nombre}!*\n\n"
            . "Te asigno {$cantidad} pedido(s) — ruta estimada {$ruta['total_km']} km.\n\n"
            . "🗺️ Abre la ruta en Google Maps:\n{$url}\n\n"
            . "Pasos:\n";

        foreach ($ruta['paradas'] as $i => $stop) {
            $num = $i + 1;
            $mensaje .= "\n{$num}. *{$stop['nombre']}* — {$stop['direccion']}";
            if ($stop['telefono']) {
                $mensaje .= "\n   📞 {$stop['telefono']}";
            }
        }

        try {
            $wa = app(\App\Services\WhatsappSenderService::class);
            $ok = $wa->enviarTexto($dom->telefonoInternacional(), $mensaje, null, false);

            $this->dispatch('notify', [
                'type'    => $ok ? 'success' : 'error',
                'message' => $ok
                    ? "✅ Ruta enviada a {$dom->nombre} por WhatsApp."
                    : "❌ No se pudo enviar la ruta por WhatsApp.",
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        $pedidos = $this->pedidosEnPreparacion()->get();

        // Agrupar por zona (los sin zona van como "Sin zona asignada")
        $agrupados = $pedidos->groupBy(function ($p) {
            return $p->zona_cobertura_id ?? 0;
        })->map(function ($grupo, $zonaId) {
            $zona = $zonaId ? $grupo->first()->zonaCobertura : null;
            return [
                'zona'    => $zona,
                'pedidos' => $grupo,
                'total'   => $grupo->sum('total'),
            ];
        });

        // Si hay filtro de zona seleccionada
        if ($this->zonaSeleccionada !== null) {
            $agrupados = $agrupados->filter(fn ($g, $k) => $k == $this->zonaSeleccionada);
        }

        // Domiciliarios disponibles (filtrados por zona si aplica)
        $domiciliarios = Domiciliario::with('zonas')
            ->where('activo', true)
            ->orderByRaw("CASE estado WHEN 'disponible' THEN 0 WHEN 'ocupado' THEN 1 ELSE 2 END")
            ->orderBy('nombre')
            ->get();

        // Si hay zona seleccionada en modal, sugerir domiciliarios de esa zona
        if (!empty($this->seleccionados)) {
            $zonasDePedidosSeleccionados = Pedido::whereIn('id', collect($this->seleccionados)->filter()->keys())
                ->pluck('zona_cobertura_id')
                ->filter()
                ->unique();

            if ($zonasDePedidosSeleccionados->count() === 1) {
                $zonaUnica = $zonasDePedidosSeleccionados->first();
                $domiciliarios->each(function ($d) use ($zonaUnica) {
                    $d->cubre_zona = $d->zonas->contains('id', $zonaUnica);
                });
            }
        }

        $sedes = Sede::orderBy('nombre')->get();

        $totalPedidos    = $pedidos->count();
        $totalSelected   = count(array_filter($this->seleccionados));
        $totalSelMonto   = Pedido::whereIn('id', collect($this->seleccionados)->filter()->keys())->sum('total');

        // ── Pedidos en ruta agrupados por domiciliario ──
        // Pedidos asignados a un domiciliario que aún no han sido entregados ni cancelados.
        $enRutaQuery = Pedido::query()
            ->with(['domiciliario', 'zonaCobertura', 'sede', 'detalles'])
            ->whereNotNull('domiciliario_id')
            ->whereNotIn('estado', [
                Pedido::ESTADO_ENTREGADO,
                Pedido::ESTADO_CANCELADO,
            ])
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId))
            ->orderBy('domiciliario_id')
            ->orderBy('fecha_pedido');

        // Paginar por domiciliario (5 por página). Cada domiciliario muestra todos sus pedidos.
        // reorder() limpia los orderBy heredados del clone — necesario para
        // que MySQL en modo only_full_group_by no reclame por `fecha_pedido`
        // al agrupar por `domiciliario_id`.
        $domiciliariosPag = (clone $enRutaQuery)
            ->reorder()
            ->select('domiciliario_id', DB::raw('COUNT(*) as cant'), DB::raw('SUM(total) as monto'))
            ->groupBy('domiciliario_id')
            ->orderBy('domiciliario_id')
            ->paginate(5, ['*'], 'pageDom');

        $idsDom = collect($domiciliariosPag->items())->pluck('domiciliario_id')->all();

        $pedidosEnRuta = $idsDom
            ? (clone $enRutaQuery)->whereIn('domiciliario_id', $idsDom)->get()
            : collect();

        $porDomiciliario = $pedidosEnRuta->groupBy('domiciliario_id')->map(function ($grupo) {
            return [
                'domiciliario' => $grupo->first()->domiciliario,
                'pedidos'      => $grupo,
                'total'        => $grupo->sum('total'),
                'cantidad'     => $grupo->count(),
            ];
        });

        return view('livewire.despachos.index', compact(
            'agrupados',
            'sedes',
            'domiciliarios',
            'totalPedidos',
            'totalSelected',
            'totalSelMonto',
            'porDomiciliario',
            'domiciliariosPag'
        ))->layout('layouts.app');
    }
}
