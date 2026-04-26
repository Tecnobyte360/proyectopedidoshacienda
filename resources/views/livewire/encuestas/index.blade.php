<div>
    <div class="mb-5">
        <h2 class="text-2xl font-extrabold text-slate-800">Encuestas de entrega</h2>
        <p class="text-sm text-slate-500">Calificaciones del proceso y de los domiciliarios desde el cliente.</p>
    </div>

    {{-- Alerta de encuestas sin enviar (probable queue worker caído) --}}
    @if($estadisticas['sin_enviar'] > 0)
        <div class="mb-4 rounded-xl bg-rose-50 border-2 border-rose-300 p-4 flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-2xl mt-0.5"></i>
            <div class="flex-1">
                <div class="font-bold text-rose-800">{{ $estadisticas['sin_enviar'] }} encuesta(s) sin enviar</div>
                <p class="text-xs text-rose-700 mt-1">
                    Hay encuestas creadas pero el WhatsApp nunca salió. Esto suele pasar si el
                    <strong>queue worker</strong> no está corriendo. En el servidor ejecuta:
                </p>
                <code class="inline-block mt-2 rounded bg-rose-900 text-rose-100 px-2 py-1 text-[11px]">docker exec -d pedidos_hacienda_app php artisan queue:work --tries=3 --timeout=60</code>
                <p class="text-xs text-rose-700 mt-2">
                    Mientras tanto, puedes
                    <button wire:click="$set('filtro', 'sin_enviar')" class="underline font-bold">filtrar las pendientes</button>
                    y reenviarlas manualmente con el botón verde de cada fila.
                </p>
            </div>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div class="rounded-xl bg-white border border-slate-200 p-4">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total</div>
            <div class="text-2xl font-extrabold text-slate-800 mt-1">{{ $estadisticas['total'] }}</div>
            <div class="text-[11px] text-slate-500">{{ $estadisticas['tasa'] }}% respondidas</div>
        </div>
        <div class="rounded-xl bg-white border border-amber-200 p-4">
            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Promedio proceso</div>
            <div class="text-2xl font-extrabold text-amber-700 mt-1">
                @for($i = 1; $i <= 5; $i++)<i class="fa-solid fa-star text-sm {{ $i <= round($estadisticas['prom_proc']) ? 'text-amber-500' : 'text-amber-200' }}"></i>@endfor
            </div>
            <div class="text-[11px] text-slate-500">{{ $estadisticas['prom_proc'] }} / 5</div>
        </div>
        <div class="rounded-xl bg-white border border-violet-200 p-4">
            <div class="text-[10px] font-bold uppercase tracking-wider text-violet-600">Promedio domiciliario</div>
            <div class="text-2xl font-extrabold text-violet-700 mt-1">
                @for($i = 1; $i <= 5; $i++)<i class="fa-solid fa-star text-sm {{ $i <= round($estadisticas['prom_dom']) ? 'text-violet-500' : 'text-violet-200' }}"></i>@endfor
            </div>
            <div class="text-[11px] text-slate-500">{{ $estadisticas['prom_dom'] }} / 5</div>
        </div>
        <div class="rounded-xl bg-white border border-emerald-200 p-4">
            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Recomendarían</div>
            <div class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $estadisticas['recom_si'] }}</div>
            <div class="text-[11px] text-slate-500">vs {{ $estadisticas['recom_no'] }} no</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="rounded-xl bg-white border border-slate-200 p-3 mb-5 flex flex-wrap gap-2 items-center">
        @foreach([
            'todas'       => 'Todas',
            'sin_enviar'  => '🔴 Sin enviar',
            'enviadas_no_respondidas' => '📤 Enviadas (sin responder)',
            'completadas' => '✅ Respondidas',
            'pendientes'  => 'Pendientes',
            'bajas'       => '⚠ Bajas (≤3)',
        ] as $key => $label)
            <button wire:click="$set('filtro', '{{ $key }}')"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $filtro === $key ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach

        <select wire:model.live="domiciliarioId" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs">
            <option value="">Todos los domiciliarios</option>
            @foreach($domiciliarios as $d)
                <option value="{{ $d->id }}">{{ $d->nombre }}</option>
            @endforeach
        </select>

        <div class="flex-1 min-w-[200px]">
            <input type="text" wire:model.live.debounce.500ms="busqueda" placeholder="Buscar cliente…"
                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
    </div>

    {{-- Tabla --}}
    <div class="rounded-xl bg-white border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-2.5">Pedido</th>
                        <th class="px-3 py-2.5">Cliente</th>
                        <th class="px-3 py-2.5">Proceso</th>
                        <th class="px-3 py-2.5">Domiciliario</th>
                        <th class="px-3 py-2.5">Recomienda</th>
                        <th class="px-3 py-2.5">Comentarios</th>
                        <th class="px-3 py-2.5">Estado</th>
                        <th class="px-3 py-2.5">Fecha envío</th>
                        <th class="px-3 py-2.5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($encuestas as $e)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-600">#{{ $e->pedido_id }}</td>
                            <td class="px-3 py-2.5">
                                <div class="text-sm font-semibold text-slate-800">{{ $e->pedido?->cliente_nombre ?? '—' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $e->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                @if($e->calificacion_proceso)
                                    @for($i = 1; $i <= 5; $i++)<i class="fa-solid fa-star text-xs {{ $i <= $e->calificacion_proceso ? 'text-amber-500' : 'text-slate-200' }}"></i>@endfor
                                @else
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="text-xs text-slate-600">{{ $e->domiciliario?->nombre ?? '—' }}</div>
                                @if($e->calificacion_domiciliario)
                                    @for($i = 1; $i <= 5; $i++)<i class="fa-solid fa-star text-[10px] {{ $i <= $e->calificacion_domiciliario ? 'text-violet-500' : 'text-slate-200' }}"></i>@endfor
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if($e->recomendaria === true)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[10px] font-bold">
                                        <i class="fa-solid fa-thumbs-up"></i> Sí
                                    </span>
                                @elseif($e->recomendaria === false)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 text-rose-700 px-2 py-0.5 text-[10px] font-bold">
                                        <i class="fa-solid fa-thumbs-down"></i> No
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 max-w-xs">
                                @if($e->comentario_proceso)
                                    <p class="text-[11px] text-slate-700 line-clamp-2">📝 {{ $e->comentario_proceso }}</p>
                                @endif
                                @if($e->comentario_domiciliario)
                                    <p class="text-[11px] text-slate-500 line-clamp-2 mt-0.5">🛵 {{ $e->comentario_domiciliario }}</p>
                                @endif
                                @if(!$e->comentario_proceso && !$e->comentario_domiciliario)
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if($e->completada_at)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[10px] font-bold">
                                        <i class="fa-solid fa-check"></i> Respondida
                                    </span>
                                @elseif($e->vista_at)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-[10px] font-bold">
                                        <i class="fa-solid fa-eye"></i> Vista
                                    </span>
                                @elseif($e->enviada_at)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 text-blue-700 px-2 py-0.5 text-[10px] font-bold">
                                        <i class="fa-brands fa-whatsapp"></i> Enviada
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 text-rose-700 px-2 py-0.5 text-[10px] font-bold">
                                        <i class="fa-solid fa-clock"></i> Sin enviar
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-[11px] text-slate-500">
                                @if($e->enviada_at)
                                    <div title="{{ $e->enviada_at->format('d/m/Y H:i:s') }}">
                                        <i class="fa-solid fa-paper-plane text-blue-400"></i>
                                        {{ $e->enviada_at->diffForHumans() }}
                                    </div>
                                @else
                                    <span class="text-rose-600 font-bold">⚠ NO ENVIADA</span>
                                    <div class="text-[10px] text-slate-400">creada {{ $e->created_at?->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button wire:click="reenviarEncuesta({{ $e->id }})"
                                        wire:confirm="¿Reenviar la encuesta a este cliente por WhatsApp?"
                                        wire:loading.attr="disabled"
                                        wire:target="reenviarEncuesta({{ $e->id }})"
                                        class="inline-flex items-center justify-center h-8 px-3 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[11px] font-bold transition disabled:opacity-50"
                                        title="Reenviar por WhatsApp">
                                    <span wire:loading.remove wire:target="reenviarEncuesta({{ $e->id }})">
                                        <i class="fa-brands fa-whatsapp"></i> Reenviar
                                    </span>
                                    <span wire:loading wire:target="reenviarEncuesta({{ $e->id }})">
                                        <i class="fa-solid fa-spinner fa-spin"></i>
                                    </span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-clipboard-list text-3xl mb-2"></i>
                            <p>No hay encuestas con este filtro</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $encuestas->links() }}</div>
</div>
