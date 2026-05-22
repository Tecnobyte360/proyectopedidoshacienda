<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        <div wire:poll.30000ms="refreshEstadoWa"
             class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                {{-- Título --}}
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-bullhorn text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Campañas WhatsApp</h2>
                        <p class="text-sm text-slate-500">Envío masivo pausado para evitar baneos. Configura intervalos, lotes y ventana horaria.</p>
                    </div>
                </div>

                {{-- Centro: badge sesión WA --}}
                <div class="flex items-center gap-2 px-3 py-2 rounded-xl border {{ $waConectado ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-100 border-slate-200' }}">
                    <i class="fa-brands fa-whatsapp text-base {{ $waConectado ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                    <div>
                        <p class="text-[11px] font-bold flex items-center gap-1 {{ $waConectado ? 'text-emerald-700' : 'text-slate-500' }}">
                            <span class="relative flex h-2 w-2">
                                @if($waConectado)
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                @endif
                                <span class="relative inline-flex rounded-full h-2 w-2 {{ $waConectado ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            </span>
                            {{ $waConectado ? 'Conectado' : ($waStatus === 'PAIRING' ? 'Esperando QR' : ($waStatus === 'DISCONNECTED' ? 'Desconectado' : 'Verificando…')) }}
                        </p>
                        @if($waPhone)
                            <p class="text-[11px] font-mono font-semibold text-slate-700 leading-tight">{{ $waPhone }}</p>
                        @elseif($waConnectionId)
                            <p class="text-[10px] font-mono text-slate-400 leading-tight">ID: {{ $waConnectionId }}</p>
                        @else
                            <p class="text-[10px] text-slate-400 leading-tight">Sin número detectado</p>
                        @endif
                    </div>
                </div>

                {{-- Botón nueva campaña --}}
                <button wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nueva campaña
                </button>
            </div>
        </div>

        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-800">
            <p class="font-bold mb-1"><i class="fa-solid fa-shield-halved"></i> Anti-baneo activado</p>
            <p>El envío usa intervalos aleatorios entre cada mensaje y descansos por lote para evitar que WhatsApp banee tu número (ya que estamos usando whatsapp-web.js, no la API oficial de Meta). Asegúrate que el cron <code class="bg-white px-1 rounded">campanas:procesar</code> corra cada minuto.</p>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Audiencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Progreso</th>
                        <th class="px-4 py-3">Programada</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($campanas as $c)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-800">{{ $c->nombre }}</p>
                                <p class="text-[11px] text-slate-500 truncate max-w-[260px]">{{ mb_strimwidth($c->mensaje, 0, 80, '…') }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono">{{ $c->audiencia_tipo }}</span>
                                <p class="text-slate-500 mt-0.5">{{ $c->total_destinatarios }} destinatarios</p>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $colorEstado = [
                                        'borrador'   => 'bg-slate-100 text-slate-700',
                                        'programada' => 'bg-blue-100 text-blue-700',
                                        'corriendo'  => 'bg-emerald-100 text-emerald-700',
                                        'pausada'    => 'bg-amber-100 text-amber-700',
                                        'completada' => 'bg-violet-100 text-violet-700',
                                        'cancelada'  => 'bg-rose-100 text-rose-700',
                                    ][$c->estado] ?? 'bg-slate-100';
                                @endphp
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $colorEstado }}">
                                    {{ ucfirst($c->estado) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="flex flex-col gap-0.5">
                                    <span><i class="fa-solid fa-check text-emerald-500"></i> {{ $c->total_enviados }}</span>
                                    <span><i class="fa-solid fa-xmark text-rose-500"></i> {{ $c->total_fallidos }}</span>
                                    <span><i class="fa-regular fa-clock text-slate-400"></i> {{ $c->total_pendientes }}</span>
                                </div>
                                @if($c->total_destinatarios > 0)
                                    @php $pct = (int) round((($c->total_enviados + $c->total_fallidos) / $c->total_destinatarios) * 100); @endphp
                                    <div class="w-full h-1.5 bg-slate-100 rounded-full mt-1">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $c->programada_para?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if($c->estado === 'borrador' || $c->estado === 'programada' || $c->estado === 'pausada')
                                        <button wire:click="iniciar({{ $c->id }})" title="Iniciar / reanudar"
                                                class="h-8 w-8 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 transition">
                                            <i class="fa-solid fa-play text-xs"></i>
                                        </button>
                                    @endif
                                    @if($c->estado === 'corriendo')
                                        <button wire:click="pausar({{ $c->id }})" title="Pausar"
                                                class="h-8 w-8 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 transition">
                                            <i class="fa-solid fa-pause text-xs"></i>
                                        </button>
                                    @endif
                                    <button wire:click="verProgreso({{ $c->id }})" title="Ver progreso en vivo"
                                            class="h-8 w-8 rounded-lg bg-sky-100 hover:bg-sky-200 text-sky-700 transition relative">
                                        <i class="fa-solid fa-chart-line text-xs"></i>
                                        @if($c->estado === 'corriendo')
                                            <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 border-2 border-white"></span>
                                            </span>
                                        @endif
                                    </button>
                                    <button wire:click="generarAudiencia({{ $c->id }})" title="Generar/regenerar audiencia"
                                            class="h-8 w-8 rounded-lg bg-violet-100 hover:bg-violet-200 text-violet-700 transition">
                                        <i class="fa-solid fa-users text-xs"></i>
                                    </button>
                                    <button wire:click="abrirEditar({{ $c->id }})" title="Editar"
                                            class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    @if(in_array($c->estado, ['programada','corriendo','pausada']))
                                        <button wire:click="cancelar({{ $c->id }})" wire:confirm="¿Cancelar campaña?"
                                                class="h-8 w-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition" title="Cancelar">
                                            <i class="fa-solid fa-ban text-xs"></i>
                                        </button>
                                    @endif
                                    <button wire:click="eliminar({{ $c->id }})" wire:confirm="¿Eliminar campaña?"
                                            class="h-8 w-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition" title="Eliminar">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">
                            <i class="fa-solid fa-bullhorn text-3xl text-slate-300 mb-2 block"></i>
                            Sin campañas. Crea la primera para enviar mensajes masivos a tus clientes.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($campanas->hasPages())
                <div class="p-4 border-t border-slate-100">{{ $campanas->links() }}</div>
            @endif
        </div>
    </div>

    {{-- 📡 MODAL MONITOR EN VIVO --}}
    @if($monitoreoId && $monitorCampana)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarMonitor"
             wire:poll.3s>
            <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>

                {{-- Header --}}
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-sky-50 via-white to-emerald-50">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-emerald-500 text-white shadow-lg">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-800 flex items-center gap-2">
                                {{ $monitorCampana->nombre }}
                                @if($monitorCampana->estado === 'corriendo')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                        </span>
                                        En vivo · actualiza cada 3s
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                        {{ ucfirst($monitorCampana->estado) }}
                                    </span>
                                @endif
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ $monitorEstadisticas['total'] }} destinatarios ·
                                Iniciada {{ $monitorCampana->iniciada_at?->diffForHumans() ?? '—' }}
                                @if($monitorCampana->completada_at)
                                    · Finalizada {{ $monitorCampana->completada_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <button wire:click="cerrarMonitor" class="text-slate-400 hover:text-slate-700 transition">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">

                    {{-- KPIs grandes --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-4 text-white shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider opacity-80">Enviados</span>
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="text-3xl font-extrabold">{{ $monitorEstadisticas['enviado'] }}</div>
                        </div>
                        <div class="rounded-2xl bg-gradient-to-br from-rose-500 to-rose-600 p-4 text-white shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider opacity-80">Fallidos</span>
                                <i class="fa-solid fa-circle-xmark"></i>
                            </div>
                            <div class="text-3xl font-extrabold">{{ $monitorEstadisticas['fallido'] }}</div>
                        </div>
                        <div class="rounded-2xl bg-gradient-to-br from-amber-400 to-amber-500 p-4 text-white shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider opacity-90">Pendientes</span>
                                <i class="fa-regular fa-clock"></i>
                            </div>
                            <div class="text-3xl font-extrabold">{{ $monitorEstadisticas['pendiente'] }}</div>
                        </div>
                        <div class="rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 p-4 text-white shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider opacity-80">Total</span>
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="text-3xl font-extrabold">{{ $monitorEstadisticas['total'] }}</div>
                        </div>
                    </div>

                    {{-- Barra de progreso --}}
                    <div>
                        <div class="flex items-center justify-between mb-2 text-xs">
                            <span class="font-bold text-slate-700">Progreso</span>
                            <span class="font-extrabold text-emerald-600">{{ $monitorEstadisticas['pct'] }}%</span>
                        </div>
                        <div class="w-full h-3 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-emerald-500 via-emerald-400 to-sky-500 rounded-full transition-all duration-500"
                                 style="width: {{ $monitorEstadisticas['pct'] }}%"></div>
                        </div>
                        <p class="text-[10px] text-slate-500 mt-1 text-center">
                            {{ $monitorEstadisticas['enviado'] + $monitorEstadisticas['fallido'] }} procesados de {{ $monitorEstadisticas['total'] }}
                        </p>
                    </div>

                    {{-- Filtros + acciones --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button wire:click="$set('filtroMonitor', 'todos')"
                                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition
                                           {{ $filtroMonitor === 'todos' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                Todos
                            </button>
                            <button wire:click="$set('filtroMonitor', 'enviado')"
                                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition flex items-center gap-1
                                           {{ $filtroMonitor === 'enviado' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                <i class="fa-solid fa-check"></i> Enviados
                            </button>
                            <button wire:click="$set('filtroMonitor', 'fallido')"
                                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition flex items-center gap-1
                                           {{ $filtroMonitor === 'fallido' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}">
                                <i class="fa-solid fa-xmark"></i> Fallidos
                            </button>
                            <button wire:click="$set('filtroMonitor', 'pendiente')"
                                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition flex items-center gap-1
                                           {{ $filtroMonitor === 'pendiente' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                                <i class="fa-regular fa-clock"></i> Pendientes
                            </button>
                        </div>
                        @if($monitorEstadisticas['fallido'] > 0)
                            <button wire:click="reintentarFallidos({{ $monitorCampana->id }})"
                                    wire:confirm="¿Reintentar los {{ $monitorEstadisticas['fallido'] }} fallidos?"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold px-3 py-1.5 transition shadow-sm">
                                <i class="fa-solid fa-rotate-right"></i>
                                Reintentar fallidos ({{ $monitorEstadisticas['fallido'] }})
                            </button>
                        @endif
                    </div>

                    {{-- Lista de destinatarios --}}
                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                                <tr>
                                    <th class="px-3 py-2 text-left">Estado</th>
                                    <th class="px-3 py-2 text-left">Nombre</th>
                                    <th class="px-3 py-2 text-left">Teléfono</th>
                                    <th class="px-3 py-2 text-left">Enviado</th>
                                    <th class="px-3 py-2 text-left">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($monitorDestinatarios as $d)
                                    @php
                                        $estilo = match($d->estado) {
                                            'enviado'   => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'icon' => 'fa-check', 'label' => 'Enviado'],
                                            'fallido'   => ['bg' => 'bg-rose-100',    'text' => 'text-rose-700',    'dot' => 'bg-rose-500',    'icon' => 'fa-xmark', 'label' => 'Fallido'],
                                            'pendiente' => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dot' => 'bg-amber-500',   'icon' => 'fa-clock', 'label' => 'Pendiente'],
                                            'omitido'   => ['bg' => 'bg-slate-100',   'text' => 'text-slate-600',   'dot' => 'bg-slate-400',   'icon' => 'fa-ban',   'label' => 'Omitido'],
                                            default     => ['bg' => 'bg-slate-100',   'text' => 'text-slate-600',   'dot' => 'bg-slate-400',   'icon' => 'fa-circle', 'label' => $d->estado],
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center gap-1.5 rounded-full {{ $estilo['bg'] }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $estilo['text'] }}">
                                                <i class="fa-solid {{ $estilo['icon'] }}"></i>
                                                {{ $estilo['label'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-800 font-semibold truncate max-w-[180px]">{{ $d->nombre }}</td>
                                        <td class="px-3 py-2 text-slate-600 font-mono text-xs">{{ $d->telefono }}</td>
                                        <td class="px-3 py-2 text-xs text-slate-500">
                                            {{ $d->enviado_at?->diffForHumans() ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-500 truncate max-w-[280px]">
                                            @if($d->error_detalle)
                                                <span class="text-rose-600" title="{{ $d->error_detalle }}">
                                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                                    {{ Str::limit($d->error_detalle, 60) }}
                                                </span>
                                            @elseif($d->intentos > 0)
                                                <span class="text-slate-400">{{ $d->intentos }} intento(s)</span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                            <i class="fa-solid fa-inbox text-2xl block mb-2"></i>
                                            No hay destinatarios con este estado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-3 border-t border-slate-100 bg-slate-50 flex justify-end">
                    <button wire:click="cerrarMonitor" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <h3 class="font-bold text-slate-800">{{ $editandoId ? 'Editar' : 'Nueva' }} campaña</h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                        <input type="text" wire:model="nombre" placeholder="Promoción semana santa"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mensaje *</label>
                        <textarea wire:model="mensaje" rows="4" placeholder="¡Hola {primer_nombre}! Tenemos un 20% de descuento en chuletas hasta el domingo. ¿Te animas? 🥩"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"></textarea>
                        <p class="text-[10px] text-slate-500 mt-1">
                            Variables: <code>{nombre}</code>, <code>{primer_nombre}</code>, <code>{telefono}</code>
                        </p>
                    </div>

                    <div class="rounded-xl border-2 border-slate-200 p-4 space-y-3">
                        <h4 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-users text-brand"></i> Audiencia</h4>
                        <select wire:model.live="audienciaTipo" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="todos">Todos los clientes activos</option>
                            <option value="zona">Por zona de cobertura</option>
                            <option value="sede">Por sede (clientes con pedidos en esa sede)</option>
                            <option value="con_pedidos">Con N o más pedidos</option>
                            <option value="sin_pedidos">Que NO han pedido aún</option>
                            <option value="manual">Lista manual de teléfonos</option>
                        </select>

                        @if($audienciaTipo === 'zona')
                            <select wire:model="zonaId" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">— Selecciona zona —</option>
                                @foreach($zonas as $z)
                                    <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                        @elseif($audienciaTipo === 'sede')
                            <select wire:model="sedeId" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">— Selecciona sede —</option>
                                @foreach($sedes as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        @elseif($audienciaTipo === 'con_pedidos')
                            <input type="number" wire:model="minPedidos" min="1" placeholder="Mínimo de pedidos"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        @elseif($audienciaTipo === 'manual')
                            <textarea wire:model="telefonosManual" rows="4"
                                      placeholder="Pega los teléfonos uno por línea o separados por coma:&#10;573001234567&#10;573009876543"
                                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono"></textarea>
                        @endif
                    </div>

                    {{-- 📊 Importar Excel/CSV --}}
                    <div class="rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/40 p-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-2">
                            <i class="fa-solid fa-file-import text-emerald-600"></i> Importar Excel/CSV
                        </h4>
                        <p class="text-xs text-slate-600 mb-3">
                            Sube un archivo <code class="bg-white px-1 rounded">.xlsx</code>, <code class="bg-white px-1 rounded">.xls</code> o
                            <code class="bg-white px-1 rounded">.csv</code>. Detectará automáticamente la columna de teléfono (busca encabezados como
                            "telefono", "celular", "phone"). Los números se cargan al campo manual de arriba.
                        </p>
                        <input type="file" wire:model="archivoExcel" accept=".xlsx,.xls,.csv,.txt"
                               class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-emerald-600 file:text-white file:font-semibold file:cursor-pointer hover:file:bg-emerald-700">
                        <div wire:loading wire:target="archivoExcel" class="text-xs text-emerald-700 mt-2">
                            <i class="fa-solid fa-spinner fa-spin"></i> Procesando archivo…
                        </div>
                        @error('archivoExcel') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @if($numerosImportados > 0)
                            <p class="text-xs text-emerald-700 mt-2 font-semibold">
                                ✓ {{ $numerosImportados }} números importados.
                            </p>
                        @endif
                    </div>

                    {{-- 🖼️ Imagen del envío --}}
                    <div class="rounded-xl border-2 border-dashed border-sky-300 bg-sky-50/40 p-4">
                        <h4 class="font-bold text-slate-800 text-sm mb-2">
                            <i class="fa-solid fa-image text-sky-600"></i> Imagen (opcional)
                        </h4>
                        <p class="text-xs text-slate-600 mb-3">
                            Se enviará a cada destinatario junto con el mensaje como caption. Tamaño máx: 20 MB.
                        </p>
                        <input type="file" wire:model="imagen" accept="image/*"
                               class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-sky-600 file:text-white file:font-semibold file:cursor-pointer hover:file:bg-sky-700">
                        <div wire:loading wire:target="imagen" class="text-xs text-sky-700 mt-2">
                            <i class="fa-solid fa-spinner fa-spin"></i> Subiendo imagen…
                        </div>
                        @error('imagen') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror

                        @if($imagen)
                            <div class="mt-3 flex items-center gap-3">
                                <img src="{{ $imagen->temporaryUrl() }}" alt="Preview" class="w-24 h-24 object-cover rounded-xl border border-slate-200">
                                <button type="button" wire:click="$set('imagen', null)" class="text-xs text-rose-600 hover:underline">Quitar</button>
                            </div>
                        @elseif($mediaUrlExistente)
                            <div class="mt-3 flex items-center gap-3">
                                <img src="{{ $mediaUrlExistente }}" alt="Actual" class="w-24 h-24 object-cover rounded-xl border border-slate-200">
                                <button type="button" wire:click="$set('mediaUrlExistente', null)" class="text-xs text-rose-600 hover:underline">Quitar</button>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl border-2 border-amber-200 bg-amber-50/30 p-4 space-y-3">
                        <h4 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-shield-halved text-amber-600"></i> Anti-baneo (throttle)</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Intervalo MIN entre mensajes (seg)</label>
                                <input type="number" wire:model="intervaloMinSeg" min="1" max="300"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Intervalo MAX entre mensajes (seg)</label>
                                <input type="number" wire:model="intervaloMaxSeg" min="1" max="600"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tamaño de lote</label>
                                <input type="number" wire:model="loteTamano" min="1" max="500"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Descanso entre lotes (min)</label>
                                <input type="number" wire:model="descansoLoteMin" min="0" max="1440"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Ventana DESDE</label>
                                <input type="time" wire:model="ventanaDesde"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Ventana HASTA</label>
                                <input type="time" wire:model="ventanaHasta"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Programar para (opcional)</label>
                        <input type="datetime-local" wire:model="programadaPara"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <p class="text-[10px] text-slate-500 mt-1">Si vacío, queda como borrador. Cuando le des "Iniciar" arrancará.</p>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                    <button wire:click="guardar" class="rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-5 py-2 text-sm font-bold text-white shadow-lg">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
