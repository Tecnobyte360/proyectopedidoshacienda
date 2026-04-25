<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Domiciliarios</h2>
            <p class="text-sm text-slate-500">Gestiona los repartidores de tu equipo.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button wire:click="liberarTodos"
                    wire:confirm="¿Liberar a todos los domiciliarios ocupados/en ruta? Esto pone su estado en 'disponible' para que vuelvan a aparecer en pedidos."
                    class="rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-3 font-semibold transition">
                <i class="fa-solid fa-lock-open mr-2"></i> Liberar todos
            </button>
            <button wire:click="abrirModalCrear"
                    class="rounded-2xl bg-brand px-5 py-3 text-white font-semibold shadow hover:bg-brand-dark transition">
                <i class="fa-solid fa-plus mr-2"></i> Nuevo domiciliario
            </button>
        </div>
    </div>

    {{-- KPIS --}}
    @php
        $totalActivos = $domiciliarios->where('activo', true)->count();
        $disponibles  = $domiciliarios->where('estado', 'disponible')->where('activo', true)->count();
        $ocupados     = $domiciliarios->where('estado', 'ocupado')->count();
        $inactivos    = $domiciliarios->where('activo', false)->count();
    @endphp

    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totalActivos }}</div>
                    <div class="text-xs text-slate-500">Activos</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $disponibles }}</div>
                    <div class="text-xs text-slate-500">Disponibles</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $ocupados }}</div>
                    <div class="text-xs text-slate-500">En ruta</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $inactivos }}</div>
                    <div class="text-xs text-slate-500">Inactivos</div>
                </div>
            </div>
        </div>
    </div>

    {{-- SEARCH --}}
    <div class="mb-4">
        <input type="text"
               wire:model.live.debounce.400ms="buscar"
               placeholder="Buscar por nombre, teléfono, vehículo o placa..."
               class="w-full md:w-96 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">
    </div>

    {{-- GRID DE DOMICILIARIOS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse($domiciliarios as $dom)
            @php
                $estadoColor = match($dom->estado) {
                    'disponible' => 'bg-green-100 text-green-700 border-green-200',
                    'ocupado'    => 'bg-amber-100 text-amber-700 border-amber-200',
                    'inactivo'   => 'bg-slate-100 text-slate-600 border-slate-200',
                    default      => 'bg-slate-100 text-slate-600 border-slate-200',
                };

                $vehiculoIcon = match(strtolower($dom->vehiculo ?? '')) {
                    'moto', 'motocicleta' => 'fa-motorcycle',
                    'bicicleta', 'bici'   => 'fa-bicycle',
                    'carro', 'auto'       => 'fa-car',
                    default               => 'fa-truck-fast',
                };
            @endphp

            <div class="rounded-2xl bg-white shadow hover:shadow-lg transition overflow-hidden">

                {{-- HEADER tarjeta --}}
                <div class="relative bg-gradient-to-br from-brand to-brand-secondary p-5 text-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur text-2xl font-bold">
                            {{ strtoupper(substr($dom->nombre, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-lg truncate">{{ $dom->nombre }}</h3>
                            <div class="flex items-center gap-2 text-xs text-white/80">
                                <i class="fa-solid {{ $vehiculoIcon }}"></i>
                                <span>{{ $dom->vehiculo ?: 'Sin vehículo' }}</span>
                                @if($dom->placa)
                                    <span class="bg-white/20 rounded px-1.5 py-0.5 font-mono text-[10px]">{{ $dom->placa }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if(!$dom->activo)
                        <span class="absolute top-3 right-3 rounded-full bg-slate-700/80 px-2 py-0.5 text-[10px] font-bold uppercase">
                            Inactivo
                        </span>
                    @endif
                </div>

                {{-- BODY --}}
                <div class="p-4 space-y-3">

                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium capitalize {{ $estadoColor }}">
                            @if($dom->estado === 'disponible')
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            @elseif($dom->estado === 'ocupado')
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            @endif
                            {{ $dom->estado }}
                        </span>

                        <button wire:click="cambiarActivo({{ $dom->id }})"
                                class="text-xs px-3 py-1 rounded-lg transition
                                       {{ $dom->activo ? 'text-slate-500 hover:bg-slate-100' : 'text-green-600 hover:bg-green-50' }}">
                            <i class="fa-solid {{ $dom->activo ? 'fa-toggle-on' : 'fa-toggle-off' }} mr-1"></i>
                            {{ $dom->activo ? 'Activo' : 'Inactivo' }}
                        </button>
                    </div>

                    @if($dom->telefono)
                        <a href="tel:{{ $dom->telefonoInternacional() }}"
                           class="flex items-center gap-2 text-sm text-slate-600 hover:text-brand transition">
                            <i class="fa-solid fa-phone text-xs text-slate-400"></i>
                            <span class="font-mono">{{ $dom->telefonoFormateado() }}</span>
                        </a>
                    @endif

                    {{-- Zonas asignadas --}}
                    @if($dom->zonas->count())
                        <div class="flex flex-wrap gap-1">
                            @foreach($dom->zonas->take(3) as $z)
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-medium text-white"
                                      style="background-color: {{ $z->color }}">
                                    <i class="fa-solid fa-map-pin mr-1"></i>{{ $z->nombre }}
                                </span>
                            @endforeach
                            @if($dom->zonas->count() > 3)
                                <span class="inline-flex items-center rounded-md bg-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                    +{{ $dom->zonas->count() - 3 }}
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                        <button wire:click="abrirModalEditar({{ $dom->id }})"
                                class="flex-1 rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-200 transition">
                            <i class="fa-solid fa-pen-to-square mr-1"></i> Editar
                        </button>
                        @if($dom->whatsappUrl())
                            <a href="{{ $dom->whatsappUrl() }}"
                               target="_blank"
                               class="rounded-lg bg-green-50 p-2 text-green-600 hover:bg-green-100 transition"
                               title="WhatsApp">
                                <i class="fa-brands fa-whatsapp"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl bg-white p-12 text-center text-slate-400 shadow">
                <i class="fa-solid fa-motorcycle text-4xl mb-3 block"></i>
                Aún no tienes domiciliarios registrados.
            </div>
        @endforelse
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl my-8">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 sticky top-0 bg-white rounded-t-2xl">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ $modoEdicion ? 'Editar domiciliario' : 'Nuevo domiciliario' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                        <input type="text" wire:model="nombre"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Teléfono con indicativo --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
                        <div class="flex gap-2">
                            <select wire:model="pais_codigo"
                                    class="w-32 rounded-xl border border-slate-200 px-2 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                @foreach($paises as $p)
                                    <option value="{{ $p['codigo'] }}">{{ $p['flag'] }} {{ $p['codigo'] }}</option>
                                @endforeach
                            </select>
                            <input type="tel" wire:model="telefono" placeholder="3001234567" inputmode="numeric"
                                   class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Estado *</label>
                        <select wire:model="estado"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            <option value="disponible">Disponible</option>
                            <option value="ocupado">Ocupado</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Vehículo</label>
                            <select wire:model="vehiculo"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                <option value="">Sin vehículo</option>
                                <option value="Moto">Moto</option>
                                <option value="Bicicleta">Bicicleta</option>
                                <option value="Carro">Carro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Placa</label>
                            <input type="text" wire:model="placa" placeholder="ABC123"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand uppercase">
                        </div>
                    </div>

                    {{-- Zonas de cobertura asignadas --}}
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-slate-700">
                                <i class="fa-solid fa-map-location-dot text-brand mr-1"></i>
                                Zonas que cubre
                            </label>
                            <span class="text-xs text-slate-400">{{ count($zonasIds) }} seleccionada(s)</span>
                        </div>

                        @if($zonasDisponibles->count() === 0)
                            <p class="text-xs text-slate-400 italic">
                                No hay zonas activas. Crea zonas primero en
                                <a href="{{ route('zonas.index') }}" class="text-brand underline">Zonas de cobertura</a>.
                            </p>
                        @else
                            <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto">
                                @foreach($zonasDisponibles as $z)
                                    <label class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 cursor-pointer hover:bg-slate-100 transition">
                                        <input type="checkbox" wire:model="zonasIds" value="{{ $z->id }}"
                                               class="rounded border-slate-300 text-brand">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $z->color }}"></span>
                                        <span class="text-xs text-slate-700 truncate">{{ $z->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-brand">
                        <span class="text-sm text-slate-700">Domiciliario activo</span>
                    </label>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
