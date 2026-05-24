<div class="p-4 md:p-6 space-y-5" wire:ignore.self>
    @php $k = $this->kpis; @endphp

    {{-- HEADER --}}
    <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
        <div class="flex items-center gap-4 flex-wrap">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-lg">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-extrabold text-slate-800">Dashboard de Ventas</h1>
                <p class="text-sm text-slate-500"><i class="fa-solid fa-circle-info text-slate-400"></i> Análisis completo de ingresos, clientes y conversión</p>
            </div>
            <select wire:model.live="rango"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                <option value="30d">Últimos 30 días</option>
                <option value="90d">Últimos 90 días</option>
                <option value="12m">Últimos 12 meses</option>
                <option value="ytd">Año en curso</option>
            </select>
        </div>
    </div>

    {{-- KPIs HERMOSOS --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

        {{-- Ingresos del rango --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700 p-5 text-white shadow-xl">
            <div class="absolute -right-4 -bottom-4 opacity-10">
                <i class="fa-solid fa-sack-dollar text-9xl"></i>
            </div>
            <div class="relative">
                <p class="text-[10px] uppercase tracking-wider opacity-80 font-bold">Ingresos del rango</p>
                <p class="text-3xl font-extrabold mt-1">${{ number_format($k['ingresosRango'], 0, ',', '.') }}</p>
                <p class="text-[11px] opacity-80 mt-1">COP · {{ $k['pagosRangoCount'] }} pago{{ $k['pagosRangoCount'] === 1 ? '' : 's' }}</p>
            </div>
        </div>

        {{-- MRR --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sky-500 via-blue-600 to-indigo-700 p-5 text-white shadow-xl">
            <div class="absolute -right-4 -bottom-4 opacity-10">
                <i class="fa-solid fa-arrows-rotate text-9xl"></i>
            </div>
            <div class="relative">
                <p class="text-[10px] uppercase tracking-wider opacity-80 font-bold">MRR (recurrente)</p>
                <p class="text-3xl font-extrabold mt-1">${{ number_format($k['mrr'], 0, ',', '.') }}</p>
                <p class="text-[11px] opacity-80 mt-1">COP / mes</p>
            </div>
        </div>

        {{-- Ticket promedio --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-500 via-purple-600 to-fuchsia-700 p-5 text-white shadow-xl">
            <div class="absolute -right-4 -bottom-4 opacity-10">
                <i class="fa-solid fa-receipt text-9xl"></i>
            </div>
            <div class="relative">
                <p class="text-[10px] uppercase tracking-wider opacity-80 font-bold">Ticket promedio</p>
                <p class="text-3xl font-extrabold mt-1">${{ number_format($k['ticketPromedio'], 0, ',', '.') }}</p>
                <p class="text-[11px] opacity-80 mt-1">COP por pago</p>
            </div>
        </div>

        {{-- Tenants --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-5 text-white shadow-xl">
            <div class="absolute -right-4 -bottom-4 opacity-10">
                <i class="fa-solid fa-users text-9xl"></i>
            </div>
            <div class="relative">
                <p class="text-[10px] uppercase tracking-wider opacity-80 font-bold">Tenants activos</p>
                <p class="text-3xl font-extrabold mt-1">{{ $k['tenantsActivos'] }}</p>
                <p class="text-[11px] opacity-80 mt-1">
                    @if($k['tenantsNuevos'] > 0)
                        <i class="fa-solid fa-arrow-up"></i> +{{ $k['tenantsNuevos'] }} nuevos en el rango
                    @else
                        Sin nuevos en el rango
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Mes vs Mes anterior --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <i class="fa-solid fa-calendar-check text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-bold uppercase">Mes en curso vs mes pasado</p>
                    <p class="text-2xl font-extrabold text-slate-800">
                        ${{ number_format($k['ingresosMes'], 0, ',', '.') }}
                        <span class="text-slate-400 font-normal text-sm">vs ${{ number_format($k['ingresosMesP'], 0, ',', '.') }}</span>
                    </p>
                </div>
            </div>
            @if($k['deltaPct'] !== null)
                <div class="flex items-center gap-2 px-4 py-2 rounded-xl {{ $k['deltaPct'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                    <i class="fa-solid fa-arrow-{{ $k['deltaPct'] >= 0 ? 'up' : 'down' }} text-lg"></i>
                    <span class="text-xl font-extrabold">{{ abs($k['deltaPct']) }}%</span>
                </div>
            @endif
        </div>
    </div>

    {{-- GRÁFICA PRINCIPAL: ingresos diarios --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <i class="fa-solid fa-chart-area text-emerald-500 text-lg"></i>
            <h3 class="text-base font-bold text-slate-800">Ingresos diarios</h3>
            <span class="text-xs text-slate-400 ml-auto">{{ $k['desde']->format('d/m/Y') }} → {{ $k['hasta']->format('d/m/Y') }}</span>
        </div>
        <div wire:ignore id="chart-ingresos" style="min-height: 320px;"></div>
    </div>

    {{-- ROW 2: Por plan + Por método --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-layer-group text-violet-500 text-lg"></i>
                <h3 class="text-base font-bold text-slate-800">Suscripciones por plan</h3>
            </div>
            <div wire:ignore id="chart-planes" style="min-height: 280px;"></div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-credit-card text-sky-500 text-lg"></i>
                <h3 class="text-base font-bold text-slate-800">Ingresos por método de pago</h3>
            </div>
            <div wire:ignore id="chart-metodos" style="min-height: 280px;"></div>
        </div>
    </div>

    {{-- 2 cards: Próximos vencimientos + Alertas operativas --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Próximos vencimientos --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="flex items-center gap-2 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-white">
                <i class="fa-solid fa-clock text-amber-500 text-lg"></i>
                <h3 class="text-base font-bold text-slate-800">Próximos vencimientos (15 días)</h3>
            </div>
            @if($k['proximosVenc']->isEmpty())
                <div class="p-8 text-center text-slate-400">
                    <i class="fa-solid fa-circle-check text-emerald-400 text-3xl mb-2"></i>
                    <p class="text-sm">Ninguna suscripción vence pronto.</p>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Tenant</th>
                            <th class="px-4 py-2.5 text-left">Plan</th>
                            <th class="px-4 py-2.5 text-center">Vence</th>
                            <th class="px-4 py-2.5 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($k['proximosVenc'] as $sus)
                            @php
                                $dias = (int) now()->startOfDay()->diffInDays($sus->fecha_fin->startOfDay(), false);
                                $color = $dias <= 3 ? 'text-rose-600 bg-rose-50' : ($dias <= 7 ? 'text-amber-600 bg-amber-50' : 'text-emerald-700 bg-emerald-50');
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-building text-violet-500 text-xs"></i>
                                        <span class="font-bold text-slate-800">{{ $sus->tenant?->nombre ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 text-xs">{{ $sus->plan?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $color }}">
                                        {{ $sus->fecha_fin->format('d/m') }} ({{ $dias }}d)
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-bold text-slate-700">${{ number_format($sus->monto, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Alertas operativas --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 text-lg"></i>
                <h3 class="text-base font-bold text-slate-800">Alertas operativas</h3>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-hourglass-half text-amber-600 text-lg"></i>
                        <div>
                            <p class="text-[10px] uppercase text-amber-700 font-bold">Por cobrar</p>
                            <p class="text-xl font-extrabold text-amber-800">${{ number_format($k['pendientes'], 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-rose-50 border border-rose-200 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-fire text-rose-600 text-lg"></i>
                        <div>
                            <p class="text-[10px] uppercase text-rose-700 font-bold">Morosos</p>
                            <p class="text-xl font-extrabold text-rose-800">{{ $k['morosos'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between rounded-xl bg-slate-100 border border-slate-200 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-lock text-slate-600 text-lg"></i>
                        <div>
                            <p class="text-[10px] uppercase text-slate-600 font-bold">Suspendidos</p>
                            <p class="text-xl font-extrabold text-slate-700">{{ $k['suspendidos'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TOP CLIENTES --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-white">
            <i class="fa-solid fa-trophy text-amber-500 text-lg"></i>
            <h3 class="text-base font-bold text-slate-800">Top 10 clientes que más han pagado</h3>
        </div>
        @if($k['topTenants']->isEmpty())
            <div class="p-10 text-center text-slate-400">
                <i class="fa-solid fa-medal text-4xl text-slate-300 mb-2"></i>
                <p class="text-sm">Sin pagos confirmados en el rango.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-center w-14">#</th>
                        <th class="px-4 py-3 text-left">Empresa</th>
                        <th class="px-4 py-3 text-center w-32">Pagos hechos</th>
                        <th class="px-4 py-3 text-right w-40">Total facturado</th>
                        <th class="px-4 py-3 w-40">Distribución</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $maxTotal = $k['topTenants']->max('total') ?: 1; @endphp
                    @foreach($k['topTenants'] as $idx => $row)
                        @php
                            $pct = ($row->total / $maxTotal) * 100;
                            $medalla = match($idx) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '' };
                        @endphp
                        <tr class="hover:bg-amber-50/30 transition">
                            <td class="px-4 py-3 text-center">
                                @if($medalla)
                                    <span class="text-xl">{{ $medalla }}</span>
                                @else
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-600 font-bold text-xs">{{ $idx + 1 }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600">
                                        <i class="fa-solid fa-building"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800">{{ $row->tenant?->nombre ?? '—' }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $row->tenant?->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-2.5 py-1 text-xs font-bold">
                                    <i class="fa-solid fa-check"></i> {{ $row->pagos }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="text-lg font-extrabold text-emerald-700">${{ number_format($row->total, 0, ',', '.') }}</div>
                                <div class="text-[10px] text-slate-400">COP</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-amber-400 to-amber-600 rounded-full transition-all"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ApexCharts --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            const opts = {
                serieDiaria: @json($k['serieDiaria']),
                porPlan: @json($k['porPlan']->map(fn($p) => ['nombre' => $p->nombre, 'count' => $p->activas_count, 'precio' => (float)$p->precio_mensual])),
                porMetodo: @json($k['porMetodo']->map(fn($m) => ['metodo' => ucfirst($m->metodo), 'total' => (float)$m->total, 'cnt' => (int)$m->cnt])),
            };

            // Destruir charts previos para evitar duplicados al re-render Livewire
            window._chartsVentas = window._chartsVentas || {};
            Object.values(window._chartsVentas).forEach(c => { try { c.destroy(); } catch(e){} });
            window._chartsVentas = {};

            // === 1. Ingresos diarios (área) ===
            const fechas = Object.keys(opts.serieDiaria);
            const valores = Object.values(opts.serieDiaria).map(v => parseFloat(v));
            window._chartsVentas.ingresos = new ApexCharts(document.querySelector("#chart-ingresos"), {
                chart: { type: 'area', height: 320, fontFamily: 'inherit', toolbar: { show: false } },
                series: [{ name: 'Ingresos', data: valores }],
                xaxis: { categories: fechas, labels: { style: { fontSize: '11px' } } },
                yaxis: { labels: { formatter: v => '$' + Math.round(v).toLocaleString('es-CO') } },
                colors: ['#10b981'],
                stroke: { curve: 'smooth', width: 3 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.05, stops: [0, 90, 100] } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                tooltip: { y: { formatter: v => '$' + Math.round(v).toLocaleString('es-CO') + ' COP' } },
                noData: { text: 'Sin datos en este rango', style: { color: '#94a3b8' } },
            });
            window._chartsVentas.ingresos.render();

            // === 2. Por plan (donut) ===
            window._chartsVentas.planes = new ApexCharts(document.querySelector("#chart-planes"), {
                chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
                series: opts.porPlan.map(p => p.count),
                labels: opts.porPlan.map(p => p.nombre),
                colors: ['#d68643', '#a85f24', '#8b5cf6', '#06b6d4', '#f59e0b'],
                plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Activas', formatter: w => w.globals.seriesTotals.reduce((a,b)=>a+b,0) } } } } },
                legend: { position: 'bottom', fontSize: '12px' },
                dataLabels: { enabled: true, formatter: (v, opts) => opts.w.globals.series[opts.seriesIndex] },
                tooltip: { y: { formatter: v => v + ' suscripciones' } },
                noData: { text: 'Sin planes activos', style: { color: '#94a3b8' } },
            });
            window._chartsVentas.planes.render();

            // === 3. Por método de pago (barras) ===
            window._chartsVentas.metodos = new ApexCharts(document.querySelector("#chart-metodos"), {
                chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false } },
                series: [{ name: 'Total', data: opts.porMetodo.map(m => m.total) }],
                xaxis: { categories: opts.porMetodo.map(m => m.metodo), labels: { style: { fontSize: '11px' } } },
                yaxis: { labels: { formatter: v => '$' + Math.round(v).toLocaleString('es-CO') } },
                colors: ['#0ea5e9'],
                plotOptions: { bar: { borderRadius: 8, columnWidth: '55%', distributed: true } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                legend: { show: false },
                tooltip: { y: { formatter: v => '$' + Math.round(v).toLocaleString('es-CO') + ' COP' } },
                noData: { text: 'Sin métodos registrados', style: { color: '#94a3b8' } },
            });
            window._chartsVentas.metodos.render();
        })();
    </script>
</div>
