<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER GRANDE --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-lg">
                        <i class="fa-solid fa-bolt text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Respuestas rápidas</h2>
                        <p class="text-sm text-slate-500">
                            Atajo
                            <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 text-slate-700 font-mono text-[11px]">/</kbd>
                            en el chat para insertarlas con un click.
                        </p>
                    </div>
                </div>
                <button wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nueva respuesta
                </button>
            </div>
        </div>

        {{-- BÚSQUEDA --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" wire:model.live.debounce.300ms="busqueda"
                       placeholder="Buscar atajo o texto…"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm focus:border-amber-500 focus:bg-white focus:ring-2 focus:ring-amber-200">
            </div>
        </div>

        {{-- TABLA --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            @if($items->isEmpty())
                <div class="p-16 text-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 text-amber-500 mb-4">
                        <i class="fa-solid fa-bolt text-2xl"></i>
                    </div>
                    <p class="text-lg font-semibold text-slate-700">Sin respuestas rápidas</p>
                    <p class="text-sm text-slate-500 mt-1">Crea la primera con el botón de arriba.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 w-16">
                                    <i class="fa-solid fa-hashtag"></i> Orden
                                </th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 w-48">Atajo</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Texto del mensaje</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500 w-24">Estado</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500 w-32">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($items as $r)
                                <tr class="transition hover:bg-amber-50/40 {{ !$r->activa ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-3.5 text-xs text-slate-400 font-mono">{{ $r->orden }}</td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
                                            <i class="fa-solid fa-bolt text-[10px]"></i>
                                            {{ $r->atajo ?: '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-700 text-sm">
                                        <p class="line-clamp-2 max-w-3xl">{{ $r->texto }}</p>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <button wire:click="toggleActiva({{ $r->id }})"
                                                title="{{ $r->activa ? 'Activa · click para desactivar' : 'Inactiva · click para activar' }}"
                                                class="text-2xl {{ $r->activa ? 'text-emerald-500 hover:text-emerald-600' : 'text-slate-300 hover:text-slate-400' }} transition">
                                            <i class="fa-solid {{ $r->activa ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <button wire:click="abrirEditar({{ $r->id }})"
                                                title="Editar"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 hover:bg-amber-100 hover:text-amber-700 text-slate-600 transition">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button wire:click="eliminar({{ $r->id }})"
                                                wire:confirm="¿Eliminar esta respuesta rápida?"
                                                title="Eliminar"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
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
