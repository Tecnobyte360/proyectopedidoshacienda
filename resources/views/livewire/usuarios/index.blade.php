<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER GRANDE --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white shadow-lg">
                        <i class="fa-solid fa-users-gear text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Gestión de Usuarios</h2>
                        <p class="text-sm text-slate-500">Cuentas que acceden a la plataforma · roles y sedes asignadas</p>
                    </div>
                </div>
                @can('usuarios.crear')
                    <button wire:click="abrirModalCrear"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] text-white font-bold px-5 py-3 transition shadow-lg">
                        <i class="fa-solid fa-user-plus"></i> Nuevo usuario
                    </button>
                @endcan
            </div>
        </div>

        {{-- KPIS --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 font-bold">Total</p>
                        <p class="text-3xl font-extrabold text-slate-800 mt-1">{{ $kpis['total'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white border border-emerald-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-emerald-600 font-bold">Activos</p>
                        <p class="text-3xl font-extrabold text-emerald-700 mt-1">{{ $kpis['activos'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white border border-rose-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-rose-600 font-bold">Inactivos</p>
                        <p class="text-3xl font-extrabold text-rose-700 mt-1">{{ $kpis['inactivos'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white border border-violet-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-violet-600 font-bold">Admins</p>
                        <p class="text-3xl font-extrabold text-violet-700 mt-1">{{ $kpis['admins'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- BÚSQUEDA --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" wire:model.live.debounce.400ms="busqueda"
                       placeholder="Buscar por nombre o email…"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm focus:border-[#d68643] focus:bg-white focus:ring-2 focus:ring-[#d68643]/20">
            </div>
        </div>

        {{-- TABLA --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            @if($usuarios->isEmpty())
                <div class="p-16 text-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-4">
                        <i class="fa-solid fa-users text-2xl"></i>
                    </div>
                    <p class="text-lg font-semibold text-slate-700">Sin usuarios</p>
                    <p class="text-sm text-slate-500 mt-1">Crea el primer usuario con el botón de arriba.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Usuario</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Rol</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden md:table-cell">Sede</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden lg:table-cell">Último login</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($usuarios as $u)
                                @php $esYo = $u->id === auth()->id(); @endphp
                                <tr class="transition hover:bg-amber-50/30 {{ $esYo ? 'bg-amber-50/40' : '' }}">
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white text-sm font-bold shadow-sm">
                                                {{ $u->iniciales() ?: 'US' }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-800 truncate">{{ $u->name }}</span>
                                                    @if($esYo)
                                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#fbe9d7] text-[#a85f24] uppercase">Tú</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-slate-500 truncate">{{ $u->email }}</div>
                                                @if($u->telefono)
                                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                        <i class="fa-solid fa-phone"></i> {{ $u->telefono }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @forelse($u->roles as $r)
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-violet-100 text-violet-700 capitalize mr-1">
                                                <i class="fa-solid fa-shield-halved text-[9px]"></i>
                                                {{ $r->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400">Sin rol</span>
                                        @endforelse
                                    </td>
                                    <td class="px-4 py-3.5 hidden md:table-cell">
                                        @if($u->sede)
                                            <span class="inline-flex items-center gap-1 text-xs text-slate-600">
                                                <i class="fa-solid fa-shop text-[#d68643]"></i>
                                                {{ $u->sede->nombre }}
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if($u->activo)
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                                <i class="fa-solid fa-circle text-[8px]"></i> Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-rose-100 text-rose-700">
                                                <i class="fa-solid fa-circle text-[8px]"></i> Inactivo
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-500 hidden lg:table-cell">
                                        @if($u->ultimo_login_at)
                                            <div>{{ $u->ultimo_login_at->format('d/m/Y') }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $u->ultimo_login_at->diffForHumans() }}</div>
                                        @else
                                            <span class="text-slate-400">Nunca</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        @can('usuarios.editar')
                                            <button wire:click="abrirModalEditar({{ $u->id }})"
                                                    title="Editar"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 hover:bg-[#fbe9d7] hover:text-[#a85f24] text-slate-600 transition">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </button>
                                            @if(!$esYo)
                                                <button wire:click="toggleActivo({{ $u->id }})"
                                                        title="{{ $u->activo ? 'Desactivar' : 'Activar' }}"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ $u->activo ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} transition">
                                                    <i class="fa-solid {{ $u->activo ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i>
                                                </button>
                                            @endif
                                        @endcan
                                        @can('usuarios.eliminar')
                                            @if(!$esYo)
                                                <button wire:click="eliminar({{ $u->id }})"
                                                        wire:confirm="¿Eliminar a {{ $u->name }}? Esta acción es irreversible."
                                                        title="Eliminar"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $usuarios->links() }}</div>
            @endif
        </div>
    </div>

    {{-- MODAL CREAR/EDITAR --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl my-8 overflow-hidden" @click.stop>
                {{-- Header del modal con gradiente brand --}}
                <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white shadow">
                            <i class="fa-solid {{ $editandoId ? 'fa-pen-to-square' : 'fa-user-plus' }}"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-800">
                                {{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}
                            </h3>
                            <p class="text-xs text-slate-500">Datos de acceso, rol y sede</p>
                        </div>
                    </div>
                    <button wire:click="cerrarModal"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre completo *</label>
                            <input type="text" wire:model="name" placeholder="Stiven Madrid"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                            @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Email *</label>
                            <input type="email" wire:model="email" placeholder="usuario@hacienda.com"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                            @error('email') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Teléfono</label>
                            <input type="text" wire:model="telefono" placeholder="3001234567"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Sede asignada</label>
                            <select wire:model="sede_id"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                                <option value="">— Sin sede asignada —</option>
                                @foreach($sedes as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Rol *</label>
                            <select wire:model="rol"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                                <option value="">— Selecciona rol —</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->name }}">{{ ucfirst($r->name) }}</option>
                                @endforeach
                            </select>
                            @error('rol') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Contraseña {{ $editandoId ? '(deja vacío para no cambiar)' : '*' }}
                            </label>
                            <input type="password" wire:model="password" placeholder="••••••••"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                            @error('password') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            @if(!$editandoId)
                                <p class="text-xs text-slate-500 mt-1">Mínimo 6 caracteres</p>
                            @endif
                        </div>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 border border-slate-200 p-3 cursor-pointer">
                        <input type="checkbox" wire:model="activo"
                               class="mt-0.5 rounded border-slate-300 text-[#d68643] focus:ring-[#d68643]">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">Usuario activo</div>
                            <div class="text-xs text-slate-500">Podrá iniciar sesión en la plataforma. Desactívalo en lugar de eliminarlo si solo quieres bloquear acceso temporalmente.</div>
                        </div>
                    </label>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] px-6 py-2.5 text-sm font-bold text-white shadow-lg transition">
                            <i class="fa-solid fa-floppy-disk"></i>
                            {{ $editandoId ? 'Actualizar usuario' : 'Crear usuario' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
