<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Tiempos ANS</h2>
            <p class="text-sm text-slate-500">Define los tiempos objetivo, alerta y crítico de cada estado del pedido.</p>
        </div>

        <button wire:click="abrirModalCrear"
                class="rounded-2xl bg-[#d68643] px-5 py-3 text-white font-semibold shadow hover:bg-[#c97a36] transition">
            <i class="fa-solid fa-plus mr-2"></i> Nuevo ANS
        </button>
    </div>

    {{-- LEYENDA --}}
    <div class="mb-6 rounded-2xl bg-white p-5 shadow border border-slate-200">
        <h3 class="text-sm font-bold text-slate-800 mb-3">¿Cómo funciona el semáforo?</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200 p-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-white">
                    <i class="fa-solid fa-circle-check text-xs"></i>
                </span>
                <div class="text-xs text-emerald-700">
                    <strong class="block text-emerald-800">VERDE</strong>
                    Hasta el tiempo objetivo. Todo en orden.
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-amber-50 border border-amber-200 p-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-white">
                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                </span>
                <div class="text-xs text-amber-700">
                    <strong class="block text-amber-800">AMARILLO</strong>
                    Pasó la alerta. Atención: cerca del límite.
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 p-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-500 text-white">
                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                </span>
                <div class="text-xs text-rose-700">
                    <strong class="block text-rose-800">ROJO</strong>
                    Pasó el crítico. Vencido — acción urgente.
                </div>
            </div>
        </div>
    </div>

    {{-- GRID DE ANS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($ans as $a)
            @php
                $estadoColor = match($a->estado) {
                    'nuevo'                 => 'bg-blue-50 border-blue-200 text-blue-700',
                    'en_preparacion'        => 'bg-amber-50 border-amber-200 text-amber-700',
                    'repartidor_en_camino'  => 'bg-violet-50 border-violet-200 text-violet-700',
                    default                 => 'bg-slate-50 border-slate-200 text-slate-700',
                };

                $estadoIcono = match($a->estado) {
                    'nuevo'                 => 'fa-bell',
                    'en_preparacion'        => 'fa-gears',
                    'repartidor_en_camino'  => 'fa-motorcycle',
                    default                 => 'fa-circle',
                };
            @endphp

            <div class="rounded-2xl bg-white shadow hover:shadow-lg transition overflow-hidden">

                <div class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl border {{ $estadoColor }}">
                                <i class="fa-solid {{ $estadoIcono }} text-base"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-slate-800 truncate">{{ $a->nombre }}</h3>
                                <span class="inline-flex rounded-md border px-2 py-0.5 text-[10px] font-mono font-medium {{ $estadoColor }}">
                                    {{ $a->estado }}
                                </span>
                            </div>
                        </div>

                        <button wire:click="toggleActivo({{ $a->id }})"
                                class="text-xs px-2.5 py-1 rounded-full font-medium transition
                                       {{ $a->activo ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' }}">
                            {{ $a->activo ? 'Activo' : 'Inactivo' }}
                        </button>
                    </div>

                    @if($a->descripcion)
                        <p class="text-xs text-slate-500 mb-4">{{ $a->descripcion }}</p>
                    @endif

                    {{-- Tiempos --}}
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="rounded-lg border-l-4 border-emerald-500 bg-emerald-50 px-3 py-2">
                            <div class="text-[9px] font-bold uppercase text-emerald-600">Objetivo</div>
                            <div class="text-lg font-extrabold text-emerald-800">{{ $a->minutos_objetivo }}<span class="text-xs font-normal">min</span></div>
                        </div>
                        <div class="rounded-lg border-l-4 border-amber-500 bg-amber-50 px-3 py-2">
                            <div class="text-[9px] font-bold uppercase text-amber-600">Alerta</div>
                            <div class="text-lg font-extrabold text-amber-800">{{ $a->minutos_alerta }}<span class="text-xs font-normal">min</span></div>
                        </div>
                        <div class="rounded-lg border-l-4 border-rose-500 bg-rose-50 px-3 py-2">
                            <div class="text-[9px] font-bold uppercase text-rose-600">Crítico</div>
                            <div class="text-lg font-extrabold text-rose-800">{{ $a->minutos_critico }}<span class="text-xs font-normal">min</span></div>
                        </div>
                    </div>

                    {{-- Visualización de la barra --}}
                    <div class="mb-3">
                        <div class="text-[10px] font-semibold text-slate-500 uppercase mb-1">Vista previa del semáforo</div>
                        <div class="relative h-3 w-full rounded-full bg-slate-100 overflow-hidden">
                            @php
                                $pctVerde   = $a->minutos_critico > 0 ? min(100, ($a->minutos_alerta / $a->minutos_critico) * 100) : 0;
                                $pctAmarillo = $a->minutos_critico > 0 ? min(100, (($a->minutos_critico - $a->minutos_alerta) / $a->minutos_critico) * 100) : 0;
                            @endphp
                            <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-emerald-400 to-emerald-500" style="width: {{ $pctVerde }}%"></div>
                            <div class="absolute inset-y-0 bg-gradient-to-r from-amber-400 to-amber-500" style="left: {{ $pctVerde }}%; width: {{ $pctAmarillo }}%"></div>
                        </div>
                        <div class="flex justify-between text-[9px] text-slate-400 mt-0.5">
                            <span>0</span>
                            <span>{{ $a->minutos_critico }}min</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-1 pt-3 border-t border-slate-100">
                        <button wire:click="abrirModalEditar({{ $a->id }})"
                                class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button wire:click="eliminar({{ $a->id }})"
                                wire:confirm="¿Eliminar este ANS?"
                                class="rounded-lg p-2 text-red-500 hover:bg-red-50 transition">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl bg-white p-12 text-center text-slate-400 shadow">
                <i class="fa-solid fa-stopwatch text-4xl mb-3 block"></i>
                Aún no hay ANS configurados.
            </div>
        @endforelse
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">

            <div class="w-full sm:max-w-xl rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl my-0 sm:my-8 max-h-[95vh] flex flex-col">

                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 shrink-0">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ $editandoId ? 'Editar ANS' : 'Nuevo ANS' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4 overflow-y-auto">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Estado *</label>
                            <select wire:model="estado"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                                @foreach($estadosDisponibles as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('estado') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="Ej: Atención inicial"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                            @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="descripcion" placeholder="Para qué sirve este ANS"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    </div>

                    {{-- Tiempos --}}
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h4 class="text-sm font-bold text-slate-800 mb-3">Configuración del semáforo (en minutos)</h4>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-emerald-700 mb-1">
                                    <i class="fa-solid fa-circle-check mr-1"></i> Objetivo
                                </label>
                                <input type="number" wire:model="minutos_objetivo" min="1"
                                       class="w-full rounded-xl border border-emerald-200 bg-emerald-50/50 px-3 py-2 text-sm font-bold text-emerald-800 focus:border-emerald-400 focus:bg-white focus:ring-2 focus:ring-emerald-100">
                                @error('minutos_objetivo') <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-amber-700 mb-1">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> Alerta
                                </label>
                                <input type="number" wire:model="minutos_alerta" min="1"
                                       class="w-full rounded-xl border border-amber-200 bg-amber-50/50 px-3 py-2 text-sm font-bold text-amber-800 focus:border-amber-400 focus:bg-white focus:ring-2 focus:ring-amber-100">
                                @error('minutos_alerta') <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-rose-700 mb-1">
                                    <i class="fa-solid fa-circle-exclamation mr-1"></i> Crítico
                                </label>
                                <input type="number" wire:model="minutos_critico" min="1"
                                       class="w-full rounded-xl border border-rose-200 bg-rose-50/50 px-3 py-2 text-sm font-bold text-rose-800 focus:border-rose-400 focus:bg-white focus:ring-2 focus:ring-rose-100">
                                @error('minutos_critico') <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 mt-3">
                            <i class="fa-solid fa-info-circle"></i>
                            Regla: <strong>Objetivo ≤ Alerta ≤ Crítico</strong>. Ejemplo: 5 / 8 / 12 minutos.
                        </p>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-[#d68643]">
                            <span class="text-sm text-slate-700">ANS activo</span>
                        </label>

                        <div class="flex items-center gap-2">
                            <label class="text-sm text-slate-700">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-20 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-[#d68643] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#c97a36]">
                            Guardar ANS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
