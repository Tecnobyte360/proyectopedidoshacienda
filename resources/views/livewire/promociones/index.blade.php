<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Promociones</h2>
            <p class="text-sm text-slate-500">Descuentos, combos y precios especiales.</p>
        </div>

        <button wire:click="abrirModalCrear"
                class="rounded-2xl bg-brand px-5 py-3 text-white font-semibold shadow hover:bg-brand-dark transition">
            <i class="fa-solid fa-plus mr-2"></i> Nueva promoción
        </button>
    </div>

    {{-- FILTROS --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-3">
        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Buscar promoción..."
               class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">

        <select wire:model.live="filtroEstado"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">
            <option value="todas">Todas</option>
            <option value="vigentes">Vigentes</option>
            <option value="inactivas">Inactivas</option>
        </select>
    </div>

    {{-- GRID DE PROMOCIONES --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($promociones as $promo)
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-50 to-orange-100 shadow hover:shadow-lg transition">
                @if($promo->imagen_url)
                    <img src="{{ $promo->imagen_url }}" class="absolute inset-0 h-full w-full object-cover opacity-30">
                @endif

                <div class="relative p-5">
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center rounded-full bg-white/80 backdrop-blur px-3 py-1 text-xs font-bold text-brand uppercase">
                            <i class="fa-solid fa-tag mr-1"></i>
                            {{ str_replace('_', ' ', $promo->tipo) }}
                        </span>

                        @if($promo->estaVigente())
                            <span class="inline-flex items-center rounded-full bg-green-500 px-3 py-1 text-xs font-medium text-white">
                                <span class="h-2 w-2 rounded-full bg-white animate-pulse mr-1.5"></span>
                                Vigente
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-400 px-3 py-1 text-xs font-medium text-white">
                                Inactiva
                            </span>
                        @endif
                    </div>

                    <h3 class="text-lg font-extrabold text-slate-800 mb-1">{{ $promo->nombre }}</h3>
                    <p class="text-sm text-slate-600 mb-3">{{ $promo->descripcionCorta() }}</p>

                    @if($promo->descripcion)
                        <p class="text-xs text-slate-500 mb-3">{{ $promo->descripcion }}</p>
                    @endif

                    <div class="space-y-1 text-xs text-slate-600 mb-4">
                        @if($promo->fecha_inicio || $promo->fecha_fin)
                            <div>
                                <i class="fa-solid fa-calendar-days text-brand mr-1"></i>
                                {{ $promo->fecha_inicio?->format('d/m/Y') ?? 'Sin inicio' }}
                                →
                                {{ $promo->fecha_fin?->format('d/m/Y') ?? 'Sin fin' }}
                            </div>
                        @endif

                        <div>
                            <i class="fa-solid fa-box mr-1 text-brand"></i>
                            {{ $promo->aplica_todos_productos ? 'Todos los productos' : $promo->productos->count() . ' producto(s)' }}
                        </div>

                        <div>
                            <i class="fa-solid fa-store mr-1 text-brand"></i>
                            {{ $promo->aplica_todas_sedes ? 'Todas las sedes' : $promo->sedes->count() . ' sede(s)' }}
                        </div>

                        @if($promo->codigo_cupon)
                            <div>
                                <i class="fa-solid fa-ticket mr-1 text-brand"></i>
                                Cupón: <span class="font-mono font-bold">{{ $promo->codigo_cupon }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-3 border-t border-white/50">
                        <button wire:click="toggleActiva({{ $promo->id }})"
                                class="text-xs font-medium px-3 py-1.5 rounded-lg transition
                                       {{ $promo->activa ? 'bg-green-500 text-white hover:bg-green-600' : 'bg-slate-300 text-slate-700 hover:bg-slate-400' }}">
                            {{ $promo->activa ? 'Activa' : 'Inactiva' }}
                        </button>

                        <div class="flex gap-1">
                            <button wire:click="abrirModalEditar({{ $promo->id }})"
                                    class="rounded-lg p-2 text-slate-600 hover:bg-white/50 transition">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button wire:click="eliminar({{ $promo->id }})"
                                    wire:confirm="¿Eliminar esta promoción?"
                                    class="rounded-lg p-2 text-red-500 hover:bg-white/50 transition">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl bg-white p-12 text-center text-slate-400 shadow">
                <i class="fa-solid fa-tags text-4xl mb-3 block"></i>
                Aún no tienes promociones.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $promociones->links() }}
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 sticky top-0 bg-white rounded-t-2xl">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ $editandoId ? 'Editar promoción' : 'Nueva promoción' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Cupón</label>
                            <input type="text" wire:model="codigo_cupon" placeholder="VERANO10"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="descripcion"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tipo *</label>
                            <select wire:model.live="tipo"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                <option value="porcentaje">Porcentaje (%)</option>
                                <option value="monto_fijo">Monto fijo ($)</option>
                                <option value="precio_especial">Precio especial</option>
                                <option value="nx1">N x 1 (combo)</option>
                            </select>
                        </div>

                        @if($tipo === 'nx1')
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Lleva</label>
                                <input type="number" wire:model="compra" min="1"
                                       class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Paga</label>
                                <input type="number" wire:model="paga" min="1"
                                       class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            </div>
                        @else
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Valor *</label>
                                <input type="number" step="0.01" wire:model="valor"
                                       class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                @error('valor') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Inicio</label>
                            <input type="datetime-local" wire:model="fecha_inicio"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fin</label>
                            <input type="datetime-local" wire:model="fecha_fin"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Imagen (URL)</label>
                        <input type="url" wire:model="imagen_url" placeholder="https://..."
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    </div>

                    {{-- PRODUCTOS --}}
                    <div class="rounded-xl border border-slate-200 p-4">
                        <label class="inline-flex items-center gap-2 mb-3">
                            <input type="checkbox" wire:model.live="aplica_todos_productos"
                                   class="rounded border-slate-300 text-brand">
                            <span class="text-sm font-medium text-slate-700">Aplicar a todos los productos</span>
                        </label>

                        @if(!$aplica_todos_productos)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                                @foreach($productos as $prod)
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 bg-slate-50 px-3 py-2 rounded-lg">
                                        <input type="checkbox"
                                               wire:model="productosSeleccionados"
                                               value="{{ $prod->id }}"
                                               class="rounded border-slate-300 text-brand">
                                        <span class="truncate">{{ $prod->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- SEDES --}}
                    <div class="rounded-xl border border-slate-200 p-4">
                        <label class="inline-flex items-center gap-2 mb-3">
                            <input type="checkbox" wire:model.live="aplica_todas_sedes"
                                   class="rounded border-slate-300 text-brand">
                            <span class="text-sm font-medium text-slate-700">Aplicar en todas las sedes</span>
                        </label>

                        @if(!$aplica_todas_sedes)
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                @foreach($sedes as $sede)
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 bg-slate-50 px-3 py-2 rounded-lg">
                                        <input type="checkbox"
                                               wire:model="sedesSeleccionadas"
                                               value="{{ $sede->id }}"
                                               class="rounded border-slate-300 text-brand">
                                        <span class="truncate">{{ $sede->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="activa" class="rounded border-slate-300 text-brand">
                            <span class="text-sm text-slate-700">Promoción activa</span>
                        </label>

                        <div class="flex items-center gap-2">
                            <label class="text-sm text-slate-700">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-24 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">
                            Guardar promoción
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
