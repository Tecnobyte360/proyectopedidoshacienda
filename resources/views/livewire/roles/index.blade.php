<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER GRANDE --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-shield-halved text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Roles y Permisos</h2>
                        <p class="text-sm text-slate-500">Define qué puede hacer cada rol en la plataforma</p>
                    </div>
                </div>
                <button wire:click="abrirModalCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nuevo rol
                </button>
            </div>
        </div>

        {{-- LISTADO DE ROLES --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($roles as $rol)
                @php
                    $colores = [
                        'admin'    => ['bg' => 'from-rose-500 to-rose-600',     'border' => 'border-rose-200',    'text' => 'text-rose-600',     'badge' => 'bg-rose-100 text-rose-700',     'icon' => 'fa-crown'],
                        'gerente'  => ['bg' => 'from-violet-500 to-violet-600', 'border' => 'border-violet-200',  'text' => 'text-violet-600',   'badge' => 'bg-violet-100 text-violet-700', 'icon' => 'fa-briefcase'],
                        'operador' => ['bg' => 'from-blue-500 to-blue-600',     'border' => 'border-blue-200',    'text' => 'text-blue-600',     'badge' => 'bg-blue-100 text-blue-700',     'icon' => 'fa-user-tie'],
                        'cajero'   => ['bg' => 'from-emerald-500 to-emerald-600','border' => 'border-emerald-200','text' => 'text-emerald-600',  'badge' => 'bg-emerald-100 text-emerald-700','icon' => 'fa-cash-register'],
                    ];
                    $c = $colores[$rol->name] ?? ['bg' => 'from-slate-500 to-slate-600', 'border' => 'border-slate-200', 'text' => 'text-slate-600', 'badge' => 'bg-slate-100 text-slate-700', 'icon' => 'fa-user-shield'];
                @endphp

                <div class="rounded-2xl bg-white border-2 {{ $c['border'] }} shadow-sm overflow-hidden hover:shadow-lg transition">
                    {{-- Cabecera con gradiente --}}
                    <div class="bg-gradient-to-r {{ $c['bg'] }} p-4 text-white">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                                    <i class="fa-solid {{ $c['icon'] }} text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-extrabold capitalize">{{ $rol->name }}</h3>
                                    <p class="text-xs text-white/80">
                                        {{ $rol->users_count }} usuario(s)
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-1">
                                <button wire:click="abrirModalEditar({{ $rol->id }})"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur-sm transition"
                                        title="Editar">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                @if($rol->name !== 'admin')
                                    <button wire:click="eliminar({{ $rol->id }})"
                                            wire:confirm="¿Eliminar el rol '{{ $rol->name }}'?"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 hover:bg-rose-500 backdrop-blur-sm transition"
                                            title="Eliminar">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Cuerpo --}}
                    <div class="p-5 space-y-3">
                        <div class="flex items-baseline justify-between">
                            <p class="text-xs uppercase tracking-wider text-slate-500 font-bold">Permisos</p>
                            <span class="text-2xl font-extrabold {{ $c['text'] }}">{{ $rol->permissions->count() }}</span>
                        </div>

                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            @if($rol->permissions->count() > 0)
                                <details class="text-xs">
                                    <summary class="cursor-pointer {{ $c['text'] }} font-semibold hover:underline">
                                        Ver permisos asignados
                                    </summary>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($rol->permissions->sortBy('name') as $p)
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded {{ $c['badge'] }}">{{ $p->name }}</span>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <p class="text-xs text-slate-400">Este rol no tiene permisos asignados.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- MODAL CREAR/EDITAR --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8 overflow-hidden" @click.stop>
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-brand-soft/40 via-white to-white border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-800">
                                {{ $editandoId ? 'Editar rol' : 'Nuevo rol' }}
                            </h3>
                            <p class="text-xs text-slate-500">Define el nombre y los permisos por módulo</p>
                        </div>
                    </div>
                    <button wire:click="cerrarModal"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre del rol *</label>
                        <input type="text" wire:model="name" placeholder="ej. supervisor"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        <p class="text-xs text-slate-500 mt-1">Usa minúsculas, sin espacios. Ej: <code class="text-brand">supervisor</code>, <code class="text-brand">domiciliario</code></p>
                        @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-medium text-slate-700">Permisos por módulo</label>
                            <span class="text-xs text-slate-500">
                                <span class="font-bold text-brand-secondary">{{ count($permisosSel) }}</span> permisos seleccionados
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($permisosPorMod as $modulo => $permisos)
                                @php
                                    $todosSel = !array_diff($permisos, $permisosSel);
                                    $algunoSel = !empty(array_intersect($permisos, $permisosSel));
                                @endphp
                                <div class="rounded-xl border-2 transition
                                            {{ $todosSel ? 'border-brand bg-brand-soft/30' : ($algunoSel ? 'border-amber-200 bg-amber-50/30' : 'border-slate-200 bg-white') }}
                                            p-4">
                                    <div class="flex items-center justify-between mb-2.5">
                                        <h4 class="font-bold text-sm text-slate-800 capitalize">
                                            {{ str_replace('_', ' ', $modulo) }}
                                        </h4>
                                        <button type="button" wire:click="toggleModulo('{{ $modulo }}')"
                                                class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-lg transition
                                                       {{ $todosSel ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-brand-soft text-brand-secondary hover:bg-brand-soft-2' }}">
                                            <i class="fa-solid {{ $todosSel ? 'fa-square-minus' : 'fa-square-check' }}"></i>
                                            {{ $todosSel ? 'Quitar todos' : 'Marcar todos' }}
                                        </button>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach($permisos as $perm)
                                            <label class="flex items-center gap-2 cursor-pointer text-xs hover:bg-white/50 rounded-md px-1 py-0.5 transition">
                                                <input type="checkbox" value="{{ $perm }}" wire:model="permisosSel"
                                                       class="rounded border-slate-300 text-brand focus:ring-brand/30">
                                                <span class="font-mono text-slate-700">{{ $perm }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-6 py-2.5 text-sm font-bold text-white shadow-lg transition">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Guardar rol
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
