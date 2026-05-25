<div class="min-h-screen bg-slate-50 p-4 sm:p-6" wire:poll.5s>
    <div class="max-w-7xl mx-auto">

        {{-- Encabezado --}}
        <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-dog"></i> Monitor del Watchdog
                </h1>
                <p class="text-sm text-slate-500 mt-1">
                    Rescates automáticos de conversaciones donde el bot quedó sin responder.
                    Actualiza cada 5 segundos.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ventana:</label>
                <select wire:model.live="horas"
                        class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="1">Última hora</option>
                    <option value="6">Últimas 6h</option>
                    <option value="24">Últimas 24h</option>
                    <option value="168">Última semana</option>
                </select>
            </div>
        </div>

        {{-- KPIs --}}
        @php $k = $this->kpis; @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total rescates</p>
                <h3 class="mt-2 text-3xl font-bold text-slate-900">{{ $k['total'] }}</h3>
                <p class="mt-1 text-[11px] text-slate-400">en las últimas {{ $horas }}h</p>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Exitosos</p>
                <h3 class="mt-2 text-3xl font-bold text-emerald-700">{{ $k['exitosos'] }}</h3>
                <p class="mt-1 text-[11px] text-emerald-600">{{ $k['tasa_exito'] }}% éxito</p>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Fallidos</p>
                <h3 class="mt-2 text-3xl font-bold text-rose-700">{{ $k['fallidos'] }}</h3>
                <p class="mt-1 text-[11px] text-rose-600">requieren atención manual</p>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Espera prom.</p>
                <h3 class="mt-2 text-3xl font-bold text-amber-700">
                    {{ $k['promedio_segs'] }}<span class="text-base font-normal">s</span>
                </h3>
                <p class="mt-1 text-[11px] text-amber-600">antes del rescate</p>
            </div>

            <div class="rounded-2xl border border-sky-200 bg-sky-50/40 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Clientes</p>
                <h3 class="mt-2 text-3xl font-bold text-sky-700">{{ $k['clientes_unicos'] }}</h3>
                <p class="mt-1 text-[11px] text-sky-600">únicos rescatados</p>
            </div>
        </div>

        {{-- Mensaje informativo si no hay rescates --}}
        @if($k['total'] === 0)
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-10 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 text-3xl">
                    <i class="fa-solid fa-dog"></i>
                </div>
                <h3 class="mt-3 text-lg font-semibold text-slate-700">Sin rescates en las últimas {{ $horas }}h</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-md mx-auto">
                    El watchdog está corriendo cada minuto pero no ha tenido que rescatar ninguna conversación.
                    Eso es bueno: significa que el bot está respondiendo bien a todos los clientes.
                </p>
            </div>
        @else
            {{-- Tabla / lista de rescates --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2">
                    <h2 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                        Últimos rescates
                        @if(!$mostrarResueltos)
                            <span class="text-[10px] font-normal text-slate-400">(ocultos resueltos)</span>
                        @endif
                    </h2>
                    <div class="flex items-center gap-2 flex-wrap">
                        <label class="inline-flex items-center gap-1 text-[11px] text-slate-600 cursor-pointer">
                            <input type="checkbox" wire:model.live="mostrarResueltos" class="rounded">
                            <span>Mostrar resueltos</span>
                        </label>
                        @if($k['fallidos'] > 0)
                            <button wire:click="marcarTodosResueltos"
                                    wire:confirm="¿Marcar los {{ $k['fallidos'] }} fallidos como resueltos?"
                                    class="inline-flex items-center gap-1 rounded-md bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2 py-1 text-[10px] font-semibold text-emerald-700 transition">
                                <i class="fa-solid fa-check-double text-[9px]"></i>
                                Resolver todos ({{ $k['fallidos'] }})
                            </button>
                        @endif
                        @if($k['ultimo_at'])
                            <span class="text-[11px] text-slate-500">
                                Último: hace {{ $k['ultimo_at']->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Hora</th>
                                <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cliente / Teléfono</th>
                                <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden md:table-cell">Conv</th>
                                <th class="px-3 py-2.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Mensaje del cliente</th>
                                <th class="px-3 py-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Esperó</th>
                                <th class="px-3 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                                <th class="px-3 py-2.5 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($this->rescates as $r)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-3 align-middle whitespace-nowrap">
                                        <div class="text-xs font-semibold text-slate-700">
                                            {{ $r->created_at->format('h:i a') }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ $r->created_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 align-middle">
                                        <div class="text-xs font-semibold text-slate-900">
                                            {{ $r->conversacion?->cliente?->nombre ?? 'Sin nombre' }}
                                        </div>
                                        <div class="text-[10px] text-slate-500 font-mono">
                                            {{ $r->telefono }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 align-middle hidden md:table-cell">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">
                                            #{{ $r->conversacion_id }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 align-middle">
                                        <div class="text-xs text-slate-700 truncate max-w-[280px]"
                                             title="{{ $r->mensaje_contenido }}">
                                            "{{ \Illuminate\Support\Str::limit($r->mensaje_contenido, 80) }}"
                                        </div>
                                        @if($r->error_mensaje && !$r->exitoso)
                                            <div class="text-[10px] text-rose-600 mt-1 truncate max-w-[280px]"
                                                 title="{{ $r->error_mensaje }}">
                                                <i class="fa-solid fa-circle-exclamation text-[9px]"></i>
                                                {{ \Illuminate\Support\Str::limit($r->error_mensaje, 60) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 align-middle text-right whitespace-nowrap">
                                        @php
                                            $segs = (int) $r->segundos_estancada;
                                            $tono = $segs < 60 ? 'bg-emerald-100 text-emerald-700'
                                                  : ($segs < 300 ? 'bg-amber-100 text-amber-700'
                                                  : 'bg-rose-100 text-rose-700');
                                            $human = $segs >= 60 ? floor($segs/60).'m '.($segs%60).'s' : $segs.'s';
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full {{ $tono }} px-2 py-0.5 text-[10px] font-bold">
                                            <i class="fa-regular fa-clock text-[9px]"></i>
                                            {{ $human }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 align-middle text-center">
                                        @if($r->exitoso)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                                <i class="fa-solid fa-circle-check text-[9px]"></i>
                                                Rescatado
                                            </span>
                                        @elseif($r->resuelto_at)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500"
                                                  title="Resuelto manualmente el {{ $r->resuelto_at->format('d/m H:i') }}">
                                                <i class="fa-solid fa-check text-[9px]"></i>
                                                Resuelto
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                                                <i class="fa-solid fa-circle-xmark text-[9px]"></i>
                                                Falló
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 align-middle text-center whitespace-nowrap">
                                        @if(!$r->exitoso && !$r->resuelto_at)
                                            <button wire:click="marcarResuelto({{ $r->id }})"
                                                    wire:confirm="¿Marcar este rescate fallido como resuelto?"
                                                    class="inline-flex items-center gap-1 rounded-md bg-slate-100 hover:bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:text-emerald-700 transition"
                                                    title="Marcar como resuelto (lo oculta del dashboard)">
                                                <i class="fa-solid fa-check text-[9px]"></i>
                                                Resolver
                                            </button>
                                        @else
                                            <span class="text-slate-300 text-[10px]">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-2 border-t border-slate-100 bg-slate-50">
                    <p class="text-[10px] text-slate-500">
                        Mostrando hasta 100 rescates más recientes en las últimas {{ $horas }}h.
                    </p>
                </div>
            </div>
        @endif

        {{-- Footer informativo --}}
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2 mb-2">
                <i class="fa-solid fa-info-circle text-slate-400"></i>
                ¿Qué hace el watchdog?
            </h3>
            <ul class="text-xs text-slate-600 space-y-1.5">
                <li>• Corre <strong>cada 60 segundos</strong> en el scheduler.</li>
                <li>• Detecta conversaciones donde el último mensaje sea del <strong>cliente</strong> y hayan pasado más de 15 segundos sin respuesta del bot.</li>
                <li>• Re-envía el mensaje del cliente al webhook para que el bot lo procese de nuevo.</li>
                <li>• Cubre fallos transitorios: errores PHP, timeouts de Anthropic, tool_use huérfanos, etc.</li>
                <li>• Cooldown de 5 minutos por mensaje para evitar loops.</li>
            </ul>
        </div>

    </div>
</div>
