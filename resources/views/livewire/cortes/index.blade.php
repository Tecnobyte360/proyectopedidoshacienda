<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white shadow-lg">
                        <i class="fa-solid fa-knife text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Cortes</h2>
                        <p class="text-sm text-slate-500">Tipos de corte disponibles y a qué productos aplican (Mariposa, Medallones, Molido, Goulash, etc.)</p>
                    </div>
                </div>
                <button wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nuevo corte
                </button>
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <input type="text" wire:model.live.debounce.300ms="busqueda"
                   placeholder="Buscar corte..."
                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($cortes as $c)
                <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm {{ !$c->activo ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#fbe9d7] text-2xl">
                                {{ $c->icono_emoji ?: '🔪' }}
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800">{{ $c->nombre }}</h3>
                                <p class="text-xs text-slate-500">Aplica a {{ $c->productos_count }} {{ $c->productos_count === 1 ? 'producto' : 'productos' }}</p>
                            </div>
                        </div>
                        @if($c->activo)
                            <span class="text-[10px] font-semibold text-emerald-700">● Activo</span>
                        @else
                            <span class="text-[10px] font-semibold text-slate-500">● Inactivo</span>
                        @endif
                    </div>

                    @if($c->descripcion)
                        <p class="text-xs text-slate-600 mb-3 italic">{{ $c->descripcion }}</p>
                    @endif

                    <div class="flex items-center justify-end gap-1 pt-2 border-t border-slate-100">
                        <button wire:click="abrirEditar({{ $c->id }})" class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>
                        <button wire:click="toggleActivo({{ $c->id }})" class="h-8 w-8 rounded-lg {{ $c->activo ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} transition">
                            <i class="fa-solid {{ $c->activo ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                        </button>
                        <button wire:click="eliminar({{ $c->id }})" wire:confirm="¿Eliminar este corte?"
                                class="h-8 w-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl bg-white border border-slate-200 p-12 text-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i class="fa-solid fa-knife text-2xl"></i>
                    </div>
                    <p class="text-base font-semibold text-slate-700">Sin cortes</p>
                    <p class="text-sm text-slate-500">Crea los tipos de corte que manejas (Mariposa, Medallones, Goulash, Molido, etc.).</p>
                </div>
            @endforelse
        </div>
    </div>

    @if($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white">
                    <h3 class="font-bold text-slate-800">{{ $editandoId ? 'Editar' : 'Nuevo' }} corte</h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="Mariposa, Medallones, Goulash, Molido..."
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Emoji</label>
                            <input type="text" wire:model="iconoEmoji" maxlength="4"
                                   class="w-full rounded-xl border border-slate-200 px-2 py-2 text-xl text-center">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Descripción (opcional)</label>
                        <input type="text" wire:model="descripcion" placeholder="Ej. Mínimo 100gr por corte"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-2">Productos a los que aplica este corte</label>
                        <div class="max-h-64 overflow-y-auto border border-slate-200 rounded-xl p-3 bg-slate-50 grid grid-cols-1 md:grid-cols-2 gap-1">
                            @foreach($productos as $p)
                                <label class="flex items-center gap-2 text-sm py-1 px-2 rounded hover:bg-white cursor-pointer">
                                    <input type="checkbox" wire:model="productoIds" value="{{ $p->id }}"
                                           class="rounded text-[#d68643]">
                                    <span class="text-slate-700">
                                        @if($p->codigo) <span class="text-[10px] font-mono text-slate-500">{{ $p->codigo }}</span> @endif
                                        {{ $p->nombre }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[10px] text-slate-500 mt-1">Selecciona todos los productos que pueden cortarse así.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="activo" class="rounded text-[#d68643]">
                                <span class="font-semibold text-slate-700">Activo</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                    <button wire:click="guardar" class="rounded-xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] px-5 py-2 text-sm font-bold text-white shadow-lg">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
