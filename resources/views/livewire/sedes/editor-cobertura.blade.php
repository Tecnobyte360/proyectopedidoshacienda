<div class="px-6 lg:px-10 py-8">

    <div class="mb-6">
        <a href="{{ route('sedes.index') }}" class="text-xs text-slate-500 hover:text-slate-800">
            <i class="fa-solid fa-arrow-left mr-1"></i> Volver a sedes
        </a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-slate-800">
                    <i class="fa-solid fa-map-location-dot text-blue-600 mr-2"></i>
                    Cobertura de: {{ $sede->nombre }}
                </h2>
                <p class="text-sm text-slate-500">
                    Dibuja el área que cubre esta sede para domicilios. El bot la usará automáticamente cuando un cliente dé su dirección.
                </p>
            </div>
            <button wire:click="guardar"
                    wire:loading.attr="disabled" wire:target="guardar"
                    class="rounded-2xl bg-emerald-600 hover:bg-emerald-700 px-5 py-3 text-white font-bold shadow disabled:opacity-50">
                <span wire:loading.remove wire:target="guardar">
                    <i class="fa-solid fa-save mr-2"></i> Guardar polígono
                </span>
                <span wire:loading wire:target="guardar">
                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Guardando...
                </span>
            </button>
        </div>
    </div>

    @if (!$gmapsActivo)
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-6 text-center">
            <i class="fa-solid fa-triangle-exclamation text-3xl text-amber-600 mb-3"></i>
            <h3 class="text-lg font-bold text-amber-800 mb-1">Google Maps no está activo</h3>
            <p class="text-sm text-amber-700 mb-3">
                Para usar este editor visual, ve a <strong>/admin/tenants</strong>, edita el tenant y configura tu API Key de Google Maps.
            </p>
            <a href="{{ route('admin.tenants.index') }}"
               class="inline-block rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 text-sm font-bold">
                <i class="fa-solid fa-gear mr-1"></i> Ir a configuración
            </a>
        </div>
    @else
        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="rounded-xl bg-white p-3 shadow border border-slate-200">
                <div class="text-[11px] text-slate-500">Zonas / vértices</div>
                <div class="text-xl font-extrabold text-slate-800" id="gmaps-sede-conteo-zonas">
                    —
                </div>
            </div>
            <div class="rounded-xl bg-white p-3 shadow border border-slate-200">
                <div class="text-[11px] text-slate-500">Centro lat / lng</div>
                <div class="text-xs font-mono font-bold text-slate-800">
                    {{ $cobertura_centro_lat ? number_format($cobertura_centro_lat, 5) : '—' }}, {{ $cobertura_centro_lng ? number_format($cobertura_centro_lng, 5) : '—' }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-3 shadow border border-slate-200">
                <div class="text-[11px] text-slate-500">Área aproximada</div>
                <div class="text-xl font-extrabold text-slate-800">
                    {{ $area_km2 ? number_format($area_km2, 2) . ' km²' : '—' }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-3 shadow border border-slate-200">
                <div class="text-[11px] text-slate-500">Costo / Tiempo</div>
                <div class="text-xs font-bold text-slate-800">
                    ${{ number_format($sede->cobertura_costo_envio ?? 0, 0, ',', '.') }} · {{ $sede->cobertura_tiempo_min ?? 45 }} min
                </div>
            </div>
        </div>

        {{-- 🔍 Buscador inteligente de áreas administrativas --}}
        <div class="rounded-2xl bg-white border-2 border-blue-200 p-4 mb-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <i class="fa-solid fa-magnifying-glass-location text-blue-600 text-lg"></i>
                <h3 class="text-sm font-bold text-slate-800">Buscar y dibujar área automáticamente</h3>
            </div>
            <p class="text-xs text-slate-600 mb-3">
                Escribe el nombre de un barrio, ciudad, departamento, área metropolitana o <strong>país completo</strong> (ej: <em>"Niquía"</em>, <em>"Bello"</em>, <em>"Antioquia"</em>, <em>"Colombia"</em>, <em>"México"</em>). Si OpenStreetMap tiene su polígono administrativo, se dibuja automáticamente.
            </p>

            <div class="flex gap-2 items-center">
                <input type="text" id="gmaps-busqueda-area"
                       placeholder="Ej: Niquía, Bello, Antioquia, Colombia, México..."
                       class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();gmapsSedeBuscarArea();}">
                <button type="button" onclick="gmapsSedeBuscarArea()"
                        id="gmaps-btn-buscar"
                        class="rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-bold whitespace-nowrap shadow disabled:opacity-50">
                    <i class="fa-solid fa-search mr-1"></i> Buscar y dibujar
                </button>
            </div>

            <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                <input type="checkbox" id="gmaps-busqueda-mundial" class="rounded border-slate-300 text-blue-600">
                <span>🌎 Buscar en todo el mundo (no solo Colombia)</span>
            </label>

            <div id="gmaps-busqueda-resultados" class="mt-2 hidden">
                <p class="text-[11px] text-slate-500 mb-1">Encontré varios. Click en uno para dibujarlo:</p>
                <div id="gmaps-resultados-lista" class="space-y-1"></div>
            </div>

            <div id="gmaps-busqueda-error" class="mt-2 hidden rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr,280px] gap-3">
            <div>
                {{-- 🔒 wire:ignore es CRÍTICO: evita que Livewire borre el mapa al re-renderizar --}}
                <div wire:ignore id="gmaps-sede-editor-wrap">
                    <div id="gmaps-sede-editor" style="height: 65vh; width: 100%; border-radius: 1rem; border: 1px solid #cbd5e1;"></div>
                </div>
                <div id="gmaps-sede-status" class="mt-2 text-xs text-slate-500 font-mono"></div>
            </div>

            {{-- 🗂️ Panel lateral de zonas --}}
            <div class="rounded-2xl bg-white border-2 border-slate-200 p-3 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-bold text-slate-800">
                        <i class="fa-solid fa-layer-group text-emerald-600 mr-1"></i> Zonas
                    </h3>
                    <button type="button" onclick="gmapsSedeNuevaZona()"
                            class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 text-[11px] font-bold">
                        <i class="fa-solid fa-plus mr-1"></i> Nueva zona
                    </button>
                </div>
                <p class="text-[11px] text-slate-500 mb-2">
                    Puedes combinar varias áreas (ej: Bello + Envigado + Sabaneta) o un país completo.
                </p>
                <div wire:ignore id="gmaps-sede-zonas-lista" class="space-y-1 max-h-[55vh] overflow-y-auto">
                    <p class="text-[11px] text-slate-400 italic" id="gmaps-zonas-empty">Sin zonas aún. Usa el buscador o el lápiz del mapa.</p>
                </div>
            </div>
        </div>

        <script src="https://maps.googleapis.com/maps/api/js?key={{ $config['api_key'] }}&libraries=drawing,geometry&language=es&region=CO"
                defer
                onload="initGoogleMapsSedeEditor()"></script>

        <script>
            window.gmapsSedeState = {
                polygons: [],     // ✨ multi-zona: array de google.maps.Polygon
                nombresZonas: [], // labels paralelos a polygons
                drawingManager: null,
                map: null,
                poligonoInicial: @json($cobertura_poligono),
                color: @json($color),
                centroDefault: { lat: {{ $config['centro_lat'] }}, lng: {{ $config['centro_lng'] }} },
                zoomDefault: {{ $config['zoom'] }},
            };

            function gmapsSedeSetStatus(msg) {
                const el = document.getElementById('gmaps-sede-status');
                if (el) el.textContent = msg;
            }

            // Convierte un google.maps.Polygon a [[lat,lng],...]
            function gmapsSedePathToCoords(polygon) {
                const coords = [];
                polygon.getPath().forEach(latlng => coords.push([latlng.lat(), latlng.lng()]));
                if (coords.length > 0) {
                    const a = coords[0], b = coords[coords.length-1];
                    if (a[0] !== b[0] || a[1] !== b[1]) coords.push(a);
                }
                return coords;
            }

            // Recopila TODAS las zonas y las envía a Livewire (estructura multi).
            function gmapsSedeSyncEstado() {
                const state = window.gmapsSedeState;
                const polygons = state.polygons.map(p => gmapsSedePathToCoords(p)).filter(c => c.length >= 3);

                // Centro = promedio de todos los puntos de todas las zonas
                let totalLat = 0, totalLng = 0, totalPts = 0;
                polygons.forEach(coords => coords.forEach(c => { totalLat += c[0]; totalLng += c[1]; totalPts++; }));
                const center = totalPts > 0 ? { lat: totalLat/totalPts, lng: totalLng/totalPts } : { lat: 0, lng: 0 };

                // Área total
                let areaKm2 = 0;
                try {
                    state.polygons.forEach(p => {
                        areaKm2 += google.maps.geometry.spherical.computeArea(p.getPath()) / 1_000_000;
                    });
                } catch (e) {}

                const totalVerts = polygons.reduce((acc, c) => acc + c.length, 0);
                const cnt = document.getElementById('gmaps-sede-conteo-zonas');
                if (cnt) cnt.textContent = polygons.length + ' / ' + totalVerts;

                gmapsSedeSetStatus(`✓ ${polygons.length} zona(s) · ${totalVerts} vértices · ${areaKm2.toFixed(2)} km²`);
                gmapsSedeRenderListaZonas();

                @this.actualizarPoligono({
                    polygons: polygons,
                    center: center,
                    area_km2: areaKm2,
                });
            }

            // Renderiza el panel lateral con cada zona y un botón eliminar.
            function gmapsSedeRenderListaZonas() {
                const state = window.gmapsSedeState;
                const lista = document.getElementById('gmaps-sede-zonas-lista');
                const empty = document.getElementById('gmaps-zonas-empty');
                if (!lista) return;

                if (state.polygons.length === 0) {
                    lista.innerHTML = '<p class="text-[11px] text-slate-400 italic">Sin zonas aún. Usa el buscador o el lápiz del mapa.</p>';
                    return;
                }

                lista.innerHTML = '';
                state.polygons.forEach((p, idx) => {
                    const nombre = state.nombresZonas[idx] || `Zona ${idx+1}`;
                    const pts = p.getPath().getLength();
                    const item = document.createElement('div');
                    item.className = 'rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 flex items-center justify-between gap-2 hover:bg-slate-100';
                    item.innerHTML = `
                        <div class="flex-1 min-w-0">
                            <div class="text-[11px] font-bold text-slate-800 truncate" title="${nombre}">
                                <span class="inline-block w-2 h-2 rounded-full mr-1" style="background:${state.color}"></span>
                                ${nombre}
                            </div>
                            <div class="text-[10px] text-slate-500">${pts} pts</div>
                        </div>
                        <button type="button" class="text-rose-600 hover:text-rose-800 text-xs" title="Eliminar zona">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                    item.querySelector('button').onclick = () => gmapsSedeEliminarZona(idx);
                    item.querySelector('div.flex-1').onclick = () => {
                        // Centrar el mapa en esta zona
                        const bounds = new google.maps.LatLngBounds();
                        p.getPath().forEach(ll => bounds.extend(ll));
                        state.map.fitBounds(bounds);
                    };
                    lista.appendChild(item);
                });
            }

            function gmapsSedeEliminarZona(idx) {
                const state = window.gmapsSedeState;
                if (!state.polygons[idx]) return;
                state.polygons[idx].setMap(null);
                state.polygons.splice(idx, 1);
                state.nombresZonas.splice(idx, 1);
                gmapsSedeSyncEstado();
            }

            // Activa el drawing manager para dibujar UNA nueva zona manual.
            function gmapsSedeNuevaZona() {
                const state = window.gmapsSedeState;
                if (!state.drawingManager) return;
                state.drawingManager.setDrawingMode(google.maps.drawing.OverlayType.POLYGON);
                gmapsSedeSetStatus('✏️ Modo dibujo activo. Haz clic en el mapa para trazar la nueva zona.');
            }

            // Conecta listeners a un Polygon para que ediciones disparen sync.
            function gmapsSedeConectarListeners(polygon) {
                const onChange = () => gmapsSedeSyncEstado();
                google.maps.event.addListener(polygon.getPath(), 'set_at', onChange);
                google.maps.event.addListener(polygon.getPath(), 'insert_at', onChange);
                google.maps.event.addListener(polygon.getPath(), 'remove_at', onChange);
            }

            // Agrega un nuevo polígono al state.
            function gmapsSedeAgregarPoligono(coords, nombre = null) {
                const state = window.gmapsSedeState;
                if (!coords || coords.length < 3) return null;

                const path = coords.map(c =>
                    Array.isArray(c) ? { lat: c[0], lng: c[1] } : c
                );

                const poly = new google.maps.Polygon({
                    paths: path,
                    editable: true,
                    draggable: false,
                    strokeColor: state.color,
                    strokeOpacity: 0.9,
                    strokeWeight: 2,
                    fillColor: state.color,
                    fillOpacity: 0.25,
                });
                poly.setMap(state.map);
                gmapsSedeConectarListeners(poly);

                state.polygons.push(poly);
                state.nombresZonas.push(nombre || `Zona ${state.polygons.length}`);
                return poly;
            }

            function initGoogleMapsSedeEditor() {
                const state = window.gmapsSedeState;

                state.map = new google.maps.Map(document.getElementById('gmaps-sede-editor'), {
                    center: state.centroDefault,
                    zoom: state.zoomDefault,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true,
                });

                // Cargar polígono(s) inicial(es). Soporta:
                //   - legacy: [[lat,lng],...]            (un solo polígono)
                //   - multi:  [[[lat,lng],...], ...]     (varias zonas)
                if (state.poligonoInicial && Array.isArray(state.poligonoInicial) && state.poligonoInicial.length > 0) {
                    const primero = state.poligonoInicial[0];
                    const esMulti = Array.isArray(primero) && Array.isArray(primero[0]);

                    const todasLasZonas = esMulti ? state.poligonoInicial : [state.poligonoInicial];
                    const boundsTotales = new google.maps.LatLngBounds();

                    todasLasZonas.forEach((coords, i) => {
                        if (Array.isArray(coords) && coords.length >= 3) {
                            gmapsSedeAgregarPoligono(coords, `Zona ${i+1}`);
                            coords.forEach(c => boundsTotales.extend({ lat: c[0], lng: c[1] }));
                        }
                    });

                    if (state.polygons.length > 0) {
                        state.map.fitBounds(boundsTotales);
                        gmapsSedeSetStatus(`Cargada(s) ${state.polygons.length} zona(s).`);
                    }
                } else {
                    gmapsSedeSetStatus('Sin zonas. Busca un lugar o usa el lápiz para dibujar.');
                }

                state.drawingManager = new google.maps.drawing.DrawingManager({
                    drawingMode: state.polygons.length === 0 ? google.maps.drawing.OverlayType.POLYGON : null,
                    drawingControl: true,
                    drawingControlOptions: {
                        position: google.maps.ControlPosition.TOP_LEFT,
                        drawingModes: [google.maps.drawing.OverlayType.POLYGON],
                    },
                    polygonOptions: {
                        editable: true,
                        draggable: false,
                        strokeColor: state.color,
                        strokeOpacity: 0.9,
                        strokeWeight: 2,
                        fillColor: state.color,
                        fillOpacity: 0.25,
                    },
                });
                state.drawingManager.setMap(state.map);

                // Cuando termina de dibujar manual → AGREGA (no reemplaza)
                google.maps.event.addListener(state.drawingManager, 'polygoncomplete', (poly) => {
                    state.polygons.push(poly);
                    state.nombresZonas.push(`Zona ${state.polygons.length}`);
                    gmapsSedeConectarListeners(poly);
                    state.drawingManager.setDrawingMode(null);
                    gmapsSedeSyncEstado();
                });

                // Render inicial
                gmapsSedeRenderListaZonas();
                if (state.polygons.length > 0) gmapsSedeSyncEstado();
            }

            // ═══════════════════════════════════════════════════════════════
            // 🔍 BUSCADOR INTELIGENTE: escribe nombre → dibuja polígono auto
            // Usa Nominatim (OpenStreetMap) que es gratis y devuelve polígonos
            // administrativos para barrios/ciudades/áreas conocidas.
            // ═══════════════════════════════════════════════════════════════

            async function gmapsSedeBuscarArea() {
                const input = document.getElementById('gmaps-busqueda-area');
                const btn = document.getElementById('gmaps-btn-buscar');
                const errBox = document.getElementById('gmaps-busqueda-error');
                const listBox = document.getElementById('gmaps-busqueda-resultados');
                const lista = document.getElementById('gmaps-resultados-lista');

                const query = input.value.trim();
                if (!query) {
                    errBox.classList.remove('hidden');
                    errBox.textContent = '⚠️ Escribe el nombre de un lugar.';
                    return;
                }

                errBox.classList.add('hidden');
                listBox.classList.add('hidden');
                lista.innerHTML = '';
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Buscando...';

                try {
                    // Buscar en Nominatim con polygon_geojson=1 para obtener forma
                    const mundial = document.getElementById('gmaps-busqueda-mundial')?.checked;
                    // Heurística: si la consulta parece un país (ej: "colombia", "mexico"),
                    // o el usuario activó búsqueda mundial → no restringir a Colombia.
                    const paisesComunes = ['colombia','mexico','méxico','peru','perú','chile','argentina','ecuador','venezuela','panama','panamá','costa rica','guatemala','honduras','nicaragua','el salvador','cuba','republica dominicana','república dominicana','bolivia','paraguay','uruguay','brasil','brazil','estados unidos','usa','united states','españa','spain','francia','france','italia','italy','alemania','germany','portugal','reino unido','uk'];
                    const pareceQueriesPais = paisesComunes.some(p => query.toLowerCase() === p);

                    const url = new URL('https://nominatim.openstreetmap.org/search');
                    url.searchParams.set('q', mundial || pareceQueriesPais ? query : query + ', Colombia');
                    url.searchParams.set('format', 'json');
                    url.searchParams.set('polygon_geojson', '1');
                    // Simplificar polígonos grandes (países tienen miles de puntos).
                    // 0.01 ≈ ~1km de tolerancia, suficiente para cobertura de envíos.
                    url.searchParams.set('polygon_threshold', '0.01');
                    url.searchParams.set('limit', '5');
                    if (!mundial && !pareceQueriesPais) {
                        url.searchParams.set('countrycodes', 'co');
                    }

                    const resp = await fetch(url.toString(), {
                        headers: { 'Accept-Language': 'es' }
                    });
                    if (!resp.ok) throw new Error('Error consultando OpenStreetMap');
                    const datos = await resp.json();

                    if (!datos || datos.length === 0) {
                        errBox.classList.remove('hidden');
                        errBox.textContent = '😔 No encontré resultados para "' + query + '". Prueba con otro nombre o dibújalo manualmente con la herramienta del lápiz.';
                        return;
                    }

                    // Si solo hay 1 resultado, aplicar directo
                    if (datos.length === 1) {
                        gmapsSedeAplicarLugar(datos[0]);
                        return;
                    }

                    // Varios → mostrar opciones
                    listBox.classList.remove('hidden');
                    datos.forEach((d, idx) => {
                        const tienePol = d.geojson && (d.geojson.type === 'Polygon' || d.geojson.type === 'MultiPolygon');
                        const btnRes = document.createElement('button');
                        btnRes.type = 'button';
                        btnRes.className = 'w-full text-left rounded-lg border ' + (tienePol ? 'border-blue-200 bg-blue-50 hover:bg-blue-100' : 'border-slate-200 bg-slate-50 hover:bg-slate-100') + ' px-3 py-2 text-xs transition';
                        btnRes.innerHTML = `
                            <div class="font-semibold text-slate-800">${d.display_name}</div>
                            <div class="text-[10px] text-slate-500 mt-0.5">
                                ${d.type || ''} ${d.class ? '· ' + d.class : ''}
                                ${tienePol ? '<span class="text-blue-600 font-bold">· ✓ Tiene polígono</span>' : '<span class="text-amber-600">· solo punto</span>'}
                            </div>
                        `;
                        btnRes.onclick = () => gmapsSedeAplicarLugar(d);
                        lista.appendChild(btnRes);
                    });
                } catch (e) {
                    errBox.classList.remove('hidden');
                    errBox.textContent = '❌ Error: ' + e.message;
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-search mr-1"></i> Buscar y dibujar';
                }
            }

            function gmapsSedeAplicarLugar(lugar) {
                const state = window.gmapsSedeState;
                document.getElementById('gmaps-busqueda-resultados').classList.add('hidden');

                if (!lugar.geojson) {
                    // Solo es un punto, centrar el mapa
                    if (lugar.lat && lugar.lon) {
                        state.map.setCenter({ lat: parseFloat(lugar.lat), lng: parseFloat(lugar.lon) });
                        state.map.setZoom(14);
                    }
                    gmapsSedeSetStatus('📍 Centrado en ' + lugar.display_name + ' (sin polígono administrativo, dibújalo manualmente).');
                    return;
                }

                // Convertir GeoJSON a path Google Maps [{lat,lng}]
                let coords = [];

                if (lugar.geojson.type === 'Polygon') {
                    // Polígono simple — coords[0] es el anillo exterior
                    coords = lugar.geojson.coordinates[0].map(c => ({ lat: c[1], lng: c[0] }));
                } else if (lugar.geojson.type === 'MultiPolygon') {
                    // MultiPolygon — tomar el polígono más grande (mayor área aproximada)
                    let mayor = lugar.geojson.coordinates[0];
                    let mayorPts = mayor[0].length;
                    for (const p of lugar.geojson.coordinates) {
                        if (p[0].length > mayorPts) {
                            mayor = p;
                            mayorPts = p[0].length;
                        }
                    }
                    coords = mayor[0].map(c => ({ lat: c[1], lng: c[0] }));
                } else {
                    gmapsSedeSetStatus('⚠️ El lugar no tiene polígono dibujable.');
                    return;
                }

                if (coords.length < 3) {
                    gmapsSedeSetStatus('⚠️ El polígono recibido es inválido.');
                    return;
                }

                // ✨ AGREGAR (no reemplazar) → soporta múltiples zonas combinadas
                const nombreCorto = (lugar.display_name || 'Zona').split(',').slice(0, 2).join(',').trim();
                const coordsArr = coords.map(c => [c.lat, c.lng]);
                gmapsSedeAgregarPoligono(coordsArr, nombreCorto);

                state.drawingManager.setDrawingMode(null);

                // Centrar en el polígono recién agregado
                const bounds = new google.maps.LatLngBounds();
                coords.forEach(p => bounds.extend(p));
                state.map.fitBounds(bounds);

                gmapsSedeSetStatus('✅ "' + nombreCorto + '" agregado como zona ' + state.polygons.length + ' (' + coords.length + ' pts). Busca otra para combinar o guarda.');

                // Persistir en Livewire (todas las zonas)
                gmapsSedeSyncEstado();
            }
        </script>
    @endif
</div>
