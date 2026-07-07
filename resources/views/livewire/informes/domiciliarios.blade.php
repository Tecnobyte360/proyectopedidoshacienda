<div class="px-6 lg:px-10 py-8">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">
                <i class="fa-solid fa-chart-column text-brand"></i> Informe de domiciliarios
            </h2>
            <p class="text-sm text-slate-500">Pedidos del día. Filtra por domiciliario.</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Desde</label>
                <input type="date" wire:model.live="desde"
                       class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Hasta</label>
                <input type="date" wire:model.live="hasta"
                       class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-brand">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Domiciliario</label>
                <select wire:model.live="filtroDomiciliario"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-brand min-w-[180px]">
                    <option value="">Todos</option>
                    @foreach($domiciliarios as $d)
                        <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                    @endforeach
                    <option value="sin">— Sin asignar —</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Estado</label>
                <select wire:model.live="filtroEstado"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-brand">
                    <option value="todos">Todos</option>
                    <option value="entregados">Solo entregados</option>
                </select>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white p-4 shadow">
            <div class="text-2xl font-extrabold text-slate-800">{{ $kpis['total_pedidos'] }}</div>
            <div class="text-xs text-slate-500">Pedidos</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow">
            <div class="text-2xl font-extrabold text-blue-600">{{ $kpis['entregados'] }}</div>
            <div class="text-xs text-slate-500">Entregados</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow">
            <div class="text-2xl font-extrabold text-emerald-600">${{ number_format($kpis['total_valor'], 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">Valor total</div>
        </div>
    </div>

    {{-- Tabla única --}}
    <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Domiciliario</th>
                        <th class="px-4 py-3 text-left">Cliente</th>
                        <th class="px-4 py-3 text-left">Dirección</th>
                        <th class="px-4 py-3 text-left">Estado</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-left">Fecha / Hora</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pedidos as $p)
                        @php
                            $colorEstado = match($p->estado) {
                                'nuevo' => 'bg-blue-100 text-blue-700',
                                'en_preparacion' => 'bg-amber-100 text-amber-700',
                                'repartidor_en_camino' => 'bg-violet-100 text-violet-700',
                                'entregado' => 'bg-emerald-100 text-emerald-700',
                                'cancelado' => 'bg-rose-100 text-rose-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5 font-bold text-slate-800">#{{ $p->id }}</td>
                            <td class="px-4 py-2.5">
                                @if($p->domiciliario)
                                    <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                                        <i class="fa-solid fa-motorcycle text-[11px] text-brand"></i> {{ $p->domiciliario->nombre }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400 italic">Sin asignar</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $p->cliente_nombre }}</td>
                            <td class="px-4 py-2.5 text-slate-500 text-xs max-w-[240px] truncate">
                                {{ $p->esRecogerEnSede() ? '🏪 Recoge en sede' : ($p->direccion ?: '-') }}
                                @if(!$p->esRecogerEnSede() && $p->barrio)<span class="text-slate-400"> · {{ $p->barrio }}</span>@endif
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $colorEstado }}">
                                    {{ ucfirst(str_replace('_', ' ', $p->estado)) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold text-slate-800">${{ number_format((float) $p->total, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-slate-500 text-xs">{{ $p->created_at?->timezone('America/Bogota')->format('d/m/Y h:i a') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-50"></i>
                                No hay pedidos con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($pedidos->count() > 0)
                    <tfoot class="bg-slate-50 font-bold text-slate-800">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right">Total ({{ $pedidos->count() }} pedidos):</td>
                            <td class="px-4 py-3 text-right text-emerald-600">${{ number_format($kpis['total_valor'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
