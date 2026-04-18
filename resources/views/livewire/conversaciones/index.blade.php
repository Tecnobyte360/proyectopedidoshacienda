<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6">
        <h2 class="text-3xl font-extrabold text-slate-800">Conversaciones</h2>
        <p class="text-sm text-slate-500">Todas las charlas del bot con cada cliente — guardadas en BD para siempre.</p>
    </div>

    {{-- KPIS --}}
    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totales['total'] }}</div>
                    <div class="text-xs text-slate-500">Total</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-circle-dot"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totales['activas'] }}</div>
                    <div class="text-xs text-slate-500">Activas ahora</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totales['con_pedido'] }}</div>
                    <div class="text-xs text-slate-500">Generaron pedido</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-[#d68643] to-[#a85f24] p-4 shadow text-white">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                    <i class="fa-solid fa-message"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold">{{ number_format($totales['mensajes']) }}</div>
                    <div class="text-xs opacity-80">Mensajes totales</div>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Buscar por nombre, teléfono..."
               class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-[#d68643] focus:ring-[#d68643]">

        <select wire:model.live="filtroEstado"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
            <option value="todas">Todos los estados</option>
            <option value="activa">Activas</option>
            <option value="cerrada">Cerradas</option>
            <option value="con_pedido">Con pedido</option>
            <option value="sin_pedido">Sin pedido</option>
        </select>

        <select wire:model.live="orden"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
            <option value="recientes">Más recientes</option>
            <option value="mas_mensajes">Más mensajes</option>
            <option value="antiguas">Más antiguas</option>
        </select>
    </div>

    {{-- LISTA --}}
    <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">
        @forelse($conversaciones as $c)
            @php
                $iniciales = collect(explode(' ', trim($c->cliente?->nombre ?? 'C')))
                    ->filter()->take(2)
                    ->map(fn($p) => mb_substr($p, 0, 1))
                    ->implode('');
            @endphp

            <div wire:click="ver({{ $c->id }})"
                 class="flex items-center gap-4 px-4 py-3 border-b border-slate-100 hover:bg-amber-50/30 cursor-pointer transition">

                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white font-bold text-sm">
                    {{ $iniciales ?: 'C' }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-800 truncate">{{ $c->cliente?->nombre ?? 'Cliente' }}</span>
                        @if($c->estado === 'activa')
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Activa
                            </span>
                        @elseif($c->estado === 'cerrada')
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 uppercase">
                                Cerrada
                            </span>
                        @endif
                        @if($c->genero_pedido)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                <i class="fa-solid fa-bag-shopping"></i>
                                #{{ str_pad($c->pedido_id, 3, '0', STR_PAD_LEFT) }}
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-500 truncate">
                        {{ $c->telefono_normalizado }} · {{ $c->total_mensajes }} mensajes
                        ({{ $c->total_mensajes_cliente }} cliente / {{ $c->total_mensajes_bot }} bot)
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <div class="text-xs text-slate-700">{{ $c->ultimo_mensaje_at?->diffForHumans() }}</div>
                    <div class="text-[10px] text-slate-400">{{ $c->ultimo_mensaje_at?->format('d/m/Y H:i') }}</div>
                </div>

                <button onclick="event.stopPropagation()"
                        @click.prevent="$dispatch('confirm-show', {
                            title: 'Eliminar conversación',
                            message: 'Se borrarán todos los mensajes. ¿Seguro?',
                            confirmText: 'Sí, eliminar',
                            type: 'danger',
                            onConfirm: () => $wire.eliminar({{ $c->id }}),
                        })"
                        class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-500 transition">
                    <i class="fa-solid fa-trash text-xs"></i>
                </button>
            </div>
        @empty
            <div class="p-12 text-center text-slate-400">
                <i class="fa-solid fa-comments text-4xl mb-3 block"></i>
                <p class="text-sm">Aún no hay conversaciones registradas.</p>
                <p class="text-xs mt-1">Llegarán solas cuando alguien escriba al bot.</p>
            </div>
        @endforelse

        <div class="p-3 border-t border-slate-100">
            {{ $conversaciones->links() }}
        </div>
    </div>

    {{-- ╔═══ MODAL DE DETALLE DE CONVERSACIÓN ═══╗ --}}
    @if($verConversacion)
        @php
            $iniVer = collect(explode(' ', trim($verConversacion->cliente?->nombre ?? 'C')))
                ->filter()->take(2)
                ->map(fn($p) => mb_substr($p, 0, 1))
                ->implode('');
        @endphp

        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 overflow-y-auto"
             wire:click.self="cerrarVer"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">

            <div class="w-full sm:max-w-3xl rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl my-0 sm:my-8 max-h-[95vh] flex flex-col">

                {{-- Header --}}
                <div class="bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white px-6 py-4 sm:rounded-t-2xl flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 backdrop-blur text-lg font-bold">
                            {{ $iniVer ?: 'C' }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-lg truncate">{{ $verConversacion->cliente?->nombre ?? 'Cliente' }}</h3>
                            <div class="text-xs text-white/80">
                                <i class="fa-solid fa-phone mr-1"></i>
                                <span class="font-mono">{{ $verConversacion->telefono_normalizado }}</span>
                                · {{ $verConversacion->total_mensajes }} mensajes
                            </div>
                        </div>
                    </div>
                    <button wire:click="cerrarVer"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20 hover:bg-white/30 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                {{-- Outcomes bar --}}
                @if($verConversacion->genero_pedido && $verConversacion->pedido)
                    <div class="bg-emerald-50 border-b border-emerald-200 px-6 py-2 text-xs text-emerald-800 flex items-center gap-2">
                        <i class="fa-solid fa-circle-check"></i>
                        Esta conversación generó el pedido
                        <strong>#{{ str_pad($verConversacion->pedido->id, 3, '0', STR_PAD_LEFT) }}</strong>
                        — Total ${{ number_format($verConversacion->pedido->total, 0, ',', '.') }}
                    </div>
                @endif

                {{-- Mensajes (estilo WhatsApp) --}}
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2 bg-[#efeae2]">
                    @foreach($verConversacion->mensajes as $m)
                        @if($m->rol === 'user')
                            <div class="flex justify-start">
                                <div class="max-w-[80%] rounded-2xl rounded-tl-sm bg-white px-4 py-2 shadow-sm">
                                    <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                    <p class="text-[10px] text-slate-400 mt-1 text-right">
                                        {{ $m->created_at->format('H:i') }}
                                    </p>
                                </div>
                            </div>
                        @elseif($m->rol === 'assistant')
                            <div class="flex justify-end">
                                <div class="max-w-[80%] rounded-2xl rounded-tr-sm bg-[#dcf8c6] px-4 py-2 shadow-sm">
                                    @if($m->tipo === 'tool_call' && isset($m->meta['imagenes_enviadas']))
                                        <div class="text-[10px] uppercase font-bold text-emerald-700 mb-1">
                                            <i class="fa-solid fa-camera"></i>
                                            Envió {{ $m->meta['imagenes_enviadas'] }} imagen(es)
                                        </div>
                                    @endif
                                    <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                    <p class="text-[10px] text-slate-500 mt-1 text-right">
                                        🤖 {{ $m->created_at->format('H:i') }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Footer info --}}
                <div class="border-t border-slate-200 px-4 py-3 bg-slate-50 text-xs text-slate-600 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <i class="fa-solid fa-clock mr-1"></i>
                        Inició {{ $verConversacion->primer_mensaje_at?->diffForHumans() }} ·
                        Último {{ $verConversacion->ultimo_mensaje_at?->diffForHumans() }}
                    </div>
                    <div class="flex gap-2">
                        @if($verConversacion->cliente)
                            <a href="{{ $verConversacion->cliente->whatsappUrl() }}" target="_blank"
                               class="rounded-lg bg-green-500 px-3 py-1.5 text-white text-xs font-semibold hover:bg-green-600 transition">
                                <i class="fa-brands fa-whatsapp mr-1"></i> Continuar en WhatsApp
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
