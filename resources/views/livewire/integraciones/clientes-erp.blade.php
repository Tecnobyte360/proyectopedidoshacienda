<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-id-card text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Clientes en el ERP</h2>
                        <p class="text-sm text-slate-500">Auditoría de búsquedas y creaciones de clientes en TblTerceros del ERP.</p>
                    </div>
                </div>
                <div class="flex gap-2 items-center">
                    <a href="{{ route('integraciones.exports') }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white hover:border-brand hover:bg-brand-soft/30 px-4 py-2.5 text-sm text-slate-700 font-semibold transition">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Exports
                    </a>
                    <a href="{{ route('integraciones.index') }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white hover:border-brand hover:bg-brand-soft/30 px-4 py-2.5 text-sm text-slate-700 font-semibold transition">
                        <i class="fa-solid fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        {{-- KPI cards --}}
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <button wire:click="aplicarFiltro('todos')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'todos' ? 'border-brand' : 'border-slate-200 hover:border-slate-300' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total</div>
                    <i class="fa-solid fa-list text-slate-400"></i>
                </div>
                <div class="text-2xl font-extrabold text-slate-800">{{ $stats['total'] }}</div>
            </button>

            <button wire:click="aplicarFiltro('encontrado')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'encontrado' ? 'border-emerald-400' : 'border-slate-200 hover:border-emerald-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Encontrados</div>
                    <i class="fa-solid fa-magnifying-glass-plus text-emerald-500"></i>
                </div>
                <div class="text-2xl font-extrabold text-emerald-700">{{ $stats['encontrados'] }}</div>
            </button>

            <button wire:click="aplicarFiltro('no_encontrado')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'no_encontrado' ? 'border-amber-400' : 'border-slate-200 hover:border-amber-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600">No existían</div>
                    <i class="fa-solid fa-user-slash text-amber-500"></i>
                </div>
                <div class="text-2xl font-extrabold text-amber-700">{{ $stats['no_encontrados'] }}</div>
            </button>

            <button wire:click="aplicarFiltro('creado')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'creado' ? 'border-blue-400' : 'border-slate-200 hover:border-blue-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Creados</div>
                    <i class="fa-solid fa-user-plus text-blue-500"></i>
                </div>
                <div class="text-2xl font-extrabold text-blue-700">{{ $stats['creados'] }}</div>
            </button>

            <button wire:click="aplicarFiltro('error')"
                    class="rounded-2xl bg-white p-4 shadow-sm border-2 transition text-left
                           {{ $filtroEstado === 'error' ? 'border-rose-400' : 'border-slate-200 hover:border-rose-200' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Errores</div>
                    <i class="fa-solid fa-circle-xmark text-rose-500"></i>
                </div>
                <div class="text-2xl font-extrabold text-rose-700">{{ $stats['errores'] }}</div>
            </button>

            <div class="rounded-2xl bg-white p-4 shadow-sm border-2 border-slate-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Hoy</div>
                    <i class="fa-solid fa-calendar-day text-brand"></i>
                </div>
                <div class="text-2xl font-extrabold text-slate-800">{{ $stats['hoy'] }}</div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-3 shadow-sm flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Acción:</span>
                @foreach (['todos' => 'Todas', 'buscar' => '<i class="fa-solid fa-magnifying-glass"></i> Buscar', 'crear' => '<i class="fa-solid fa-plus"></i> Crear'] as $key => $label)
                    <button wire:click="$set('filtroAccion', '{{ $key }}')"
                            class="rounded-xl px-3 py-1.5 text-xs font-bold transition
                                   {{ $filtroAccion === $key ? 'bg-gradient-to-r from-brand to-brand-secondary text-white shadow' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if($integraciones->count() > 0)
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider ml-2">Integración:</span>
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
            @endif

            <div class="ml-auto flex items-center gap-2">
                <input type="text" wire:model.live.debounce.500ms="busquedaCedula"
                       placeholder="Buscar por cédula..."
                       class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm w-56">
            </div>
        </div>

        {{-- Tabla de logs --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            @if($logs->count() === 0)
                <div class="p-12 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i class="fa-solid fa-id-card text-2xl"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-700 mb-1">Sin búsquedas o creaciones de clientes</h3>
                    <p class="text-sm text-slate-500">Cuando el bot tome pedidos y consulte/cree clientes en el ERP, aparecerán aquí.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Fecha</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Acción</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cédula</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cliente</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Teléfono</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Integración</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                                <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($logs as $log)
                                <tr class="hover:bg-brand-soft/20 transition">
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        <div class="font-mono text-slate-600">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($log->created_at)->format('h:i a') }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        @if($log->accion === 'buscar')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                                <i class="fa-solid fa-magnifying-glass"></i> BUSCAR
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                                <i class="fa-solid fa-plus"></i> CREAR
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono whitespace-nowrap text-slate-800">
                                        {{ $log->cedula ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-700 truncate max-w-[180px]">
                                        {{ $log->nombre ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono text-slate-600 whitespace-nowrap">
                                        {{ $log->telefono ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-slate-900 px-2 py-0.5 font-mono text-[10px] font-bold text-white">
                                            {{ strtoupper($log->integracion_tipo ?? '?') }}
                                        </span>
                                        <span class="ml-1 font-semibold text-slate-800">{{ $log->integracion_nombre ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        @if(!$log->exitoso)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-1 text-[10px] font-bold uppercase text-rose-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                Error
                                            </span>
                                        @elseif($log->accion === 'buscar' && $log->encontrado)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                Existe
                                            </span>
                                        @elseif($log->accion === 'buscar' && !$log->encontrado)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-1 text-[10px] font-bold uppercase text-amber-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                No existía
                                            </span>
                                        @elseif($log->accion === 'crear')
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-200 px-2.5 py-1 text-[10px] font-bold uppercase text-blue-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                                Creado
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-center">
                                        <button wire:click="verLog({{ $log->id }})"
                                                class="rounded-lg bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 text-slate-700 transition">
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </button>
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

    {{-- Modal de detalle --}}
    @if($logActual)
        <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 flex items-center justify-center p-4"
             wire:click.self="cerrarLog">
            <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl shadow
                                    {{ !$logActual->exitoso ? 'bg-gradient-to-br from-rose-500 to-rose-600 text-white'
                                        : ($logActual->accion === 'crear' ? 'bg-gradient-to-br from-blue-500 to-blue-600 text-white'
                                          : ($logActual->encontrado ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 text-white' : 'bg-gradient-to-br from-amber-500 to-amber-600 text-white')) }}">
                            <i class="fa-solid {{ !$logActual->exitoso ? 'fa-circle-xmark' : ($logActual->accion === 'crear' ? 'fa-user-plus' : ($logActual->encontrado ? 'fa-check' : 'fa-user-slash')) }}"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800">
                                @if(!$logActual->exitoso) Error en operación
                                @elseif($logActual->accion === 'crear') Cliente creado en ERP
                                @elseif($logActual->encontrado) Cliente encontrado
                                @else Cliente no existía
                                @endif
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ \Carbon\Carbon::parse($logActual->created_at)->format('d/m/Y h:i:s a') }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="cerrarLog" class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cédula buscada</div>
                            <div class="text-base font-mono font-bold text-slate-800">{{ $logActual->cedula ?: '—' }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Teléfono</div>
                            <div class="text-base font-mono font-bold text-slate-800">{{ $logActual->telefono ?: '—' }}</div>
                        </div>
                        @if($logActual->nombre)
                            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 col-span-2">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Nombre</div>
                                <div class="text-base font-bold text-slate-800">{{ $logActual->nombre }}</div>
                            </div>
                        @endif
                        @if($logActual->direccion)
                            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 col-span-2">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Dirección</div>
                                <div class="text-sm text-slate-800">{{ $logActual->direccion }}</div>
                            </div>
                        @endif
                    </div>

                    @if($logActual->datos_cliente_erp)
                        @php $datos = json_decode($logActual->datos_cliente_erp, true) ?? []; @endphp
                        <div class="rounded-2xl bg-emerald-50 border-2 border-emerald-200 p-4">
                            <div class="text-xs font-bold uppercase tracking-wider text-emerald-800 mb-2">
                                <i class="fa-solid fa-database"></i> Datos del cliente en el ERP
                            </div>
                            <div class="rounded-lg bg-white border border-emerald-100 p-3 grid grid-cols-2 gap-2 text-xs">
                                @foreach($datos as $col => $val)
                                    <div>
                                        <div class="text-[10px] font-bold text-slate-500">{{ $col }}</div>
                                        <div class="text-slate-800 truncate" title="{{ $val }}">{{ $val ?: '—' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($logActual->error_mensaje)
                        <div class="rounded-2xl bg-rose-50 border-2 border-rose-200 p-4">
                            <div class="text-xs font-bold uppercase tracking-wider text-rose-800 mb-2">
                                <i class="fa-solid fa-triangle-exclamation"></i> Error
                            </div>
                            <pre class="text-xs text-rose-900 whitespace-pre-wrap font-mono bg-white/60 rounded-lg p-3 border border-rose-100">{{ $logActual->error_mensaje }}</pre>
                        </div>
                    @endif

                    @if($logActual->cedula)
                        <div class="rounded-xl bg-slate-900 p-4 text-white">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-300 mb-2">
                                <i class="fa-solid fa-terminal"></i> Verifica en SQL Server
                            </div>
                            <code class="text-emerald-300 font-mono text-xs block">SELECT * FROM TblTerceros WHERE StrIdTercero = '{{ $logActual->cedula }}'</code>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-end">
                    <button wire:click="cerrarLog"
                            class="rounded-xl border border-slate-300 hover:bg-slate-100 text-slate-700 px-5 py-2.5 text-sm font-bold transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
