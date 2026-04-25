<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Productos</h2>
            <p class="text-sm text-slate-500">Catálogo disponible para ventas y bot WhatsApp.</p>
        </div>

        <div class="flex items-center gap-2">
            {{-- Toggle vista --}}
            <div class="inline-flex items-center rounded-xl bg-white p-1 shadow border border-slate-200">
                <button wire:click="$set('vista', 'tabla')"
                        class="px-3 py-2 text-xs font-semibold rounded-lg transition
                              {{ $vista === 'tabla' ? 'bg-brand text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
                    <i class="fa-solid fa-table-list mr-1.5"></i> Tabla
                </button>
                <button wire:click="$set('vista', 'grid')"
                        class="px-3 py-2 text-xs font-semibold rounded-lg transition
                              {{ $vista === 'grid' ? 'bg-brand text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
                    <i class="fa-solid fa-th-large mr-1.5"></i> Grid
                </button>
            </div>

            <button wire:click="abrirModalCrear"
                    class="rounded-2xl bg-brand px-5 py-3 text-white font-semibold shadow hover:bg-brand-dark transition">
                <i class="fa-solid fa-plus mr-2"></i> Nuevo producto
            </button>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Buscar por nombre o código..."
               class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">

        <select wire:model.live="filtroCategoria"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">
            <option value="">Todas las categorías</option>
            @foreach($categorias as $cat)
                <option value="{{ $cat->id }}">{{ $cat->icono_emoji }} {{ $cat->nombre }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtroEstado"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">
            <option value="todos">Todos los estados</option>
            <option value="activos">Solo activos</option>
            <option value="inactivos">Solo inactivos</option>
        </select>
    </div>

    {{-- KPI bar --}}
    @php
        $totalProductos    = $productos->total();
        $productosActivos  = $productos->getCollection()->where('activo', true)->count();
        $productosDestacados = $productos->getCollection()->where('destacado', true)->count();
    @endphp

    <div class="mb-4 inline-flex items-center gap-4 rounded-full bg-white shadow border border-slate-200 px-4 py-2 text-xs">
        <span class="font-bold text-slate-800">
            <i class="fa-solid fa-boxes-stacked text-brand mr-1"></i>
            {{ $totalProductos }} productos
        </span>
        <span class="text-slate-300">·</span>
        <span class="text-emerald-700 font-semibold">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ $productosActivos }} activos
        </span>
        <span class="text-slate-300">·</span>
        <span class="text-amber-700 font-semibold">
            <i class="fa-solid fa-star mr-1"></i> {{ $productosDestacados }} destacados
        </span>
    </div>

    {{-- ╔═══ VISTA TABLA ═══╗ --}}
    @if($vista === 'tabla')
        <div class="overflow-hidden rounded-2xl bg-white shadow border border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gradient-to-r from-slate-50 to-white border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">Producto</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap hidden md:table-cell">Código</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap hidden lg:table-cell">Categoría</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap hidden xl:table-cell">Unidad</th>
                            <th class="px-3 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">Precio</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap hidden lg:table-cell">Sedes</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">Estado</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">Destacado</th>
                            <th class="px-3 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse($productos as $producto)
                            <tr class="group transition hover:bg-amber-50/30 {{ !$producto->activo ? 'opacity-60' : '' }}">

                                {{-- Producto: imagen + nombre --}}
                                <td class="px-3 py-3 align-middle">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-amber-50 to-orange-100 border border-amber-100">
                                            @if($producto->urlImagen())
                                                <img src="{{ $producto->urlImagen() }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                <span class="text-xl">{{ $producto->categoria?->icono_emoji ?? '📦' }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0 max-w-[220px]">
                                            <div class="font-semibold text-slate-800 truncate">{{ $producto->nombre }}</div>
                                            @if($producto->descripcion_corta)
                                                <div class="text-xs text-slate-500 truncate">{{ $producto->descripcion_corta }}</div>
                                            @endif
                                            <div class="md:hidden text-[10px] text-slate-400 font-mono">{{ $producto->codigo }}</div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Código --}}
                                <td class="px-3 py-3 align-middle hidden md:table-cell">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-mono font-bold text-slate-700">
                                        {{ $producto->codigo ?: '—' }}
                                    </span>
                                </td>

                                {{-- Categoría --}}
                                <td class="px-3 py-3 align-middle hidden lg:table-cell">
                                    @if($producto->categoria)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-700"
                                              style="color: {{ $producto->categoria->color ?? '#475569' }}">
                                            {{ $producto->categoria->icono_emoji }}
                                            {{ $producto->categoria->nombre }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400 italic">Sin categoría</span>
                                    @endif
                                </td>

                                {{-- Unidad --}}
                                <td class="px-3 py-3 align-middle hidden xl:table-cell">
                                    <span class="text-xs text-slate-600 capitalize">{{ $producto->unidad }}</span>
                                </td>

                                {{-- Precio --}}
                                <td class="px-3 py-3 align-middle text-right">
                                    <div class="font-bold text-slate-900">${{ number_format($producto->precio_base, 0, ',', '.') }}</div>
                                    <div class="text-[10px] text-slate-400">por {{ $producto->unidad }}</div>
                                </td>

                                {{-- Sedes --}}
                                <td class="px-3 py-3 align-middle text-center hidden lg:table-cell">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                        <i class="fa-solid fa-store text-[10px] text-brand"></i>
                                        {{ $producto->sedes->count() }}
                                    </span>
                                </td>

                                {{-- Estado toggle --}}
                                <td class="px-3 py-3 align-middle text-center">
                                    <button wire:click="toggleActivo({{ $producto->id }})"
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors
                                                  {{ $producto->activo ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow-md transition-transform
                                                    {{ $producto->activo ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                                    </button>
                                </td>

                                {{-- Destacado --}}
                                <td class="px-3 py-3 align-middle text-center">
                                    <button wire:click="toggleDestacado({{ $producto->id }})"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition
                                                  {{ $producto->destacado ? 'bg-amber-100 text-amber-500 hover:bg-amber-200' : 'text-slate-300 hover:bg-slate-100 hover:text-amber-400' }}">
                                        <i class="fa-solid fa-star text-sm"></i>
                                    </button>
                                </td>

                                {{-- Acciones --}}
                                <td class="px-3 py-3 align-middle">
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="abrirModalEditar({{ $producto->id }})"
                                                class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-brand transition"
                                                title="Editar">
                                            <i class="fa-solid fa-pen-to-square text-sm"></i>
                                        </button>
                                        <button wire:click="eliminar({{ $producto->id }})"
                                                wire:confirm="¿Eliminar este producto?"
                                                class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-500 transition"
                                                title="Eliminar">
                                            <i class="fa-solid fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-slate-400">
                                        <i class="fa-solid fa-box-open text-xl"></i>
                                    </div>
                                    <h3 class="mt-3 text-base font-semibold text-slate-700">Sin productos</h3>
                                    <p class="mt-1 text-sm text-slate-500">Crea el primer producto para empezar a vender.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $productos->links() }}
            </div>
        </div>

    {{-- ╔═══ VISTA GRID (cards) ═══╗ --}}
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @forelse($productos as $producto)
                <div class="rounded-2xl bg-white shadow hover:shadow-lg transition overflow-hidden">
                    <div class="relative h-40 bg-gradient-to-br from-amber-50 to-orange-100 flex items-center justify-center">
                        @if($producto->urlImagen())
                            <img src="{{ $producto->urlImagen() }}" alt="{{ $producto->nombre }}" class="h-full w-full object-cover">
                        @else
                            <span class="text-5xl">{{ $producto->categoria?->icono_emoji ?? '📦' }}</span>
                        @endif

                        <button wire:click="toggleDestacado({{ $producto->id }})"
                                class="absolute top-2 right-2 h-8 w-8 rounded-full backdrop-blur transition
                                       {{ $producto->destacado ? 'bg-amber-400 text-white' : 'bg-white/80 text-slate-400' }}">
                            <i class="fa-solid fa-star text-xs"></i>
                        </button>

                        @if(!$producto->activo)
                            <div class="absolute top-2 left-2 rounded-full bg-slate-700 px-2 py-1 text-xs font-medium text-white">Inactivo</div>
                        @endif
                    </div>

                    <div class="p-4">
                        <div class="text-xs text-slate-400 truncate">{{ $producto->codigo }}</div>
                        <h3 class="font-semibold text-slate-800 truncate">{{ $producto->nombre }}</h3>
                        <div class="text-xs text-slate-500">{{ $producto->categoria?->nombre ?? 'Sin categoría' }}</div>

                        <div class="mt-3 flex items-baseline justify-between">
                            <div>
                                <div class="text-xl font-bold text-brand">${{ number_format($producto->precio_base, 0, ',', '.') }}</div>
                                <div class="text-xs text-slate-400">por {{ $producto->unidad }}</div>
                            </div>
                            <div class="text-xs text-slate-500">
                                <i class="fa-solid fa-store mr-1"></i>{{ $producto->sedes->count() }} sede(s)
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <button wire:click="toggleActivo({{ $producto->id }})"
                                    class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition
                                          {{ $producto->activo ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' }}">
                                {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                            </button>
                            <button wire:click="abrirModalEditar({{ $producto->id }})"
                                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 transition">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button wire:click="eliminar({{ $producto->id }})" wire:confirm="¿Eliminar este producto?"
                                    class="rounded-lg p-2 text-red-500 hover:bg-red-50 transition">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl bg-white p-12 text-center text-slate-400 shadow">
                    <i class="fa-solid fa-box-open text-4xl mb-3 block"></i>
                    No hay productos. Crea el primero para empezar a vender.
                </div>
            @endforelse
        </div>

        <div class="mt-6">{{ $productos->links() }}</div>
    @endif

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 overflow-y-auto"
             wire:click.self="cerrarModal"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">
            <div class="w-full sm:max-w-3xl rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl my-0 sm:my-8 max-h-[95vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 sticky top-0 bg-white rounded-t-2xl shrink-0">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ $editandoId ? 'Editar producto' : 'Nuevo producto' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5 overflow-y-auto">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Código (SKU)</label>
                            <input type="text" wire:model="codigo"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Categoría</label>
                            <select wire:model="categoria_id"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                <option value="">Sin categoría</option>
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->icono_emoji }} {{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Unidad *</label>
                            <select wire:model="unidad"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                <option value="unidad">Unidad</option>
                                <option value="libra">Libra</option>
                                <option value="kg">Kilogramo</option>
                                <option value="gramo">Gramo</option>
                                <option value="litro">Litro</option>
                                <option value="paquete">Paquete</option>
                                <option value="docena">Docena</option>
                                <option value="bandeja">Bandeja</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Precio base *</label>
                            <input type="number" step="0.01" wire:model="precio_base"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            @error('precio_base') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción corta</label>
                        <input type="text" wire:model="descripcion_corta" placeholder="Para el bot y tarjetas"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción larga</label>
                        <textarea wire:model="descripcion" rows="2"
                                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand"></textarea>
                    </div>

                    {{-- IMAGEN: subir archivo o pegar URL --}}
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h4 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-camera text-brand"></i> Imagen del producto
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                            {{-- Preview --}}
                            <div class="md:col-span-1">
                                <div class="aspect-square w-full rounded-xl bg-gradient-to-br from-amber-50 to-orange-100 border border-amber-100 flex items-center justify-center overflow-hidden relative">
                                    @if($imagenFile)
                                        <img src="{{ $imagenFile->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                                        <div class="absolute bottom-2 left-2 right-2 rounded-lg bg-amber-500/90 px-2 py-1 text-[10px] font-bold text-white text-center">
                                            <i class="fa-solid fa-clock mr-1"></i> Pendiente de guardar
                                        </div>
                                    @elseif($imagen_path)
                                        <img src="{{ asset('storage/' . $imagen_path) }}" alt="" class="h-full w-full object-cover">
                                        <button type="button" wire:click="quitarImagenActual"
                                                wire:confirm="¿Quitar la imagen actual?"
                                                class="absolute top-2 right-2 h-7 w-7 rounded-full bg-red-500 text-white shadow hover:bg-red-600 transition">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    @elseif($imagen_url)
                                        <img src="{{ $imagen_url }}" alt="" class="h-full w-full object-cover"
                                             onerror="this.style.display='none'">
                                    @else
                                        <div class="text-center text-slate-400">
                                            <i class="fa-solid fa-image text-3xl mb-2"></i>
                                            <p class="text-xs">Sin imagen</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Inputs --}}
                            <div class="md:col-span-2 space-y-3">

                                {{-- Subir archivo --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        <i class="fa-solid fa-cloud-arrow-up mr-1"></i> Subir desde tu equipo
                                    </label>
                                    <input type="file" wire:model="imagenFile"
                                           accept="image/jpeg,image/png,image/webp"
                                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-white file:font-semibold file:cursor-pointer">
                                    @error('imagenFile') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                    <p class="text-[10px] text-slate-400 mt-1">JPG, PNG o WebP — máx 2 MB</p>

                                    <div wire:loading wire:target="imagenFile" class="text-xs text-amber-600 mt-1">
                                        <i class="fa-solid fa-spinner fa-spin"></i> Cargando imagen...
                                    </div>
                                </div>

                                <div class="text-center text-[10px] text-slate-400 uppercase font-semibold">— O —</div>

                                {{-- URL externa --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        <i class="fa-solid fa-link mr-1"></i> URL externa
                                    </label>
                                    <input type="url" wire:model="imagen_url" placeholder="https://..."
                                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-brand focus:ring-brand">
                                    <p class="text-[10px] text-slate-400 mt-1">Si subes archivo, este URL se ignora.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Palabras clave <span class="text-xs text-slate-400">(coma — para que el bot encuentre el producto)</span>
                        </label>
                        <input type="text" wire:model="palabrasClaveTexto" placeholder="pollo, pechuga, deshuesada"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4">
                        <h4 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-store text-brand"></i> Precios y disponibilidad por sede
                        </h4>
                        @if(count($preciosSedes) === 0)
                            <p class="text-xs text-slate-400">No hay sedes registradas.</p>
                        @else
                            <p class="text-xs text-slate-500 mb-3">Si dejas el precio en blanco, se usa el precio base.</p>
                            <div class="space-y-2">
                                @foreach($sedes as $sede)
                                    <div class="grid grid-cols-12 gap-2 items-center bg-slate-50 rounded-lg px-3 py-2">
                                        <div class="col-span-5 text-sm font-medium text-slate-700 truncate">{{ $sede->nombre }}</div>
                                        <div class="col-span-4">
                                            <input type="number" step="0.01"
                                                   wire:model="preciosSedes.{{ $sede->id }}.precio"
                                                   placeholder="Precio personalizado"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-brand focus:ring-brand">
                                        </div>
                                        <div class="col-span-3">
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                <input type="checkbox" wire:model="preciosSedes.{{ $sede->id }}.disponible"
                                                       class="rounded border-slate-300 text-brand">
                                                Disponible
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- ✂️ CORTES DISPONIBLES PARA ESTE PRODUCTO --}}
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h4 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-scissors text-brand"></i> Cortes disponibles para este producto
                        </h4>
                        @if($cortes->isEmpty())
                            <p class="text-xs text-slate-500">
                                No hay cortes registrados.
                                <a href="{{ route('cortes.index') }}" class="text-brand underline">Crear cortes →</a>
                            </p>
                        @else
                            <p class="text-xs text-slate-500 mb-3">
                                Marca los cortes que aplican para este producto. La IA los ofrecerá al cliente.
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-64 overflow-y-auto">
                                @foreach($cortes as $corte)
                                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 hover:bg-amber-50 border border-slate-200 cursor-pointer transition">
                                        <input type="checkbox" wire:model="corteIds" value="{{ $corte->id }}"
                                               class="rounded text-brand focus:ring-brand">
                                        <span class="text-sm text-slate-700">{{ $corte->nombre }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-brand">
                            <span class="text-sm text-slate-700">Activo</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="destacado" class="rounded border-slate-300 text-brand">
                            <span class="text-sm text-slate-700">Destacado</span>
                        </label>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">
                            Guardar producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
