<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-fuchsia-500 to-fuchsia-700 text-white shadow-lg">
                        <i class="fa-solid fa-square-poll-vertical text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Constructor de Encuestas</h2>
                        <p class="text-sm text-slate-500">Crea encuestas con los campos que tú quieras y compártelas por link</p>
                    </div>
                </div>
                @if($vista === 'lista')
                    <button wire:click="nuevaEncuesta"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                        <i class="fa-solid fa-plus"></i> Nueva encuesta
                    </button>
                @else
                    <button wire:click="volver" class="inline-flex items-center gap-2 rounded-2xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-5 py-3 transition">
                        <i class="fa-solid fa-arrow-left"></i> Volver
                    </button>
                @endif
            </div>
        </div>

        {{-- ══════════ LISTA ══════════ --}}
        @if($vista === 'lista')
            @if($encuestas->isEmpty())
                <div class="rounded-2xl bg-white border-2 border-dashed border-slate-200 p-10 text-center text-slate-500">
                    <i class="fa-solid fa-square-poll-vertical text-3xl mb-3 text-slate-300"></i>
                    <p class="font-semibold">Aún no tienes encuestas.</p>
                    <p class="text-sm">Pulsa <strong>Nueva encuesta</strong> para crear la primera con tus propios campos.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach($encuestas as $e)
                        <div class="rounded-2xl bg-white border-2 {{ $e->activa ? 'border-slate-200' : 'border-rose-200 opacity-80' }} shadow-sm overflow-hidden flex flex-col">
                            <div class="p-5 border-b border-slate-100">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-lg font-extrabold text-slate-800">{{ $e->nombre }}</h3>
                                    @if(!$e->activa)
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Inactiva</span>
                                    @endif
                                </div>
                                @if($e->descripcion)<p class="text-xs text-slate-500 mt-1">{{ $e->descripcion }}</p>@endif
                                <div class="flex items-center gap-4 mt-3 text-xs text-slate-500">
                                    <span><i class="fa-solid fa-list-ul"></i> {{ $e->campos_count }} campos</span>
                                    <span><i class="fa-solid fa-inbox"></i> {{ $e->respuestas_count }} respuestas</span>
                                </div>
                            </div>

                            {{-- Link público --}}
                            <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between gap-2">
                                <code class="text-xs text-slate-600 truncate">{{ url('/e/'.$e->token) }}</code>
                                <button onclick="navigator.clipboard.writeText('{{ url('/e/'.$e->token) }}')"
                                        class="shrink-0 text-xs px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold" title="Copiar link">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </div>

                            <div class="p-4 grid grid-cols-2 gap-2 mt-auto">
                                <button wire:click="abrirCampos({{ $e->id }})" class="text-xs px-3 py-2 rounded-lg bg-brand-soft text-brand-dark hover:bg-brand-soft/70 font-bold">
                                    <i class="fa-solid fa-sliders"></i> Campos
                                </button>
                                <button wire:click="abrirResultados({{ $e->id }})" class="text-xs px-3 py-2 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold">
                                    <i class="fa-solid fa-chart-column"></i> Resultados
                                </button>
                                <button wire:click="editarEncuesta({{ $e->id }})" class="text-xs px-3 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold">
                                    <i class="fa-solid fa-pen"></i> Editar
                                </button>
                                <button wire:click="toggleActiva({{ $e->id }})" class="text-xs px-3 py-2 rounded-lg {{ $e->activa ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }} font-semibold">
                                    {{ $e->activa ? 'Desactivar' : 'Activar' }}
                                </button>
                                <button wire:click="eliminarEncuesta({{ $e->id }})" wire:confirm="¿Eliminar esta encuesta y todas sus respuestas?"
                                        class="col-span-2 text-xs px-3 py-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 font-semibold">
                                    <i class="fa-solid fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- ══════════ CAMPOS ══════════ --}}
        @if($vista === 'campos' && $encuestaCampos)
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">Campos de «{{ $encuestaCampos->nombre }}»</h3>
                <button wire:click="nuevoCampo" class="inline-flex items-center gap-2 rounded-xl bg-brand text-white font-bold px-4 py-2 text-sm hover:bg-brand-dark">
                    <i class="fa-solid fa-plus"></i> Agregar campo
                </button>
            </div>

            @if($encuestaCampos->campos->isEmpty())
                <div class="rounded-2xl bg-white border-2 border-dashed border-slate-200 p-8 text-center text-slate-500 text-sm">
                    Sin campos aún. Agrega el primero (una pregunta o dato que quieras capturar).
                </div>
            @else
                <div class="space-y-2">
                    @foreach($encuestaCampos->campos as $i => $c)
                        <div class="rounded-xl bg-white border border-slate-200 p-4 flex items-center gap-3">
                            <div class="flex flex-col gap-1">
                                <button wire:click="moverCampo({{ $c->id }},'up')" @disabled($loop->first) class="text-slate-300 hover:text-slate-600 disabled:opacity-30"><i class="fa-solid fa-caret-up"></i></button>
                                <button wire:click="moverCampo({{ $c->id }},'down')" @disabled($loop->last) class="text-slate-300 hover:text-slate-600 disabled:opacity-30"><i class="fa-solid fa-caret-down"></i></button>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-slate-800">{{ $c->etiqueta }}</span>
                                    @if($c->requerido)<span class="text-[10px] font-bold text-rose-600">*obligatorio</span>@endif
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ \App\Models\EncuestaCampo::TIPOS[$c->tipo] ?? $c->tipo }}</span>
                                </div>
                                @if($c->esOpcionado() && !empty($c->opciones['lista']))
                                    <p class="text-xs text-slate-400 mt-0.5 truncate">Opciones: {{ implode(' · ', $c->opciones['lista']) }}</p>
                                @endif
                            </div>
                            <button wire:click="editarCampo({{ $c->id }})" class="text-slate-400 hover:text-slate-700 px-2"><i class="fa-solid fa-pen"></i></button>
                            <button wire:click="eliminarCampo({{ $c->id }})" wire:confirm="¿Eliminar este campo?" class="text-slate-400 hover:text-rose-600 px-2"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- ══════════ RESULTADOS ══════════ --}}
        @if($vista === 'resultados' && $resultados)
            <h3 class="text-lg font-bold text-slate-800">Resultados de «{{ $resultados->nombre }}» ({{ $resultados->respuestasCompletadas->count() }})</h3>
            @if($resultados->respuestasCompletadas->isEmpty())
                <div class="rounded-2xl bg-white border-2 border-dashed border-slate-200 p-8 text-center text-slate-500 text-sm">Aún no hay respuestas.</div>
            @else
                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">Fecha</th>
                                <th class="px-3 py-2 text-left">Quién</th>
                                @foreach($resultados->campos as $c)
                                    <th class="px-3 py-2 text-left">{{ $c->etiqueta }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($resultados->respuestasCompletadas as $r)
                                @php $porCampo = $r->valores->keyBy('encuesta_campo_id'); @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 whitespace-nowrap text-slate-500">{{ optional($r->completada_at)->format('d/m/Y h:i a') }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $r->respondente_nombre ?: '—' }}</td>
                                    @foreach($resultados->campos as $c)
                                        <td class="px-3 py-2">
                                            @php $val = optional($porCampo->get($c->id))->legible; @endphp
                                            @if($c->tipo === 'estrellas' && $val !== null && $val !== '')
                                                <span class="text-amber-500">{{ str_repeat('★', (int) $val) }}<span class="text-slate-300">{{ str_repeat('★', max(0, 5 - (int) $val)) }}</span></span>
                                            @else
                                                {{ $val !== null && $val !== '' ? $val : '—' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    {{-- ══════ MODAL ENCUESTA ══════ --}}
    @if($modalEncuesta)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50" wire:click.self="$set('modalEncuesta', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
                <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold text-slate-800">{{ $encuestaId ? 'Editar encuesta' : 'Nueva encuesta' }}</h3>
                    <button wire:click="$set('modalEncuesta', false)" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nombre</label>
                        <input wire:model="nombre" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Ej. Satisfacción del cliente">
                        @error('nombre')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descripción (opcional)</label>
                        <textarea wire:model="descripcion" rows="2" class="w-full rounded-xl border-slate-200 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Mensaje de agradecimiento (tras responder)</label>
                        <input wire:model="mensaje_gracias" class="w-full rounded-xl border-slate-200 text-sm" placeholder="¡Gracias por tu tiempo! 🙏">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="activa" class="rounded border-slate-300 text-brand"> Encuesta activa (acepta respuestas)
                    </label>
                </div>
                <div class="border-t border-slate-100 px-6 py-4 flex justify-end gap-2">
                    <button wire:click="$set('modalEncuesta', false)" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-semibold text-sm hover:bg-slate-200">Cancelar</button>
                    <button wire:click="guardarEncuesta" class="px-5 py-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary text-white font-bold text-sm hover:from-brand-dark hover:to-brand-dark">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════ MODAL CAMPO ══════ --}}
    @if($modalCampo)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50" wire:click.self="$set('modalCampo', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold text-slate-800">{{ $campoId ? 'Editar campo' : 'Nuevo campo' }}</h3>
                    <button wire:click="$set('modalCampo', false)" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Etiqueta / Pregunta</label>
                        <input wire:model="c_etiqueta" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Ej. ¿Cómo calificas la atención?">
                        @error('c_etiqueta')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo de campo</label>
                        <select wire:model.live="c_tipo" class="w-full rounded-xl border-slate-200 text-sm">
                            @foreach(\App\Models\EncuestaCampo::TIPOS as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(in_array($c_tipo, ['radio','checkbox']))
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Opciones (una por línea)</label>
                            <textarea wire:model="c_opciones_text" rows="4" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Excelente&#10;Bueno&#10;Regular&#10;Malo"></textarea>
                            @error('c_opciones_text')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        </div>
                    @endif
                    @if(in_array($c_tipo, ['texto','textarea','numero']))
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Placeholder (opcional)</label>
                            <input wire:model="c_placeholder" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Texto de ayuda dentro del campo">
                        </div>
                    @endif
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="c_requerido" class="rounded border-slate-300 text-brand"> Campo obligatorio
                    </label>
                </div>
                <div class="border-t border-slate-100 px-6 py-4 flex justify-end gap-2">
                    <button wire:click="$set('modalCampo', false)" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-semibold text-sm hover:bg-slate-200">Cancelar</button>
                    <button wire:click="guardarCampo" class="px-5 py-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary text-white font-bold text-sm hover:from-brand-dark hover:to-brand-dark">Guardar campo</button>
                </div>
            </div>
        </div>
    @endif
</div>
