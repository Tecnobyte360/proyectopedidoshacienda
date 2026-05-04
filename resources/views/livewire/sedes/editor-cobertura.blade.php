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
                <div class="text-[11px] text-slate-500">Vértices</div>
                <div class="text-xl font-extrabold text-slate-800">
                    {{ count($cobertura_poligono ?? []) }}
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
                Escribe el nombre de un barrio, ciudad o área (ej: <em>"Niquía"</em>, <em>"Área Metropolitana del Valle de Aburrá"</em>, <em>"Bello"</em>). Si OpenStreetMap tiene su polígono administrativo, se dibuja automáticamente.
            </p>

            <div class="flex gap-2">
                <input type="text" id="gmaps-busqueda-area"
                       placeholder="Ej: Niquía, Bello, Área Metropolitana..."
                       class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();gmapsSedeBuscarArea();}">
                <button type="button" onclick="gmapsSedeBuscarArea()"
                        id="gmaps-btn-buscar"
                        class="rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-bold whitespace-nowrap shadow disabled:opacity-50">
                    <i class="fa-solid fa-search mr-1"></i> Buscar y dibujar
                </button>
            </div>

            <div id="gmaps-busqueda-resultados" class="mt-2 hidden">
                <p class="text-[11px] text-slate-500 mb-1">Encontré varios. Click en uno para dibujarlo:</p>
                <div id="gmaps-resultados-lista" class="space-y-1"></div>
            </div>

            <div id="gmaps-busqueda-error" class="mt-2 hidden rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700"></div>
        </div>

        <div id="gmaps-sede-editor" style="height: 65vh; width: 100%; border-radius: 1rem; border: 1px solid #cbd5e1;"></div>
        <div id="gmaps-sede-status" class="mt-2 text-xs text-slate-500 font-mono"></div>

        <script src="https://maps.googleapis.com/maps/api/js?key={{ $config['api_key'] }}&libraries=drawing,geometry&language=es&region=CO"
                defer
                onload="initGoogleMapsSedeEditor()"></script>

        <script>
            window.gmapsSedeState = {
                polygon: null,
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

            function gmapsSedeCalcularYEnviar(polygon) {
                const path = polygon.getPath();
                const coords = [];
                path.forEach(latlng => coords.push([latlng.lat(), latlng.lng()]));

                if (coords.length > 0 && (coords[0][0] !== coords[coords.length-1][0] || coords[0][1] !== coords[coords.length-1][1])) {
                    coords.push(coords[0]);
                }

                const center = coords.reduce((acc, c) => ({ lat: acc.lat + c[0]/coords.length, lng: acc.lng + c[1]/coords.length }), { lat: 0, lng: 0 });

                let areaKm2 = 0;
                try {
                    const m2 = google.maps.geometry.spherical.computeArea(path);
                    areaKm2 = m2 / 1_000_000;
                } catch (e) {}

                gmapsSedeSetStatus(`✓ Polígono: ${coords.length} vértices · Área: ${areaKm2.toFixed(2)} km²`);

                @this.actualizarPoligono({
                    coordinates: coords,
                    center: center,
                    area_km2: areaKm2,
                });
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

                if (state.poligonoInicial && Array.isArray(state.poligonoInicial) && state.poligonoInicial.length >= 3) {
                    const path = state.poligonoInicial.map(p => ({ lat: p[0], lng: p[1] }));
                    state.polygon = new google.maps.Polygon({
                        paths: path,
                        editable: true,
                        draggable: true,
                        strokeColor: state.color,
                        strokeOpacity: 0.9,
                        strokeWeight: 2,
                        fillColor: state.color,
                        fillOpacity: 0.25,
                    });
                    state.polygon.setMap(state.map);

                    const bounds = new google.maps.LatLngBounds();
                    path.forEach(p => bounds.extend(p));
                    state.map.fitBounds(bounds);

                    google.maps.event.addListener(state.polygon.getPath(), 'set_at', () => gmapsSedeCalcularYEnviar(state.polygon));
                    google.maps.event.addListener(state.polygon.getPath(), 'insert_at', () => gmapsSedeCalcularYEnviar(state.polygon));
                    google.maps.event.addListener(state.polygon.getPath(), 'remove_at', () => gmapsSedeCalcularYEnviar(state.polygon));

                    gmapsSedeSetStatus('Polígono cargado. Arrastra los puntos para editarlo.');
                } else {
                    gmapsSedeSetStatus('Sin polígono. Usa la herramienta del lápiz para dibujar.');
                }

                state.drawingManager = new google.maps.drawing.DrawingManager({
                    drawingMode: state.polygon ? null : google.maps.drawing.OverlayType.POLYGON,
                    drawingControl: true,
                    drawingControlOptions: {
                        position: google.maps.ControlPosition.TOP_LEFT,
                        drawingModes: [google.maps.drawing.OverlayType.POLYGON],
                    },
                    polygonOptions: {
                        editable: true,
                        draggable: true,
                        strokeColor: state.color,
                        strokeOpacity: 0.9,
                        strokeWeight: 2,
                        fillColor: state.color,
                        fillOpacity: 0.25,
                    },
                });
                state.drawingManager.setMap(state.map);

                google.maps.event.addListener(state.drawingManager, 'polygoncomplete', (poly) => {
                    if (state.polygon) state.polygon.setMap(null);
                    state.polygon = poly;
                    state.drawingManager.setDrawingMode(null);

                    google.maps.event.addListener(poly.getPath(), 'set_at', () => gmapsSedeCalcularYEnviar(poly));
                    google.maps.event.addListener(poly.getPath(), 'insert_at', () => gmapsSedeCalcularYEnviar(poly));
                    google.maps.event.addListener(poly.getPath(), 'remove_at', () => gmapsSedeCalcularYEnviar(poly));

                    gmapsSedeCalcularYEnviar(poly);
                });
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
                    const url = new URL('https://nominatim.openstreetmap.org/search');
                    url.searchParams.set('q', query + ', Colombia');
                    url.searchParams.set('format', 'json');
                    url.searchParams.set('polygon_geojson', '1');
                    url.searchParams.set('limit', '5');
                    url.searchParams.set('countrycodes', 'co');

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

                // Quitar polígono actual y dibujar el nuevo
                if (state.polygon) state.polygon.setMap(null);

                state.polygon = new google.maps.Polygon({
                    paths: coords,
                    editable: true,
                    draggable: false,
                    strokeColor: state.color,
                    strokeOpacity: 0.9,
                    strokeWeight: 2,
                    fillColor: state.color,
                    fillOpacity: 0.25,
                });
                state.polygon.setMap(state.map);

                // Listeners para edición posterior
                google.maps.event.addListener(state.polygon.getPath(), 'set_at', () => gmapsSedeCalcularYEnviar(state.polygon));
                google.maps.event.addListener(state.polygon.getPath(), 'insert_at', () => gmapsSedeCalcularYEnviar(state.polygon));
                google.maps.event.addListener(state.polygon.getPath(), 'remove_at', () => gmapsSedeCalcularYEnviar(state.polygon));

                // Apagar drawing manager (ya hay polígono)
                state.drawingManager.setDrawingMode(null);

                // Centrar el mapa en el polígono
                const bounds = new google.maps.LatLngBounds();
                coords.forEach(p => bounds.extend(p));
                state.map.fitBounds(bounds);

                gmapsSedeSetStatus('✅ Polígono de "' + lugar.display_name + '" cargado (' + coords.length + ' pts). Puedes ajustarlo o guardarlo.');

                // Persistir en Livewire
                gmapsSedeCalcularYEnviar(state.polygon);
            }
        </script>
    @endif
</div>
