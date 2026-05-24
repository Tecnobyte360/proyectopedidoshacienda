<div>
@php $info = $this->info; @endphp
@if($info && !$oculto)
    <div class="rounded-2xl bg-gradient-to-r {{ $info['estilos']['bg'] }} text-white shadow-lg p-4 mb-4 flex items-center gap-4 flex-wrap"
         x-data
         x-on:abrir-url.window="window.open($event.detail[0]?.url || $event.detail.url, '_blank')">

        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm flex-shrink-0">
            <i class="fa-solid {{ $info['estilos']['icon'] }} text-2xl"></i>
        </div>

        <div class="flex-1 min-w-[200px]">
            <div class="font-bold text-base flex items-center gap-2 flex-wrap">
                {{ $info['mensaje'] }}
                @if($info['plan'])
                    <span class="text-[10px] uppercase bg-white/25 rounded-full px-2 py-0.5 font-bold">
                        Plan {{ $info['plan'] }}
                    </span>
                @endif
            </div>
            <div class="text-xs text-white/90 mt-0.5">
                Fecha límite: <strong>{{ $info['fecha_fin']->format('d/m/Y') }}</strong>
                @if($info['monto'] > 0)
                    · Monto: <strong>${{ number_format($info['monto'], 0, ',', '.') }} COP</strong>
                @endif
                @if($info['sev'] === 'rojo')
                    · <strong>⚠️ Tu acceso será suspendido pronto</strong>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            @if($info['monto'] > 0)
                @if($info['link_pago'])
                    <a href="{{ $info['link_pago'] }}" target="_blank"
                       class="inline-flex items-center gap-2 bg-white text-slate-800 hover:bg-slate-100 rounded-xl px-4 py-2 text-sm font-bold shadow transition">
                        <i class="fa-solid fa-credit-card"></i>
                        Pagar ahora
                    </a>
                @else
                    <button type="button" wire:click="pagarAhora"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 bg-white text-slate-800 hover:bg-slate-100 rounded-xl px-4 py-2 text-sm font-bold shadow transition disabled:opacity-50">
                        <i class="fa-solid fa-credit-card" wire:loading.remove wire:target="pagarAhora"></i>
                        <i class="fa-solid fa-spinner animate-spin" wire:loading wire:target="pagarAhora"></i>
                        Pagar ahora
                    </button>
                @endif
            @endif

            {{-- Solo permitir dismiss si no está vencida (sev rojo no se puede ocultar) --}}
            @if($info['sev'] !== 'rojo')
                <button type="button" wire:click="ocultar"
                        title="Ocultar hasta mañana"
                        class="text-white/70 hover:text-white p-2 rounded-lg hover:bg-white/10 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            @endif
        </div>
    </div>
@endif
</div>
