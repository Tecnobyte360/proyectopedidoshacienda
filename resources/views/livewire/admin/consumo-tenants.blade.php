<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">📊 Consumo por Tenant</h1>
            <p class="text-sm text-gray-500">Llamadas LLM, tokens, costo estimado y actividad por cliente</p>
        </div>
        <div class="text-right">
            <div class="text-xs text-gray-500">Servidor: {{ gethostname() }}</div>
            <div class="text-xs text-gray-500">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Desde</label>
                <input type="date" wire:model.live="desde" class="border rounded px-2 py-1 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Hasta</label>
                <input type="date" wire:model.live="hasta" class="border rounded px-2 py-1 text-sm">
            </div>
            <div class="flex gap-1">
                <button wire:click="setRango('hoy')" class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Hoy</button>
                <button wire:click="setRango('7d')" class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">7d</button>
                <button wire:click="setRango('30d')" class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">30d</button>
                <button wire:click="setRango('mes')" class="px-3 py-1 text-xs bg-emerald-100 hover:bg-emerald-200 rounded font-medium">Este mes</button>
                <button wire:click="setRango('mes_anterior')" class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Mes anterior</button>
            </div>
        </div>
    </div>

    {{-- Totales globales --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
            <div class="text-xs text-gray-500 uppercase">Costo total (estimado)</div>
            <div class="text-2xl font-bold text-emerald-700">${{ number_format($datos['total']['costo_usd'], 2) }}</div>
            <div class="text-xs text-gray-400">USD en LLM</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-xs text-gray-500 uppercase">Llamadas LLM</div>
            <div class="text-2xl font-bold text-blue-700">{{ number_format($datos['total']['llamadas']) }}</div>
            <div class="text-xs text-gray-400">Total invocaciones</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="text-xs text-gray-500 uppercase">Tokens totales</div>
            <div class="text-2xl font-bold text-purple-700">{{ number_format($datos['total']['tk_in'] + $datos['total']['tk_out']) }}</div>
            <div class="text-xs text-gray-400">
                in {{ number_format($datos['total']['tk_in']) }} / out {{ number_format($datos['total']['tk_out']) }}
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="text-xs text-gray-500 uppercase">Pedidos creados</div>
            <div class="text-2xl font-bold text-orange-700">{{ number_format($datos['total']['pedidos']) }}</div>
            <div class="text-xs text-gray-400">${{ number_format($datos['total']['facturado'], 0, ',', '.') }} facturados</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-pink-500">
            <div class="text-xs text-gray-500 uppercase">Mensajes WA</div>
            <div class="text-2xl font-bold text-pink-700">{{ number_format($datos['total']['msgs_user'] + $datos['total']['msgs_bot']) }}</div>
            <div class="text-xs text-gray-400">
                cli {{ number_format($datos['total']['msgs_user']) }} / bot {{ number_format($datos['total']['msgs_bot']) }}
            </div>
        </div>
    </div>

    {{-- Tabla detallada por tenant --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
                <tr>
                    <th class="px-3 py-2 text-left">Tenant</th>
                    <th class="px-3 py-2 text-right">Costo USD</th>
                    <th class="px-3 py-2 text-right">Llamadas</th>
                    <th class="px-3 py-2 text-right">Tokens in</th>
                    <th class="px-3 py-2 text-right">Tokens out</th>
                    <th class="px-3 py-2 text-right">Cache R/W</th>
                    <th class="px-3 py-2 text-right">Msgs WA</th>
                    <th class="px-3 py-2 text-right">Pedidos</th>
                    <th class="px-3 py-2 text-right">Facturado</th>
                    <th class="px-3 py-2 text-right">Tools</th>
                    <th class="px-3 py-2 text-right">Lat.</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($datos['tenants'] as $row)
                    <tr class="hover:bg-emerald-50">
                        <td class="px-3 py-2">
                            <div class="font-medium text-gray-800">{{ $row['tenant']->nombre }}</div>
                            <div class="text-xs text-gray-500">
                                #{{ $row['tenant']->id }} · {{ $row['tenant']->slug }}
                                @if(!$row['tenant']->activo) <span class="text-red-500">· suspendido</span> @endif
                            </div>
                            @if(count($row['modelos']) > 0)
                                <div class="mt-1 text-xs text-gray-400">
                                    @foreach($row['modelos'] as $m)
                                        <span class="inline-block bg-gray-100 rounded px-1.5 py-0.5 mr-1">{{ $m['modelo'] }} ({{ $m['llamadas'] }})</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right font-semibold {{ $row['costo_usd'] > 5 ? 'text-red-600' : ($row['costo_usd'] > 1 ? 'text-orange-600' : 'text-emerald-700') }}">
                            ${{ number_format($row['costo_usd'], 3) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ number_format($row['llamadas']) }}
                            @if($row['llamadas'] > 0 && $row['exitosas'] < $row['llamadas'])
                                <span class="text-xs text-red-500">({{ $row['llamadas'] - $row['exitosas'] }} fail)</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-blue-700">{{ number_format($row['tk_in']) }}</td>
                        <td class="px-3 py-2 text-right text-purple-700">{{ number_format($row['tk_out']) }}</td>
                        <td class="px-3 py-2 text-right text-xs text-gray-500">
                            {{ number_format($row['tk_cache_r']) }} / {{ number_format($row['tk_cache_w']) }}
                        </td>
                        <td class="px-3 py-2 text-right text-pink-700">{{ number_format($row['msgs_user'] + $row['msgs_bot']) }}</td>
                        <td class="px-3 py-2 text-right text-orange-700 font-medium">{{ number_format($row['pedidos']) }}</td>
                        <td class="px-3 py-2 text-right">${{ number_format($row['facturado'], 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ number_format($row['tools']) }}</td>
                        <td class="px-3 py-2 text-right text-xs {{ $row['lat_ms_avg'] > 3000 ? 'text-red-500' : 'text-gray-500' }}">
                            {{ $row['lat_ms_avg'] > 0 ? $row['lat_ms_avg'].'ms' : '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-bold text-sm border-t-2">
                <tr>
                    <td class="px-3 py-2">TOTAL</td>
                    <td class="px-3 py-2 text-right text-emerald-700">${{ number_format($datos['total']['costo_usd'], 2) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($datos['total']['llamadas']) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($datos['total']['tk_in']) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($datos['total']['tk_out']) }}</td>
                    <td class="px-3 py-2 text-right text-xs">
                        {{ number_format($datos['total']['tk_cache_r']) }} / {{ number_format($datos['total']['tk_cache_w']) }}
                    </td>
                    <td class="px-3 py-2 text-right">{{ number_format($datos['total']['msgs_user'] + $datos['total']['msgs_bot']) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($datos['total']['pedidos']) }}</td>
                    <td class="px-3 py-2 text-right">${{ number_format($datos['total']['facturado'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($datos['total']['tools']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-4 text-xs text-gray-400">
        Costos estimados según pricing público de Anthropic / OpenAI. Pricing puede variar; valida con tu facturación oficial.
    </div>
</div>
