<div class="px-6 lg:px-10 py-8" wire:poll.30s>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">
                <i class="fa-solid fa-route text-brand"></i> Rutas
            </h2>
            <p class="text-sm text-slate-500">Pedidos asignados a cada domiciliario con la ruta óptima de entrega.</p>
        </div>

        <div class="flex items-center gap-3">
            <input type="text" wire:model.live.debounce.400ms="busqueda"
                   placeholder="Buscar domiciliario..."
                   class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand focus:ring-brand">
        </div>
    </div>

    {{-- Resumen rápido --}}
    @php
        $totalPedidosAsignados = collect($rutas)->sum('total_pedidos');
        $totalDomisConCarga = collect($rutas)->filter(fn ($r) => $r['total_pedidos'] > 0)->count();
    @endphp
    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-soft text-brand-secondary">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totalDomisConCarga }}</div>
                    <div class="text-xs text-slate-500">Domiciliarios en ruta</div>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totalPedidosAsignados }}</div>
                    <div class="text-xs text-slate-500">Pedidos en curso</div>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $sinAsignar->count() }}</div>
                    <div class="text-xs text-slate-500">Sin asignar</div>
                </div>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $domiciliarios->count() }}</div>
                    <div class="text-xs text-slate-500">Domiciliarios activos</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Columna principal: lista de domiciliarios + sus pedidos --}}
        <div class="lg:col-span-2 space-y-4">

            @forelse($domiciliarios as $domi)
                @php
                    $r = $rutas[$domi->id];
                    $expandido = $domiExpandido === $domi->id;
                    $vehiculoIcon = match(strtolower($domi->vehiculo ?? '')) {
                        'moto', 'motocicleta' => 'fa-motorcycle',
                        'bicicleta', 'bici'   => 'fa-bicycle',
                        'carro', 'auto'       => 'fa-car',
                        default               => 'fa-truck-fast',
                    };
                @endphp

                <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">

                    {{-- Cabecera --}}
                    <div class="flex items-center gap-3 px-5 py-4 cursor-pointer hover:bg-slate-50"
                         wire:click="expandir({{ $domi->id }})">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl text-white text-xl font-extrabold flex-shrink-0"
                             style="background: linear-gradient(135deg, var(--brand-primary, #d68643), var(--brand-secondary, #a85f24));">
                            {{ mb_substr($domi->nombre, 0, 1) }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-slate-800 truncate">{{ $domi->nombre }}</div>
                            <div class="text-xs text-slate-500 flex items-center gap-2 flex-wrap">
                                <span><i class="fa-solid {{ $vehiculoIcon }}"></i> {{ $domi->vehiculo ?: 'N/A' }}</span>
                                @if($domi->placa)
                                    <span class="bg-slate-100 rounded px-1.5 py-0.5 font-mono text-[10px]">{{ $domi->placa }}</span>
                                @endif
                                @if($domi->telefono)
                                    <a href="tel:{{ $domi->telefonoInternacional() }}"
                                       wire:click.stop
                                       class="text-emerald-600 hover:underline font-mono">
                                        <i class="fa-solid fa-phone text-[10px]"></i> {{ $domi->telefono }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Badge cantidad de pedidos --}}
                        <div class="flex flex-col items-end gap-1">
                            @if($r['total_pedidos'] > 0)
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-700">
                                    <i class="fa-solid fa-bag-shopping text-[10px]"></i> {{ $r['total_pedidos'] }} pedido(s)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500">
                                    <i class="fa-solid fa-bed text-[10px]"></i> Sin pedidos
                                </span>
                            @endif
                            <i class="fa-solid {{ $expandido ? 'fa-chevron-up' : 'fa-chevron-down' }} text-slate-400 text-xs"></i>
                        </div>
                    </div>

                    {{-- Contenido expandido --}}
                    @if($expandido && $r['total_pedidos'] > 0)
                        <div class="border-t border-slate-100 bg-slate-50/40 px-5 py-4">

                            {{-- Botón ruta optimizada en GMaps --}}
                            @if($r['url_maps'])
                                <a href="{{ $r['url_maps'] }}" target="_blank" rel="noopener"
                                   class="block rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-4 py-3 text-center mb-3 transition shadow">
                                    <i class="fa-brands fa-google mr-1"></i>
                                    Abrir ruta optimizada en Google Maps
                                    <span class="text-xs opacity-90 block">{{ $r['total_pedidos'] }} parada(s) en orden óptimo</span>
                                </a>
                            @endif

                            {{-- Lista de pedidos en orden óptimo --}}
                            <div class="space-y-2">
                                @foreach($r['pedidos'] as $i => $p)
                                    @php
                                        $colorEstado = match($p->estado) {
                                            'nuevo' => 'bg-blue-100 text-blue-700',
                                            'en_preparacion' => 'bg-amber-100 text-amber-700',
                                            'repartidor_en_camino' => 'bg-violet-100 text-violet-700',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <div class="rounded-xl bg-white border border-slate-200 p-3 flex items-start gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-white font-extrabold text-xs flex-shrink-0"
                                             style="background: linear-gradient(135deg, var(--brand-primary, #d68643), var(--brand-secondary, #a85f24));">
                                            {{ $i + 1 }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="font-bold text-slate-800 text-sm">#{{ $p->id }}</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $colorEstado }}">
                                                    {{ ucfirst(str_replace('_', ' ', $p->estado)) }}
                                                </span>
                                                <span class="text-xs text-slate-500 truncate">{{ $p->cliente_nombre }}</span>
                                            </div>
                                            <div class="text-xs text-slate-600">
                                                <i class="fa-solid fa-location-dot text-rose-400 mr-1"></i>
                                                {{ $p->direccion ?? 'Sin dirección' }}@if($p->barrio), {{ $p->barrio }}@endif
                                            </div>
                                            <div class="flex items-center gap-3 mt-1 text-[11px] text-slate-500">
                                                <span><i class="fa-solid fa-phone text-[9px]"></i> {{ $p->telefono_contacto ?? $p->telefono_whatsapp }}</span>
                                                <span><i class="fa-solid fa-money-bill text-[9px] text-emerald-500"></i> ${{ number_format((float) $p->total, 0, ',', '.') }}</span>
                                                @if($p->lat && $p->lng)
                                                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $p->lat }},{{ $p->lng }}"
                                                       target="_blank" rel="noopener"
                                                       class="text-blue-600 hover:underline">
                                                        <i class="fa-brands fa-google"></i> Ver
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                        <button wire:click="liberarPedido({{ $p->id }})"
                                                wire:confirm="¿Desasignar el pedido #{{ $p->id }} de {{ $domi->nombre }}?"
                                                class="text-rose-500 hover:text-rose-700 text-xs"
                                                title="Quitar asignación">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Compartir portal con el domi --}}
                            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-200" x-data="{ copiado: false }">
                                <a href="{{ $domi->urlPortal() }}" target="_blank"
                                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold px-3 py-2">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir su portal
                                </a>
                                <button @click="navigator.clipboard.writeText('{{ $domi->urlPortal() }}'); copiado = true; setTimeout(() => copiado = false, 1500)"
                                        class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-2">
                                    <span x-show="!copiado"><i class="fa-solid fa-link"></i> Copiar link</span>
                                    <span x-show="copiado" x-cloak class="text-emerald-600"><i class="fa-solid fa-check"></i> Copiado</span>
                                </button>
                                @if($domi->whatsappUrl())
                                    <a href="{{ $domi->whatsappUrl() }}?text={{ urlencode('Hola ' . explode(' ', $domi->nombre)[0] . ', estos son tus pedidos de hoy con la ruta óptima:%0A' . $domi->urlPortal()) }}"
                                       target="_blank"
                                       class="rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-2 text-xs font-bold"
                                       title="Enviar por WhatsApp">
                                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl bg-white p-10 text-center text-slate-400 shadow">
                    <i class="fa-solid fa-motorcycle text-4xl mb-3 block opacity-50"></i>
                    <p class="text-sm">No hay domiciliarios activos. Crea uno en
                        <a href="{{ route('domiciliarios.index') }}" class="text-brand-secondary underline font-bold">/domiciliarios</a>.</p>
                </div>
            @endforelse
        </div>

        {{-- Columna lateral: pedidos sin asignar --}}
        <div class="space-y-4">
            <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 bg-gradient-to-r from-amber-50 to-white border-b border-slate-200">
                    <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-amber-500"></i>
                        Pedidos sin asignar
                        <span class="ml-auto text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-extrabold">{{ $sinAsignar->count() }}</span>
                    </h3>
                </div>
                <div class="max-h-[600px] overflow-y-auto">
                    @forelse($sinAsignar as $p)
                        <div class="px-4 py-3 border-b border-slate-100 hover:bg-slate-50">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-slate-800 text-sm">#{{ $p->id }}</span>
                                <span class="text-xs text-slate-500 truncate">{{ $p->cliente_nombre }}</span>
                            </div>
                            <div class="text-xs text-slate-600 mb-2">
                                <i class="fa-solid fa-location-dot text-rose-400 mr-1"></i>
                                {{ $p->barrio ?? $p->direccion ?? 'Sin dirección' }}
                            </div>
                            <div class="text-xs text-slate-500 mb-2">
                                <i class="fa-solid fa-money-bill text-emerald-500 mr-1"></i>${{ number_format((float) $p->total, 0, ',', '.') }}
                            </div>
                            {{-- Asignación rápida --}}
                            <select wire:change="asignarPedido({{ $p->id }}, $event.target.value); $event.target.value = ''"
                                    class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-brand">
                                <option value="">— Asignar a... —</option>
                                @foreach($domiciliarios as $d)
                                    <option value="{{ $d->id }}">{{ $d->nombre }} ({{ ($rutas[$d->id]['total_pedidos'] ?? 0) }} en curso)</option>
                                @endforeach
                            </select>
                        </div>
                    @empty
                        <div class="px-4 py-10 text-center text-slate-400">
                            <i class="fa-regular fa-circle-check text-3xl mb-2 block opacity-50"></i>
                            <p class="text-xs">Todos los pedidos están asignados.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
