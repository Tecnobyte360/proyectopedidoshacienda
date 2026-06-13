<div class="min-h-screen bg-slate-50/50">
    <div class="px-4 lg:px-8 py-6 max-w-6xl mx-auto pb-24">

        {{-- Header --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
            <div class="h-1 bg-gradient-to-r from-brand to-brand-secondary"></div>
            <div class="p-5 sm:p-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand text-white shadow-md">
                        <i class="fa-solid fa-users-rectangle text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-800">Grupos de clientes</h2>
                        <p class="text-sm text-slate-500 mt-0.5">Armá listas y enviales un mensaje a todos por plantilla</p>
                    </div>
                </div>
                <button wire:click="nuevoGrupo"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white px-4 py-2.5 text-sm font-bold shadow-sm transition">
                    <i class="fa-solid fa-plus"></i> Nuevo grupo
                </button>
            </div>
        </div>

        {{-- Grilla de grupos --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($grupos as $g)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-5 flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center text-white"
                                  style="background-color: {{ $g->color ?: '#d68643' }}">
                                <i class="fa-solid fa-users text-sm"></i>
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-bold text-slate-800 truncate">{{ $g->nombre }}</h3>
                                <p class="text-xs text-slate-500">{{ $g->clientes_count }} cliente(s)</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button wire:click="editarGrupo({{ $g->id }})" class="text-slate-400 hover:text-brand p-1" title="Editar">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </button>
                            <button wire:click="eliminarGrupo({{ $g->id }})"
                                    wire:confirm="¿Eliminar el grupo '{{ $g->nombre }}'?"
                                    class="text-slate-400 hover:text-rose-600 p-1" title="Eliminar">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </div>
                    </div>
                    @if($g->descripcion)
                        <p class="text-xs text-slate-500 mt-2 line-clamp-2">{{ $g->descripcion }}</p>
                    @endif
                    <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100">
                        <button wire:click="gestionarMiembros({{ $g->id }})"
                                class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-user-plus mr-1"></i> Miembros
                        </button>
                        <button wire:click="difundir({{ $g->id }})"
                                class="flex-1 rounded-lg bg-brand hover:bg-brand-dark px-3 py-2 text-xs font-bold text-white transition">
                            <i class="fa-brands fa-whatsapp mr-1"></i> Difundir
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16">
                    <i class="fa-solid fa-users-rectangle text-4xl text-slate-300"></i>
                    <p class="mt-3 text-slate-500">Aún no tenés grupos. Creá el primero.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">{{ $grupos->links() }}</div>
    </div>

    {{-- ───────── Modal crear/editar grupo ───────── --}}
    @if($modalGrupo)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);" wire:click="$set('modalGrupo', false)">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-2xl" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800">{{ $grupoId ? 'Editar grupo' : 'Nuevo grupo' }}</h3>
                    <button wire:click="$set('modalGrupo', false)" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nombre *</label>
                        <input type="text" wire:model="nombre" placeholder="Ej. Mayoristas"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none">
                        @error('nombre') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Descripción</label>
                        <input type="text" wire:model="descripcion" placeholder="Opcional"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Color</label>
                        <input type="color" wire:model="color" class="h-10 w-20 rounded-lg border border-slate-300 cursor-pointer">
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-slate-100 flex gap-2">
                    <button wire:click="$set('modalGrupo', false)" class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button>
                    <button wire:click="guardarGrupo" class="flex-1 rounded-xl bg-brand hover:bg-brand-dark px-4 py-2.5 text-sm font-bold text-white">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ───────── Modal gestionar miembros ───────── --}}
    @if($modalMiembros)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);" wire:click="$set('modalMiembros', false)">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl max-h-[85vh] flex flex-col" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
                    <h3 class="text-sm font-bold text-slate-800">Miembros del grupo</h3>
                    <button wire:click="$set('modalMiembros', false)" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                </div>

                {{-- Buscar y agregar --}}
                <div class="px-5 pt-4 shrink-0">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" wire:model.live.debounce.300ms="buscarCliente" placeholder="Buscar por nombre, teléfono o cédula…"
                               class="w-full rounded-xl border border-slate-300 pl-9 pr-3.5 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none">
                    </div>
                    @if(count($resultados) > 0)
                        <div class="mt-2 rounded-xl border border-slate-200 divide-y divide-slate-100 max-h-44 overflow-y-auto">
                            @foreach($resultados as $c)
                                <button wire:click="agregarCliente({{ $c->id }})"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 hover:bg-brand-soft text-left transition">
                                    <span class="text-sm text-slate-700 truncate">{{ $c->nombre ?: 'Cliente' }} <span class="text-xs text-slate-400">· {{ $c->telefono_normalizado }}</span></span>
                                    <i class="fa-solid fa-plus text-brand text-xs"></i>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Lista de miembros actuales --}}
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">En el grupo ({{ count($miembros) }})</p>
                    @forelse($miembros as $m)
                        <div class="flex items-center justify-between gap-2 py-2 border-b border-slate-50">
                            <span class="text-sm text-slate-700 truncate">{{ $m->nombre ?: 'Cliente' }} <span class="text-xs text-slate-400">· {{ $m->telefono_normalizado }}</span></span>
                            <button wire:click="quitarCliente({{ $m->id }})" class="text-slate-400 hover:text-rose-600 p-1" title="Quitar">
                                <i class="fa-solid fa-user-minus text-xs"></i>
                            </button>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic text-center py-4">Sin miembros. Buscá clientes arriba para agregarlos.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
