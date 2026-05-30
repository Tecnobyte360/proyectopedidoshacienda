<div class="p-4 lg:p-6 space-y-6 max-w-7xl mx-auto">

    {{-- Header con nombre + back --}}
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('campanas.index') }}" class="text-xs text-slate-500 hover:underline">
                <i class="fa-solid fa-arrow-left"></i> Volver a campañas
            </a>
            <h1 class="text-2xl font-bold text-slate-800 mt-1 truncate">📊 {{ $campana->nombre }}</h1>
            <p class="text-sm text-slate-500">
                {{ $campana->programada_para?->format('d M Y H:i') ?? 'Sin programar' }} ·
                <span class="font-semibold">{{ ucfirst($campana->estado) }}</span>
                @if($campana->plantilla_meta_nombre)
                    · Plantilla: <code class="bg-slate-100 px-1 rounded">{{ $campana->plantilla_meta_nombre }}</code>
                @endif
            </p>
        </div>
    </div>

    {{-- 📈 KPIs FUNNEL --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Destinatarios</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($kpis['total']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">100%</div>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <div class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">Enviados</div>
            <div class="text-2xl font-bold text-emerald-800 mt-1">{{ number_format($kpis['enviados']) }}</div>
            <div class="text-[10px] text-emerald-600 mt-0.5">Salieron OK por Meta</div>
        </div>

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
            <div class="text-[10px] uppercase font-bold text-blue-700 tracking-wider">Entregados ✓✓</div>
            <div class="text-2xl font-bold text-blue-800 mt-1">{{ number_format($kpis['entregados']) }}</div>
            <div class="text-[10px] text-blue-600 mt-0.5">{{ $kpis['pct_entregados'] }}% de enviados</div>
        </div>

        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
            <div class="text-[10px] uppercase font-bold text-indigo-700 tracking-wider">Leídos 👀</div>
            <div class="text-2xl font-bold text-indigo-800 mt-1">{{ number_format($kpis['leidos']) }}</div>
            <div class="text-[10px] text-indigo-600 mt-0.5">{{ $kpis['pct_leidos'] }}% de entregados</div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <div class="text-[10px] uppercase font-bold text-amber-700 tracking-wider">Respondieron 💬</div>
            <div class="text-2xl font-bold text-amber-800 mt-1">{{ number_format($kpis['respondieron']) }}</div>
            <div class="text-[10px] text-amber-600 mt-0.5">{{ $kpis['pct_respondieron'] }}% engagement</div>
        </div>

        <div class="rounded-2xl border border-fuchsia-200 bg-fuchsia-50 p-4 shadow-sm">
            <div class="text-[10px] uppercase font-bold text-fuchsia-700 tracking-wider">Conversión 🛒</div>
            <div class="text-2xl font-bold text-fuchsia-800 mt-1">{{ number_format($kpis['convirtieron']) }}</div>
            <div class="text-[10px] text-fuchsia-600 mt-0.5">{{ $kpis['pct_convirtieron'] }}% → pedido</div>
        </div>
    </div>

    {{-- 🔘 BOTONES + REACCIONES (lado a lado) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Botones Quick Reply --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-700 mb-3">
                <i class="fa-solid fa-hand-pointer text-violet-500"></i> Clics en botones
                <span class="text-xs text-slate-400 font-normal">({{ $kpis['clicaron'] }} clientes — {{ $kpis['pct_clicaron'] }}%)</span>
            </h3>
            @if(empty($botones))
                <p class="text-sm text-slate-400 italic">Aún nadie ha tocado los botones.</p>
            @else
                <div class="space-y-2">
                    @foreach($botones as $b)
                        @php $pct = $kpis['enviados'] > 0 ? round(($b['n'] / $kpis['enviados']) * 100, 1) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-semibold text-slate-700">"{{ $b['boton_click'] }}"</span>
                                <span class="text-slate-500">{{ $b['n'] }} · {{ $pct }}%</span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden mt-1">
                                <div class="h-full bg-gradient-to-r from-violet-400 to-violet-600" style="width: {{ min(100, $pct) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Reacciones --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-700 mb-3">
                <i class="fa-solid fa-heart text-rose-500"></i> Reacciones
                <span class="text-xs text-slate-400 font-normal">({{ $kpis['reaccionaron'] }} clientes)</span>
            </h3>
            @if(empty($reacciones))
                <p class="text-sm text-slate-400 italic">Nadie ha reaccionado al mensaje.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach($reacciones as $r)
                        <div class="flex items-center gap-2 rounded-full bg-rose-50 border border-rose-200 px-3 py-1.5">
                            <span class="text-xl">{{ $r['reaccion'] }}</span>
                            <span class="text-sm font-bold text-rose-700">{{ $r['n'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- 🔍 FILTROS + TABLA --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex flex-wrap gap-1.5">
                @foreach([
                    'todos'         => ['Todos', $kpis['total'], 'bg-slate-100 text-slate-700'],
                    'leyeron'       => ['Leyeron', $kpis['leidos'], 'bg-indigo-100 text-indigo-700'],
                    'respondieron'  => ['Respondieron', $kpis['respondieron'], 'bg-amber-100 text-amber-700'],
                    'clicaron'      => ['Clicaron botón', $kpis['clicaron'], 'bg-violet-100 text-violet-700'],
                    'reaccionaron' => ['Reaccionaron', $kpis['reaccionaron'], 'bg-rose-100 text-rose-700'],
                    'convirtieron' => ['Convirtieron', $kpis['convirtieron'], 'bg-fuchsia-100 text-fuchsia-700'],
                    'fallaron'      => ['Fallaron', $kpis['fallaron'], 'bg-red-100 text-red-700'],
                ] as $k => [$label, $count, $color])
                    <button wire:click="setFiltro('{{ $k }}')"
                            class="px-3 py-1.5 rounded-full text-xs font-bold transition border
                            {{ $filtro === $k ? 'bg-slate-800 text-white border-slate-800' : $color . ' border-transparent hover:border-slate-300' }}">
                        {{ $label }} <span class="opacity-70">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
            <div class="ml-auto">
                <input wire:model.live.debounce.400ms="busqueda" type="text"
                       placeholder="Buscar por nombre o teléfono..."
                       class="rounded-xl border-slate-200 text-sm px-3 py-1.5 w-64">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                    <tr>
                        <th class="px-4 py-2 text-left">Cliente</th>
                        <th class="px-4 py-2 text-left">Teléfono</th>
                        <th class="px-4 py-2 text-center">Enviado</th>
                        <th class="px-4 py-2 text-center">Entregado</th>
                        <th class="px-4 py-2 text-center">Leído</th>
                        <th class="px-4 py-2 text-center">Respondió</th>
                        <th class="px-4 py-2 text-left">Botón</th>
                        <th class="px-4 py-2 text-center">React.</th>
                        <th class="px-4 py-2 text-center">Pedido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($destinatarios as $d)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 font-medium text-slate-700">{{ $d->nombre ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-500 font-mono text-xs">{{ $d->telefono }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($d->enviado_at)
                                    <span class="text-emerald-600" title="{{ $d->enviado_at }}">✓</span>
                                @elseif($d->estado === 'fallido')
                                    <span class="text-red-500" title="{{ $d->error_detalle }}">✗</span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($d->entregado_at)
                                    <span class="text-slate-600" title="{{ $d->entregado_at }}">✓✓</span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($d->leido_at)
                                    <span class="text-blue-500 font-bold" title="{{ $d->leido_at }}">✓✓</span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($d->respondio_at)
                                    <span class="text-amber-600" title="{{ $d->respondio_at }} · {{ $d->respuestas_count }} resp">
                                        💬 {{ $d->respuestas_count }}
                                    </span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs">
                                @if($d->boton_click)
                                    <span class="bg-violet-100 text-violet-700 rounded-full px-2 py-0.5 font-semibold">
                                        {{ $d->boton_click }}
                                    </span>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center text-lg">
                                {{ $d->reaccion ?: '·' }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($d->pedido_id)
                                    <a href="#" class="text-fuchsia-600 hover:underline text-xs font-bold">
                                        #{{ $d->pedido_id }}
                                    </a>
                                @else
                                    <span class="text-slate-300">·</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400 italic">
                            Sin destinatarios con este filtro.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-t border-slate-200">
            {{ $destinatarios->links() }}
        </div>
    </div>
</div>
