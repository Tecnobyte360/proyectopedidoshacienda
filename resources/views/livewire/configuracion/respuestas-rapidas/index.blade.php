<div class="p-6 max-w-5xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-bolt text-amber-500"></i>
                Respuestas rápidas
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Textos predefinidos para que el operador responda con un click o escribiendo
                <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 text-slate-700 font-mono text-xs">/</kbd> en el chat.
            </p>
        </div>
        <button wire:click="abrirCrear"
                class="inline-flex items-center gap-2 bg-brand hover:bg-brand-dark text-white rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition">
            <i class="fa-solid fa-plus"></i>
            Nueva respuesta
        </button>
    </div>

    {{-- Búsqueda --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="busqueda"
               placeholder="🔍 Buscar por atajo o texto…"
               class="w-full md:w-80 rounded-xl border border-slate-200 px-4 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100">
    </div>

    {{-- Lista --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        @if($items->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <i class="fa-solid fa-bolt text-5xl mb-3 text-slate-300"></i>
                <p class="text-sm">No hay respuestas rápidas todavía.</p>
                <p class="text-xs mt-1">Crea la primera con el botón "Nueva respuesta".</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-left w-12">#</th>
                        <th class="px-4 py-3 text-left w-40">Atajo</th>
                        <th class="px-4 py-3 text-left">Texto</th>
                        <th class="px-4 py-3 text-center w-20">Estado</th>
                        <th class="px-4 py-3 text-center w-32">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($items as $r)
                        <tr class="hover:bg-amber-50/30 transition {{ !$r->activa ? 'opacity-50' : '' }}">
                            <td class="px-4 py-3 text-xs text-slate-400 font-mono">{{ $r->orden }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-700">
                                    <i class="fa-solid fa-bolt text-amber-500 text-[10px]"></i>
                                    {{ $r->atajo ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700 text-xs">
                                <p class="whitespace-pre-wrap max-w-2xl line-clamp-2">{{ $r->texto }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleActiva({{ $r->id }})"
                                        title="Click para {{ $r->activa ? 'desactivar' : 'activar' }}"
                                        class="inline-flex h-6 w-11 rounded-full transition {{ $r->activa ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                    <span class="inline-block h-5 w-5 rounded-full bg-white shadow transform transition mt-0.5
                                                 {{ $r->activa ? 'translate-x-5' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="abrirEditar({{ $r->id }})"
                                        class="text-slate-600 hover:text-brand p-2 rounded-lg hover:bg-slate-100 transition"
                                        title="Editar">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button wire:click="eliminar({{ $r->id }})"
                                        wire:confirm="¿Eliminar esta respuesta rápida?"
                                        class="text-rose-500 hover:text-rose-700 p-2 rounded-lg hover:bg-rose-50 transition"
                                        title="Eliminar">
                                    <i class="fa-solid fa-trash text-xs"></i>
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
                <div class="px-5 py-4 border-b border-slate-200 bg-gradient-to-br from-amber-500 to-orange-500 text-white">
                    <h3 class="font-bold flex items-center gap-2">
                        <i class="fa-solid fa-bolt"></i>
                        {{ $editandoId ? 'Editar' : 'Nueva' }} respuesta rápida
                    </h3>
                </div>

                <form wire:submit.prevent="guardar" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Atajo <span class="text-slate-400 font-normal">(opcional, ej. "Saludo")</span>
                        </label>
                        <input type="text" wire:model="atajo" maxlength="40"
                               placeholder="Saludo, Horario, Domicilio…"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100">
                        @error('atajo') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Texto del mensaje *</label>
                        <textarea wire:model="texto" rows="5" maxlength="2000"
                                  placeholder="Hola, gracias por escribir…"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100"></textarea>
                        @error('texto') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Orden</label>
                            <input type="number" wire:model="orden" min="0" max="9999"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <p class="text-[10px] text-slate-400 mt-1">Menor = aparece primero en el menú /</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Activa</label>
                            <label class="inline-flex items-center gap-2 mt-2">
                                <input type="checkbox" wire:model="activa" class="rounded">
                                <span class="text-sm text-slate-700">Visible en el chat</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="cerrarModal"
                                class="px-4 py-2 text-sm rounded-xl border border-slate-200 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold rounded-xl bg-brand hover:bg-brand-dark text-white shadow-sm">
                            {{ $editandoId ? 'Guardar cambios' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
