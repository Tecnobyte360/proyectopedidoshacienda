<div class="min-h-screen bg-slate-50 text-slate-800">
    <div class="w-full px-4 py-4 sm:px-6 lg:px-8">

        {{-- HEADER --}}
        <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-violet-500 via-fuchsia-500 to-pink-500"></div>

            <div class="flex flex-col gap-4 px-6 py-6 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-pink-500 text-white shadow-lg">
                        <i class="fa-solid fa-motorcycle text-xl"></i>
                    </div>

                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-violet-700 ring-1 ring-violet-100">
                            <span class="h-2 w-2 rounded-full bg-violet-500 animate-pulse"></span>
                            Logística de entrega
                        </div>

                        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 md:text-4xl">
                            Gestión de Domiciliarios
                        </h1>

                        <p class="mt-2 text-sm text-slate-500 md:text-base">
                            Administra los domiciliarios disponibles, ocupados e inactivos para enrutar pedidos.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative min-w-[260px]">
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="buscar"
                            placeholder="Buscar por nombre, placa, teléfono..."
                            class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-11 pr-4 text-sm font-medium text-slate-700 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100"
                        >
                    </div>

                    <button
                        wire:click="abrirModalCrear"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-slate-900 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Nuevo domiciliario
                    </button>
                </div>
            </div>
        </div>

        {{-- KPIS --}}
        @php
            $total = $domiciliarios->count();
            $disponibles = $domiciliarios->where('estado', 'disponible')->where('activo', true)->count();
            $ocupados = $domiciliarios->where('estado', 'ocupado')->count();
            $inactivos = $domiciliarios->where('activo', false)->count();
        @endphp

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Total</p>
                <div class="mt-2 flex items-center justify-between">
                    <h2 class="text-3xl font-black text-slate-900">{{ $total }}</h2>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-600">Disponibles</p>
                <div class="mt-2 flex items-center justify-between">
                    <h2 class="text-3xl font-black text-emerald-600">{{ $disponibles }}</h2>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-amber-600">Ocupados</p>
                <div class="mt-2 flex items-center justify-between">
                    <h2 class="text-3xl font-black text-amber-600">{{ $ocupados }}</h2>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <i class="fa-solid fa-road"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-rose-600">Inactivos</p>
                <div class="mt-2 flex items-center justify-between">
                    <h2 class="text-3xl font-black text-rose-600">{{ $inactivos }}</h2>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLA --}}
        <div class="mt-4 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-600">
                    <i class="fa-solid fa-database text-[10px]"></i>
                    {{ $domiciliarios->count() }} registros
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Teléfono</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Vehículo</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Placa</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Estado</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Activo</th>
                            <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($domiciliarios as $domiciliario)
                            @php
                                $badgeEstado = match($domiciliario->estado) {
                                    'disponible' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'ocupado' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'inactivo' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                                };
                            @endphp

                            <tr class="transition hover:bg-slate-50">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-sm font-black text-white">
                                            {{ strtoupper(mb_substr($domiciliario->nombre, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $domiciliario->nombre }}</div>
                                            <div class="text-xs text-slate-500">ID #{{ $domiciliario->id }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 text-sm font-medium text-slate-700">
                                    {{ $domiciliario->telefono ?: 'Sin teléfono' }}
                                </td>

                                <td class="px-4 py-3.5 text-sm font-medium text-slate-700">
                                    {{ $domiciliario->vehiculo ?: 'No definido' }}
                                </td>

                                <td class="px-4 py-3.5 text-sm font-medium text-slate-700">
                                    {{ $domiciliario->placa ?: 'Sin placa' }}
                                </td>

                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.15em] {{ $badgeEstado }}">
                                        <span class="h-2 w-2 rounded-full {{ $domiciliario->estado === 'disponible' ? 'bg-emerald-500' : ($domiciliario->estado === 'ocupado' ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
                                        {{ ucfirst($domiciliario->estado) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    @if($domiciliario->activo)
                                        <span class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">
                                            <i class="fa-solid fa-toggle-on"></i>
                                            Sí
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700">
                                            <i class="fa-solid fa-toggle-off"></i>
                                            No
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3.5 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            wire:click="abrirModalEditar({{ $domiciliario->id }})"
                                            class="inline-flex items-center gap-2 rounded-xl bg-violet-500 px-3 py-2 text-xs font-bold text-white transition hover:bg-violet-600"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                            Editar
                                        </button>

                                        <button
                                            wire:click="cambiarActivo({{ $domiciliario->id }})"
                                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            <i class="fa-solid fa-power-off"></i>
                                            {{ $domiciliario->activo ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-400">
                                        <i class="fa-solid fa-motorcycle text-2xl"></i>
                                    </div>
                                    <h3 class="mt-4 text-xl font-bold text-slate-800">No hay domiciliarios registrados</h3>
                                    <p class="mt-1 text-sm text-slate-500">Empieza creando tu primer domiciliario.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- MODAL --}}
        @if($modalAbierto)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background: rgba(15,23,42,0.55); backdrop-filter: blur(5px);">

                <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-5">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-600">
                            <i class="fa-solid {{ $modoEdicion ? 'fa-pen' : 'fa-plus' }}"></i>
                        </div>

                        <div>
                            <h3 class="text-lg font-black text-slate-800">
                                {{ $modoEdicion ? 'Editar domiciliario' : 'Nuevo domiciliario' }}
                            </h3>
                            <p class="text-sm text-slate-500">
                                Completa la información operativa del domiciliario.
                            </p>
                        </div>

                        <button
                            wire:click="cerrarModal"
                            class="ml-auto flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 px-6 py-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-bold text-slate-700">Nombre</label>
                            <input
                                type="text"
                                wire:model.live="nombre"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100"
                                placeholder="Ej: Carlos Pérez"
                            >
                            @error('nombre') <div class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-slate-700">Teléfono</label>
                            <input
                                type="text"
                                wire:model.live="telefono"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100"
                                placeholder="Ej: 3001234567"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-slate-700">Vehículo</label>
                            <input
                                type="text"
                                wire:model.live="vehiculo"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100"
                                placeholder="Ej: Moto"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-slate-700">Placa</label>
                            <input
                                type="text"
                                wire:model.live="placa"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100"
                                placeholder="Ej: ABC123"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-slate-700">Estado</label>
                            <select
                                wire:model.live="estado"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100"
                            >
                                <option value="disponible">Disponible</option>
                                <option value="ocupado">Ocupado</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                            @error('estado') <div class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</div> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 cursor-pointer">
                                <input type="checkbox" wire:model.live="activo" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                <span class="text-sm font-semibold text-slate-700">Domiciliario activo</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3 border-t border-slate-100 px-6 py-5">
                        <button
                            wire:click="cerrarModal"
                            class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </button>

                        <button
                            wire:click="guardar"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            {{ $modoEdicion ? 'Actualizar domiciliario' : 'Guardar domiciliario' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>