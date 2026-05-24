<div class="p-4 md:p-6 max-w-5xl mx-auto">

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-bolt text-amber-500"></i>
                Respuestas rápidas
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Atajo
                <kbd class="px-1 py-0.5 rounded bg-slate-100 border border-slate-300 text-slate-700 font-mono text-[10px]">/</kbd>
                en el chat para usarlas.
            </p>
        </div>
        <button wire:click="abrirCrear"
                class="inline-flex items-center gap-1.5 bg-brand hover:bg-brand-dark text-white rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition">
            <i class="fa-solid fa-plus text-[10px]"></i>
            Nueva
        </button>
    </div>

    {{-- Búsqueda --}}
    <div class="mb-3 relative w-full md:w-72">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        <input type="text" wire:model.live.debounce.300ms="busqueda"
               placeholder="Buscar atajo o texto..."
               class="w-full rounded-lg border border-slate-200 pl-9 pr-3 py-1.5 text-xs focus:border-brand focus:ring-2 focus:ring-amber-100">
    </div>

    {{-- Tabla compacta --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        @if($items->isEmpty())
            <div class="p-8 text-center text-slate-400">
                <i class="fa-solid fa-bolt text-3xl mb-2 text-slate-300"></i>
                <p class="text-xs">Sin respuestas. Crea la primera con "Nueva".</p>
            </div>
        @else
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold">
                    <tr>
                        <th class="px-2 py-2 text-left w-10"><i class="fa-solid fa-hashtag"></i></th>
                        <th class="px-2 py-2 text-left w-32">Atajo</th>
                        <th class="px-2 py-2 text-left">Texto</th>
                        <th class="px-2 py-2 text-center w-14">
                            <i class="fa-solid fa-toggle-on"></i>
                        </th>
                        <th class="px-2 py-2 text-center w-20">
                            <i class="fa-solid fa-gears"></i>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($items as $r)
                        <tr class="hover:bg-amber-50/40 transition {{ !$r->activa ? 'opacity-60' : '' }}">
                            <td class="px-2 py-1.5 text-[10px] text-slate-400 font-mono">{{ $r->orden }}</td>
                            <td class="px-2 py-1.5">
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700">
                                    <i class="fa-solid fa-bolt text-amber-500 text-[9px]"></i>
                                    {{ $r->atajo ?: '—' }}
                                </span>
                            </td>
                            <td class="px-2 py-1.5 text-slate-700 text-[11px]">
                                <p class="line-clamp-1 max-w-md">{{ $r->texto }}</p>
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <button wire:click="toggleActiva({{ $r->id }})"
                                        title="{{ $r->activa ? 'Activa — click para desactivar' : 'Inactiva — click para activar' }}"
                                        class="text-base {{ $r->activa ? 'text-emerald-500 hover:text-emerald-600' : 'text-slate-300 hover:text-slate-400' }} transition">
                                    <i class="fa-solid {{ $r->activa ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                </button>
                            </td>
                            <td class="px-2 py-1.5 text-center whitespace-nowrap">
                                <button wire:click="abrirEditar({{ $r->id }})"
                                        class="text-slate-500 hover:text-brand p-1 rounded transition" title="Editar">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <button wire:click="eliminar({{ $r->id }})"
                                        wire:confirm="¿Eliminar esta respuesta?"
                                        class="text-rose-400 hover:text-rose-600 p-1 rounded transition" title="Eliminar">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Modal crear/editar --}}
    @if($modal)
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
             wire:click.self="cerrarModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-between">
                    <h3 class="font-bold text-sm flex items-center gap-2">
                        <i class="fa-solid fa-bolt"></i>
                        {{ $editandoId ? 'Editar' : 'Nueva' }} respuesta
                    </h3>
                    <button wire:click="cerrarModal" type="button" class="text-white/80 hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-4 space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                            Atajo <span class="text-slate-400 font-normal">(opcional)</span>
                        </label>
                        <input type="text" wire:model="atajo" maxlength="40"
                               placeholder="Saludo, Horario, Domicilio..."
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100">
                        @error('atajo') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Texto del mensaje *</label>
                        <textarea wire:model="texto" rows="5" maxlength="2000"
                                  placeholder="Hola, gracias por escribir..."
                                  class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100"></textarea>
                        @error('texto') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Orden</label>
                            <input type="number" wire:model="orden" min="0" max="9999"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Visible</label>
                            <label class="inline-flex items-center gap-2 mt-1">
                                <input type="checkbox" wire:model="activa" class="rounded">
                                <span class="text-sm text-slate-700">Activa</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="cerrarModal"
                                class="px-3 py-1.5 text-xs rounded-lg border border-slate-200 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-brand hover:bg-brand-dark text-white shadow-sm">
                            <i class="fa-solid fa-{{ $editandoId ? 'check' : 'plus' }} text-[10px]"></i>
                            {{ $editandoId ? 'Guardar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
