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

        {{-- 🔄 MONITOR DE COLA DE JOBS (auto-refresh 5s) --}}
        @if(($colaJobs && $colaJobs->isNotEmpty()) || ($failedJobs && $failedJobs->isNotEmpty()))
            <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 text-white p-5 shadow-lg" wire:poll.5s>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 backdrop-blur">
                            <i class="fa-solid fa-list-check text-brand"></i>
                        </span>
                        <div>
                            <h3 class="font-extrabold text-base flex items-center gap-2">
                                Cola de envíos
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 text-emerald-300 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    En vivo · 5s
                                </span>
                            </h3>
                            <p class="text-[11px] text-slate-400">Worker procesando jobs en background</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <div class="text-center">
                            <div class="text-2xl font-black text-brand">{{ $colaJobs->count() }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Pendientes</div>
                        </div>
                        @if($failedJobs->isNotEmpty())
                            <div class="text-center">
                                <div class="text-2xl font-black text-rose-400">{{ $failedJobs->count() }}</div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Fallidos</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Jobs pendientes --}}
                @if($colaJobs->isNotEmpty())
                    <div class="space-y-2 mb-4">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-slate-400"><i class="fa-solid fa-clipboard"></i> Esperando ejecución</p>
                        @foreach($colaJobs as $job)
                            @php
                                $eta = $job->available_at->diffForHumans(['parts' => 2, 'short' => true]);
                                $listo = $job->available_at->isPast();
                            @endphp
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-white/5 backdrop-blur border border-white/10 px-4 py-2.5 hover:bg-white/10 transition">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $listo ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300' }}">
                                        @if($listo)
                                            <i class="fa-solid fa-bolt text-xs"></i>
                                        @else
                                            <i class="fa-regular fa-clock text-xs"></i>
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold truncate">
                                            @if($job->campana)
                                                {{ $job->campana->nombre }}
                                            @else
                                                <span class="text-slate-400 italic">Campaña #{{ $job->campana_id }}</span>
                                            @endif
                                        </p>
                                        <p class="text-[11px] text-slate-400">
                                            @if($listo)
                                                <span class="text-emerald-300 font-semibold"><i class="fa-solid fa-bolt"></i> Procesando ahora...</span>
                                            @else
                                                Se ejecutará {{ $eta }}
                                                <span class="text-slate-500">·</span>
                                                {{ $job->available_at->format('d/m H:i:s') }}
                                            @endif
                                            @if($job->attempts > 0)
                                                <span class="text-amber-400 ml-2">· {{ $job->attempts }} intento(s)</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono">#{{ $job->id }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Jobs fallidos --}}
                @if($failedJobs->isNotEmpty())
                    <div class="space-y-2">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-rose-400">
                            <i class="fa-solid fa-triangle-exclamation"></i> Fallos recientes
                        </p>
                        @foreach($failedJobs as $job)
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-rose-500/10 border border-rose-500/30 px-4 py-2.5">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500/20 text-rose-300">
                                        <i class="fa-solid fa-circle-xmark text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold truncate">
                                            @if($job->campana)
                                                {{ $job->campana->nombre }}
                                            @else
                                                <span class="text-slate-400 italic">Campaña #{{ $job->campana_id }}</span>
                                            @endif
                                        </p>
                                        <p class="text-[11px] text-rose-300 truncate" title="{{ $job->error }}">
                                            {{ $job->error }}
                                        </p>
                                        <p class="text-[10px] text-slate-500 mt-0.5">Falló {{ $job->failed_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button wire:click="reintentarJobFallido({{ $job->id }})"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 transition"
                                            title="Reintentar">
                                        <i class="fa-solid fa-rotate-right text-xs"></i>
                                    </button>
                                    <button wire:click="borrarJobFallido({{ $job->id }})"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-700/50 hover:bg-slate-600/50 text-slate-300 transition"
                                            title="Eliminar">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

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
            <div class="w-full max-w-7xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>

                {{-- Header con gradient brand --}}
                <div class="relative px-6 py-5 border-b border-slate-100 overflow-hidden"
                     style="background: linear-gradient(135deg, rgba(16,185,129,0.08) 0%, rgba(5,150,105,0.04) 50%, rgba(255,255,255,1) 100%);">

                    {{-- Decoración suave --}}
                    <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full bg-brand/5 blur-3xl"></div>

                    <div class="relative flex items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-lg shadow-brand/30">
                                <i class="fa-solid fa-chart-line text-xl"></i>
                                @if($monitorCampana->estado === 'corriendo')
                                    <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 border-2 border-white"></span>
                                    </span>
                                @endif
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg leading-tight">{{ $monitorCampana->nombre }}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    @if($monitorCampana->estado === 'corriendo')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand/10 text-brand-dark px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                            <span class="h-1.5 w-1.5 rounded-full bg-brand animate-pulse"></span>
                                            En vivo · 3s
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                            <i class="fa-solid fa-check"></i> {{ ucfirst($monitorCampana->estado) }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-slate-500">
                                        Iniciada {{ $monitorCampana->iniciada_at?->diffForHumans() ?? '—' }}
                                        @if($monitorCampana->completada_at)
                                            · Finalizada {{ $monitorCampana->completada_at->diffForHumans() }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button wire:click="cerrarMonitor" class="h-10 w-10 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-700 transition flex items-center justify-center shadow-sm">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto bg-gradient-to-b from-white to-slate-50/30">

                    {{-- 📊 Resumen compacto (solo métricas confiables) --}}
                    <div class="rounded-2xl bg-gradient-to-r from-brand/5 via-white to-emerald-50 border border-slate-200 p-4 flex flex-wrap items-center gap-6">
                        {{-- Enviados (= Entregados, porque API solo acepta si sesión activa) --}}
                        <div class="flex items-center gap-3"
                             title="Mensajes aceptados por WhatsApp y entregados al destinatario. Si está en verde, llegó al cliente.">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-md shadow-brand/30">
                                <i class="fa-solid fa-check-double text-base"></i>
                            </span>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold leading-none">Llegaron</div>
                                <div class="text-2xl font-black text-slate-800 leading-tight">
                                    {{ $monitorEstadisticas['enviado'] }}
                                    <span class="text-sm text-slate-400 font-normal">/ {{ $monitorEstadisticas['total'] }}</span>
                                </div>
                                <div class="text-[10px] text-emerald-600 font-bold">
                                    {{ $monitorEstadisticas['total'] > 0 ? round(($monitorEstadisticas['enviado'] / $monitorEstadisticas['total']) * 100, 0) : 0 }}% del total
                                </div>
                            </div>
                        </div>

                        <div class="h-12 w-px bg-slate-200"></div>

                        {{-- Respondieron (métrica de engagement real) --}}
                        <div class="flex items-center gap-3"
                             title="Clientes que contestaron al mensaje. Engagement real.">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 text-white shadow-md shadow-blue-300">
                                <i class="fa-solid fa-reply text-base"></i>
                            </span>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold leading-none">Respondieron</div>
                                <div class="text-2xl font-black text-slate-800 leading-tight">{{ $monitorEstadisticas['respondieron'] ?? 0 }}</div>
                                <div class="text-[10px] text-blue-600 font-bold">{{ $monitorEstadisticas['tasa_respuesta'] ?? 0 }}% tasa respuesta</div>
                            </div>
                        </div>

                        <div class="h-12 w-px bg-slate-200"></div>

                        {{-- Fallidos --}}
                        <div class="flex items-center gap-3"
                             title="Mensajes que no se pudieron enviar (sesión WhatsApp caída, número inválido, etc.)">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 to-rose-700 text-white shadow-md shadow-rose-300">
                                <i class="fa-solid fa-circle-exclamation text-base"></i>
                            </span>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold leading-none">Fallidos</div>
                                <div class="text-2xl font-black text-slate-800 leading-tight">{{ $monitorEstadisticas['fallido'] }}</div>
                                <div class="text-[10px] text-rose-600 font-bold">no llegaron</div>
                            </div>
                        </div>

                        <div class="h-12 w-px bg-slate-200 hidden md:block"></div>

                        {{-- Info badge --}}
                        <div class="ml-auto text-xs text-slate-500 max-w-xs hidden md:block">
                            <i class="fa-solid fa-circle-info text-brand"></i>
                            <strong class="text-slate-700">"Llegaron"</strong> = aceptados por WhatsApp.
                            Si el icono <i class="fa-solid fa-check"></i> está verde, el cliente lo recibió.
                        </div>
                    </div>

                    {{-- Filtros tipo pills + acción --}}
                    <div class="rounded-2xl bg-white border border-slate-200 p-3 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="inline-flex items-center bg-slate-50 rounded-xl p-1 gap-0.5 flex-wrap">
                                <button wire:click="$set('filtroMonitor', 'todos')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition
                                               {{ $filtroMonitor === 'todos' ? 'bg-slate-900 text-white shadow' : 'text-slate-600 hover:bg-white' }}">
                                    <i class="fa-solid fa-table-list text-[10px]"></i> Todos
                                    <span class="text-[10px] {{ $filtroMonitor === 'todos' ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-700' }} rounded-full px-1.5 font-extrabold">{{ $monitorEstadisticas['total'] }}</span>
                                </button>
                                <button wire:click="$set('filtroMonitor', 'enviado')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition
                                               {{ $filtroMonitor === 'enviado' ? 'bg-brand text-white shadow' : 'text-emerald-700 hover:bg-white' }}">
                                    <i class="fa-solid fa-check text-[10px]"></i> Enviados
                                    <span class="text-[10px] {{ $filtroMonitor === 'enviado' ? 'bg-white/25 text-white' : 'bg-emerald-100 text-emerald-700' }} rounded-full px-1.5 font-extrabold">{{ $monitorEstadisticas['enviado'] }}</span>
                                </button>
                                <button wire:click="$set('filtroMonitor', 'fallido')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition
                                               {{ $filtroMonitor === 'fallido' ? 'bg-rose-600 text-white shadow' : 'text-rose-700 hover:bg-white' }}">
                                    <i class="fa-solid fa-xmark text-[10px]"></i> Fallidos
                                    <span class="text-[10px] {{ $filtroMonitor === 'fallido' ? 'bg-white/25 text-white' : 'bg-rose-100 text-rose-700' }} rounded-full px-1.5 font-extrabold">{{ $monitorEstadisticas['fallido'] }}</span>
                                </button>
                                <button wire:click="$set('filtroMonitor', 'pendiente')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition
                                               {{ $filtroMonitor === 'pendiente' ? 'bg-amber-500 text-white shadow' : 'text-amber-700 hover:bg-white' }}">
                                    <i class="fa-regular fa-clock text-[10px]"></i> Pendientes
                                    <span class="text-[10px] {{ $filtroMonitor === 'pendiente' ? 'bg-white/25 text-white' : 'bg-amber-100 text-amber-700' }} rounded-full px-1.5 font-extrabold">{{ $monitorEstadisticas['pendiente'] }}</span>
                                </button>
                            </div>
                            @if($monitorEstadisticas['fallido'] > 0)
                                <button wire:click="reintentarFallidos({{ $monitorCampana->id }})"
                                        wire:confirm="¿Reintentar los {{ $monitorEstadisticas['fallido'] }} fallidos?"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white text-xs font-bold px-4 py-2 transition shadow-md shadow-rose-200">
                                    <i class="fa-solid fa-rotate-right"></i>
                                    Reintentar ({{ $monitorEstadisticas['fallido'] }})
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Lista de destinatarios con estética premium --}}
                    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
                            <h4 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-list-ul text-brand"></i>
                                Detalle de destinatarios
                            </h4>
                            <span class="text-[10px] uppercase tracking-wider font-bold text-slate-500">
                                {{ $monitorDestinatarios->count() }} {{ $filtroMonitor !== 'todos' ? '· '.$filtroMonitor : '' }}
                            </span>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50/50 text-[10px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-left">Estado</th>
                                    <th class="px-4 py-3 text-left">Cliente</th>
                                    <th class="px-4 py-3 text-left">Teléfono</th>
                                    <th class="px-4 py-3 text-left">Cuándo se envió</th>
                                    <th class="px-4 py-3 text-center">Respondió</th>
                                    <th class="px-4 py-3 text-left">Detalle</th>
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
                                            @if($d->enviado_at)
                                                <span class="font-medium text-slate-700">{{ $d->enviado_at->format('d/m H:i') }}</span>
                                                <div class="text-[10px] text-slate-400">{{ $d->enviado_at->diffForHumans() }}</div>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Respondió --}}
                                        <td class="px-3 py-2 text-center">
                                            @if($d->respondio_at)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-brand/15 text-brand-dark px-2 py-0.5 text-[10px] font-bold"
                                                      title="Respondió {{ $d->respondio_at->diffForHumans() }} · {{ $d->respuestas_count }} mensaje(s)">
                                                    <i class="fa-solid fa-reply"></i>
                                                    @if($d->respuestas_count > 1)
                                                        {{ $d->respuestas_count }}x
                                                    @else
                                                        Sí
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-slate-300 text-[10px]">—</span>
                                            @endif
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
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                            <i class="fa-solid fa-inbox text-2xl block mb-2"></i>
                                            No hay destinatarios con este estado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 bg-gradient-to-r from-slate-50 to-white flex justify-between items-center">
                    <p class="text-xs text-slate-500">
                        <i class="fa-solid fa-shield-halved text-brand"></i>
                        Anti-baneo activo · Lotes pausados automáticamente
                    </p>
                    <button wire:click="cerrarMonitor" class="rounded-xl bg-slate-900 hover:bg-slate-800 px-5 py-2 text-sm font-bold text-white transition shadow">
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

                    @if($providerMeta)
                        {{-- 🟢 PROVIDER META: usa plantillas aprobadas (oblig. >24h) --}}
                        <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50/40 p-4 space-y-3">
                            <div class="flex items-center gap-2">
                                <i class="fa-brands fa-meta text-emerald-600"></i>
                                <h4 class="font-bold text-slate-800 text-sm">Plantilla Meta WhatsApp *</h4>
                            </div>
                            <p class="text-[11px] text-slate-600">
                                Este tenant usa Meta WhatsApp Cloud API. Para envíos masivos es obligatorio
                                usar una <strong>plantilla aprobada</strong> por Meta. Si no tienes plantillas,
                                créalas en <a href="/meta-whatsapp" class="text-emerald-700 underline" target="_blank">/meta-whatsapp</a>.
                            </p>

                            @if($plantillasMeta->isEmpty())
                                <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    No tienes plantillas aprobadas todavía. Ve a /meta-whatsapp y sincroniza desde Meta o crea una nueva.
                                </div>
                            @else
                                <select wire:model.live="plantillaMetaId" class="w-full rounded-xl border border-emerald-300 px-3 py-2 text-sm">
                                    <option value="">— Selecciona plantilla aprobada —</option>
                                    @foreach($plantillasMeta as $tpl)
                                        <option value="{{ $tpl->id }}">{{ $tpl->nombre }} ({{ $tpl->idioma }}) — {{ $tpl->categoria }}</option>
                                    @endforeach
                                </select>

                                @if($plantillaSeleccionada)
                                    <div class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-xs">
                                        <p class="font-semibold text-slate-700 mb-1">Vista previa:</p>
                                        <pre class="whitespace-pre-wrap text-slate-600">{{ $plantillaSeleccionada->body_preview ?: '(sin body)' }}</pre>
                                    </div>

                                    @if(($plantillaSeleccionada->num_variables ?? 0) > 0)
                                        <div class="space-y-2">
                                            <p class="text-xs font-semibold text-slate-700">
                                                Variables ({{ $plantillaSeleccionada->num_variables }}):
                                            </p>
                                            @for($i = 1; $i <= $plantillaSeleccionada->num_variables; $i++)
                                                @php $ph = '{{' . $i . '}}'; @endphp
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center justify-center w-12 h-8 rounded bg-emerald-100 text-emerald-700 text-xs font-bold">
                                                        {{ $ph }}
                                                    </span>
                                                    <input type="text" wire:model="plantillaVariables.{{ $i }}"
                                                           placeholder="Valor para {{ $ph }}"
                                                           class="flex-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                                                </div>
                                            @endfor
                                            <p class="text-[10px] text-slate-500">
                                                Estas variables se enviarán igual para TODOS los destinatarios.
                                                Para personalización por cliente, usa los placeholders que Meta soporte en la plantilla.
                                            </p>
                                        </div>
                                    @endif
                                @endif
                            @endif
                        </div>
                    @else
                        {{-- 🟡 PROVIDER TecnoByteApp: texto libre (modo legacy) --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Mensaje *</label>
                            <textarea wire:model="mensaje" rows="4" placeholder="¡Hola {primer_nombre}! Tenemos un 20% de descuento en chuletas hasta el domingo. ¿Te animas? 🥩"
                                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"></textarea>
                            <p class="text-[10px] text-slate-500 mt-1">
                                Variables: <code>{nombre}</code>, <code>{primer_nombre}</code>, <code>{telefono}</code>
                            </p>
                        </div>
                    @endif

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
                                <i class="fa-solid fa-check"></i> {{ $numerosImportados }} números importados.
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
