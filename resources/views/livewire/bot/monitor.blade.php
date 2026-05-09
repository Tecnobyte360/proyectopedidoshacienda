<div class="px-4 lg:px-8 py-6" wire:poll.3s>

    {{-- ╔═══ HEADER ═══╗ --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800 flex items-center gap-2">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow">
                    <i class="fa-solid fa-tower-broadcast"></i>
                </span>
                Monitor del bot
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    EN VIVO
                </span>
            </h2>
            <p class="text-sm text-slate-500 mt-1">
                Auto-refresh cada 3s · Ventana últimos {{ $ventanaMinutos }} min
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-400">
            <i class="fa-solid fa-clock"></i>
            {{ now()->format('H:i:s') }}
        </div>
    </div>

    {{-- ╔═══ KPIs ═══╗ --}}
    @php $k = $this->kpisHoy; @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200 p-4">
            <div class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Pedidos hoy</div>
            <div class="text-3xl font-extrabold text-emerald-700 mt-1">{{ $k['pedidos_hoy'] }}</div>
            <div class="text-xs text-emerald-600 mt-1">${{ number_format($k['total_facturado'], 0, ',', '.') }}</div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 p-4">
            <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Conv. activas (60m)</div>
            <div class="text-3xl font-extrabold text-blue-700 mt-1">{{ $k['conv_activas_60m'] }}</div>
            <div class="text-xs text-blue-600 mt-1">{{ $k['estados_en_curso'] }} en flujo</div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200 p-4">
            <div class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Alucinaciones</div>
            <div class="text-3xl font-extrabold text-amber-700 mt-1">{{ $k['alucinaciones'] }}</div>
            <div class="text-xs text-amber-600 mt-1">interceptadas</div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-rose-50 to-rose-100 border border-rose-200 p-4">
            <div class="text-[10px] font-bold text-rose-700 uppercase tracking-wider">Alertas total</div>
            <div class="text-3xl font-extrabold text-rose-700 mt-1">{{ $k['alertas_total'] }}</div>
            <div class="text-xs text-rose-600 mt-1">hoy</div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-violet-50 to-violet-100 border border-violet-200 p-4">
            <div class="text-[10px] font-bold text-violet-700 uppercase tracking-wider">% éxito</div>
            <div class="text-3xl font-extrabold text-violet-700 mt-1">
                @php
                    $exito = $k['pedidos_hoy'] > 0
                        ? min(100, round(($k['pedidos_hoy'] / max(1, $k['pedidos_hoy'] + $k['alucinaciones'])) * 100))
                        : 100;
                @endphp
                {{ $exito }}%
            </div>
            <div class="text-xs text-violet-600 mt-1">pedidos confirmados</div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200 p-4">
            <div class="text-[10px] font-bold text-slate-700 uppercase tracking-wider">Sistema</div>
            <div class="text-3xl font-extrabold text-emerald-600 mt-1">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="text-xs text-slate-600 mt-1">Operando</div>
        </div>
    </div>

    {{-- ╔═══ COLUMNAS PRINCIPALES ═══╗ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ─── COL 1: ESTADOS EN CURSO (2/3 del ancho) ─── --}}
        <div class="lg:col-span-2 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">
                    <i class="fa-solid fa-play-circle text-emerald-500"></i>
                    Conversaciones en flujo
                </h3>
                <span class="text-xs text-slate-400">{{ $this->estadosActivos->count() }} activas</span>
            </div>

            @forelse($this->estadosActivos as $estado)
                @php
                    $pasoColores = [
                        'inicio'         => ['bg-slate-100','text-slate-700','border-slate-300'],
                        'producto'       => ['bg-blue-50','text-blue-700','border-blue-300'],
                        'entrega'        => ['bg-cyan-50','text-cyan-700','border-cyan-300'],
                        'identificacion' => ['bg-amber-50','text-amber-700','border-amber-300'],
                        'confirmacion'   => ['bg-violet-50','text-violet-700','border-violet-300'],
                        'confirmado'     => ['bg-emerald-50','text-emerald-700','border-emerald-300'],
                        'abandonado'     => ['bg-rose-50','text-rose-700','border-rose-300'],
                    ];
                    [$bgPaso, $textPaso, $borderPaso] = $pasoColores[$estado->paso_actual] ?? $pasoColores['inicio'];
                    $faltantes = $estado->camposFaltantes();
                    $completo = $estado->estaCompleto();
                @endphp

                <div class="rounded-2xl bg-white border-l-4 {{ $borderPaso }} border-y border-r border-slate-200 p-4 shadow-sm hover:shadow-md transition cursor-pointer"
                     wire:click="abrirFoco({{ $estado->conversacion_id }})">

                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-800 truncate">
                                    {{ $estado->conversacion?->cliente?->nombre ?: ($estado->nombre_cliente ?: $estado->conversacion?->telefono_normalizado) }}
                                </span>
                                <span class="rounded-full {{ $bgPaso }} {{ $textPaso }} px-2 py-0.5 text-[10px] font-bold uppercase">
                                    {{ $estado->paso_actual }}
                                </span>
                                @if($estado->pedido_id)
                                    <span class="rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[10px] font-bold">
                                        ✅ #{{ $estado->pedido_id }}
                                    </span>
                                @endif
                                @if($completo && !$estado->pedido_id)
                                    <span class="rounded-full bg-violet-100 text-violet-700 px-2 py-0.5 text-[10px] font-bold animate-pulse">
                                        DATOS COMPLETOS
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                {{ $estado->conversacion?->telefono_normalizado }}
                                @if($estado->cedula) · 🪪 {{ $estado->cedula }} @endif
                                @if($estado->cliente_existe_erp) <span class="text-emerald-600">· ERP ✓</span> @endif
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-[10px] text-slate-400">Hace</div>
                            <div class="text-xs font-semibold text-slate-600">
                                {{ $estado->updated_at?->diffForHumans(null, true) }}
                            </div>
                        </div>
                    </div>

                    {{-- Mini-resumen de datos --}}
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                        {{-- Productos --}}
                        <div class="rounded-lg bg-slate-50 p-2">
                            <div class="text-[9px] text-slate-500 font-bold uppercase">🛒 Productos</div>
                            @if(!empty($estado->productos))
                                @foreach(array_slice($estado->productos, 0, 2) as $p)
                                    <div class="text-slate-700 truncate">{{ $p['quantity'] ?? '?' }} {{ $p['unit'] ?? '' }} {{ $p['name'] ?? '?' }}</div>
                                @endforeach
                                @if(count($estado->productos) > 2)
                                    <div class="text-[10px] text-slate-400">+ {{ count($estado->productos) - 2 }} más</div>
                                @endif
                            @else
                                <div class="text-slate-400 italic">—</div>
                            @endif
                        </div>

                        {{-- Entrega --}}
                        <div class="rounded-lg bg-slate-50 p-2">
                            <div class="text-[9px] text-slate-500 font-bold uppercase">🚚 Entrega</div>
                            @if($estado->metodo_entrega === 'recoger')
                                <div class="text-slate-700">Cliente recoge</div>
                                <div class="text-[10px] text-slate-500 truncate">{{ $estado->sede?->nombre ?: '—' }}</div>
                            @elseif($estado->metodo_entrega === 'domicilio')
                                <div class="text-slate-700">Despacho</div>
                                <div class="text-[10px] text-slate-500 truncate">{{ $estado->direccion ?: '—' }}</div>
                                @if($estado->cobertura_validada)
                                    <div class="text-[10px] text-emerald-600">✓ {{ $estado->distancia_km }}km</div>
                                @endif
                            @else
                                <div class="text-slate-400 italic">—</div>
                            @endif
                        </div>

                        {{-- Identificación --}}
                        <div class="rounded-lg bg-slate-50 p-2">
                            <div class="text-[9px] text-slate-500 font-bold uppercase">👤 Identif.</div>
                            <div class="text-slate-700 truncate">{{ $estado->nombre_cliente ?: '—' }}</div>
                            <div class="text-[10px] text-slate-500">{{ $estado->cedula ?: '—' }}</div>
                        </div>

                        {{-- Pendientes --}}
                        <div class="rounded-lg {{ $completo ? 'bg-emerald-50' : 'bg-amber-50' }} p-2">
                            <div class="text-[9px] {{ $completo ? 'text-emerald-700' : 'text-amber-700' }} font-bold uppercase">
                                {{ $completo ? '✅ Listo' : '⚠️ Falta' }}
                            </div>
                            @if($completo)
                                <div class="text-emerald-700 text-[10px]">A confirmar pedido</div>
                            @else
                                <div class="text-amber-700 text-[10px] truncate">{{ implode(', ', array_slice($faltantes, 0, 2)) }}</div>
                                @if(count($faltantes) > 2)
                                    <div class="text-[9px] text-amber-600">+ {{ count($faltantes) - 2 }} más</div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-8 text-center text-slate-400">
                    <i class="fa-solid fa-moon text-3xl mb-2"></i>
                    <p class="text-sm">Sin conversaciones activas en los últimos {{ $ventanaMinutos }} min</p>
                </div>
            @endforelse
        </div>

        {{-- ─── COL 2: TIMELINE DE EVENTOS (1/3 del ancho) ─── --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">
                    <i class="fa-solid fa-stream text-violet-500"></i>
                    Timeline del bot
                </h3>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 shadow-sm max-h-[800px] overflow-y-auto">
                @forelse($this->timelineEventos as $ev)
                    @php
                        $colorMap = [
                            'amber'   => 'bg-amber-50 border-amber-200 text-amber-800',
                            'emerald' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                            'cyan'    => 'bg-cyan-50 border-cyan-200 text-cyan-800',
                            'rose'    => 'bg-rose-50 border-rose-200 text-rose-800',
                        ];
                        $cls = $colorMap[$ev['color']] ?? 'bg-slate-50 border-slate-200 text-slate-800';
                    @endphp
                    <div class="border-b border-slate-100 last:border-b-0 p-3">
                        <div class="flex items-start gap-2">
                            <div class="text-base flex-shrink-0">{{ $ev['icon'] }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-slate-700 truncate">
                                    {{ $ev['titulo'] }}
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    {{ $ev['at']->format('H:i:s') }} ·
                                    {{ $ev['at']->diffForHumans(null, true) }}
                                </div>
                                @if(!empty($ev['meta']['from']))
                                    <div class="text-[10px] text-slate-500">
                                        📞 {{ $ev['meta']['from'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-400 text-sm">
                        Sin eventos recientes
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ╔═══ MODAL FOCO (cuando se hace click en una conversación) ═══╗ --}}
    @if($this->conversacionFoco)
        @php $cf = $this->conversacionFoco; $ef = $this->estadoFoco; @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             wire:click.self="cerrarFoco">
            <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">

                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-violet-50 to-white px-6 py-4">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-800">
                            <i class="fa-solid fa-magnifying-glass-chart text-violet-600"></i>
                            Detalle de conversación
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $cf->telefono_normalizado }} · {{ $cf->cliente?->nombre ?: ($ef?->nombre_cliente ?: '—') }}
                        </p>
                    </div>
                    <button wire:click="cerrarFoco" class="rounded-full w-9 h-9 inline-flex items-center justify-center text-slate-500 hover:bg-slate-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="grid md:grid-cols-2 gap-0 divide-x divide-slate-100">
                    {{-- Estado a la izquierda --}}
                    <div class="p-5 space-y-3">
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wide">📋 Estado del pedido</h4>
                        @if($ef)
                            <div class="text-xs space-y-1">
                                <p><strong>Paso:</strong> <span class="rounded bg-violet-100 text-violet-700 px-2 py-0.5 font-bold">{{ $ef->paso_actual }}</span></p>
                                <p><strong>Productos:</strong></p>
                                @forelse(($ef->productos ?: []) as $p)
                                    <div class="ml-4 text-slate-600">• {{ $p['quantity'] ?? '?' }} {{ $p['unit'] ?? '' }} {{ $p['name'] ?? '?' }}</div>
                                @empty
                                    <div class="ml-4 text-slate-400">—</div>
                                @endforelse
                                <p class="mt-2"><strong>Método:</strong> {{ $ef->metodo_entrega ?: '—' }}</p>
                                @if($ef->metodo_entrega === 'domicilio')
                                    <p><strong>Dirección:</strong> {{ $ef->direccion ?: '—' }}</p>
                                    <p><strong>Cobertura:</strong> {{ $ef->cobertura_validada ? '✅' : '❌' }}</p>
                                @elseif($ef->metodo_entrega === 'recoger')
                                    <p><strong>Sede:</strong> {{ $ef->sede?->nombre ?: '—' }}</p>
                                @endif
                                <p class="mt-2"><strong>Cédula:</strong> {{ $ef->cedula ?: '—' }}</p>
                                <p><strong>Nombre:</strong> {{ $ef->nombre_cliente ?: '—' }}</p>
                                @if($ef->pedido_id)
                                    <p class="mt-2 rounded bg-emerald-50 border border-emerald-300 p-2">
                                        <strong>✅ Pedido #{{ $ef->pedido_id }}</strong>
                                        creado el {{ $ef->confirmado_at?->format('d/m/Y H:i') }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <p class="text-xs text-slate-400 italic">Sin estado de pedido asociado</p>
                        @endif
                    </div>

                    {{-- Últimos mensajes a la derecha --}}
                    <div class="p-5">
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">💬 Últimos 15 mensajes</h4>
                        <div class="space-y-2 text-xs max-h-96 overflow-y-auto">
                            @foreach($cf->mensajes->reverse() as $m)
                                <div class="rounded-lg p-2 {{ $m->rol === 'user' ? 'bg-blue-50' : ($m->rol === 'assistant' ? 'bg-emerald-50' : 'bg-slate-50') }}">
                                    <div class="text-[9px] font-bold uppercase {{ $m->rol === 'user' ? 'text-blue-700' : ($m->rol === 'assistant' ? 'text-emerald-700' : 'text-slate-500') }}">
                                        {{ $m->rol }} · {{ $m->created_at?->format('H:i:s') }}
                                    </div>
                                    <div class="text-slate-700 mt-0.5">
                                        {{ \Illuminate\Support\Str::limit($m->contenido ?? '', 200) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-3">
                    <a href="{{ route('chat.index') }}?conv={{ $cf->id }}"
                       class="rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 text-xs font-bold transition mr-2">
                        Ir al chat completo
                    </a>
                    <button wire:click="cerrarFoco"
                            class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
