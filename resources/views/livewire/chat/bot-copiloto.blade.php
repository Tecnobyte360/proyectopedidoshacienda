<div>
    @if($cargando)
        <div class="mx-3 mb-2 rounded-xl border border-violet-200 bg-violet-50/60 px-4 py-3">
            <div class="flex items-center gap-2 text-[13px] text-violet-600">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                El bot está pensando una sugerencia…
            </div>
        </div>
    @elseif($texto && !$oculto)
        <div class="mx-3 mb-2 rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white shadow-sm overflow-hidden">
            {{-- Encabezado: deja CLARÍSIMO que no se envía --}}
            <div class="flex items-center justify-between px-4 py-2 bg-violet-100/60 border-b border-violet-200">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-violet-700">
                    <i class="fa-solid fa-robot text-[11px]"></i>
                    Sugerencia del bot
                </span>
                <span class="text-[10px] font-semibold text-rose-600 inline-flex items-center gap-1">
                    <i class="fa-solid fa-lock text-[9px]"></i>
                    NO se envía al cliente · solo entrenamiento
                </span>
            </div>

            {{-- Texto sugerido --}}
            <div class="px-4 py-3">
                <p class="text-[13.5px] text-slate-700 whitespace-pre-wrap leading-relaxed">{{ $texto }}</p>
            </div>

            {{-- Botones de decisión --}}
            <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50/80 border-t border-slate-100">
                <button wire:click="usar"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-semibold transition">
                    <i class="fa-solid fa-check text-[10px]"></i> Usar tal cual
                </button>
                <button wire:click="editar"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-slate-100 ring-1 ring-slate-200 text-slate-700 text-[12px] font-semibold transition">
                    <i class="fa-solid fa-pen text-[10px]"></i> Editar y enviar
                </button>
                <button wire:click="ignorar"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-400 hover:text-rose-600 text-[12px] font-medium transition ml-auto">
                    <i class="fa-solid fa-xmark text-[10px]"></i> Ignorar
                </button>
            </div>
        </div>
    @endif
</div>
