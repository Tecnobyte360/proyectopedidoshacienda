<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-user-shield text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Usuarios internos</h2>
                        <p class="text-sm text-slate-500">Teléfonos del equipo que escriben al WhatsApp del negocio. El bot <strong>NO responde</strong> a estos números.</p>
                    </div>
                </div>
                <button wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nuevo usuario interno
                </button>
            </div>
        </div>

        {{-- BÚSQUEDA --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       placeholder="Buscar por nombre, teléfono o cargo..."
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm focus:border-brand focus:bg-white focus:ring-2 focus:ring-amber-100">
            </div>
        </div>

        {{-- TABLA --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Teléfono</th>
                        <th class="px-4 py-3">Cargo</th>
                        <th class="px-4 py-3">Departamento</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($usuarios as $u)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-4 py-3 font-semibold text-slate-800">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-violet-100 text-violet-700">
                                        <i class="fa-solid fa-user-tie"></i>
                                    </div>
                                    {{ $u->nombre }}
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $u->telefono_normalizado }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $u->cargo ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $u->departamento ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if($u->activo)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Activo</span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="abrirEditar({{ $u->id }})"
                                            title="Editar"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    <button wire:click="toggleActivo({{ $u->id }})"
                                            title="{{ $u->activo ? 'Desactivar' : 'Activar' }}"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg {{ $u->activo ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} transition">
                                        <i class="fa-solid {{ $u->activo ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                                    </button>
                                    <button wire:click="eliminar({{ $u->id }})"
                                            wire:confirm="¿Eliminar este usuario interno?"
                                            title="Eliminar"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                                    <i class="fa-solid fa-user-shield text-2xl"></i>
                                </div>
                                <p class="text-base font-semibold text-slate-700">Sin usuarios internos</p>
                                <p class="text-sm text-slate-500">Agrega los teléfonos del equipo para que el bot no les responda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL --}}
    @if($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white">
                            <i class="fa-solid {{ $editandoId ? 'fa-pen-to-square' : 'fa-plus' }}"></i>
                        </div>
                        <h3 class="font-bold text-slate-800">{{ $editandoId ? 'Editar usuario interno' : 'Nuevo usuario interno' }}</h3>
                    </div>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-5 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Teléfono *</label>
                            <input type="text" wire:model="telefono" placeholder="573001234567"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                            @error('telefono') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="Ana Pérez"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            @error('nombre') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Cargo</label>
                            <input type="text" wire:model="cargo" placeholder="Gerente, Vendedor, Logística..."
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Departamento</label>
                            <select wire:model="departamentoId" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">— Sin departamento —</option>
                                @foreach($departamentos as $d)
                                    <option value="{{ $d->id }}">{{ $d->icono_emoji }} {{ $d->nombre }}</option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-slate-500 mt-1">Recibirá notificaciones cuando se derive una conversación a su departamento.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Notas</label>
                        <textarea wire:model="notas" rows="2"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="activo" class="rounded text-brand">
                        <span class="font-semibold text-slate-700">Activo</span>
                    </label>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                    <button wire:click="guardar" class="rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-5 py-2 text-sm font-bold text-white shadow-lg">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
