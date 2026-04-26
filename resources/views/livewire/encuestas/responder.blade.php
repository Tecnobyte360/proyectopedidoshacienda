<div>
    @push('styles')
    <style>
        .star-btn { background: none; border: 0; cursor: pointer; padding: 0.25rem; transition: transform 0.1s; }
        .star-btn:hover { transform: scale(1.18); }
        .star-btn:disabled { cursor: default; }
        .star-icon { font-size: 2.25rem; color: #e2e8f0; transition: color 0.15s; }
        .star-icon.active { color: #f59e0b; text-shadow: 0 2px 8px rgba(245, 158, 11, 0.35); }
        .form-card {
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 8px 32px -12px rgba(15, 23, 42, 0.12);
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        @media (min-width: 640px) { .form-card { padding: 2rem; } }
    </style>
    @endpush

    <div class="form-card space-y-5">
        @if($encuesta->isCompletada())
            <div class="text-center py-6">
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full mb-3"
                     style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fa-solid fa-check text-white text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-1">¡Gracias por tu opinión!</h2>
                <p class="text-sm text-slate-600">Recibimos tu respuesta y la usaremos para mejorar.</p>

                <div class="mt-5 rounded-xl bg-slate-50 border border-slate-200 p-4 text-left text-sm space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-600">Proceso:</span>
                        <span>
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fa-solid fa-star {{ $i <= $encuesta->calificacion_proceso ? 'text-amber-500' : 'text-slate-300' }}"></i>
                            @endfor
                        </span>
                    </div>
                    @if($encuesta->calificacion_domiciliario)
                        <div class="flex items-center gap-2">
                            <span class="text-slate-600">Domiciliario:</span>
                            <span>
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="fa-solid fa-star {{ $i <= $encuesta->calificacion_domiciliario ? 'text-amber-500' : 'text-slate-300' }}"></i>
                                @endfor
                            </span>
                        </div>
                    @endif
                    @if($encuesta->recomendaria !== null)
                        <div class="text-slate-600">
                            ¿Recomendarías?: <strong class="{{ $encuesta->recomendaria ? 'text-emerald-600' : 'text-rose-600' }}">{{ $encuesta->recomendaria ? 'Sí 👍' : 'No 👎' }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        @else

        <div class="text-center pb-2 border-b border-slate-100">
            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800">¡Hola{{ $pedido?->cliente_nombre ? ', ' . explode(' ', $pedido->cliente_nombre)[0] : '' }}!</h2>
            <p class="text-sm text-slate-500 mt-1">Tu opinión es muy importante. Solo te tomará 30 segundos.</p>
        </div>

        {{-- Calificación del proceso --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">
                <i class="fa-solid fa-utensils text-slate-400 mr-1"></i>
                ¿Cómo fue tu experiencia con tu pedido?
                <span class="text-rose-500">*</span>
            </label>
            <div class="flex items-center gap-1">
                @for($i = 1; $i <= 5; $i++)
                    <button type="button" wire:click="setRating('calificacion_proceso', {{ $i }})" class="star-btn">
                        <i class="fa-solid fa-star star-icon {{ $i <= $calificacion_proceso ? 'active' : '' }}"></i>
                    </button>
                @endfor
                <span class="ml-2 text-xs text-slate-500">
                    @if($calificacion_proceso > 0)
                        {{ ['','Mala','Regular','Buena','Muy buena','Excelente'][$calificacion_proceso] ?? '' }}
                    @endif
                </span>
            </div>
            @error('calificacion_proceso') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror

            <textarea wire:model="comentario_proceso" rows="2"
                      placeholder="Cuéntanos algo (opcional)…"
                      maxlength="1000"
                      class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-amber-400 focus:ring-2 focus:ring-amber-100"></textarea>
        </div>

        {{-- Calificación del domiciliario (si hay) --}}
        @if($domiciliario)
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i class="fa-solid fa-motorcycle text-slate-400 mr-1"></i>
                    ¿Cómo te atendió <strong>{{ $domiciliario->nombre }}</strong>?
                </label>
                <div class="flex items-center gap-1">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" wire:click="setRating('calificacion_domiciliario', {{ $i }})" class="star-btn">
                            <i class="fa-solid fa-star star-icon {{ $i <= $calificacion_domiciliario ? 'active' : '' }}"></i>
                        </button>
                    @endfor
                </div>

                <textarea wire:model="comentario_domiciliario" rows="2"
                          placeholder="¿Algún comentario sobre el repartidor?"
                          maxlength="1000"
                          class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-amber-400 focus:ring-2 focus:ring-amber-100"></textarea>
            </div>
        @endif

        {{-- ¿Recomendaría? --}}
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">
                <i class="fa-solid fa-thumbs-up text-slate-400 mr-1"></i>
                ¿Nos recomendarías a un amigo?
            </label>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="setRecomendaria(true)"
                        class="rounded-xl border-2 px-4 py-3 text-sm font-bold transition
                            {{ $recomendaria === true ? 'border-emerald-400 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}">
                    <i class="fa-solid fa-thumbs-up mr-1"></i> Sí
                </button>
                <button type="button" wire:click="setRecomendaria(false)"
                        class="rounded-xl border-2 px-4 py-3 text-sm font-bold transition
                            {{ $recomendaria === false ? 'border-rose-400 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}">
                    <i class="fa-solid fa-thumbs-down mr-1"></i> No
                </button>
            </div>
        </div>

        <button type="button" wire:click="guardar"
                wire:loading.attr="disabled"
                wire:target="guardar"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold text-white shadow-lg transition disabled:opacity-50"
                style="background: linear-gradient(135deg, var(--brand-primary, #d68643), var(--brand-secondary, #a85f24));">
            <span wire:loading.remove wire:target="guardar">
                <i class="fa-solid fa-paper-plane mr-1"></i> Enviar respuesta
            </span>
            <span wire:loading wire:target="guardar">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Enviando…
            </span>
        </button>
        @endif
    </div>
</div>
