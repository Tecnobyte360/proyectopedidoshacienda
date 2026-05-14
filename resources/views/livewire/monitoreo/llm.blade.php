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
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 text-3xl">🐕</div>
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

    @endif
</div>
