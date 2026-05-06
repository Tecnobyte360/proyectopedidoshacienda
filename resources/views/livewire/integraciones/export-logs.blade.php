<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Sincronización con ERP</h2>
                        <p class="text-sm text-slate-500">Auditoría de pedidos exportados a las integraciones de tu negocio.</p>
                    </div>
                </div>
                <a href="{{ route('integraciones.index') }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white hover:border-brand hover:bg-brand-soft/30 px-5 py-3 text-sm text-slate-700 font-semibold transition">
                    <i class="fa-solid fa-arrow-left"></i> Volver a integraciones
                </a>
            </div>
        </div>

        {{-- 📊 STATS --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button wire:click="aplicarFiltro('todos')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'todos' ? 'border-brand' : 'border-slate-200 hover:border-slate-300' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total</div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                        <i class="fa-solid fa-list text-sm"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-800">{{ $stats['total'] }}</div>
            </button>

            <button wire:click="aplicarFiltro('ok')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'ok' ? 'border-emerald-400' : 'border-slate-200 hover:border-emerald-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Exitosos</div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <i class="fa-solid fa-check text-sm"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-emerald-700">{{ $stats['ok'] }}</div>
            </button>

            <button wire:click="aplicarFiltro('error')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'error' ? 'border-rose-400' : 'border-slate-200 hover:border-rose-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-rose-600">Con error</div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <i class="fa-solid fa-circle-xmark text-sm"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-rose-700">{{ $stats['error'] }}</div>
            </button>

            <div class="rounded-2xl bg-white p-4 shadow-sm border-2 border-slate-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Hoy</div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-soft text-brand">
                        <i class="fa-solid fa-calendar-day text-sm"></i>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-slate-800">{{ $stats['hoy'] }}</div>
            </div>
        </div>

        {{-- 🔍 FILTROS POR INTEGRACIÓN --}}
        @if($integraciones->count() > 0)
            <div class="rounded-2xl bg-white border border-slate-200 p-3 shadow-sm">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mr-1">
                        <i class="fa-solid fa-filter mr-1 text-slate-400"></i> Integración:
                    </span>
                    <button wire:click="$set('filtroIntegracion', null)"
                            class="rounded-xl px-3 py-1.5 text-xs font-bold transition
                                   {{ !$filtroIntegracion ? 'bg-gradient-to-r from-brand to-brand-secondary text-white shadow' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                        Todas
                    </button>
                    @foreach($integraciones as $int)
                        <button wire:click="$set('filtroIntegracion', {{ $int->id }})"
                                class="rounded-xl px-3 py-1.5 text-xs font-bold transition
                                       {{ $filtroIntegracion === $int->id ? 'bg-gradient-to-r from-brand to-brand-secondary text-white shadow' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                            {{ $int->nombre }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 📋 TABLA DE LOGS --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            @if($logs->count() === 0)
                <div class="p-12 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i class="fa-solid fa-inbox text-2xl"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-700 mb-1">Sin registros de exportación</h3>
                    <p class="text-sm text-slate-500">Cuando se confirme un pedido y haya integraciones con export activado, aparecerán acá.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Fecha</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Integración</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Pedido</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cliente</th>
                                <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Total</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Documento ERP</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($logs as $log)
                                <tr class="hover:bg-brand-soft/20 transition">
                                    <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                        <div class="font-mono">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($log->created_at)->format('h:i a') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1 rounded-md bg-slate-900 px-2 py-0.5 font-mono text-[10px] font-bold text-white">
                                                {{ strtoupper($log->integracion_tipo ?? '?') }}
                                            </span>
                                            <span class="font-semibold text-slate-800">{{ $log->integracion_nombre ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-700">
                                            <i class="fa-solid fa-hashtag text-[9px] text-slate-400"></i>{{ str_pad($log->pedido_id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-700 truncate max-w-[180px]">
                                        {{ $log->cliente_nombre ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-right whitespace-nowrap">
                                        <span class="inline-flex rounded-lg bg-slate-900 px-2 py-1 font-mono font-bold text-white shadow-sm">
                                            ${{ number_format($log->pedido_total ?? 0, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        @if($log->estado === 'ok')
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                Exitoso
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-rose-700"
                                                  title="{{ $log->error_mensaje }}">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                Error
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        @if($log->documento_id)
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-brand-soft to-white border border-[#fbe9d7] px-2 py-1 font-mono font-bold text-brand-dark">
                                                <i class="fa-solid fa-file-invoice text-[10px]"></i>{{ $log->documento_id }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 italic">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <button wire:click="verLog({{ $log->id }})"
                                                    title="Ver SQL ejecutado y detalles"
                                                    class="rounded-lg bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 text-slate-700 transition">
                                                <i class="fa-solid fa-eye text-xs"></i>
                                            </button>
                                            @if($log->estado === 'error')
                                                <button wire:click="reintentar({{ $log->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="reintentar({{ $log->id }})"
                                                        title="Reintentar el export de este pedido"
                                                        class="rounded-lg bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white px-2.5 py-1.5 transition shadow disabled:opacity-50">
                                                    <i class="fa-solid fa-arrow-rotate-right text-xs" wire:loading.remove wire:target="reintentar({{ $log->id }})"></i>
                                                    <i class="fa-solid fa-circle-notch fa-spin text-xs" wire:loading wire:target="reintentar({{ $log->id }})"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 bg-slate-50 border-t border-slate-100">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- 🔎 MODAL DETALLE --}}
    @if($logActual)
        <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 flex items-center justify-center p-4"
             wire:click.self="cerrarLog">
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">

                {{-- Header del modal --}}
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl shadow
                                    {{ $logActual->estado === 'ok' ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 text-white' : 'bg-gradient-to-br from-rose-500 to-rose-600 text-white' }}">
                            <i class="fa-solid {{ $logActual->estado === 'ok' ? 'fa-check' : 'fa-circle-xmark' }} text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800">
                                {{ $logActual->estado === 'ok' ? 'Export exitoso' : 'Export con error' }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                Pedido #{{ str_pad($logActual->pedido_id, 3, '0', STR_PAD_LEFT) }}
                                · {{ \Carbon\Carbon::parse($logActual->created_at)->format('d/m/Y h:i a') }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="cerrarLog"
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                {{-- Contenido scrolleable --}}
                <div class="p-6 overflow-y-auto flex-1 space-y-4">

                    @if($logActual->documento_id)
                        <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-50/30 border-2 border-emerald-200 p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fa-solid fa-file-invoice text-emerald-600"></i>
                                <div class="text-xs font-bold uppercase tracking-wider text-emerald-800">Documento generado en el ERP</div>
                            </div>
                            <div class="text-3xl font-mono font-extrabold text-emerald-700 mb-2">
                                {{ $logActual->documento_id }}
                            </div>
                            <div class="text-[11px] text-emerald-700 font-mono bg-emerald-100/50 rounded-lg p-2">
                                Verifícalo en SQL Server:<br>
                                <code class="text-emerald-900 font-bold">SELECT * FROM TblDocumentos WHERE IntDocumento = {{ $logActual->documento_id }}</code>
                            </div>
                        </div>
                    @endif

                    @if($logActual->error_mensaje)
                        <div class="rounded-2xl bg-rose-50 border-2 border-rose-200 p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                                <div class="text-xs font-bold uppercase tracking-wider text-rose-800">Error reportado por SQL Server</div>
                            </div>
                            <pre class="text-xs text-rose-900 whitespace-pre-wrap font-mono bg-white/60 rounded-lg p-3 border border-rose-100">{{ $logActual->error_mensaje }}</pre>
                        </div>
                    @endif

                    @if($logActual->sql_ejecutado)
                        <div class="rounded-2xl bg-slate-900 p-5 overflow-x-auto">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fa-solid fa-terminal text-emerald-400"></i>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-300">SQL ejecutado</div>
                            </div>
                            <pre class="text-xs text-emerald-300 font-mono whitespace-pre-wrap leading-relaxed">{{ $logActual->sql_ejecutado }}</pre>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Fecha de export</div>
                            <div class="text-sm font-bold text-slate-800">
                                {{ \Carbon\Carbon::parse($logActual->created_at)->format('d/m/Y') }}
                            </div>
                            <div class="text-xs text-slate-500 font-mono">
                                {{ \Carbon\Carbon::parse($logActual->created_at)->format('H:i:s') }}
                            </div>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Pedido</div>
                            <a href="{{ route('pedidos.index') }}?pedido={{ $logActual->pedido_id }}"
                               class="text-sm font-bold text-brand-dark hover:underline inline-flex items-center gap-1">
                                #{{ str_pad($logActual->pedido_id, 3, '0', STR_PAD_LEFT) }}
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Footer del modal --}}
                <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
                    @if($logActual->estado === 'error')
                        <button wire:click="reintentar({{ $logActual->id }})"
                                wire:loading.attr="disabled"
                                wire:target="reintentar({{ $logActual->id }})"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white px-5 py-2.5 text-sm font-bold transition shadow disabled:opacity-50">
                            <i class="fa-solid fa-arrow-rotate-right" wire:loading.remove wire:target="reintentar({{ $logActual->id }})"></i>
                            <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="reintentar({{ $logActual->id }})"></i>
                            Reintentar export
                        </button>
                    @endif
                    <button wire:click="cerrarLog"
                            class="rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 px-5 py-2.5 text-sm font-bold transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
