<div class="min-h-screen bg-slate-100 py-8 px-4">
    <div class="max-w-xl mx-auto">
        @if($enviada)
            <div class="bg-white rounded-2xl shadow-lg p-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <i class="fa-solid fa-check text-2xl"></i>
                </div>
                <h1 class="text-xl font-extrabold text-slate-800 mb-2">¡Listo!</h1>
                <p class="text-slate-500">{{ $encuesta->mensaje_gracias ?: 'Gracias por responder. 🙏' }}</p>
            </div>
        @else
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-brand to-brand-secondary p-6 text-white">
                    <h1 class="text-2xl font-extrabold">{{ $encuesta->nombre }}</h1>
                    @if($encuesta->descripcion)<p class="text-white/90 text-sm mt-1">{{ $encuesta->descripcion }}</p>@endif
                </div>

                <form wire:submit="enviar" class="p-6 space-y-6">
                    {{-- Nombre de quien responde (opcional) --}}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Tu nombre <span class="text-slate-400 font-normal">(opcional)</span></label>
                        <input wire:model="respondente_nombre" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>

                    @foreach($encuesta->campos as $c)
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                {{ $c->etiqueta }} @if($c->requerido)<span class="text-rose-500">*</span>@endif
                            </label>

                            @switch($c->tipo)
                                @case('estrellas')
                                    <div class="flex gap-1" x-data="{ v: @entangle('valores.'.$c->id) }">
                                        @for($s = 1; $s <= 5; $s++)
                                            <button type="button" @click="v = {{ $s }}"
                                                    class="text-3xl transition"
                                                    :class="(v >= {{ $s }}) ? 'text-amber-400' : 'text-slate-300 hover:text-amber-200'">★</button>
                                        @endfor
                                    </div>
                                    @break

                                @case('texto')
                                    <input wire:model="valores.{{ $c->id }}" placeholder="{{ $c->placeholder }}" class="w-full rounded-xl border-slate-200 text-sm">
                                    @break

                                @case('textarea')
                                    <textarea wire:model="valores.{{ $c->id }}" rows="3" placeholder="{{ $c->placeholder }}" class="w-full rounded-xl border-slate-200 text-sm"></textarea>
                                    @break

                                @case('numero')
                                    <input type="number" wire:model="valores.{{ $c->id }}" placeholder="{{ $c->placeholder }}" class="w-full rounded-xl border-slate-200 text-sm">
                                    @break

                                @case('si_no')
                                    <div class="flex gap-3">
                                        @foreach(['Sí','No'] as $op)
                                            <label class="flex-1 cursor-pointer">
                                                <input type="radio" wire:model="valores.{{ $c->id }}" value="{{ $op }}" class="peer sr-only">
                                                <div class="text-center py-2.5 rounded-xl border-2 border-slate-200 text-slate-600 font-semibold peer-checked:border-brand peer-checked:bg-brand-soft peer-checked:text-brand-dark">{{ $op }}</div>
                                            </label>
                                        @endforeach
                                    </div>
                                    @break

                                @case('radio')
                                    <div class="space-y-2">
                                        @foreach(($c->opciones['lista'] ?? []) as $op)
                                            <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-slate-50">
                                                <input type="radio" wire:model="valores.{{ $c->id }}" value="{{ $op }}" class="text-brand border-slate-300">
                                                <span class="text-sm text-slate-700">{{ $op }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @break

                                @case('checkbox')
                                    <div class="space-y-2">
                                        @foreach(($c->opciones['lista'] ?? []) as $op)
                                            <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-slate-50">
                                                <input type="checkbox" wire:model="valores.{{ $c->id }}" value="{{ $op }}" class="rounded text-brand border-slate-300">
                                                <span class="text-sm text-slate-700">{{ $op }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @break
                            @endswitch

                            @error('valores.'.$c->id)<span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span>@enderror
                        </div>
                    @endforeach

                    <button type="submit" wire:loading.attr="disabled"
                            class="w-full rounded-xl bg-gradient-to-r from-brand to-brand-secondary text-white font-bold py-3 hover:from-brand-dark hover:to-brand-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="enviar">Enviar respuesta</span>
                        <span wire:loading wire:target="enviar">Enviando…</span>
                    </button>
                </form>
            </div>
        @endif
        <p class="text-center text-xs text-slate-400 mt-4">Powered by KIVOX</p>
    </div>
</div>
