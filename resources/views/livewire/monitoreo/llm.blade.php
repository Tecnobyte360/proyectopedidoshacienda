<div class="p-4 sm:p-6 space-y-5" wire:poll.5s>

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white text-xl"
                 style="background: linear-gradient(135deg, #6366f1, #a855f7);">
                <i class="fa-solid fa-microchip"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Monitor del Sistema</h1>
                <p class="text-xs text-slate-500">LLM (Anthropic), watchdog y eventos del bot en tiempo real</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <x-tenant-view-selector />
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

    {{-- 📑 TABS: LLM / Watchdog --}}
    <div class="inline-flex items-center gap-1 rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
        <button type="button" wire:click="$set('tab', 'llm')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition
                       {{ $tab === 'llm' ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-microchip text-[12px]"></i>
            LLM Anthropic
            <span class="inline-flex items-center justify-center min-w-[20px] h-[20px] px-1.5 rounded-full text-[10px] font-bold
                         {{ $tab === 'llm' ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-700' }}">
                {{ $total }}
            </span>
        </button>
        <button type="button" wire:click="$set('tab', 'watchdog')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition
                       {{ $tab === 'watchdog' ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-dog text-[12px]"></i>
            Watchdog
            <span class="inline-flex items-center justify-center min-w-[20px] h-[20px] px-1.5 rounded-full text-[10px] font-bold
                         {{ $tab === 'watchdog' ? 'bg-white/25 text-white' : ($wdFallidos > 0 ? 'bg-rose-200 text-rose-800' : 'bg-amber-200 text-amber-800') }}">
                {{ $wdTotal }}
            </span>
        </button>
        <button type="button" wire:click="$set('tab', 'agente')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition
                       {{ $tab === 'agente' ? 'bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-robot text-[12px]"></i>
            Agente (Tools)
            <span class="inline-flex items-center justify-center min-w-[20px] h-[20px] px-1.5 rounded-full text-[10px] font-bold
                         {{ $tab === 'agente' ? 'bg-white/25 text-white' : 'bg-emerald-200 text-emerald-800' }}">
                {{ $agTotal }}
            </span>
        </button>
        <button type="button" wire:click="$set('tab', 'envivo')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition
                       {{ $tab === 'envivo' ? 'bg-gradient-to-r from-rose-500 to-red-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-tower-broadcast text-[12px]"></i>
            En Vivo
            <span class="inline-flex items-center justify-center px-1.5 h-[20px] rounded-full text-[9px] font-bold
                         {{ $tab === 'envivo' ? 'bg-white/25 text-white' : 'bg-rose-200 text-rose-800' }}">
                LIVE
            </span>
        </button>
        <button type="button" wire:click="$set('tab', 'cola')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition
                       {{ $tab === 'cola' ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-envelope-circle-check text-[12px]"></i>
            Cola Salida
            <span class="inline-flex items-center justify-center min-w-[20px] h-[20px] px-1.5 rounded-full text-[10px] font-bold
                         {{ $tab === 'cola' ? 'bg-white/25 text-white' : ($coPendientes > 0 ? 'bg-rose-200 text-rose-800 animate-pulse' : 'bg-indigo-200 text-indigo-800') }}">
                {{ $coPendientes }}
            </span>
        </button>
        <button type="button" wire:click="$set('tab', 'erp')"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition
                       {{ $tab === 'erp' ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-database text-[12px]"></i>
            ERP Queue
            <span class="inline-flex items-center justify-center min-w-[20px] h-[20px] px-1.5 rounded-full text-[10px] font-bold
                         {{ $tab === 'erp' ? 'bg-white/25 text-white' : ($erpPendientes > 0 ? 'bg-amber-200 text-amber-800 animate-pulse' : 'bg-amber-100 text-amber-700') }}">
                {{ $erpPendientes }}
            </span>
        </button>
    </div>

    @if($tab === 'llm')

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
            <p class="text-[11px] uppercase font-bold text-emerald-700">Cache hits <i class="fa-solid fa-money-bill"></i></p>
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

    @elseif($tab === 'watchdog')

    {{-- ╔═══════════════ TAB WATCHDOG ═══════════════╗ --}}

    {{-- Estado general del watchdog --}}
    @php
        $wdEstado = $wdFallidos > 0 ? 'fallidos' : ($wdTotal > 0 ? 'activo' : 'ok');
        $wdColor = ['ok' => 'emerald', 'activo' => 'amber', 'fallidos' => 'rose'][$wdEstado];
        $wdTexto = [
            'ok' => '✅ Sin rescates (bot funcionando bien)',
            'activo' => '🐕 Rescates exitosos',
            'fallidos' => '⚠️ Hay rescates fallidos',
        ][$wdEstado];
    @endphp
    <div class="rounded-2xl border-2 border-{{ $wdColor }}-300 bg-{{ $wdColor }}-50 p-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <p class="text-xs font-bold uppercase text-{{ $wdColor }}-700">Estado del Watchdog</p>
            <p class="text-2xl font-bold text-{{ $wdColor }}-900 mt-1">{{ $wdTexto }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-500">Tasa de rescate ({{ $minutos }} min)</p>
            <p class="text-3xl font-black text-{{ $wdColor }}-700">{{ $wdTasaExito }}%</p>
        </div>
    </div>

    {{-- KPIs watchdog --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total rescates</p>
            <h3 class="mt-2 text-3xl font-bold text-slate-900">{{ $wdTotal }}</h3>
            <p class="mt-1 text-[11px] text-slate-400">últimos {{ $minutos }} min</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Exitosos</p>
            <h3 class="mt-2 text-3xl font-bold text-emerald-700">{{ $wdExitosos }}</h3>
            <p class="mt-1 text-[11px] text-emerald-600">{{ $wdTasaExito }}% éxito</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Fallidos</p>
            <h3 class="mt-2 text-3xl font-bold text-rose-700">{{ $wdFallidos }}</h3>
            <p class="mt-1 text-[11px] text-rose-600">requieren atención</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Espera prom.</p>
            <h3 class="mt-2 text-3xl font-bold text-amber-700">
                {{ $wdPromSegs }}<span class="text-base font-normal">s</span>
            </h3>
            <p class="mt-1 text-[11px] text-amber-600">antes del rescate</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50/40 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Clientes</p>
            <h3 class="mt-2 text-3xl font-bold text-sky-700">{{ $wdClientes }}</h3>
            <p class="mt-1 text-[11px] text-sky-600">únicos rescatados</p>
        </div>
    </div>

    {{-- Listado --}}
    @if($wdTotal === 0)
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 text-3xl"><i class="fa-solid fa-dog"></i></div>
            <h3 class="mt-3 text-lg font-semibold text-slate-700">Sin rescates en los últimos {{ $minutos }} minutos</h3>
            <p class="mt-1 text-sm text-slate-500 max-w-md mx-auto">
                El watchdog está corriendo cada minuto pero no ha tenido que rescatar ninguna conversación.
                El bot está respondiendo bien a todos los clientes.
            </p>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2">
                <h2 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                    Últimos rescates
                </h2>
                @if($wdUltimo)
                    <span class="text-[11px] text-slate-500">
                        Último: hace {{ $wdUltimo->created_at->diffForHumans() }}
                    </span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Hora</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cliente / Teléfono</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden md:table-cell">Conv</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Mensaje del cliente</th>
                            <th class="px-3 py-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Esperó</th>
                            <th class="px-3 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($wdRescates as $r)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 align-middle whitespace-nowrap">
                                    <div class="text-xs font-semibold text-slate-700">{{ $r->created_at->format('h:i a') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $r->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="text-xs font-semibold text-slate-900">{{ $r->conversacion?->cliente?->nombre ?? 'Sin nombre' }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">{{ $r->telefono }}</div>
                                </td>
                                <td class="px-3 py-3 align-middle hidden md:table-cell">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">
                                        #{{ $r->conversacion_id }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="text-xs text-slate-700 truncate max-w-[280px]" title="{{ $r->mensaje_contenido }}">
                                        "{{ \Illuminate\Support\Str::limit($r->mensaje_contenido, 80) }}"
                                    </div>
                                    @if($r->error_mensaje && !$r->exitoso)
                                        <div class="text-[10px] text-rose-600 mt-1 truncate max-w-[280px]" title="{{ $r->error_mensaje }}">
                                            <i class="fa-solid fa-circle-exclamation text-[9px]"></i>
                                            {{ \Illuminate\Support\Str::limit($r->error_mensaje, 60) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 align-middle text-right whitespace-nowrap">
                                    @php
                                        $segs = (int) $r->segundos_estancada;
                                        $tono = $segs < 60 ? 'bg-emerald-100 text-emerald-700'
                                              : ($segs < 300 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
                                        $human = $segs >= 60 ? floor($segs/60).'m '.($segs%60).'s' : $segs.'s';
                                    @endphp
                                    <span class="inline-flex items-center gap-1 rounded-full {{ $tono }} px-2 py-0.5 text-[10px] font-bold">
                                        <i class="fa-regular fa-clock text-[9px]"></i>{{ $human }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-middle text-center">
                                    @if($r->exitoso)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                            <i class="fa-solid fa-circle-check text-[9px]"></i>Rescatado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                                            <i class="fa-solid fa-circle-xmark text-[9px]"></i>Falló
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Info del watchdog --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2 mb-2">
            <i class="fa-solid fa-info-circle text-slate-400"></i>
            ¿Qué hace el watchdog?
        </h3>
        <ul class="text-xs text-slate-600 space-y-1.5">
            <li>• Corre <strong>cada 60 segundos</strong> en el scheduler.</li>
            <li>• Detecta conversaciones donde el último mensaje sea del <strong>cliente</strong> y hayan pasado más de 15 segundos sin respuesta del bot.</li>
            <li>• Re-envía el mensaje del cliente al webhook para que el bot lo procese de nuevo.</li>
            <li>• Cubre fallos transitorios: errores PHP, timeouts de Anthropic, tool_use huérfanos, etc.</li>
            <li>• Cooldown de 30 min por conversación y 24h por mensaje para evitar pedidos duplicados.</li>
        </ul>
    </div>

    @elseif($tab === 'agente')

    {{-- ╔═══════════════ TAB AGENTE (TOOLS) ═══════════════╗ --}}

    {{-- Estado general del agente --}}
    @php
        $agEstado = $agTasaExito >= 95 ? 'ok' : ($agTasaExito >= 80 ? 'amber' : 'fallidos');
        $agColor = ['ok' => 'emerald', 'amber' => 'amber', 'fallidos' => 'rose'][$agEstado];
    @endphp
    <div class="rounded-2xl border-2 border-{{ $agColor }}-300 bg-{{ $agColor }}-50 p-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <p class="text-xs font-bold uppercase text-{{ $agColor }}-700">Estado del Agente (uso de tools)</p>
            <p class="text-2xl font-bold text-{{ $agColor }}-900 mt-1">
                {{ $agTotal }} invocacion{{ $agTotal === 1 ? '' : 'es' }} en {{ $minutos }} min
            </p>
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-500">Tasa de éxito</p>
            <p class="text-3xl font-black text-{{ $agColor }}-700">{{ $agTasaExito }}%</p>
        </div>
    </div>

    {{-- KPIs agente --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total invocaciones</p>
            <h3 class="mt-2 text-3xl font-bold text-slate-900">{{ $agTotal }}</h3>
            <p class="mt-1 text-[11px] text-slate-400">últimos {{ $minutos }} min</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Exitosas</p>
            <h3 class="mt-2 text-3xl font-bold text-emerald-700">{{ $agExitosos }}</h3>
            <p class="mt-1 text-[11px] text-emerald-600">{{ $agTasaExito }}% éxito</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Latencia prom.</p>
            <h3 class="mt-2 text-3xl font-bold text-amber-700">
                {{ $agLatencia }}<span class="text-base font-normal">ms</span>
            </h3>
            <p class="mt-1 text-[11px] text-amber-600">por tool</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Sin resultados</p>
            <h3 class="mt-2 text-3xl font-bold text-rose-700">{{ $agSinResults }}</h3>
            <p class="mt-1 text-[11px] text-rose-600">tools que devolvieron vacío</p>
        </div>
    </div>

    {{-- Distribución por tool + Top queries --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2 mb-3">
                <i class="fa-solid fa-chart-bar text-slate-400"></i>
                Distribución por tool
            </h3>
            @if($agPorTool->isEmpty())
                <p class="text-xs text-slate-400 italic">Sin invocaciones en este rango.</p>
            @else
                <div class="space-y-2">
                    @foreach($agPorTool as $t)
                        @php $pct = round(($t->total / $agMaxPorTool) * 100); @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs mb-0.5">
                                <span class="font-semibold text-slate-700 truncate">{{ $t->tool_name }}</span>
                                <span class="text-slate-500">
                                    {{ $t->total }} <span class="text-slate-400">· {{ (int) $t->latencia }}ms</span>
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="bg-emerald-500 h-full" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2 mb-3">
                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                Top búsquedas de productos
            </h3>
            @if($agTopQueries->isEmpty())
                <p class="text-xs text-slate-400 italic">Sin búsquedas en este rango.</p>
            @else
                <div class="flex flex-wrap gap-1.5">
                    @foreach($agTopQueries as $q => $c)
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                            "{{ $q }}"
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-slate-700 text-white text-[10px] font-bold">{{ $c }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Últimas invocaciones --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200">
            <h2 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                Últimas invocaciones de tools
            </h2>
        </div>
        @if($agInvocaciones->isEmpty())
            <div class="p-8 text-center text-sm text-slate-400 italic">
                Sin invocaciones en los últimos {{ $minutos }} minutos.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Hora</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Tool</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Teléfono</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Args</th>
                            <th class="px-3 py-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Resultados</th>
                            <th class="px-3 py-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Latencia</th>
                            <th class="px-3 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($agInvocaciones as $inv)
                            @php
                                $argsStr = is_array($inv->args) ? json_encode($inv->args, JSON_UNESCAPED_UNICODE) : (string) $inv->args;
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 align-middle whitespace-nowrap">
                                    <div class="text-xs font-semibold text-slate-700">{{ $inv->created_at->format('h:i:s a') }}</div>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                        {{ $inv->tool_name }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <span class="text-[11px] text-slate-600 font-mono">{{ $inv->telefono_cliente ?? '—' }}</span>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <code class="text-[10px] text-slate-600 bg-slate-50 px-1.5 py-0.5 rounded truncate max-w-[220px] inline-block"
                                          title="{{ $argsStr }}">
                                        {{ \Illuminate\Support\Str::limit($argsStr, 60) }}
                                    </code>
                                </td>
                                <td class="px-3 py-2 align-middle text-right">
                                    <span class="text-xs font-bold {{ ($inv->count_resultados ?? 0) === 0 ? 'text-rose-600' : 'text-slate-700' }}">
                                        {{ $inv->count_resultados ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-middle text-right">
                                    <span class="text-xs text-slate-600">{{ $inv->latencia_ms }}ms</span>
                                </td>
                                <td class="px-3 py-2 align-middle text-center">
                                    @if($inv->exitoso)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                            <i class="fa-solid fa-circle-check text-[9px]"></i>OK
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700"
                                              title="{{ $inv->error }}">
                                            <i class="fa-solid fa-circle-xmark text-[9px]"></i>Falló
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @elseif($tab === 'envivo')

    {{-- 📡 TAB EN VIVO: Monitor del Bot --}}
    <div class="rounded-2xl border border-rose-200 bg-rose-50/30 p-3 mb-4">
        <p class="text-xs text-slate-600">
            <i class="fa-solid fa-tower-broadcast mr-1 text-rose-600"></i>
            <strong>Monitor en vivo del bot</strong> — KPIs del día, conversaciones activas, alucinaciones detectadas, timeline. Auto-refresca cada 3 segundos.
        </p>
    </div>

    {{-- Embeber el componente Bot/Monitor que ya existe --}}
    @livewire('bot.monitor', [], key('bot-monitor-livewire'))

    @elseif($tab === 'cola')

    {{-- ═════════════════════════════════════════════════════════════
         📬 TAB COLA SALIDA — Mensajes pendientes de envío a WhatsApp
         ═════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/40 p-3 mb-4">
        <p class="text-xs text-slate-600">
            <i class="fa-solid fa-envelope-circle-check mr-1 text-indigo-600"></i>
            <strong>Cola de mensajes salientes</strong> — Cuando WhatsApp se desconecta, las respuestas del bot
            entran a esta cola y se reintentan automáticamente (backoff progresivo: 15s, 30s, 1m, 2m, 5m, 15m, 1h, 6h).
            Tras 12 intentos sin éxito → marcado como fallido permanente.
        </p>
    </div>

    {{-- KPIs cola --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[11px] uppercase font-bold text-slate-500">Pendientes</p>
            <p class="text-3xl font-black {{ $coPendientes > 0 ? 'text-rose-700' : 'text-emerald-700' }} mt-1">{{ $coPendientes }}</p>
            <p class="text-xs text-slate-500 mt-1">Esperan envío</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[11px] uppercase font-bold text-slate-500">Listos ahora</p>
            <p class="text-3xl font-black text-indigo-700 mt-1">{{ $coReady }}</p>
            <p class="text-xs text-slate-500 mt-1">próximo_intento ≤ now</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[11px] uppercase font-bold text-slate-500">Enviados ({{ $minutos }}m)</p>
            <p class="text-3xl font-black text-emerald-700 mt-1">{{ $coEnviadosVentana }}</p>
            <p class="text-xs text-slate-500 mt-1">recuperados tras caída</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-[11px] uppercase font-bold text-slate-500">Fallidos perm. ({{ $minutos }}m)</p>
            <p class="text-3xl font-black {{ $coFallidosPerm > 0 ? 'text-rose-700' : 'text-slate-700' }} mt-1">{{ $coFallidosPerm }}</p>
            <p class="text-xs text-slate-500 mt-1">12 intentos agotados</p>
        </div>
    </div>

    {{-- Acciones --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 mt-4 flex items-center gap-2 flex-wrap">
        <p class="text-sm text-slate-600 mr-auto">
            <i class="fa-solid fa-screwdriver-wrench text-indigo-600"></i>
            <strong>Acciones rápidas:</strong>
        </p>
        <button type="button" wire:click="$refresh"
                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700">
            <i class="fa-solid fa-arrows-rotate"></i> Refrescar
        </button>
        <button type="button" wire:click="limpiarColaSalida"
                wire:confirm="¿Eliminar mensajes enviados/fallidos de más de 7 días?"
                class="inline-flex items-center gap-1.5 rounded-xl bg-amber-100 hover:bg-amber-200 px-3 py-1.5 text-xs font-bold text-amber-800">
            <i class="fa-solid fa-broom"></i> Limpiar antiguos (>7d)
        </button>
    </div>

    {{-- Parámetros editables --}}
    <details class="rounded-2xl border border-indigo-200 bg-white p-4 mt-4" open>
        <summary class="cursor-pointer text-sm font-bold text-slate-700 flex items-center gap-2">
            <i class="fa-solid fa-sliders text-indigo-600"></i>
            Parámetros configurables
        </summary>

        <form wire:submit.prevent="guardarParametrosCola" class="mt-4 space-y-4">
            {{-- Toggle activa --}}
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" wire:model="coActiva"
                       class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                <div>
                    <p class="text-sm font-bold text-slate-700">Cola de salida activa</p>
                    <p class="text-[11px] text-slate-500">Si la apagas, los envíos fallidos NO se encolan (comportamiento legacy: el mensaje se pierde).</p>
                </div>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Máx intentos --}}
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                        Máx intentos antes de marcar fallido
                    </label>
                    <input type="number" wire:model="coMaxIntentos" min="1" max="50"
                           class="w-full rounded-xl border-2 border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <p class="text-[11px] text-slate-500 mt-1">Entre 1 y 50. Default: 12.</p>
                </div>

                {{-- Email de alerta --}}
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                        Email para alertas (opcional)
                    </label>
                    <input type="email" wire:model="coEmailAlerta" placeholder="admin@empresa.com"
                           class="w-full rounded-xl border-2 border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <p class="text-[11px] text-slate-500 mt-1">Recibe email si un mensaje queda fallido permanente. Máx 1 por hora.</p>
                </div>
            </div>

            {{-- Backoff --}}
            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                    Backoff (segundos por intento, separados por coma)
                </label>
                <input type="text" wire:model="coBackoffTexto"
                       placeholder="15,30,60,120,300,900,3600,21600"
                       class="w-full rounded-xl border-2 border-slate-200 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none">
                <p class="text-[11px] text-slate-500 mt-1">
                    Cada número es el delay antes del siguiente intento. Ej:
                    <code class="bg-slate-100 px-1 rounded">15,30,60</code> significa intento1→espera 15s, intento2→30s, intento3→60s.
                    Si hay más intentos que valores, se usa el último.
                </p>
                <p class="text-[11px] text-slate-500 mt-1">
                    Default: <code class="bg-slate-100 px-1 rounded">15,30,60,120,300,900,3600,21600</code>
                    (15s → 30s → 1m → 2m → 5m → 15m → 1h → 6h)
                </p>
            </div>

            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 text-sm font-bold">
                    <i class="fa-solid fa-save"></i>
                    Guardar parámetros
                </button>
                <span class="text-[11px] text-slate-500">Se aplican al siguiente ciclo del scheduler (próximo minuto).</span>
            </div>
        </form>

        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="font-bold text-slate-700 mb-1"><i class="fa-solid fa-clock"></i> Frecuencia de reintento</p>
                <p class="text-slate-600">Cada <strong>1 minuto</strong> (scheduler — no parametrizable)</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <p class="font-bold text-slate-700 mb-1"><i class="fa-solid fa-satellite-dish"></i> Auto-reconexión WhatsApp</p>
                <p class="text-slate-600">Cada <strong>1 min</strong> · 3 fallos → email del tenant</p>
            </div>
        </div>
    </details>

    {{-- Tabla últimos mensajes --}}
    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden mt-4">
        <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700">
                <i class="fa-solid fa-list text-indigo-600 mr-1"></i>
                Últimos 50 mensajes en cola
            </h3>
            <span class="text-xs text-slate-500">{{ $coUltimosMensajes->count() }} registros</span>
        </div>

        @if($coUltimosMensajes->isEmpty())
            <div class="p-8 text-center text-slate-500">
                <i class="fa-solid fa-inbox text-3xl mb-2 opacity-50"></i>
                <p class="text-sm">No hay mensajes en la cola.</p>
                <p class="text-xs text-slate-400 mt-1">Cuando WhatsApp se caiga y un envío falle, aparecerá aquí.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-[10px]">
                        <tr>
                            <th class="px-3 py-2 text-left">ID</th>
                            <th class="px-3 py-2 text-left">Tel</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                            <th class="px-3 py-2 text-left">Intentos</th>
                            <th class="px-3 py-2 text-left">Próximo</th>
                            <th class="px-3 py-2 text-left">Mensaje</th>
                            <th class="px-3 py-2 text-left">Error</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($coUltimosMensajes as $m)
                            @php
                                $payloadArr = is_array($m->payload) ? $m->payload : (json_decode($m->payload, true) ?: []);
                                $bodyPreview = mb_substr((string) ($payloadArr['body'] ?? ''), 0, 80);
                                if ($m->enviado_at) {
                                    $estado = ['emerald', '✅ Enviado'];
                                } elseif ($m->fallido_permanente_at) {
                                    $estado = ['rose', '❌ Fallido'];
                                } elseif ($m->proximo_intento_at && \Carbon\Carbon::parse($m->proximo_intento_at)->isFuture()) {
                                    $estado = ['amber', '⏳ Esperando'];
                                } else {
                                    $estado = ['indigo', '🔄 Listo'];
                                }
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono text-[10px] text-slate-500">{{ $m->id }}</td>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $m->telefono }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-{{ $estado[0] }}-100 text-{{ $estado[0] }}-800 px-2 py-0.5 text-[10px] font-bold">
                                        {{ $estado[1] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $m->intentos }}</td>
                                <td class="px-3 py-2 text-[11px] text-slate-600">
                                    @if($m->enviado_at)
                                        <span class="text-emerald-700">{{ \Carbon\Carbon::parse($m->enviado_at)->diffForHumans() }}</span>
                                    @elseif($m->fallido_permanente_at)
                                        <span class="text-rose-700">{{ \Carbon\Carbon::parse($m->fallido_permanente_at)->diffForHumans() }}</span>
                                    @elseif($m->proximo_intento_at)
                                        {{ \Carbon\Carbon::parse($m->proximo_intento_at)->diffForHumans() }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 max-w-[300px] truncate text-slate-700" title="{{ $payloadArr['body'] ?? '' }}">
                                    {{ $bodyPreview ?: '(sin texto)' }}
                                </td>
                                <td class="px-3 py-2 max-w-[200px] truncate text-rose-700 text-[11px]" title="{{ $m->ultimo_error }}">
                                    {{ mb_substr((string) $m->ultimo_error, 0, 60) }}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @if(!$m->enviado_at && !$m->fallido_permanente_at)
                                        <button type="button"
                                                wire:click="reintentarMensajePendiente({{ $m->id }})"
                                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-100 hover:bg-indigo-200 px-2 py-1 text-[10px] font-bold text-indigo-700"
                                                title="Reintentar ahora">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                        <button type="button"
                                                wire:click="descartarMensajePendiente({{ $m->id }})"
                                                wire:confirm="¿Descartar este mensaje? El cliente no lo recibirá."
                                                class="inline-flex items-center gap-1 rounded-lg bg-rose-100 hover:bg-rose-200 px-2 py-1 text-[10px] font-bold text-rose-700"
                                                title="Descartar">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @elseif($tab === 'erp')

    {{-- ═════════════════════════════════════════════════════════════
         🔄 TAB ERP RETRY QUEUE
         Pedidos/clientes que fallaron al sincronizar con el ERP
         (SQL Server caído) y se están reintentando en background.
         ═════════════════════════════════════════════════════════════ --}}
    <div class="space-y-5">

        {{-- ─── KPIs principales ─── --}}
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs uppercase tracking-wide text-amber-700 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-hourglass-half"></i> Pendientes
                </div>
                <div class="text-3xl font-black text-amber-900 mt-1">{{ $erpPendientes }}</div>
                <div class="text-[11px] text-amber-700 mt-1">esperando reintento</div>
            </div>
            <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4">
                <div class="text-xs uppercase tracking-wide text-orange-700 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-bolt"></i> Listos ahora
                </div>
                <div class="text-3xl font-black text-orange-900 mt-1">{{ $erpReady }}</div>
                <div class="text-[11px] text-orange-700 mt-1">próximo intento ya pasó</div>
            </div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <div class="text-xs uppercase tracking-wide text-blue-700 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-spinner"></i> Procesando
                </div>
                <div class="text-3xl font-black text-blue-900 mt-1">{{ $erpProcesando }}</div>
                <div class="text-[11px] text-blue-700 mt-1">en ejecución</div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs uppercase tracking-wide text-emerald-700 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-circle-check"></i> Completados 24h
                </div>
                <div class="text-3xl font-black text-emerald-900 mt-1">{{ $erpCompletados24h }}</div>
                <div class="text-[11px] text-emerald-700 mt-1">sincronizados con ERP</div>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <div class="text-xs uppercase tracking-wide text-rose-700 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-circle-exclamation"></i> Máx alcanzado
                </div>
                <div class="text-3xl font-black text-rose-900 mt-1">{{ $erpFallidosMax }}</div>
                <div class="text-[11px] text-rose-700 mt-1">requieren intervención manual</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-700 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-clock"></i> Próximo intento
                </div>
                <div class="text-sm font-bold text-slate-900 mt-1">
                    @if($erpProximoIntento)
                        {{ \Carbon\Carbon::parse($erpProximoIntento)->diffForHumans() }}
                    @else
                        —
                    @endif
                </div>
                <div class="text-[11px] text-slate-600 mt-1">scheduler corre c/5min</div>
            </div>
        </div>

        {{-- ─── Acciones ─── --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-slate-600">
                <i class="fa-solid fa-circle-info text-amber-500"></i>
                Cuando el ERP cae (SQL Server, timeout, deadlock), los clientes/pedidos se
                encolan aquí y se reintentan automáticamente con <strong>backoff exponencial</strong>
                (5min → 10min → 20min → 40min → 1h máx, hasta 20 intentos).
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="erpProcesarTodos"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-3 py-2 text-xs font-bold text-white shadow hover:shadow-lg transition">
                    <i class="fa-solid fa-play"></i>
                    Procesar todos ahora
                </button>
                <button type="button" wire:click="erpLimpiarHistorico"
                        wire:confirm="¿Eliminar items completados/descartados con más de 7 días?"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition">
                    <i class="fa-solid fa-broom"></i>
                    Limpiar histórico >7d
                </button>
            </div>
        </div>

        {{-- ─── Tabla de items recientes ─── --}}
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-amber-600"></i>
                    Últimos 50 items de la cola
                </h3>
            </div>

            @if($erpUltimos->isEmpty())
                <div class="p-8 text-center text-slate-500">
                    <i class="fa-solid fa-check-circle text-4xl text-emerald-400 mb-2"></i>
                    <p class="text-sm">No hay items en la cola. Todo está sincronizado <i class="fa-solid fa-sparkles"></i></p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Tipo</th>
                                <th class="px-3 py-2 text-left">Tenant</th>
                                <th class="px-3 py-2 text-left">Pedido / Datos</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                <th class="px-3 py-2 text-center">Intentos</th>
                                <th class="px-3 py-2 text-left">Próximo intento</th>
                                <th class="px-3 py-2 text-left">Último error</th>
                                <th class="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($erpUltimos as $p)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 text-slate-500 font-mono text-[11px]">{{ $p->id }}</td>
                                    <td class="px-3 py-2">
                                        @if($p->tipo === \App\Models\ErpPedidoPendiente::TIPO_CLIENTE_CREAR)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-violet-100 text-violet-800 px-2 py-0.5 text-[10px] font-bold">
                                                <i class="fa-solid fa-user-plus"></i> Crear cliente
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-md bg-blue-100 text-blue-800 px-2 py-0.5 text-[10px] font-bold">
                                                <i class="fa-solid fa-file-export"></i> Exportar pedido
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        {{ $p->tenant?->nombre ?? 'tenant#' . $p->tenant_id }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        @if($p->pedido_id)
                                            <a href="{{ url('/pedidos/' . $p->pedido_id) }}"
                                               class="text-blue-600 hover:underline font-bold">#{{ $p->pedido_id }}</a>
                                        @endif
                                        @if(!empty($p->payload['cedula']))
                                            <div class="text-[11px] text-slate-500">
                                                Céd: {{ $p->payload['cedula'] }}
                                                @if(!empty($p->payload['nombre']))
                                                    · {{ $p->payload['nombre'] }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @php
                                            $badge = match($p->estado) {
                                                'pendiente'   => 'bg-amber-100 text-amber-800',
                                                'procesando'  => 'bg-blue-100 text-blue-800 animate-pulse',
                                                'completado'  => 'bg-emerald-100 text-emerald-800',
                                                'fallido_max' => 'bg-rose-100 text-rose-800',
                                                'descartado'  => 'bg-slate-200 text-slate-700',
                                                default       => 'bg-slate-100 text-slate-700',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ $badge }}">
                                            {{ $p->estado }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center text-slate-700">
                                        <span class="font-bold">{{ $p->intentos }}</span>
                                        <span class="text-slate-400">/ {{ $p->max_intentos }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-600">
                                        @if($p->proximo_intento_at && $p->estado === 'pendiente')
                                            {{ \Carbon\Carbon::parse($p->proximo_intento_at)->diffForHumans() }}
                                        @elseif($p->completado_at)
                                            <span class="text-emerald-700"><i class="fa-solid fa-check"></i> {{ \Carbon\Carbon::parse($p->completado_at)->diffForHumans() }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-600 max-w-[280px]">
                                        @if($p->ultimo_error)
                                            <span title="{{ $p->ultimo_error }}" class="text-rose-700">
                                                {{ \Illuminate\Support\Str::limit($p->ultimo_error, 80) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        @if(in_array($p->estado, ['pendiente', 'fallido_max']))
                                            <button type="button" wire:click="erpReintentar({{ $p->id }})"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-amber-100 hover:bg-amber-200 px-2 py-1 text-[10px] font-bold text-amber-800 mr-1"
                                                    title="Reintentar ahora">
                                                <i class="fa-solid fa-rotate-right"></i>
                                            </button>
                                            <button type="button" wire:click="erpDescartar({{ $p->id }})"
                                                    wire:confirm="¿Descartar este item? No se reintentará más."
                                                    class="inline-flex items-center gap-1 rounded-lg bg-rose-100 hover:bg-rose-200 px-2 py-1 text-[10px] font-bold text-rose-700"
                                                    title="Descartar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @endif
</div>
