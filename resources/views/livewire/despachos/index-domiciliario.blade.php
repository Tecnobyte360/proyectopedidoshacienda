@if(!$domiActual)
<div class="px-4 lg:px-10 py-12 max-w-2xl mx-auto text-center">
    <div class="rounded-2xl border-2 border-amber-200 bg-amber-50 p-8">
        <i class="fa-solid fa-triangle-exclamation text-5xl text-amber-500 mb-4"></i>
        <h2 class="text-xl font-bold text-slate-800 mb-2">Tu cuenta no está vinculada</h2>
        <p class="text-sm text-slate-600 mb-4">
            Tu usuario tiene rol <strong>domiciliario</strong> pero todavía NO está vinculado a un
            perfil en el sistema. Pide a un administrador que te vincule en
            <code class="bg-white px-1 rounded">/domiciliarios</code>.
        </p>
        <p class="text-xs text-slate-500">
            Mientras tanto, contacta a tu encargado para que reciba los pedidos manualmente.
        </p>
    </div>
</div>
@else
<div class="px-4 lg:px-10 py-6 max-w-3xl mx-auto" wire:poll.30s>

    {{-- Card de bienvenida con stats --}}
    <div class="rounded-3xl bg-gradient-to-br from-brand to-brand-dark text-white p-6 shadow-xl">
        <div class="flex items-center gap-4 mb-6">
            <div class="h-16 w-16 rounded-full bg-white/20 flex items-center justify-center text-2xl font-extrabold">
                {{ mb_substr($domiActual->nombre, 0, 1) }}
            </div>
            <div>
                <h2 class="text-2xl font-extrabold">¡Hola {{ explode(' ', $domiActual->nombre)[0] }}!</h2>
                <p class="text-white/80 text-sm">
                    <i class="fa-solid fa-motorcycle"></i>
                    {{ $domiActual->vehiculo ?: 'Vehículo' }} · {{ $domiActual->placa ?: '—' }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl bg-white/15 backdrop-blur-sm p-4 text-center">
                <div class="text-3xl font-black">{{ $statsDomi['pendientes'] }}</div>
                <div class="text-[11px] uppercase tracking-wider mt-1 text-white/80">Pendientes</div>
            </div>
            <div class="rounded-2xl bg-white/15 backdrop-blur-sm p-4 text-center">
                <div class="text-3xl font-black">{{ $statsDomi['entregados'] }}</div>
                <div class="text-[11px] uppercase tracking-wider mt-1 text-white/80">Entregados</div>
            </div>
            <div class="rounded-2xl bg-white/15 backdrop-blur-sm p-4 text-center">
                <div class="text-3xl font-black">{{ $statsDomi['total_hoy'] }}</div>
                <div class="text-[11px] uppercase tracking-wider mt-1 text-white/80">Total hoy</div>
            </div>
        </div>
    </div>

    {{-- Botón ruta óptima --}}
    @if($rutaOptimaUrl && $pedidosOrdenados->count() > 0)
        <a href="{{ $rutaOptimaUrl }}" target="_blank" rel="noopener"
           class="mt-4 flex items-center justify-between rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4 hover:bg-emerald-100 transition group">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-xl">
                    <i class="fa-solid fa-route"></i>
                </div>
                <div>
                    <div class="font-bold text-emerald-900">Ver ruta óptima en Google Maps</div>
                    <div class="text-xs text-emerald-700">{{ $pedidosOrdenados->count() }} parada(s) · Optimizado por cercanía</div>
                </div>
            </div>
            <i class="fa-brands fa-google text-2xl text-emerald-600 group-hover:scale-110 transition"></i>
        </a>
    @endif

    {{-- Lista de pedidos en orden --}}
    <div class="mt-6">
        <h3 class="text-xs uppercase tracking-wider text-slate-500 font-bold mb-3">
            <i class="fa-solid fa-list-ol"></i> Pedidos en orden de entrega
        </h3>

        @if($pedidosOrdenados->isEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">
                <i class="fa-solid fa-inbox text-3xl mb-2 opacity-50"></i>
                <p class="text-sm">No tienes pedidos pendientes ahora mismo.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($pedidosOrdenados as $i => $p)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-brand text-white flex items-center justify-center font-bold">
                                    {{ $i + 1 }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800">Pedido #{{ $p->id }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->cliente_nombre ?: 'Cliente' }}</div>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[10px] font-bold">
                                @if($p->estado === \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO) <i class="fa-solid fa-motorcycle"></i> En camino
                                @elseif($p->estado === \App\Models\Pedido::ESTADO_EN_PREPARACION) <i class="fa-solid fa-user"></i>‍<i class="fa-solid fa-egg"></i> En preparación
                                @else {{ $p->estado }}
                                @endif
                            </span>
                        </div>

                        <div class="text-sm text-slate-700 mb-2">
                            <i class="fa-solid fa-location-dot text-rose-500"></i>
                            {{ $p->direccion ?: 'Sin dirección' }}{{ $p->barrio ? ', ' . $p->barrio : '' }}
                        </div>
                        <div class="flex items-center justify-between mb-3">
                            @if($p->telefono_contacto ?: $p->telefono_whatsapp)
                                <a href="tel:{{ $p->telefono_contacto ?: $p->telefono_whatsapp }}" class="text-sm text-emerald-700 hover:underline">
                                    <i class="fa-solid fa-phone"></i> {{ $p->telefono_contacto ?: $p->telefono_whatsapp }}
                                </a>
                            @else
                                <span class="text-sm text-slate-400">Sin teléfono</span>
                            @endif
                            <span class="font-bold text-slate-800">${{ number_format((float) $p->total, 0, ',', '.') }}</span>
                        </div>

                        {{-- 🔑 Código de entrega visible para el domiciliario --}}
                        @if($p->token_entrega && $p->estado === \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)
                            <div x-data="{ ver: false }" class="rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 p-2.5 mb-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold text-amber-900">
                                        <i class="fa-solid fa-key"></i> Código del cliente:
                                    </span>
                                    <button type="button" @click="ver = !ver"
                                            class="text-[11px] font-bold text-amber-700 underline">
                                        <span x-show="!ver"><i class="fa-solid fa-eye"></i> Ver</span>
                                        <span x-show="ver" x-cloak><i class="fa-solid fa-eye-slash"></i> Ocultar</span>
                                    </button>
                                </div>
                                <div x-show="ver" x-cloak class="text-center mt-1">
                                    <span class="font-mono text-2xl font-black tracking-[0.4em] text-amber-900">{{ $p->token_entrega }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-2">
                            @if($p->lat && $p->lng)
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $p->lat }},{{ $p->lng }}&travelmode=driving"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 py-2.5 text-sm font-bold text-slate-700">
                                    <i class="fa-brands fa-google"></i> Ir
                                </a>
                            @elseif($p->direccion)
                                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($p->direccion ?? '') . ' ' . ($p->barrio ?? '')) }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 py-2.5 text-sm font-bold text-slate-700">
                                    <i class="fa-brands fa-google"></i> Ir
                                </a>
                            @else
                                <span class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 py-2.5 text-sm font-bold text-slate-400 cursor-not-allowed">
                                    <i class="fa-solid fa-location-crosshairs"></i> Sin GPS
                                </span>
                            @endif

                            @if($p->estado === \App\Models\Pedido::ESTADO_REPARTIDOR_EN_CAMINO)
                                <button type="button" wire:click="marcarEntregado({{ $p->id }})"
                                        wire:confirm="¿Marcar pedido #{{ $p->id }} como ENTREGADO?"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 text-sm font-bold">
                                    <i class="fa-solid fa-circle-check"></i> Entregado
                                </button>
                            @else
                                <button type="button" wire:click="marcarEnCamino({{ $p->id }})"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white py-2.5 text-sm font-bold">
                                    <i class="fa-solid fa-motorcycle"></i> Salir
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">
        <i class="fa-solid fa-arrows-rotate"></i> Se actualiza cada 30 segundos
    </p>
</div>
@endif
