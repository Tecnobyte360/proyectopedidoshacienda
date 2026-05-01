<div class="px-6 lg:px-10 py-8" wire:poll.5s="$refresh">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">
                <i class="fa-solid fa-robot text-purple-600 mr-2"></i> Monitoreo del Agente
            </h2>
            <p class="text-sm text-slate-500">Cada tool call que el bot ejecuta en tiempo real. Refresca cada 5s.</p>
        </div>
        <div class="flex items-center gap-2">
            @foreach (['hoy' => 'Hoy', '7d' => '7 días', '30d' => '30 días'] as $key => $label)
                <button wire:click="$set('rango', '{{ $key }}')"
                        class="px-4 py-2 text-sm font-semibold rounded-xl transition {{ $rango === $key ? 'bg-purple-600 text-white shadow' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ number_format($totalInvocaciones) }}</div>
                    <div class="text-xs text-slate-500">Invocaciones</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $tasaExito }}%</div>
                    <div class="text-xs text-slate-500">Tasa éxito</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $latenciaProm }}<span class="text-base">ms</span></div>
                    <div class="text-xs text-slate-500">Latencia promedio</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ number_format($sinResultados) }}</div>
                    <div class="text-xs text-slate-500">Búsquedas sin resultado</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Distribución por tool --}}
        <div class="rounded-2xl bg-white p-5 shadow border border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 mb-3">
                <i class="fa-solid fa-chart-simple text-purple-600 mr-1"></i> Tools más usadas
            </h3>
            @if ($porTool->isEmpty())
                <p class="text-xs text-slate-400">Aún no hay datos.</p>
            @else
                <div class="space-y-2">
                    @foreach ($porTool as $row)
                        @php $pct = $maxPorTool > 0 ? round($row->total / $maxPorTool * 100) : 0; @endphp
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-mono text-slate-700">{{ $row->tool_name }}</span>
                                <span class="font-bold text-slate-800">{{ $row->total }} <span class="text-slate-400">· {{ (int) $row->latencia }}ms</span></span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-purple-500 to-indigo-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top búsquedas --}}
        <div class="rounded-2xl bg-white p-5 shadow border border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 mb-3">
                <i class="fa-solid fa-fire text-amber-500 mr-1"></i> Top búsquedas (buscar_productos)
            </h3>
            @if ($topQueries->isEmpty())
                <p class="text-xs text-slate-400">Aún no hay búsquedas.</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($topQueries as $query => $cnt)
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-mono text-slate-700 truncate">{{ \Illuminate\Support\Str::limit($query, 35) }}</span>
                            <span class="bg-amber-50 text-amber-700 rounded-md px-2 py-0.5 font-bold">{{ $cnt }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Filtros --}}
        <div class="rounded-2xl bg-white p-5 shadow border border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 mb-3">
                <i class="fa-solid fa-filter text-slate-500 mr-1"></i> Filtros
            </h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tool</label>
                    <select wire:model.live="filtroTool" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="todas">Todas</option>
                        @foreach ($toolsDisponibles as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar (teléfono / args)</label>
                    <input type="text" wire:model.live.debounce.400ms="busqueda"
                           placeholder="Ej: 3216499744 o pierna"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
            </div>
        </div>
    </div>

    {{-- LISTA en vivo --}}
    <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700">
                <i class="fa-solid fa-list text-slate-500 mr-1"></i> Invocaciones en vivo
            </h3>
            <span class="text-[11px] text-slate-400">
                <i class="fa-solid fa-circle text-emerald-500 animate-pulse"></i> Auto-refresh 5s
            </span>
        </div>

        @if ($invocaciones->isEmpty())
            <div class="p-10 text-center">
                <i class="fa-solid fa-inbox text-4xl text-slate-300 mb-3"></i>
                <p class="text-sm text-slate-500">No hay invocaciones aún en este rango.</p>
                <p class="text-xs text-slate-400 mt-1">Cuando un cliente le escriba al bot y este use una tool, aparecerá aquí.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Hora</th>
                            <th class="px-3 py-2 text-left">Tool</th>
                            <th class="px-3 py-2 text-left">Args</th>
                            <th class="px-3 py-2 text-center">Resultados</th>
                            <th class="px-3 py-2 text-center">Latencia</th>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-center">Estado</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invocaciones as $i)
                            @php
                                $argsStr = collect($i->args ?? [])
                                    ->map(fn ($v, $k) => "{$k}=" . (is_scalar($v) ? $v : json_encode($v)))
                                    ->implode(', ');
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-500 font-mono">{{ $i->created_at->format('H:i:s') }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-block rounded-md bg-purple-100 text-purple-700 px-2 py-0.5 font-mono font-bold">{{ $i->tool_name }}</span>
                                </td>
                                <td class="px-3 py-2 font-mono text-slate-600 max-w-xs truncate" title="{{ $argsStr }}">
                                    {{ \Illuminate\Support\Str::limit($argsStr, 50) }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($i->count_resultados > 0)
                                        <span class="inline-block rounded bg-emerald-100 text-emerald-700 px-2 py-0.5 font-bold">{{ $i->count_resultados }}</span>
                                    @else
                                        <span class="inline-block rounded bg-slate-100 text-slate-500 px-2 py-0.5">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center font-mono text-slate-600">{{ $i->latencia_ms }}ms</td>
                                <td class="px-3 py-2 font-mono text-slate-600">{{ $i->telefono_cliente }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if ($i->exitoso)
                                        <i class="fa-solid fa-circle-check text-emerald-500"></i>
                                    @else
                                        <i class="fa-solid fa-circle-xmark text-rose-500" title="{{ $i->error }}"></i>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="verDetalle({{ $i->id }})"
                                            class="text-purple-600 hover:text-purple-800 font-semibold">
                                        Ver
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-slate-100">
                {{ $invocaciones->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL DETALLE --}}
    @if ($detalle)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="cerrarDetalle">
            <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-800">
                            <i class="fa-solid fa-magnifying-glass-chart text-purple-600 mr-1"></i>
                            Detalle invocación #{{ $detalle->id }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $detalle->created_at->format('d/m/Y H:i:s') }} · {{ $detalle->latencia_ms }}ms</p>
                    </div>
                    <button wire:click="cerrarDetalle" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <div class="overflow-y-auto p-6 space-y-4 text-sm">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase mb-1">Tool</div>
                        <div class="font-mono bg-purple-50 text-purple-700 inline-block rounded px-2 py-0.5">{{ $detalle->tool_name }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase mb-1">Cliente</div>
                        <div class="font-mono">{{ $detalle->telefono_cliente }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase mb-1">Args (lo que el LLM le pasó)</div>
                        <pre class="rounded-xl bg-slate-900 text-emerald-100 p-3 text-[11px] overflow-x-auto whitespace-pre-wrap">{{ json_encode($detalle->args, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase mb-1">Resultado (resumen)</div>
                        <pre class="rounded-xl bg-slate-900 text-blue-200 p-3 text-[11px] overflow-x-auto whitespace-pre-wrap">{{ json_encode($detalle->resultado, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                    @if (!$detalle->exitoso && $detalle->error)
                        <div>
                            <div class="text-xs font-bold text-rose-600 uppercase mb-1">Error</div>
                            <pre class="rounded-xl bg-rose-50 text-rose-800 border border-rose-200 p-3 text-[11px] overflow-x-auto whitespace-pre-wrap">{{ $detalle->error }}</pre>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-3 border-t border-slate-200 bg-slate-50 flex justify-end">
                    <button wire:click="cerrarDetalle" class="rounded-xl bg-slate-200 hover:bg-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
