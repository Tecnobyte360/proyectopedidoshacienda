<div class="p-4 md:p-6 max-w-6xl mx-auto">

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-violet-500"></i>
                Lecciones del bot
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Errores reportados al bot. Se inyectan al prompt del LLM como reglas obligatorias.
            </p>
        </div>
        <button wire:click="abrirCrear"
                class="inline-flex items-center gap-1.5 bg-brand hover:bg-brand-dark text-white rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition">
            <i class="fa-solid fa-plus text-[10px]"></i>
            Nueva lección
        </button>
    </div>

    <div class="flex items-center gap-2 mb-3 flex-wrap">
        <div class="relative w-full md:w-72">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" wire:model.live.debounce.300ms="busqueda"
                   placeholder="Buscar..."
                   class="w-full rounded-lg border border-slate-200 pl-9 pr-3 py-1.5 text-xs">
        </div>
        <select wire:model.live="filtroCategoria" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs">
            <option value="">Todas las categorías</option>
            @foreach($categorias as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        @if($items->isEmpty())
            <div class="p-10 text-center text-slate-400">
                <i class="fa-solid fa-graduation-cap text-4xl mb-2 text-slate-300"></i>
                <p class="text-sm">El bot aún no tiene lecciones.</p>
                <p class="text-xs mt-1">Cuando reportes errores desde /chat (botón "El bot se equivocó"), aparecerán aquí.</p>
            </div>
        @else
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold">
                    <tr>
                        <th class="px-2 py-2 text-left w-32">Categoría</th>
                        <th class="px-2 py-2 text-left">Título</th>
                        <th class="px-2 py-2 text-left hidden md:table-cell">Frase / Regla</th>
                        <th class="px-2 py-2 text-center w-14"><i class="fa-solid fa-repeat" title="Veces aplicada"></i></th>
                        <th class="px-2 py-2 text-center w-14"><i class="fa-solid fa-toggle-on"></i></th>
                        <th class="px-2 py-2 text-center w-20"><i class="fa-solid fa-gears"></i></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($items as $l)
                        <tr class="hover:bg-violet-50/30 {{ !$l->activa ? 'opacity-50' : '' }}">
                            <td class="px-2 py-2 align-top">
                                <span class="text-[10px] font-bold text-violet-700">{{ $categorias[$l->categoria] ?? $l->categoria }}</span>
                            </td>
                            <td class="px-2 py-2 align-top">
                                <div class="font-semibold text-slate-800">{{ $l->titulo }}</div>
                                @if($l->contexto_error)
                                    <div class="text-[10px] text-rose-600 mt-0.5"><i class="fa-solid fa-circle-xmark"></i> {{ \Illuminate\Support\Str::limit($l->contexto_error, 100) }}</div>
                                @endif
                            </td>
                            <td class="px-2 py-2 align-top hidden md:table-cell">
                                @if($l->frase_disparadora)
                                    <div class="text-[10px] text-slate-500 italic">"{{ \Illuminate\Support\Str::limit($l->frase_disparadora, 50) }}"</div>
                                @endif
                                @if($l->regla)
                                    <div class="text-[10px] text-emerald-700 mt-0.5"><i class="fa-solid fa-circle-check"></i> {{ \Illuminate\Support\Str::limit($l->regla, 100) }}</div>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-center text-[10px] text-slate-500">{{ $l->veces_aplicada }}</td>
                            <td class="px-2 py-2 text-center">
                                <button wire:click="toggleActiva({{ $l->id }})"
                                        class="text-base {{ $l->activa ? 'text-emerald-500' : 'text-slate-300' }}">
                                    <i class="fa-solid {{ $l->activa ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                </button>
                            </td>
                            <td class="px-2 py-2 text-center whitespace-nowrap">
                                <button wire:click="abrirEditar({{ $l->id }})" class="text-slate-500 hover:text-brand p-1">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <button wire:click="eliminar({{ $l->id }})" wire:confirm="¿Eliminar lección?"
                                        class="text-rose-400 hover:text-rose-600 p-1">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($modal)
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
             wire:click.self="cerrarModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
                <div class="px-4 py-3 border-b bg-gradient-to-br from-violet-500 to-purple-500 text-white flex items-center justify-between">
                    <h3 class="font-bold text-sm flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap"></i>
                        {{ $editandoId ? 'Editar' : 'Nueva' }} lección
                    </h3>
                    <button wire:click="cerrarModal" type="button" class="text-white/80 hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-4 space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Categoría *</label>
                        <select wire:model="categoria" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                            @foreach($categorias as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">Título *</label>
                        <input type="text" wire:model="titulo" maxlength="200"
                               placeholder='Ej: No confundir "recojo" con dirección'
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                        @error('titulo') <p class="text-[11px] text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                            Frase disparadora <span class="text-slate-400 font-normal">(cuando el cliente diga...)</span>
                        </label>
                        <input type="text" wire:model="frase_disparadora" maxlength="200"
                               placeholder='recojo, paso por allá, voy a recoger'
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-rose-700 mb-1"><i class="fa-solid fa-circle-xmark"></i> Qué NO debe hacer el bot</label>
                        <textarea wire:model="contexto_error" rows="3" maxlength="1000"
                                  placeholder='Ej: Interpretar "recojo" como una dirección o ciudad'
                                  class="w-full rounded-lg border border-rose-200 bg-rose-50/30 px-3 py-1.5 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-emerald-700 mb-1"><i class="fa-solid fa-circle-check"></i> Qué SÍ debe hacer</label>
                        <textarea wire:model="regla" rows="3" maxlength="1000"
                                  placeholder='Ej: Entender que el cliente quiere RECOGER en sede. Confirmar sede y NO llamar validar_cobertura'
                                  class="w-full rounded-lg border border-emerald-200 bg-emerald-50/30 px-3 py-1.5 text-sm"></textarea>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="activa" class="rounded">
                        <span class="text-sm text-slate-700">Lección activa (se inyecta al prompt del bot)</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="cerrarModal" class="px-3 py-1.5 text-xs rounded-lg border border-slate-200 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-brand hover:bg-brand-dark text-white">
                            <i class="fa-solid fa-{{ $editandoId ? 'check' : 'plus' }} text-[10px]"></i>
                            {{ $editandoId ? 'Guardar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
