@php
    $isMobile = $isMobile ?? false;
    // Base premium: gradiente diagonal + sombra de color + scale en hover
    $btnBase = 'group inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-bold text-white transition-all duration-200 disabled:opacity-60 shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-md ring-1 ring-white/20';
@endphp

@if($pedido->estado === \App\Models\Pedido::ESTADO_NUEVO)
    {{-- Iniciar preparación: ámbar cálido con un toque dorado, evoca "encender los fogones" --}}
    <button type="button"
            wire:click="marcarEnPreparacion({{ $pedido->id }})"
            wire:loading.attr="disabled"
            wire:target="marcarEnPreparacion({{ $pedido->id }})"
            class="{{ $btnBase }} bg-gradient-to-br from-amber-400 via-amber-500 to-orange-500 hover:from-amber-500 hover:to-orange-600 shadow-amber-500/30 hover:shadow-amber-500/40">
        <i class="fa-solid fa-utensils transition-transform group-hover:scale-110" wire:loading.class="hidden" wire:target="marcarEnPreparacion({{ $pedido->id }})"></i>
        <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="marcarEnPreparacion({{ $pedido->id }})"></i>
        <span>Iniciar preparación</span>
    </button>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_EN_PREPARACION)
    {{-- Asignar y despachar: violeta-índigo profundo, transmite "movimiento" --}}
    <button type="button"
            wire:click="abrirModalDespacho({{ $pedido->id }})"
            wire:loading.attr="disabled"
            wire:target="abrirModalDespacho({{ $pedido->id }})"
            class="{{ $btnBase }} bg-gradient-to-br from-violet-500 via-purple-600 to-indigo-600 hover:from-violet-600 hover:to-indigo-700 shadow-violet-500/30 hover:shadow-violet-500/40">
        <i class="fa-solid fa-motorcycle transition-transform group-hover:translate-x-0.5" wire:loading.class="hidden" wire:target="abrirModalDespacho({{ $pedido->id }})"></i>
        <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="abrirModalDespacho({{ $pedido->id }})"></i>
        <span>{{ $pedido->domiciliario ? 'Reasignar y despachar' : 'Asignar y despachar' }}</span>
    </button>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)
    {{-- Confirmar entrega: verde esmeralda → teal, transmite "logro completo" --}}
    <button type="button"
            wire:click="abrirModalEntrega({{ $pedido->id }})"
            class="{{ $btnBase }} bg-gradient-to-br from-emerald-400 via-emerald-500 to-teal-600 hover:from-emerald-500 hover:to-teal-700 shadow-emerald-500/30 hover:shadow-emerald-500/40">
        <i class="fa-solid fa-circle-check transition-transform group-hover:scale-110"></i>
        <span>Confirmar entrega</span>
    </button>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_ENTREGADO)
    <span class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100/50 px-3 py-2.5 text-xs font-bold text-emerald-700 shadow-sm">
        <i class="fa-solid fa-circle-check"></i>
        Entregado
    </span>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_CANCELADO)
    <span class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-gradient-to-br from-rose-50 to-rose-100/50 px-3 py-2.5 text-xs font-bold text-rose-700 shadow-sm">
        <i class="fa-solid fa-ban"></i>
        Cancelado
    </span>

@else
    <span class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-xs font-bold text-slate-600">
        <i class="fa-solid fa-circle-info"></i>
        {{ ucfirst(str_replace('_', ' ', $pedido->estado)) }}
    </span>
@endif
