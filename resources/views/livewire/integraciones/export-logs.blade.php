<div class="px-6 lg:px-10 py-8">
    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">
                <i class="fa-solid fa-cloud-arrow-up text-blue-600 mr-2"></i>
                Sincronización con ERP
            </h2>
            <p class="text-sm text-slate-500">Auditoría de pedidos exportados a las integraciones de tu negocio.</p>
        </div>
        <a href="{{ route('integraciones.index') }}"
           class="rounded-xl border border-slate-200 hover:border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 font-medium">
            <i class="fa-solid fa-arrow-left mr-1"></i> Volver a integraciones
        </a>
    </div>

    {{-- 📊 STATS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <button wire:click="aplicarFiltro('todos')"
                class="rounded-xl bg-white p-4 shadow-sm border-2 transition text-left
                       {{ $filtroEstado === 'todos' ? 'border-blue-400' : 'border-slate-200 hover:border-slate-300' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total</div>
            <div class="text-2xl font-extrabold text-slate-800 mt-1">{{ $stats['total'] }}</div>
        </button>
        <button wire:click="aplicarFiltro('ok')"
                class="rounded-xl bg-white p-4 shadow-sm border-2 transition text-left
                       {{ $filtroEstado === 'ok' ? 'border-emerald-400' : 'border-slate-200 hover:border-emerald-200' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">✅ Exitosos</div>
            <div class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $stats['ok'] }}</div>
        </button>
        <button wire:click="aplicarFiltro('error')"
                class="rounded-xl bg-white p-4 shadow-sm border-2 transition text-left
                       {{ $filtroEstado === 'error' ? 'border-rose-400' : 'border-slate-200 hover:border-rose-200' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-rose-600">❌ Con error</div>
            <div class="text-2xl font-extrabold text-rose-700 mt-1">{{ $stats['error'] }}</div>
        </button>
        <div class="rounded-xl bg-white p-4 shadow-sm border-2 border-slate-200">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">📅 Hoy</div>
            <div class="text-2xl font-extrabold text-slate-800 mt-1">{{ $stats['hoy'] }}</div>
        </div>
    </div>

    {{-- 🔍 FILTRO POR INTEGRACIÓN --}}
    @if($integraciones->count() > 0)
        <div class="mb-4 flex items-center gap-3 flex-wrap">
            <span class="text-xs font-semibold text-slate-600">Filtrar por integración:</span>
            <button wire:click="$set('filtroIntegracion', null)"
                    class="rounded-lg px-3 py-1 text-xs font-medium transition
                           {{ !$filtroIntegracion ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' }}">
                Todas
            </button>
            @foreach($integraciones as $int)
                <button wire:click="$set('filtroIntegracion', {{ $int->id }})"
                        class="rounded-lg px-3 py-1 text-xs font-medium transition
                               {{ $filtroIntegracion === $int->id ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' }}">
                    {{ $int->nombre }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- 📋 TABLA DE LOGS --}}
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        @if($logs->count() === 0)
            <div class="p-10 text-center text-slate-500">
                <i class="fa-solid fa-inbox text-4xl mb-3 opacity-50"></i>
                <p class="font-semibold">Sin registros de exportación</p>
                <p class="text-xs mt-1">Cuando se confirme un pedido y haya integraciones con export activado, aparecerán acá.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Fecha</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Integración</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Pedido</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cliente</th>
                            <th class="px-3 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Total</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">IntDocumento</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($logs as $log)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-3 py-3 text-xs text-slate-600 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-700 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px]">
                                        {{ strtoupper($log->integracion_tipo ?? '') }}
                                    </span>
                                    {{ $log->integracion_nombre ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap">
                                    <span class="font-mono font-bold text-slate-800">#{{ str_pad($log->pedido_id, 3, '0', STR_PAD_LEFT) }}</span>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-700 truncate max-w-[160px]">
                                    {{ $log->cliente_nombre ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-xs text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                                    ${{ number_format($log->pedido_total ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap">
                                    @if($log->estado === 'ok')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                            <i class="fa-solid fa-check-circle"></i> EXITOSO
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-bold text-rose-700"
                                              title="{{ $log->error_mensaje }}">
                                            <i class="fa-solid fa-circle-xmark"></i> ERROR
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-700 font-mono whitespace-nowrap">
                                    @if($log->documento_id)
                                        <span class="rounded bg-blue-50 border border-blue-200 px-2 py-0.5 font-bold text-blue-700">
                                            {{ $log->documento_id }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs whitespace-nowrap text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <button wire:click="verLog({{ $log->id }})"
                                                title="Ver SQL ejecutado y detalles"
                                                class="rounded-lg bg-slate-100 hover:bg-slate-200 px-2 py-1 text-[11px] text-slate-700 font-medium">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        @if($log->estado === 'error')
                                            <button wire:click="reintentar({{ $log->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="reintentar({{ $log->id }})"
                                                    title="Reintentar el export de este pedido"
                                                    class="rounded-lg bg-amber-100 hover:bg-amber-200 px-2 py-1 text-[11px] text-amber-800 font-medium disabled:opacity-50">
                                                <i class="fa-solid fa-arrow-rotate-right" wire:loading.remove wire:target="reintentar({{ $log->id }})"></i>
                                                <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="reintentar({{ $log->id }})"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3 bg-slate-50 border-t border-slate-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- 🔎 MODAL DETALLE --}}
    @if($logActual)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
             wire:click.self="cerrarLog">
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[85vh] overflow-hidden shadow-2xl">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">
                        @if($logActual->estado === 'ok')
                            <i class="fa-solid fa-check-circle text-emerald-600"></i> Export exitoso
                        @else
                            <i class="fa-solid fa-circle-xmark text-rose-600"></i> Export con error
                        @endif
                        — Pedido #{{ $logActual->pedido_id }}
                    </h3>
                    <button wire:click="cerrarLog" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <div class="p-5 overflow-y-auto max-h-[70vh]">
                    @if($logActual->documento_id)
                        <div class="mb-4 rounded-xl bg-emerald-50 border-2 border-emerald-200 p-3">
                            <div class="text-xs text-emerald-800 font-semibold">📄 Documento generado en el ERP</div>
                            <div class="text-2xl font-mono font-extrabold text-emerald-700 mt-1">
                                IntDocumento: {{ $logActual->documento_id }}
                            </div>
                            <div class="text-[11px] text-emerald-700 mt-1">
                                Búscalo en SQL Server: <code class="bg-emerald-100 px-1 rounded">SELECT * FROM TblDocumentos WHERE IntDocumento = {{ $logActual->documento_id }}</code>
                            </div>
                        </div>
                    @endif

                    @if($logActual->error_mensaje)
                        <div class="mb-4 rounded-xl bg-rose-50 border-2 border-rose-200 p-3">
                            <div class="text-xs text-rose-800 font-semibold mb-1">❌ Error</div>
                            <pre class="text-xs text-rose-900 whitespace-pre-wrap font-mono">{{ $logActual->error_mensaje }}</pre>
                        </div>
                    @endif

                    @if($logActual->sql_ejecutado)
                        <div class="rounded-xl bg-slate-900 p-4 overflow-x-auto">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">SQL ejecutado</div>
                            <pre class="text-xs text-emerald-300 font-mono whitespace-pre-wrap">{{ $logActual->sql_ejecutado }}</pre>
                        </div>
                    @endif

                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-2.5">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Fecha de export</div>
                            <div class="text-slate-800 font-medium">{{ \Carbon\Carbon::parse($logActual->created_at)->format('d/m/Y H:i:s') }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-2.5">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Pedido relacionado</div>
                            <a href="{{ route('pedidos.index') }}?pedido={{ $logActual->pedido_id }}"
                               class="text-blue-700 font-bold hover:underline">
                                Pedido #{{ $logActual->pedido_id }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
                    @if($logActual->estado === 'error')
                        <button wire:click="reintentar({{ $logActual->id }})"
                                class="rounded-xl bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 text-sm font-bold">
                            <i class="fa-solid fa-arrow-rotate-right mr-1"></i> Reintentar export
                        </button>
                    @endif
                    <button wire:click="cerrarLog"
                            class="rounded-xl bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 text-sm font-bold">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
