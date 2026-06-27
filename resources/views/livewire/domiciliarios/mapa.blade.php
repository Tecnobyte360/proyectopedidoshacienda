<div class="px-4 lg:px-6 py-4">
    {{-- Header --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800">
                <i class="fa-solid fa-map-location-dot text-emerald-600 mr-2"></i>
                Domiciliarios en vivo
            </h2>
            <p class="text-xs text-slate-500">
                Ubicación en tiempo real. Las motos se mueven solas cuando los domiciliarios reportan posición.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-600">
                <input type="checkbox" wire:model.live="mostrarSoloActivos" class="rounded border-slate-300" style="accent-color:#10b981;">
                Solo activos
            </label>
            <select wire:model.live="filtroEstado" class="text-sm rounded-lg border border-slate-200 px-3 py-1.5">
                <option value="">Todos los estados</option>
                <option value="disponible">Disponible</option>
                <option value="en_ruta">En ruta</option>
                <option value="ocupado">Ocupado</option>
                <option value="descanso">Descanso</option>
            </select>
        </div>
    </div>

    @if (!$gmapsCfg['activo'])
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-6 text-center">
            <i class="fa-solid fa-triangle-exclamation text-3xl text-amber-600 mb-3"></i>
            <h3 class="text-lg font-bold text-amber-800 mb-1">Google Maps no está activo</h3>
            <p class="text-sm text-amber-700">
                Ve a <strong>/admin/tenants</strong> y configura tu API Key de Google Maps.
            </p>
        </div>
    @else
        {{-- KPIs arriba --}}
        @php
            $totDomis = count($domiciliarios);
            $enRuta = count(array_filter($domiciliarios, fn($d) => $d['estado'] === 'en_ruta'));
            $online = count(array_filter($domiciliarios, fn($d) => ($d['minutos_inactivo'] ?? 999) < 5));
            $conRuta = count(array_filter($domiciliarios, fn($d) => !empty($d['ruta'])));
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2 flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
                <div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wide font-bold">Total</div>
                    <div class="text-lg font-extrabold text-slate-800">{{ $totDomis }}</div>
                </div>
            </div>
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2 flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                    <span class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></span>
                </div>
                <div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wide font-bold">Online</div>
                    <div class="text-lg font-extrabold text-slate-800">{{ $online }}</div>
                </div>
            </div>
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2 flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-route"></i>
                </div>
                <div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wide font-bold">En ruta</div>
                    <div class="text-lg font-extrabold text-slate-800">{{ $enRuta }}</div>
                </div>
            </div>
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2 flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center">
                    <i class="fa-solid fa-location-arrow"></i>
                </div>
                <div>
                    <div class="text-[10px] text-slate-500 uppercase tracking-wide font-bold">Con destino</div>
                    <div class="text-lg font-extrabold text-slate-800">{{ $conRuta }}</div>
                </div>
            </div>
        </div>

        {{-- Cards horizontales scrollables (arriba) --}}
        @if(count($domiciliarios) > 0)
            <div class="mb-3 overflow-x-auto">
                <div class="flex gap-2 pb-1" style="min-width: max-content;">
                    @foreach($domiciliarios as $d)
                        @php
                            $estadoColor = match($d['estado']) {
                                'disponible' => 'emerald',
                                'en_ruta'    => 'blue',
                                'ocupado'    => 'orange',
                                'descanso'   => 'slate',
                                default      => 'slate',
                            };
                            $minIna = $d['minutos_inactivo'];
                            $online = $minIna !== null && $minIna < 5;
                        @endphp
                        <div onclick="window.mapaDomi && window.mapaDomi.focusMarker({{ $d['id'] }})"
                             class="cursor-pointer rounded-xl bg-white border-2 border-slate-200 hover:border-{{ $estadoColor }}-400 hover:shadow-lg px-3 py-2 transition flex-shrink-0"
                             style="min-width: 220px;">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-9 h-9 rounded-full bg-{{ $estadoColor }}-100 text-{{ $estadoColor }}-600 flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-motorcycle"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-bold text-slate-800 truncate">{{ $d['nombre'] }}</div>
                                    <div class="text-[10px] text-slate-500 truncate">{{ $d['placa'] ?: $d['vehiculo'] ?: '—' }}</div>
                                </div>
                                <span class="w-2 h-2 rounded-full {{ $online ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300' }}"></span>
                            </div>
                            <div class="flex items-center justify-between text-[10px]">
                                <span class="font-bold uppercase tracking-wide text-{{ $estadoColor }}-600">
                                    {{ str_replace('_', ' ', $d['estado'] ?? 'inactivo') }}
                                </span>
                                <span class="text-slate-400">
                                    @if($minIna === null) — @elseif($minIna < 1) ahora @elseif($minIna < 60) {{ $minIna }}m @else {{ floor($minIna/60) }}h @endif
                                </span>
                            </div>
                            @if(!empty($d['ruta']))
                                <div class="mt-1.5 pt-1.5 border-t border-slate-100 text-[10px] text-slate-600 flex items-center gap-1">
                                    <i class="fa-solid fa-location-dot text-rose-500"></i>
                                    <span class="truncate flex-1">→ {{ Str::limit($d['ruta']['direccion'] ?? '', 24) }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-6 text-center mb-3">
                <i class="fa-solid fa-motorcycle text-3xl text-slate-300 mb-2"></i>
                <p class="text-sm text-slate-500">Sin domiciliarios reportando ubicación todavía. Cuando abran su portal y permitan GPS aparecerán aquí.</p>
            </div>
        @endif

        {{-- MAPA GRANDE --}}
        <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-slate-200">
            <div wire:ignore id="mapaDomiciliarios" style="height: 75vh; min-height: 600px; width: 100%;"></div>
            <div id="mapaStatus" class="absolute bottom-3 left-3 bg-white/95 backdrop-blur px-3 py-1.5 rounded-full text-[11px] text-slate-700 shadow font-medium">
                <i class="fa-solid fa-hourglass-half"></i> Cargando mapa...
            </div>
            <button onclick="window.mapaDomi && window.mapaDomi.fitAll()"
                    class="absolute bottom-3 right-3 bg-white hover:bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-full text-[11px] text-slate-700 shadow font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-arrows-to-eye"></i> Ajustar vista
            </button>
        </div>

        {{-- Carga de Google Maps + libraries necesarias (incluye Routes / Directions) --}}
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $gmapsCfg['api_key'] }}&amp;libraries=geometry,places&amp;language=es&amp;region=CO"
                defer
                onload="initMapaDomiciliarios()"></script>

        <script>
            window.mapaDomiState = {
                map: null,
                markers: {},
                routes: {},
                tenantId: {{ $tenantId }},
                centroDefault: { lat: {{ $gmapsCfg['centro_lat'] }}, lng: {{ $gmapsCfg['centro_lng'] }} },
                zoomDefault: {{ $gmapsCfg['zoom'] }},
                domiciliarios: @json($domiciliarios),
            };

            function initMapaDomiciliarios() {
                const el = document.getElementById('mapaDomiciliarios');
                if (!el) return;

                window.mapaDomiState.map = new google.maps.Map(el, {
                    center: window.mapaDomiState.centroDefault,
                    zoom: window.mapaDomiState.zoomDefault,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                    zoomControl: true,
                    gestureHandling: 'greedy',
                    styles: [
                        { featureType: 'poi.business', stylers: [{ visibility: 'off' }] },
                        { featureType: 'transit', stylers: [{ visibility: 'off' }] },
                        { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] }
                    ],
                });

                // Render inicial
                window.mapaDomiState.domiciliarios.forEach(renderDomi);
                ajustarVista();

                document.getElementById('mapaStatus').innerHTML =
                    `🟢 Conectado · ${Object.keys(window.mapaDomiState.markers).length} moto(s) en mapa`;

                window.mapaDomi = {
                    focusMarker(id) {
                        const entry = window.mapaDomiState.markers[id];
                        if (entry) {
                            window.mapaDomiState.map.panTo(entry.marker.getPosition());
                            window.mapaDomiState.map.setZoom(16);
                            google.maps.event.trigger(entry.marker, 'click');
                        }
                    },
                    fitAll() { ajustarVista(); }
                };

                conectarLiveSocket();
            }

            function colorPorEstado(estado) {
                switch(estado) {
                    case 'disponible': return '#10b981';
                    case 'en_ruta':    return '#3b82f6';
                    case 'ocupado':    return '#f59e0b'; // ámbar (más agradable que el naranja chillón)
                    case 'descanso':   return '#64748b';
                    default:           return '#94a3b8';
                }
            }

            // 🛵 Marcador estilo Waze: insignia circular limpia con ring blanco,
            // degradado por estado, sombra suave y una colita inferior discreta.
            function gradPorEstado(estado) {
                switch(estado) {
                    case 'disponible': return ['#34d399', '#10b981'];
                    case 'en_ruta':    return ['#60a5fa', '#3b82f6'];
                    case 'ocupado':    return ['#fbbf24', '#f59e0b'];
                    case 'descanso':   return ['#94a3b8', '#64748b'];
                    default:           return ['#cbd5e1', '#94a3b8'];
                }
            }
            function svgMoto(estado) {
                const [c1, c2] = gradPorEstado(estado);
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
  <!-- colita -->
  <path d="M25 57 L18.5 43 H31.5 Z" fill="#ffffff" filter="url(#s)"/>
  <!-- ring blanco -->
  <circle cx="25" cy="23" r="20" fill="#ffffff" filter="url(#s)"/>
  <!-- círculo de color -->
  <circle cx="25" cy="23" r="16" fill="url(#g)"/>
  <!-- moto -->
  <text x="25" y="31" font-size="20" text-anchor="middle">🛵</text>
</svg>`;
                return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
            }

            function svgPing(estado) {
                const color = colorPorEstado(estado);
                const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 72 72">
  <circle cx="36" cy="36" r="12" fill="${color}" opacity="0.28">
    <animate attributeName="r" from="12" to="30" dur="2s" repeatCount="indefinite"/>
    <animate attributeName="opacity" from="0.35" to="0" dur="2s" repeatCount="indefinite"/>
  </circle>
</svg>`;
                return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
            }

            // Marker para sede (origen) — casa verde
            function svgSede() {
                const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="40" height="52" viewBox="0 0 40 52">
  <path fill="#059669" stroke="white" stroke-width="2.5"
        d="M20 2C10 2 2 10 2 20c0 14 18 30 18 30s18-16 18-30C38 10 30 2 20 2z"/>
  <circle cx="20" cy="20" r="11" fill="white"/>
  <text x="20" y="26" font-size="14" text-anchor="middle">🏪</text>
</svg>`;
                return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
            }

            // Marker para destino (cliente)
            function svgDestino() {
                const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="40" height="52" viewBox="0 0 40 52">
  <path fill="#ef4444" stroke="white" stroke-width="2.5"
        d="M20 2C10 2 2 10 2 20c0 14 18 30 18 30s18-16 18-30C38 10 30 2 20 2z"/>
  <circle cx="20" cy="20" r="11" fill="white"/>
  <text x="20" y="26" font-size="14" text-anchor="middle">🏠</text>
</svg>`;
                return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
            }

            function renderDomi(d) {
                if (!d.lat || !d.lng) return;
                const state = window.mapaDomiState;
                const map = state.map;

                const iconMoto = {
                    url: svgMoto(d.estado),
                    scaledSize: new google.maps.Size(50, 60),
                    anchor: new google.maps.Point(25, 57),
                };
                const iconPing = {
                    url: svgPing(d.estado),
                    scaledSize: new google.maps.Size(72, 72),
                    anchor: new google.maps.Point(36, 50),
                };

                // Si ya existe → animar movimiento
                if (state.markers[d.id]) {
                    const entry = state.markers[d.id];
                    const nuevaPos = new google.maps.LatLng(d.lat, d.lng);
                    animarMarcador(entry.marker, nuevaPos);
                    animarMarcador(entry.pulse, nuevaPos);
                    entry.marker.setIcon(iconMoto);
                    entry.pulse.setIcon(iconPing);
                    renderRuta(d, entry);
                    return;
                }

                const pulse = new google.maps.Marker({
                    position: { lat: d.lat, lng: d.lng },
                    map, icon: iconPing,
                    clickable: false, zIndex: 1,
                });
                const marker = new google.maps.Marker({
                    position: { lat: d.lat, lng: d.lng },
                    map, title: d.nombre, icon: iconMoto, zIndex: 100,
                });

                const iw = new google.maps.InfoWindow();
                marker.addListener('click', () => {
                    let rutaInfo = '';
                    if (d.ruta) {
                        const e = window.mapaDomiState.markers[d.id]?.ruta;
                        const eta = e?.etaTexto ? `<div style="margin-top:4px;font-size:11px;color:${colorPorEstado(d.estado)};font-weight:800;">🛵 ${e.distTexto} · ETA ${e.etaTexto}</div>` : '';
                        rutaInfo = `
                            <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e2e8f0;font-size:11px;">
                                <div style="color:#059669;font-weight:700;">🏪 ${d.ruta.origen_nombre}</div>
                                <div style="color:#ef4444;font-weight:700;margin-top:2px;">🏠 ${d.ruta.cliente}</div>
                                <div style="color:#64748b;margin-top:2px;">${d.ruta.direccion || ''}</div>
                                ${eta}
                            </div>`;
                    }
                    iw.setContent(`
                        <div style="font-family:system-ui,sans-serif;padding:4px;min-width:240px;">
                            <div style="font-weight:800;font-size:15px;color:#0f172a;">${d.nombre}</div>
                            <div style="font-size:12px;color:#64748b;">${d.placa || d.vehiculo || ''}</div>
                            <div style="margin-top:6px;">
                                <span style="background:${colorPorEstado(d.estado)};color:white;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">${(d.estado||'').replace('_',' ')}</span>
                            </div>
                            ${d.telefono ? `<a href="tel:${d.telefono}" style="display:inline-block;margin-top:8px;font-size:13px;color:#10b981;font-weight:700;">📞 ${d.telefono}</a>` : ''}
                            ${rutaInfo}
                            <div style="font-size:10px;color:#94a3b8;margin-top:6px;">Actualizado: ${d.updated_at ? new Date(d.updated_at).toLocaleString('es-CO') : '—'}</div>
                        </div>
                    `);
                    iw.open({ anchor: marker, map });
                });

                const entry = { marker, pulse, ruta: null };
                state.markers[d.id] = entry;
                renderRuta(d, entry);
            }

            // Servicio compartido de Directions
            function dirSvc() {
                if (!window.__dirSvc) window.__dirSvc = new google.maps.DirectionsService();
                return window.__dirSvc;
            }

            function renderRuta(d, entry) {
                // Limpiar ruta anterior
                if (entry.ruta) {
                    if (entry.ruta.origen) entry.ruta.origen.setMap(null);
                    if (entry.ruta.destino) entry.ruta.destino.setMap(null);
                    if (entry.ruta.rendererPlan) entry.ruta.rendererPlan.setMap(null);
                    if (entry.ruta.rendererReal) entry.ruta.rendererReal.setMap(null);
                    entry.ruta = null;
                }
                if (!d.ruta || !d.ruta.destino_lat || !d.ruta.destino_lng) return;

                const map = window.mapaDomiState.map;
                const ruta = { etaTexto: '', distTexto: '' };
                const color = colorPorEstado(d.estado);

                // Marker destino (casa cliente)
                ruta.destino = new google.maps.Marker({
                    position: { lat: d.ruta.destino_lat, lng: d.ruta.destino_lng },
                    map,
                    title: d.ruta.cliente + ' · ' + (d.ruta.direccion || ''),
                    icon: { url: svgDestino(), scaledSize: new google.maps.Size(40, 52), anchor: new google.maps.Point(20, 52) },
                    zIndex: 50,
                });

                // Marker sede (origen)
                if (d.ruta.origen_lat && d.ruta.origen_lng) {
                    ruta.origen = new google.maps.Marker({
                        position: { lat: d.ruta.origen_lat, lng: d.ruta.origen_lng },
                        map,
                        title: d.ruta.origen_nombre,
                        icon: { url: svgSede(), scaledSize: new google.maps.Size(40, 52), anchor: new google.maps.Point(20, 52) },
                        zIndex: 50,
                    });

                    // 🗺️ RUTA PLANEADA: sede → destino siguiendo calles (Directions API)
                    ruta.rendererPlan = new google.maps.DirectionsRenderer({
                        map,
                        suppressMarkers: true, // markers ya los pusimos
                        preserveViewport: true,
                        polylineOptions: {
                            strokeColor: '#94a3b8',
                            strokeOpacity: 0.55,
                            strokeWeight: 5,
                        },
                    });
                    dirSvc().route({
                        origin: { lat: d.ruta.origen_lat, lng: d.ruta.origen_lng },
                        destination: { lat: d.ruta.destino_lat, lng: d.ruta.destino_lng },
                        travelMode: google.maps.TravelMode.DRIVING,
                    }, (res, st) => {
                        if (st === 'OK') ruta.rendererPlan.setDirections(res);
                    });
                }

                // 🏍️ RUTA REAL en curso: posición actual del domi → destino siguiendo calles
                ruta.rendererReal = new google.maps.DirectionsRenderer({
                    map,
                    suppressMarkers: true,
                    preserveViewport: true,
                    polylineOptions: {
                        strokeColor: color,
                        strokeOpacity: 0.95,
                        strokeWeight: 6,
                    },
                });
                dirSvc().route({
                    origin: { lat: d.lat, lng: d.lng },
                    destination: { lat: d.ruta.destino_lat, lng: d.ruta.destino_lng },
                    travelMode: google.maps.TravelMode.DRIVING,
                }, (res, st) => {
                    if (st === 'OK') {
                        ruta.rendererReal.setDirections(res);
                        // Capturar distancia y ETA
                        const leg = res.routes[0]?.legs[0];
                        if (leg) {
                            ruta.distTexto = leg.distance?.text || '';
                            ruta.etaTexto = leg.duration?.text || '';
                            // Actualizar status bar con la ETA si este es el único en ruta
                            const status = document.getElementById('mapaStatus');
                            if (status) {
                                status.innerHTML = `🟢 ${d.nombre} → ${ruta.distTexto} · ETA ${ruta.etaTexto}`;
                            }
                        }
                    }
                });

                entry.ruta = ruta;
            }

            function animarMarcador(marker, nuevaPos) {
                if (!marker) return;
                const start = marker.getPosition();
                if (!start) { marker.setPosition(nuevaPos); return; }
                const sLat = start.lat(), sLng = start.lng();
                const eLat = nuevaPos.lat(), eLng = nuevaPos.lng();
                const dur = 1000, t0 = performance.now();
                (function frame(t) {
                    const k = Math.min(1, (t - t0) / dur);
                    marker.setPosition({ lat: sLat + (eLat - sLat) * k, lng: sLng + (eLng - sLng) * k });
                    if (k < 1) requestAnimationFrame(frame);
                })(t0);
            }

            function ajustarVista() {
                const entries = Object.values(window.mapaDomiState.markers);
                if (entries.length === 0) return;
                const bounds = new google.maps.LatLngBounds();
                entries.forEach(e => {
                    bounds.extend(e.marker.getPosition());
                    if (e.ruta && e.ruta.destino) bounds.extend(e.ruta.destino.getPosition());
                    if (e.ruta && e.ruta.origen) bounds.extend(e.ruta.origen.getPosition());
                });
                if (entries.length === 1 && !entries[0].ruta) {
                    window.mapaDomiState.map.panTo(entries[0].marker.getPosition());
                    window.mapaDomiState.map.setZoom(16);
                } else {
                    window.mapaDomiState.map.fitBounds(bounds, 100);
                }
            }

            function quitarMarkersFantasma(idsActivos) {
                Object.keys(window.mapaDomiState.markers).forEach(id => {
                    if (!idsActivos.includes(parseInt(id))) {
                        const entry = window.mapaDomiState.markers[id];
                        if (entry.marker) entry.marker.setMap(null);
                        if (entry.pulse) entry.pulse.setMap(null);
                        if (entry.ruta) {
                            ['origen','destino','rendererPlan','rendererReal'].forEach(k => entry.ruta[k] && entry.ruta[k].setMap(null));
                        }
                        delete window.mapaDomiState.markers[id];
                    }
                });
            }

            function conectarLiveSocket() {
                if (typeof window.Echo === 'undefined') {
                    document.getElementById('mapaStatus').innerHTML = '⚠️ Reverb no disponible';
                    return;
                }
                window.Echo.channel(`domiciliarios.tenant.${window.mapaDomiState.tenantId}`)
                    .listen('.domiciliario.ubicacion', (data) => {
                        renderDomi(data);
                        document.getElementById('mapaStatus').innerHTML =
                            `🟢 Tiempo real · ${data.nombre} movió ${new Date().toLocaleTimeString('es-CO')}`;
                    });
                setInterval(() => window.Livewire && window.Livewire.dispatch('$refresh'), 30000);
            }

            document.addEventListener('livewire:updated', () => {
                if (!window.mapaDomiState.map) return;
                const fresh = @json($domiciliarios);
                const ids = fresh.map(d => d.id);
                quitarMarkersFantasma(ids);
                fresh.forEach(renderDomi);
            });
        </script>
    @endif
</div>
