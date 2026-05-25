<div class="px-6 lg:px-10 py-8">

    @once
    @push('scripts')
    <script>
        // Helper: encuentra el componente Livewire de Categorias buscando por método 'eliminar'
        window._findCategoriaComponent = function() {
            if (!window.Livewire) return null;
            // Buscar todos los componentes con wire:id y elegir el que tenga el método 'eliminar'
            const roots = document.querySelectorAll('[wire\\:id]');
            for (const root of roots) {
                const id = root.getAttribute('wire:id');
                const cmp = Livewire.find(id);
                // Verificar que sea el componente de Categorias buscando un wire:click="eliminar..."
                if (root.querySelector('button[onclick*="confirmarEliminarCategoria"]')) {
                    return cmp;
                }
            }
            // Fallback: el primero
            return roots.length > 0 ? Livewire.find(roots[0].getAttribute('wire:id')) : null;
        };

        window.confirmarEliminarCategoria = function(catId, catNombre, productosCount) {
            const callEliminar = function() {
                const cmp = window._findCategoriaComponent();
                if (cmp) {
                    cmp.call('eliminar', catId);
                } else {
                    console.error('No se encontró el componente Livewire de Categorias');
                    alert('Error: no se pudo conectar con el sistema. Recarga la página.');
                }
            };

            if (typeof Swal === 'undefined') {
                if (confirm('¿Seguro que deseas eliminar la categoría "' + catNombre + '"?')) {
                    callEliminar();
                }
                return;
            }

            const tieneProductos = productosCount > 0;

            Swal.fire({
                title: '¿Eliminar categoría?',
                html: `
                    <div style="text-align:left;font-size:14px;color:#475569">
                        <div style="background:#fef2f2;padding:12px 14px;border-radius:10px;border-left:4px solid #ef4444;margin-bottom:10px">
                            <div style="font-size:11px;text-transform:uppercase;color:#dc2626;letter-spacing:0.05em;font-weight:700">Categoría a eliminar</div>
                            <div style="font-weight:800;color:#0f172a;font-size:16px;margin-top:2px"><i class="fa-solid fa-folder" style="color:#f59e0b"></i> ${catNombre}</div>
                        </div>
                        ${tieneProductos ? `
                            <div style="background:#fef3c7;padding:12px 14px;border-radius:10px;border-left:4px solid #f59e0b">
                                <div style="font-size:12px;color:#92400e;font-weight:700">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Esta categoría tiene <b>${productosCount} producto(s)</b> asignados.
                                </div>
                                <div style="font-size:11px;color:#a16207;margin-top:4px">
                                    Los productos quedarán sin categoría tras eliminarla.
                                </div>
                            </div>
                        ` : `
                            <div style="background:#f0fdf4;padding:10px 14px;border-radius:10px;font-size:12px;color:#166534">
                                <i class="fa-solid fa-circle-check"></i> Esta categoría no tiene productos asociados.
                            </div>
                        `}
                        <div style="margin-top:10px;font-size:12px;color:#94a3b8;text-align:center">
                            Esta acción no se puede deshacer
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: '<i class="fa-solid fa-trash-can"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                focusCancel: true,
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-xl px-5 py-2.5 font-bold',
                    cancelButton: 'rounded-xl px-5 py-2.5 font-bold',
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    callEliminar();
                }
            });
        };
    </script>
    @endpush
    @endonce

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Categorías</h2>
            <p class="text-sm text-slate-500">Organiza tus productos por categorías.</p>
        </div>

        <button wire:click="abrirModalCrear"
                class="rounded-2xl bg-brand px-5 py-3 text-white font-semibold shadow hover:bg-brand-dark transition">
            <i class="fa-solid fa-plus mr-2"></i> Nueva categoría
        </button>
    </div>

    {{-- SEARCH --}}
    <div class="mb-4">
        <input type="text"
               wire:model.live.debounce.400ms="search"
               placeholder="Buscar categoría..."
               class="w-full md:w-96 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">
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
                                    {{ $cat->icono_emoji ?: '<i class="fa-solid fa-box"></i>' }}
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
                            <button type="button"
                                    onclick="window.confirmarEliminarCategoria({{ $cat->id }}, @js($cat->nombre), {{ $cat->productos_count }})"
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
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="descripcion"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Emoji</label>
                            <input type="text" wire:model="icono_emoji" placeholder="🥩"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-center text-2xl focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Color</label>
                            <input type="color" wire:model="color"
                                   class="w-full h-11 rounded-xl border border-slate-200">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-brand">
                        <span class="text-sm text-slate-700">Categoría activa</span>
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
