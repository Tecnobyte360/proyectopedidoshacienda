<div class="px-6 lg:px-10 py-8" wire:poll.30s="refrescar">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Despachos</h2>
            <p class="text-sm text-slate-500">Asigna pedidos a domiciliarios agrupados por zona.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="sedeId"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-[#d68643] focus:ring-[#d68643]">
                <option value="">Todas las sedes</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- BARRA STICKY DE SELECCIÓN --}}
    @if($totalSelected > 0)
        <div class="sticky top-20 z-30 mb-6 rounded-2xl border-2 border-[#d68643] bg-white shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#d68643] text-white text-lg font-bold">
                        {{ $totalSelected }}
                    </div>
                    <div>
                        <div class="font-semibold text-slate-800">
                            {{ $totalSelected }} pedido(s) seleccionado(s)
                        </div>
                        <div class="text-xs text-slate-500">
                            Total: <span class="font-bold text-[#d68643]">${{ number_format($totalSelMonto, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="limpiarSeleccion"
                            class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 transition">
                        Cancelar
                    </button>
                    <button wire:click="abrirModalDespacho"
                            class="rounded-xl bg-[#d68643] px-5 py-2.5 text-sm font-bold text-white shadow hover:bg-[#c97a36] transition">
                        <i class="fa-solid fa-motorcycle mr-2"></i> Despachar selección
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- KPI BAR --}}
    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-fire"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totalPedidos }}</div>
                    <div class="text-xs text-slate-500">Listos para despacho</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $agrupados->count() }}</div>
                    <div class="text-xs text-slate-500">Zonas con pedidos</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $domiciliarios->where('estado','disponible')->count() }}</div>
                    <div class="text-xs text-slate-500">Domiciliarios libres</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-route"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $domiciliarios->where('estado','ocupado')->count() }}</div>
                    <div class="text-xs text-slate-500">En ruta</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 🗺️ MAPA DE RUTA (aparece cuando hay pedidos seleccionados) --}}
    @if($totalSelected > 0)
        @php
            $ruta = $this->rutaParaMapa;
            $urlGmaps = $this->rutaGoogleMapsUrl;
        @endphp

        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm mb-6 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-slate-100 bg-gradient-to-r from-violet-50 to-white">
                <div>
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        🗺️ Ruta de entrega
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ count($ruta['paradas']) }} parada(s) · ~{{ $ruta['total_km'] }} km desde la sede
                        @if(count($ruta['paradas']) < $totalSelected)
                            <span class="text-amber-600 font-semibold">
                                · {{ $totalSelected - count($ruta['paradas']) }} sin coordenadas
                            </span>
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($urlGmaps)
                        <a href="{{ $urlGmaps }}" target="_blank"
                           class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-semibold px-4 py-2 transition">
                            <i class="fa-brands fa-google"></i>
                            Abrir en Google Maps
                        </a>
                    @endif
                    @if($domiciliarioSeleccionado)
                        <button type="button" wire:click="enviarRutaDomiciliario"
                                wire:confirm="¿Enviar la ruta por WhatsApp al domiciliario seleccionado?"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#d68643] hover:bg-[#c97a36] text-white text-xs font-semibold px-4 py-2 transition">
                            <i class="fa-brands fa-whatsapp"></i>
                            Enviar ruta al domiciliario
                        </button>
                    @endif
                </div>
            </div>

            @if(!$ruta['origen'])
                <div class="px-5 py-2 bg-amber-50 border-y border-amber-200 text-xs text-amber-800">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    La sede no tiene coordenadas configuradas — la ruta se muestra solo con los pedidos.
                    Configura lat/lng de la sede para ver la ruta completa desde el origen.
                </div>
            @endif

            <div wire:ignore
                 id="mapa-despacho"
                 data-ruta="{{ json_encode($ruta) }}"
                 style="height: 360px; background:#e5e7eb;"></div>
        </div>

        {{-- Livewire 3: @script re-ejecuta el bloque en cada morph del componente --}}
        @script
        <script>
            (function () {
                function iniciarMapaDespacho() {
                    var el = document.getElementById('mapa-despacho');
                    if (!el) return true;           // no div, nada que hacer
                    if (!window.L) return false;    // Leaflet aún no cargado, reintentar

                    var data;
                    try { data = JSON.parse(el.dataset.ruta || '{}'); }
                    catch (e) { console.error('Ruta JSON inválido', e); return true; }

                    var tienePuntos = data.origen || (data.paradas && data.paradas.length > 0);
                    if (!tienePuntos) return true;

                    if (el._leafletMap) {
                        try { el._leafletMap.remove(); } catch(e) {}
                        el._leafletMap = null;
                    }
                    el.innerHTML = '';

                    var puntos = [];
                    if (data.origen) puntos.push([data.origen.lat, data.origen.lng]);
                    (data.paradas || []).forEach(function (p) { puntos.push([p.lat, p.lng]); });

                    var map = L.map('mapa-despacho');
                    el._leafletMap = map;

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);

                    if (data.origen) {
                        var sedeIcon = L.divIcon({
                            className: 'sede-marker',
                            html: '<div style="background:#10b981;color:#fff;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-weight:bold;box-shadow:0 2px 8px rgba(0,0,0,0.3);border:3px solid #fff">🏪</div>',
                            iconSize: [36, 36], iconAnchor: [18, 18],
                        });
                        L.marker([data.origen.lat, data.origen.lng], { icon: sedeIcon })
                            .addTo(map)
                            .bindPopup('<b>' + (data.origen.nombre || 'Sede') + '</b><br>' + (data.origen.detalle || ''));
                    }

                    (data.paradas || []).forEach(function (p, i) {
                        var num = i + 1;
                        var pinIcon = L.divIcon({
                            className: 'pedido-marker',
                            html: '<div style="background:#d68643;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-weight:bold;box-shadow:0 2px 8px rgba(0,0,0,0.3);border:3px solid #fff">' + num + '</div>',
                            iconSize: [32, 32], iconAnchor: [16, 16],
                        });
                        L.marker([p.lat, p.lng], { icon: pinIcon })
                            .addTo(map)
                            .bindPopup(
                                '<b>#' + num + ' ' + (p.nombre || '') + '</b><br>' +
                                (p.direccion || '') + '<br>' +
                                (p.barrio ? ('📍 ' + p.barrio + '<br>') : '') +
                                (p.telefono ? ('📞 ' + p.telefono + '<br>') : '') +
                                '💵 $' + Number(p.total || 0).toLocaleString('es-CO')
                            );
                    });

                    if (puntos.length > 1) {
                        L.polyline(puntos, { color: '#d68643', weight: 4, opacity: 0.75, dashArray: '8, 8' }).addTo(map);
                    }

                    if (puntos.length === 1) {
                        map.setView(puntos[0], 15);
                    } else if (puntos.length > 1) {
                        map.fitBounds(puntos, { padding: [40, 40] });
                    }

                    setTimeout(function () { try { map.invalidateSize(); } catch (e) {} }, 250);

                    return true;
                }

                // Polling con reintentos (Leaflet tiene defer, puede tardar en cargar)
                var intentos = 0;
                var intervalo = setInterval(function () {
                    intentos++;
                    var ok = iniciarMapaDespacho();
                    if (ok || intentos > 40) clearInterval(intervalo);
                }, 200);
            })();
        </script>
        @endscript
    @endif

    {{-- ZONAS --}}
    @forelse($agrupados as $zonaId => $grupo)
        @php
            $zona = $grupo['zona'];
            $color = $zona?->color ?? '#94a3b8';
            $nombreZona = $zona?->nombre ?? 'Sin zona asignada';
        @endphp

        <div class="mb-6 rounded-2xl bg-white shadow overflow-hidden">

            {{-- Header de la zona --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"
                 style="background: linear-gradient(135deg, {{ $color }}20, transparent);">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl text-white shadow"
                         style="background-color: {{ $color }}">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800">{{ $nombreZona }}</h3>
                        <div class="text-xs text-slate-500">
                            {{ $grupo['pedidos']->count() }} pedido(s) ·
                            <span class="font-semibold text-slate-700">${{ number_format($grupo['total'], 0, ',', '.') }}</span>
                            @if($zona && $zona->tiempo_estimado_min)
                                · <i class="fa-solid fa-clock"></i> ~{{ $zona->tiempo_estimado_min }} min
                            @endif
                        </div>
                    </div>
                </div>

                @if($zonaId)
                    <button wire:click="seleccionarTodosDeZona({{ $zonaId }})"
                            class="rounded-lg bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow hover:bg-slate-50 transition">
                        <i class="fa-solid fa-check-double mr-1"></i> Seleccionar todos
                    </button>
                @endif
            </div>

            {{-- Pedidos de esta zona --}}
            <div class="divide-y divide-slate-100">
                @foreach($grupo['pedidos'] as $p)
                    @php $isSelected = !empty($seleccionados[$p->id]); @endphp

                    <label class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50 cursor-pointer transition
                                  {{ $isSelected ? 'bg-amber-50/50' : '' }}">

                        <input type="checkbox"
                               wire:model.live="seleccionados.{{ $p->id }}"
                               class="mt-1 h-5 w-5 rounded border-slate-300 text-[#d68643] focus:ring-[#d68643]">

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-800">#{{ $p->id }}</span>
                                        <span class="text-sm font-semibold text-slate-700 truncate">
                                            {{ $p->cliente_nombre }}
                                        </span>
                                        @if($p->canal === 'whatsapp')
                                            <i class="fa-brands fa-whatsapp text-green-500 text-sm"></i>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1">
                                        @if($p->direccion)
                                            <span><i class="fa-solid fa-location-dot text-[#d68643] mr-1"></i>{{ $p->direccion }}</span>
                                        @endif
                                        @if($p->barrio)
                                            <span><i class="fa-solid fa-map-pin mr-1"></i>{{ $p->barrio }}</span>
                                        @endif
                                        @if($p->telefono_whatsapp || $p->telefono)
                                            <span><i class="fa-solid fa-phone mr-1"></i>{{ $p->telefono_whatsapp ?? $p->telefono }}</span>
                                        @endif
                                    </div>

                                    {{-- Productos --}}
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($p->detalles->take(3) as $d)
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700">
                                                {{ rtrim(rtrim(number_format($d->cantidad, 2, ',', '.'), '0'), ',') }}
                                                {{ $d->unidad }}
                                                · {{ $d->producto }}
                                            </span>
                                        @endforeach
                                        @if($p->detalles->count() > 3)
                                            <span class="inline-flex items-center rounded-md bg-slate-200 px-2 py-0.5 text-[11px] text-slate-700">
                                                +{{ $p->detalles->count() - 3 }} más
                                            </span>
                                        @endif
                                    </div>

                                    @if($p->domiciliario_id)
                                        <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-medium text-blue-700">
                                            <i class="fa-solid fa-motorcycle"></i>
                                            Reasignar de: {{ $p->domiciliario?->nombre }}
                                        </div>
                                    @endif
                                </div>

                                <div class="text-right shrink-0">
                                    <div class="text-lg font-extrabold text-[#d68643]">
                                        ${{ number_format($p->total, 0, ',', '.') }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 uppercase">
                                        {{ $p->fecha_pedido?->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-2xl bg-white p-16 text-center shadow">
            <i class="fa-solid fa-mug-hot text-5xl text-slate-300 mb-4 block"></i>
            <h3 class="font-bold text-slate-700 mb-1">Todo despachado</h3>
            <p class="text-sm text-slate-500">No hay pedidos en preparación esperando.</p>
        </div>
    @endforelse

    {{-- MODAL DE DESPACHO --}}
    @if($modalAbierto)
        @php
            $gruposSel = $this->seleccionadosPorZona;
            $multiZona = $gruposSel->count() > 1;
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">
                            {{ $multiZona ? '🗺️ Despacho masivo por zonas' : 'Asignar domiciliario' }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $totalSelected }} pedido(s) en {{ $gruposSel->count() }} zona(s)
                            · ${{ number_format($totalSelMonto, 0, ',', '.') }}
                        </p>
                    </div>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                @if($multiZona)
                    {{-- ═══════ MODO MASIVO POR ZONA ═══════ --}}
                    <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="rounded-xl bg-violet-50 border border-violet-200 p-3 text-sm text-violet-800">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            Seleccionaste pedidos de <b>{{ $gruposSel->count() }} zonas</b>. Asigna un domiciliario
                            a cada zona — el sistema despacha todo y envía la ruta por WhatsApp a cada uno.
                        </div>

                        @foreach($gruposSel as $zonaId => $grupo)
                            @php
                                $keyGrupo = $zonaId ?: 0;
                                $nombreZona = $grupo['zona']?->nombre ?? 'Sin zona';
                                $color = $grupo['zona']?->color ?? '#94a3b8';
                                $cantPedidos = $grupo['pedidos']->count();
                                // Domiciliarios que cubren esta zona (si zona tiene cobertura definida)
                                $domsDeZona = $grupo['zona']
                                    ? $domiciliarios->filter(fn($d) => $d->zonas->contains('id', $zonaId))
                                    : collect();
                            @endphp

                            <div class="rounded-2xl border-2 border-slate-200 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 bg-slate-50 border-b border-slate-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-3 h-10 rounded-full" style="background: {{ $color }}"></div>
                                        <div>
                                            <div class="font-bold text-slate-800">📍 {{ $nombreZona }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ $cantPedidos }} pedido(s) · ${{ number_format($grupo['total'], 0, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-4 py-3 space-y-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                                        Domiciliario para esta zona:
                                    </label>
                                    <select wire:model="domiciliariosPorZona.{{ $keyGrupo }}"
                                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                                        <option value="">— Selecciona un domiciliario —</option>
                                        @if($domsDeZona->isNotEmpty())
                                            <optgroup label="✓ Cubren esta zona">
                                                @foreach($domsDeZona as $d)
                                                    <option value="{{ $d->id }}">
                                                        {{ $d->nombre }} ({{ ucfirst($d->estado) }}){{ $d->vehiculo ? ' · '.$d->vehiculo : '' }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Otros disponibles">
                                                @foreach($domiciliarios->whereNotIn('id', $domsDeZona->pluck('id')) as $d)
                                                    <option value="{{ $d->id }}">
                                                        {{ $d->nombre }} ({{ ucfirst($d->estado) }}){{ $d->vehiculo ? ' · '.$d->vehiculo : '' }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @else
                                            @foreach($domiciliarios as $d)
                                                <option value="{{ $d->id }}">
                                                    {{ $d->nombre }} ({{ ucfirst($d->estado) }}){{ $d->vehiculo ? ' · '.$d->vehiculo : '' }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>

                                    <div class="text-xs text-slate-500 space-y-0.5 pt-2">
                                        @foreach($grupo['pedidos'] as $p)
                                            <div>• #{{ $p->id }} {{ $p->cliente_nombre }} — {{ $p->direccion }}</div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-3 p-4 border-t border-slate-100 bg-slate-50">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">
                            Cancelar
                        </button>
                        <button type="button" wire:click="confirmarDespachoMasivoPorZona"
                                wire:confirm="¿Confirmar despacho masivo? Se asignarán los domiciliarios y se enviará la ruta por WhatsApp a cada uno."
                                class="rounded-xl bg-violet-600 hover:bg-violet-700 px-6 py-2.5 text-sm font-bold text-white shadow">
                            <i class="fa-solid fa-paper-plane mr-2"></i>
                            Despachar {{ $totalSelected }} pedido(s) en {{ $gruposSel->count() }} zonas
                        </button>
                    </div>
                @else
                    {{-- ═══════ MODO CLÁSICO (1 SOLA ZONA) ═══════ --}}
                    <form wire:submit.prevent="confirmarDespacho" class="p-6 space-y-4">

                        @if($domiciliarios->where('estado', 'disponible')->count() === 0)
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                No hay domiciliarios disponibles. Puedes asignar uno ocupado, pero ya tiene otros pedidos en ruta.
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Selecciona el domiciliario *</label>
                            <div class="space-y-2 max-h-80 overflow-y-auto">
                                @foreach($domiciliarios as $dom)
                                    @php
                                        $statusColor = match($dom->estado) {
                                            'disponible' => 'bg-green-100 text-green-700 border-green-200',
                                            'ocupado'    => 'bg-amber-100 text-amber-700 border-amber-200',
                                            default      => 'bg-slate-100 text-slate-600 border-slate-200',
                                        };
                                        $cubreZona = property_exists($dom, 'cubre_zona') ? $dom->cubre_zona : null;
                                    @endphp

                                    <label class="flex items-center gap-3 rounded-xl border-2 p-3 cursor-pointer transition hover:bg-slate-50
                                                  {{ $domiciliarioSeleccionado === $dom->id ? 'border-[#d68643] bg-amber-50' : 'border-slate-200' }}">
                                        <input type="radio" wire:model="domiciliarioSeleccionado" value="{{ $dom->id }}"
                                               class="text-[#d68643] focus:ring-[#d68643]">

                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white text-sm font-bold">
                                            {{ strtoupper(substr($dom->nombre, 0, 1)) }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-slate-800 truncate">{{ $dom->nombre }}</span>
                                                @if($cubreZona === true)
                                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">
                                                        ✓ Cubre zona
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{ $dom->vehiculo ?? 'Sin vehículo' }}
                                                @if($dom->placa) · {{ $dom->placa }} @endif
                                            </div>
                                        </div>

                                        <span class="rounded-full border px-3 py-1 text-xs font-medium capitalize {{ $statusColor }}">
                                            {{ $dom->estado }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('domiciliarioSeleccionado')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Nota (opcional)
                                <span class="text-xs text-slate-400 font-normal">— se guarda en el historial</span>
                            </label>
                            <input type="text" wire:model="notaDespacho" placeholder="Ej: Salida juntos, ruta optimizada"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" wire:click="cerrarModal"
                                    class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="rounded-xl bg-[#d68643] px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-[#c97a36]">
                                <i class="fa-solid fa-paper-plane mr-2"></i>
                                Despachar {{ $totalSelected }} pedido(s)
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
