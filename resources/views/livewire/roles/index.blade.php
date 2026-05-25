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

        {{-- TABLA DE ROLES (estilo /usuarios) --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            @if($roles->isEmpty())
                <div class="p-16 text-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-4">
                        <i class="fa-solid fa-shield-halved text-2xl"></i>
                    </div>
                    <p class="text-lg font-semibold text-slate-700">Sin roles</p>
                    <p class="text-sm text-slate-500 mt-1">Crea el primer rol con el botón de arriba.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Rol</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Tipo</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Permisos</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($roles as $rol)
                                @php
                                    $colores = [
                                        'admin'       => ['bg' => 'from-rose-500 to-rose-600',      'badge' => 'bg-rose-100 text-rose-700',       'icon' => 'fa-crown'],
                                        'gerente'     => ['bg' => 'from-violet-500 to-violet-600',  'badge' => 'bg-violet-100 text-violet-700',   'icon' => 'fa-briefcase'],
                                        'operador'    => ['bg' => 'from-blue-500 to-blue-600',      'badge' => 'bg-blue-100 text-blue-700',       'icon' => 'fa-user-tie'],
                                        'cajero'      => ['bg' => 'from-emerald-500 to-emerald-600','badge' => 'bg-emerald-100 text-emerald-700', 'icon' => 'fa-cash-register'],
                                        'super-admin' => ['bg' => 'from-amber-500 to-amber-600',    'badge' => 'bg-amber-100 text-amber-700',     'icon' => 'fa-shield-halved'],
                                        'domiciliario'=> ['bg' => 'from-cyan-500 to-cyan-600',      'badge' => 'bg-cyan-100 text-cyan-700',       'icon' => 'fa-motorcycle'],
                                        'chatbot'     => ['bg' => 'from-indigo-500 to-indigo-600',  'badge' => 'bg-indigo-100 text-indigo-700',   'icon' => 'fa-robot'],
                                        'chat-only'   => ['bg' => 'from-pink-500 to-pink-600',      'badge' => 'bg-pink-100 text-pink-700',       'icon' => 'fa-comments'],
                                    ];
                                    // 🔧 Normalizar nombre del rol a minúsculas para que el lookup funcione
                                    // sin importar si en DB está como "ChatBot", "Chat-Only", etc.
                                    $rolKey = strtolower(trim($rol->name));
                                    $c = $colores[$rolKey] ?? ['bg' => 'from-slate-400 to-slate-500', 'badge' => 'bg-slate-100 text-slate-700', 'icon' => 'fa-user-shield'];
                                    $agrupados = $rol->permissions->sortBy('name')->groupBy(function ($p) {
                                        return str_contains($p->name, '.') ? explode('.', $p->name)[0] : 'otros';
                                    });
                                @endphp
                                <tr class="transition hover:bg-amber-50/30">
                                    {{-- ROL: icono + nombre + usuarios --}}
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $c['bg'] }} text-white text-sm font-bold shadow-sm">
                                                <i class="fa-solid {{ $c['icon'] }}"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-slate-800 capitalize truncate">{{ $rol->name }}</div>
                                                <div class="text-xs text-slate-500">
                                                    <i class="fa-solid fa-users text-[10px]"></i>
                                                    {{ $rol->users_count }} usuario(s)
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- TIPO --}}
                                    <td class="px-4 py-3.5">
                                        @if(($rol->es_global ?? false))
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 uppercase tracking-wider">
                                                <i class="fa-solid fa-lock text-[9px]"></i> Sistema
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 uppercase tracking-wider">
                                                <i class="fa-solid fa-building text-[9px]"></i> Mi empresa
                                            </span>
                                        @endif
                                    </td>
                                    {{-- PERMISOS: solo contador + botón para verlos en el modal --}}
                                    <td class="px-4 py-3.5">
                                        <button wire:click="abrirModalEditar({{ $rol->id }})"
                                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg {{ $c['badge'] }} hover:opacity-80 transition text-xs font-bold"
                                                title="Ver/editar permisos">
                                            <i class="fa-solid fa-list-check text-[11px]"></i>
                                            {{ $rol->permissions->count() }} permiso(s)
                                            <i class="fa-solid fa-arrow-right text-[10px] opacity-70"></i>
                                        </button>
                                    </td>
                                    {{-- ACCIONES --}}
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
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
                                        @endif

                                        {{-- 🗑️ ELIMINAR
                                             - Super-admin: puede eliminar cualquier rol (incluso los del sistema)
                                                excepto 'admin' y 'super-admin' que son críticos.
                                             - Tenant admin: solo elimina roles propios de su tenant. --}}
                                        @php
                                            $rolNombre = strtolower($rol->name ?? '');
                                            $esIntocable = in_array($rolNombre, ['admin', 'super-admin'], true);
                                            $puedeEliminar = !$esIntocable && (
                                                $esSuperAdmin
                                                || (!($rol->es_global ?? false) && ($rol->es_editable ?? false))
                                            );
                                        @endphp
                                        @if($puedeEliminar)
                                            <button wire:click="eliminar({{ $rol->id }})"
                                                    wire:confirm="¿Eliminar el rol '{{ $rol->name }}'? {{ $rol->users_count > 0 ? '⚠️ Tiene ' . $rol->users_count . ' usuario(s) — debes reasignarlos primero.' : 'Esta acción no se puede deshacer.' }}"
                                                    title="Eliminar"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
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
                                {{ $editandoId ? ($soloLectura ? 'Ver rol' : 'Editar rol') : 'Nuevo rol' }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ $soloLectura ? 'Rol del sistema — solo lectura. Clónalo para personalizarlo.' : 'Define el nombre y los permisos por módulo' }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="cerrarModal"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                    @if($soloLectura)
                        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 flex items-start gap-3">
                            <i class="fa-solid fa-circle-info text-sky-600 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-sky-900">Este es un rol del sistema</p>
                                <p class="text-xs text-sky-700 mt-0.5">No se puede editar ni eliminar directamente. Si necesitas un rol parecido pero con tus propios permisos, usa <b>Clonar</b> y modifica la copia.</p>
                            </div>
                            <button type="button" wire:click="clonar({{ $editandoId }})"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold px-3 py-1.5 transition flex-shrink-0">
                                <i class="fa-solid fa-copy"></i> Clonar
                            </button>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre del rol *</label>
                        <input type="text" wire:model="name" placeholder="ej. supervisor"
                               @if($soloLectura) disabled @endif
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20 {{ $soloLectura ? 'bg-slate-50 cursor-not-allowed' : '' }}">
                        @if(!$soloLectura)
                            <p class="text-xs text-slate-500 mt-1">Usa minúsculas, sin espacios. Ej: <code class="text-brand">supervisor</code>, <code class="text-brand">domiciliario</code></p>
                        @endif
                        @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

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
                                        @if(!$soloLectura)
                                            <button type="button" wire:click="toggleModulo('{{ $modulo }}')"
                                                    class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-lg transition
                                                           {{ $todosSel ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-brand-soft text-brand-secondary hover:bg-brand-soft-2' }}">
                                                <i class="fa-solid {{ $todosSel ? 'fa-square-minus' : 'fa-square-check' }}"></i>
                                                {{ $todosSel ? 'Quitar todos' : 'Marcar todos' }}
                                            </button>
                                        @endif
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach($permisos as $perm)
                                            <label class="flex items-center gap-2 text-xs rounded-md px-1 py-0.5 {{ $soloLectura ? 'cursor-default' : 'cursor-pointer hover:bg-white/50 transition' }}">
                                                <input type="checkbox" value="{{ $perm }}" wire:model="permisosSel"
                                                       @if($soloLectura) disabled @endif
                                                       class="rounded border-slate-300 text-brand focus:ring-brand/30 {{ $soloLectura ? 'cursor-not-allowed' : '' }}">
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
                            {{ $soloLectura ? 'Cerrar' : 'Cancelar' }}
                        </button>
                        @if(!$soloLectura)
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-6 py-2.5 text-sm font-bold text-white shadow-lg transition">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Guardar rol
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
