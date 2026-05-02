<div class="px-6 lg:px-10 py-8">

    <div class="mb-6">
        <a href="{{ route('zonas.index') }}" class="text-xs text-slate-500 hover:text-slate-800">
            <i class="fa-solid fa-arrow-left mr-1"></i> Volver a zonas
        </a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-slate-800">
                    <i class="fa-solid fa-map-location-dot text-blue-600 mr-2"></i>
                    Editor visual: {{ $zona->nombre }}
                </h2>
                <p class="text-sm text-slate-500">
                    Dibuja el polígono de cobertura sobre el mapa. Usa la herramienta del lápiz arriba a la izquierda del mapa.
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
                <div class="text-[11px] text-slate-500">Vértices del polígono</div>
                <div class="text-xl font-extrabold text-slate-800">
                    {{ count($poligono ?? []) }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-3 shadow border border-slate-200">
                <div class="text-[11px] text-slate-500">Centro lat / lng</div>
                <div class="text-xs font-mono font-bold text-slate-800">
                    {{ $centro_lat ? number_format($centro_lat, 5) : '—' }}, {{ $centro_lng ? number_format($centro_lng, 5) : '—' }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-3 shadow border border-slate-200">
                <div class="text-[11px] text-slate-500">Área aproximada</div>
                <div class="text-xl font-extrabold text-slate-800">
                    {{ $area_km2 ? number_format($area_km2, 2) . ' km²' : '—' }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-3 shadow border border-slate-200">
                <div class="text-[11px] text-slate-500">Color de la zona</div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded" style="background: {{ $color }}"></div>
                    <span class="text-xs font-mono">{{ $color }}</span>
                </div>
            </div>
        </div>

        {{-- Mapa Google Maps --}}
        <div id="gmaps-zona-editor" style="height: 70vh; width: 100%; border-radius: 1rem; border: 1px solid #cbd5e1;"></div>
        <div id="gmaps-status" class="mt-2 text-xs text-slate-500 font-mono"></div>

        {{-- Cargar Google Maps API + Drawing library --}}
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $config['api_key'] }}&libraries=drawing,geometry&language=es&region=CO"
                defer
                onload="initGoogleMapsZonaEditor()"></script>

        <script>
            // Estado global del editor
            window.gmapsZonaState = {
                polygon: null,
                drawingManager: null,
                map: null,
                poligonoInicial: @json($poligono),
                color: @json($color),
                centroDefault: { lat: {{ $config['centro_lat'] }}, lng: {{ $config['centro_lng'] }} },
                zoomDefault: {{ $config['zoom'] }},
            };

            function gmapsZonaSetStatus(msg) {
                const el = document.getElementById('gmaps-status');
                if (el) el.textContent = msg;
            }

            function gmapsZonaCalcularYEnviar(polygon) {
                const path = polygon.getPath();
                const coords = [];
                path.forEach(latlng => coords.push([latlng.lat(), latlng.lng()]));

                // Cierre del polígono (first === last)
                if (coords.length > 0 && (coords[0][0] !== coords[coords.length-1][0] || coords[0][1] !== coords[coords.length-1][1])) {
                    coords.push(coords[0]);
                }

                // Centro = promedio
                const center = coords.reduce((acc, c) => ({ lat: acc.lat + c[0]/coords.length, lng: acc.lng + c[1]/coords.length }), { lat: 0, lng: 0 });

                // Área en km² usando google.maps.geometry
                let areaKm2 = 0;
                try {
                    const m2 = google.maps.geometry.spherical.computeArea(path);
                    areaKm2 = m2 / 1_000_000;
                } catch (e) {}

                gmapsZonaSetStatus(`✓ Polígono: ${coords.length} vértices · Área: ${areaKm2.toFixed(2)} km²`);

                @this.actualizarPoligono({
                    coordinates: coords,
                    center: center,
                    area_km2: areaKm2,
                });
            }

            function initGoogleMapsZonaEditor() {
                const state = window.gmapsZonaState;
                const centro = state.centroDefault;

                state.map = new google.maps.Map(document.getElementById('gmaps-zona-editor'), {
                    center: centro,
                    zoom: state.zoomDefault,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true,
                });

                // Si la zona ya tiene polígono, dibujarlo
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

                    // Centrar en el polígono
                    const bounds = new google.maps.LatLngBounds();
                    path.forEach(p => bounds.extend(p));
                    state.map.fitBounds(bounds);

                    // Listener para cambios al editar
                    google.maps.event.addListener(state.polygon.getPath(), 'set_at', () => gmapsZonaCalcularYEnviar(state.polygon));
                    google.maps.event.addListener(state.polygon.getPath(), 'insert_at', () => gmapsZonaCalcularYEnviar(state.polygon));
                    google.maps.event.addListener(state.polygon.getPath(), 'remove_at', () => gmapsZonaCalcularYEnviar(state.polygon));

                    gmapsZonaSetStatus('Polígono cargado. Arrastra los puntos para editarlo.');
                } else {
                    gmapsZonaSetStatus('Sin polígono. Usa la herramienta del lápiz para dibujar.');
                }

                // Drawing manager
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

                // Cuando el usuario termina de dibujar
                google.maps.event.addListener(state.drawingManager, 'polygoncomplete', (poly) => {
                    // Si ya había uno antes, lo quitamos
                    if (state.polygon) {
                        state.polygon.setMap(null);
                    }
                    state.polygon = poly;
                    state.drawingManager.setDrawingMode(null);

                    google.maps.event.addListener(poly.getPath(), 'set_at', () => gmapsZonaCalcularYEnviar(poly));
                    google.maps.event.addListener(poly.getPath(), 'insert_at', () => gmapsZonaCalcularYEnviar(poly));
                    google.maps.event.addListener(poly.getPath(), 'remove_at', () => gmapsZonaCalcularYEnviar(poly));

                    gmapsZonaCalcularYEnviar(poly);
                });
            }

            // Si Livewire navega y vuelve, reinicializar
            document.addEventListener('livewire:navigated', () => {
                if (typeof google !== 'undefined' && google.maps) {
                    setTimeout(initGoogleMapsZonaEditor, 100);
                }
            });
        </script>
    @endif
</div>
