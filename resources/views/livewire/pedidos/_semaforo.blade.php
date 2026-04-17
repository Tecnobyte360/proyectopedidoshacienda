@php
    $sem  = $pedido->semaforoEstado();
    $size = $size ?? 'md';   // sm | md | lg
    $modo = $modo ?? 'completo';   // completo | dot | barra

    $colores = [
        'verde'    => ['bg' => 'bg-emerald-500', 'bgLight' => 'bg-emerald-50',  'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'shadow' => 'shadow-emerald-300'],
        'amarillo' => ['bg' => 'bg-amber-500',   'bgLight' => 'bg-amber-50',    'text' => 'text-amber-700',   'border' => 'border-amber-200',   'shadow' => 'shadow-amber-300'],
        'rojo'     => ['bg' => 'bg-rose-500',    'bgLight' => 'bg-rose-50',     'text' => 'text-rose-700',    'border' => 'border-rose-200',    'shadow' => 'shadow-rose-300'],
        'gris'     => ['bg' => 'bg-slate-400',   'bgLight' => 'bg-slate-100',   'text' => 'text-slate-600',   'border' => 'border-slate-200',   'shadow' => 'shadow-slate-300'],
    ];
    $c = $colores[$sem['color']] ?? $colores['gris'];

    $iconColor = match($sem['color']) {
        'verde'    => 'fa-circle-check',
        'amarillo' => 'fa-triangle-exclamation',
        'rojo'     => 'fa-circle-exclamation',
        default    => 'fa-circle',
    };

    $tooltip = $sem['ans_nombre']
        ? $sem['ans_nombre'] . ' — Objetivo ' . $sem['minutos_objetivo'] . 'min · Alerta ' . $sem['minutos_alerta'] . 'min · Crítico ' . $sem['minutos_critico'] . 'min'
        : 'Sin ANS configurado';
@endphp

@if($modo === 'dot')
    {{-- Solo punto pulsante --}}
    <span class="relative inline-flex h-2.5 w-2.5" title="{{ $tooltip }}">
        @if($sem['color'] !== 'gris')
            <span class="absolute inline-flex h-full w-full rounded-full {{ $c['bg'] }} opacity-60 animate-ping"></span>
        @endif
        <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $c['bg'] }}"></span>
    </span>

@elseif($modo === 'barra')
    {{-- Barra de progreso --}}
    <div class="w-full" title="{{ $tooltip }}">
        <div class="flex items-center justify-between text-[10px] mb-0.5">
            <span class="font-semibold {{ $c['text'] }}">
                <i class="fa-solid {{ $iconColor }} mr-0.5"></i>
                {{ $sem['mensaje'] }}
            </span>
            @if($sem['ans_nombre'])
                <span class="text-slate-400">/{{ $sem['minutos_critico'] }}min</span>
            @endif
        </div>
        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full {{ $c['bg'] }} transition-all duration-500"
                 style="width: {{ $sem['porcentaje'] }}%"></div>
        </div>
    </div>

@else
    {{-- Pill completa --}}
    @php
        $padding = $size === 'sm' ? 'px-2 py-0.5' : ($size === 'lg' ? 'px-3 py-1.5' : 'px-2.5 py-1');
        $textSize = $size === 'sm' ? 'text-[10px]' : ($size === 'lg' ? 'text-xs' : 'text-[11px]');
    @endphp
    <span class="inline-flex items-center gap-1.5 rounded-full border {{ $c['border'] }} {{ $c['bgLight'] }} {{ $padding }} {{ $textSize }} font-semibold {{ $c['text'] }} whitespace-nowrap"
          title="{{ $tooltip }}">
        <span class="relative inline-flex h-2 w-2">
            @if($sem['color'] !== 'gris' && $sem['color'] !== 'verde')
                <span class="absolute inline-flex h-full w-full rounded-full {{ $c['bg'] }} opacity-75 animate-ping"></span>
            @endif
            <span class="relative inline-flex rounded-full h-2 w-2 {{ $c['bg'] }}"></span>
        </span>
        <i class="fa-solid {{ $iconColor }} text-[9px]"></i>
        <span>{{ $sem['mensaje'] }}</span>
    </span>
@endif
