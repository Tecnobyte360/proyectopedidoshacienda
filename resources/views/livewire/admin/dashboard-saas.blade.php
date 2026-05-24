<div class="p-4 md:p-6 max-w-7xl mx-auto space-y-5">

    {{-- HEADER --}}
    <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-lg">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-800">Dashboard Kivox</h1>
                <p class="text-sm text-slate-500">Métricas SaaS — MRR, ingresos, churn y próximos cobros</p>
            </div>
        </div>
    </div>

    {{-- KPIs PRINCIPALES --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-5 text-white shadow-lg">
            <div class="text-[10px] uppercase opacity-80 font-bold">MRR (Ingreso mensual recurrente)</div>
            <div class="text-3xl font-extrabold mt-1">${{ number_format($this->mrr, 0, ',', '.') }}</div>
            <div class="text-[11px] opacity-90 mt-1">COP / mes</div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="text-[10px] uppercase text-slate-500 font-bold">Ingresos mes en curso</div>
            <div class="text-2xl font-extrabold text-slate-800 mt-1">${{ number_format($this->kpis['ingresoMes'], 0, ',', '.') }}</div>
            @if($this->kpis['delta'] !== null)
                <div class="text-[11px] mt-1 {{ $this->kpis['delta'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    <i class="fa-solid fa-arrow-{{ $this->kpis['delta'] >= 0 ? 'up' : 'down' }}"></i>
                    {{ abs($this->kpis['delta']) }}% vs mes pasado
                </div>
            @else
                <div class="text-[11px] text-slate-400 mt-1">Sin comparativa</div>
            @endif
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="text-[10px] uppercase text-slate-500 font-bold">Tenants activos</div>
            <div class="text-2xl font-extrabold text-slate-800 mt-1">{{ $this->kpis['activos'] }} / {{ $this->kpis['tenants'] }}</div>
            <div class="text-[11px] text-slate-400 mt-1">
                @if($this->kpis['suspendidos'] > 0)
                    <span class="text-rose-500 font-semibold">{{ $this->kpis['suspendidos'] }} suspendidos</span>
                @else
                    Todos activos ✓
                @endif
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="text-[10px] uppercase text-slate-500 font-bold">Por cobrar (pendiente)</div>
            <div class="text-2xl font-extrabold text-amber-600 mt-1">${{ number_format($this->kpis['pendientes'], 0, ',', '.') }}</div>
            <div class="text-[11px] text-slate-400 mt-1">
                {{ $this->kpis['morosos'] }} suscripciones vencidas
            </div>
        </div>
    </div>

    {{-- GRÁFICA INGRESOS 12 MESES --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-brand"></i>
                Ingresos últimos 12 meses
            </h3>
        </div>
        @php
            $maxVal = max(array_values($this->serie12meses)) ?: 1;
        @endphp
        <div class="flex items-end gap-2 h-44">
            @foreach($this->serie12meses as $mes => $valor)
                @php
                    $altura = $valor > 0 ? max(8, ($valor / $maxVal) * 100) : 2;
                    $esActual = $mes === now()->format('Y-m');
                @endphp
                <div class="flex-1 flex flex-col items-center group">
                    <div class="w-full text-center text-[9px] font-mono text-slate-500 mb-1 opacity-0 group-hover:opacity-100">
                        ${{ number_format($valor, 0, ',', '.') }}
                    </div>
                    <div class="w-full rounded-t transition-all hover:opacity-80
                                {{ $esActual ? 'bg-gradient-to-t from-emerald-500 to-emerald-400' : 'bg-gradient-to-t from-brand to-brand-secondary' }}"
                         style="height: {{ $altura }}%;"
                         title="{{ $mes }} — ${{ number_format($valor, 0, ',', '.') }}">
                    </div>
                    <div class="text-[10px] text-slate-500 mt-1 font-mono">{{ \Carbon\Carbon::createFromFormat('Y-m', $mes)->isoFormat('MMM') }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- POR PLAN + PRÓXIMOS VENCIMIENTOS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-violet-500"></i>
                    Distribución por plan
                </h3>
            </div>
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Plan</th>
                        <th class="px-3 py-2 text-center">Activas</th>
                        <th class="px-3 py-2 text-right">Precio mensual</th>
                        <th class="px-3 py-2 text-right">Aporta MRR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($this->porPlan as $plan)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 font-semibold text-slate-800">{{ $plan->nombre }}</td>
                            <td class="px-3 py-2 text-center">{{ $plan->activas_count }}</td>
                            <td class="px-3 py-2 text-right">${{ number_format($plan->precio_mensual, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-bold text-emerald-700">
                                ${{ number_format($plan->precio_mensual * $plan->activas_count, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-clock text-amber-500"></i>
                    Próximos vencimientos (15 días)
                </h3>
            </div>
            @if($this->proximosVencimientos->isEmpty())
                <div class="p-6 text-center text-slate-400 text-xs">
                    <i class="fa-solid fa-circle-check text-emerald-400 text-2xl mb-2"></i>
                    <p>Ninguna suscripción vence pronto</p>
                </div>
            @else
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Tenant</th>
                            <th class="px-3 py-2 text-left">Plan</th>
                            <th class="px-3 py-2 text-center">Vence</th>
                            <th class="px-3 py-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($this->proximosVencimientos as $sus)
                            @php
                                $dias = (int) now()->startOfDay()->diffInDays($sus->fecha_fin->startOfDay(), false);
                                $colorDias = $dias <= 3 ? 'text-rose-600' : ($dias <= 7 ? 'text-amber-600' : 'text-slate-600');
                            @endphp
                            <tr class="hover:bg-amber-50/30">
                                <td class="px-3 py-2 font-semibold text-slate-800">{{ $sus->tenant?->nombre ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $sus->plan?->nombre ?? '—' }}</td>
                                <td class="px-3 py-2 text-center {{ $colorDias }} font-bold">
                                    {{ $sus->fecha_fin->format('d/m') }}
                                    <div class="text-[9px]">({{ $dias }}d)</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold">${{ number_format($sus->monto, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-600">
        <p class="font-bold text-slate-700 mb-2">⚙️ Crons automáticos activos</p>
        <ul class="space-y-1 list-disc list-inside">
            <li><code>saas:generar-facturas-mensuales --dias=7 --enviar</code> — diario 09:00 — crea factura + manda link Wompi 7 días antes del vencimiento</li>
            <li><code>tenants:suspender-vencidos --gracia=7 --enviar</code> — diario 10:00 — recordatorios escalonados + suspensión al día 7 de mora</li>
        </ul>
    </div>
</div>
