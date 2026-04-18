<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Zonas de cobertura</h2>
            <p class="text-sm text-slate-500">Define dónde entregas — con polígonos en el mapa y barrios.</p>
        </div>

        <div class="flex items-center gap-2">
            {{-- Toggle vista --}}
            <div class="inline-flex items-center rounded-xl bg-white p-1 shadow border border-slate-200">
                <button wire:click="$set('vista', 'lista')"
                        class="px-3 py-2 text-xs font-semibold rounded-lg transition
                              {{ $vista === 'lista' ? 'bg-[#d68643] text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
                    <i class="fa-solid fa-list mr-1.5"></i> Lista
                </button>
                <button wire:click="$set('vista', 'mapa')"
                        class="px-3 py-2 text-xs font-semibold rounded-lg transition
                              {{ $vista === 'mapa' ? 'bg-[#d68643] text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
                    <i class="fa-solid fa-map-location-dot mr-1.5"></i> Mapa
                </button>
            </div>

            <button wire:click="abrirModalCrear"
                    class="rounded-2xl bg-[#d68643] px-5 py-3 text-white font-semibold shadow hover:bg-[#c97a36] transition">
                <i class="fa-solid fa-plus mr-2"></i> Nueva zona
            </button>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Buscar zona..."
               class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-[#d68643] focus:ring-[#d68643]">

        <select wire:model.live="filtroSedeId"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-[#d68643] focus:ring-[#d68643]">
            <option value="">Todas las sedes</option>
            @foreach($sedes as $sede)
                <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtroEstado"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-[#d68643] focus:ring-[#d68643]">
            <option value="todas">Todas</option>
            <option value="activas">Solo activas</option>
            <option value="inactivas">Solo inactivas</option>
        </select>
    </div>

    {{-- ╔═══ VISTA MAPA ═══╗ --}}
    @if($vista === 'mapa')
        <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">

            {{-- Resumen de cobertura --}}
            @php
                $totalArea = $zonasMapa->sum('area_km2');
                $totalZonas = $zonasMapa->count();
                $totalPedidos = $zonasMapa->sum('pedidos');
            @endphp

            <div class="grid grid-cols-3 gap-px bg-slate-200">
                <div class="bg-white px-5 py-3">
                    <div class="text-[10px] font-bold uppercase text-slate-500">Zonas mapeadas</div>
                    <div class="text-xl font-extrabold text-slate-800">{{ $totalZonas }}</div>
                </div>
                <div class="bg-white px-5 py-3">
                    <div class="text-[10px] font-bold uppercase text-slate-500">Cobertura total</div>
                    <div class="text-xl font-extrabold text-[#d68643]">{{ number_format($totalArea, 2, ',', '.') }} km²</div>
                </div>
                <div class="bg-white px-5 py-3">
                    <div class="text-[10px] font-bold uppercase text-slate-500">Pedidos en zonas</div>
                    <div class="text-xl font-extrabold text-slate-800">{{ $totalPedidos }}</div>
                </div>
            </div>

            {{-- Toggle de capas --}}
            <div class="flex items-center gap-3 px-5 py-2 border-b border-slate-100 bg-slate-50/50"
                 x-data="{ verPedidos: true }">
                <span class="text-xs font-bold uppercase text-slate-500">Capas:</span>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="verPedidos"
                           @change="window.dispatchEvent(new CustomEvent('toggle-pedidos-mapa', { detail: verPedidos }))"
                           class="rounded border-slate-300 text-[#d68643]">
                    <span class="text-xs text-slate-700">
                        <i class="fa-solid fa-location-dot text-[#d68643]"></i>
                        Pedidos activos ({{ $pedidosMapa->count() }})
                    </span>
                </label>
            </div>

            {{-- Mapa global --}}
            <div wire:ignore
                 x-data="zonasMapaGlobal(@js($zonasMapa), @js($pedidosMapa))"
                 x-init="initMap()"
                 class="relative">
                <div id="mapa-global" style="height: 600px; width: 100%;"></div>

                @if($zonasMapa->isEmpty())
                    <div class="absolute inset-0 flex items-center justify-center bg-slate-900/60 backdrop-blur z-[400] pointer-events-none">
                        <div class="text-center text-white pointer-events-auto">
                            <i class="fa-solid fa-map-location-dot text-5xl mb-3"></i>
                            <h3 class="font-bold text-lg">Aún no hay zonas dibujadas</h3>
                            <p class="text-sm text-white/80 mb-4">Crea una zona y dibuja su polígono en el mapa.</p>
                            <button wire:click="abrirModalCrear"
                                    class="rounded-xl bg-[#d68643] px-5 py-2.5 text-sm font-bold hover:bg-[#c97a36] transition">
                                <i class="fa-solid fa-plus mr-2"></i> Crear primera zona
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    {{-- ╔═══ VISTA LISTA ═══╗ --}}
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($zonas as $zona)
                <div class="rounded-2xl bg-white shadow hover:shadow-lg transition overflow-hidden border-l-4"
                     style="border-color: {{ $zona->color }}">

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl text-white shadow"
                                     style="background-color: {{ $zona->color }}">
                                    <i class="fa-solid fa-map-location-dot"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-slate-800 truncate">{{ $zona->nombre }}</h3>
                                    <div class="text-xs text-slate-500">
                                        <i class="fa-solid fa-store mr-1"></i>
                                        {{ $zona->sede?->nombre ?? 'Todas las sedes' }}
                                    </div>
                                </div>
                            </div>

                            <button wire:click="toggleActiva({{ $zona->id }})"
                                    class="text-xs px-2.5 py-1 rounded-full font-medium transition
                                           {{ $zona->activa ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' }}">
                                {{ $zona->activa ? 'Activa' : 'Inactiva' }}
                            </button>
                        </div>

                        {{-- Indicador de mapa --}}
                        @if($zona->poligono)
                            <div class="flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 mb-3">
                                <div class="flex items-center gap-2 text-xs text-emerald-700 font-semibold">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Polígono mapeado
                                </div>
                                @if($zona->area_km2)
                                    <span class="text-xs font-bold text-emerald-800">{{ number_format($zona->area_km2, 2, ',', '.') }} km²</span>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 mb-3 text-xs text-amber-700 font-medium">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Sin polígono — edita y dibuja en el mapa
                            </div>
                        @endif

                        @if($zona->descripcion)
                            <p class="text-xs text-slate-500 mb-3">{{ $zona->descripcion }}</p>
                        @endif

                        {{-- Barrios chips --}}
                        @if($zona->barrios->count())
                            <div class="flex flex-wrap gap-1 mb-3">
                                @foreach($zona->barrios->take(8) as $b)
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                        {{ $b->nombre }}
                                    </span>
                                @endforeach
                                @if($zona->barrios->count() > 8)
                                    <span class="inline-flex items-center rounded-md bg-slate-200 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                        +{{ $zona->barrios->count() - 8 }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="grid grid-cols-3 gap-2 mb-3 text-center">
                            <div class="rounded-lg bg-slate-50 py-2">
                                <div class="text-sm font-bold text-slate-800">{{ $zona->barrios_count }}</div>
                                <div class="text-[10px] text-slate-500 uppercase">Barrios</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 py-2">
                                <div class="text-sm font-bold text-slate-800">{{ $zona->domiciliarios_count }}</div>
                                <div class="text-[10px] text-slate-500 uppercase">Repartidores</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 py-2">
                                <div class="text-sm font-bold text-slate-800">{{ $zona->pedidos_count }}</div>
                                <div class="text-[10px] text-slate-500 uppercase">Pedidos</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 mb-3 flex-wrap gap-2">
                            <div>
                                <i class="fa-solid fa-truck mr-1 text-[#d68643]"></i>
                                ${{ number_format($zona->costo_envio, 0, ',', '.') }}
                            </div>
                            @if($zona->tiempo_estimado_min)
                                <div>
                                    <i class="fa-solid fa-clock mr-1 text-[#d68643]"></i>
                                    {{ $zona->tiempo_estimado_min }} min
                                </div>
                            @endif
                            @if((float) $zona->pedido_minimo > 0)
                                <div>
                                    <i class="fa-solid fa-cart-shopping mr-1 text-[#d68643]"></i>
                                    mín ${{ number_format($zona->pedido_minimo, 0, ',', '.') }}
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center justify-end gap-1 pt-3 border-t border-slate-100">
                            <button wire:click="abrirModalEditar({{ $zona->id }})"
                                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button wire:click="eliminar({{ $zona->id }})"
                                    wire:confirm="¿Eliminar esta zona?"
                                    class="rounded-lg p-2 text-red-500 hover:bg-red-50 transition">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl bg-white p-12 text-center text-slate-400 shadow">
                    <i class="fa-solid fa-map-location-dot text-4xl mb-3 block"></i>
                    Aún no hay zonas de cobertura. Crea la primera para empezar.
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $zonas->links() }}
        </div>
    @endif

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">

            <div class="w-full sm:max-w-4xl rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl my-0 sm:my-8 max-h-[95vh] flex flex-col">

                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 sticky top-0 bg-white rounded-t-2xl shrink-0">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ $editandoId ? 'Editar zona' : 'Nueva zona de cobertura' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4 overflow-y-auto">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="Ej: Zona Norte"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                            @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Color</label>
                            <input type="color" wire:model="color"
                                   class="w-full h-11 rounded-xl border border-slate-200">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="descripcion" placeholder="Descripción breve"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sede</label>
                            <select wire:model="sede_id"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                                <option value="">Todas las sedes</option>
                                @foreach($sedes as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Costo de envío</label>
                            <input type="number" step="100" wire:model="costo_envio" min="0"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Pedido mínimo
                                <span class="text-xs text-slate-400 font-normal">(0 = sin mínimo)</span>
                            </label>
                            <input type="number" step="1000" wire:model="pedido_minimo" min="0" placeholder="30000"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tiempo estimado (min)</label>
                            <input type="number" wire:model="tiempo_estimado_min" min="1" placeholder="30"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>
                    </div>

                    {{-- ╔═══ MAPA INTERACTIVO ═══╗ --}}
                    <div class="rounded-xl border-2 border-[#d68643] bg-amber-50/30 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-bold text-slate-800">
                                <i class="fa-solid fa-draw-polygon text-[#d68643] mr-1"></i>
                                Dibujar zona en el mapa
                            </label>
                            <span class="text-xs text-slate-500">
                                @if($area_km2)
                                    Área: <strong class="text-[#a85f24]">{{ number_format($area_km2, 2, ',', '.') }} km²</strong>
                                @else
                                    Click "Polígono" para empezar a dibujar
                                @endif
                            </span>
                        </div>

                        <div wire:ignore
                             x-data="zonaMapaEditor({
                                poligono: @js($poligono),
                                color: @js($color),
                                nombre: @js($nombre),
                             })"
                             x-init="initMap()">

                            {{-- Buscador Nominatim --}}
                            <div class="mb-3 rounded-xl bg-white border border-slate-200 p-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-magnifying-glass-location text-[#d68643]"></i>
                                    <label class="text-xs font-bold text-slate-700">
                                        Importar área de un lugar
                                    </label>
                                    <span class="text-[10px] text-slate-400">— escribe Bello, Copacabana, Itagüí...</span>
                                </div>

                                <div class="flex gap-2">
                                    <input type="text"
                                           x-model="busquedaTexto"
                                           @keydown.enter.prevent="buscarLugar()"
                                           placeholder="Ej: Bello, Antioquia"
                                           class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-amber-100">
                                    <button type="button"
                                            @click="buscarLugar()"
                                            :disabled="buscando"
                                            class="rounded-xl bg-[#d68643] px-4 py-2 text-sm font-bold text-white hover:bg-[#c97a36] transition disabled:opacity-60 whitespace-nowrap">
                                        <span x-show="!buscando">
                                            <i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar
                                        </span>
                                        <span x-show="buscando">
                                            <i class="fa-solid fa-spinner fa-spin"></i> Buscando...
                                        </span>
                                    </button>
                                </div>

                                {{-- Resultados --}}
                                <template x-if="resultados.length > 0">
                                    <div class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                                        <template x-for="(r, idx) in resultados" :key="idx">
                                            <button type="button"
                                                    @click="usarLugar(r)"
                                                    class="w-full text-left px-3 py-2 text-xs hover:bg-amber-50 transition">
                                                <div class="font-semibold text-slate-800" x-text="r.display_name"></div>
                                                <div class="text-[10px] text-slate-500" x-text="r.type + ' · ' + (r.class || '')"></div>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="errorBusqueda">
                                    <div class="mt-2 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700"
                                         x-text="errorBusqueda"></div>
                                </template>
                            </div>

                            <div id="mapa-zona-modal" style="height: 350px; width: 100%; border-radius: 0.75rem; border: 1px solid #e2e8f0;"></div>

                            <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
                                <span>
                                    <i class="fa-solid fa-circle-info"></i>
                                    Usa el ícono de polígono (arriba izquierda) para dibujar manualmente, o busca un lugar arriba.
                                </span>
                                <button type="button" @click="centrarEnColombia()"
                                        class="text-[#d68643] hover:underline font-semibold">
                                    <i class="fa-solid fa-location-crosshairs"></i> Centrar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-slate-700">
                                Barrios incluidos
                                <span class="text-xs text-slate-400 font-normal">(uno por línea o separados por coma)</span>
                            </label>
                            <button type="button"
                                    wire:click="autodetectarBarrios"
                                    wire:loading.attr="disabled"
                                    wire:target="autodetectarBarrios"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#d68643] hover:bg-[#c97a36] text-white transition disabled:opacity-60 disabled:cursor-wait">
                                <span wire:loading.remove wire:target="autodetectarBarrios">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    Auto-detectar
                                </span>
                                <span wire:loading wire:target="autodetectarBarrios" class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                    Consultando OSM…
                                </span>
                            </button>
                        </div>
                        <textarea wire:model="barriosTexto" rows="6" placeholder="Niquía&#10;Belén&#10;Pajarito&#10;&#10;Dibuja un polígono arriba y presiona Auto-detectar para llenarlo automáticamente."
                                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]"></textarea>
                        <p class="text-xs text-slate-500 mt-1">
                            <i class="fa-solid fa-info-circle"></i>
                            Auto-detectar busca los barrios en OpenStreetMap dentro del polígono dibujado.
                            También puedes escribirlos a mano. El sistema normaliza para emparejar lo que escribe el cliente.
                        </p>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="activa" class="rounded border-slate-300 text-[#d68643]">
                            <span class="text-sm text-slate-700">Zona activa</span>
                        </label>

                        <div class="flex items-center gap-2">
                            <label class="text-sm text-slate-700">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-24 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-[#d68643] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#c97a36]">
                            Guardar zona
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Componente Alpine para el mapa global (vista mapa)
    window.zonasMapaGlobal = (zonas, pedidos) => ({
        map: null,
        zonasLayer: null,
        pedidosLayer: null,

        initMap() {
            const checkLeaflet = () => {
                if (typeof L === 'undefined') { setTimeout(checkLeaflet, 100); return; }
                this.crearMapa(zonas, pedidos || []);
                window.addEventListener('toggle-pedidos-mapa', e => this.togglePedidos(e.detail));
            };
            checkLeaflet();
        },

        crearMapa(zonas, pedidos) {
            const centro = [6.3373, -75.5567];
            this.map = L.map('mapa-global').setView(centro, 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            const allBounds = [];
            this.zonasLayer = L.layerGroup().addTo(this.map);

            zonas.forEach(z => {
                if (!z.poligono || !Array.isArray(z.poligono) || z.poligono.length === 0) return;

                const latlngs = z.poligono.map(p => [p[0], p[1]]);
                const polygon = L.polygon(latlngs, {
                    color: z.color,
                    fillColor: z.color,
                    fillOpacity: 0.25,
                    weight: 2,
                }).addTo(this.zonasLayer);

                polygon.bindPopup(`
                    <div style="font-family: Inter, sans-serif; min-width: 180px;">
                        <div style="font-weight: 700; color: ${z.color}; margin-bottom: 4px;">${z.nombre}</div>
                        ${z.sede ? `<div style="font-size: 11px; color: #64748b;">📍 ${z.sede}</div>` : ''}
                        <div style="margin-top: 8px; display: flex; gap: 12px; font-size: 11px;">
                            ${z.area_km2 ? `<span><strong>${z.area_km2.toFixed(2)} km²</strong></span>` : ''}
                            <span><strong>${z.pedidos}</strong> pedidos</span>
                        </div>
                    </div>
                `);

                allBounds.push(polygon.getBounds());
            });

            // Pedidos como markers
            this.pedidosLayer = L.layerGroup().addTo(this.map);
            this.dibujarPedidos(pedidos);

            // Auto-fit
            if (allBounds.length > 0) {
                const combinedBounds = allBounds.length > 1
                    ? L.latLngBounds(allBounds.map(b => [b.getSouthWest(), b.getNorthEast()]).flat())
                    : allBounds[0];
                this.map.fitBounds(combinedBounds, { padding: [40, 40] });
            }
        },

        dibujarPedidos(pedidos) {
            if (!this.pedidosLayer) return;

            const colores = {
                'nuevo':                 { bg: '#3b82f6', emoji: '🔔' },
                'en_preparacion':        { bg: '#f59e0b', emoji: '🍳' },
                'repartidor_en_camino':  { bg: '#8b5cf6', emoji: '🛵' },
            };

            pedidos.forEach(p => {
                const cfg = colores[p.estado] || { bg: '#64748b', emoji: '📦' };

                const icon = L.divIcon({
                    html: `<div style="
                        background: ${cfg.bg};
                        width: 32px; height: 32px;
                        border-radius: 50% 50% 50% 0;
                        transform: rotate(-45deg);
                        display: flex; align-items: center; justify-content: center;
                        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
                        border: 2px solid white;">
                        <span style="transform: rotate(45deg); font-size: 14px;">${cfg.emoji}</span>
                    </div>`,
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32],
                });

                const marker = L.marker([p.lat, p.lng], { icon }).addTo(this.pedidosLayer);

                marker.bindPopup(`
                    <div style="font-family: Inter, sans-serif; min-width: 200px;">
                        <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;">
                            <strong style="color:#1e293b;">#${String(p.id).padStart(3, '0')} ${p.cliente}</strong>
                            <span style="background: ${cfg.bg}; color: white; padding: 2px 8px; border-radius: 999px; font-size: 10px; text-transform: uppercase;">
                                ${p.estado.replace('_', ' ')}
                            </span>
                        </div>
                        ${p.barrio ? `<div style="font-size:11px;color:#64748b;">📍 ${p.barrio}</div>` : ''}
                        ${p.direccion ? `<div style="font-size:11px;color:#64748b;">${p.direccion}</div>` : ''}
                        <div style="margin-top:6px;font-weight:bold;color:#d68643;">$${new Intl.NumberFormat('es-CO').format(p.total)}</div>
                    </div>
                `);
            });
        },

        togglePedidos(visible) {
            if (!this.pedidosLayer) return;
            if (visible) {
                this.map.addLayer(this.pedidosLayer);
            } else {
                this.map.removeLayer(this.pedidosLayer);
            }
        },
    });

    // Componente Alpine para el editor de mapa en el modal
    window.zonaMapaEditor = (config) => ({
        map: null,
        drawnItems: null,
        drawControl: null,
        currentLayer: null,
        config: config,
        busquedaTexto: '',
        buscando: false,
        resultados: [],
        errorBusqueda: '',

        initMap() {
            const checkLeaflet = () => {
                if (typeof L === 'undefined' || typeof L.Control.Draw === 'undefined') {
                    setTimeout(checkLeaflet, 100);
                    return;
                }
                this.crearMapa();
            };
            checkLeaflet();
        },

        crearMapa() {
            const centro = [6.3373, -75.5567]; // Bello
            this.map = L.map('mapa-zona-modal').setView(centro, 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            this.drawnItems = new L.FeatureGroup();
            this.map.addLayer(this.drawnItems);

            // Si hay polígono existente, dibujarlo
            if (this.config.poligono && Array.isArray(this.config.poligono) && this.config.poligono.length > 0) {
                const latlngs = this.config.poligono.map(p => [p[0], p[1]]);
                this.currentLayer = L.polygon(latlngs, {
                    color: this.config.color || '#d68643',
                    fillColor: this.config.color || '#d68643',
                    fillOpacity: 0.3,
                    weight: 2,
                });
                this.drawnItems.addLayer(this.currentLayer);
                this.map.fitBounds(this.currentLayer.getBounds(), { padding: [40, 40] });
            }

            // Controles de dibujo
            this.drawControl = new L.Control.Draw({
                position: 'topleft',
                draw: {
                    polygon: {
                        allowIntersection: false,
                        showArea: true,
                        shapeOptions: {
                            color: this.config.color || '#d68643',
                            fillColor: this.config.color || '#d68643',
                            fillOpacity: 0.3,
                            weight: 2,
                        },
                    },
                    polyline: false,
                    rectangle: false,
                    circle: false,
                    marker: false,
                    circlemarker: false,
                },
                edit: {
                    featureGroup: this.drawnItems,
                    remove: true,
                },
            });
            this.map.addControl(this.drawControl);

            // Eventos
            this.map.on(L.Draw.Event.CREATED, (e) => {
                // Solo permitir un polígono — eliminar el anterior si existe
                this.drawnItems.clearLayers();
                this.currentLayer = e.layer;
                this.drawnItems.addLayer(this.currentLayer);
                this.enviarAlServidor();
            });

            this.map.on(L.Draw.Event.EDITED, () => this.enviarAlServidor());
            this.map.on(L.Draw.Event.DELETED, () => {
                this.currentLayer = null;
                this.enviarVacio();
            });

            // Ajustar tamaño cuando el modal termina de animarse
            setTimeout(() => this.map.invalidateSize(), 200);
        },

        enviarAlServidor() {
            if (!this.currentLayer) return;

            const latlngs = this.currentLayer.getLatLngs()[0];
            const coords = latlngs.map(p => [p.lat, p.lng]);
            const center = this.currentLayer.getBounds().getCenter();

            // Calcular área con turf.js (en km²)
            let areaKm2 = 0;
            try {
                if (typeof turf !== 'undefined') {
                    const geoJsonCoords = [...coords, coords[0]].map(c => [c[1], c[0]]); // [lng, lat] y cerrar
                    const polygon = turf.polygon([geoJsonCoords]);
                    areaKm2 = turf.area(polygon) / 1_000_000; // m² → km²
                }
            } catch (e) { console.warn('turf.area falló:', e); }

            // @this de Livewire
            @this.call('actualizarPoligono', {
                coordinates: coords,
                center: { lat: center.lat, lng: center.lng },
                area_km2: areaKm2,
            });
        },

        enviarVacio() {
            @this.call('actualizarPoligono', {
                coordinates: null,
                center: null,
                area_km2: null,
            });
        },

        centrarEnColombia() {
            this.map.setView([4.5709, -74.2973], 6);
        },

        async buscarLugar() {
            const q = this.busquedaTexto.trim();
            if (!q) return;

            this.buscando = true;
            this.errorBusqueda = '';
            this.resultados = [];

            try {
                // Nominatim: buscar con geometría (polígono)
                const url = new URL('https://nominatim.openstreetmap.org/search');
                url.searchParams.set('q', q);
                url.searchParams.set('format', 'json');
                url.searchParams.set('polygon_geojson', '1');
                url.searchParams.set('addressdetails', '1');
                url.searchParams.set('limit', '5');
                url.searchParams.set('countrycodes', 'co');   // priorizar Colombia
                url.searchParams.set('accept-language', 'es');

                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!res.ok) throw new Error('Error consultando Nominatim');

                const data = await res.json();
                const conPoligono = data.filter(d => d.geojson && (
                    d.geojson.type === 'Polygon' || d.geojson.type === 'MultiPolygon'
                ));

                if (conPoligono.length === 0) {
                    this.errorBusqueda = 'No se encontró ningún lugar con polígono. Prueba un nombre más específico (ej: "Bello, Antioquia").';
                    return;
                }

                this.resultados = conPoligono;
            } catch (e) {
                this.errorBusqueda = 'Error al consultar el servicio de mapas: ' + e.message;
            } finally {
                this.buscando = false;
            }
        },

        usarLugar(resultado) {
            // Convertir geometría GeoJSON a polígono Leaflet
            const geo = resultado.geojson;
            let coords;

            if (geo.type === 'Polygon') {
                // GeoJSON: [[[lng, lat], ...]]
                coords = geo.coordinates[0].map(c => [c[1], c[0]]);
            } else if (geo.type === 'MultiPolygon') {
                // Toma el polígono más grande
                let mayor = geo.coordinates[0];
                let mayorArea = 0;
                geo.coordinates.forEach(p => {
                    if (p[0].length > mayorArea) { mayor = p; mayorArea = p[0].length; }
                });
                coords = mayor[0].map(c => [c[1], c[0]]);
            } else {
                return;
            }

            // Limpiar polígono actual y dibujar el nuevo
            this.drawnItems.clearLayers();
            this.currentLayer = L.polygon(coords, {
                color: this.config.color || '#d68643',
                fillColor: this.config.color || '#d68643',
                fillOpacity: 0.3,
                weight: 2,
            });
            this.drawnItems.addLayer(this.currentLayer);
            this.map.fitBounds(this.currentLayer.getBounds(), { padding: [40, 40] });

            // Enviar al backend
            this.enviarAlServidor();

            // Limpiar UI
            this.resultados = [];
            this.busquedaTexto = resultado.display_name.split(',')[0];
        },
    });
</script>
@endpush
