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

    {{-- ════════════════════════════════════════════════════════════════
         🛵 PANEL PERSONAL DEL DOMICILIARIO (cuando es solo rol 'domiciliario')
         Se muestra ARRIBA del view admin estándar. El view admin se filtra
         automáticamente a sus pedidos.
         ════════════════════════════════════════════════════════════════ --}}
    @if($esDomiciliarioPuro && $domiActual)
        <div class="mb-6 max-w-5xl">
            {{-- Welcome card con stats --}}
            <div class="rounded-3xl bg-gradient-to-br from-brand to-brand-dark text-white p-6 shadow-xl">
                <div class="flex items-center gap-4 mb-5">
                    <div class="h-14 w-14 rounded-full bg-white/20 flex items-center justify-center text-2xl font-extrabold">
                        {{ mb_substr($domiActual->nombre, 0, 1) }}
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold">¡Hola {{ explode(' ', $domiActual->nombre)[0] }}!</h2>
                        <p class="text-white/80 text-sm">
                            <i class="fa-solid fa-motorcycle"></i>
                            {{ $domiActual->vehiculo ?: 'Vehículo' }} · {{ $domiActual->placa ?: '—' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-white/15 backdrop-blur-sm p-3 text-center">
                        <div class="text-2xl font-black">{{ $statsDomi['pendientes'] }}</div>
                        <div class="text-[10px] uppercase tracking-wider text-white/80">Pendientes</div>
                    </div>
                    <div class="rounded-2xl bg-white/15 backdrop-blur-sm p-3 text-center">
                        <div class="text-2xl font-black">{{ $statsDomi['entregados'] }}</div>
                        <div class="text-[10px] uppercase tracking-wider text-white/80">Entregados</div>
                    </div>
                    <div class="rounded-2xl bg-white/15 backdrop-blur-sm p-3 text-center">
                        <div class="text-2xl font-black">{{ $statsDomi['total_hoy'] }}</div>
                        <div class="text-[10px] uppercase tracking-wider text-white/80">Total hoy</div>
                    </div>
                </div>
            </div>

            {{-- Botón ruta óptima --}}
            @if($rutaOptimaUrl && $pedidosOrdenados->count() > 0)
                <a href="{{ $rutaOptimaUrl }}" target="_blank" rel="noopener"
                   class="mt-3 flex items-center justify-between rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4 hover:bg-emerald-100 transition group">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center">
                            <i class="fa-solid fa-route"></i>
                        </div>
                        <div>
                            <div class="font-bold text-emerald-900">Ver ruta óptima en Google Maps</div>
                            <div class="text-[11px] text-emerald-700">{{ $pedidosOrdenados->count() }} parada(s) · Optimizado por cercanía</div>
                        </div>
                    </div>
                    <i class="fa-brands fa-google text-xl text-emerald-600 group-hover:scale-110 transition"></i>
                </a>
            @endif

            {{-- Lista de pedidos del domiciliario con código y botones --}}
            @if($pedidosOrdenados->count() > 0)
                <div class="mt-4">
                    <h3 class="text-xs uppercase tracking-wider text-slate-500 font-bold mb-3">
                        <i class="fa-solid fa-list-ol"></i> Mis pedidos en orden
                    </h3>
                    <div class="space-y-3">
                        @foreach($pedidosOrdenados as $i => $p)
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-brand text-white flex items-center justify-center font-bold">
                                            {{ $i + 1 }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800">Pedido #{{ $p->id }}</div>
                                            <div class="text-xs text-slate-500">{{ $p->cliente_nombre ?: 'Cliente' }}</div>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold
                                        @if($p->estado === 'repartidor_en_camino') bg-violet-100 text-violet-800
                                        @else bg-amber-100 text-amber-800 @endif">
                                        @if($p->estado === 'repartidor_en_camino') 🛵 En camino
                                        @else 👨‍🍳 En preparación @endif
                                    </span>
                                </div>

                                <div class="text-sm text-slate-700 mb-1">
                                    <i class="fa-solid fa-location-dot text-rose-500"></i>
                                    {{ $p->direccion ?: 'Sin dirección' }}{{ $p->barrio ? ', ' . $p->barrio : '' }}
                                </div>
                                <div class="flex items-center justify-between mb-3">
                                    @if($p->telefono_contacto ?: $p->telefono_whatsapp)
                                        <a href="tel:{{ $p->telefono_contacto ?: $p->telefono_whatsapp }}" class="text-xs text-emerald-700 hover:underline">
                                            <i class="fa-solid fa-phone"></i> {{ $p->telefono_contacto ?: $p->telefono_whatsapp }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">Sin teléfono</span>
                                    @endif
                                    <span class="font-bold text-slate-800">${{ number_format((float) $p->total, 0, ',', '.') }}</span>
                                </div>

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
                                            <div class="font-bold text-rose-800">⚠️ Pedido SIN pagar</div>
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
                               class="mt-1 h-5 w-5 rounded border-slate-300 text-brand focus:ring-brand">

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
                                            <span><i class="fa-solid fa-location-dot text-brand mr-1"></i>{{ $p->direccion }}</span>
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
                                    <div class="text-lg font-extrabold text-brand">
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

    {{-- 🛵 PEDIDOS EN RUTA AGRUPADOS POR DOMICILIARIO --}}
    @if(isset($porDomiciliario) && $porDomiciliario->isNotEmpty())
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
                                <div class="px-5 py-3 flex items-center gap-4 hover:bg-slate-50 transition">
                                    <div class="flex-shrink-0 px-3 py-1 rounded-lg bg-slate-100 text-xs font-mono font-bold">
                                        #{{ $p->id }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 truncate">{{ $p->cliente_nombre }}</p>
                                        <p class="text-xs text-slate-500 truncate">
                                            <i class="fa-solid fa-location-dot"></i>
                                            {{ $p->direccion ?: 'Sin dirección' }}
                                            @if($p->zonaCobertura) · <span class="text-emerald-600">{{ $p->zonaCobertura->nombre }}</span> @endif
                                        </p>
                                    </div>
                                    <div class="text-xs text-slate-500 flex-shrink-0">
                                        <i class="fa-solid fa-phone"></i> {{ $p->telefono }}
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-sm font-bold text-slate-800">${{ number_format($p->total, 0, ',', '.') }}</p>
                                        <p class="text-[10px] uppercase font-bold tracking-wider"
                                           style="color: {{ $p->estado === 'despachado' ? '#3b82f6' : '#10b981' }}">
                                            {{ $p->estado }}
                                        </p>
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
                                'efectivo' => ['💵', 'Efectivo'],
                                'transferencia' => ['🏦', 'Transferencia'],
                                'tarjeta' => ['💳', 'Tarjeta'],
                            ] as $val => [$emoji, $label])
                                <button type="button" wire:click="$set('modalPagoMetodo', '{{ $val }}')"
                                        class="rounded-xl border-2 p-3 text-center transition
                                            {{ $modalPagoMetodo === $val ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-slate-200 hover:border-slate-300 text-slate-700' }}">
                                    <div class="text-2xl mb-1">{{ $emoji }}</div>
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
                                        <span x-show="!ver">👁️ Mostrar</span>
                                        <span x-show="ver" x-cloak>🙈 Ocultar</span>
                                    </button>
                                </div>
                                <div x-show="ver" x-cloak class="mt-2 text-center">
                                    <div class="font-mono text-3xl font-black tracking-[0.5em] text-amber-900">
                                        {{ $pEnt->token_entrega }}
                                    </div>
                                    <p class="text-[10px] text-amber-700 mt-1 italic">⚠️ Úsalo solo como referencia, el cliente debe dictarlo</p>
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
                                <strong>{{ ucfirst($pEnt->metodo_pago ?: 'efectivo') }} ✓</strong>
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
