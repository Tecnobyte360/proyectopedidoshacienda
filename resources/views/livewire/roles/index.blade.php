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
                        <p class="text-sm text-slate-500">
                            @if($esSuperAdmin)
                                Como super-admin, gestionas roles globales y de cualquier tenant.
                            @else
                                Crea y personaliza roles para los usuarios de tu empresa. Los roles del SISTEMA son plantillas read-only que puedes clonar.
                            @endif
                        </p>
                    </div>
                </div>
                <button wire:click="abrirModalCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nuevo rol
                </button>
            </div>
        </div>

        {{-- TABLA DE ROLES --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-bold text-slate-600 uppercase tracking-wider text-[11px]">Rol</th>
                            <th class="text-left px-5 py-3 font-bold text-slate-600 uppercase tracking-wider text-[11px]">Tipo</th>
                            <th class="text-center px-5 py-3 font-bold text-slate-600 uppercase tracking-wider text-[11px]">Usuarios</th>
                            <th class="text-center px-5 py-3 font-bold text-slate-600 uppercase tracking-wider text-[11px]">Permisos</th>
                            <th class="text-left px-5 py-3 font-bold text-slate-600 uppercase tracking-wider text-[11px]">Módulos / Acciones</th>
                            <th class="text-right px-5 py-3 font-bold text-slate-600 uppercase tracking-wider text-[11px]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($roles as $rol)
                            @php
                                $colores = [
                                    'admin'      => ['bg' => 'from-rose-500 to-rose-600',     'text' => 'text-rose-600',    'badge' => 'bg-rose-100 text-rose-700',    'icon' => 'fa-crown'],
                                    'gerente'    => ['bg' => 'from-violet-500 to-violet-600', 'text' => 'text-violet-600',  'badge' => 'bg-violet-100 text-violet-700','icon' => 'fa-briefcase'],
                                    'operador'   => ['bg' => 'from-blue-500 to-blue-600',     'text' => 'text-blue-600',    'badge' => 'bg-blue-100 text-blue-700',    'icon' => 'fa-user-tie'],
                                    'cajero'     => ['bg' => 'from-emerald-500 to-emerald-600','text' => 'text-emerald-600','badge' => 'bg-emerald-100 text-emerald-700','icon' => 'fa-cash-register'],
                                    'super-admin'=> ['bg' => 'from-amber-500 to-amber-600',   'text' => 'text-amber-600',   'badge' => 'bg-amber-100 text-amber-700',  'icon' => 'fa-shield-halved'],
                                    'domiciliario'=>['bg' => 'from-cyan-500 to-cyan-600',     'text' => 'text-cyan-600',    'badge' => 'bg-cyan-100 text-cyan-700',    'icon' => 'fa-motorcycle'],
                                ];
                                $c = $colores[$rol->name] ?? ['bg' => 'from-slate-500 to-slate-600', 'text' => 'text-slate-600', 'badge' => 'bg-slate-100 text-slate-700', 'icon' => 'fa-user-shield'];
                                $agrupados = $rol->permissions->sortBy('name')->groupBy(function ($p) {
                                    return str_contains($p->name, '.') ? explode('.', $p->name)[0] : 'otros';
                                });
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                {{-- ROL --}}
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br {{ $c['bg'] }} text-white shadow">
                                            <i class="fa-solid {{ $c['icon'] }} text-sm"></i>
                                        </div>
                                        <div class="font-bold capitalize text-slate-800">{{ $rol->name }}</div>
                                    </div>
                                </td>
                                {{-- TIPO --}}
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if(($rol->es_global ?? false))
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-600 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider">
                                            <i class="fa-solid fa-lock text-[9px]"></i> Sistema
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider">
                                            <i class="fa-solid fa-building text-[9px]"></i> Mi empresa
                                        </span>
                                    @endif
                                </td>
                                {{-- USUARIOS --}}
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center justify-center min-w-[2rem] rounded-lg bg-slate-100 px-2 py-0.5 text-sm font-bold text-slate-700">
                                        {{ $rol->users_count }}
                                    </span>
                                </td>
                                {{-- PERMISOS COUNT --}}
                                <td class="px-5 py-3 text-center">
                                    <span class="inline-flex items-center justify-center min-w-[2.5rem] rounded-lg {{ $c['badge'] }} px-2.5 py-0.5 text-sm font-extrabold">
                                        {{ $rol->permissions->count() }}
                                    </span>
                                </td>
                                {{-- MÓDULOS / ACCIONES --}}
                                <td class="px-5 py-3">
                                    @if($rol->permissions->count() > 0)
                                        <div class="space-y-1 max-w-xl">
                                            @foreach($agrupados as $modulo => $perms)
                                                <div class="flex items-start gap-2">
                                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold capitalize {{ $c['badge'] }} flex-shrink-0 mt-0.5">
                                                        {{ $modulo }}
                                                    </span>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($perms as $p)
                                                            @php
                                                                $accion = str_contains($p->name, '.') ? substr($p->name, strpos($p->name, '.') + 1) : $p->name;
                                                            @endphp
                                                            <span class="inline-block text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-700">
                                                                {{ $accion }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">Sin permisos</span>
                                    @endif
                                </td>
                                {{-- ACCIONES --}}
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if(($rol->es_global ?? false))
                                        <button wire:click="clonar({{ $rol->id }})"
                                                title="Clonar para personalizar"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-600 transition">
                                            <i class="fa-solid fa-copy text-xs"></i>
                                        </button>
                                    @endif
                                    @if($rol->es_editable ?? false)
                                        <button wire:click="abrirModalEditar({{ $rol->id }})"
                                                title="Editar"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 hover:bg-brand-soft hover:text-brand-secondary text-slate-600 transition">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        @if($rol->name !== 'admin' && !($rol->es_global ?? false))
                                            <button wire:click="eliminar({{ $rol->id }})"
                                                    wire:confirm="¿Eliminar el rol '{{ $rol->name }}'?"
                                                    title="Eliminar"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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

                    {{-- 📋 RESUMEN: Permisos actuales del rol (siempre visibles) --}}
                    @if(count($permisosSel) > 0)
                        @php
                            $resumenAgrupado = collect($permisosSel)->sort()->groupBy(function ($p) {
                                return str_contains($p, '.') ? explode('.', $p)[0] : 'otros';
                            });
                        @endphp
                        <div class="rounded-2xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50/30 overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-2.5 bg-emerald-100/60 border-b border-emerald-200">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                    <span class="text-sm font-bold text-emerald-900">Permisos actualmente asignados</span>
                                </div>
                                <span class="inline-flex items-center justify-center min-w-[2rem] rounded-lg bg-emerald-600 text-white px-2.5 py-0.5 text-sm font-extrabold">
                                    {{ count($permisosSel) }}
                                </span>
                            </div>
                            <div class="max-h-56 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-emerald-50 sticky top-0 z-10">
                                        <tr>
                                            <th class="text-left px-4 py-1.5 font-bold text-emerald-800 uppercase tracking-wider text-[10px]">Módulo</th>
                                            <th class="text-left px-4 py-1.5 font-bold text-emerald-800 uppercase tracking-wider text-[10px]">Acción</th>
                                            <th class="text-right px-4 py-1.5 font-bold text-emerald-800 uppercase tracking-wider text-[10px]">Quitar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-emerald-100 bg-white">
                                        @foreach($resumenAgrupado as $mod => $perms)
                                            @foreach($perms as $i => $perm)
                                                @php
                                                    $accion = str_contains($perm, '.') ? substr($perm, strpos($perm, '.') + 1) : $perm;
                                                @endphp
                                                <tr class="hover:bg-emerald-50/50">
                                                    <td class="px-4 py-1.5 align-top whitespace-nowrap">
                                                        @if($i === 0)
                                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold capitalize bg-emerald-100 text-emerald-800">
                                                                {{ $mod }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-1.5 font-mono text-slate-700">{{ $accion }}</td>
                                                    <td class="px-4 py-1.5 text-right">
                                                        <button type="button"
                                                                onclick="this.closest('form').querySelector('input[value=&quot;{{ $perm }}&quot;]').click()"
                                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-rose-50 hover:bg-rose-100 text-rose-600 transition"
                                                                title="Quitar este permiso">
                                                            <i class="fa-solid fa-xmark text-[10px]"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 px-4 py-3 text-center">
                            <i class="fa-solid fa-circle-exclamation text-slate-400"></i>
                            <span class="text-xs text-slate-500 ml-1">Este rol aún no tiene permisos. Selecciónalos abajo.</span>
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-sm font-medium text-slate-700">
                                <i class="fa-solid fa-list-check text-brand"></i> Agregar / quitar permisos
                            </label>
                            <span class="text-xs text-slate-500">
                                <span class="font-bold text-brand-secondary">{{ count($permisosSel) }}</span> seleccionados en total
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
