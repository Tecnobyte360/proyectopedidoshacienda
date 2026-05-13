<div class="p-4 sm:p-6 space-y-5" wire:poll.5s>

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white text-xl"
                 style="background: linear-gradient(135deg, #6366f1, #a855f7);">
                <i class="fa-solid fa-microchip"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Monitor LLM (Anthropic)</h1>
                <p class="text-xs text-slate-500">Cada llamada al modelo, status, tokens y errores en tiempo real</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs text-slate-500">Ventana:</label>
            <select wire:model.live="minutos" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                <option value="5">5 min</option>
                <option value="15">15 min</option>
                <option value="30">30 min</option>
                <option value="60">1 hora</option>
                <option value="240">4 horas</option>
                <option value="1440">24 horas</option>
            </select>
        </div>
    </div>

    {{-- Estado actual de la API --}}
    @php
        $estadoColor = ['ok' => 'emerald', 'rate_limit' => 'amber', 'overloaded' => 'rose'][$estado] ?? 'slate';
        $estadoTexto = ['ok' => '✅ Funcionando', 'rate_limit' => '⚠️ Rate Limit', 'overloaded' => '🔴 Saturada'][$estado] ?? '?';
    @endphp
    <div class="rounded-2xl border-2 border-{{ $estadoColor }}-300 bg-{{ $estadoColor }}-50 p-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <p class="text-xs font-bold uppercase text-{{ $estadoColor }}-700">Estado actual Anthropic API</p>
            <p class="text-2xl font-bold text-{{ $estadoColor }}-900 mt-1">{{ $estadoTexto }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-500">Tasa de éxito ({{ $minutos }} min)</p>
            <p class="text-3xl font-black text-{{ $estadoColor }}-700">{{ $tasaExito }}%</p>
        </div>
    </div>

    {{-- Rate limit (tokens último minuto) --}}
    @php
        $barColor = $porcentajeUso >= 90 ? 'rose' : ($porcentajeUso >= 70 ? 'amber' : 'emerald');
    @endphp
    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between mb-2">
            <div>
                <p class="text-xs uppercase font-bold text-slate-600">Rate limit organización (Tier 1)</p>
                <p class="text-[11px] text-slate-500">Último minuto. Si pasa de 100% → Anthropic responde 429</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-{{ $barColor }}-700">{{ number_format($tokensUltimoMin) }} / {{ number_format($rateLimitTier) }}</p>
                <p class="text-xs text-slate-500">tokens / minuto</p>
            </div>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
            <div class="bg-{{ $barColor }}-500 h-full transition-all" style="width: {{ $porcentajeUso }}%"></div>
        </div>
        <p class="text-[11px] text-{{ $barColor }}-700 mt-1">{{ $porcentajeUso }}% usado</p>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div class="rounded-2xl bg-white border border-slate-200 p-3">
            <p class="text-[11px] uppercase font-bold text-slate-500">Total llamadas</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $total }}</p>
        </div>
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-3">
            <p class="text-[11px] uppercase font-bold text-emerald-700">Exitosas</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $exitosos }}</p>
        </div>
        <div class="rounded-2xl bg-rose-50 border border-rose-200 p-3">
            <p class="text-[11px] uppercase font-bold text-rose-700">Errores</p>
            <p class="text-2xl font-bold text-rose-700 mt-1">{{ $errores }}</p>
        </div>
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-3">
            <p class="text-[11px] uppercase font-bold text-amber-700">Rate limit (429)</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ $rateLimit }}</p>
        </div>
        <div class="rounded-2xl bg-rose-50 border border-rose-200 p-3">
            <p class="text-[11px] uppercase font-bold text-rose-700">Overloaded (529)</p>
            <p class="text-2xl font-bold text-rose-700 mt-1">{{ $overloaded }}</p>
        </div>
        <div class="rounded-2xl bg-blue-50 border border-blue-200 p-3">
            <p class="text-[11px] uppercase font-bold text-blue-700">Latencia prom.</p>
            <p class="text-2xl font-bold text-blue-700 mt-1">{{ $latPromedio }}<span class="text-sm">ms</span></p>
        </div>
    </div>

    {{-- Tokens consumidos --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl bg-white border border-slate-200 p-3">
            <p class="text-[11px] uppercase font-bold text-slate-500">Tokens input</p>
            <p class="text-xl font-bold text-slate-800 mt-1">{{ number_format($tokensInput) }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-3">
            <p class="text-[11px] uppercase font-bold text-slate-500">Tokens output</p>
            <p class="text-xl font-bold text-slate-800 mt-1">{{ number_format($tokensOutput) }}</p>
        </div>
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-3" title="Cache HIT = no cuenta hacia rate limit, costo 10%">
            <p class="text-[11px] uppercase font-bold text-emerald-700">Cache hits 💰</p>
            <p class="text-xl font-bold text-emerald-700 mt-1">{{ number_format($tokensCacheRead) }}</p>
        </div>
        <div class="rounded-2xl bg-sky-50 border border-sky-200 p-3" title="Tokens guardados en cache (cobran 125%)">
            <p class="text-[11px] uppercase font-bold text-sky-700">Cache creado</p>
            <p class="text-xl font-bold text-sky-700 mt-1">{{ number_format($tokensCacheCreate) }}</p>
        </div>
    </div>

    {{-- Recomendaciones automáticas --}}
    @if($rateLimit > 0 || $overloaded > 0 || $porcentajeUso >= 70)
        <div class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-4">
            <p class="font-bold text-amber-900 mb-2"><i class="fa-solid fa-lightbulb"></i> Recomendaciones</p>
            <ul class="list-disc ml-5 text-sm text-amber-900 space-y-1">
                @if($rateLimit > 0)
                    <li><strong>Rate limit (429) detectado:</strong> tu organización superó 30K tokens/min. Subir tier a Tier 2 ($40 USD) → 80K tokens/min en <a href="https://console.anthropic.com/settings/limits" target="_blank" class="underline">console.anthropic.com</a></li>
                @endif
                @if($overloaded > 0)
                    <li><strong>Overloaded (529) detectado:</strong> Anthropic global saturada. El sistema hizo {{ $fallbacks }} fallback(s) a Haiku. Estado: <a href="https://status.anthropic.com" target="_blank" class="underline">status.anthropic.com</a></li>
                @endif
                @if($porcentajeUso >= 70)
                    <li><strong>Consumo alto ({{ $porcentajeUso }}%):</strong> estás cerca del rate limit. Considera subir tier o reducir conversaciones simultáneas.</li>
                @endif
            </ul>
        </div>
    @endif

    {{-- Timeline de llamadas --}}
    <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-700">Últimas {{ count($invocaciones) }} llamadas a Anthropic</h2>
            <p class="text-[11px] text-slate-500">Actualiza cada 5 segundos</p>
        </div>
        @if($invocaciones->isEmpty())
            <div class="p-8 text-center text-slate-500 text-sm">
                Sin llamadas en los últimos {{ $minutos }} min. Cuando un cliente escriba al bot, aparecerán aquí.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Hora</th>
                            <th class="px-3 py-2 text-left">Modelo</th>
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-right">Latencia</th>
                            <th class="px-3 py-2 text-right">Tokens IN</th>
                            <th class="px-3 py-2 text-right">Tokens OUT</th>
                            <th class="px-3 py-2 text-right">Cache HIT</th>
                            <th class="px-3 py-2 text-center">Intentos</th>
                            <th class="px-3 py-2 text-left">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invocaciones as $inv)
                            @php
                                $rowColor = $inv->exitoso ? '' : ($inv->http_status === 429 ? 'bg-amber-50' : 'bg-rose-50');
                                $statusBg = match (true) {
                                    $inv->exitoso              => 'bg-emerald-100 text-emerald-800',
                                    $inv->http_status === 429  => 'bg-amber-200 text-amber-900',
                                    $inv->http_status === 529  => 'bg-rose-200 text-rose-900',
                                    default                    => 'bg-slate-200 text-slate-700',
                                };
                                $statusLabel = $inv->exitoso ? '200 OK' : ($inv->http_status ?: 'ERR');
                            @endphp
                            <tr class="border-t border-slate-100 {{ $rowColor }}">
                                <td class="px-3 py-2 text-xs text-slate-500 whitespace-nowrap">{{ $inv->created_at->format('H:i:s') }}</td>
                                <td class="px-3 py-2 text-xs font-mono">
                                    {{ str_replace('claude-', '', $inv->modelo) }}
                                    @if($inv->es_fallback)
                                        <span class="ml-1 px-1 py-0.5 rounded bg-purple-100 text-purple-700 text-[9px] font-bold">FALLBACK</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $statusBg }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-right text-slate-600">{{ $inv->latencia_ms }}ms</td>
                                <td class="px-3 py-2 text-xs text-right text-slate-600">{{ $inv->tokens_input ? number_format($inv->tokens_input) : '—' }}</td>
                                <td class="px-3 py-2 text-xs text-right text-slate-600">{{ $inv->tokens_output ? number_format($inv->tokens_output) : '—' }}</td>
                                <td class="px-3 py-2 text-xs text-right text-emerald-700 font-semibold">{{ $inv->tokens_cache_read ? number_format($inv->tokens_cache_read) : '—' }}</td>
                                <td class="px-3 py-2 text-center text-xs {{ $inv->intentos > 1 ? 'font-bold text-amber-700' : 'text-slate-500' }}">{{ $inv->intentos }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600 max-w-md truncate" title="{{ $inv->error_mensaje }}">
                                    @if($inv->error_tipo)
                                        <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px] font-bold mr-1">{{ $inv->error_tipo }}</span>
                                    @endif
                                    {{ \Illuminate\Support\Str::limit($inv->error_mensaje, 80) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
