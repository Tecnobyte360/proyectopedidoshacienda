@php
    $isMobile = $isMobile ?? false;
    $btnBase = 'inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-bold text-white transition disabled:opacity-60';
@endphp

@if($pedido->estado === \App\Models\Pedido::ESTADO_NUEVO)
    <button type="button"
            wire:click="marcarEnPreparacion({{ $pedido->id }})"
            wire:loading.attr="disabled"
            wire:target="marcarEnPreparacion({{ $pedido->id }})"
            class="{{ $btnBase }} bg-amber-500 hover:bg-amber-600">
        <i class="fa-solid fa-utensils" wire:loading.class="hidden" wire:target="marcarEnPreparacion({{ $pedido->id }})"></i>
        <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="marcarEnPreparacion({{ $pedido->id }})"></i>
        <span>Iniciar preparación</span>
    </button>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_EN_PREPARACION)
    <button type="button"
            wire:click="abrirModalDespacho({{ $pedido->id }})"
            wire:loading.attr="disabled"
            wire:target="abrirModalDespacho({{ $pedido->id }})"
            class="{{ $btnBase }} bg-violet-500 hover:bg-violet-600">
        <i class="fa-solid fa-motorcycle" wire:loading.class="hidden" wire:target="abrirModalDespacho({{ $pedido->id }})"></i>
        <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="abrirModalDespacho({{ $pedido->id }})"></i>
        <span>{{ $pedido->domiciliario ? 'Reasignar y despachar' : 'Asignar y despachar' }}</span>
    </button>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)
    <button type="button"
            wire:click="abrirModalEntrega({{ $pedido->id }})"
            class="{{ $btnBase }} bg-emerald-500 hover:bg-emerald-600">
        <i class="fa-solid fa-circle-check"></i>
        <span>Confirmar entrega</span>
    </button>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_ENTREGADO)
    <span class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs font-bold text-emerald-700">
        <i class="fa-solid fa-circle-check"></i>
        Entregado
    </span>

@elseif($pedido->estado === \App\Models\Pedido::ESTADO_CANCELADO)
    <span class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs font-bold text-rose-700">
        <i class="fa-solid fa-ban"></i>
        Cancelado
    </span>

@else
    <span class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-xs font-bold text-slate-600">
        <i class="fa-solid fa-circle-info"></i>
        {{ ucfirst(str_replace('_', ' ', $pedido->estado)) }}
    </span>
@endif
