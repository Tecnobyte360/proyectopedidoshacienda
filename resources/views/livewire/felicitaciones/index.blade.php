<div class="p-4 lg:p-8 space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                🎂 Historial de felicitaciones
            </h2>
            <p class="text-sm text-slate-500">
                Trazabilidad de los mensajes de cumpleaños enviados a los clientes.
            </p>
        </div>
    </div>

    {{-- Métricas --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Total en {{ $anio }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800">{{ $totales['total'] }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">✅ Enviados</p>
            <p class="mt-1 text-2xl font-extrabold text-emerald-600">{{ $totales['enviados'] }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">❌ Fallidos</p>
            <p class="mt-1 text-2xl font-extrabold {{ $totales['fallidos'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                {{ $totales['fallidos'] }}
            </p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">👁️ Dry-run (prueba)</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-500">{{ $totales['dry_run'] }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="rounded-2xl bg-white border border-slate-200 p-4">
        <div class="grid md:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Año</label>
                <select wire:model.live="anio"
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    @foreach($aniosDisponibles as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Estado</label>
                <select wire:model.live="filtroEstado"
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    <option value="todas">Todos</option>
                    <option value="enviado">✅ Enviados</option>
                    <option value="fallido">❌ Fallidos</option>
                    <option value="dry_run">👁️ Dry-run</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Origen</label>
                <select wire:model.live="filtroOrigen"
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    <option value="todos">Todos</option>
                    <option value="scheduled">🤖 Automático</option>
                    <option value="manual">✋ Manual</option>
                    <option value="force">⚡ Force</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">
                    <i class="fa-brands fa-whatsapp"></i> Conexión
                </label>
                <select wire:model.live="filtroConexion"
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    <option value="todas">Todas</option>
                    <option value="ninguna">Sin conexión asignada</option>
                    @foreach($conexionesUsadas as $c)
                        <option value="{{ $c }}">WhatsApp #{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar</label>
                <input type="text" wire:model.live.debounce.400ms="busqueda"
                       placeholder="Nombre o teléfono..."
                       class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
            </div>
        </div>
    </div>

    {{-- Lista --}}
    <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
        @if($felicitaciones->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <i class="fa-solid fa-cake-candles text-5xl text-pink-300 mb-3"></i>
                <p class="text-lg font-semibold text-slate-600">Sin felicitaciones registradas</p>
                <p class="text-sm">Cuando el sistema envíe (o intente enviar) felicitaciones, aparecerán aquí.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Cliente</th>
                            <th class="px-4 py-3 text-left">Teléfono</th>
                            <th class="px-4 py-3 text-left">Conexión</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Origen</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($felicitaciones as $f)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $f->cliente_nombre }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $f->telefono }}</td>
                                <td class="px-4 py-3">
                                    @if($f->connection_id)
                                        <span class="inline-flex items-center gap-1 text-xs font-mono px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            <i class="fa-brands fa-whatsapp"></i> #{{ $f->connection_id }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full
                                                 @if($f->estado === 'enviado') bg-emerald-100 text-emerald-700
                                                 @elseif($f->estado === 'fallido') bg-rose-100 text-rose-700
                                                 @else bg-slate-100 text-slate-600
                                                 @endif">
                                        {{ $f->badgeIcono() }} {{ ucfirst(str_replace('_', ' ', $f->estado)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    @switch($f->origen)
                                        @case('scheduled') 🤖 Automático @break
                                        @case('manual')    ✋ Manual @break
                                        @case('force')     ⚡ Force @break
                                        @default           {{ $f->origen }}
                                    @endswitch
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    <div>{{ $f->enviado_at?->format('d/m/Y H:i') }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $f->enviado_at?->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="abrirDetalle({{ $f->id }})"
                                            class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                                        <i class="fa-solid fa-eye"></i> Ver
                                    </button>
                                    @if($f->estado === 'fallido')
                                        <button wire:click="reintentar({{ $f->id }})"
                                                wire:confirm="¿Reintentar este envío ahora?"
                                                class="text-xs px-3 py-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 font-semibold">
                                            <i class="fa-solid fa-rotate"></i> Reintentar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $felicitaciones->links() }}
            </div>
        @endif
    </div>

    {{-- Modal detalle --}}
    @if($detalle)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             wire:click.self="cerrarDetalle">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-pink-50 to-white">
                    <div>
                        <h3 class="font-bold text-slate-800">🎂 Felicitación a {{ $detalle->cliente_nombre }}</h3>
                        <p class="text-xs text-slate-500">{{ $detalle->telefono }} · {{ $detalle->enviado_at?->format('d/m/Y H:i:s') }}</p>
                    </div>
                    <button wire:click="cerrarDetalle" class="text-slate-400 hover:text-slate-700 text-xl">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="px-5 py-4 overflow-y-auto space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Estado</p>
                            <p class="text-slate-800 font-semibold">{{ $detalle->badgeIcono() }} {{ ucfirst(str_replace('_', ' ', $detalle->estado)) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Origen</p>
                            <p class="text-slate-800">{{ $detalle->origen }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Conexión WhatsApp</p>
                            <p class="text-slate-800 font-mono">
                                @if($detalle->connection_id)
                                    <i class="fa-brands fa-whatsapp text-emerald-600"></i> #{{ $detalle->connection_id }}
                                @else
                                    <span class="text-slate-400">Por defecto del sistema</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Año</p>
                            <p class="text-slate-800">{{ $detalle->anio }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">ID</p>
                            <p class="text-slate-800 font-mono">#{{ $detalle->id }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Teléfono destino</p>
                            <p class="text-slate-800 font-mono">{{ $detalle->telefono }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide mb-1">Mensaje enviado</p>
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-slate-800 whitespace-pre-line">{{ $detalle->mensaje }}</div>
                    </div>

                    @if($detalle->error_detalle)
                        <div>
                            <p class="text-xs font-semibold uppercase text-rose-600 tracking-wide mb-1">⚠️ Error</p>
                            <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 whitespace-pre-line">{{ $detalle->error_detalle }}</div>
                        </div>
                    @endif
                </div>

                <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
                    @if($detalle->estado === 'fallido')
                        <button wire:click="reintentar({{ $detalle->id }})"
                                class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">
                            <i class="fa-solid fa-rotate mr-1"></i> Reintentar
                        </button>
                    @endif
                    <button wire:click="cerrarDetalle"
                            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
