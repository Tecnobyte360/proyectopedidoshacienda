<div class="p-4 md:p-6 max-w-[1400px] mx-auto space-y-6" wire:ignore.self>

    {{-- ───────────────────────────────────────── HEADER ─────────────────────────────────────── --}}
    <header class="flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0 flex-1">
            <a href="{{ route('campanas.index') }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 transition">
                <i class="fa-solid fa-arrow-left text-[10px]"></i>
                Campañas
            </a>
            <h1 class="text-[26px] leading-tight font-semibold text-slate-900 mt-1 tracking-tight">
                {{ $campana->nombre }}
            </h1>
            <div class="flex items-center flex-wrap gap-x-3 gap-y-1 mt-1.5 text-[13px] text-slate-500">
                <span class="inline-flex items-center gap-1.5">
                    <i class="fa-regular fa-calendar text-[11px]"></i>
                    {{ $campana->programada_para?->format('d M Y, H:i') ?? 'Sin programar' }}
                </span>
                @if($campana->plantilla_meta_nombre)
                    <span class="text-slate-300">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-bookmark text-[11px] text-slate-400"></i>
                        <code class="font-mono text-[12px] text-slate-700">{{ $campana->plantilla_meta_nombre }}</code>
                    </span>
                @endif
                <span class="text-slate-300">·</span>
                <span @class([
                    'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wide',
                    'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/60' => $campana->estado === 'completada',
                    'bg-sky-50 text-sky-700 ring-1 ring-sky-200/60' => $campana->estado === 'corriendo',
                    'bg-amber-50 text-amber-700 ring-1 ring-amber-200/60' => in_array($campana->estado, ['pausada','programada','borrador']),
                    'bg-rose-50 text-rose-700 ring-1 ring-rose-200/60' => $campana->estado === 'cancelada',
                ])>
                    @if($campana->estado === 'corriendo')
                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                    @endif
                    {{ $campana->estado }}
                </span>
            </div>
        </div>
    </header>

    {{-- ───────────────────────────────────────── KPI ROW ────────────────────────────────────── --}}
    @php
        // En campañas pre-tracking solo conocemos: destinatarios, enviados, respondieron
        $kpisVisibles = $kpis['sin_tracking']
            ? ['total','enviados','respondieron']
            : ['total','enviados','entregados','leidos','respondieron','convirtieron'];

        $fmtPct = fn($p, $suf) => $p === null ? '—' : ($p . '% ' . $suf);
        $allCards = [
            'total'        => ['Destinatarios',  $kpis['total'],        'audiencia',                                          'fa-users',          'slate'],
            'enviados'     => ['Enviados',       $kpis['enviados'],     'salieron por Meta',                                   'fa-paper-plane',    'emerald'],
            'entregados'   => ['Entregados',     $kpis['entregados'],   $fmtPct($kpis['pct_entregados'],   'de enviados'),    'fa-check-double',   'sky'],
            'leidos'       => ['Leídos',         $kpis['leidos'],       $fmtPct($kpis['pct_leidos'],       'open rate'),      'fa-eye',            'indigo'],
            'respondieron' => ['Respondieron',   $kpis['respondieron'], $fmtPct($kpis['pct_respondieron'], 'engagement'),     'fa-comment',        'amber'],
            'convirtieron' => ['Conversión',     $kpis['convirtieron'], $fmtPct($kpis['pct_convirtieron'], '→ pedido'),       'fa-cart-shopping',  'fuchsia'],
        ];
        $kpiCards = collect($kpisVisibles)->map(fn($k) => array_combine(['label','value','sub','icon','tone'], $allCards[$k]))->all();
        $tones = [
            'slate'   => ['ring-slate-200',   'text-slate-500',   'bg-slate-100',   'text-slate-600'],
            'emerald' => ['ring-emerald-200', 'text-emerald-600', 'bg-emerald-100', 'text-emerald-700'],
            'sky'     => ['ring-sky-200',     'text-sky-600',     'bg-sky-100',     'text-sky-700'],
            'indigo'  => ['ring-indigo-200',  'text-indigo-600',  'bg-indigo-100',  'text-indigo-700'],
            'amber'   => ['ring-amber-200',   'text-amber-600',   'bg-amber-100',   'text-amber-700'],
            'fuchsia' => ['ring-fuchsia-200', 'text-fuchsia-600', 'bg-fuchsia-100', 'text-fuchsia-700'],
        ];
    @endphp
    <div @class([
        'grid gap-3',
        'grid-cols-1 sm:grid-cols-3' => $kpis['sin_tracking'],
        'grid-cols-2 md:grid-cols-3 lg:grid-cols-6' => !$kpis['sin_tracking'],
    ])>
        @foreach($kpiCards as $card)
            @php [$ring, $iconText, $iconBg, $subText] = $tones[$card['tone']]; @endphp
            <div class="rounded-xl bg-white p-4 ring-1 {{ $ring }} hover:shadow-sm transition">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">{{ $card['label'] }}</span>
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md {{ $iconBg }} {{ $iconText }}">
                        <i class="fa-solid {{ $card['icon'] }} text-[11px]"></i>
                    </span>
                </div>
                <div class="text-2xl font-semibold text-slate-900 tabular-nums leading-none">{{ number_format($card['value']) }}</div>
                <div class="text-[11px] {{ $subText }} mt-1.5 truncate">{{ $card['sub'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ────────────────────────────────────── PANEL CAMPAÑA SIN TRACKING ─────────────────────────────── --}}
    @if($kpis['sin_tracking'])
        <div class="rounded-xl bg-white ring-1 ring-slate-200 p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 ring-1 ring-amber-200/60 shrink-0">
                    <i class="fa-solid fa-circle-info text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Datos limitados de esta campaña</h3>
                    <p class="text-[13px] text-slate-500 mt-1 leading-relaxed max-w-2xl">
                        Esta campaña se envió antes de implementar tracking detallado de Meta. Solo guardamos
                        el conteo de envíos y respuestas. En tu próxima campaña verás
                        <span class="text-slate-700 font-medium">entregados, leídos, clics en botones, reacciones y conversión a pedido</span>
                        en tiempo real.
                    </p>
                </div>
            </div>

            {{-- Mini resumen visual de lo que sí tenemos --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-6">
                @php
                    $resumen = [
                        ['Tasa de entrega API',   $kpis['total'] > 0 ? round(($kpis['enviados'] / $kpis['total']) * 100, 1) : 0, $kpis['enviados'] . ' / ' . $kpis['total'], 'emerald'],
                        ['Tasa de respuesta',     $kpis['enviados'] > 0 ? round(($kpis['respondieron'] / $kpis['enviados']) * 100, 1) : 0, $kpis['respondieron'] . ' / ' . $kpis['enviados'], 'amber'],
                    ];
                @endphp
                @foreach($resumen as [$titulo, $pct, $ratio, $tone])
                    <div class="rounded-lg ring-1 ring-slate-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[12px] uppercase tracking-wider font-semibold text-slate-500">{{ $titulo }}</span>
                            <span class="text-[11px] text-slate-400 tabular-nums">{{ $ratio }}</span>
                        </div>
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-3xl font-semibold text-slate-900 tabular-nums leading-none">{{ $pct }}<span class="text-lg text-slate-400 font-normal">%</span></span>
                        </div>
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div @class([
                                'h-full rounded-full',
                                'bg-emerald-500' => $tone === 'emerald',
                                'bg-amber-500'   => $tone === 'amber',
                            ]) style="width: {{ min(100, $pct) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else

    {{-- ───────────────────────────────────────── CHARTS ROW 1 ───────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Funnel --}}
        <div class="lg:col-span-2 rounded-xl bg-white ring-1 ring-slate-200">
            <div class="px-5 pt-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Funnel de conversión</h3>
                    <p class="text-[12px] text-slate-500 mt-0.5">Caída de destinatarios paso a paso</p>
                </div>
                <span class="text-[11px] font-medium text-slate-400 tabular-nums">
                    {{ $kpis['total'] }} → {{ $kpis['convirtieron'] }}
                </span>
            </div>
            <div class="p-2">
                <div id="chart-funnel" style="min-height: 280px;"></div>
            </div>
        </div>

        {{-- Donut botones --}}
        <div class="rounded-xl bg-white ring-1 ring-slate-200">
            <div class="px-5 pt-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Clics en botones</h3>
                    <p class="text-[12px] text-slate-500 mt-0.5">Quick reply seleccionado</p>
                </div>
                <span class="text-[11px] font-medium text-slate-400 tabular-nums">{{ $kpis['clicaron'] }} clics</span>
            </div>
            <div class="p-2">
                @if(empty($botones))
                    <div class="flex items-center justify-center h-[280px]">
                        <div class="text-center">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-300 mb-2">
                                <i class="fa-solid fa-hand-pointer"></i>
                            </div>
                            <p class="text-[13px] text-slate-400">Sin clics todavía</p>
                            <p class="text-[11px] text-slate-300 mt-0.5">Aparecerán al usar plantilla con botones</p>
                        </div>
                    </div>
                @else
                    <div id="chart-botones" style="min-height: 280px;"></div>
                @endif
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────── CHARTS ROW 2 ───────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Timeline --}}
        <div class="lg:col-span-2 rounded-xl bg-white ring-1 ring-slate-200">
            <div class="px-5 pt-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Actividad por hora</h3>
                    <p class="text-[12px] text-slate-500 mt-0.5">Envíos · lecturas · respuestas</p>
                </div>
            </div>
            <div class="p-2">
                @if(empty($timeline['horas']))
                    <div class="flex items-center justify-center h-[280px]">
                        <div class="text-center">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-300 mb-2">
                                <i class="fa-regular fa-clock"></i>
                            </div>
                            <p class="text-[13px] text-slate-400">Sin actividad registrada</p>
                            <p class="text-[11px] text-slate-300 mt-0.5">Se llena al recibir webhooks de Meta</p>
                        </div>
                    </div>
                @else
                    <div id="chart-timeline" style="min-height: 280px;"></div>
                @endif
            </div>
        </div>

        {{-- Reacciones --}}
        <div class="rounded-xl bg-white ring-1 ring-slate-200">
            <div class="px-5 pt-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Reacciones</h3>
                    <p class="text-[12px] text-slate-500 mt-0.5">Emoji al mensaje de campaña</p>
                </div>
                <span class="text-[11px] font-medium text-slate-400 tabular-nums">{{ $kpis['reaccionaron'] }}</span>
            </div>
            <div class="p-5">
                @if(empty($reacciones))
                    <div class="flex items-center justify-center h-64 text-center">
                        <div>
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-300 mb-2">
                                <i class="fa-regular fa-face-meh"></i>
                            </div>
                            <p class="text-[13px] text-slate-400">Sin reacciones todavía</p>
                        </div>
                    </div>
                @else
                    <ul class="space-y-3">
                        @foreach($reacciones as $r)
                            @php $pct = $kpis['enviados'] > 0 ? round(($r['n'] / $kpis['enviados']) * 100, 1) : 0; @endphp
                            <li>
                                <div class="flex items-center justify-between text-[13px] mb-1.5">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="text-lg leading-none">{{ $r['reaccion'] }}</span>
                                        <span class="text-slate-600">{{ $r['n'] }} clientes</span>
                                    </span>
                                    <span class="font-medium text-slate-500 tabular-nums">{{ $pct }}%</span>
                                </div>
                                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-slate-700" style="width: {{ min(100, $pct * 5) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @endif {{-- sin_tracking --}}

    {{-- ───────────────────────────────────────── TABLA ──────────────────────────────────────── --}}
    <div class="rounded-xl bg-white ring-1 ring-slate-200 overflow-hidden">

        {{-- Header + filtros --}}
        <div class="px-5 pt-4 pb-3 border-b border-slate-100">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Destinatarios</h3>
                    <p class="text-[12px] text-slate-500 mt-0.5">Tracking individual por cliente</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400"></i>
                        <input wire:model.live.debounce.400ms="busqueda" type="text"
                               placeholder="Buscar nombre o teléfono"
                               class="rounded-md border-slate-200 text-[13px] pl-8 pr-3 py-1.5 w-64 focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                    </div>
                    {{-- 🤖 Analizar interesados con IA --}}
                    @if(($kpis['sin_analizar'] ?? 0) > 0)
                        <button wire:click="analizarInteresados" wire:loading.attr="disabled" wire:target="analizarInteresados"
                                class="shrink-0 rounded-md bg-violet-600 hover:bg-violet-700 text-white text-[12px] font-semibold px-3 py-1.5 inline-flex items-center gap-1.5 transition disabled:opacity-60">
                            <i class="fa-solid fa-wand-magic-sparkles" wire:loading.remove wire:target="analizarInteresados"></i>
                            <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="analizarInteresados"></i>
                            <span>Analizar interesados con IA ({{ $kpis['sin_analizar'] }})</span>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Resumen de interés (IA) --}}
            @if(($kpis['interesados'] ?? 0) + ($kpis['no_interesados'] ?? 0) + ($kpis['dudas'] ?? 0) > 0)
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-2.5 py-1 text-[12px] font-semibold">😊 Interesados: {{ $kpis['interesados'] }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 text-amber-700 ring-1 ring-amber-200 px-2.5 py-1 text-[12px] font-semibold">🤔 Dudas: {{ $kpis['dudas'] }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 text-slate-600 ring-1 ring-slate-200 px-2.5 py-1 text-[12px] font-semibold">🙅 No interesados: {{ $kpis['no_interesados'] }}</span>
                </div>
            @endif

            {{-- Filtros chips --}}
            @php
                $chips = [
                    'todos'         => ['Todos',           $kpis['total'],         'bg-slate-100 text-slate-700 hover:bg-slate-200'],
                    'leyeron'       => ['Leyeron',         $kpis['leidos'],        'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'],
                    'respondieron'  => ['Respondieron',    $kpis['respondieron'],  'bg-amber-50 text-amber-700 hover:bg-amber-100'],
                    'interesados'   => ['😊 Interesados',  $kpis['interesados'],   'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'],
                    'dudas'         => ['🤔 Dudas',        $kpis['dudas'],         'bg-amber-50 text-amber-700 hover:bg-amber-100'],
                    'no_interesados'=> ['🙅 No interesados', $kpis['no_interesados'], 'bg-slate-100 text-slate-600 hover:bg-slate-200'],
                    'clicaron'      => ['Clicaron',        $kpis['clicaron'],      'bg-violet-50 text-violet-700 hover:bg-violet-100'],
                    'reaccionaron' => ['Reaccionaron',    $kpis['reaccionaron'],  'bg-rose-50 text-rose-700 hover:bg-rose-100'],
                    'convirtieron' => ['Convirtieron',    $kpis['convirtieron'],  'bg-fuchsia-50 text-fuchsia-700 hover:bg-fuchsia-100'],
                    'fallaron'      => ['Fallaron',        $kpis['fallaron'],      'bg-red-50 text-red-700 hover:bg-red-100'],
                ];
            @endphp
            <div class="flex flex-wrap gap-1.5 mt-3">
                @foreach($chips as $k => [$label, $count, $baseClass])
                    <button wire:click="setFiltro('{{ $k }}')"
                            class="px-2.5 py-1 rounded-md text-[12px] font-medium transition inline-flex items-center gap-1.5
                            {{ $filtro === $k ? 'bg-slate-900 text-white' : $baseClass }}">
                        {{ $label }}
                        <span class="tabular-nums opacity-70">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Tabla --}}
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-slate-50/60 text-[10.5px] uppercase font-semibold text-slate-500 tracking-wider">
                        <th class="px-5 py-2.5 text-left">Cliente</th>
                        <th class="px-3 py-2.5 text-left">Teléfono</th>
                        <th class="px-3 py-2.5 text-center w-16" title="Enviado">Env.</th>
                        <th class="px-3 py-2.5 text-center w-16" title="Entregado">Entr.</th>
                        <th class="px-3 py-2.5 text-center w-16" title="Leído">Leído</th>
                        <th class="px-3 py-2.5 text-center w-20">Resp.</th>
                        <th class="px-3 py-2.5 text-left w-64">Interés (IA)</th>
                        <th class="px-3 py-2.5 text-left">Botón</th>
                        <th class="px-3 py-2.5 text-center w-14">React.</th>
                        <th class="px-3 py-2.5 text-center w-20">Pedido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($destinatarios as $d)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-5 py-2.5 text-slate-700 font-medium">{{ $d->nombre ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-slate-500 font-mono text-[12px] tabular-nums">{{ $d->telefono }}</td>
                            <td class="px-3 py-2.5 text-center">
                                @if($d->enviado_at)
                                    <i class="fa-solid fa-check text-emerald-500 text-[12px]" title="{{ $d->enviado_at }}"></i>
                                @elseif($d->estado === 'fallido')
                                    <i class="fa-solid fa-xmark text-red-500 text-[12px]" title="{{ $d->error_detalle }}"></i>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if($d->entregado_at)
                                    <span class="inline-flex text-slate-500 font-bold text-[11px] tracking-tighter" title="{{ $d->entregado_at }}">✓✓</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if($d->leido_at)
                                    <span class="inline-flex text-sky-500 font-bold text-[11px] tracking-tighter" title="{{ $d->leido_at }}">✓✓</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if($d->respondio_at)
                                    <span class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-700 ring-1 ring-amber-200/60 text-[11px] font-semibold tabular-nums"
                                          title="{{ $d->respondio_at }}">
                                        {{ $d->respuestas_count }}
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @php
                                    $iMap = [
                                        'interesado'    => ['😊 Interesado',   'bg-emerald-50 text-emerald-700 ring-emerald-200'],
                                        'duda'          => ['🤔 Duda',         'bg-amber-50 text-amber-700 ring-amber-200'],
                                        'no_interesado' => ['🙅 No interesado','bg-slate-100 text-slate-600 ring-slate-200'],
                                    ];
                                @endphp
                                @if($d->interes && isset($iMap[$d->interes]))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md ring-1 text-[11.5px] font-semibold {{ $iMap[$d->interes][1] }}"
                                          title="{{ $d->interes_motivo }}">{{ $iMap[$d->interes][0] }}</span>
                                    @if($d->respuesta_texto)
                                        <div class="text-[11px] text-slate-400 truncate max-w-[230px] mt-0.5" title="{{ $d->respuesta_texto }}">“{{ \Illuminate\Support\Str::limit($d->respuesta_texto, 50) }}”</div>
                                    @endif
                                @elseif($d->respondio_at)
                                    <span class="text-slate-300 text-[11px]">sin analizar</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if($d->boton_click)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-violet-50 text-violet-700 ring-1 ring-violet-200/60 text-[11.5px] font-medium">
                                        {{ $d->boton_click }}
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center text-base leading-none">
                                {{ $d->reaccion ?: '—' }}
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if($d->pedido_id)
                                    <a href="#" class="text-fuchsia-600 hover:text-fuchsia-700 text-[12px] font-semibold tabular-nums">
                                        #{{ $d->pedido_id }}
                                    </a>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-12 text-center">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-300 mb-2">
                                <i class="fa-regular fa-folder-open"></i>
                            </div>
                            <p class="text-[13px] text-slate-400">Sin destinatarios con este filtro</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-slate-100">
            {{ $destinatarios->links() }}
        </div>
    </div>

    {{-- ───────────────────────────────────────── APEXCHARTS ─────────────────────────────────── --}}
    @unless($kpis['sin_tracking'])
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            const k = @json($kpis);
            const botones = @json($botones);
            const timeline = @json($timeline);

            window._chartsInforme = window._chartsInforme || {};
            Object.values(window._chartsInforme).forEach(c => { try { c.destroy(); } catch(e){} });
            window._chartsInforme = {};

            // Paleta calibrada (tono medio para fills, oscuro para acentos)
            const C = {
                slate:   '#64748b',
                emerald: '#10b981',
                sky:     '#0ea5e9',
                indigo:  '#6366f1',
                amber:   '#f59e0b',
                fuchsia: '#d946ef',
                violet:  '#8b5cf6',
                rose:    '#f43f5e',
            };

            const baseFont = { fontFamily: 'inherit' };
            const baseChart = { ...baseFont, toolbar: { show: false }, animations: { enabled: true, easing: 'easeinout', speed: 600 } };

            // === FUNNEL HORIZONTAL ===
            const funnelCats   = ['Destinatarios', 'Enviados', 'Entregados', 'Leídos', 'Respondieron', 'Conversión'];
            const funnelVals   = [k.total, k.enviados, k.entregados, k.leidos, k.respondieron, k.convirtieron];
            const funnelColors = [C.slate, C.emerald, C.sky, C.indigo, C.amber, C.fuchsia];

            window._chartsInforme.funnel = new ApexCharts(document.querySelector("#chart-funnel"), {
                chart: { type: 'bar', height: 300, ...baseChart, offsetX: 0 },
                series: [{ name: 'Clientes', data: funnelVals }],
                xaxis: {
                    categories: funnelCats,
                    max: Math.max(funnelVals[0], 1) * 1.08, // espacio para labels externos
                    labels: { style: { fontSize: '11px', colors: '#94a3b8' } },
                    axisBorder: { show: false }, axisTicks: { show: false },
                },
                yaxis: {
                    labels: {
                        style: { colors: '#475569', fontSize: '12px', fontWeight: 500 },
                        offsetX: 0,
                        minWidth: 110,
                    },
                },
                colors: funnelColors,
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        borderRadiusApplication: 'end',
                        barHeight: '58%',
                        distributed: true,
                        dataLabels: { position: 'top' }, // si la barra es chica, label va afuera
                    }
                },
                dataLabels: {
                    enabled: true,
                    textAnchor: 'start',
                    formatter: function (val) {
                        const pct = funnelVals[0] > 0 ? Math.round((val / funnelVals[0]) * 100) : 0;
                        return val.toLocaleString() + ' · ' + pct + '%';
                    },
                    style: { fontSize: '11.5px', fontWeight: 600, colors: ['#334155'] },
                    offsetX: 6,
                },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { left: 0, right: 12, top: 0, bottom: 0 } },
                legend: { show: false },
                tooltip: { theme: 'light', y: { formatter: v => v.toLocaleString() + ' destinatarios' } },
                states: { hover: { filter: { type: 'darken', value: 0.92 } } },
                noData: { text: 'Sin datos', style: { color: '#cbd5e1', fontSize: '13px' } },
            });
            window._chartsInforme.funnel.render();

            // === DONUT BOTONES === (solo si hay container, tiene HTML empty state si no)
            const botonesEl = document.querySelector("#chart-botones");
            if (botonesEl && botones.length) {
            const donutColors = [C.violet, C.indigo, C.sky, C.fuchsia, C.amber, C.emerald];
            window._chartsInforme.botones = new ApexCharts(botonesEl, {
                chart: { type: 'donut', height: 280, ...baseChart },
                series: botones.length ? botones.map(b => b.n) : [1],
                labels: botones.length ? botones.map(b => b.boton_click) : ['Sin clics'],
                colors: botones.length ? donutColors : ['#e2e8f0'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                name: { fontSize: '11px', color: '#94a3b8', fontWeight: 500, offsetY: -4 },
                                value: { fontSize: '24px', fontWeight: 600, color: '#0f172a', offsetY: 4 },
                                total: {
                                    show: true,
                                    showAlways: true,
                                    label: 'Total clics',
                                    color: '#94a3b8',
                                    fontSize: '11px',
                                    fontWeight: 500,
                                    formatter: w => botones.length ? w.globals.seriesTotals.reduce((a,b)=>a+b,0).toLocaleString() : '0'
                                }
                            }
                        }
                    }
                },
                legend: { position: 'bottom', fontSize: '12px', labels: { colors: '#475569' }, markers: { radius: 999, size: 7 }, itemMargin: { horizontal: 8, vertical: 4 } },
                dataLabels: { enabled: false },
                stroke: { width: 2, colors: ['#ffffff'] },
                tooltip: { enabled: botones.length > 0, theme: 'light', y: { formatter: v => v + ' clientes' } },
                noData: { text: 'Sin clics todavía', style: { color: '#cbd5e1', fontSize: '13px' } },
            });
            window._chartsInforme.botones.render();
            }

            // === TIMELINE ÁREA === (solo si hay container)
            const timelineEl = document.querySelector("#chart-timeline");
            if (timelineEl && timeline.horas && timeline.horas.length) {
            window._chartsInforme.timeline = new ApexCharts(timelineEl, {
                chart: { type: 'area', height: 280, ...baseChart, zoom: { enabled: false } },
                series: [
                    { name: 'Enviados',     data: timeline.enviados },
                    { name: 'Leídos',       data: timeline.leidos },
                    { name: 'Respondieron', data: timeline.respondio },
                ],
                xaxis: {
                    categories: timeline.horas.map(h => h.substring(5, 16)),
                    labels: { style: { fontSize: '10.5px', colors: '#94a3b8' }, rotate: -30, hideOverlappingLabels: true },
                    axisBorder: { show: false }, axisTicks: { show: false },
                },
                yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
                colors: [C.emerald, C.indigo, C.amber],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0.6, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 95, 100] } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { left: 10, right: 10 } },
                markers: { size: 0, hover: { size: 4 } },
                legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', labels: { colors: '#475569' }, markers: { radius: 999, size: 7 } },
                tooltip: { theme: 'light', shared: true, y: { formatter: v => v + ' clientes' } },
                noData: { text: 'Sin actividad registrada', style: { color: '#cbd5e1', fontSize: '13px' } },
            });
            window._chartsInforme.timeline.render();
            }
        })();
    </script>
    @endunless
</div>
