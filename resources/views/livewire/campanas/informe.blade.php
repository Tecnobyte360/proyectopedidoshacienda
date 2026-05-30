<div class="p-4 md:p-6 space-y-5" wire:ignore.self>

    {{-- HEADER --}}
    <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
        <div class="flex items-center gap-4 flex-wrap">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-lg">
                <i class="fa-solid fa-chart-pie text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <a href="{{ route('campanas.index') }}" class="text-xs text-slate-500 hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Volver a campañas
                </a>
                <h1 class="text-2xl font-extrabold text-slate-800 truncate">{{ $campana->nombre }}</h1>
                <p class="text-sm text-slate-500">
                    <i class="fa-solid fa-calendar text-slate-400"></i>
                    {{ $campana->programada_para?->format('d M Y · H:i') ?? 'Sin programar' }}
                    @if($campana->plantilla_meta_nombre)
                        · <i class="fa-brands fa-meta text-blue-500"></i>
                        <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{{ $campana->plantilla_meta_nombre }}</code>
                    @endif
                </p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold uppercase tracking-wider
                {{ $campana->estado === 'completada' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                <span class="h-2 w-2 rounded-full bg-current animate-pulse"></span>
                {{ $campana->estado }}
            </span>
        </div>
    </div>

    {{-- KPIs SOFT PASTEL (estilo dashboard ventas) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">

        {{-- Destinatarios --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100/60 p-5 border border-slate-200/60 hover:shadow-lg transition group">
            <div class="absolute -right-3 -bottom-3 text-slate-200/40 group-hover:text-slate-200/60 transition">
                <i class="fa-solid fa-users text-7xl"></i>
            </div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-500/10 text-slate-600">
                        <i class="fa-solid fa-users text-sm"></i>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-slate-700 font-bold">Destinatarios</p>
                </div>
                <p class="text-3xl font-extrabold text-slate-900">{{ number_format($kpis['total']) }}</p>
                <p class="text-[11px] text-slate-700/70 mt-1">audiencia total</p>
            </div>
        </div>

        {{-- Enviados --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100/60 p-5 border border-emerald-200/60 hover:shadow-lg transition group">
            <div class="absolute -right-3 -bottom-3 text-emerald-200/40 group-hover:text-emerald-200/60 transition">
                <i class="fa-solid fa-paper-plane text-7xl"></i>
            </div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                        <i class="fa-solid fa-paper-plane text-sm"></i>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-emerald-700 font-bold">Enviados</p>
                </div>
                <p class="text-3xl font-extrabold text-emerald-900">{{ number_format($kpis['enviados']) }}</p>
                <p class="text-[11px] text-emerald-700/70 mt-1">salieron por Meta</p>
            </div>
        </div>

        {{-- Entregados --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sky-50 to-sky-100/60 p-5 border border-sky-200/60 hover:shadow-lg transition group">
            <div class="absolute -right-3 -bottom-3 text-sky-200/40 group-hover:text-sky-200/60 transition">
                <i class="fa-solid fa-check-double text-7xl"></i>
            </div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600">
                        <i class="fa-solid fa-check-double text-sm"></i>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-sky-700 font-bold">Entregados</p>
                </div>
                <p class="text-3xl font-extrabold text-sky-900">{{ number_format($kpis['entregados']) }}</p>
                <p class="text-[11px] text-sky-700/70 mt-1">{{ $kpis['pct_entregados'] }}% de enviados</p>
            </div>
        </div>

        {{-- Leídos --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100/60 p-5 border border-indigo-200/60 hover:shadow-lg transition group">
            <div class="absolute -right-3 -bottom-3 text-indigo-200/40 group-hover:text-indigo-200/60 transition">
                <i class="fa-solid fa-eye text-7xl"></i>
            </div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600">
                        <i class="fa-solid fa-eye text-sm"></i>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-indigo-700 font-bold">Leídos</p>
                </div>
                <p class="text-3xl font-extrabold text-indigo-900">{{ number_format($kpis['leidos']) }}</p>
                <p class="text-[11px] text-indigo-700/70 mt-1">{{ $kpis['pct_leidos'] }}% open rate</p>
            </div>
        </div>

        {{-- Respondieron --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-50 to-orange-100/60 p-5 border border-amber-200/60 hover:shadow-lg transition group">
            <div class="absolute -right-3 -bottom-3 text-amber-200/40 group-hover:text-amber-200/60 transition">
                <i class="fa-solid fa-comments text-7xl"></i>
            </div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600">
                        <i class="fa-solid fa-comments text-sm"></i>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-amber-700 font-bold">Respondieron</p>
                </div>
                <p class="text-3xl font-extrabold text-amber-900">{{ number_format($kpis['respondieron']) }}</p>
                <p class="text-[11px] text-amber-700/70 mt-1">{{ $kpis['pct_respondieron'] }}% engagement</p>
            </div>
        </div>

        {{-- Conversión --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-fuchsia-50 to-pink-100/60 p-5 border border-fuchsia-200/60 hover:shadow-lg transition group">
            <div class="absolute -right-3 -bottom-3 text-fuchsia-200/40 group-hover:text-fuchsia-200/60 transition">
                <i class="fa-solid fa-cart-shopping text-7xl"></i>
            </div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-fuchsia-500/10 text-fuchsia-600">
                        <i class="fa-solid fa-cart-shopping text-sm"></i>
                    </div>
                    <p class="text-[10px] uppercase tracking-wider text-fuchsia-700 font-bold">Conversión</p>
                </div>
                <p class="text-3xl font-extrabold text-fuchsia-900">{{ number_format($kpis['convirtieron']) }}</p>
                <p class="text-[11px] text-fuchsia-700/70 mt-1">{{ $kpis['pct_convirtieron'] }}% → pedido</p>
            </div>
        </div>
    </div>

    {{-- GRÁFICAS PRINCIPALES --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Funnel horizontal --}}
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-base font-bold text-slate-800">📊 Funnel de la campaña</h3>
                    <p class="text-xs text-slate-500">De los {{ $kpis['total'] }} destinatarios, cuántos llegaron a cada paso</p>
                </div>
            </div>
            <div id="chart-funnel" style="min-height: 280px;"></div>
        </div>

        {{-- Donut botones --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3">
                <h3 class="text-base font-bold text-slate-800">🔘 Clics en botones</h3>
                <p class="text-xs text-slate-500">{{ $kpis['clicaron'] }} clientes ({{ $kpis['pct_clicaron'] }}%)</p>
            </div>
            <div id="chart-botones" style="min-height: 280px;"></div>
        </div>
    </div>

    {{-- TIMELINE + REACCIONES --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Timeline horario --}}
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3">
                <h3 class="text-base font-bold text-slate-800">📈 Actividad por hora</h3>
                <p class="text-xs text-slate-500">Envíos, lecturas y respuestas a lo largo del tiempo</p>
            </div>
            <div id="chart-timeline" style="min-height: 280px;"></div>
        </div>

        {{-- Reacciones bar --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3">
                <h3 class="text-base font-bold text-slate-800">❤️ Reacciones</h3>
                <p class="text-xs text-slate-500">{{ $kpis['reaccionaron'] }} clientes reaccionaron</p>
            </div>
            @if(empty($reacciones))
                <div class="flex items-center justify-center h-64 text-slate-400 text-sm italic">
                    <div class="text-center">
                        <i class="fa-regular fa-face-smile text-4xl mb-2 text-slate-300"></i>
                        <p>Aún nadie ha reaccionado</p>
                    </div>
                </div>
            @else
                <div class="space-y-3 pt-2">
                    @foreach($reacciones as $r)
                        @php $pct = $kpis['enviados'] > 0 ? round(($r['n'] / $kpis['enviados']) * 100, 1) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-2xl">{{ $r['reaccion'] }}</span>
                                <span class="text-sm font-bold text-slate-700">{{ $r['n'] }} <span class="text-xs text-slate-400 font-normal">({{ $pct }}%)</span></span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-rose-400 to-rose-500" style="width: {{ min(100, $pct * 5) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- TABLA DETALLE --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex flex-col md:flex-row md:items-center gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800">📋 Detalle por destinatario</h3>
                <p class="text-xs text-slate-500">Cada cliente con todas sus interacciones</p>
            </div>
            <div class="md:ml-auto">
                <input wire:model.live.debounce.400ms="busqueda" type="text"
                       placeholder="Buscar por nombre o teléfono..."
                       class="rounded-xl border-slate-200 text-sm px-3 py-2 w-64 focus:border-brand focus:ring-2 focus:ring-brand/20">
            </div>
        </div>

        {{-- Filtros chips --}}
        <div class="px-4 pt-3 pb-2 border-b border-slate-100 flex flex-wrap gap-1.5">
            @php
                $chips = [
                    'todos'         => ['Todos',          $kpis['total'],         'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100',     'bg-slate-200/60'],
                    'leyeron'       => ['Leyeron',        $kpis['leidos'],        'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100', 'bg-indigo-200/60'],
                    'respondieron'  => ['Respondieron',   $kpis['respondieron'],  'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100',     'bg-amber-200/60'],
                    'clicaron'      => ['Clicaron botón', $kpis['clicaron'],      'bg-violet-50 text-violet-700 border-violet-200 hover:bg-violet-100', 'bg-violet-200/60'],
                    'reaccionaron' => ['Reaccionaron',   $kpis['reaccionaron'],  'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100',         'bg-rose-200/60'],
                    'convirtieron' => ['Convirtieron',   $kpis['convirtieron'],  'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200 hover:bg-fuchsia-100', 'bg-fuchsia-200/60'],
                    'fallaron'      => ['Fallaron',       $kpis['fallaron'],      'bg-red-50 text-red-700 border-red-200 hover:bg-red-100',             'bg-red-200/60'],
                ];
            @endphp
            @foreach($chips as $k => [$label, $count, $baseClass, $badgeClass])
                <button wire:click="setFiltro('{{ $k }}')"
                        class="px-3 py-1.5 rounded-full text-xs font-bold transition border
                        {{ $filtro === $k ? 'bg-slate-800 text-white border-slate-800' : $baseClass }}">
                    {{ $label }}
                    <span class="opacity-80 ml-0.5 inline-flex items-center justify-center rounded-full text-[10px] font-bold w-5 h-5
                        {{ $filtro === $k ? 'bg-white/20' : $badgeClass }}">{{ $count }}</span>
                </button>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Cliente</th>
                        <th class="px-4 py-3 text-left">Teléfono</th>
                        <th class="px-4 py-3 text-center">Enviado</th>
                        <th class="px-4 py-3 text-center">Entregado</th>
                        <th class="px-4 py-3 text-center">Leído</th>
                        <th class="px-4 py-3 text-center">Respondió</th>
                        <th class="px-4 py-3 text-left">Botón</th>
                        <th class="px-4 py-3 text-center">React.</th>
                        <th class="px-4 py-3 text-center">Pedido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($destinatarios as $d)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-2.5 font-medium text-slate-700">{{ $d->nombre ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-500 font-mono text-xs">{{ $d->telefono }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if($d->enviado_at)
                                    <span class="text-emerald-600 text-base" title="{{ $d->enviado_at }}">✓</span>
                                @elseif($d->estado === 'fallido')
                                    <span class="text-red-500 text-base" title="{{ $d->error_detalle }}">✗</span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if($d->entregado_at)
                                    <span class="text-slate-600 font-bold" title="{{ $d->entregado_at }}">✓✓</span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if($d->leido_at)
                                    <span class="text-blue-500 font-bold" title="{{ $d->leido_at }}">✓✓</span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if($d->respondio_at)
                                    <span class="inline-flex items-center gap-1 text-amber-700 font-semibold text-xs" title="{{ $d->respondio_at }}">
                                        💬 {{ $d->respuestas_count }}
                                    </span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($d->boton_click)
                                    <span class="inline-flex items-center gap-1 bg-violet-100 text-violet-700 rounded-full px-2.5 py-0.5 text-xs font-bold">
                                        <i class="fa-solid fa-hand-pointer text-[10px]"></i>
                                        {{ $d->boton_click }}
                                    </span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center text-xl">
                                {{ $d->reaccion ?: '·' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if($d->pedido_id)
                                    <a href="#" class="inline-flex items-center gap-1 text-fuchsia-600 hover:underline text-xs font-bold">
                                        <i class="fa-solid fa-cart-shopping"></i> #{{ $d->pedido_id }}
                                    </a>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-12 text-center text-slate-400">
                            <i class="fa-regular fa-folder-open text-3xl mb-2 block text-slate-300"></i>
                            <p class="italic">Sin destinatarios con este filtro</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-t border-slate-200">
            {{ $destinatarios->links() }}
        </div>
    </div>

    {{-- ApexCharts --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            const k = @json($kpis);
            const botones = @json($botones);
            const timeline = @json($timeline);

            // Destruir charts previos para evitar duplicados al re-render Livewire
            window._chartsInforme = window._chartsInforme || {};
            Object.values(window._chartsInforme).forEach(c => { try { c.destroy(); } catch(e){} });
            window._chartsInforme = {};

            const palette = ['#86efac', '#7dd3fc', '#c4b5fd', '#fcd34d', '#fda4af', '#67e8f9', '#fdba74', '#a5b4fc'];

            // === 1. FUNNEL HORIZONTAL ===
            const funnelCats = ['Destinatarios','Enviados','Entregados','Leídos','Respondieron','Conversión'];
            const funnelVals = [k.total, k.enviados, k.entregados, k.leidos, k.respondieron, k.convirtieron];
            const funnelColors = ['#94a3b8','#34d399','#0ea5e9','#6366f1','#f59e0b','#d946ef'];

            window._chartsInforme.funnel = new ApexCharts(document.querySelector("#chart-funnel"), {
                chart: { type: 'bar', height: 320, fontFamily: 'inherit', toolbar: { show: false } },
                series: [{ name: 'Clientes', data: funnelVals }],
                xaxis: { categories: funnelCats, labels: { style: { fontSize: '11px', colors: '#64748b' } }, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
                colors: funnelColors,
                plotOptions: { bar: { horizontal: true, borderRadius: 8, borderRadiusApplication: 'end', barHeight: '70%', distributed: true } },
                dataLabels: {
                    enabled: true,
                    formatter: function (val, opts) {
                        const pct = funnelVals[0] > 0 ? Math.round((val / funnelVals[0]) * 100) : 0;
                        return val + '  ·  ' + pct + '%';
                    },
                    style: { fontSize: '12px', fontWeight: 700, colors: ['#fff'] },
                    offsetX: 10,
                },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                legend: { show: false },
                tooltip: { y: { formatter: v => v + ' destinatarios' } },
                noData: { text: 'Sin datos', style: { color: '#cbd5e1', fontSize: '13px' } },
            });
            window._chartsInforme.funnel.render();

            // === 2. DONUT BOTONES ===
            window._chartsInforme.botones = new ApexCharts(document.querySelector("#chart-botones"), {
                chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
                series: botones.length ? botones.map(b => b.n) : [1],
                labels: botones.length ? botones.map(b => b.boton_click) : ['Sin clics aún'],
                colors: botones.length ? palette : ['#e2e8f0'],
                plotOptions: { pie: { donut: { size: '70%', labels: { show: true, name: { fontSize: '12px', color: '#94a3b8' }, value: { fontSize: '24px', fontWeight: 700, color: '#334155' }, total: { show: true, label: 'Total clics', color: '#94a3b8', fontSize: '11px', formatter: w => botones.length ? w.globals.seriesTotals.reduce((a,b)=>a+b,0) : 0 } } } } },
                legend: { position: 'bottom', fontSize: '12px', labels: { colors: '#64748b' }, markers: { radius: 6 } },
                dataLabels: { enabled: botones.length > 0, style: { fontSize: '13px', fontWeight: 700, colors: ['#fff'] }, dropShadow: { enabled: false }, formatter: (v, opts) => opts.w.globals.series[opts.seriesIndex] },
                stroke: { width: 3, colors: ['#ffffff'] },
                tooltip: { enabled: botones.length > 0, y: { formatter: v => v + ' clientes' } },
                noData: { text: 'Sin clics todavía', style: { color: '#cbd5e1', fontSize: '13px' } },
            });
            window._chartsInforme.botones.render();

            // === 3. TIMELINE HORARIO ===
            window._chartsInforme.timeline = new ApexCharts(document.querySelector("#chart-timeline"), {
                chart: { type: 'area', height: 320, fontFamily: 'inherit', toolbar: { show: false } },
                series: [
                    { name: 'Enviados', data: timeline.enviados },
                    { name: 'Leídos', data: timeline.leidos },
                    { name: 'Respondieron', data: timeline.respondio },
                ],
                xaxis: { categories: timeline.horas.map(h => h.substring(5, 16).replace('-', '/').replace(' ', ' · ')), labels: { style: { fontSize: '10px', colors: '#64748b' }, rotate: -35 }, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
                colors: ['#34d399', '#6366f1', '#f59e0b'],
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0.7, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { left: 10, right: 10 } },
                markers: { size: 0, hover: { size: 5 } },
                legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', labels: { colors: '#64748b' }, markers: { radius: 6 } },
                tooltip: { x: { show: true }, y: { formatter: v => v + ' clientes' } },
                noData: { text: 'Sin actividad registrada', style: { color: '#cbd5e1', fontSize: '13px' } },
            });
            window._chartsInforme.timeline.render();
        })();
    </script>
</div>
