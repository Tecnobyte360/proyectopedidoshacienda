@if(!empty($googleMapsApiKey))
    @once
    @push('scripts')
    <script>
        if (!window._gmapsLoading && !window.google?.maps) {
            window._gmapsLoading = true;
            var s = document.createElement('script');
            s.src = 'https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=geometry&language=es&region=CO';
            s.async = true; s.defer = true;
            document.head.appendChild(s);
        }
    </script>
    @endpush
    @endonce
@endif

<div class="px-6 lg:px-10 py-8" wire:poll.30s="refrescar">

    @once
    @push('scripts')
    <script>
        // 🎨 Confirmación con SweetAlert2 para asignar/reasignar pedidos
        window.confirmarReasignar = function(selectEl, pedidoId, modo) {
            const domiId = selectEl.value;
            if (!domiId) return;

            // Texto del domiciliario seleccionado (incluye estado/vehículo)
            const opcionTexto = selectEl.options[selectEl.selectedIndex].text;

            // Resetear el select inmediatamente
            selectEl.value = '';

            const esReasignar = modo === 'reasignar';
            const titulo = esReasignar ? '¿Reasignar este pedido?' : '¿Asignar este pedido?';
            const colorPrimario = esReasignar ? '#10b981' : '#f43f5e'; // brand verde / rose
            const icono = esReasignar ? 'question' : 'info';

            if (typeof Swal === 'undefined') {
                if (confirm(titulo + '\n\nPedido #' + pedidoId + ' → ' + opcionTexto)) {
                    Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'))
                        ?.call('reasignarPedido', pedidoId, domiId);
                }
                return;
            }

            Swal.fire({
                title: titulo,
                html: `
                    <div style="font-size:14px;color:#475569;text-align:left">
                        <div style="background:#f1f5f9;padding:10px 12px;border-radius:8px;margin-bottom:10px">
                            <div style="font-size:11px;text-transform:uppercase;color:#94a3b8;letter-spacing:0.05em;font-weight:700">Pedido</div>
                            <div style="font-weight:800;color:#0f172a;font-size:16px">#${String(pedidoId).padStart(3,'0')}</div>
                        </div>
                        <div style="background:${colorPrimario}15;padding:10px 12px;border-radius:8px;border-left:4px solid ${colorPrimario}">
                            <div style="font-size:11px;text-transform:uppercase;color:${colorPrimario};letter-spacing:0.05em;font-weight:700">${esReasignar ? 'Nuevo domiciliario' : 'Domiciliario'}</div>
                            <div style="font-weight:700;color:#0f172a">${opcionTexto}</div>
                        </div>
                    </div>
                `,
                icon: icono,
                showCancelButton: true,
                confirmButtonColor: colorPrimario,
                cancelButtonColor: '#94a3b8',
                confirmButtonText: esReasignar
                    ? '<i class="fa-solid fa-rotate"></i> Sí, reasignar'
                    : '<i class="fa-solid fa-check"></i> Sí, asignar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl px-5 py-2.5 font-bold',
                    cancelButton: 'rounded-xl px-5 py-2.5 font-bold',
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    // Buscar el componente Livewire de Despachos
                    const root = selectEl.closest('[wire\\:id]');
                    if (root && window.Livewire) {
                        const cmp = Livewire.find(root.getAttribute('wire:id'));
                        if (cmp) cmp.call('reasignarPedido', pedidoId, domiId);
                    } else {
                        // Fallback
                        Livewire.dispatch('reasignar-pedido', { pedidoId: pedidoId, domiId: domiId });
                    }
                }
            });
        };
    </script>
    @endpush
    @endonce

    {{-- ════════════════════════════════════════════════════════════════
         🛵 PANEL PERSONAL DEL DOMICILIARIO (cuando es solo rol 'domiciliario')
         Se muestra ARRIBA del view admin estándar. El view admin se filtra
         automáticamente a sus pedidos.
         ════════════════════════════════════════════════════════════════ --}}
    @if($esDomiciliarioPuro && $domiActual)
        @php
            // Calcular stats extras para los KPIs estilo /pedidos
            $totalEnCaminoHoy = collect($pedidosOrdenados)->where('estado', 'repartidor_en_camino')->count();
            $totalEnPrepHoy   = collect($pedidosOrdenados)->where('estado', 'en_preparacion')->count();
            $totalMontoHoy    = collect($pedidosOrdenados)->sum(fn ($p) => (float) $p->total);
        @endphp

        {{-- ╔══════════════ HEADER: avatar + saludo + estado en vivo ══════════════╗ --}}
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white text-xl font-extrabold shadow-sm">
                    {{ mb_substr($domiActual->nombre, 0, 1) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            En tiempo real
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800">Mis despachos · {{ explode(' ', $domiActual->nombre)[0] }}</h2>
                    <p class="text-xs text-slate-500">
                        <i class="fa-solid fa-motorcycle"></i> {{ $domiActual->vehiculo ?: 'Vehículo' }}
                        <span class="text-slate-300 mx-1">·</span>
                        {{ $domiActual->placa ?: '—' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($rutaOptimaUrl && $pedidosOrdenados->count() > 0)
                    <a href="{{ $rutaOptimaUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2.5 text-sm font-bold shadow-sm transition">
                        <i class="fa-solid fa-route"></i>
                        <span class="hidden sm:inline">Ruta óptima</span>
                        <span class="sm:hidden">Ruta</span>
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-white/25 text-[10px] font-extrabold">
                            {{ $pedidosOrdenados->count() }}
                        </span>
                    </a>
                @endif
                <button wire:click="refrescar" wire:loading.attr="disabled"
                        title="Refrescar"
                        class="inline-flex items-center justify-center h-10 w-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition">
                    <i class="fa-solid fa-arrows-rotate" wire:loading.class="fa-spin"></i>
                </button>
            </div>
        </div>

        {{-- ╔══════════════ KPI BAR estilo /pedidos ══════════════╗ --}}
        <div class="mb-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            {{-- KPI 1: En preparación --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Por recoger</div>
                    <i class="fa-solid fa-utensils text-amber-500"></i>
                </div>
                <div class="text-2xl font-black text-slate-800">{{ $totalEnPrepHoy }}</div>
                <div class="mt-1.5 h-1 rounded-full bg-amber-100"><div class="h-1 rounded-full bg-amber-400" style="width: {{ $statsDomi['pendientes'] > 0 ? ($totalEnPrepHoy / $statsDomi['pendientes']) * 100 : 0 }}%"></div></div>
            </div>

            {{-- KPI 2: En camino --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">En camino</div>
                    <i class="fa-solid fa-motorcycle text-violet-500"></i>
                </div>
                <div class="text-2xl font-black text-slate-800">{{ $totalEnCaminoHoy }}</div>
                <div class="mt-1.5 h-1 rounded-full bg-violet-100"><div class="h-1 rounded-full bg-violet-500" style="width: {{ $statsDomi['pendientes'] > 0 ? ($totalEnCaminoHoy / $statsDomi['pendientes']) * 100 : 0 }}%"></div></div>
            </div>

            {{-- KPI 3: Entregados hoy --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Entregados</div>
                    <i class="fa-solid fa-circle-check text-emerald-500"></i>
                </div>
                <div class="text-2xl font-black text-emerald-700">{{ $statsDomi['entregados'] }}</div>
                <div class="text-[10px] text-slate-400 mt-1">Hoy</div>
            </div>

            {{-- KPI 4: Total hoy --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total hoy</div>
                    <i class="fa-solid fa-list text-blue-500"></i>
                </div>
                <div class="text-2xl font-black text-slate-800">{{ $statsDomi['total_hoy'] }}</div>
                <div class="text-[10px] text-slate-400 mt-1">Asignados</div>
            </div>

            {{-- KPI 5: Monto en ruta --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm col-span-2 sm:col-span-1">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total $</div>
                    <i class="fa-solid fa-coins text-yellow-500"></i>
                </div>
                <div class="text-2xl font-black text-slate-800">${{ number_format($totalMontoHoy, 0, ',', '.') }}</div>
                <div class="text-[10px] text-slate-400 mt-1">En ruta</div>
            </div>
        </div>

        {{-- ╔══════════════ TABS de filtro estilo /pedidos ══════════════╗ --}}
        @php
            $filtroDomi = $filtroEstadoDomi ?? 'todos';
        @endphp
        <div class="mb-5 flex flex-wrap gap-2" x-data="{ tab: '{{ $filtroDomi }}' }">
            <button @click="tab = 'todos'" wire:click="$set('filtroEstadoDomi', 'todos')"
                    :class="tab === 'todos' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-bold transition">
                <i class="fa-solid fa-list"></i> Todos
                <span :class="tab === 'todos' ? 'bg-white/20' : 'bg-slate-100'" class="ml-1 px-1.5 rounded-full text-[10px]">{{ $pedidosOrdenados->count() }}</span>
            </button>
            <button @click="tab = 'por_recoger'" wire:click="$set('filtroEstadoDomi', 'por_recoger')"
                    :class="tab === 'por_recoger' ? 'bg-amber-500 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-amber-50'"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-bold transition">
                <i class="fa-solid fa-utensils"></i> Por recoger
                <span :class="tab === 'por_recoger' ? 'bg-white/20' : 'bg-amber-100 text-amber-700'" class="ml-1 px-1.5 rounded-full text-[10px]">{{ $totalEnPrepHoy }}</span>
            </button>
            <button @click="tab = 'en_camino'" wire:click="$set('filtroEstadoDomi', 'en_camino')"
                    :class="tab === 'en_camino' ? 'bg-violet-600 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-violet-50'"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-bold transition">
                <i class="fa-solid fa-motorcycle"></i> En camino
                <span :class="tab === 'en_camino' ? 'bg-white/20' : 'bg-violet-100 text-violet-700'" class="ml-1 px-1.5 rounded-full text-[10px]">{{ $totalEnCaminoHoy }}</span>
            </button>
            <button @click="tab = 'entregados'" wire:click="$set('filtroEstadoDomi', 'entregados')"
                    :class="tab === 'entregados' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-emerald-50'"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-bold transition">
                <i class="fa-solid fa-circle-check"></i> Entregados
                <span :class="tab === 'entregados' ? 'bg-white/20' : 'bg-emerald-100 text-emerald-700'" class="ml-1 px-1.5 rounded-full text-[10px]">{{ $statsDomi['entregados'] }}</span>
            </button>
        </div>

        <div class="mb-6">

            {{-- 🗺️ MAPA de ruta OCULTO para el domiciliario (usa Google Maps/Waze
                 directo desde cada pedido). Se mantiene el script de navegación. --}}
            @if(false && !empty($rutaDomi['paradas']))
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-map-location-dot text-brand"></i>
                            <h3 class="text-sm font-bold text-slate-700">Mapa de tu ruta</h3>
                        </div>
                        <span class="text-xs text-slate-500" id="ruta-info-domi-osrm">
                            {{ count($rutaDomi['paradas']) }} parada(s)
                        </span>
                    </div>
                    <div wire:ignore
                         id="mapa-domiciliario"
                         data-ruta="{{ json_encode($rutaDomi) }}"
                         style="height: 380px; background:#e5e7eb;"></div>
                </div>
            @endif

            @if(!empty($rutaDomi['paradas']))
                @script
                <script>
                    (function () {
                        window.navegarHasta = window.navegarHasta || function (lat, lng) {
                            window.open('https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng + '&travelmode=driving', '_blank');
                        };
                        window.navegarWaze = window.navegarWaze || function (lat, lng) {
                            window.open('https://www.waze.com/ul?ll=' + lat + ',' + lng + '&navigate=yes', '_blank');
                        };

                        function buildPopupDomi(p, num) {
                            var html = '<div style="min-width:200px;font-family:sans-serif">';
                            html += '<b>#' + num + ' ' + (p.nombre || 'Cliente') + '</b><br>';
                            if (p.direccion) html += '📍 ' + p.direccion + '<br>';
                            if (p.barrio) html += '🏘️ ' + p.barrio + '<br>';
                            if (p.telefono) html += '📞 ' + p.telefono + '<br>';
                            html += '💵 $' + Number(p.total || 0).toLocaleString('es-CO');
                            html += '<div style="margin-top:10px;display:flex;gap:4px;flex-wrap:wrap">';
                            html += '<button onclick="navegarHasta(' + p.lat + ',' + p.lng + ')" '
                                 + 'style="background:#4285f4;color:#fff;border:none;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer">'
                                 + '🧭 Google Maps</button>';
                            html += '<button onclick="navegarWaze(' + p.lat + ',' + p.lng + ')" '
                                 + 'style="background:#33ccff;color:#fff;border:none;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer">'
                                 + '🚗 Waze</button>';
                            if (p.telefono) {
                                html += '<a href="https://wa.me/' + String(p.telefono).replace(/\D/g,'') + '" target="_blank" '
                                     + 'style="background:#25d366;color:#fff;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:600;text-decoration:none">'
                                     + '💬 WA</a>';
                            }
                            html += '</div></div>';
                            return html;
                        }

                        function iniciarMapaDomi() {
                            var el = document.getElementById('mapa-domiciliario');
                            if (!el) return true;
                            if (!window.google || !window.google.maps) return false;

                            var data;
                            try { data = JSON.parse(el.dataset.ruta || '{}'); }
                            catch (e) { return true; }

                            if (!data.paradas || data.paradas.length === 0) return true;

                            if (el._gmap) {
                                if (el._gmapMarkers) el._gmapMarkers.forEach(function(m){ m.setMap(null); });
                                if (el._gmapPath) el._gmapPath.setMap(null);
                            }
                            el.innerHTML = '';
                            el._gmapMarkers = [];

                            var puntos = [];
                            if (data.origen) puntos.push({lat: parseFloat(data.origen.lat), lng: parseFloat(data.origen.lng)});
                            data.paradas.forEach(function(p){ puntos.push({lat: parseFloat(p.lat), lng: parseFloat(p.lng)}); });

                            var map = new google.maps.Map(el, {
                                zoom: 13,
                                center: puntos[0] || {lat: 6.34, lng: -75.56},
                                mapTypeControl: true,
                                streetViewControl: true,
                                fullscreenControl: true,
                            });
                            el._gmap = map;

                            if (data.origen) {
                                var sedeMarker = new google.maps.Marker({
                                    position: {lat: parseFloat(data.origen.lat), lng: parseFloat(data.origen.lng)},
                                    map: map,
                                    label: { text: '🏪', fontSize: '20px' },
                                    icon: { path: google.maps.SymbolPath.CIRCLE, scale: 18, fillColor: '#10b981', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 3 },
                                    title: data.origen.nombre || 'Origen'
                                });
                                el._gmapMarkers.push(sedeMarker);
                            }

                            data.paradas.forEach(function(p, i) {
                                var num = i + 1;
                                var marker = new google.maps.Marker({
                                    position: {lat: parseFloat(p.lat), lng: parseFloat(p.lng)},
                                    map: map,
                                    label: { text: String(num), color: '#fff', fontWeight: 'bold', fontSize: '14px' },
                                    icon: { path: google.maps.SymbolPath.CIRCLE, scale: 16, fillColor: '#d68643', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 3 },
                                    title: 'Pedido ' + num + ' · ' + (p.nombre || '')
                                });
                                var info = new google.maps.InfoWindow({ content: buildPopupDomi(p, num) });
                                marker.addListener('click', function(){ info.open(map, marker); });
                                el._gmapMarkers.push(marker);
                            });

                            if (puntos.length === 1) {
                                map.setCenter(puntos[0]); map.setZoom(15);
                            } else {
                                var bounds = new google.maps.LatLngBounds();
                                puntos.forEach(function(p){ bounds.extend(p); });
                                map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
                            }

                            if (puntos.length > 1) {
                                var ds = new google.maps.DirectionsService();
                                var dr = new google.maps.DirectionsRenderer({
                                    map: map, suppressMarkers: true,
                                    polylineOptions: { strokeColor: '#d68643', strokeWeight: 5, strokeOpacity: 0.85 }
                                });
                                el._gmapPath = dr;
                                var waypoints = puntos.slice(1, -1).map(function(p){ return {location: p, stopover: true}; });
                                ds.route({
                                    origin: puntos[0],
                                    destination: puntos[puntos.length - 1],
                                    waypoints: waypoints,
                                    travelMode: google.maps.TravelMode.DRIVING
                                }, function(result, status) {
                                    if (status === 'OK' && result) {
                                        dr.setDirections(result);
                                        var totalDur = 0, totalDist = 0;
                                        result.routes[0].legs.forEach(function(l){ totalDur += l.duration.value; totalDist += l.distance.value; });
                                        var info = document.getElementById('ruta-info-domi-osrm');
                                        if (info) info.textContent = (totalDist/1000).toFixed(1) + ' km · ~' + Math.round(totalDur/60) + ' min';
                                    }
                                });
                            }
                            return true;
                        }

                        var intentos = 0;
                        var intv = setInterval(function () {
                            intentos++;
                            if (iniciarMapaDomi() || intentos > 60) clearInterval(intv);
                        }, 250);
                    })();
                </script>
                @endscript
            @endif

            {{-- Lista de pedidos del domiciliario con código y botones --}}
            @php
                // Aplicar filtro por tab
                $pedidosFiltrados = match ($filtroEstadoDomi ?? 'todos') {
                    'por_recoger' => $pedidosOrdenados->where('estado', 'en_preparacion'),
                    'en_camino'   => $pedidosOrdenados->where('estado', 'repartidor_en_camino'),
                    'entregados'  => \App\Models\Pedido::where('domiciliario_id', $domiActual->id)
                                        ->whereDate('fecha_entregado', now()->toDateString())
                                        ->with(['sede:id,nombre', 'detalles'])
                                        ->orderByDesc('fecha_entregado')
                                        ->get(),
                    default       => $pedidosOrdenados,
                };
            @endphp

            @if($pedidosFiltrados->count() > 0)
                <div class="mt-4">
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 font-bold mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-list-ol"></i>
                        @if(($filtroEstadoDomi ?? 'todos') === 'entregados')
                            Entregados hoy ({{ $pedidosFiltrados->count() }})
                        @elseif(($filtroEstadoDomi ?? 'todos') === 'por_recoger')
                            Por recoger en sede ({{ $pedidosFiltrados->count() }})
                        @elseif(($filtroEstadoDomi ?? 'todos') === 'en_camino')
                            En camino al cliente ({{ $pedidosFiltrados->count() }})
                        @else
                            Mis pedidos en orden ({{ $pedidosFiltrados->count() }})
                        @endif
                    </h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach($pedidosFiltrados as $i => $p)
                            @php
                                $estadoLabel = match($p->estado) {
                                    'repartidor_en_camino' => ['🛵', 'En camino', 'violet'],
                                    'entregado'            => ['✅', 'Entregado', 'emerald'],
                                    'en_preparacion'       => ['👨‍🍳', 'Por recoger', 'amber'],
                                    default                => ['📦', ucfirst($p->estado), 'slate'],
                                };
                                $detalles = $p->detalles ?? collect();
                            @endphp
                            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col">
                                {{-- Header card --}}
                                <div class="flex items-start justify-between gap-3 p-4 border-b border-slate-100 bg-slate-50/50">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="flex-shrink-0 h-11 w-11 rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white flex items-center justify-center font-extrabold shadow-sm">
                                            {{ $i + 1 }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-extrabold text-slate-800">#{{ $p->id }}</span>
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold bg-{{ $estadoLabel[2] }}-100 text-{{ $estadoLabel[2] }}-800">
                                                    {{ $estadoLabel[0] }} {{ $estadoLabel[1] }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-500 truncate">{{ $p->cliente_nombre ?: 'Cliente' }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="font-extrabold text-slate-800">${{ number_format((float) $p->total, 0, ',', '.') }}</div>
                                        @if($detalles->count() > 0)
                                            <div class="text-[10px] text-slate-400 mt-0.5">
                                                <i class="fa-solid fa-cart-shopping"></i> {{ $detalles->count() }} {{ $detalles->count() === 1 ? 'item' : 'items' }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="p-4 flex-1 flex flex-col gap-3">
                                    {{-- Dirección + teléfono --}}
                                    <div class="space-y-1">
                                        <div class="text-sm text-slate-700 flex items-start gap-2">
                                            <i class="fa-solid fa-location-dot text-rose-500 mt-0.5 flex-shrink-0"></i>
                                            <span class="leading-tight">{{ $p->direccion ?: 'Sin dirección' }}@if($p->barrio), {{ $p->barrio }}@endif</span>
                                        </div>
                                        @if($p->telefono_contacto ?: $p->telefono_whatsapp)
                                            <a href="tel:{{ $p->telefono_contacto ?: $p->telefono_whatsapp }}" class="text-xs text-emerald-700 hover:underline inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-phone"></i> {{ $p->telefono_contacto ?: $p->telefono_whatsapp }}
                                            </a>
                                        @endif
                                    </div>

                                    {{-- 🛒 Productos --}}
                                    @if($detalles->count() > 0)
                                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-2.5">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                                                <i class="fa-solid fa-cart-shopping text-[9px]"></i> Productos
                                            </div>
                                            <ul class="space-y-1">
                                                @foreach($detalles as $d)
                                                    @php
                                                        $cant = (float) ($d->cantidad ?? 1);
                                                        $cantFmt = fmod($cant, 1) == 0 ? (int) $cant : rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.');
                                                        $unit = $d->unidad ?: '';
                                                    @endphp
                                                    <li class="flex items-start gap-2 text-[11px] leading-tight">
                                                        <span class="inline-flex items-center justify-center min-w-[28px] h-[18px] rounded bg-slate-900 text-white text-[10px] font-bold shrink-0">
                                                            {{ $cantFmt }}{{ $unit ? ' ' . (in_array(strtolower($unit), ['kg','kl']) ? 'kg' : (in_array(strtolower($unit), ['lb','libra','libras']) ? 'lb' : '')) : '' }}
                                                        </span>
                                                        <span class="font-medium text-slate-700 leading-tight">
                                                            {{ \Illuminate\Support\Str::limit($d->producto ?? 'Item', 30) }}
                                                            @if(!empty($d->corte_nombre))
                                                                <span class="text-[9px] text-violet-600">· {{ $d->corte_nombre }}</span>
                                                            @endif
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                {{-- 🔑 INDICADOR (no mostramos el código — se valida en modal) --}}
                                @if($p->token_entrega && $p->estado === 'repartidor_en_camino')
                                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-2 mb-3 flex items-center gap-2 text-xs">
                                        <i class="fa-solid fa-key text-slate-500"></i>
                                        <span class="text-slate-600">El cliente recibió un <strong>código de 4 dígitos</strong>. Te lo pedirá al entregar.</span>
                                    </div>
                                @endif

                                {{-- 💰 ESTADO DE PAGO --}}
                                @if($p->estado_pago !== 'aprobado')
                                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-3 flex items-center gap-3">
                                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-lg"></i>
                                        <div class="flex-1 text-xs">
                                            <div class="font-bold text-rose-800"><i class="fa-solid fa-triangle-exclamation"></i> Pedido SIN pagar</div>
                                            <div class="text-rose-700">
                                                Antes de entregar, marca el pago (efectivo). Sin pago no podrás cerrar el pedido.
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-2 mb-3 flex items-center gap-2 text-xs">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                        <span class="font-bold text-emerald-800">Pago confirmado</span>
                                        @if($p->metodo_pago)
                                            <span class="text-emerald-700">({{ $p->metodo_pago }})</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- BOTONES DE ACCIÓN --}}
                                <div class="grid grid-cols-2 gap-2">
                                    @if($p->lat && $p->lng)
                                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $p->lat }},{{ $p->lng }}&travelmode=driving"
                                           target="_blank" rel="noopener"
                                           class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 py-2.5 text-sm font-bold text-slate-700">
                                            <i class="fa-brands fa-google"></i> Ir
                                        </a>
                                    @elseif($p->direccion)
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($p->direccion ?? '') . ' ' . ($p->barrio ?? '')) }}"
                                           target="_blank" rel="noopener"
                                           class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 py-2.5 text-sm font-bold text-slate-700">
                                            <i class="fa-brands fa-google"></i> Ir
                                        </a>
                                    @else
                                        <span class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 py-2.5 text-sm font-bold text-slate-400 cursor-not-allowed">
                                            <i class="fa-solid fa-location-crosshairs"></i> Sin GPS
                                        </span>
                                    @endif

                                    @if($p->estado === 'repartidor_en_camino')
                                        @if($p->estado_pago !== 'aprobado')
                                            {{-- Pendiente de pago → abrir modal pagar --}}
                                            <button type="button" wire:click="abrirModalPago({{ $p->id }})"
                                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white py-2.5 text-sm font-bold">
                                                <i class="fa-solid fa-money-bill-wave"></i> Marcar pagado
                                            </button>
                                        @else
                                            {{-- Pagado → abrir modal entrega --}}
                                            <button type="button" wire:click="abrirModalEntrega({{ $p->id }})"
                                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 text-sm font-bold">
                                                <i class="fa-solid fa-circle-check"></i> Entregado
                                            </button>
                                        @endif
                                    @else
                                        {{-- En preparación → botón salir --}}
                                        <button type="button" wire:click="marcarEnCamino({{ $p->id }})"
                                                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white py-2.5 text-sm font-bold">
                                            <i class="fa-solid fa-motorcycle"></i> Salir
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- Estado vacío estilizado --}}
                <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    @php
                        $msgsEmpty = [
                            'por_recoger' => ['📦', 'No tienes pedidos por recoger', 'Cuando un pedido esté listo en la sede, te aparecerá aquí.'],
                            'en_camino'   => ['🛵', 'No tienes pedidos en camino', 'Cuando salgas con un pedido, te aparecerá aquí hasta que lo entregues.'],
                            'entregados'  => ['🎉', 'Aún no has entregado pedidos hoy', '¡Vamos por el primero! Tus entregas aparecerán aquí.'],
                            'todos'       => ['💤', 'No tienes pedidos asignados', 'Espera a que el sistema te asigne pedidos o pasa más tarde.'],
                        ];
                        $emp = $msgsEmpty[$filtroEstadoDomi ?? 'todos'] ?? $msgsEmpty['todos'];
                    @endphp
                    <div class="text-5xl mb-3">{{ $emp[0] }}</div>
                    <h3 class="text-base font-bold text-slate-700">{{ $emp[1] }}</h3>
                    <p class="text-sm text-slate-500 mt-1">{{ $emp[2] }}</p>
                </div>
            @endif
        </div>
    @elseif($esDomiciliarioPuro)
        <div class="mb-6 rounded-2xl border-2 border-amber-200 bg-amber-50 p-5 max-w-2xl">
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-triangle-exclamation text-2xl text-amber-500"></i>
                <div>
                    <h3 class="font-bold text-slate-800">Tu cuenta no está vinculada</h3>
                    <p class="text-sm text-slate-600 mt-1">
                        Tu usuario tiene rol <strong>domiciliario</strong> pero no está vinculado a un perfil.
                        Pide a un administrador que te vincule en <code>/domiciliarios</code>.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- 🔒 PANEL ADMIN: Solo visible para administradores (no domiciliarios puros) --}}
    @if(!$esDomiciliarioPuro)
    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Despachos</h2>
            <p class="text-sm text-slate-500">Asigna pedidos a domiciliarios agrupados por zona.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="sedeId"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-brand">
                <option value="">Todas las sedes</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- BARRA STICKY DE SELECCIÓN --}}
    @if($totalSelected > 0)
        <div class="sticky top-20 z-30 mb-6 rounded-2xl border-2 border-brand bg-white shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand text-white text-lg font-bold">
                        {{ $totalSelected }}
                    </div>
                    <div>
                        <div class="font-semibold text-slate-800">
                            {{ $totalSelected }} pedido(s) seleccionado(s)
                        </div>
                        <div class="text-xs text-slate-500">
                            Total: <span class="font-bold text-brand">${{ number_format($totalSelMonto, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="limpiarSeleccion"
                            class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 transition">
                        Cancelar
                    </button>
                    <button wire:click="abrirModalDespacho"
                            class="rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white shadow hover:bg-brand-dark transition">
                        <i class="fa-solid fa-motorcycle mr-2"></i> Despachar selección
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- KPI Bar removido a petición del usuario --}}

    {{-- 🗺️ MAPA EN VIVO DE DOMICILIARIOS (inline directo, sin Livewire anidado) --}}
    @php
        $domisActivos = $domiciliarios->filter(fn($d) => $d->lat_actual && $d->lng_actual)->values();

        // 🛡️ Umbral: ubicación con >5 min se considera "desconectado"
        $umbralMinutosVivo = 5;

        $domisData = $domisActivos->map(function($d) use ($umbralMinutosVivo) {
            $ubicAt = $d->ubicacion_actualizada_at
                ? \Carbon\Carbon::parse($d->ubicacion_actualizada_at)
                : null;
            $minutosDesde = $ubicAt ? abs((int) $ubicAt->diffInMinutes(now())) : 99999;
            $desconectado = $minutosDesde > $umbralMinutosVivo;

            return [
                'id'           => $d->id,
                'nombre'       => $d->nombre,
                'lat'          => (float) $d->lat_actual,
                'lng'          => (float) $d->lng_actual,
                'estado'       => $d->estado,
                'vehiculo'     => $d->vehiculo,
                'placa'        => $d->placa,
                'telefono'     => $d->telefono,
                'token'        => $d->token_acceso,
                'desconectado' => $desconectado,
                'minutos_desde' => $minutosDesde,
                'ubicacion_human' => $ubicAt
                    ? $ubicAt->locale('es')->diffForHumans()
                    : 'Sin ubicación',
            ];
        })->values()->toArray();

        // KPIs reales: en vivo vs desconectados
        $domisEnVivo = collect($domisData)->where('desconectado', false)->count();
        $domisDesconectados = collect($domisData)->where('desconectado', true)->count();
        $tenantActual = app(\App\Services\TenantManager::class)->current();
        $mapCenterLat = (float) ($tenantActual?->google_maps_centro_lat ?: 6.3414);
        $mapCenterLng = (float) ($tenantActual?->google_maps_centro_lng ?: -75.5538);
        $mapZoom      = (int) ($tenantActual?->google_maps_zoom ?: 12);

        // 🗺️ Zonas de cobertura (polígonos) para dibujar sobre el mapa
        try {
            $zonasCobertura = \App\Models\ZonaCobertura::where('activa', true)
                ->whereNotNull('poligono')
                ->get(['id', 'nombre', 'color', 'poligono'])
                ->map(function ($z) {
                    $coords = is_string($z->poligono) ? json_decode($z->poligono, true) : $z->poligono;
                    return [
                        'id'     => $z->id,
                        'nombre' => $z->nombre,
                        'color'  => $z->color ?: '#f59e0b',
                        'coords' => is_array($coords) ? $coords : [],
                    ];
                })
                ->filter(fn ($z) => count($z['coords']) > 2)
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            $zonasCobertura = [];
        }
    @endphp

    <div x-data="{ abierto: (localStorage.getItem('despachos_mapa_abierto') ?? '1') === '1' }"
         x-init="$watch('abierto', v => { localStorage.setItem('despachos_mapa_abierto', v ? '1' : '0'); if(v && window.initDespachosMapa) setTimeout(window.initDespachosMapa, 100); })"
         class="mb-6 rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <button type="button" @click="abierto = !abierto"
                class="w-full flex items-center justify-between px-5 py-4 hover:bg-slate-50 transition">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 text-brand">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <div class="text-left">
                    <h3 class="font-extrabold text-slate-800 flex items-center gap-2">
                        Mapa de domiciliarios
                        @if($domisEnVivo > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                {{ $domisEnVivo }} en vivo
                            </span>
                        @endif
                        @if($domisDesconectados > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-200 text-slate-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                  title="Última ubicación hace más de 5 min — el domiciliario debe abrir su portal para actualizar GPS">
                                <i class="fa-solid fa-circle-exclamation text-[9px]"></i>
                                {{ $domisDesconectados }} desconectado{{ $domisDesconectados > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </h3>
                    <p class="text-xs text-slate-500">
                        @if($domisEnVivo === 0 && $domisDesconectados > 0)
                            <i class="fa-solid fa-triangle-exclamation"></i> Ningún domiciliario tiene su portal abierto. Mostrando últimas ubicaciones conocidas.
                        @elseif($domisActivos->isEmpty())
                            Los repartidores enviarán su ubicación cuando abran el portal en su celular
                        @else
                            {{ $domisActivos->count() }} repartidor(es) con ubicación · se actualiza en tiempo real
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-brand/10 text-brand px-3 py-1 text-xs font-bold">
                    <i class="fa-solid fa-motorcycle"></i>
                    {{ $domiciliarios->where('estado','ocupado')->count() }} en ruta
                </span>
                <i class="fa-solid fa-chevron-down text-slate-400 transition-transform"
                   :class="abierto ? 'rotate-180' : ''"></i>
            </div>
        </button>

        <div x-show="abierto" x-cloak x-transition class="border-t border-slate-100">
            @if(empty($googleMapsApiKey))
                <div class="p-10 text-center">
                    <i class="fa-solid fa-key text-3xl text-amber-400 mb-3"></i>
                    <p class="text-sm font-bold text-slate-700">Google Maps no está configurado</p>
                    <p class="text-xs text-slate-500">Configura la API key del tenant para ver el mapa en vivo.</p>
                </div>
            @elseif($domisActivos->isEmpty())
                <div class="p-10 text-center">
                    <i class="fa-solid fa-location-crosshairs text-3xl text-slate-300 mb-3"></i>
                    <p class="text-sm font-bold text-slate-700">Aún no hay ubicaciones</p>
                    <p class="text-xs text-slate-500">Los repartidores enviarán su ubicación cuando abran el portal en su celular.</p>
                </div>
            @else
                {{-- Leyenda + Stats arriba del mapa --}}
                <div class="px-5 py-3 bg-gradient-to-r from-slate-50 to-white border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-4 text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-3 h-3 rounded-full" style="background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,0.2)"></span>
                            <span class="font-semibold text-slate-700">Disponible</span>
                            <span class="text-slate-500">({{ $domiciliarios->where('estado','disponible')->count() }})</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-3 h-3 rounded-full" style="background:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,0.2)"></span>
                            <span class="font-semibold text-slate-700">Ocupado</span>
                            <span class="text-slate-500">({{ $domiciliarios->where('estado','ocupado')->count() }})</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-3 h-3 rounded-full" style="background:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.2)"></span>
                            <span class="font-semibold text-slate-700">En ruta</span>
                            <span class="text-slate-500">({{ $domiciliarios->where('estado','en_ruta')->count() }})</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Selector de tipo de mapa --}}
                        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5"
                             x-data="{ tipo: localStorage.getItem('despachos_mapa_tipo') || 'hybrid' }">
                            <button type="button"
                                    @click="tipo='roadmap'; localStorage.setItem('despachos_mapa_tipo','roadmap'); document.getElementById('mapaDespachosLive')?._gmap?.setMapTypeId('roadmap')"
                                    :class="tipo==='roadmap' ? 'bg-brand text-white shadow' : 'text-slate-600 hover:bg-slate-50'"
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-bold transition"
                                    title="Mapa de calles">
                                <i class="fa-solid fa-map"></i>
                                Mapa
                            </button>
                            <button type="button"
                                    @click="tipo='hybrid'; localStorage.setItem('despachos_mapa_tipo','hybrid'); document.getElementById('mapaDespachosLive')?._gmap?.setMapTypeId('hybrid')"
                                    :class="tipo==='hybrid' ? 'bg-brand text-white shadow' : 'text-slate-600 hover:bg-slate-50'"
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-bold transition"
                                    title="Satélite con calles">
                                <i class="fa-solid fa-layer-group"></i>
                                Híbrido
                            </button>
                            <button type="button"
                                    @click="tipo='satellite'; localStorage.setItem('despachos_mapa_tipo','satellite'); document.getElementById('mapaDespachosLive')?._gmap?.setMapTypeId('satellite')"
                                    :class="tipo==='satellite' ? 'bg-brand text-white shadow' : 'text-slate-600 hover:bg-slate-50'"
                                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-bold transition"
                                    title="Solo satélite">
                                <i class="fa-solid fa-satellite"></i>
                                Satélite
                            </button>
                        </div>

                        <button type="button"
                                onclick="if(document.getElementById('mapaDespachosLive')?._gmap){ const m=document.getElementById('mapaDespachosLive')._gmap; const b=new google.maps.LatLngBounds(); Object.values(document.getElementById('mapaDespachosLive')._markers).forEach(e=>b.extend(e.marker.getPosition())); m.fitBounds(b,{top:60,right:40,bottom:60,left:40}); }"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-expand"></i>
                            Centrar
                        </button>
                        <a href="/domiciliarios/mapa" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-brand text-white px-3 py-1.5 text-xs font-bold hover:bg-brand-dark transition">
                            <i class="fa-solid fa-up-right-from-square"></i>
                            Vista completa
                        </a>
                    </div>
                </div>

                <div wire:ignore>
                    <div id="mapaDespachosLive" style="width: 100%; height: 520px; background: #f1f5f9;"></div>
                </div>

                @push('scripts')
                <script>
                window._despachosDomis = @json($domisData);
                window._despachosZonas = @json($zonasCobertura);
                window._despachosMapCfg = {
                    centerLat: {{ $mapCenterLat }},
                    centerLng: {{ $mapCenterLng }},
                    zoom: {{ $mapZoom }},
                };

                // 🎨 Colores por estado — gris si desconectado (>5min sin GPS)
                window._dpColorEstado = function(estado, desconectado) {
                    if (desconectado) return '#94a3b8'; // gris
                    switch(estado) {
                        case 'disponible': return '#10b981'; // verde
                        case 'en_ruta':    return '#3b82f6'; // azul
                        case 'ocupado':    return '#f59e0b'; // ámbar (antes naranja chillón)
                        case 'descanso':   return '#64748b';
                        default:           return '#94a3b8';
                    }
                };
                window._dpGradEstado = function(estado, desconectado) {
                    if (desconectado) return ['#cbd5e1', '#94a3b8'];
                    switch(estado) {
                        case 'disponible': return ['#34d399', '#10b981'];
                        case 'en_ruta':    return ['#60a5fa', '#3b82f6'];
                        case 'ocupado':    return ['#fbbf24', '#f59e0b'];
                        case 'descanso':   return ['#94a3b8', '#64748b'];
                        default:           return ['#cbd5e1', '#94a3b8'];
                    }
                };

                // 🛵 Marcador estilo Waze: insignia circular limpia con ring blanco
                window._dpSvgMoto = function(estado, desconectado) {
                    const [c1, c2] = window._dpGradEstado(estado, desconectado);
                    const icono = desconectado ? '📵' : '🛵';
                    const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="50" height="60" viewBox="0 0 50 60">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="${c1}"/><stop offset="1" stop-color="${c2}"/>
    </linearGradient>
    <filter id="s" x="-60%" y="-60%" width="220%" height="220%">
      <feDropShadow dx="0" dy="2" stdDeviation="2.2" flood-opacity="0.32"/>
    </filter>
  </defs>
  <path d="M25 57 L18.5 43 H31.5 Z" fill="#ffffff" filter="url(#s)"/>
  <circle cx="25" cy="23" r="20" fill="#ffffff" filter="url(#s)"/>
  <circle cx="25" cy="23" r="16" fill="url(#g)"/>
  <text x="25" y="31" font-size="20" text-anchor="middle">${icono}</text>
</svg>`;
                    return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
                };

                // ⚡ Pulso discreto (solo EN VIVO)
                window._dpSvgPing = function(estado, desconectado) {
                    if (desconectado) return '';
                    const color = window._dpColorEstado(estado, false);
                    const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 72 72">
  <circle cx="36" cy="36" r="12" fill="${color}" opacity="0.28">
    <animate attributeName="r" from="12" to="30" dur="2s" repeatCount="indefinite"/>
    <animate attributeName="opacity" from="0.35" to="0" dur="2s" repeatCount="indefinite"/>
  </circle>
</svg>`;
                    return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
                };

                window.initDespachosMapa = function() {
                    const el = document.getElementById('mapaDespachosLive');
                    if (!el || !window.google || !window.google.maps) return;
                    if (el._mapaInit) return;
                    el._mapaInit = true;

                    const cfg = window._despachosMapCfg;
                    // Tipo de mapa guardado en localStorage (default: roadmap = estilo verde de marca)
                    const tipoGuardado = localStorage.getItem('despachos_mapa_tipo') || 'roadmap';
                    const map = new google.maps.Map(el, {
                        center: { lat: cfg.centerLat, lng: cfg.centerLng },
                        zoom: cfg.zoom,
                        mapTypeId: tipoGuardado,
                        mapTypeControl: true,
                        mapTypeControlOptions: {
                            style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
                            position: google.maps.ControlPosition.TOP_RIGHT,
                            mapTypeIds: ['roadmap', 'hybrid', 'satellite', 'terrain'],
                        },
                        streetViewControl: false,
                        fullscreenControl: true,
                        zoomControl: true,
                        gestureHandling: 'greedy',
                        // Sin styles custom — usa los colores naturales de Google Maps
                        styles: [],
                    });

                    el._gmap = map;
                    el._markers = {};
                    el._zonas = [];
                    const bounds = new google.maps.LatLngBounds();

                    // 🗺️ Dibujar zonas de cobertura como polígonos
                    (window._despachosZonas || []).forEach(z => {
                        if (!z.coords || z.coords.length < 3) return;
                        const path = z.coords.map(pt => ({
                            lat: parseFloat(pt.lat ?? pt[0]),
                            lng: parseFloat(pt.lng ?? pt[1])
                        })).filter(p => !isNaN(p.lat) && !isNaN(p.lng));
                        if (path.length < 3) return;

                        const poly = new google.maps.Polygon({
                            paths: path,
                            strokeColor: z.color,
                            strokeOpacity: 0.9,
                            strokeWeight: 2,
                            fillColor: z.color,
                            fillOpacity: 0.18,
                            map: map,
                            clickable: true,
                            zIndex: 1,
                        });

                        // Label flotante con nombre de la zona
                        const polyInfo = new google.maps.InfoWindow();
                        poly.addListener('click', (e) => {
                            polyInfo.setContent(`
                                <div style="font-family:system-ui;padding:4px;min-width:140px">
                                    <div style="font-weight:800;color:${z.color};font-size:13px">📍 ${z.nombre}</div>
                                    <div style="font-size:11px;color:#64748b;margin-top:2px">Zona de cobertura</div>
                                </div>
                            `);
                            polyInfo.setPosition(e.latLng);
                            polyInfo.open({ map });
                        });

                        el._zonas.push(poly);
                        path.forEach(p => bounds.extend(p));
                    });

                    window._despachosDomis.forEach(d => {
                        const colorEstado = window._dpColorEstado(d.estado, d.desconectado);

                        // Pin de moto estilo Waze (gris si desconectado)
                        const iconMoto = {
                            url: window._dpSvgMoto(d.estado, d.desconectado),
                            scaledSize: new google.maps.Size(50, 60),
                            anchor: new google.maps.Point(25, 57),
                        };

                        // Ping (debajo) — solo si está EN VIVO
                        let pulse = null;
                        if (!d.desconectado) {
                            const iconPing = {
                                url: window._dpSvgPing(d.estado, false),
                                scaledSize: new google.maps.Size(72, 72),
                                anchor: new google.maps.Point(36, 50),
                            };
                            pulse = new google.maps.Marker({
                                position: { lat: d.lat, lng: d.lng },
                                map, icon: iconPing,
                                clickable: false, zIndex: 1,
                            });
                        }

                        // Pin (arriba)
                        const marker = new google.maps.Marker({
                            position: { lat: d.lat, lng: d.lng },
                            map,
                            title: d.nombre + ' · ' + d.ubicacion_human,
                            icon: iconMoto,
                            zIndex: d.desconectado ? 50 : 100,
                            optimized: false,
                        });

                        // 🪟 Tooltip mejorado con edad + botón reactivar
                        const estadoConexion = d.desconectado
                            ? `<div style="margin-top:8px;padding:6px 10px;background:#f1f5f9;border-radius:8px;border-left:3px solid #94a3b8">
                                  <div style="font-size:11px;color:#475569;font-weight:600">📵 Desconectado</div>
                                  <div style="font-size:10px;color:#64748b;margin-top:2px">Última ubicación: ${d.ubicacion_human}</div>
                               </div>`
                            : `<div style="margin-top:8px;padding:6px 10px;background:#ecfdf5;border-radius:8px;border-left:3px solid #10b981">
                                  <div style="font-size:11px;color:#047857;font-weight:600">🟢 En vivo</div>
                                  <div style="font-size:10px;color:#059669;margin-top:2px">Actualizado: ${d.ubicacion_human}</div>
                               </div>`;

                        const linkPortal = d.token ? `${window.location.origin}/d/${d.token}` : '';
                        const msgWhatsApp = d.token && d.telefono
                            ? `https://wa.me/${d.telefono}?text=${encodeURIComponent('Hola ' + d.nombre + ', abre tu portal de domiciliario para que veamos tu ubicación: ' + linkPortal + ' (déjalo abierto durante tu turno)')}`
                            : '';

                        const labelDiv = new google.maps.InfoWindow({
                            content: `
                                <div style="font-family:system-ui,sans-serif;padding:6px 4px;min-width:240px;max-width:280px">
                                    <div style="font-weight:800;font-size:15px;color:#0f172a;line-height:1.2">${d.nombre}</div>
                                    <div style="font-size:12px;color:#64748b;margin-top:2px">
                                        🏍️ ${d.vehiculo || 'Vehículo'}${d.placa ? ' · '+d.placa : ''}
                                    </div>
                                    <div style="margin-top:8px">
                                        <span style="background:${colorEstado};color:white;padding:3px 12px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.06em">
                                            ${(d.estado || '').replace('_',' ')}
                                        </span>
                                    </div>
                                    ${estadoConexion}
                                    <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px">
                                        ${d.telefono ? `<a href="tel:${d.telefono}" style="font-size:13px;color:#10b981;font-weight:700;text-decoration:none">📞 ${d.telefono}</a>` : ''}
                                        ${d.desconectado && msgWhatsApp
                                            ? `<a href="${msgWhatsApp}" target="_blank" style="display:inline-block;background:#25D366;color:white;text-align:center;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none">📤 Enviar link de reactivación por WhatsApp</a>`
                                            : ''}
                                    </div>
                                </div>
                            `,
                        });
                        marker.addListener('click', () => labelDiv.open({ anchor: marker, map }));

                        el._markers[d.id] = { marker, pulse, info: labelDiv };
                        bounds.extend(marker.getPosition());
                    });

                    if (window._despachosDomis.length > 1) {
                        map.fitBounds(bounds, { top: 60, right: 40, bottom: 60, left: 40 });
                        const listener = google.maps.event.addListener(map, 'idle', () => {
                            if (map.getZoom() > 15) map.setZoom(15);
                            google.maps.event.removeListener(listener);
                        });
                    } else if (window._despachosDomis.length === 1) {
                        map.setCenter(window._despachosDomis[0]);
                        map.setZoom(15);
                    }
                };

                // Inicializar cuando Google Maps cargue
                (function poll() {
                    if (window.google && window.google.maps) {
                        window.initDespachosMapa();
                    } else {
                        setTimeout(poll, 300);
                    }
                })();

                // Re-inicializar si Livewire reemplazó el contenedor
                document.addEventListener('livewire:initialized', () => {
                    Livewire.hook('morph.updated', () => {
                        const el = document.getElementById('mapaDespachosLive');
                        if (el && !el._mapaInit && window.google && window.google.maps) {
                            window.initDespachosMapa();
                        }
                    });
                });

                // Reverb: escuchar cambios de ubicación
                @if($tenantId = app(\App\Services\TenantManager::class)->id())
                document.addEventListener('livewire:initialized', () => {
                    if (window.Echo) {
                        window.Echo.channel('domiciliarios.tenant.{{ $tenantId }}')
                            .listen('.domiciliario.ubicacion', (data) => {
                                const el = document.getElementById('mapaDespachosLive');
                                if (!el || !el._markers) return;
                                const m = el._markers[data.id];
                                if (m && data.lat && data.lng) {
                                    m.marker.setPosition({ lat: parseFloat(data.lat), lng: parseFloat(data.lng) });
                                }
                            });
                    }
                });
                @endif
                </script>
                @endpush
            @endif
        </div>
    </div>

    {{-- 🗺️ MAPA DE RUTA (oculto a petición del usuario) --}}
    @if(false && $totalSelected > 0)
        @php
            $ruta = $this->rutaParaMapa;
            $urlGmaps = $this->rutaGoogleMapsUrl;
        @endphp

        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm mb-6 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-slate-100 bg-gradient-to-r from-violet-50 to-white">
                <div>
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-map text-violet-500"></i> Ruta de entrega
                    </h3>
                    <p class="text-xs text-slate-500">
                        <span>{{ count($ruta['paradas']) }} parada(s) · ~{{ $ruta['total_km'] }} km línea recta</span>
                        <span id="ruta-info-osrm" class="text-brand font-semibold"></span>
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
                           class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold px-4 py-2 transition shadow">
                            <i class="fa-brands fa-google"></i>
                            <i class="fa-solid fa-compass"></i>
                            Iniciar navegación en Google Maps
                        </a>
                    @endif
                    @if($domiciliarioSeleccionado)
                        <button type="button" wire:click="enviarRutaDomiciliario"
                                wire:confirm="¿Enviar la ruta por WhatsApp al domiciliario seleccionado?"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white text-xs font-bold px-4 py-2 transition">
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
                // Abre Google Maps para ir de la ubicación ACTUAL del domiciliario a un punto específico
                window.navegarHasta = function (lat, lng) {
                    var url = 'https://www.google.com/maps/dir/?api=1'
                            + '&destination=' + lat + ',' + lng
                            + '&travelmode=driving';
                    window.open(url, '_blank');
                };

                // Abre Waze con navegación directa (apps móviles lo abren de una)
                window.navegarWaze = function (lat, lng) {
                    window.open('https://www.waze.com/ul?ll=' + lat + ',' + lng + '&navigate=yes', '_blank');
                };

                function buildPopupPedido(p, num) {
                    var html = '<div style="min-width:200px">';
                    html += '<b>#' + num + ' ' + (p.nombre || '') + '</b><br>';
                    if (p.direccion) html += '📍 ' + p.direccion + '<br>';
                    if (p.barrio) html += '🏘️ ' + p.barrio + '<br>';
                    if (p.telefono) html += '📞 ' + p.telefono + '<br>';
                    html += '💵 $' + Number(p.total || 0).toLocaleString('es-CO');
                    html += '<div style="margin-top:10px;display:flex;gap:4px;flex-wrap:wrap">';
                    html += '<button onclick="navegarHasta(' + p.lat + ',' + p.lng + ')" '
                         + 'style="background:#4285f4;color:#fff;border:none;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer">'
                         + '🧭 Google Maps</button>';
                    html += '<button onclick="navegarWaze(' + p.lat + ',' + p.lng + ')" '
                         + 'style="background:#33ccff;color:#fff;border:none;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:600;cursor:pointer">'
                         + '🚗 Waze</button>';
                    if (p.telefono) {
                        html += '<a href="https://wa.me/' + p.telefono.replace(/\D/g,'') + '" target="_blank" '
                             + 'style="background:#25d366;color:#fff;border-radius:6px;padding:6px 10px;font-size:11px;font-weight:600;text-decoration:none">'
                             + '💬 WA</a>';
                    }
                    html += '</div></div>';
                    return html;
                }

                // Llama OSRM para obtener la ruta real por calles
                function fetchRutaOSRM(puntos) {
                    if (puntos.length < 2) return Promise.resolve(null);
                    var coords = puntos.map(function (p) { return p[1] + ',' + p[0]; }).join(';');
                    var url = 'https://router.project-osrm.org/route/v1/driving/' + coords
                            + '?overview=full&geometries=geojson';
                    return fetch(url)
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .catch(function () { return null; });
                }

                function iniciarMapaDespacho() {
                    var el = document.getElementById('mapa-despacho');
                    if (!el) return true;
                    if (!window.L) return false;

                    var data;
                    try { data = JSON.parse(el.dataset.ruta || '{}'); }
                    catch (e) { console.error('Ruta JSON inválido', e); return true; }

                    var tienePuntos = data.origen || (data.paradas && data.paradas.length > 0);
                    if (!tienePuntos) return true;

                    if (!window.google || !window.google.maps) {
                        return false; // esperar a que Google Maps cargue
                    }

                    // Limpiar mapa anterior si existe
                    if (el._gmap) {
                        el._gmap = null;
                        if (el._gmapMarkers) el._gmapMarkers.forEach(function(m){ m.setMap(null); });
                        if (el._gmapPath) el._gmapPath.setMap(null);
                    }
                    el.innerHTML = '';
                    el._gmapMarkers = [];

                    var puntos = [];
                    if (data.origen) puntos.push({lat: parseFloat(data.origen.lat), lng: parseFloat(data.origen.lng)});
                    (data.paradas || []).forEach(function (p) { puntos.push({lat: parseFloat(p.lat), lng: parseFloat(p.lng)}); });

                    // Inicializar Google Map
                    var map = new google.maps.Map(el, {
                        zoom: 13,
                        center: puntos[0] || {lat: 6.34, lng: -75.56},
                        mapTypeControl: true,
                        streetViewControl: true,
                        fullscreenControl: true,
                        styles: [
                            { featureType: 'poi.business', elementType: 'labels', stylers: [{ visibility: 'off' }] }
                        ]
                    });
                    el._gmap = map;

                    // Marker de sede (origen)
                    if (data.origen) {
                        var sedeMarker = new google.maps.Marker({
                            position: {lat: parseFloat(data.origen.lat), lng: parseFloat(data.origen.lng)},
                            map: map,
                            label: { text: '🏪', fontSize: '20px' },
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 18, fillColor: '#10b981', fillOpacity: 1,
                                strokeColor: '#fff', strokeWeight: 3
                            },
                            title: (data.origen.nombre || 'Sede')
                        });
                        var sedeInfo = new google.maps.InfoWindow({
                            content: '<b>' + (data.origen.nombre || 'Sede') + '</b><br>' + (data.origen.detalle || '')
                        });
                        sedeMarker.addListener('click', function(){ sedeInfo.open(map, sedeMarker); });
                        el._gmapMarkers.push(sedeMarker);
                    }

                    // Markers de pedidos (paradas)
                    (data.paradas || []).forEach(function (p, i) {
                        var num = i + 1;
                        var marker = new google.maps.Marker({
                            position: {lat: parseFloat(p.lat), lng: parseFloat(p.lng)},
                            map: map,
                            label: { text: String(num), color: '#fff', fontWeight: 'bold', fontSize: '14px' },
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 16, fillColor: '#d68643', fillOpacity: 1,
                                strokeColor: '#fff', strokeWeight: 3
                            },
                            title: 'Pedido ' + num
                        });
                        var info = new google.maps.InfoWindow({ content: buildPopupPedido(p, num) });
                        marker.addListener('click', function(){ info.open(map, marker); });
                        el._gmapMarkers.push(marker);
                    });

                    // Ajustar viewport a todos los puntos
                    if (puntos.length === 1) {
                        map.setCenter(puntos[0]);
                        map.setZoom(15);
                    } else if (puntos.length > 1) {
                        var bounds = new google.maps.LatLngBounds();
                        puntos.forEach(function(p){ bounds.extend(p); });
                        map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
                    }

                    // Ruta entre puntos usando DirectionsService (calles reales)
                    if (puntos.length > 1) {
                        var directionsService = new google.maps.DirectionsService();
                        var directionsRenderer = new google.maps.DirectionsRenderer({
                            map: map,
                            suppressMarkers: true, // dejar nuestros markers personalizados
                            polylineOptions: { strokeColor: '#d68643', strokeWeight: 5, strokeOpacity: 0.85 }
                        });
                        el._gmapPath = directionsRenderer;

                        var waypoints = puntos.slice(1, -1).map(function(p){ return {location: p, stopover: true}; });
                        directionsService.route({
                            origin: puntos[0],
                            destination: puntos[puntos.length - 1],
                            waypoints: waypoints,
                            travelMode: google.maps.TravelMode.DRIVING
                        }, function(result, status) {
                            if (status === 'OK' && result) {
                                directionsRenderer.setDirections(result);
                                // Sumar duración + distancia de todos los legs
                                var totalDur = 0, totalDist = 0;
                                result.routes[0].legs.forEach(function(leg){
                                    totalDur += leg.duration.value;
                                    totalDist += leg.distance.value;
                                });
                                var min = Math.round(totalDur / 60);
                                var km = (totalDist / 1000).toFixed(1);
                                var info = document.getElementById('ruta-info-osrm');
                                if (info) info.textContent = ' · ' + km + ' km real · ~' + min + ' min conduciendo';
                            } else {
                                console.warn('Google Maps Directions falló:', status);
                            }
                        });
                    }

                    return true;
                }

                // Polling con reintentos (Google Maps se carga async)
                var intentos = 0;
                var intervalo = setInterval(function () {
                    intentos++;
                    var ok = iniciarMapaDespacho();
                    if (ok || intentos > 60) clearInterval(intervalo);
                }, 250);
            })();
        </script>
        @endscript
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         TABLA UNIFICADA: SIN ASIGNAR + ASIGNADOS
         Estilo /pedidos — colores de la marca
         ═══════════════════════════════════════════════════════════════ --}}
    @php
        // Pedidos en preparación (vienen de $agrupados)
        $pedidosPrep = $agrupados->flatMap(fn($g) => $g['pedidos'])->values();
        // Pedidos en ruta (ya asignados a un domiciliario, vienen de $porDomiciliario)
        $pedidosRuta = isset($porDomiciliario)
            ? collect($porDomiciliario)->flatMap(fn($info) => $info['pedidos'])->values()
            : collect();

        // Unificar y separar (usa la relación cargada — más seguro contra IDs huérfanos)
        $todosPedidos = $pedidosPrep->merge($pedidosRuta)->unique('id')->values();
        $sinAsignar   = $todosPedidos->filter(fn($p) => empty($p->domiciliario_id) || !$p->domiciliario)->sortBy('zona_cobertura_id')->values();
        $asignados    = $todosPedidos->filter(fn($p) => !empty($p->domiciliario_id) && $p->domiciliario)->sortBy('domiciliario_id')->values();
        $totalSinAsig = $sinAsignar->sum('total');
        $totalAsig    = $asignados->sum('total');
    @endphp

    {{-- ════════════════ TABS DE FILTRO (estilo /pedidos) ════════════════ --}}
    @php
        $countTodos      = $todosPedidos->count();
        $countSinAsignar = $sinAsignar->count();
        $countAsignados  = $asignados->count();
        $mostrarSin = in_array($filtroAsignacion, ['todos', 'sin_asignar'], true);
        $mostrarAsi = in_array($filtroAsignacion, ['todos', 'asignados'], true);
    @endphp

    <div class="mb-5 rounded-2xl bg-white border border-slate-200 shadow-sm p-2 flex flex-wrap items-center gap-1.5 overflow-x-auto">
        {{-- TODOS --}}
        <button type="button" wire:click="setFiltroAsignacion('todos')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition whitespace-nowrap
                       {{ $filtroAsignacion === 'todos'
                            ? 'bg-slate-900 text-white shadow-sm'
                            : 'bg-transparent text-slate-700 hover:bg-slate-100' }}">
            <i class="fa-solid fa-table-list"></i>
            <span>Todos</span>
            <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-extrabold
                       {{ $filtroAsignacion === 'todos' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">
                {{ $countTodos }}
            </span>
        </button>

        {{-- SIN ASIGNAR --}}
        <button type="button" wire:click="setFiltroAsignacion('sin_asignar')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition whitespace-nowrap
                       {{ $filtroAsignacion === 'sin_asignar'
                            ? 'bg-rose-500 text-white shadow-sm'
                            : 'bg-transparent text-slate-700 hover:bg-rose-50' }}">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Faltan por asignar</span>
            <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-extrabold
                       {{ $filtroAsignacion === 'sin_asignar' ? 'bg-white/25 text-white' : 'bg-rose-100 text-rose-700' }}">
                {{ $countSinAsignar }}
            </span>
        </button>

        {{-- ASIGNADOS --}}
        <button type="button" wire:click="setFiltroAsignacion('asignados')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition whitespace-nowrap
                       {{ $filtroAsignacion === 'asignados'
                            ? 'bg-brand text-white shadow-sm'
                            : 'bg-transparent text-slate-700 hover:bg-brand/10' }}">
            <i class="fa-solid fa-motorcycle"></i>
            <span>Ya asignados</span>
            <span class="inline-flex items-center justify-center min-w-[24px] h-5 px-1.5 rounded-full text-[11px] font-extrabold
                       {{ $filtroAsignacion === 'asignados' ? 'bg-white/25 text-white' : 'bg-brand/10 text-brand' }}">
                {{ $countAsignados }}
            </span>
        </button>

        <div class="ml-auto pr-2 text-xs text-slate-500 hidden md:block">
            Total: <span class="font-bold text-slate-700">${{ number_format($todosPedidos->sum('total'), 0, ',', '.') }}</span>
        </div>
    </div>

    @if($todosPedidos->isEmpty() || ($filtroAsignacion === 'sin_asignar' && $sinAsignar->isEmpty()) || ($filtroAsignacion === 'asignados' && $asignados->isEmpty()))
        <div class="rounded-2xl bg-white p-16 text-center shadow-sm border border-slate-100">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-brand/10 to-brand/5">
                <i class="fa-solid fa-mug-hot text-3xl text-brand"></i>
            </div>
            <h3 class="font-extrabold text-slate-800 mb-1">
                @if($filtroAsignacion === 'sin_asignar')
                    No hay pedidos pendientes
                @elseif($filtroAsignacion === 'asignados')
                    Aún no has asignado pedidos
                @else
                    Todo despachado
                @endif
            </h3>
            <p class="text-sm text-slate-500">
                @if($filtroAsignacion === 'sin_asignar')
                    Todos los pedidos ya tienen un domiciliario asignado.
                @elseif($filtroAsignacion === 'asignados')
                    Los pedidos asignados aparecerán aquí.
                @else
                    No hay pedidos en preparación esperando.
                @endif
            </p>
        </div>
    @else
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">

            {{-- TABLA (scroll horizontal solo si la pantalla es muy angosta) --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                            <th class="px-4 py-3 text-left w-10">
                                <i class="fa-solid fa-square-check text-slate-400"></i>
                            </th>
                            <th class="px-3 py-3 text-left">Pedido</th>
                            <th class="px-3 py-3 text-left">Cliente</th>
                            <th class="px-3 py-3 text-left">Dirección</th>
                            <th class="px-3 py-3 text-left">Zona</th>
                            <th class="px-3 py-3 text-left">Estado</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-left">Asignación</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        {{-- ═══════════ SECCIÓN: SIN ASIGNAR ═══════════ --}}
                        @if($mostrarSin && $sinAsignar->isNotEmpty())
                            <tr class="bg-gradient-to-r from-rose-50 to-rose-50/30">
                                <td colspan="8" class="px-4 py-2.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-rose-500 text-white">
                                                <i class="fa-solid fa-circle-exclamation text-[11px]"></i>
                                            </span>
                                            <span class="text-xs font-extrabold uppercase tracking-wider text-rose-800">
                                                Faltan por asignar
                                            </span>
                                            <span class="rounded-full bg-rose-200 text-rose-800 px-2 py-0.5 text-[10px] font-bold">
                                                {{ $sinAsignar->count() }}
                                            </span>
                                            <span class="text-xs text-rose-700 font-semibold">
                                                ${{ number_format($totalSinAsig, 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] uppercase tracking-wider text-rose-600 font-bold">
                                            Selecciona un domiciliario →
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            @foreach($sinAsignar as $p)
                                @php $isSelected = !empty($seleccionados[$p->id]); @endphp
                                <tr class="hover:bg-rose-50/30 transition {{ $isSelected ? 'bg-rose-50' : '' }}">
                                    {{-- Checkbox --}}
                                    <td class="px-4 py-3">
                                        <input type="checkbox"
                                               wire:model.live="seleccionados.{{ $p->id }}"
                                               class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand cursor-pointer">
                                    </td>

                                    {{-- # ID --}}
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center justify-center rounded-md bg-slate-900 px-2 py-1 text-[11px] font-mono font-bold text-white">
                                            #{{ str_pad($p->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                        @if($p->canal === 'whatsapp')
                                            <div class="mt-1"><i class="fa-brands fa-whatsapp text-green-500 text-sm" title="WhatsApp"></i></div>
                                        @endif
                                    </td>

                                    {{-- Cliente --}}
                                    <td class="px-3 py-3">
                                        <div class="font-bold text-slate-800 truncate max-w-[180px]">{{ $p->cliente_nombre }}</div>
                                        @if($p->telefono_whatsapp || $p->telefono)
                                            <div class="text-[11px] text-slate-500 flex items-center gap-1">
                                                <i class="fa-solid fa-phone text-blue-400 text-[10px]"></i>
                                                {{ $p->telefono_whatsapp ?? $p->telefono }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Dirección --}}
                                    <td class="px-3 py-3 max-w-[200px]">
                                        <div class="text-xs text-slate-700 truncate flex items-start gap-1">
                                            <i class="fa-solid fa-location-dot text-rose-400 mt-0.5"></i>
                                            <span class="truncate">{{ $p->direccion ?: '—' }}</span>
                                        </div>
                                        @if($p->barrio)
                                            <div class="text-[11px] text-slate-500 truncate flex items-center gap-1 mt-0.5">
                                                <i class="fa-solid fa-map-pin text-emerald-400 text-[10px]"></i>
                                                {{ $p->barrio }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Zona --}}
                                    <td class="px-3 py-3">
                                        @if($p->zonaCobertura)
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                  style="background: {{ $p->zonaCobertura->color ?? '#94a3b8' }}20; color: {{ $p->zonaCobertura->color ?? '#475569' }}">
                                                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $p->zonaCobertura->color ?? '#94a3b8' }}"></span>
                                                {{ $p->zonaCobertura->nombre }}
                                            </span>
                                        @else
                                            <span class="text-[11px] text-slate-400 italic">Sin zona</span>
                                        @endif
                                    </td>

                                    {{-- Estado --}}
                                    <td class="px-3 py-3">
                                        @php
                                            $estadoColor = match($p->estado) {
                                                'repartidor_en_camino' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500', 'label' => 'En camino'],
                                                'entregado' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Entregado'],
                                                'en_preparacion' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'label' => 'En preparación'],
                                                'nuevo' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400', 'label' => 'Nuevo'],
                                                'cancelado' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'dot' => 'bg-rose-500', 'label' => 'Cancelado'],
                                                default => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400', 'label' => str_replace('_', ' ', $p->estado)],
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 rounded-full {{ $estadoColor['bg'] }} px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $estadoColor['text'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $estadoColor['dot'] }}"></span>
                                            {{ $estadoColor['label'] }}
                                        </span>
                                        @if($p->detalles->isNotEmpty())
                                            <div class="text-[10px] text-slate-500 mt-1 truncate max-w-[120px]" title="{{ $p->detalles->pluck('producto')->implode(', ') }}">
                                                <i class="fa-solid fa-box text-slate-400"></i>
                                                {{ $p->detalles->first()->producto }}
                                                @if($p->detalles->count() > 1)
                                                    <span class="text-slate-400">+{{ $p->detalles->count() - 1 }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Total --}}
                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        <div class="font-extrabold text-brand">${{ number_format($p->total, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $p->fecha_pedido?->diffForHumans() }}</div>
                                    </td>

                                    {{-- Acción: Asignar --}}
                                    <td class="px-3 py-3">
                                        <select
                                            onchange="window.confirmarReasignar(this, {{ $p->id }}, 'asignar')"
                                            class="w-full min-w-[160px] rounded-lg border-2 border-rose-300 bg-white px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:border-rose-400 hover:bg-rose-50 focus:border-rose-500 focus:ring-rose-500 transition cursor-pointer">
                                            <option value="">— Asignar a un domiciliario —</option>
                                            @foreach($domiciliarios as $dRe)
                                                <option value="{{ $dRe->id }}">
                                                    {{ $dRe->nombre }} · {{ ucfirst($dRe->estado) }}{{ $dRe->vehiculo ? ' · '.$dRe->vehiculo : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        @endif

                        {{-- ═══════════ SECCIÓN: YA ASIGNADOS ═══════════ --}}
                        @if($mostrarAsi && $asignados->isNotEmpty())
                            <tr class="bg-gradient-to-r from-brand/10 to-brand/5">
                                <td colspan="8" class="px-4 py-2.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand text-white">
                                                <i class="fa-solid fa-motorcycle text-[11px]"></i>
                                            </span>
                                            <span class="text-xs font-extrabold uppercase tracking-wider text-brand-dark">
                                                Ya asignados
                                            </span>
                                            <span class="rounded-full bg-brand/20 text-brand-dark px-2 py-0.5 text-[10px] font-bold">
                                                {{ $asignados->count() }}
                                            </span>
                                            <span class="text-xs text-brand-dark font-semibold">
                                                ${{ number_format($totalAsig, 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] uppercase tracking-wider text-brand-dark font-bold">
                                            Puedes reasignar si es necesario →
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            @foreach($asignados as $p)
                                @php
                                    $isSelected = !empty($seleccionados[$p->id]);
                                    $domiAsig = $p->domiciliario;
                                    $iniDom = $domiAsig
                                        ? collect(explode(' ', trim($domiAsig->nombre)))->filter()->take(2)->map(fn($x)=>mb_substr($x,0,1))->implode('')
                                        : '';
                                @endphp
                                <tr class="hover:bg-brand/5 transition {{ $isSelected ? 'bg-brand/10' : '' }}">
                                    {{-- Checkbox --}}
                                    <td class="px-4 py-3">
                                        <input type="checkbox"
                                               wire:model.live="seleccionados.{{ $p->id }}"
                                               class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand cursor-pointer">
                                    </td>

                                    {{-- # ID --}}
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center justify-center rounded-md bg-slate-900 px-2 py-1 text-[11px] font-mono font-bold text-white">
                                            #{{ str_pad($p->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                        @if($p->canal === 'whatsapp')
                                            <div class="mt-1"><i class="fa-brands fa-whatsapp text-green-500 text-sm" title="WhatsApp"></i></div>
                                        @endif
                                    </td>

                                    {{-- Cliente --}}
                                    <td class="px-3 py-3">
                                        <div class="font-bold text-slate-800 truncate max-w-[180px]">{{ $p->cliente_nombre }}</div>
                                        @if($p->telefono_whatsapp || $p->telefono)
                                            <div class="text-[11px] text-slate-500 flex items-center gap-1">
                                                <i class="fa-solid fa-phone text-blue-400 text-[10px]"></i>
                                                {{ $p->telefono_whatsapp ?? $p->telefono }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Dirección --}}
                                    <td class="px-3 py-3 max-w-[200px]">
                                        <div class="text-xs text-slate-700 truncate flex items-start gap-1">
                                            <i class="fa-solid fa-location-dot text-rose-400 mt-0.5"></i>
                                            <span class="truncate">{{ $p->direccion ?: '—' }}</span>
                                        </div>
                                        @if($p->barrio)
                                            <div class="text-[11px] text-slate-500 truncate flex items-center gap-1 mt-0.5">
                                                <i class="fa-solid fa-map-pin text-emerald-400 text-[10px]"></i>
                                                {{ $p->barrio }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Zona --}}
                                    <td class="px-3 py-3">
                                        @if($p->zonaCobertura)
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                  style="background: {{ $p->zonaCobertura->color ?? '#94a3b8' }}20; color: {{ $p->zonaCobertura->color ?? '#475569' }}">
                                                <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $p->zonaCobertura->color ?? '#94a3b8' }}"></span>
                                                {{ $p->zonaCobertura->nombre }}
                                            </span>
                                        @else
                                            <span class="text-[11px] text-slate-400 italic">Sin zona</span>
                                        @endif
                                    </td>

                                    {{-- Estado --}}
                                    <td class="px-3 py-3">
                                        @php
                                            $estadoColor = match($p->estado) {
                                                'repartidor_en_camino' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500', 'label' => 'En camino'],
                                                'entregado' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Entregado'],
                                                'en_preparacion' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'label' => 'En preparación'],
                                                'nuevo' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400', 'label' => 'Nuevo'],
                                                'cancelado' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'dot' => 'bg-rose-500', 'label' => 'Cancelado'],
                                                default => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400', 'label' => str_replace('_', ' ', $p->estado)],
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 rounded-full {{ $estadoColor['bg'] }} px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $estadoColor['text'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $estadoColor['dot'] }}"></span>
                                            {{ $estadoColor['label'] }}
                                        </span>
                                        @if($p->detalles->isNotEmpty())
                                            <div class="text-[10px] text-slate-500 mt-1 truncate max-w-[120px]" title="{{ $p->detalles->pluck('producto')->implode(', ') }}">
                                                <i class="fa-solid fa-box text-slate-400"></i>
                                                {{ $p->detalles->first()->producto }}
                                                @if($p->detalles->count() > 1)
                                                    <span class="text-slate-400">+{{ $p->detalles->count() - 1 }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Total --}}
                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        <div class="font-extrabold text-brand">${{ number_format($p->total, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $p->fecha_pedido?->diffForHumans() }}</div>
                                    </td>

                                    {{-- Asignación + Reasignar --}}
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            {{-- Avatar del domi --}}
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-brand to-brand-dark text-white text-[10px] font-extrabold shrink-0"
                                                 title="{{ $domiAsig?->nombre ?? 'Sin asignar' }}">
                                                {{ $iniDom ?: '?' }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-bold text-slate-800 truncate">{{ $domiAsig?->nombre ?? '—' }}</div>
                                                <select
                                                    onchange="window.confirmarReasignar(this, {{ $p->id }}, 'reasignar')"
                                                    class="mt-0.5 w-full min-w-[140px] rounded-md border border-brand/30 bg-brand/5 px-1.5 py-0.5 text-[10px] font-semibold text-brand-dark hover:bg-brand/10 focus:border-brand focus:ring-brand cursor-pointer">
                                                    <option value="">— Reasignar a otro domiciliario —</option>
                                                    @foreach($domiciliarios->where('id', '!=', $p->domiciliario_id) as $dRe)
                                                        <option value="{{ $dRe->id }}">
                                                            {{ $dRe->nombre }} · {{ ucfirst($dRe->estado) }}{{ $dRe->vehiculo ? ' · '.$dRe->vehiculo : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 🛵 PEDIDOS EN RUTA AGRUPADOS POR DOMICILIARIO (unificado arriba en tabla principal) --}}
    @if(false && isset($porDomiciliario) && $porDomiciliario->isNotEmpty())
        <div class="mt-8">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
                <div>
                    <h2 class="text-xl font-extrabold text-slate-800">Pedidos en ruta por domiciliario</h2>
                    <p class="text-xs text-slate-500">{{ $porDomiciliario->count() }} domiciliario(s) activo(s) · {{ $porDomiciliario->sum('cantidad') }} pedido(s) en ruta</p>
                </div>
            </div>

            @if(isset($domiciliariosPag) && $domiciliariosPag->hasPages())
                <div class="mb-3">{{ $domiciliariosPag->links() }}</div>
            @endif

            <div class="space-y-4">
                @foreach($porDomiciliario as $info)
                    @php
                        $d = $info['domiciliario'];
                        $iniciales = collect(explode(' ', trim($d?->nombre ?? '?')))
                            ->filter()->take(2)->map(fn($p)=>mb_substr($p,0,1))->implode('');
                    @endphp
                    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
                        {{-- Header del domiciliario --}}
                        <div class="px-5 py-4 bg-gradient-to-r from-blue-50 to-white border-b border-slate-100 flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-white font-extrabold">
                                {{ $iniciales ?: '?' }}
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-slate-800">{{ $d?->nombre ?? 'Sin asignar' }}</h3>
                                <p class="text-xs text-slate-500">
                                    @if($d?->telefono) <i class="fa-solid fa-phone"></i> {{ $d->telefono }} @endif
                                    @if($d?->vehiculo) · <i class="fa-solid fa-{{ $d->vehiculo === 'moto' ? 'motorcycle' : 'car' }}"></i> {{ ucfirst($d->vehiculo) }} @endif
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500">{{ $info['cantidad'] }} {{ $info['cantidad'] === 1 ? 'pedido' : 'pedidos' }}</p>
                                <p class="text-lg font-extrabold text-slate-800">${{ number_format($info['total'], 0, ',', '.') }}</p>
                            </div>
                        </div>

                        {{-- Lista de pedidos --}}
                        <div class="divide-y divide-slate-100">
                            @foreach($info['pedidos'] as $p)
                                @php
                                    $estadoColor = match($p->estado) {
                                        'repartidor_en_camino' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500'],
                                        'entregado' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
                                        'en_preparacion' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
                                        'nuevo' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400'],
                                        default => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400'],
                                    };
                                @endphp
                                <div class="px-5 py-3.5 hover:bg-slate-50 transition">
                                    <div class="grid grid-cols-12 gap-3 items-center">
                                        {{-- ID + estado --}}
                                        <div class="col-span-12 sm:col-span-2 flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center rounded-md bg-slate-900 px-2 py-1 text-[11px] font-mono font-bold text-white">
                                                #{{ $p->id }}
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 rounded-full {{ $estadoColor['bg'] }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $estadoColor['text'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $estadoColor['dot'] }}"></span>
                                                {{ str_replace('_', ' ', $p->estado) }}
                                            </span>
                                        </div>

                                        {{-- Cliente + dirección --}}
                                        <div class="col-span-12 sm:col-span-5 min-w-0">
                                            <p class="text-sm font-bold text-slate-800 truncate">{{ $p->cliente_nombre }}</p>
                                            <p class="text-xs text-slate-500 truncate flex items-center gap-1">
                                                <i class="fa-solid fa-location-dot text-rose-400"></i>
                                                {{ $p->direccion ?: 'Sin dirección' }}
                                                @if($p->zonaCobertura)
                                                    <span class="text-slate-300">·</span>
                                                    <span class="text-emerald-600 font-medium">{{ $p->zonaCobertura->nombre }}</span>
                                                @endif
                                            </p>
                                        </div>

                                        {{-- Teléfono --}}
                                        <div class="col-span-6 sm:col-span-2 text-xs text-slate-600">
                                            <div class="flex items-center gap-1">
                                                <i class="fa-solid fa-phone text-blue-400"></i>
                                                <span class="truncate">{{ $p->telefono ?: '—' }}</span>
                                            </div>
                                        </div>

                                        {{-- Total --}}
                                        <div class="col-span-6 sm:col-span-1 text-right">
                                            <p class="text-sm font-extrabold text-slate-800">${{ number_format($p->total, 0, ',', '.') }}</p>
                                        </div>

                                        {{-- Reasignar --}}
                                        <div class="col-span-12 sm:col-span-2 flex justify-end">
                                            <select
                                                onchange="window.confirmarReasignar(this, {{ $p->id }}, 'reasignar')"
                                                class="w-full rounded-lg border-2 border-amber-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-amber-800 hover:border-amber-400 hover:bg-amber-50 focus:border-amber-500 focus:ring-amber-500 transition cursor-pointer"
                                                title="Reasignar a otro domiciliario">
                                                <option value="">— Reasignar a otro domiciliario —</option>
                                                @foreach($domiciliarios->where('id', '!=', $p->domiciliario_id) as $dRe)
                                                    <option value="{{ $dRe->id }}">
                                                        {{ $dRe->nombre }} ({{ ucfirst($dRe->estado) }}){{ $dRe->vehiculo ? ' · '.$dRe->vehiculo : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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
                            <i class="fa-solid {{ $multiZona ? 'fa-map' : 'fa-motorcycle' }} text-violet-500"></i>
                            {{ $multiZona ? 'Despacho masivo por zonas' : 'Asignar domiciliario' }}
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
                                            <div class="font-bold text-slate-800">
                                                <i class="fa-solid fa-location-dot text-rose-500"></i> {{ $nombreZona }}
                                            </div>
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
                                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                                        <option value="">— Selecciona un domiciliario —</option>
                                        @if($domsDeZona->isNotEmpty())
                                            <optgroup label="<i class="fa-solid fa-check"></i> Cubren esta zona">
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
                                                  {{ $domiciliarioSeleccionado === $dom->id ? 'border-brand bg-amber-50' : 'border-slate-200' }}">
                                        <input type="radio" wire:model="domiciliarioSeleccionado" value="{{ $dom->id }}"
                                               class="text-brand focus:ring-brand">

                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white text-sm font-bold">
                                            {{ strtoupper(substr($dom->nombre, 0, 1)) }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-slate-800 truncate">{{ $dom->nombre }}</span>
                                                @if($cubreZona === true)
                                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">
                                                        <i class="fa-solid fa-check"></i> Cubre zona
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
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" wire:click="cerrarModal"
                                    class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="rounded-xl bg-brand px-6 py-2.5 text-sm font-bold text-white shadow hover:bg-brand-dark">
                                <i class="fa-solid fa-paper-plane mr-2"></i>
                                Despachar {{ $totalSelected }} pedido(s)
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
    @endif {{-- /!$esDomiciliarioPuro --}}

    {{-- ════════════════════════════════════════════════════════════════
         💰 MODAL: MARCAR PAGO RECIBIDO
         ════════════════════════════════════════════════════════════════ --}}
    @if($modalPagoPedidoId)
        @php
            $pPago = \App\Models\Pedido::find($modalPagoPedidoId);
        @endphp
        @if($pPago)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                 wire:click.self="cerrarModalPago">
                <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden"
                     wire:click.stop>
                    <div class="bg-gradient-to-br from-amber-500 to-amber-600 text-white p-6">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-white/20 flex items-center justify-center">
                                <i class="fa-solid fa-money-bill-wave text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-extrabold">Registrar pago</h3>
                                <p class="text-white/80 text-sm">Pedido #{{ $pPago->id }} · {{ $pPago->cliente_nombre }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 mb-4">
                            <div class="text-xs uppercase text-amber-700 font-bold">Total a cobrar</div>
                            <div class="text-3xl font-black text-amber-900 mt-1">
                                ${{ number_format((float) $pPago->total, 0, ',', '.') }}
                            </div>
                        </div>

                        <label class="block text-xs font-bold uppercase text-slate-600 mb-2">
                            ¿Cómo te pagó el cliente?
                        </label>
                        <div class="grid grid-cols-3 gap-2 mb-5">
                            @foreach([
                                'efectivo' => ['<i class="fa-solid fa-money-bill"></i>', 'Efectivo'],
                                'transferencia' => ['<i class="fa-solid fa-building-columns"></i>', 'Transferencia'],
                                'tarjeta' => ['<i class="fa-solid fa-credit-card"></i>', 'Tarjeta'],
                            ] as $val => [$emoji, $label])
                                <button type="button" wire:click="$set('modalPagoMetodo', '{{ $val }}')"
                                        class="rounded-xl border-2 p-3 text-center transition
                                            {{ $modalPagoMetodo === $val ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-slate-200 hover:border-slate-300 text-slate-700' }}">
                                    <div class="text-2xl mb-1">{!! $emoji !!}</div>
                                    <div class="text-xs font-bold">{{ $label }}</div>
                                </button>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            <button type="button" wire:click="cerrarModalPago"
                                    class="flex-1 rounded-xl border-2 border-slate-200 hover:bg-slate-50 py-3 text-sm font-bold text-slate-700">
                                Cancelar
                            </button>
                            <button type="button" wire:click="confirmarPago"
                                    class="flex-1 rounded-xl bg-amber-500 hover:bg-amber-600 text-white py-3 text-sm font-bold">
                                <i class="fa-solid fa-check"></i> Confirmar pago
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ════════════════════════════════════════════════════════════════
         ✅ MODAL: ENTREGAR PEDIDO (con código de verificación)
         ════════════════════════════════════════════════════════════════ --}}
    @if($modalEntregaPedidoId)
        @php
            $pEnt = \App\Models\Pedido::find($modalEntregaPedidoId);
        @endphp
        @if($pEnt)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                 wire:click.self="cerrarModalEntrega">
                <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden"
                     wire:click.stop>
                    <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 text-white p-6">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-white/20 flex items-center justify-center">
                                <i class="fa-solid fa-circle-check text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-extrabold">Confirmar entrega</h3>
                                <p class="text-white/80 text-sm">Pedido #{{ $pEnt->id }} · {{ $pEnt->cliente_nombre }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 mb-4 text-center">
                            <i class="fa-solid fa-key text-emerald-600 text-xl mb-2"></i>
                            <p class="text-sm text-emerald-900 font-bold mb-1">Pídele al cliente que te dicte su código</p>
                            <p class="text-[11px] text-emerald-700">El cliente lo recibió en su WhatsApp cuando saliste a entregar.</p>
                        </div>

                        {{-- 👁️ Código esperado (visible para el domiciliario) --}}
                        @if($pEnt->token_entrega)
                            <div x-data="{ ver: false }" class="rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50 p-3 mb-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-eye text-amber-600"></i>
                                        <span class="text-xs font-bold text-amber-900">Código esperado:</span>
                                    </div>
                                    <button type="button" @click="ver = !ver"
                                            class="text-[11px] font-bold text-amber-700 hover:text-amber-900 underline">
                                        <span x-show="!ver"><i class="fa-solid fa-eye"></i> Mostrar</span>
                                        <span x-show="ver" x-cloak><i class="fa-solid fa-eye-slash"></i> Ocultar</span>
                                    </button>
                                </div>
                                <div x-show="ver" x-cloak class="mt-2 text-center">
                                    <div class="font-mono text-3xl font-black tracking-[0.5em] text-amber-900">
                                        {{ $pEnt->token_entrega }}
                                    </div>
                                    <p class="text-[10px] text-amber-700 mt-1 italic"><i class="fa-solid fa-triangle-exclamation"></i> Úsalo solo como referencia, el cliente debe dictarlo</p>
                                </div>
                            </div>
                        @endif

                        <label class="block text-xs font-bold uppercase text-slate-600 mb-2 text-center">
                            Código del cliente (4 dígitos)
                        </label>
                        <input type="text" wire:model.live="modalEntregaCodigo"
                               inputmode="numeric" maxlength="4" autofocus
                               placeholder="• • • •"
                               class="w-full rounded-2xl border-2 border-slate-200 px-3 py-4 text-center text-3xl font-mono font-black tracking-[0.5em] focus:border-emerald-500 focus:outline-none mb-3">

                        @if($modalEntregaError)
                            <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-3 text-sm text-rose-700 text-center">
                                {{ $modalEntregaError }}
                            </div>
                        @endif

                        <div class="rounded-xl bg-slate-50 p-3 mb-4 text-xs text-slate-600">
                            <div class="flex items-center justify-between">
                                <span>Total cobrado:</span>
                                <strong class="text-emerald-700">${{ number_format((float) $pEnt->total, 0, ',', '.') }}</strong>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span>Método de pago:</span>
                                <strong>{{ ucfirst($pEnt->metodo_pago ?: 'efectivo') }} <i class="fa-solid fa-check"></i></strong>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" wire:click="cerrarModalEntrega"
                                    class="flex-1 rounded-xl border-2 border-slate-200 hover:bg-slate-50 py-3 text-sm font-bold text-slate-700">
                                Cancelar
                            </button>
                            <button type="button" wire:click="confirmarEntrega"
                                    class="flex-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white py-3 text-sm font-bold disabled:opacity-50"
                                    @if(strlen(trim($modalEntregaCodigo)) < 4) disabled @endif>
                                <i class="fa-solid fa-check"></i> Entregar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
