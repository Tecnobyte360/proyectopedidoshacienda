<div class="min-h-screen bg-slate-50" wire:poll.3s="refrescar"
     x-data="pedidosNotif()"
     x-init="init()">

    {{-- Contenedor responsivo que NO sobrepasa el padding del layout --}}
    <div class="max-w-full overflow-x-hidden">

    {{-- 🚀 BARRA FLOTANTE — aparece cuando hay pedidos seleccionados para despacho masivo --}}
    @php $cantSel = collect($seleccionadosMasivo)->filter()->count(); @endphp
    @if($cantSel > 0)
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40">
            <div class="flex items-center gap-3 rounded-2xl bg-slate-900 text-white shadow-2xl px-5 py-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand font-bold">{{ $cantSel }}</span>
                <span class="text-sm font-semibold">pedido(s) seleccionado(s)</span>
                <button wire:click="limpiarSeleccionMasiva" class="ml-2 text-xs text-slate-400 hover:text-white">
                    Cancelar
                </button>
                <button wire:click="abrirModalMasivo"
                        class="ml-2 inline-flex items-center gap-2 rounded-xl bg-brand hover:bg-brand-dark px-4 py-2 text-sm font-bold transition shadow">
                    <i class="fa-solid fa-motorcycle"></i>
                    Despachar selección
                </button>
            </div>
        </div>
    @endif

    {{-- 🗺️ MODAL DESPACHO MASIVO POR ZONA --}}
    @if($modalMasivoAbierto)
        @php
            $gruposM = $this->seleccionadosMasivoPorZona;
            $domsLista = \App\Models\Domiciliario::where('activo', true)
                ->orderByRaw("CASE estado WHEN 'disponible' THEN 0 WHEN 'ocupado' THEN 1 ELSE 2 END")
                ->orderBy('nombre')->with('zonas')->get();
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 overflow-y-auto"
             wire:click.self="cerrarModalMasivo">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8" @click.stop>
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">
                            <i class="fa-solid fa-rocket text-violet-500"></i>
                            Despacho masivo
                        </h3>
                        <p class="text-xs text-slate-500">{{ $cantSel }} pedido(s) en {{ $gruposM->count() }} zona(s)</p>
                    </div>
                    <button wire:click="cerrarModalMasivo" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                    @if($gruposM->count() > 1)
                        <div class="rounded-xl bg-violet-50 border border-violet-200 p-3 text-sm text-violet-800">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            Tienes pedidos de <b>{{ $gruposM->count() }} zonas distintas</b>. Asigna un domiciliario por zona.
                        </div>
                    @endif

                    @foreach($gruposM as $zonaId => $grupo)
                        @php
                            $keyG = $zonaId ?: 0;
                            $nombreZ = $grupo['zona']?->nombre ?? 'Sin zona';
                            $colorZ = $grupo['zona']?->color ?? '#94a3b8';
                            $domsZona = $grupo['zona']
                                ? $domsLista->filter(fn($d) => $d->zonas->contains('id', $zonaId))
                                : collect();
                        @endphp
                        <div class="rounded-2xl border-2 border-slate-200 overflow-hidden">
                            <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 border-b border-slate-200">
                                <div class="w-3 h-10 rounded-full" style="background: {{ $colorZ }}"></div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-800">
                                        <i class="fa-solid fa-location-dot text-rose-500"></i> {{ $nombreZ }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ $grupo['pedidos']->count() }} pedido(s) · ${{ number_format($grupo['total'], 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                            <div class="px-4 py-3 space-y-2">
                                <label class="block text-xs font-semibold text-slate-600">Domiciliario:</label>
                                <select wire:model="domiciliariosPorZonaMasivo.{{ $keyG }}"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-brand">
                                    <option value="">— Selecciona —</option>
                                    @if($domsZona->isNotEmpty())
                                        <optgroup label="✓ Cubren esta zona">
                                            @foreach($domsZona as $d)
                                                <option value="{{ $d->id }}">{{ $d->nombre }} ({{ ucfirst($d->estado) }})</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Otros">
                                            @foreach($domsLista->whereNotIn('id', $domsZona->pluck('id')) as $d)
                                                <option value="{{ $d->id }}">{{ $d->nombre }} ({{ ucfirst($d->estado) }})</option>
                                            @endforeach
                                        </optgroup>
                                    @else
                                        @foreach($domsLista as $d)
                                            <option value="{{ $d->id }}">{{ $d->nombre }} ({{ ucfirst($d->estado) }})</option>
                                        @endforeach
                                    @endif
                                </select>
                                <div class="text-xs text-slate-500 space-y-0.5 pt-1">
                                    @foreach($grupo['pedidos'] as $p)
                                        <div>• #{{ $p->id }} {{ $p->cliente_nombre }} — {{ $p->direccion }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-3 p-4 border-t border-slate-100 bg-slate-50">
                    <button type="button" wire:click="cerrarModalMasivo"
                            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmarDespachoMasivo"
                            wire:confirm="¿Confirmar el despacho masivo?"
                            class="rounded-xl bg-violet-600 hover:bg-violet-700 px-6 py-2.5 text-sm font-bold text-white shadow">
                        <i class="fa-solid fa-paper-plane mr-2"></i>
                        Despachar {{ $cantSel }} pedido(s)
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="w-full px-3 py-4 sm:px-6 sm:py-6 lg:px-8">

        @php
            // Los KPIs respetan zona + tipoEntrega pero NO el filtro de estado
            // (porque los KPIs son justamente las pestañas de estado).
            $pedidos          = $this->pedidosBase;
            $pedidosFiltrados = $this->pedidosFiltrados;

            $todos       = $pedidos->count();
            // 'confirmado' es legacy → cuenta como nuevo
            $nuevos      = $pedidos->whereIn('estado', [\App\Models\Pedido::ESTADO_NUEVO, 'confirmado'])->count();
            $enProceso   = $pedidos->where('estado', \App\Models\Pedido::ESTADO_EN_PREPARACION)->count();
            $despachados = $pedidos->where('estado', \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)->count();
            $entregados  = $pedidos->where('estado', \App\Models\Pedido::ESTADO_ENTREGADO)->count();
            $cancelados  = $pedidos->where('estado', \App\Models\Pedido::ESTADO_CANCELADO)->count();
            // 📅 Programados: tienen fecha programada y aún no se han entregado/cancelado
            $programados = $pedidos->filter(fn ($p) =>
                !empty($p->programado_para)
                && !in_array($p->estado, [\App\Models\Pedido::ESTADO_ENTREGADO, \App\Models\Pedido::ESTADO_CANCELADO], true)
            )->count();

            $estadosMeta = [
                \App\Models\Pedido::ESTADO_NUEVO => [
                    'label' => 'Nuevo',
                    'icon'  => 'fa-bell',
                    'badge' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'dot'   => 'bg-blue-500',
                    'btn'   => 'bg-blue-600',
                ],
                \App\Models\Pedido::ESTADO_EN_PREPARACION => [
                    'label' => 'En proceso',
                    'icon'  => 'fa-gears',
                    'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'dot'   => 'bg-amber-500',
                    'btn'   => 'bg-amber-500',
                ],
                \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO => [
                    'label' => 'Despachado',
                    'icon'  => 'fa-motorcycle',
                    'badge' => 'bg-violet-50 text-violet-700 border-violet-200',
                    'dot'   => 'bg-violet-500',
                    'btn'   => 'bg-violet-600',
                ],
                \App\Models\Pedido::ESTADO_ENTREGADO => [
                    'label' => 'Entregado',
                    'icon'  => 'fa-circle-check',
                    'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'dot'   => 'bg-emerald-500',
                    'btn'   => 'bg-emerald-600',
                ],
                \App\Models\Pedido::ESTADO_CANCELADO => [
                    'label' => 'Cancelado',
                    'icon'  => 'fa-ban',
                    'badge' => 'bg-rose-50 text-rose-700 border-rose-200',
                    'dot'   => 'bg-rose-500',
                    'btn'   => 'bg-rose-600',
                ],
            ];
        @endphp

        {{-- HEADER --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="h-1" style="background: linear-gradient(to right, var(--brand-primary), var(--brand-secondary));"></div>

            <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    <div class="flex h-11 w-11 sm:h-12 sm:w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-md"
                         style="background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));">
                        <i class="fa-solid fa-bag-shopping text-base sm:text-lg"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="inline-flex items-center gap-2 rounded-full bg-brand-soft px-2.5 py-1 text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-brand-secondary ring-1 ring-brand/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand animate-pulse"></span>
                            En tiempo real
                        </div>
                        <h2 class="mt-1.5 text-lg sm:text-xl md:text-2xl font-bold tracking-tight text-slate-800">
                            Gestión de Pedidos
                        </h2>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full lg:w-auto">
                    <div class="flex-1 lg:max-w-md">
                        @livewire('whatsapp-status-monitor')
                    </div>

                    {{-- Botón expandir / contraer pantalla completa --}}
                    <button type="button"
                            onclick="window.toggleFullscreen && window.toggleFullscreen()"
                            title="Pantalla completa (ESC para salir)"
                            class="shrink-0 flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-brand transition shadow-sm">
                        <i class="fa-solid fa-expand"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- 🧍 ALERTA: conversaciones esperando atención humana --}}
        @if($handoffTotal > 0)
            <div x-data="{ abierto: false }"
                 class="mt-3 rounded-2xl border-2 border-amber-300 bg-gradient-to-r from-amber-50 to-orange-50 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="shrink-0 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-white text-base animate-pulse">
                            <i class="fa-solid fa-user-headset"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-amber-900">
                                {{ $handoffTotal }} conversación{{ $handoffTotal === 1 ? '' : 'es' }} esperando atención humana
                            </p>
                            <p class="text-xs text-amber-700">
                                El bot derivó al humano. Revisa qué necesitan estos clientes antes de que abandonen.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="abierto = !abierto"
                                class="text-xs font-semibold text-amber-800 hover:text-amber-900 inline-flex items-center gap-1">
                            <span x-text="abierto ? 'Ocultar' : 'Ver lista'"></span>
                            <i class="fa-solid" :class="abierto ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        </button>
                        <a href="{{ route('chat.index') }}"
                           class="rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold px-3 py-2 shadow-sm inline-flex items-center gap-1">
                            <i class="fa-solid fa-comments"></i> Ir al chat
                        </a>
                    </div>
                </div>

                <div x-show="abierto" x-collapse class="border-t border-amber-200 bg-white/60">
                    <ul class="divide-y divide-amber-100">
                        @foreach($handoffPendientes as $conv)
                            @php
                                $nombreCliente = $conv->cliente?->nombre ?: 'Cliente';
                                $telCliente    = $conv->cliente?->telefono_normalizado ?: $conv->telefono_normalizado;
                                $depto         = $conv->departamento_id
                                    ? \App\Models\Departamento::find($conv->departamento_id)?->nombre
                                    : null;
                                $hace          = $conv->derivada_at
                                    ? $conv->derivada_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)
                                    : '—';
                            @endphp
                            <li class="flex items-center justify-between gap-3 px-4 py-2 hover:bg-amber-50/50">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="shrink-0 flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700 text-xs">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 truncate">{{ $nombreCliente }}</p>
                                        <p class="text-[11px] text-slate-500">
                                            <span class="font-mono">{{ $telCliente }}</span>
                                            @if($depto)
                                                · <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">{{ $depto }}</span>
                                            @endif
                                            · hace {{ $hace }}
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ route('chat.index') }}?conv={{ $conv->id }}"
                                   class="shrink-0 rounded-lg border border-amber-300 bg-white hover:bg-amber-50 text-amber-800 text-xs font-semibold px-3 py-1.5 inline-flex items-center gap-1">
                                    <i class="fa-solid fa-reply"></i> Atender
                                </a>
                            </li>
                        @endforeach
                        @if($handoffTotal > $handoffPendientes->count())
                            <li class="px-4 py-2 text-center text-xs text-slate-500">
                                Y {{ $handoffTotal - $handoffPendientes->count() }} más…
                                <a href="{{ route('chat.index') }}" class="text-amber-700 font-semibold hover:underline">Ver todas</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        @endif

        {{-- KPIS — etiquetas/iconos cambian según si el filtro es "recoger" --}}
        @php
            $modoPickup = $tipoEntrega === 'recoger';
            $lblEnProceso  = $modoPickup ? 'En preparación' : 'En proceso';
            $lblDespachado = $modoPickup ? 'Listos p/ recoger' : 'Despachados';
            $iconDespachado= $modoPickup ? 'fa-bag-shopping' : 'fa-motorcycle';
            $iconBgDesp    = $modoPickup ? 'bg-orange-50 text-orange-600' : 'bg-violet-50 text-violet-600';
            $barDesp       = $modoPickup ? 'bg-orange-500' : 'bg-violet-500';
        @endphp
        <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['label' => 'Nuevos',         'value' => $nuevos,      'icon' => 'fa-bell',           'iconBg' => 'bg-blue-50 text-blue-600',       'bar' => 'bg-blue-500'],
                ['label' => 'Programados',    'value' => $programados, 'icon' => 'fa-calendar-check', 'iconBg' => 'bg-cyan-50 text-cyan-600',       'bar' => 'bg-cyan-500'],
                ['label' => $lblEnProceso,    'value' => $enProceso,   'icon' => 'fa-gears',          'iconBg' => 'bg-amber-50 text-amber-600',     'bar' => 'bg-amber-500'],
                ['label' => $lblDespachado,   'value' => $despachados, 'icon' => $iconDespachado,     'iconBg' => $iconBgDesp,                      'bar' => $barDesp],
                ['label' => 'Entregados',     'value' => $entregados,  'icon' => 'fa-circle-check',   'iconBg' => 'bg-emerald-50 text-emerald-600', 'bar' => 'bg-emerald-500'],
                ['label' => 'Cancelados',     'value' => $cancelados,  'icon' => 'fa-ban',            'iconBg' => 'bg-rose-50 text-rose-600',       'bar' => 'bg-rose-500'],
            ] as $kpi)
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 truncate">
                                {{ $kpi['label'] }}
                            </p>
                            <h3 class="mt-1 text-xl sm:text-2xl font-bold leading-none text-slate-900">
                                {{ $kpi['value'] }}
                            </h3>
                        </div>
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $kpi['iconBg'] }}">
                            <i class="fa-solid {{ $kpi['icon'] }} text-xs"></i>
                        </div>
                    </div>
                    <div class="mt-2 h-1 w-8 rounded-full {{ $kpi['bar'] }}"></div>
                </div>
            @endforeach
        </div>

        {{-- FILTROS --}}
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">

                {{-- Tabs scrollables horizontal --}}
                <div class="flex-1 min-w-0 -mx-3 sm:-mx-4 lg:mx-0 px-3 sm:px-4 lg:px-0 overflow-x-auto pb-1 scrollbar-hide">
                    <div class="flex gap-2 min-w-max">
                        @php
                            $tabs = [
                                ['key' => 'todos',                                              'label' => 'Todos',           'count' => $todos,       'icon' => 'fa-table-cells-large', 'active' => 'bg-slate-900 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_NUEVO,                    'label' => 'Nuevos',          'count' => $nuevos,      'icon' => 'fa-bell',              'active' => 'bg-blue-600 text-white'],
                                ['key' => 'programados',                                        'label' => 'Programados',     'count' => $programados, 'icon' => 'fa-calendar-check',    'active' => 'bg-cyan-600 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_EN_PREPARACION,           'label' => $lblEnProceso,     'count' => $enProceso,   'icon' => 'fa-gears',             'active' => 'bg-amber-500 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO,     'label' => $lblDespachado,    'count' => $despachados, 'icon' => $iconDespachado,        'active' => $modoPickup ? 'bg-orange-600 text-white' : 'bg-violet-600 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_ENTREGADO,                'label' => 'Entregados',      'count' => $entregados,  'icon' => 'fa-circle-check',      'active' => 'bg-emerald-600 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_CANCELADO,                'label' => 'Cancelados',      'count' => $cancelados,  'icon' => 'fa-ban',               'active' => 'bg-rose-600 text-white'],
                            ];
                        @endphp

                        @foreach($tabs as $tab)
                            <button type="button" wire:click="cambiarTab('{{ $tab['key'] }}')"
                                    class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl px-3 sm:px-4 text-xs sm:text-sm font-semibold transition
                                           {{ $estado === $tab['key']
                                               ? $tab['active'] . ' shadow-sm'
                                               : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                <i class="fa-solid {{ $tab['icon'] }} text-[10px] sm:text-xs"></i>
                                <span>{{ $tab['label'] }}</span>
                                <span class="rounded-full px-1.5 py-0.5 text-[10px]
                                             {{ $estado === $tab['key'] ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $tab['count'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- 🚚 Filtros secundarios (tipo entrega + zona) - en su propia fila para no competir con los tabs --}}
                @php
                    // Contadores por tipo de entrega — sobre TODOS los pedidos del tenant
                    // (no respetan el filtro de tipoEntrega para que muestre el total global de cada uno)
                    $todosLosPedidos = $this->pedidos;
                    $totalAll        = $todosLosPedidos->count();
                    $totalDomicilio  = $todosLosPedidos->filter(fn ($p) => ($p->tipo_entrega ?? 'domicilio') === 'domicilio')->count();
                    $totalRecoger    = $todosLosPedidos->filter(fn ($p) => ($p->tipo_entrega ?? 'domicilio') === 'recoger')->count();
                @endphp
                <div class="flex flex-col sm:flex-row gap-2 lg:basis-full lg:order-3 lg:mt-2">
                    <div class="relative flex-1 min-w-0 sm:max-w-[260px]">
                        <i class="fa-solid fa-truck absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                        <select wire:model.live="tipoEntrega"
                                class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-9 text-sm font-medium text-slate-700 focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20">
                            <option value="todos">Todos ({{ $totalAll }})</option>
                            <option value="domicilio">🛵 Despacho ({{ $totalDomicilio }})</option>
                            <option value="recoger">🏪 Recoge en sede ({{ $totalRecoger }})</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    </div>

                    <div class="relative flex-1 min-w-0 sm:max-w-[220px]">
                        <i class="fa-solid fa-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                        <select wire:model.live="zona"
                                class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-9 text-sm font-medium text-slate-700 focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20">
                            <option value="todas">Todas las zonas</option>
                            @foreach($zonasDisponibles as $z)
                                <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                            @endforeach
                            <option value="sin_zona">Sin zona asignada</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- CONTADOR DE RESULTADOS + DESGLOSE POR TIPO ENTREGA --}}
        <div class="mt-3 flex items-center justify-between gap-3 px-1 flex-wrap">
            <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <i class="fa-solid fa-database text-[10px]"></i>
                {{ $pedidosFiltrados->count() }} {{ Str::plural('pedido', $pedidosFiltrados->count()) }}
            </div>

            {{-- 🚚 Desglose visual: cuántos despacho vs cuántos recoge --}}
            <div class="inline-flex items-center gap-2 flex-wrap">
                <button type="button" wire:click="$set('tipoEntrega', 'domicilio')"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold transition
                               {{ $tipoEntrega === 'domicilio' ? 'bg-sky-600 text-white shadow' : 'bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100' }}">
                    <i class="fa-solid fa-motorcycle text-[10px]"></i>
                    Despacho
                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full text-[10px] font-bold
                                 {{ $tipoEntrega === 'domicilio' ? 'bg-white/25 text-white' : 'bg-sky-200 text-sky-800' }}">
                        {{ $totalDomicilio }}
                    </span>
                </button>

                <button type="button" wire:click="$set('tipoEntrega', 'recoger')"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold transition
                               {{ $tipoEntrega === 'recoger' ? 'bg-amber-600 text-white shadow' : 'bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100' }}">
                    <i class="fa-solid fa-store text-[10px]"></i>
                    Recoge en sede
                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full text-[10px] font-bold
                                 {{ $tipoEntrega === 'recoger' ? 'bg-white/25 text-white' : 'bg-amber-200 text-amber-800' }}">
                        {{ $totalRecoger }}
                    </span>
                </button>

                @if($tipoEntrega !== 'todos')
                    <button type="button" wire:click="$set('tipoEntrega', 'todos')"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-medium text-slate-500 hover:bg-slate-100"
                            title="Limpiar filtro">
                        <i class="fa-solid fa-xmark text-[9px]"></i>
                        Limpiar
                    </button>
                @endif
            </div>
        </div>

        {{-- ╔═══ CARDS para móvil/tablet (< lg) ═══╗ --}}
        {{-- Responsivo: 1 col en mobile chico, 2 cols en sm, 2 cols hasta lg --}}
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:hidden">
            @forelse($pedidosFiltrados as $pedido)
                @php
                    $meta = $estadosMeta[$pedido->estado] ?? null;
                    $iniciales = collect(explode(' ', trim($pedido->cliente_nombre ?? 'CL')))
                        ->filter()->take(2)
                        ->map(fn($p) => mb_substr($p, 0, 1))
                        ->implode('');
                @endphp

                <div data-pedido-id="{{ $pedido->id }}"
                     class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden
                            {{ $pedido->estado === \App\Models\Pedido::ESTADO_CANCELADO ? 'opacity-75' : '' }}">

                    {{-- Header del card --}}
                    <div class="flex items-start justify-between gap-3 p-4 border-b border-slate-100">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white text-sm font-bold">
                                {{ $iniciales ?: 'CL' }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-[10px] font-mono text-slate-400">
                                    PED-{{ str_pad($pedido->id, 3, '0', STR_PAD_LEFT) }}
                                </div>
                                <div class="font-bold text-slate-800 truncate flex items-center gap-1.5">
                                    {{ $pedido->cliente_nombre }}
                                    @if($pedido->estadoPedidoBot && $pedido->estadoPedidoBot->cliente_existe_erp)
                                        <span class="inline-flex items-center gap-0.5 rounded bg-emerald-100 px-1 py-0.5 text-[8px] font-bold uppercase text-emerald-700 shrink-0"
                                              title="Verificado en SGI">
                                            <i class="fa-solid fa-building text-[7px]"></i>SGI
                                        </span>
                                    @elseif($pedido->estadoPedidoBot && $pedido->estadoPedidoBot->cedula && !$pedido->estadoPedidoBot->cliente_existe_erp)
                                        <span class="inline-flex items-center gap-0.5 rounded bg-amber-100 px-1 py-0.5 text-[8px] font-bold uppercase text-amber-700 shrink-0"
                                              title="No encontrado en SGI">
                                            <i class="fa-solid fa-building text-[7px]"></i>Nuevo
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500">{{ $pedido->created_at?->diffForHumans() }}</div>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="rounded-xl bg-slate-900 px-2.5 py-1 text-sm font-bold text-white shadow-sm">
                                ${{ number_format($pedido->total, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    {{-- Detalles --}}
                    <div class="p-4 space-y-2">

                        {{-- Estado + Semáforo ANS --}}
                        <div class="flex flex-wrap items-center gap-2">
                            @if($meta)
                                <span class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider {{ $meta['badge'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>
                                    <i class="fa-solid {{ $meta['icon'] }} text-[10px]"></i>
                                    {{ $meta['label'] }}
                                </span>
                            @endif

                            {{-- 📅 Badge pedido programado --}}
                            @if($pedido->programado_para)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-cyan-200 bg-cyan-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-cyan-700"
                                      title="Pedido programado para {{ $pedido->programado_para->format('d/m/Y H:i') }}">
                                    <i class="fa-solid fa-calendar-check text-[10px]"></i>
                                    Programado · {{ $pedido->programado_para->format('d/m H:i') }}
                                </span>
                            @endif

                            @include('livewire.pedidos._semaforo', ['pedido' => $pedido, 'size' => 'sm'])
                        </div>

                        {{-- Barra ANS --}}
                        <div class="pt-1">
                            @include('livewire.pedidos._semaforo', ['pedido' => $pedido, 'modo' => 'barra'])
                        </div>

                        {{-- 🚚 Tipo de entrega: Despacho vs Recoger en sede --}}
                        @php $esRecoger = ($pedido->tipo_entrega ?? 'domicilio') === 'recoger'; @endphp
                        <div class="mt-2">
                            @if($esRecoger)
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                                    <i class="fa-solid fa-store"></i>
                                    Recoge en sede{{ $pedido->hora_entrega ? ' · '.\Illuminate\Support\Carbon::parse($pedido->hora_entrega)->format('h:i a') : '' }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-sky-700">
                                    <i class="fa-solid fa-motorcycle"></i>
                                    Despacho a domicilio
                                </span>
                            @endif
                        </div>

                        {{-- Info en grid — campos varían según tipo de entrega --}}
                        <div class="grid grid-cols-1 gap-1.5 text-xs text-slate-600 mt-2">
                            @if($esRecoger)
                                {{-- ━━ RECOGE EN SEDE ━━ --}}
                                @if($pedido->sede)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-store w-4 text-center text-amber-500"></i>
                                        <span class="font-medium">Sede {{ $pedido->sede->nombre }}</span>
                                    </div>
                                    @if($pedido->sede->direccion)
                                        <div class="flex items-start gap-2">
                                            <i class="fa-solid fa-location-dot w-4 text-center text-slate-400 mt-0.5"></i>
                                            <span class="text-slate-500">{{ $pedido->sede->direccion }}</span>
                                        </div>
                                    @endif
                                @endif
                                @if($pedido->hora_entrega)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-regular fa-clock w-4 text-center text-amber-500"></i>
                                        <span class="font-medium">Recoge a las {{ \Illuminate\Support\Carbon::parse($pedido->hora_entrega)->format('h:i a') }}</span>
                                    </div>
                                @endif
                            @else
                                {{-- ━━ DESPACHO A DOMICILIO ━━ --}}
                                @if($pedido->zonaCobertura)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-map-location-dot w-4 text-center" style="color: {{ $pedido->zonaCobertura->color }}"></i>
                                        <span class="font-medium">{{ $pedido->zonaCobertura->nombre }}</span>
                                    </div>
                                @endif
                                @if($pedido->barrio)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-map-pin w-4 text-center text-slate-400"></i>
                                        <span>{{ $pedido->barrio }}</span>
                                    </div>
                                @endif
                                @if($pedido->direccion)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-location-dot w-4 text-center text-slate-400"></i>
                                        <span class="truncate">{{ $pedido->direccion }}</span>
                                    </div>
                                @endif
                                @if($pedido->domiciliario)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-motorcycle w-4 text-center text-violet-500"></i>
                                        <span class="font-medium text-violet-700">{{ $pedido->domiciliario->nombre }}</span>
                                    </div>
                                @endif
                                @if($pedido->token_entrega && $pedido->estado === \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-key w-4 text-center text-violet-500"></i>
                                        <span class="font-mono font-bold tracking-widest text-violet-700">{{ $pedido->token_entrega }}</span>
                                    </div>
                                @endif
                            @endif

                            {{-- Teléfono: aplica para ambos --}}
                            @if($pedido->telefono_whatsapp || $pedido->telefono)
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-phone w-4 text-center text-slate-400"></i>
                                    <span>{{ $pedido->telefono_whatsapp ?? $pedido->telefono }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- 🛒 Detalle de productos (mobile/card) --}}
                        @if($pedido->detalles && $pedido->detalles->count() > 0)
                            <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5 flex items-center justify-between">
                                    <span><i class="fa-solid fa-cart-shopping mr-1"></i>Productos ({{ $pedido->detalles->count() }})</span>
                                    <span class="text-slate-400 normal-case font-normal text-[9px]">Subtotal</span>
                                </div>
                                <div class="space-y-1">
                                    @foreach($pedido->detalles as $detalle)
                                        @php
                                            $cant = (float) ($detalle->cantidad ?? 1);
                                            $cantFmt = fmod($cant, 1) == 0 ? (int) $cant : rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.');
                                            $unit = $detalle->unidad ?? '';
                                            $unitLbl = in_array(strtolower($unit), ['kg','kl']) ? 'kg' : (in_array(strtolower($unit), ['lb','libra','libras']) ? 'lb' : (in_array(strtolower($unit), ['und','unidad']) ? '' : $unit));
                                        @endphp
                                        <div class="flex items-start justify-between gap-2 text-xs">
                                            <div class="flex items-start gap-1.5 flex-1 min-w-0">
                                                <span class="inline-flex items-center justify-center min-w-[32px] h-[18px] rounded bg-slate-900 text-white text-[10px] font-bold shrink-0 mt-0.5">
                                                    {{ $cantFmt }}{{ $unitLbl }}
                                                </span>
                                                <span class="text-slate-800 font-medium leading-tight">{{ $detalle->producto }}</span>
                                            </div>
                                            <span class="font-mono text-slate-700 text-[11px] font-semibold whitespace-nowrap">
                                                ${{ number_format((float) $detalle->subtotal, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Acción --}}
                    <div class="px-4 pb-4 space-y-2">
                        @include('livewire.pedidos._accion_pedido', ['pedido' => $pedido])
                        @if($pedido->canal === 'whatsapp')
                            <button type="button"
                                    wire:click="verConversacion({{ $pedido->id }})"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                                <i class="fa-solid fa-robot text-violet-500"></i>
                                Ver conversación IA
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-slate-400">
                        <i class="fa-solid fa-inbox text-xl"></i>
                    </div>
                    <h3 class="mt-3 text-base font-semibold text-slate-700">Sin pedidos</h3>
                    <p class="mt-1 text-sm text-slate-500">No hay pedidos con los filtros actuales.</p>
                </div>
            @endforelse
        </div>

        {{-- ╔═══ TABLA para desktop (>= lg) ═══╗ --}}
        {{-- Scroll horizontal cuando no caiga, sin forzar min-w para no romper el layout --}}
        <div class="mt-3 hidden lg:block rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto rounded-2xl">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 w-10">
                                <button type="button" wire:click="seleccionarTodosVisibles"
                                        title="Seleccionar todos los despachables visibles"
                                        class="text-slate-400 hover:text-brand transition">
                                    <i class="fa-solid fa-check-double text-sm"></i>
                                </button>
                            </th>
                            @php
                                // Tabla compacta: prioriza columnas críticas que SIEMPRE deben caber
                                // sin scroll en lg+. Datos secundarios (telefono, hora) viven en cards mobile.
                                $cols = [
                                    ['label' => 'Pedido',       'cls' => ''],
                                    ['label' => 'Cliente',      'cls' => ''],
                                    ['label' => 'Productos',    'cls' => 'hidden xl:table-cell'],
                                    ['label' => 'Zona/Sede',    'cls' => ''],
                                    ['label' => 'Estado',       'cls' => ''],
                                    ['label' => 'ANS',          'cls' => ''],
                                    ['label' => 'Total',        'cls' => ''],
                                    ['label' => 'Domiciliario', 'cls' => 'hidden xl:table-cell'],
                                    ['label' => 'Acción',       'cls' => 'text-center'],
                                ];
                            @endphp
                            @foreach($cols as $c)
                                <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap {{ $c['cls'] }}">
                                    {{ $c['label'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse($pedidosFiltrados as $pedido)
                            @php
                                $meta = $estadosMeta[$pedido->estado] ?? null;
                                $iniciales = collect(explode(' ', trim($pedido->cliente_nombre ?? 'CL')))
                                    ->filter()->take(2)
                                    ->map(fn($p) => mb_substr($p, 0, 1))
                                    ->implode('');
                            @endphp

                            @php
                                // Solo pedidos de domicilio son despachables (los de recoger no necesitan domiciliario)
                                $esDomicilio = ($pedido->tipo_entrega ?? 'domicilio') === 'domicilio';
                                $despachable = $esDomicilio && in_array($pedido->estado, [
                                    \App\Models\Pedido::ESTADO_NUEVO,
                                    \App\Models\Pedido::ESTADO_EN_PREPARACION,
                                    'confirmado',
                                ], true);
                                $estaSeleccionado = !empty($seleccionadosMasivo[$pedido->id]);
                            @endphp

                            <tr data-pedido-id="{{ $pedido->id }}"
                                class="transition hover:bg-slate-50
                                       {{ $pedido->estado === \App\Models\Pedido::ESTADO_CANCELADO ? 'opacity-75' : '' }}
                                       {{ $estaSeleccionado ? 'bg-amber-50' : '' }}">

                                {{-- Checkbox de selección masiva (solo para despachables) --}}
                                <td class="px-3 py-3.5 align-middle">
                                    @if($despachable)
                                        <input type="checkbox"
                                               wire:click="toggleSeleccionMasiva({{ $pedido->id }})"
                                               @checked($estaSeleccionado)
                                               class="rounded border-slate-300 text-brand focus:ring-brand cursor-pointer">
                                    @endif
                                </td>

                                {{-- Pedido --}}
                                <td class="px-3 py-3.5 align-middle">
                                    <div class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-700 whitespace-nowrap">
                                        <i class="fa-solid fa-hashtag text-[9px] text-slate-400"></i>
                                        {{ str_pad($pedido->id, 3, '0', STR_PAD_LEFT) }}
                                    </div>
                                </td>

                                {{-- Cliente --}}
                                <td class="px-3 py-3.5 align-middle">
                                    @php
                                        $esRecogerRow = ($pedido->tipo_entrega ?? 'domicilio') === 'recoger';
                                        $horaEntregaFmt = $pedido->hora_entrega
                                            ? \Illuminate\Support\Carbon::parse($pedido->hora_entrega)->format('h:i a')
                                            : null;
                                    @endphp
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">
                                            {{ $iniciales ?: 'CL' }}
                                        </div>
                                        <div class="min-w-0 max-w-[200px]">
                                            <div class="truncate font-semibold text-slate-900 text-sm flex items-center gap-1.5">
                                                {{ $pedido->cliente_nombre }}
                                                @if($pedido->estadoPedidoBot && $pedido->estadoPedidoBot->cliente_existe_erp)
                                                    <span class="inline-flex items-center gap-0.5 rounded bg-emerald-100 px-1 py-0.5 text-[8px] font-bold uppercase text-emerald-700 whitespace-nowrap shrink-0"
                                                          title="Cliente verificado en SGI (ERP)">
                                                        <i class="fa-solid fa-building text-[7px]"></i>SGI
                                                    </span>
                                                @elseif($pedido->estadoPedidoBot && $pedido->estadoPedidoBot->cedula && !$pedido->estadoPedidoBot->cliente_existe_erp)
                                                    <span class="inline-flex items-center gap-0.5 rounded bg-amber-100 px-1 py-0.5 text-[8px] font-bold uppercase text-amber-700 whitespace-nowrap shrink-0"
                                                          title="Cédula dada pero NO encontrado en SGI">
                                                        <i class="fa-solid fa-building text-[7px]"></i>Nuevo
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-slate-500 truncate flex items-center gap-1 flex-wrap">
                                                @if($esRecogerRow)
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-700 whitespace-nowrap"
                                                          title="Cliente recoge en sede{{ $horaEntregaFmt ? ' a las '.$horaEntregaFmt : '' }}">
                                                        <i class="fa-solid fa-store text-[8px]"></i>RECOGE
                                                    </span>
                                                    @if($horaEntregaFmt)
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 whitespace-nowrap"
                                                              title="Hora de recogida">
                                                            <i class="fa-regular fa-clock text-[8px]"></i>{{ $horaEntregaFmt }}
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-sky-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-sky-700 whitespace-nowrap"
                                                          title="Despacho a domicilio">
                                                        <i class="fa-solid fa-motorcycle text-[8px]"></i>DESPACHO
                                                    </span>
                                                    @if($horaEntregaFmt)
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 border border-sky-200 px-1.5 py-0.5 text-[9px] font-bold text-sky-700 whitespace-nowrap"
                                                              title="Hora estimada de entrega">
                                                            <i class="fa-regular fa-clock text-[8px]"></i>{{ $horaEntregaFmt }}
                                                        </span>
                                                    @endif
                                                @endif
                                                <span class="truncate">· {{ $pedido->created_at?->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- 🛒 Productos del pedido (visible desde xl, antes era 2xl) --}}
                                <td class="px-3 py-3.5 align-middle hidden xl:table-cell relative">
                                    @php
                                        $detalles    = $pedido->detalles ?? collect();
                                        $totalLineas = $detalles->count();
                                        $totalPedido = $detalles->sum('subtotal');
                                    @endphp

                                    @if($totalLineas > 0)
                                        {{-- 🎯 Trigger: chip compacto que muestra resumen.
                                              Al hover → muestra tooltip rico con tabla completa de productos. --}}
                                        <div class="min-w-0 max-w-[260px] relative group"
                                             x-data="{ hover: false }"
                                             @mouseenter="hover = true"
                                             @mouseleave="hover = false">

                                            {{-- Chip resumen visible siempre --}}
                                            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 cursor-help hover:bg-brand-soft hover:border-brand/30 transition">
                                                <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] rounded-lg bg-brand text-white text-[10px] font-bold">
                                                    {{ $totalLineas }}
                                                </span>
                                                <div class="flex flex-col">
                                                    <span class="text-[11px] font-semibold text-slate-700 leading-tight">
                                                        {{ $totalLineas === 1 ? 'producto' : 'productos' }}
                                                    </span>
                                                    <span class="text-[10px] text-slate-500 leading-tight">
                                                        Pasa el cursor 👆
                                                    </span>
                                                </div>
                                                <i class="fa-solid fa-eye text-[10px] text-slate-400 ml-1"></i>
                                            </div>

                                            {{-- 📋 Tooltip rico con tabla — aparece al hover --}}
                                            <div x-show="hover"
                                                 x-cloak
                                                 x-transition:enter="transition ease-out duration-150"
                                                 x-transition:enter-start="opacity-0 translate-y-1"
                                                 x-transition:enter-end="opacity-100 translate-y-0"
                                                 x-transition:leave="transition ease-in duration-100"
                                                 x-transition:leave-start="opacity-100"
                                                 x-transition:leave-end="opacity-0"
                                                 class="absolute z-50 left-0 top-full mt-2 w-[380px] max-w-[90vw] rounded-xl border border-slate-200 bg-white shadow-2xl pointer-events-none">

                                                {{-- Header del tooltip --}}
                                                <div class="px-3 py-2 border-b border-slate-100 bg-gradient-to-r from-brand/5 to-brand-secondary/5 rounded-t-xl">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold text-slate-700">
                                                            <i class="fa-solid fa-cart-shopping text-brand mr-1"></i>
                                                            Detalle del pedido #{{ $pedido->id }}
                                                        </span>
                                                        <span class="text-[10px] text-slate-500">
                                                            {{ $totalLineas }} {{ $totalLineas === 1 ? 'ítem' : 'ítems' }}
                                                        </span>
                                                    </div>
                                                </div>

                                                {{-- Tabla de productos --}}
                                                <div class="max-h-[280px] overflow-y-auto">
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-slate-50 sticky top-0">
                                                            <tr class="text-left">
                                                                <th class="px-3 py-1.5 text-[10px] font-bold uppercase text-slate-500 tracking-wider">Cant.</th>
                                                                <th class="px-2 py-1.5 text-[10px] font-bold uppercase text-slate-500 tracking-wider">Producto</th>
                                                                <th class="px-3 py-1.5 text-[10px] font-bold uppercase text-slate-500 tracking-wider text-right">Subtotal</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-100">
                                                            @foreach($detalles as $d)
                                                                @php
                                                                    $cant = (float) ($d->cantidad ?? 1);
                                                                    $cantFmt = fmod($cant, 1) == 0 ? (int) $cant : rtrim(rtrim(number_format($cant, 3, '.', ''), '0'), '.');
                                                                    $unit = $d->unidad ?? '';
                                                                    $unitDisplay = in_array(strtolower($unit), ['kg','kl']) ? 'kg' : (in_array(strtolower($unit), ['lb','libra','libras']) ? 'lb' : $unit);
                                                                    $sub  = number_format((float) $d->subtotal, 0, ',', '.');
                                                                    $precioUnit = number_format((float) ($d->precio_unitario ?? 0), 0, ',', '.');
                                                                @endphp
                                                                <tr class="hover:bg-slate-50/50">
                                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                                        <span class="inline-flex items-center justify-center min-w-[34px] h-6 rounded-md bg-slate-900 text-white text-[10px] font-bold">
                                                                            {{ $cantFmt }}{{ $unitDisplay ? ' '.$unitDisplay : '' }}
                                                                        </span>
                                                                    </td>
                                                                    <td class="px-2 py-2">
                                                                        <div class="font-medium text-slate-800 leading-tight">
                                                                            {{ $d->producto ?? 'Producto' }}
                                                                        </div>
                                                                        @if($d->precio_unitario && $cant > 1)
                                                                            <div class="text-[9px] text-slate-400 mt-0.5">
                                                                                ${{ $precioUnit }} c/u
                                                                            </div>
                                                                        @endif
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right whitespace-nowrap font-mono text-[11px] font-semibold text-slate-700">
                                                                        ${{ $sub }}
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                        <tfoot class="bg-slate-50 border-t border-slate-200">
                                                            <tr>
                                                                <td colspan="2" class="px-3 py-2 text-right text-[10px] font-bold uppercase text-slate-600 tracking-wider">
                                                                    Total productos
                                                                </td>
                                                                <td class="px-3 py-2 text-right whitespace-nowrap font-mono text-xs font-bold text-emerald-700">
                                                                    ${{ number_format((float) $totalPedido, 0, ',', '.') }}
                                                                </td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">Sin detalle</span>
                                    @endif
                                </td>

                                {{-- Zona / Sede (visible desde lg) --}}
                                <td class="px-3 py-3.5 align-middle">
                                    @if($esRecogerRow)
                                        <div class="flex flex-col gap-1">
                                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 whitespace-nowrap w-fit"
                                                  title="Cliente recoge en sede">
                                                <i class="fa-solid fa-store text-[10px]"></i>
                                                {{ $pedido->sede?->nombre ?? 'Sede' }}
                                            </span>
                                            @if($horaEntregaFmt)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 whitespace-nowrap">
                                                    <i class="fa-regular fa-clock text-[10px]"></i>
                                                    Recoge {{ $horaEntregaFmt }}
                                                </span>
                                            @endif
                                        </div>
                                    @elseif($pedido->zonaCobertura)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 whitespace-nowrap">
                                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $pedido->zonaCobertura->color }}"></span>
                                            {{ $pedido->zonaCobertura->nombre }}
                                        </span>
                                    @elseif($pedido->barrio)
                                        <span class="text-xs text-slate-500">{{ $pedido->barrio }}</span>
                                    @else
                                        <span class="text-xs text-slate-400 italic">—</span>
                                    @endif
                                </td>

                                {{-- Teléfono se eliminó de la tabla; visible en cards mobile y en detalle --}}

                                {{-- Estado --}}
                                <td class="px-3 py-3.5 align-middle">
                                    <div class="flex flex-col gap-1">
                                        @if($meta)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap {{ $meta['badge'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>
                                                {{ $meta['label'] }}
                                            </span>
                                        @endif
                                        @if($pedido->programado_para)
                                            <span class="inline-flex items-center gap-1 rounded-full border border-cyan-200 bg-cyan-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-cyan-700 whitespace-nowrap"
                                                  title="Programado para {{ $pedido->programado_para->format('d/m/Y H:i') }}">
                                                <i class="fa-solid fa-calendar-check text-[9px]"></i>
                                                {{ $pedido->programado_para->format('d/m H:i') }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Semáforo ANS — siempre visible desde lg --}}
                                <td class="px-3 py-3.5 align-middle">
                                    <div class="w-[100px]">
                                        @include('livewire.pedidos._semaforo', ['pedido' => $pedido, 'modo' => 'barra'])
                                    </div>
                                </td>
                                {{-- Hora se eliminó de la tabla --}}

                                {{-- Total --}}
                                <td class="px-3 py-3.5 align-middle">
                                    <span class="inline-flex rounded-lg bg-slate-900 px-2 py-1 text-xs font-bold text-white shadow-sm whitespace-nowrap">
                                        ${{ number_format($pedido->total, 0, ',', '.') }}
                                    </span>
                                </td>

                                {{-- Domiciliario / Recoge (xl+) --}}
                                <td class="px-3 py-3.5 align-middle hidden xl:table-cell">
                                    @if($esRecogerRow)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-700 whitespace-nowrap"
                                              title="No requiere domiciliario — el cliente recoge">
                                            <i class="fa-solid fa-store text-[10px]"></i>
                                            No aplica
                                        </span>
                                    @elseif($pedido->domiciliario)
                                        <div class="min-w-0 max-w-[120px]">
                                            <div class="truncate text-xs font-semibold text-slate-800">{{ $pedido->domiciliario->nombre }}</div>
                                            <div class="truncate text-[10px] text-slate-500">
                                                {{ $pedido->domiciliario->vehiculo ?? '' }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">—</span>
                                    @endif
                                </td>

                                {{-- Acción --}}
                                <td class="px-3 py-3.5 text-center align-middle">
                                    <div class="flex flex-col items-center gap-1.5">
                                        @include('livewire.pedidos._accion_pedido', ['pedido' => $pedido])
                                        @if($pedido->canal === 'whatsapp')
                                            <button type="button"
                                                    wire:click="verConversacion({{ $pedido->id }})"
                                                    title="Ver conversación con IA"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition">
                                                <i class="fa-solid fa-robot text-[10px] text-violet-500"></i>
                                                Chat IA
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-slate-400">
                                        <i class="fa-solid fa-inbox text-xl"></i>
                                    </div>
                                    <h3 class="mt-3 text-base font-semibold text-slate-700">Sin pedidos</h3>
                                    <p class="mt-1 text-sm text-slate-500">No hay pedidos con los filtros actuales.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- MODAL DESPACHO --}}
        @if($modalDespachoAbierto)
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4"
                 style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">

                <div class="w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white shadow-2xl max-h-[90vh] flex flex-col" @click.stop>
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4 sm:px-5 py-4 shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-slate-800">Asignar domiciliario</h3>
                            <p class="text-xs text-slate-500">Pedido #{{ str_pad($pedidoIdDespacho, 3, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <button wire:click="cerrarModalDespacho"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="px-4 sm:px-5 py-4 overflow-y-auto flex-1">
                        <p class="mb-3 text-sm text-slate-600">Selecciona el domiciliario que llevará este pedido.</p>

                        <select wire:model.live="domiciliarioSeleccionado"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm focus:border-violet-400 focus:bg-white focus:ring-2 focus:ring-violet-100">
                            <option value="">-- Seleccionar --</option>
                            @foreach($domiciliarios as $d)
                                <option value="{{ $d->id }}">{{ $d->nombre }} · {{ $d->telefonoFormateado() ?? 'Sin teléfono' }}</option>
                            @endforeach
                        </select>

                        @error('domiciliarioSeleccionado')
                            <div class="mt-2 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-2 border-t border-slate-100 px-4 sm:px-5 py-4 shrink-0">
                        <button wire:click="cerrarModalDespacho"
                                class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button wire:click="confirmarDespacho" wire:loading.attr="disabled" wire:target="confirmarDespacho"
                                class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-600 disabled:opacity-60">
                            <i class="fa-solid fa-motorcycle" wire:loading.class="hidden" wire:target="confirmarDespacho"></i>
                            <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="confirmarDespacho"></i>
                            Despachar
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- MODAL TOKEN ENTREGA --}}
        @if($modalTokenAbierto)
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4"
                 style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">

                <div class="w-full sm:max-w-sm rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white shadow-2xl" @click.stop>
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4 sm:px-5 py-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-slate-800">Verificar entrega</h3>
                            <p class="text-xs text-slate-500">Pedido #{{ str_pad($pedidoIdEntregando, 3, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <button wire:click="cerrarModalEntrega"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="px-4 sm:px-5 py-4">
                        <p class="text-sm text-slate-600">Ingresa el código de 4 dígitos que recibió el cliente.</p>

                        <input wire:model="tokenIngresado" wire:keydown.enter="confirmarEntregaConToken"
                               type="text" inputmode="numeric" maxlength="4" placeholder="0000" autofocus
                               class="mt-3 w-full rounded-xl border {{ $tokenError ? 'border-rose-400 bg-rose-50 focus:ring-rose-100' : 'border-slate-200 bg-slate-50 focus:ring-emerald-100' }} px-4 py-3 text-center text-2xl font-bold tracking-[0.5em] focus:border-emerald-400 focus:bg-white focus:ring-4">

                        @if($tokenError)
                            <div class="mt-2 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                {{ $tokenError }}
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-2 border-t border-slate-100 px-4 sm:px-5 py-4">
                        <button wire:click="cerrarModalEntrega"
                                class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button wire:click="confirmarEntregaConToken" wire:loading.attr="disabled" wire:target="confirmarEntregaConToken"
                                class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-600 disabled:opacity-60">
                            <i class="fa-solid fa-circle-check" wire:loading.class="hidden" wire:target="confirmarEntregaConToken"></i>
                            <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="confirmarEntregaConToken"></i>
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- MODAL CONVERSACIÓN IA --}}
        @if($modalConversacionAbierto)
            <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4"
                 style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
                 @click="$wire.cerrarModalConversacion()">

                <div class="w-full sm:max-w-xl rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white shadow-2xl max-h-[90vh] flex flex-col" @click.stop>
                    {{-- Header --}}
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4 sm:px-5 py-4 shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-slate-800">Conversación con IA</h3>
                            <p class="text-xs text-slate-500">Pedido #{{ str_pad($pedidoIdConversacion, 3, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        {{-- SGI Badge en header --}}
                        @if($clienteExisteErp === true)
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 border border-emerald-200 px-2.5 py-1.5 text-[10px] font-bold text-emerald-700">
                                <i class="fa-solid fa-building text-emerald-500"></i>
                                SGI Verificado
                                @if($cedulaCliente)
                                    <span class="font-mono">{{ $cedulaCliente }}</span>
                                @endif
                            </span>
                        @elseif($clienteExisteErp === false && $cedulaCliente)
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 border border-amber-200 px-2.5 py-1.5 text-[10px] font-bold text-amber-700">
                                <i class="fa-solid fa-user-plus text-amber-500"></i>
                                Cliente nuevo
                                <span class="font-mono">{{ $cedulaCliente }}</span>
                            </span>
                        @elseif($cedulaCliente === null)
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 border border-slate-200 px-2.5 py-1.5 text-[10px] font-bold text-slate-500">
                                <i class="fa-solid fa-circle-question text-slate-400"></i>
                                Sin validar
                            </span>
                        @endif
                        <button wire:click="cerrarModalConversacion"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    {{-- Mensajes --}}
                    <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-4 space-y-3" style="max-height: 60vh;">
                        @if(empty($mensajesConversacion))
                            <div class="text-center py-10 text-slate-400">
                                <i class="fa-solid fa-comments text-3xl mb-3"></i>
                                <p class="text-sm">No hay mensajes de conversación registrados para este pedido.</p>
                            </div>
                        @else
                            @foreach($mensajesConversacion as $msg)
                                <div class="flex {{ $msg['rol'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-sm
                                                {{ $msg['rol'] === 'user'
                                                    ? 'bg-emerald-500 text-white rounded-br-md'
                                                    : 'bg-slate-100 text-slate-800 rounded-bl-md border border-slate-200' }}">
                                        {{-- Icono de rol --}}
                                        <div class="flex items-center gap-1.5 mb-1">
                                            @if($msg['rol'] === 'user')
                                                <i class="fa-solid fa-user text-[9px] text-emerald-200"></i>
                                                <span class="text-[10px] font-bold text-emerald-200">Cliente</span>
                                            @else
                                                <i class="fa-solid fa-robot text-[9px] text-violet-500"></i>
                                                <span class="text-[10px] font-bold text-violet-600">Asistente IA</span>
                                            @endif
                                            @if(!empty($msg['hora']))
                                                <span class="text-[9px] {{ $msg['rol'] === 'user' ? 'text-emerald-200' : 'text-slate-400' }} ml-auto">{{ $msg['hora'] }}</span>
                                            @endif
                                        </div>
                                        {{-- Contenido --}}
                                        <div class="whitespace-pre-line break-words">{{ $msg['contenido'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between border-t border-slate-100 px-4 sm:px-5 py-3 shrink-0">
                        <div class="text-[10px] text-slate-400">
                            {{ count($mensajesConversacion) }} mensajes
                        </div>
                        <button wire:click="cerrarModalConversacion"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- AUDIO se mueve al layout para que no se reemplace en re-renders de Livewire --}}
    </div>

    </div> {{-- /max-w-full overflow-x-hidden --}}
</div>

@push('styles')
<style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

    /* Animación cuando llega un pedido nuevo — glow intenso + fondo verde */
    @keyframes pedido-glow {
        0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.9), inset 0 0 20px rgba(16, 185, 129, 0.3);
               background-color: rgba(16, 185, 129, 0.25); }
        50%  { box-shadow: 0 0 0 16px rgba(16, 185, 129, 0), inset 0 0 30px rgba(16, 185, 129, 0.5);
               background-color: rgba(16, 185, 129, 0.4); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0), inset 0 0 20px rgba(16, 185, 129, 0);
               background-color: rgba(209, 250, 229, 0.3); }
    }
    .pedido-nuevo-highlight {
        animation: pedido-glow 1.5s ease-out 4;
        border-left: 5px solid #10b981 !important;
        position: relative;
    }
    .pedido-nuevo-highlight::before {
        content: '🆕 NUEVO';
        position: absolute;
        top: 4px;
        right: 4px;
        background: #10b981;
        color: white;
        font-size: 9px;
        font-weight: 900;
        padding: 2px 8px;
        border-radius: 6px;
        z-index: 10;
        animation: bounce 1s ease-in-out infinite;
    }
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-3px); }
    }

    /* Toast de pedido nuevo */
    @keyframes slide-in-right {
        from { transform: translateX(400px); opacity: 0; }
        to   { transform: translateX(0); opacity: 1; }
    }
    .toast-pedido-nuevo {
        animation: slide-in-right 0.4s ease-out;
    }
</style>
@endpush

@push('scripts')
<script>
function pedidosNotif() {
    return {
        toast: null,
        _audioCtx: null,
        _audioUnlocked: false,
        _primerosIds: null,
        _idsConocidos: null,

        init() {
            // Desbloquear audio al primer input del usuario
            const unlock = () => {
                if (this._audioUnlocked) return;
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    this._audioCtx = new Ctx();
                    if (this._audioCtx.state === 'suspended') this._audioCtx.resume();
                    this._audioUnlocked = true;
                    console.log('🔓 Audio desbloqueado');
                } catch (e) { console.warn('No se pudo inicializar audio', e); }
            };
            document.addEventListener('click', unlock);
            document.addEventListener('keydown', unlock);

            // 1) Listener Livewire (evento del server cuando llega pedido via Reverb)
            if (window.Livewire) {
                Livewire.on('nuevo-pedido-en-vivo', (e) => {
                    const cliente = (e && e.cliente) || (Array.isArray(e) && e[0]?.cliente) || 'un cliente';
                    this.notificar(cliente);
                    // El DOM aún no tiene el pedido nuevo. Esperamos al morph.
                    setTimeout(() => this._detectarNuevos(), 300);
                    setTimeout(() => this._detectarNuevos(), 1000);   // retry por si el morph tardó
                });
            }

            // 2) Snapshot inicial de IDs actuales (para no marcar como "nuevos" los que ya estaban)
            setTimeout(() => {
                this._idsConocidos = this._snapshotIds();
                console.log('📋 IDs iniciales:', this._idsConocidos.size, 'pedidos');
            }, 500);

            // 3) Hook de Livewire en cada render para detectar nuevos IDs
            if (window.Livewire?.hook) {
                const onUpdate = () => setTimeout(() => this._detectarNuevos(), 100);
                // Probar varios hooks para cubrir versiones de Livewire 3
                try { Livewire.hook('morph.updated', onUpdate); } catch (_) {}
                try { Livewire.hook('morphed',       onUpdate); } catch (_) {}
                try { Livewire.hook('commit',        ({ succeed }) => succeed(onUpdate)); } catch (_) {}
            }

            // 4) Observer DOM como red de seguridad absoluta
            const observer = new MutationObserver(() => {
                clearTimeout(this._obsTimeout);
                this._obsTimeout = setTimeout(() => this._detectarNuevos(), 150);
            });
            observer.observe(document.body, { childList: true, subtree: true });
        },

        _idsPorIluminar: new Set(),   // IDs que deben mostrar highlight durante X segundos
        _maxIdConocido: 0,             // Mayor ID visto hasta ahora — solo importa si CRECE

        _snapshotIds() {
            return Array.from(document.querySelectorAll('[data-pedido-id]'))
                .map(n => parseInt(n.dataset.pedidoId, 10))
                .filter(n => !isNaN(n));
        },

        _detectarNuevos() {
            const ids = this._snapshotIds();
            if (ids.length === 0) return;

            const maxAhora = Math.max(...ids);

            // Primera ejecución: solo registramos baseline, NO notificamos.
            if (this._maxIdConocido === 0) {
                this._maxIdConocido = maxAhora;
                return;
            }

            // Solo notificamos cuando aparece un ID MAYOR al máximo conocido.
            // Esto evita falsos positivos al cambiar de filtro o al recargar
            // (donde aparecen IDs que existían pero no estaban en pantalla).
            if (maxAhora > this._maxIdConocido) {
                const realmenteNuevos = ids.filter(id => id > this._maxIdConocido);
                console.log('🛒 Pedidos NUEVOS detectados:', realmenteNuevos);
                this.notificar('un cliente');
                realmenteNuevos.forEach(id => {
                    this._idsPorIluminar.add(String(id));
                    this._aplicarHighlight(id);
                    setTimeout(() => {
                        this._idsPorIluminar.delete(String(id));
                        this._quitarHighlight(id);
                    }, 6000);
                });
                this._maxIdConocido = maxAhora;
            }

            // Re-aplicar highlight a los que aún deben iluminar
            this._idsPorIluminar.forEach(id => this._aplicarHighlight(id));
        },

        _aplicarHighlight(id) {
            const filas = document.querySelectorAll(`[data-pedido-id="${id}"]`);
            filas.forEach(f => {
                if (!f.classList.contains('pedido-nuevo-highlight')) {
                    f.classList.add('pedido-nuevo-highlight');
                }
            });
        },

        _quitarHighlight(id) {
            const filas = document.querySelectorAll(`[data-pedido-id="${id}"]`);
            filas.forEach(f => f.classList.remove('pedido-nuevo-highlight'));
        },

        notificar(cliente) {
            this._playBeep();

            this.toast = `🛒 Nuevo pedido de ${cliente}`;
            setTimeout(() => { this.toast = null; }, 7000);

            this._flashTitle('🛒 ¡Nuevo pedido!');
        },

        // Genera un beep con Web Audio API (sin archivo mp3)
        _playBeep() {
            if (!this._audioUnlocked || !this._audioCtx) {
                console.warn('🔇 Audio aún no desbloqueado. Haz clic en la página primero.');
                return;
            }
            try {
                const ctx = this._audioCtx;
                const now = ctx.currentTime;

                // Dos tonos tipo notificación: primero agudo, luego más grave
                [
                    { freq: 880, start: 0,    dur: 0.15 },
                    { freq: 660, start: 0.18, dur: 0.22 },
                ].forEach(t => {
                    const osc  = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = t.freq;
                    gain.gain.setValueAtTime(0, now + t.start);
                    gain.gain.linearRampToValueAtTime(0.35, now + t.start + 0.02);
                    gain.gain.linearRampToValueAtTime(0, now + t.start + t.dur);
                    osc.connect(gain).connect(ctx.destination);
                    osc.start(now + t.start);
                    osc.stop(now + t.start + t.dur + 0.05);
                });
            } catch (e) { console.warn('Error reproduciendo beep', e); }
        },

        _flashTitle(txt) {
            const original = document.title;
            let toggle = true;
            const int = setInterval(() => {
                document.title = toggle ? txt : original;
                toggle = !toggle;
            }, 800);
            setTimeout(() => { clearInterval(int); document.title = original; }, 7000);
        },
    };
}
</script>

<template x-if="toast">
    <div class="toast-pedido-nuevo fixed top-6 right-6 z-[60] bg-emerald-500 text-white font-bold px-5 py-4 rounded-2xl shadow-2xl flex items-center gap-3 cursor-pointer"
         @click="toast = null">
        <i class="fa-solid fa-bag-shopping text-2xl"></i>
        <div>
            <p class="text-sm" x-text="toast"></p>
            <p class="text-[11px] opacity-80">Clic para cerrar</p>
        </div>
    </div>
</template>
@endpush
