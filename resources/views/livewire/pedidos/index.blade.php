<div class="min-h-screen bg-white text-slate-800">
    <div class="w-full px-4 py-4 sm:px-6 lg:px-8">

        @php
            $todos = $pedidos->count();
            $nuevos = $pedidos->where('estado', \App\Models\Pedido::ESTADO_NUEVO)->count();
            $enProceso = $pedidos->where('estado', \App\Models\Pedido::ESTADO_EN_PREPARACION)->count();
            $despachados = $pedidos->where('estado', \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)->count();
            $entregados = $pedidos->where('estado', \App\Models\Pedido::ESTADO_ENTREGADO)->count();
            $cancelados = $pedidos->where('estado', \App\Models\Pedido::ESTADO_CANCELADO)->count();

            $tab = request('estado', 'todos');
            $zona = request('zona', 'todas');

            $pedidosFiltrados = $pedidos;

            if ($tab !== 'todos') {
                $pedidosFiltrados = $pedidosFiltrados->where('estado', $tab);
            }

            if ($zona !== 'todas') {
                $pedidosFiltrados = $pedidosFiltrados->where('zona', $zona);
            }
        @endphp

        {{-- HEADER PRINCIPAL --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-slate-900 via-indigo-600 to-sky-500"></div>

            <div class="flex items-center gap-4 px-5 py-4">
                <div
                    class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white shadow-md">
                    <i class="fa-solid fa-bag-shopping text-lg"></i>
                    <div class="absolute inset-0 rounded-2xl bg-indigo-500/20 blur-lg"></div>
                </div>

                <div class="min-w-0">

                    <h4 class="mt-1.5 text-2xl font-semibold tracking-tight text-slate-800 md:text-3xl">
                        Gestión de Pedidos
                    </h4>
                </div>
            </div>
        </div>

        {{-- KPIS --}}
        <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.22em] text-slate-500">Nuevos</p>
                        <h2 class="mt-1 text-2xl font-semibold leading-none text-slate-900">{{ $nuevos }}</h2>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-bell text-xs"></i>
                    </div>
                </div>
                <div class="mt-2.5 h-1 w-10 rounded-full bg-blue-500"></div>
                <p class="mt-1.5 text-[11px] leading-tight text-slate-500">Pendientes de atención</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.22em] text-slate-500">En proceso</p>
                        <h2 class="mt-1 text-2xl font-semibold leading-none text-slate-900">{{ $enProceso }}</h2>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <i class="fa-solid fa-gears text-xs"></i>
                    </div>
                </div>
                <div class="mt-2.5 h-1 w-10 rounded-full bg-amber-500"></div>
                <p class="mt-1.5 text-[11px] leading-tight text-slate-500">Pedidos en preparación</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.22em] text-slate-500">Despachados</p>
                        <h2 class="mt-1 text-2xl font-semibold leading-none text-slate-900">{{ $despachados }}</h2>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                        <i class="fa-solid fa-motorcycle text-xs"></i>
                    </div>
                </div>
                <div class="mt-2.5 h-1 w-10 rounded-full bg-violet-500"></div>
                <p class="mt-1.5 text-[11px] leading-tight text-slate-500">En ruta de entrega</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.22em] text-slate-500">Entregados</p>
                        <h2 class="mt-1 text-2xl font-semibold leading-none text-slate-900">{{ $entregados }}</h2>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <i class="fa-solid fa-circle-check text-xs"></i>
                    </div>
                </div>
                <div class="mt-2.5 h-1 w-10 rounded-full bg-emerald-500"></div>
                <p class="mt-1.5 text-[11px] leading-tight text-slate-500">Finalizados correctamente</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.22em] text-slate-500">Cancelados</p>
                        <h2 class="mt-1 text-2xl font-semibold leading-none text-slate-900">{{ $cancelados }}</h2>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <i class="fa-solid fa-ban text-xs"></i>
                    </div>
                </div>
                <div class="mt-2.5 h-1 w-10 rounded-full bg-rose-500"></div>
                <p class="mt-1.5 text-[11px] leading-tight text-slate-500">No completados</p>
            </div>
        </div>

        {{-- FILTROS Y ACCIONES --}}
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ request()->fullUrlWithQuery(['estado' => 'todos']) }}"
                        class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition {{ $tab === 'todos' ? 'bg-slate-900 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50' }}">
                        <i class="fa-solid fa-table-cells-large text-xs"></i>
                        Todos
                        <span
                            class="rounded-full bg-black/10 px-2 py-0.5 text-[11px] {{ $tab === 'todos' ? 'bg-white/15 text-white' : '' }}">{{ $todos }}</span>
                    </a>

                    <a href="{{ request()->fullUrlWithQuery(['estado' => 'nuevo']) }}"
                        class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition {{ $tab === 'nuevo' ? 'bg-blue-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-blue-50 hover:text-blue-600' }}">
                        <i class="fa-solid fa-bell text-xs"></i>
                        Nuevos
                        <span
                            class="rounded-full bg-black/10 px-2 py-0.5 text-[11px] {{ $tab === 'nuevo' ? 'bg-white/15 text-white' : '' }}">{{ $nuevos }}</span>
                    </a>

                    <a href="{{ request()->fullUrlWithQuery(['estado' => 'en_proceso']) }}"
                        class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition {{ $tab === 'en_proceso' ? 'bg-amber-500 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-amber-50 hover:text-amber-600' }}">
                        <i class="fa-solid fa-gears text-xs"></i>
                        En proceso
                        <span
                            class="rounded-full bg-black/10 px-2 py-0.5 text-[11px] {{ $tab === 'en_proceso' ? 'bg-white/15 text-white' : '' }}">{{ $enProceso }}</span>
                    </a>

                    <a href="{{ request()->fullUrlWithQuery(['estado' => 'despachado']) }}"
                        class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition {{ $tab === 'despachado' ? 'bg-violet-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-violet-50 hover:text-violet-600' }}">
                        <i class="fa-solid fa-motorcycle text-xs"></i>
                        Despachados
                        <span
                            class="rounded-full bg-black/10 px-2 py-0.5 text-[11px] {{ $tab === 'despachado' ? 'bg-white/15 text-white' : '' }}">{{ $despachados }}</span>
                    </a>

                    <a href="{{ request()->fullUrlWithQuery(['estado' => 'entregado']) }}"
                        class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition {{ $tab === 'entregado' ? 'bg-emerald-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-emerald-50 hover:text-emerald-600' }}">
                        <i class="fa-solid fa-circle-check text-xs"></i>
                        Entregados
                        <span
                            class="rounded-full bg-black/10 px-2 py-0.5 text-[11px] {{ $tab === 'entregado' ? 'bg-white/15 text-white' : '' }}">{{ $entregados }}</span>
                    </a>

                    <a href="{{ request()->fullUrlWithQuery(['estado' => 'cancelado']) }}"
                        class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold transition {{ $tab === 'cancelado' ? 'bg-rose-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:bg-rose-50 hover:text-rose-600' }}">
                        <i class="fa-solid fa-ban text-xs"></i>
                        Cancelados
                        <span
                            class="rounded-full bg-black/10 px-2 py-0.5 text-[11px] {{ $tab === 'cancelado' ? 'bg-white/15 text-white' : '' }}">{{ $cancelados }}</span>
                    </a>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <form method="GET" class="w-full sm:w-auto">
                        <input type="hidden" name="estado" value="{{ $tab }}">
                        <div class="relative min-w-[220px]">
                            <i
                                class="fa-solid fa-location-dot pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>

                            <select name="zona" onchange="this.form.submit()"
                                class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-10 text-sm font-medium text-slate-700 outline-none transition focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                                <option value="todas" {{ $zona === 'todas' ? 'selected' : '' }}>Todas las zonas
                                </option>
                                <option value="norte" {{ $zona === 'norte' ? 'selected' : '' }}>Zona Norte</option>
                                <option value="sur" {{ $zona === 'sur' ? 'selected' : '' }}>Zona Sur</option>
                                <option value="centro" {{ $zona === 'centro' ? 'selected' : '' }}>Zona Centro</option>
                            </select>

                            <i
                                class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- TABLA PROFESIONAL --}}
        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">


                    <div
                        class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-600">
                        <i class="fa-solid fa-database text-[10px]"></i>
                        {{ $pedidosFiltrados->count() }} registros
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Pedido</th>
                            <th
                                class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Cliente</th>
                            <th
                                class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Zona</th>
                            <th
                                class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Teléfono</th>
                            <th
                                class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Estado</th>
                            <th
                                class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Hora</th>
                            <th
                                class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Total</th>
                            <th
                                class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                                Acción</th>
                        </tr>
                    </thead>

                    <tbody id="orders-list" class="divide-y divide-slate-100">
                        @forelse($pedidosFiltrados as $pedido)
                            @php
                                $estado = $pedido->estado ?? 'nuevo';

                                $badgeClass = match ($estado) {
                                    'nuevo' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'en_proceso' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'despachado' => 'bg-violet-50 text-violet-700 border-violet-200',
                                    'entregado' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'cancelado' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                                };

                                $dotClass = match ($estado) {
                                    'nuevo' => 'bg-blue-500',
                                    'en_proceso' => 'bg-amber-500',
                                    'despachado' => 'bg-violet-500',
                                    'entregado' => 'bg-emerald-500',
                                    'cancelado' => 'bg-rose-500',
                                    default => 'bg-slate-400',
                                };

                                $iconEstado = match ($estado) {
                                    'nuevo' => 'fa-bell',
                                    'en_proceso' => 'fa-gears',
                                    'despachado' => 'fa-motorcycle',
                                    'entregado' => 'fa-circle-check',
                                    'cancelado' => 'fa-ban',
                                    default => 'fa-circle',
                                };

                                $labelEstado = match ($estado) {
                                    'nuevo' => 'Nuevo',
                                    'en_proceso' => 'En proceso',
                                    'despachado' => 'Despachado',
                                    'entregado' => 'Entregado',
                                    'cancelado' => 'Cancelado',
                                    default => ucfirst(str_replace('_', ' ', $estado)),
                                };

                                $iniciales = collect(explode(' ', trim($pedido->cliente_nombre ?? 'CL')))
                                    ->filter()
                                    ->take(2)
                                    ->map(fn($parte) => mb_substr($parte, 0, 1))
                                    ->implode('');
                            @endphp

                            <tr data-id="{{ $pedido->id }}"
                                class="transition hover:bg-slate-50 {{ $estado === 'cancelado' ? 'opacity-75' : '' }}">
                                <td class="px-4 py-3.5 align-middle">
                                    <div
                                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-700">
                                        <i class="fa-solid fa-hashtag text-[9px] text-slate-400"></i>
                                        PED-{{ str_pad($pedido->id, 3, '0', STR_PAD_LEFT) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-middle">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">
                                            {{ $iniciales ?: 'CL' }}
                                        </div>

                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-slate-900">
                                                {{ $pedido->cliente_nombre }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ \Carbon\Carbon::parse($pedido->created_at)->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-middle">
                                    <span
                                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700">
                                        <i class="fa-solid fa-location-dot text-indigo-500 text-[11px]"></i>
                                        {{ $pedido->zona ? ucfirst($pedido->zona) : 'Sin zona' }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 align-middle">
                                    <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                        <i class="fa-solid fa-phone text-slate-400 text-[11px]"></i>
                                        {{ $pedido->telefono ?? 'Sin teléfono' }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 align-middle">
                                    <span
                                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.15em] {{ $badgeClass }}">
                                        <span class="h-2 w-2 rounded-full {{ $dotClass }}"></span>
                                        <i class="fa-solid {{ $iconEstado }} text-[10px]"></i>
                                        {{ $labelEstado }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 align-middle">
                                    <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                        <i class="fa-regular fa-clock text-slate-400 text-[11px]"></i>
                                        {{ \Carbon\Carbon::parse($pedido->created_at)->format('h:i a') }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 align-middle">
                                    <span
                                        class="inline-flex rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white shadow-sm">
                                        ${{ number_format($pedido->total, 0, ',', '.') }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-center align-middle">
                                    <button wire:click="$refresh" class="bg-red-500 text-white p-2">
                                        TEST
                                    </button>
                                    @if ($pedido->estado === \App\Models\Pedido::ESTADO_NUEVO)
                                        <button type="button" wire:click="marcarEnPreparacion({{ $pedido->id }})"
                                            class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-3 py-2 text-xs font-bold text-white transition hover:bg-amber-600">
                                            <i class="fa-solid fa-utensils"></i>
                                            Iniciar preparación
                                        </button>
                                    @elseif($pedido->estado === \App\Models\Pedido::ESTADO_EN_PREPARACION)
                                        <span
                                            class="inline-flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 border border-amber-200">
                                            <i class="fa-solid fa-utensils"></i>
                                            En preparación
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 border border-slate-200">
                                            <i class="fa-solid fa-circle-info"></i>
                                            {{ ucfirst(str_replace('_', ' ', $pedido->estado)) }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="empty-state">
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-400">
                                        <i class="fa-solid fa-inbox text-2xl"></i>
                                    </div>
                                    <h3 class="mt-4 text-xl font-semibold text-slate-800">Sin pedidos para mostrar</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        No hay pedidos disponibles con los filtros actuales.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TOAST --}}
        <div id="toast"
            class="pointer-events-none fixed bottom-5 right-5 z-50 hidden min-w-[320px] max-w-sm rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100 p-4 shadow-[0_20px_50px_rgba(16,185,129,0.22)]">

            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md">
                    <i class="fa-solid fa-check text-sm"></i>
                </div>

                <div class="flex-1">
                    <h4 class="text-sm font-bold uppercase tracking-[0.16em] text-emerald-700">
                        ¡Nuevo pedido!
                    </h4>

                    <p id="toast-message" class="mt-1 text-sm leading-relaxed text-emerald-800">
                        Se ha recibido un nuevo pedido.
                    </p>
                </div>

                <audio id="new-order-sound" preload="auto">
                    <source src="{{ asset('sounds/new-order.mp3') }}" type="audio/mpeg">
                </audio>
            </div>
        </div>
    </div>
</div>
