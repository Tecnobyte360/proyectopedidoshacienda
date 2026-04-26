<div class="px-6 lg:px-10 py-8" wire:poll.60s="actualizar">

    {{-- HEADER + FILTROS --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Reportes</h2>
            <p class="text-sm text-slate-500">Métricas de ventas y operaciones.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="sedeId"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-brand">
                <option value="">Todas las sedes</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                @endforeach
            </select>

            <div class="inline-flex items-center rounded-xl bg-white shadow p-1">
                @foreach(['hoy' => 'Hoy', 'semana' => '7 días', 'mes' => '30 días', 'trimestre' => '90 días'] as $key => $label)
                    <button wire:click="$set('rango', '{{ $key }}')"
                            class="px-4 py-2 text-xs font-semibold rounded-lg transition
                                  {{ $rango === $key ? 'bg-brand text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @php $kpis = $this->kpis; @endphp

    {{-- KPIS principales --}}
    <div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="rounded-2xl bg-gradient-to-br from-brand to-brand-secondary p-5 text-white shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Ingresos</span>
                <i class="fa-solid fa-dollar-sign text-2xl opacity-60"></i>
            </div>
            <div class="text-3xl font-extrabold">${{ number_format($kpis['ingresos'], 0, ',', '.') }}</div>
            <div class="text-xs opacity-80 mt-1">Ticket promedio: ${{ number_format($kpis['ticket'], 0, ',', '.') }}</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pedidos</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $kpis['total'] }}</div>
            <div class="text-xs text-slate-500 mt-1">en este periodo</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Entregados</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-green-50 text-green-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $kpis['entregados'] }}</div>
            <div class="text-xs text-green-600 mt-1 font-semibold">
                <i class="fa-solid fa-arrow-up"></i> {{ $kpis['tasa_entrega'] }}% tasa de entrega
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cancelados</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $kpis['cancelados'] }}</div>
            <div class="text-xs text-slate-500 mt-1">en este periodo</div>
        </div>
    </div>

    {{-- GRID PRINCIPAL --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- LINE CHART: Ventas por día --}}
        <div class="lg:col-span-2 rounded-2xl bg-white p-6 shadow">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-slate-800">Evolución de ventas</h3>
                    <p class="text-xs text-slate-500">Ingresos por día (sin cancelados)</p>
                </div>
                <span class="text-xs text-slate-400">
                    <i class="fa-solid fa-chart-line mr-1"></i>
                    @switch($rango)
                        @case('hoy')       Hoy @break
                        @case('semana')    Últimos 7 días @break
                        @case('mes')       Últimos 30 días @break
                        @case('trimestre') Últimos 90 días @break
                        @default           Últimos {{ count($this->ventasPorDia) }} días
                    @endswitch
                </span>
            </div>

            <div wire:ignore>
                <canvas id="chartVentas" height="100"></canvas>
            </div>
        </div>

        {{-- DONUT: Pedidos por estado --}}
        <div class="rounded-2xl bg-white p-6 shadow">
            <h3 class="font-bold text-slate-800 mb-1">Pedidos por estado</h3>
            <p class="text-xs text-slate-500 mb-4">Distribución actual</p>

            <div wire:ignore class="relative">
                <canvas id="chartEstados" height="180"></canvas>
            </div>

            <div class="mt-4 space-y-2">
                @foreach($this->porEstado as $est)
                    @if($est['total'] > 0)
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $est['color'] }}"></span>
                                <span class="text-slate-700">{{ $est['estado'] }}</span>
                            </div>
                            <span class="font-semibold text-slate-800">{{ $est['total'] }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- SEGUNDA FILA --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- TOP PRODUCTOS --}}
        <div class="rounded-2xl bg-white p-6 shadow">
            <h3 class="font-bold text-slate-800 mb-1">Top productos</h3>
            <p class="text-xs text-slate-500 mb-4">Más vendidos</p>

            <div class="space-y-3">
                @php $maxTotal = collect($this->topProductos)->max('total') ?: 1; @endphp

                @forelse($this->topProductos as $i => $prod)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="flex h-5 w-5 items-center justify-center rounded-md bg-brand-soft text-[10px] font-bold text-brand-secondary">
                                    {{ $i + 1 }}
                                </span>
                                <span class="text-sm font-medium text-slate-700 truncate">{{ $prod['producto'] }}</span>
                            </div>
                            <span class="text-xs font-bold text-slate-800 ml-2">${{ number_format($prod['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand to-brand-secondary"
                                 style="width: {{ ($prod['total'] / $maxTotal) * 100 }}%"></div>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-0.5">{{ rtrim(rtrim(number_format($prod['cantidad'], 2, ',', '.'), '0'), ',') }} unidades</div>
                    </div>
                @empty
                    <div class="text-center text-sm text-slate-400 py-6">
                        <i class="fa-solid fa-inbox text-2xl mb-2 block"></i>
                        Sin datos en este periodo
                    </div>
                @endforelse
            </div>
        </div>

        {{-- TOP DOMICILIARIOS --}}
        <div class="rounded-2xl bg-white p-6 shadow">
            <h3 class="font-bold text-slate-800 mb-1">Top domiciliarios</h3>
            <p class="text-xs text-slate-500 mb-4">Por entregas completadas</p>

            <div class="space-y-3">
                @forelse($this->topDomiciliarios as $i => $dom)
                    <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white font-bold">
                            {{ strtoupper(substr($dom['nombre'], 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-slate-800 text-sm truncate">{{ $dom['nombre'] }}</div>
                            <div class="text-xs text-slate-500 capitalize">{{ $dom['vehiculo'] ?? 'Sin vehículo' }} · {{ $dom['estado'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-extrabold text-slate-800">{{ $dom['entregas'] }}</div>
                            <div class="text-[10px] text-slate-400 uppercase font-semibold">entregas</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-sm text-slate-400 py-6">
                        <i class="fa-solid fa-motorcycle text-2xl mb-2 block"></i>
                        Sin entregas en este periodo
                    </div>
                @endforelse
            </div>
        </div>

        {{-- VENTAS POR SEDE --}}
        <div class="rounded-2xl bg-white p-6 shadow">
            <h3 class="font-bold text-slate-800 mb-1">Ventas por sede</h3>
            <p class="text-xs text-slate-500 mb-4">Desempeño comparado</p>

            <div class="space-y-3">
                @php $maxVentas = collect($this->ventasPorSede)->max('ventas') ?: 1; @endphp

                @forelse($this->ventasPorSede as $sede)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-store text-xs text-brand"></i>
                                <span class="text-sm font-medium text-slate-700">{{ $sede['sede'] }}</span>
                            </div>
                            <span class="text-xs font-bold text-slate-800">${{ number_format($sede['ventas'], 0, ',', '.') }}</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-400 to-blue-600"
                                 style="width: {{ ($sede['ventas'] / $maxVentas) * 100 }}%"></div>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-0.5">{{ $sede['pedidos'] }} pedido(s)</div>
                    </div>
                @empty
                    <div class="text-center text-sm text-slate-400 py-6">
                        Sin sedes registradas
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══════════════ SATISFACCIÓN DEL CLIENTE ═══════════════ --}}
    @php $sat = $this->satisfaccionKpis; @endphp

    <div class="mb-4 mt-10 flex items-end justify-between">
        <div>
            <h3 class="text-2xl font-extrabold text-slate-800">Satisfacción del cliente</h3>
            <p class="text-sm text-slate-500">Resultado de las encuestas post-entrega.</p>
        </div>
        <div class="text-xs text-slate-400">
            <i class="fa-solid fa-star text-amber-400 mr-1"></i>
            {{ $sat['completadas'] }} de {{ $sat['enviadas'] }} respondidas
        </div>
    </div>

    {{-- KPIs SATISFACCIÓN --}}
    <div class="mb-6 grid grid-cols-2 lg:grid-cols-5 gap-4">

        <div class="rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 p-5 text-white shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Calificación proceso</span>
                <i class="fa-solid fa-star text-2xl opacity-60"></i>
            </div>
            <div class="text-3xl font-extrabold">
                {{ $sat['prom_proceso'] > 0 ? number_format($sat['prom_proceso'], 1) : '—' }}
                <span class="text-sm font-medium opacity-80">/ 5</span>
            </div>
            <div class="text-xs opacity-80 mt-1">Experiencia general</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Domiciliario</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">
                {{ $sat['prom_domicilio'] > 0 ? number_format($sat['prom_domicilio'], 1) : '—' }}
                <span class="text-sm font-medium text-slate-400">/ 5</span>
            </div>
            <div class="text-xs text-slate-500 mt-1">Promedio repartidores</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recomendaría</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-thumbs-up"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $sat['pct_recomienda'] }}%</div>
            <div class="text-xs text-emerald-600 mt-1 font-semibold">Volverían a pedir</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tasa respuesta</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <i class="fa-solid fa-reply"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $sat['tasa_respuesta'] }}%</div>
            <div class="text-xs text-slate-500 mt-1">{{ $sat['tasa_apertura'] }}% abrió el link</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">NPS</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl
                            {{ $sat['nps'] >= 50 ? 'bg-emerald-50 text-emerald-600' : ($sat['nps'] >= 0 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600') }}">
                    <i class="fa-solid fa-chart-simple"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold {{ $sat['nps'] >= 50 ? 'text-emerald-600' : ($sat['nps'] >= 0 ? 'text-amber-600' : 'text-red-600') }}">
                {{ $sat['nps'] > 0 ? '+' : '' }}{{ $sat['nps'] }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Promotores − Detractores</div>
        </div>
    </div>

    {{-- DISTRIBUCIÓN ESTRELLAS + RANKING DOMICILIARIOS + COMENTARIOS --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Distribución de calificaciones --}}
        <div class="rounded-2xl bg-white p-6 shadow">
            <h3 class="font-bold text-slate-800 mb-1">Distribución de calificaciones</h3>
            <p class="text-xs text-slate-500 mb-4">Cuántos clientes dan cada estrella</p>

            @php
                $dist = $this->distribucionCalificaciones;
                $maxVal = collect($dist)->flatMap(fn($d) => [$d['proceso'], $d['domiciliario']])->max() ?: 1;
                $totalRespuestas = collect($dist)->sum(fn($d) => $d['proceso']);
            @endphp

            @if($totalRespuestas === 0)
                <div class="text-center text-sm text-slate-400 py-10">
                    <i class="fa-solid fa-comment-slash text-2xl mb-2 opacity-50 block"></i>
                    Aún no hay respuestas en este periodo
                </div>
            @else
                <div class="space-y-3">
                    @foreach($dist as $row)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <div class="flex items-center gap-1 text-amber-500">
                                    @for($i = 0; $i < $row['estrellas']; $i++)
                                        <i class="fa-solid fa-star"></i>
                                    @endfor
                                </div>
                                <span class="text-slate-500">{{ $row['proceso'] }} proceso · {{ $row['domiciliario'] }} dom.</span>
                            </div>
                            <div class="flex gap-1 h-3">
                                <div class="flex-1 rounded-full bg-amber-100 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-amber-400 to-orange-500"
                                         style="width: {{ ($row['proceso'] / $maxVal) * 100 }}%"></div>
                                </div>
                                <div class="flex-1 rounded-full bg-violet-100 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-violet-400 to-violet-600"
                                         style="width: {{ ($row['domiciliario'] / $maxVal) * 100 }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex items-center gap-4 text-[10px] text-slate-500">
                    <span><span class="inline-block h-2 w-2 rounded-full bg-amber-500 mr-1"></span>Proceso</span>
                    <span><span class="inline-block h-2 w-2 rounded-full bg-violet-500 mr-1"></span>Domiciliario</span>
                </div>
            @endif
        </div>

        {{-- Ranking domiciliarios por satisfacción --}}
        <div class="rounded-2xl bg-white p-6 shadow">
            <h3 class="font-bold text-slate-800 mb-1">Top domiciliarios</h3>
            <p class="text-xs text-slate-500 mb-4">Mejor calificados por los clientes</p>

            <div class="space-y-3">
                @forelse($this->rankingDomiciliarios as $i => $d)
                    <div class="flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-slate-50">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-50 text-violet-600 font-bold text-xs flex-shrink-0">
                                #{{ $i + 1 }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-700 truncate">{{ $d['nombre'] }}</div>
                                <div class="text-[10px] text-slate-400">{{ $d['encuestas'] }} respuesta(s)</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-amber-500 flex-shrink-0">
                            <span class="text-sm font-bold">{{ number_format($d['promedio'], 1) }}</span>
                            <i class="fa-solid fa-star text-xs"></i>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-sm text-slate-400 py-6">
                        <i class="fa-solid fa-medal text-2xl mb-2 opacity-50 block"></i>
                        Sin calificaciones aún
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Comentarios recientes --}}
        <div class="rounded-2xl bg-white p-6 shadow">
            <h3 class="font-bold text-slate-800 mb-1">Comentarios recientes</h3>
            <p class="text-xs text-slate-500 mb-4">Lo que dicen tus clientes</p>

            <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                @forelse($this->comentariosRecientes as $c)
                    <div class="border-l-2 border-amber-300 pl-3 py-1">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-slate-700 truncate">{{ $c['cliente'] }}</span>
                            <span class="text-[10px] text-slate-400">{{ $c['fecha'] }}</span>
                        </div>
                        @if($c['com_proceso'])
                            <div class="flex items-start gap-1 text-xs text-slate-600 mb-1">
                                <span class="text-amber-500 flex-shrink-0">
                                    @for($i = 0; $i < (int)($c['cal_proceso'] ?? 0); $i++)<i class="fa-solid fa-star text-[8px]"></i>@endfor
                                </span>
                                <span class="italic">"{{ $c['com_proceso'] }}"</span>
                            </div>
                        @endif
                        @if($c['com_dom'])
                            <div class="flex items-start gap-1 text-xs text-slate-500">
                                <i class="fa-solid fa-motorcycle text-violet-400 text-[10px] flex-shrink-0 mt-0.5"></i>
                                <span class="italic">{{ $c['domiciliario'] }}: "{{ $c['com_dom'] }}"</span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-sm text-slate-400 py-6">
                        <i class="fa-regular fa-comments text-2xl mb-2 opacity-50 block"></i>
                        Sin comentarios todavía
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- CHART.JS --}}
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endpush

    <script>
        (function() {
            let chartVentas, chartEstados;

            function renderCharts() {
                const ventasData = @json($this->ventasPorDia);
                const estadosData = @json(collect($this->porEstado)->filter(fn($e) => $e['total'] > 0)->values());

                // Destroy previous instances
                if (chartVentas) chartVentas.destroy();
                if (chartEstados) chartEstados.destroy();

                // VENTAS LINE
                const ctxVentas = document.getElementById('chartVentas');
                if (ctxVentas && window.Chart) {
                    chartVentas = new Chart(ctxVentas, {
                        type: 'line',
                        data: {
                            labels: ventasData.map(d => d.dia),
                            datasets: [{
                                label: 'Ventas ($)',
                                data: ventasData.map(d => d.ventas),
                                borderColor: '#d68643',
                                backgroundColor: 'rgba(214, 134, 67, 0.1)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 3,
                                pointBackgroundColor: '#d68643',
                                pointHoverRadius: 6,
                                borderWidth: 3,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => '$' + new Intl.NumberFormat('es-CO').format(ctx.parsed.y),
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: v => '$' + new Intl.NumberFormat('es-CO', {
                                            notation: 'compact'
                                        }).format(v),
                                    }
                                },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }

                // ESTADOS DONUT
                const ctxEstados = document.getElementById('chartEstados');
                if (ctxEstados && window.Chart && estadosData.length) {
                    chartEstados = new Chart(ctxEstados, {
                        type: 'doughnut',
                        data: {
                            labels: estadosData.map(e => e.estado),
                            datasets: [{
                                data: estadosData.map(e => e.total),
                                backgroundColor: estadosData.map(e => e.color),
                                borderWidth: 0,
                                hoverOffset: 8,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: { display: false },
                            }
                        }
                    });
                }
            }

            function init() {
                if (window.Chart) {
                    renderCharts();
                } else {
                    setTimeout(init, 200);
                }
            }

            init();

            document.addEventListener('livewire:initialized', () => {
                Livewire.hook('morph.updated', () => {
                    setTimeout(renderCharts, 50);
                });
            });
        })();
    </script>
</div>
