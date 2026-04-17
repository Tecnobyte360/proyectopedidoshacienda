<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Categorías</h2>
            <p class="text-sm text-slate-500">Organiza tus productos por categorías.</p>
        </div>

        <button wire:click="abrirModalCrear"
                class="rounded-2xl bg-[#d68643] px-5 py-3 text-white font-semibold shadow hover:bg-[#c97a36] transition">
            <i class="fa-solid fa-plus mr-2"></i> Nueva categoría
        </button>
    </div>

    {{-- SEARCH --}}
    <div class="mb-4">
        <input type="text"
               wire:model.live.debounce.400ms="search"
               placeholder="Buscar categoría..."
               class="w-full md:w-96 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-[#d68643] focus:ring-[#d68643]">
    </div>

    {{-- TABLE --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3 text-center">Productos</th>
                    <th class="px-4 py-3 text-center">Orden</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($categorias as $cat)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 font-mono text-slate-400">{{ $cat->id }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl text-lg"
                                      style="background-color: {{ $cat->color ?? '#fef3c7' }}20;">
                                    {{ $cat->icono_emoji ?: '📦' }}
                                </span>
                                <div>
                                    <div class="font-semibold text-slate-800">{{ $cat->nombre }}</div>
                                    @if($cat->descripcion)
                                        <div class="text-xs text-slate-500">{{ $cat->descripcion }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                {{ $cat->productos_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-slate-500">{{ $cat->orden }}</td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="toggleActivo({{ $cat->id }})"
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition
                                          {{ $cat->activo ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-slate-200 text-slate-600 hover:bg-slate-300' }}">
                                {{ $cat->activo ? 'Activa' : 'Inactiva' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="abrirModalEditar({{ $cat->id }})"
                                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button wire:click="eliminar({{ $cat->id }})"
                                    wire:confirm="¿Seguro que deseas eliminar esta categoría?"
                                    class="rounded-lg p-2 text-red-500 hover:bg-red-50 transition">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-folder-open text-3xl mb-2 block"></i>
                            No hay categorías aún. Crea la primera.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $categorias->links() }}
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ $editandoId ? 'Editar categoría' : 'Nueva categoría' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                        <input type="text" wire:model="nombre"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="descripcion"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Emoji</label>
                            <input type="text" wire:model="icono_emoji" placeholder="🥩"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-center text-2xl focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Color</label>
                            <input type="color" wire:model="color"
                                   class="w-full h-11 rounded-xl border border-slate-200">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-[#d68643]">
                        <span class="text-sm text-slate-700">Categoría activa</span>
                    </label>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-[#d68643] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#c97a36]">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
