<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-building-user text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Departamentos</h2>
                        <p class="text-sm text-slate-500">Deriva conversaciones automáticamente según palabras clave. El bot se silencia y notifica al equipo del departamento.</p>
                    </div>
                </div>
                <button wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nuevo departamento
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($departamentos as $d)
                <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm {{ !$d->activo ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl text-2xl"
                                 style="background: {{ $d->color }}15; color: {{ $d->color }};">
                                {{ $d->icono_emoji ?: '🎯' }}
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800">{{ $d->nombre }}</h3>
                                <p class="text-xs text-slate-500">{{ $d->usuarios_count }} {{ $d->usuarios_count === 1 ? 'usuario interno' : 'usuarios internos' }}</p>
                            </div>
                        </div>
                        @if($d->activo)
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Activo</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Inactivo</span>
                        @endif
                    </div>

                    @if(!empty($d->keywords))
                        <div class="mb-3">
                            <p class="text-[10px] uppercase font-bold text-slate-500 mb-1">Palabras clave</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($d->keywords as $kw)
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono">{{ $kw }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($d->saludo_automatico)
                        <div class="mb-3 text-xs text-slate-600 italic border-l-2 border-slate-200 pl-2">
                            "{{ mb_strimwidth($d->saludo_automatico, 0, 100, '…') }}"
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-1 pt-2 border-t border-slate-100">
                        <button wire:click="abrirEditar({{ $d->id }})" class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>
                        <button wire:click="toggleActivo({{ $d->id }})" class="h-8 w-8 rounded-lg {{ $d->activo ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} transition">
                            <i class="fa-solid {{ $d->activo ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                        </button>
                        <button wire:click="eliminar({{ $d->id }})" wire:confirm="¿Eliminar este departamento?"
                                class="h-8 w-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl bg-white border border-slate-200 p-12 text-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i class="fa-solid fa-building-user text-2xl"></i>
                    </div>
                    <p class="text-base font-semibold text-slate-700">Sin departamentos</p>
                    <p class="text-sm text-slate-500">Crea uno para derivar conversaciones automáticamente.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <h3 class="font-bold text-slate-800">{{ $editandoId ? 'Editar' : 'Nuevo' }} departamento</h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="Servicio al Cliente"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            @error('nombre') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Ícono / Color</label>
                            <div class="flex items-center gap-2">
                                <input type="text" wire:model="iconoEmoji" maxlength="4"
                                       class="w-14 rounded-xl border border-slate-200 px-2 py-2 text-xl text-center">
                                <input type="color" wire:model="color" class="h-10 w-14 rounded-xl border border-slate-200 cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Palabras clave (separadas por coma)</label>
                        <input type="text" wire:model="keywordsStr"
                               placeholder="servicio al cliente, reclamo, queja, pqr, devolución"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <p class="text-[10px] text-slate-500 mt-1">Si el cliente escribe cualquiera de estas, la conversación se deriva a este departamento.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Saludo automático al cliente</label>
                        <textarea wire:model="saludoAutomatico" rows="3"
                                  placeholder="¡Hola! Un asesor de Servicio al Cliente te atenderá en un momento. 🙌"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                        <p class="text-[10px] text-slate-500 mt-1">Si vacío, se genera uno por defecto con el nombre del cliente y del departamento.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Orden</label>
                            <input type="number" wire:model="orden" min="0"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div class="flex items-end gap-3">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="notificarInternos" class="rounded text-brand">
                                <span class="font-semibold text-slate-700">Notificar internos</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="activo" class="rounded text-brand">
                                <span class="font-semibold text-slate-700">Activo</span>
                            </label>
                        </div>
                    </div>
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
