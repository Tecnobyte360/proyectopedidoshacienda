<div class="p-6 max-w-7xl mx-auto" wire:poll.2s>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-xl shadow-lg">
                <i class="fa-solid fa-phone-volume"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 flex items-center gap-2">
                    Monitor IVR
                    @php $enVivo = $llamadas->where('estado','en_curso')->count(); @endphp
                    @if($enVivo > 0)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold animate-pulse">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            {{ $enVivo }} en vivo
                        </span>
                    @endif
                </h1>
                <p class="text-sm text-slate-500 flex items-center gap-1">
                    Llamadas entrantes — actualiza automáticamente cada 2s
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @foreach(['hoy' => 'Hoy', 'semana' => 'Últimos 7 días', 'mes' => 'Últimos 30 días'] as $key => $label)
                <button wire:click="$set('rango', '{{ $key }}')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition
                              {{ $rango === $key ? 'bg-indigo-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        @php
            $tiles = [
                ['Total llamadas',     $kpis['total'],            'fa-phone',           'from-indigo-500 to-blue-500'],
                ['Transferidas',       $kpis['transferidas'],     'fa-headset',         'from-emerald-500 to-teal-500'],
                ['Consultas pedido',   $kpis['consultas_pedido'], 'fa-clipboard-list',  'from-amber-500 to-orange-500'],
                ['Voicemails',         $kpis['voicemails'],       'fa-voicemail',       'from-rose-500 to-pink-500'],
                ['Duración prom.',     gmdate('i:s', $kpis['duracion_promedio']), 'fa-clock', 'from-slate-600 to-slate-700'],
            ];
        @endphp
        @foreach($tiles as [$label, $valor, $icon, $grad])
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">{{ $label }}</span>
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br {{ $grad }} text-white text-xs">
                        <i class="fa-solid {{ $icon }}"></i>
                    </span>
                </div>
                <div class="text-2xl font-extrabold text-slate-900">{{ $valor }}</div>
            </div>
        @endforeach
    </div>

    {{-- Distribución de opciones --}}
    @if($opciones->isNotEmpty())
    <div class="rounded-2xl bg-white border border-slate-200 p-5 mb-6">
        <h3 class="text-sm font-bold text-slate-700 mb-3"><i class="fa-solid fa-chart-bar mr-1 text-indigo-500"></i> Opciones más elegidas</h3>
        <div class="space-y-2">
            @foreach($opciones as $op)
                @php $porcentaje = $kpis['total'] > 0 ? round(($op->total / $kpis['total']) * 100, 1) : 0; @endphp
                <div>
                    <div class="flex justify-between text-xs text-slate-600 mb-1">
                        <span class="font-mono font-bold">Opción "{{ $op->opcion_elegida }}"</span>
                        <span>{{ $op->total }} ({{ $porcentaje }}%)</span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full transition-all" style="width: {{ $porcentaje }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Filtros + tabla --}}
    <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
            <input wire:model.live.debounce.300ms="busqueda" type="text"
                   placeholder="Buscar por número o nombre..."
                   class="flex-1 min-w-[200px] rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <select wire:model.live="estado" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos los estados</option>
                <option value="en_curso">En curso</option>
                <option value="terminada_ok">Terminada OK</option>
                <option value="voicemail">Voicemail</option>
                <option value="terminada_timeout">Timeout</option>
                <option value="terminada_invalido">Opción inválida</option>
            </select>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-2.5 text-left">Cliente / Número</th>
                    <th class="px-4 py-2.5 text-left">Inicio</th>
                    <th class="px-4 py-2.5 text-center">Opción</th>
                    <th class="px-4 py-2.5 text-center">Duración</th>
                    <th class="px-4 py-2.5 text-center">Estado</th>
                    <th class="px-4 py-2.5 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($llamadas as $l)
                    @php $enCurso = $l->estado === 'en_curso'; @endphp
                    <tr class="hover:bg-slate-50/50 {{ $enCurso ? 'bg-emerald-50/60 animate-pulse-soft' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if($enCurso)
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                    </span>
                                @endif
                                <div>
                                    <div class="font-semibold text-slate-800">
                                        {{ $l->cliente?->nombre ?? 'Extensión '.$l->caller_id }}
                                    </div>
                                    <div class="text-xs text-slate-500 font-mono flex items-center gap-1.5">
                                        <span>{{ $l->caller_id }}</span>
                                        @if($l->did_destino)
                                            <span class="text-slate-300">→</span>
                                            <span class="text-indigo-600 font-bold">{{ $l->did_destino }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 text-xs">
                            {{ $l->iniciada_at->format('d/m H:i:s') }}
                            <div class="text-[10px] text-slate-400">{{ $l->iniciada_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($l->opcion_elegida)
                                <span class="inline-flex items-center justify-center h-6 w-6 rounded-lg bg-indigo-100 text-indigo-700 text-xs font-bold">
                                    {{ $l->opcion_elegida }}
                                </span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600 font-mono text-xs">
                            {{ $l->duracion_segundos ? gmdate('i:s', $l->duracion_segundos) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $badge = match($l->estado) {
                                    'en_curso'           => ['bg-gradient-to-r from-emerald-500 to-green-500 text-white shadow shadow-emerald-200', '🔴 EN VIVO'],
                                    'terminada_ok'       => ['bg-emerald-100 text-emerald-700', 'OK'],
                                    'voicemail'          => ['bg-rose-100 text-rose-700', 'Voicemail'],
                                    'terminada_timeout'  => ['bg-amber-100 text-amber-700', 'Timeout'],
                                    'terminada_invalido' => ['bg-slate-200 text-slate-600', 'Inválido'],
                                    default              => ['bg-slate-100 text-slate-600', $l->estado],
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badge[0] }}">
                                {{ $badge[1] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($l->pedido)
                                <a href="/pedidos?id={{ $l->pedido->id }}" class="text-xs text-amber-600 hover:underline">
                                    Pedido #{{ $l->pedido->id }}
                                </a>
                            @endif
                            @if($l->voicemail_path)
                                <button class="text-xs text-rose-600 hover:underline ml-2">
                                    <i class="fa-solid fa-play"></i> Audio
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-phone-slash text-3xl mb-2 text-slate-300"></i>
                            <div>Aún no hay llamadas registradas</div>
                            <div class="text-xs mt-1">Cuando Asterisk reciba una llamada y notifique a la API, aparecerá acá.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($llamadas->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">{{ $llamadas->links() }}</div>
        @endif
    </div>

</div>
