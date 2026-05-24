<div>
@php $info = $this->info; @endphp
@if($info && !$oculto)
    <div class="rounded-2xl {{ $info['estilos']['bg'] }} border {{ $info['estilos']['border'] }} shadow-sm p-4 mb-4 flex items-center gap-4 flex-wrap"
         x-data
         x-on:abrir-url.window="window.open($event.detail[0]?.url || $event.detail.url, '_blank')">

        <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $info['estilos']['iconBg'] }} flex-shrink-0">
            <i class="fa-solid {{ $info['estilos']['icon'] }} text-lg"></i>
        </div>

        <div class="flex-1 min-w-[200px]">
            <div class="font-bold text-sm {{ $info['estilos']['text'] }} flex items-center gap-2 flex-wrap">
                {{ $info['mensaje'] }}
                @if($info['plan'])
                    <span class="text-[10px] uppercase {{ $info['estilos']['iconBg'] }} rounded-full px-2 py-0.5 font-bold">
                        Plan {{ $info['plan'] }}
                    </span>
                @endif
            </div>
            <div class="text-xs {{ $info['estilos']['textSub'] }} mt-0.5">
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
                       class="inline-flex items-center gap-2 {{ $info['estilos']['btnBg'] }} text-white rounded-xl px-5 py-2.5 text-sm font-extrabold shadow-md transition-all hover:scale-105 hover:shadow-lg">
                        <i class="fa-solid fa-credit-card"></i>
                        Pagar ahora
                    </a>
                @else
                    <button type="button" wire:click="pagarAhora"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 {{ $info['estilos']['btnBg'] }} text-white rounded-xl px-5 py-2.5 text-sm font-extrabold shadow-md transition-all hover:scale-105 hover:shadow-lg disabled:opacity-50 disabled:hover:scale-100">
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
                        class="{{ $info['estilos']['textSub'] }} opacity-60 hover:opacity-100 p-2 rounded-lg hover:bg-white/60 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            @endif
        </div>
    </div>
@endif
</div>
