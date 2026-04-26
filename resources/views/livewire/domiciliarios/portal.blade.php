<div class="min-h-screen bg-slate-100 pb-20"
     x-data="domiPortal()"
     x-init="init()"
     wire:poll.30s>

    {{-- Header con identidad del domiciliario --}}
    <div class="text-white px-5 py-6 shadow-lg"
         style="background: linear-gradient(135deg, var(--brand-primary, #d68643), var(--brand-secondary, #a85f24));">
        <div class="max-w-2xl mx-auto">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-12 w-12 rounded-full bg-white/25 flex items-center justify-center text-2xl font-extrabold backdrop-blur">
                    {{ mb_substr($domiciliario->nombre, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-extrabold leading-tight">¡Hola {{ explode(' ', $domiciliario->nombre)[0] }}!</h1>
                    <p class="text-xs opacity-80">
                        <i class="fa-solid fa-motorcycle"></i>
                        {{ ucfirst($domiciliario->vehiculo ?? 'Domiciliario') }}
                        @if($domiciliario->placa) · {{ $domiciliario->placa }} @endif
                    </p>
                </div>
                <button @click="actualizarUbicacion()"
                        :disabled="cargandoUbicacion"
                        class="ml-auto h-10 w-10 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition disabled:opacity-50"
                        title="Actualizar mi ubicación">
                    <i class="fa-solid fa-location-crosshairs" :class="cargandoUbicacion ? 'fa-spin' : ''"></i>
                </button>
            </div>

            {{-- KPIs del día --}}
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-white/15 backdrop-blur px-3 py-2.5 text-center">
                    <div class="text-2xl font-extrabold">{{ $this->pedidosActivos->count() }}</div>
                    <div class="text-[10px] uppercase tracking-wider opacity-80 font-bold">Pendientes</div>
                </div>
                <div class="rounded-xl bg-white/15 backdrop-blur px-3 py-2.5 text-center">
                    <div class="text-2xl font-extrabold">{{ $this->entregadosHoy }}</div>
                    <div class="text-[10px] uppercase tracking-wider opacity-80 font-bold">Entregados</div>
                </div>
                <div class="rounded-xl bg-white/15 backdrop-blur px-3 py-2.5 text-center">
                    <div class="text-2xl font-extrabold">{{ $this->totalPedidosHoy }}</div>
                    <div class="text-[10px] uppercase tracking-wider opacity-80 font-bold">Total hoy</div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

        {{-- Botón ruta optimizada --}}
        @if($this->urlRutaOptima)
            <a href="{{ $this->urlRutaOptima }}" target="_blank" rel="noopener"
               class="block rounded-2xl bg-white border-2 border-emerald-300 hover:border-emerald-400 shadow hover:shadow-lg transition p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 text-2xl flex-shrink-0">
                        <i class="fa-solid fa-route"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-extrabold text-slate-800">Ver ruta óptima</div>
                        <div class="text-xs text-slate-500">
                            {{ $this->pedidosActivos->count() }} parada(s) · Optimizado por cercanía
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-emerald-600">
                        <i class="fa-brands fa-google text-xl"></i>
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>
            </a>
        @endif

        {{-- Lista de pedidos en orden óptimo --}}
        @if($this->pedidosOrdenados->isEmpty())
            <div class="rounded-2xl bg-white p-10 text-center text-slate-400 shadow">
                <i class="fa-regular fa-circle-check text-5xl mb-3 text-emerald-300"></i>
                <h3 class="font-bold text-slate-700 mb-1">¡Vas al día!</h3>
                <p class="text-sm">No tienes pedidos asignados ahora mismo. Disfruta el descanso 🙌</p>
            </div>
        @else
            <div class="text-xs text-slate-500 font-bold uppercase tracking-wider mt-4 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-list-check"></i>
                Pedidos en orden de entrega
            </div>

            @foreach($this->pedidosOrdenados as $i => $p)
                @php
                    $colores = [
                        'nuevo' => ['bg-blue-100 text-blue-700', 'fa-circle-dot'],
                        'en_preparacion' => ['bg-amber-100 text-amber-700', 'fa-fire'],
                        'repartidor_en_camino' => ['bg-violet-100 text-violet-700', 'fa-motorcycle'],
                    ];
                    [$colorEstado, $iconoEstado] = $colores[$p->estado] ?? ['bg-slate-100 text-slate-700', 'fa-clock'];
                @endphp
                <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">
                    {{-- Header con número de orden y estado --}}
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full text-white font-extrabold text-sm flex-shrink-0"
                             style="background: linear-gradient(135deg, var(--brand-primary, #d68643), var(--brand-secondary, #a85f24));">
                            {{ $i + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-slate-800">Pedido #{{ $p->id }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ $p->cliente_nombre }}</div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-bold {{ $colorEstado }}">
                            <i class="fa-solid {{ $iconoEstado }}"></i>
                            {{ ucfirst(str_replace('_', ' ', $p->estado)) }}
                        </span>
                    </div>

                    <div class="p-4 space-y-3">
                        {{-- Dirección --}}
                        <div class="flex items-start gap-2 text-sm text-slate-700">
                            <i class="fa-solid fa-location-dot text-rose-500 mt-1 flex-shrink-0"></i>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium">{{ $p->direccion ?? 'Sin dirección' }}</div>
                                @if($p->barrio)
                                    <div class="text-xs text-slate-500">{{ $p->barrio }}</div>
                                @endif
                            </div>
                        </div>

                        {{-- Teléfono y total --}}
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <a href="tel:+{{ preg_replace('/\D+/', '', $p->telefono_contacto ?? $p->telefono_whatsapp ?? '') }}"
                               class="inline-flex items-center gap-2 text-slate-700 hover:text-emerald-600">
                                <i class="fa-solid fa-phone text-emerald-500"></i>
                                <span class="font-mono">{{ $p->telefono_contacto ?? $p->telefono_whatsapp }}</span>
                            </a>
                            <span class="font-extrabold text-slate-800">${{ number_format((float) $p->total, 0, ',', '.') }}</span>
                        </div>

                        {{-- Token de entrega si existe --}}
                        @if($p->token_entrega && $p->estado === 'repartidor_en_camino')
                            <div class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-xs flex items-center gap-2">
                                <i class="fa-solid fa-key text-amber-600"></i>
                                <div>
                                    <div class="font-bold text-amber-800">Código del cliente:</div>
                                    <div class="text-amber-700">Pídele al cliente este número: <strong class="text-base font-mono">{{ $p->token_entrega }}</strong></div>
                                </div>
                            </div>
                        @endif

                        {{-- Botones de acción --}}
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            @if($p->lat && $p->lng)
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $p->lat }},{{ $p->lng }}&travelmode=driving"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-sm py-2.5 transition">
                                    <i class="fa-brands fa-google"></i> Ir
                                </a>
                            @endif

                            @if($p->estado === 'en_preparacion' || $p->estado === 'nuevo')
                                <button wire:click="marcarEnCamino({{ $p->id }})"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-bold text-sm py-2.5 transition">
                                    <i class="fa-solid fa-motorcycle"></i> Salir
                                </button>
                            @elseif($p->estado === 'repartidor_en_camino')
                                <button wire:click="marcarEntregado({{ $p->id }})"
                                        wire:confirm="¿Confirmar que entregaste el pedido #{{ $p->id }}?"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm py-2.5 transition">
                                    <i class="fa-solid fa-check"></i> Entregado
                                </button>
                            @endif
                        </div>

                        {{-- Notas del pedido --}}
                        @if($p->notas)
                            <div class="text-[11px] text-slate-500 italic border-t border-slate-100 pt-2">
                                <i class="fa-regular fa-note-sticky mr-1"></i> {{ $p->notas }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Footer info --}}
        <div class="text-center text-xs text-slate-400 pt-6">
            <i class="fa-solid fa-rotate"></i> Se actualiza automáticamente cada 30 segundos
        </div>
    </div>

    <script>
        function domiPortal() {
            return {
                cargandoUbicacion: false,
                init() {
                    // Pedir ubicación al entrar (con permiso del usuario)
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            pos => this.$wire.actualizarMiUbicacion(pos.coords.latitude, pos.coords.longitude),
                            () => {} // si rechaza, no pasa nada
                        );
                    }
                },
                async actualizarUbicacion() {
                    if (!navigator.geolocation) {
                        alert('Tu navegador no soporta geolocalización.');
                        return;
                    }
                    this.cargandoUbicacion = true;
                    navigator.geolocation.getCurrentPosition(
                        pos => {
                            this.$wire.actualizarMiUbicacion(pos.coords.latitude, pos.coords.longitude);
                            this.cargandoUbicacion = false;
                        },
                        err => {
                            alert('No pudimos obtener tu ubicación: ' + err.message);
                            this.cargandoUbicacion = false;
                        }
                    );
                }
            }
        }
    </script>
</div>
