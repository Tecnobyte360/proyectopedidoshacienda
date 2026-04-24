<div class="min-h-screen bg-slate-50" wire:poll.3s="refrescar"
     x-data="pedidosNotif()"
     x-init="init()">

    {{-- 🚀 BARRA FLOTANTE — aparece cuando hay pedidos seleccionados para despacho masivo --}}
    @php $cantSel = collect($seleccionadosMasivo)->filter()->count(); @endphp
    @if($cantSel > 0)
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40">
            <div class="flex items-center gap-3 rounded-2xl bg-slate-900 text-white shadow-2xl px-5 py-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#d68643] font-bold">{{ $cantSel }}</span>
                <span class="text-sm font-semibold">pedido(s) seleccionado(s)</span>
                <button wire:click="limpiarSeleccionMasiva" class="ml-2 text-xs text-slate-400 hover:text-white">
                    Cancelar
                </button>
                <button wire:click="abrirModalMasivo"
                        class="ml-2 inline-flex items-center gap-2 rounded-xl bg-[#d68643] hover:bg-[#c97a36] px-4 py-2 text-sm font-bold transition shadow">
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
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
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
            $pedidos          = $this->pedidos;
            $pedidosFiltrados = $this->pedidosFiltrados;

            $todos       = $pedidos->count();
            // 'confirmado' es legacy → cuenta como nuevo
            $nuevos      = $pedidos->whereIn('estado', [\App\Models\Pedido::ESTADO_NUEVO, 'confirmado'])->count();
            $enProceso   = $pedidos->where('estado', \App\Models\Pedido::ESTADO_EN_PREPARACION)->count();
            $despachados = $pedidos->where('estado', \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)->count();
            $entregados  = $pedidos->where('estado', \App\Models\Pedido::ESTADO_ENTREGADO)->count();
            $cancelados  = $pedidos->where('estado', \App\Models\Pedido::ESTADO_CANCELADO)->count();

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
            <div class="h-1 bg-gradient-to-r from-orange-400 via-orange-500 to-orange-600"></div>

            <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    <div class="flex h-11 w-11 sm:h-12 sm:w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-400 to-orange-600 text-white shadow-md">
                        <i class="fa-solid fa-bag-shopping text-base sm:text-lg"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-2.5 py-1 text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-orange-600 ring-1 ring-orange-100">
                            <span class="h-1.5 w-1.5 rounded-full bg-orange-500 animate-pulse"></span>
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
                            class="shrink-0 flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-orange-600 transition shadow-sm">
                        <i class="fa-solid fa-expand"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- KPIS --}}
        <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach([
                ['label' => 'Nuevos',      'value' => $nuevos,      'icon' => 'fa-bell',         'iconBg' => 'bg-blue-50 text-blue-600',       'bar' => 'bg-blue-500'],
                ['label' => 'En proceso',  'value' => $enProceso,   'icon' => 'fa-gears',        'iconBg' => 'bg-amber-50 text-amber-600',     'bar' => 'bg-amber-500'],
                ['label' => 'Despachados', 'value' => $despachados, 'icon' => 'fa-motorcycle',   'iconBg' => 'bg-violet-50 text-violet-600',   'bar' => 'bg-violet-500'],
                ['label' => 'Entregados',  'value' => $entregados,  'icon' => 'fa-circle-check', 'iconBg' => 'bg-emerald-50 text-emerald-600', 'bar' => 'bg-emerald-500'],
                ['label' => 'Cancelados',  'value' => $cancelados,  'icon' => 'fa-ban',          'iconBg' => 'bg-rose-50 text-rose-600',       'bar' => 'bg-rose-500'],
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
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">

                {{-- Tabs scrollables horizontal --}}
                <div class="flex-1 min-w-0 -mx-3 sm:-mx-4 lg:mx-0 px-3 sm:px-4 lg:px-0 overflow-x-auto pb-1 scrollbar-hide">
                    <div class="flex gap-2 min-w-max">
                        @php
                            $tabs = [
                                ['key' => 'todos',                                              'label' => 'Todos',       'count' => $todos,       'icon' => 'fa-table-cells-large', 'active' => 'bg-slate-900 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_NUEVO,                    'label' => 'Nuevos',      'count' => $nuevos,      'icon' => 'fa-bell',              'active' => 'bg-blue-600 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_EN_PREPARACION,           'label' => 'En proceso',  'count' => $enProceso,   'icon' => 'fa-gears',             'active' => 'bg-amber-500 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO,     'label' => 'Despachados', 'count' => $despachados, 'icon' => 'fa-motorcycle',        'active' => 'bg-violet-600 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_ENTREGADO,                'label' => 'Entregados',  'count' => $entregados,  'icon' => 'fa-circle-check',      'active' => 'bg-emerald-600 text-white'],
                                ['key' => \App\Models\Pedido::ESTADO_CANCELADO,                'label' => 'Cancelados',  'count' => $cancelados,  'icon' => 'fa-ban',               'active' => 'bg-rose-600 text-white'],
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

                {{-- Filtro de zona — alineado con los tabs --}}
                <div class="relative w-full lg:w-56 shrink-0">
                    <i class="fa-solid fa-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <select wire:model.live="zona"
                            class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-10 text-sm font-medium text-slate-700 focus:border-orange-400 focus:bg-white focus:ring-2 focus:ring-orange-100">
                        <option value="todas">Todas las zonas</option>
                        @foreach($zonasDisponibles as $z)
                            <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                        @endforeach
                        <option value="sin_zona">Sin zona asignada</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>
            </div>
        </div>

        {{-- CONTADOR DE RESULTADOS --}}
        <div class="mt-3 flex items-center justify-between px-1">
            <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-600">
                <i class="fa-solid fa-database text-[10px]"></i>
                {{ $pedidosFiltrados->count() }} {{ Str::plural('pedido', $pedidosFiltrados->count()) }}
            </div>
        </div>

        {{-- ╔═══ CARDS para móvil/tablet (< lg) ═══╗ --}}
        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 lg:hidden">
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
                                <div class="font-bold text-slate-800 truncate">{{ $pedido->cliente_nombre }}</div>
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

                            @include('livewire.pedidos._semaforo', ['pedido' => $pedido, 'size' => 'sm'])
                        </div>

                        {{-- Barra ANS --}}
                        <div class="pt-1">
                            @include('livewire.pedidos._semaforo', ['pedido' => $pedido, 'modo' => 'barra'])
                        </div>

                        {{-- Info en grid --}}
                        <div class="grid grid-cols-1 gap-1.5 text-xs text-slate-600 mt-2">
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

                            @if($pedido->telefono_whatsapp || $pedido->telefono)
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-phone w-4 text-center text-slate-400"></i>
                                    <span>{{ $pedido->telefono_whatsapp ?? $pedido->telefono }}</span>
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
                        </div>
                    </div>

                    {{-- Acción --}}
                    <div class="px-4 pb-4">
                        @include('livewire.pedidos._accion_pedido', ['pedido' => $pedido])
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-slate-400">
                        <i class="fa-solid fa-inbox text-xl"></i>
                    </div>
                    <h3 class="mt-3 text-base font-semibold text-slate-700">Sin pedidos</h3>
                    <p class="mt-1 text-sm text-slate-500">No hay pedidos con los filtros actuales.</p>
                </div>
            @endforelse
        </div>

        {{-- ╔═══ TABLA para desktop (>= lg) ═══╗ --}}
        <div class="mt-3 hidden lg:block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 w-10">
                                <button type="button" wire:click="seleccionarTodosVisibles"
                                        title="Seleccionar todos los despachables visibles"
                                        class="text-slate-400 hover:text-[#d68643] transition">
                                    <i class="fa-solid fa-check-double text-sm"></i>
                                </button>
                            </th>
                            @php
                                $cols = [
                                    ['label' => 'Pedido',       'cls' => ''],
                                    ['label' => 'Cliente',      'cls' => ''],
                                    ['label' => 'Zona',         'cls' => 'hidden xl:table-cell'],
                                    ['label' => 'Teléfono',     'cls' => 'hidden 2xl:table-cell'],
                                    ['label' => 'Estado',       'cls' => ''],
                                    ['label' => 'Tiempo (ANS)', 'cls' => ''],
                                    ['label' => 'Hora',         'cls' => 'hidden 2xl:table-cell'],
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
                                $despachable = in_array($pedido->estado, [
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
                                               class="rounded border-slate-300 text-[#d68643] focus:ring-[#d68643] cursor-pointer">
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
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">
                                            {{ $iniciales ?: 'CL' }}
                                        </div>
                                        <div class="min-w-0 max-w-[160px]">
                                            <div class="truncate font-semibold text-slate-900 text-sm">{{ $pedido->cliente_nombre }}</div>
                                            <div class="text-[11px] text-slate-500 truncate">
                                                {{ $pedido->created_at?->diffForHumans() }}
                                                @if($pedido->barrio)
                                                    <span class="xl:hidden"> · {{ $pedido->barrio }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Zona (xl+) --}}
                                <td class="px-3 py-3.5 align-middle hidden xl:table-cell">
                                    @if($pedido->zonaCobertura)
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

                                {{-- Teléfono (2xl+) --}}
                                <td class="px-3 py-3.5 align-middle hidden 2xl:table-cell">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 whitespace-nowrap">
                                        <i class="fa-solid fa-phone text-slate-400 text-[10px]"></i>
                                        {{ $pedido->telefono_whatsapp ?? $pedido->telefono ?? '—' }}
                                    </span>
                                </td>

                                {{-- Estado --}}
                                <td class="px-3 py-3.5 align-middle">
                                    @if($meta)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap {{ $meta['badge'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>
                                            {{ $meta['label'] }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Semáforo ANS --}}
                                <td class="px-3 py-3.5 align-middle">
                                    <div class="w-[110px]">
                                        @include('livewire.pedidos._semaforo', ['pedido' => $pedido, 'modo' => 'barra'])
                                    </div>
                                </td>

                                {{-- Hora (2xl+) --}}
                                <td class="px-3 py-3.5 align-middle whitespace-nowrap hidden 2xl:table-cell">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <i class="fa-regular fa-clock text-slate-400 text-[10px]"></i>
                                        {{ $pedido->created_at?->format('h:i a') }}
                                    </span>
                                </td>

                                {{-- Total --}}
                                <td class="px-3 py-3.5 align-middle">
                                    <span class="inline-flex rounded-lg bg-slate-900 px-2 py-1 text-xs font-bold text-white shadow-sm whitespace-nowrap">
                                        ${{ number_format($pedido->total, 0, ',', '.') }}
                                    </span>
                                </td>

                                {{-- Domiciliario (xl+) --}}
                                <td class="px-3 py-3.5 align-middle hidden xl:table-cell">
                                    @if($pedido->domiciliario)
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
                                    @include('livewire.pedidos._accion_pedido', ['pedido' => $pedido])
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

        {{-- AUDIO se mueve al layout para que no se reemplace en re-renders de Livewire --}}
    </div>
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
            // Desbloquear audio al primer click del usuario
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
            document.addEventListener('click', unlock, { once: false });
            document.addEventListener('keydown', unlock, { once: false });

            // 1) Listener Livewire para el evento 'nuevo-pedido-en-vivo' dispatch desde onPedidoConfirmado
            if (window.Livewire) {
                Livewire.on('nuevo-pedido-en-vivo', (e) => {
                    const cliente = (e && e.cliente) || (Array.isArray(e) && e[0]?.cliente) || 'un cliente';
                    this.notificar(cliente);
                });
            }

            // 2) Fallback: si Reverb no conecta, detectamos pedidos nuevos comparando IDs tras cada render.
            //    Guardamos el snapshot actual para no disparar el primer render.
            this._idsConocidos = this._snapshotIds();

            if (window.Livewire?.hook) {
                Livewire.hook('morph.updated', () => {
                    // Dar tiempo a que el DOM se estabilice
                    setTimeout(() => this._detectarNuevos(), 50);
                });
            }
        },

        _snapshotIds() {
            return new Set(
                Array.from(document.querySelectorAll('[data-pedido-id]'))
                    .map(n => n.dataset.pedidoId)
            );
        },

        _detectarNuevos() {
            const ahora = this._snapshotIds();
            const nuevos = [...ahora].filter(id => !this._idsConocidos.has(id));
            this._idsConocidos = ahora;

            if (nuevos.length > 0) {
                console.log('🛒 Detectados pedidos nuevos:', nuevos);
                this.notificar('un cliente');
                // Iluminar todos los nuevos (no solo el primero)
                nuevos.forEach(id => {
                    const fila = document.querySelector(`[data-pedido-id="${id}"]`);
                    if (fila) {
                        fila.classList.add('pedido-nuevo-highlight');
                        setTimeout(() => fila.classList.remove('pedido-nuevo-highlight'), 6000);
                    }
                });
            }
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
