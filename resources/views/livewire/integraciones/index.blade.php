<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-plug text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Integraciones</h2>
                        <p class="text-sm text-slate-500">Conecta a la BD externa de esta empresa y sincroniza productos o categorías</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('integraciones.exports') }}"
                       class="inline-flex items-center gap-2 rounded-2xl border-2 border-[#fbe9d7] bg-white hover:bg-brand-soft/40 text-brand-dark font-bold px-5 py-3 transition shadow-sm">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Ver exports
                    </a>
                    <button wire:click="abrirCrear"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                        <i class="fa-solid fa-plus"></i> Nueva integración
                    </button>
                </div>
            </div>
        </div>

        {{-- LISTA --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Entidad</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Última sync</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($integraciones as $i)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $i->nombre }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-mono text-slate-700 uppercase">{{ $i->tipo }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-md bg-violet-100 text-violet-700 px-2 py-1 text-xs font-semibold">{{ $i->entidad }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($i->activo)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Activo</span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                @if($i->ultima_sincronizacion_at)
                                    <div class="flex flex-col">
                                        <span>{{ $i->ultima_sincronizacion_at->diffForHumans() }}</span>
                                        <span class="text-[10px]">
                                            @if($i->ultima_sincronizacion_estado === 'ok')
                                                <i class="fa-solid fa-check text-emerald-500"></i> {{ $i->total_registros_ultima_sync }} registros
                                            @else
                                                <i class="fa-solid fa-triangle-exclamation text-rose-500"></i> Error
                                            @endif
                                        </span>
                                    </div>
                                @else
                                    <span class="text-slate-400">Nunca</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('integraciones.consultas', $i->id) }}"
                                       title="Consultas guardadas"
                                       class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-brand-soft hover:bg-brand-soft-2 text-brand transition">
                                        <i class="fa-solid fa-database text-xs"></i>
                                    </a>
                                    <button wire:click="sincronizar({{ $i->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="sincronizar({{ $i->id }})"
                                            title="Sincronizar ahora"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 transition disabled:opacity-50">
                                        <i class="fa-solid fa-rotate" wire:loading.remove wire:target="sincronizar({{ $i->id }})"></i>
                                        <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="sincronizar({{ $i->id }})"></i>
                                    </button>
                                    <button wire:click="abrirEditar({{ $i->id }})"
                                            title="Editar"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    <button wire:click="toggleActivo({{ $i->id }})"
                                            title="{{ $i->activo ? 'Desactivar' : 'Activar' }}"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 transition">
                                        <i class="fa-solid {{ $i->activo ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                                    </button>
                                    <button wire:click="eliminar({{ $i->id }})"
                                            wire:confirm="¿Eliminar esta integración?"
                                            title="Eliminar"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        @if($i->ultima_sincronizacion_log)
                            <tr class="bg-slate-50/50">
                                <td colspan="6" class="px-4 py-2">
                                    <details class="text-xs text-slate-600">
                                        <summary class="cursor-pointer font-semibold hover:text-slate-800">Ver log de última sincronización</summary>
                                        <pre class="mt-2 bg-slate-900 text-emerald-300 p-3 rounded-lg overflow-x-auto text-[11px] whitespace-pre-wrap">{{ $i->ultima_sincronizacion_log }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                                    <i class="fa-solid fa-plug text-2xl"></i>
                                </div>
                                <p class="text-base font-semibold text-slate-700">Sin integraciones</p>
                                <p class="text-sm text-slate-500">Crea la primera para conectar a tu ERP / POS / BD externa.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL --}}
    @if($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white">
                            <i class="fa-solid {{ $editandoId ? 'fa-pen-to-square' : 'fa-plus' }}"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-800">
                                {{ $editandoId ? 'Editar integración' : 'Nueva integración' }}
                            </h3>
                            <p class="text-xs text-slate-500">Conecta a la BD externa de esta empresa</p>
                        </div>
                    </div>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                    {{-- Básicos --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="ERP Producción"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100">
                            @error('nombre') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de BD *</label>
                            <select wire:model="tipo" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="sqlsrv">SQL Server</option>
                                <option value="mysql">MySQL / MariaDB</option>
                                <option value="pgsql">PostgreSQL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Entidad a sincronizar *</label>
                            <select wire:model="entidad" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                                <option value="productos">Productos</option>
                                <option value="categorias">Categorías</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="activo" class="rounded text-brand">
                                <span class="font-semibold text-slate-700">Integración activa</span>
                            </label>
                        </div>
                    </div>

                    {{-- Conexión --}}
                    <div class="rounded-xl border-2 border-slate-200 p-4 space-y-3">
                        <h4 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-server text-brand"></i> Conexión</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Host / IP *</label>
                                <input type="text" wire:model="host" placeholder="192.168.1.100 o servidor.empresa.com"
                                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Puerto</label>
                                <input type="text" wire:model="port" placeholder="1433"
                                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Base de datos *</label>
                            <input type="text" wire:model="database" placeholder="HaciendaERP"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Usuario</label>
                                <input type="text" wire:model="username" placeholder="sa"
                                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                                <input type="password" wire:model="password"
                                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                            </div>
                        </div>
                    </div>

                    {{-- Explorador BD --}}
                    <div class="rounded-xl border-2 border-dashed border-sky-300 bg-sky-50 p-4 space-y-3">
                        <h4 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-database text-sky-600"></i> Explorador de la BD</h4>
                        <p class="text-xs text-slate-600">
                            Primero llena host, puerto, BD, usuario y password arriba.
                            Luego haz clic aquí para ver TODAS las tablas de la base:
                        </p>
                        <button wire:click="listarTablas"
                                wire:loading.attr="disabled"
                                wire:target="listarTablas"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-4 py-3 text-sm transition disabled:opacity-50 border border-slate-200">
                            <i class="fa-solid fa-magnifying-glass text-slate-500" wire:loading.remove wire:target="listarTablas"></i>
                            <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="listarTablas"></i>
                            <span wire:loading.remove wire:target="listarTablas">Listar todas las tablas de la BD</span>
                            <span wire:loading wire:target="listarTablas">Conectando...</span>
                        </button>

                        @if($explorarError)
                            <p class="text-xs text-rose-600 font-semibold bg-rose-50 border border-rose-200 rounded p-2">
                                <i class="fa-solid fa-triangle-exclamation"></i> {{ $explorarError }}
                            </p>
                        @endif

                        @if(!empty($tablas))
                            @php
                                // Agrupar por schema (dbo.tabla → dbo=[tabla, ...])
                                $grupos = [];
                                foreach ($tablas as $t) {
                                    if (str_contains($t, '.')) {
                                        [$sch, $tbl] = explode('.', $t, 2);
                                    } else {
                                        $sch = 'default'; $tbl = $t;
                                    }
                                    $grupos[$sch][] = $tbl;
                                }
                                ksort($grupos);

                                // Buscador para filtrar
                            @endphp

                            <div x-data="{ filtro: '', abiertos: {{ json_encode(array_fill_keys(array_keys($grupos), true)) }} }">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-bold text-slate-700">
                                        <i class="fa-solid fa-database text-sky-600"></i>
                                        {{ count($tablas) }} tablas en {{ count($grupos) }} schema(s)
                                    </p>
                                    <div class="relative">
                                        <i class="fa-solid fa-search absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
                                        <input type="text" x-model="filtro" placeholder="Filtrar tablas..."
                                               class="rounded-lg border border-sky-200 pl-6 pr-2 py-1 text-xs w-48 focus:border-sky-400 focus:ring-1 focus:ring-sky-200">
                                    </div>
                                </div>

                                <div class="max-h-72 overflow-y-auto bg-white border border-sky-200 rounded-lg p-2 font-mono">
                                    @foreach($grupos as $schema => $tablasSchema)
                                        <div class="mb-1">
                                            {{-- Schema header --}}
                                            <button @click="abiertos['{{ $schema }}'] = !abiertos['{{ $schema }}']"
                                                    type="button"
                                                    class="w-full flex items-center gap-2 px-2 py-1.5 rounded hover:bg-sky-50 transition text-left">
                                                <i class="fa-solid text-slate-500 text-[10px] w-3"
                                                   :class="abiertos['{{ $schema }}'] ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                                <i class="fa-solid fa-folder-open text-amber-500"></i>
                                                <span class="text-sm font-bold text-slate-800">{{ $schema }}</span>
                                                <span class="text-[10px] text-slate-500 ml-auto">{{ count($tablasSchema) }} tablas</span>
                                            </button>

                                            {{-- Tables --}}
                                            <div x-show="abiertos['{{ $schema }}']" x-collapse class="ml-6 mt-0.5 space-y-0.5 border-l-2 border-slate-100 pl-2">
                                                @foreach($tablasSchema as $tbl)
                                                    @php $full = $schema === 'default' ? $tbl : "$schema.$tbl"; @endphp
                                                    <button wire:click="seleccionarTabla('{{ $full }}')"
                                                            type="button"
                                                            x-show="filtro === '' || '{{ strtolower($tbl) }}'.includes(filtro.toLowerCase())"
                                                            class="w-full flex items-center gap-2 px-2 py-1 rounded text-left transition text-xs
                                                                   {{ $tablaSeleccionada === $full ? 'bg-sky-500 text-white font-bold' : 'text-slate-700 hover:bg-sky-50' }}">
                                                        <i class="fa-solid fa-table {{ $tablaSeleccionada === $full ? 'text-white' : 'text-sky-400' }}"></i>
                                                        <span>{{ $tbl }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($tablaSeleccionada && !empty($columnas))
                            <div class="bg-white border-2 border-emerald-200 rounded-lg p-3">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white">
                                        <i class="fa-solid fa-table"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-800">{{ $tablaSeleccionada }}</p>
                                        <p class="text-[10px] text-slate-500">{{ count($columnas) }} columnas</p>
                                    </div>
                                </div>

                                <table class="w-full text-[11px]">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-2 py-1 text-left font-bold text-slate-600">Columna</th>
                                            <th class="px-2 py-1 text-left font-bold text-slate-600">Tipo</th>
                                            <th class="px-2 py-1 text-left font-bold text-slate-600">Null</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($columnas as $c)
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-2 py-1 font-mono font-semibold text-slate-800">{{ $c['nombre'] }}</td>
                                                <td class="px-2 py-1 font-mono text-slate-600">{{ $c['tipo'] }}</td>
                                                <td class="px-2 py-1 text-slate-500">{{ strtoupper($c['nullable'] ?? '') === 'YES' ? '✓' : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <p class="text-[10px] text-emerald-700 mt-3 font-semibold bg-emerald-50 rounded p-2">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    Query y mapeo auto-rellenados abajo. Ajústalos si hace falta.
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Query --}}
                    <div class="rounded-xl border-2 border-slate-200 p-4 space-y-2">
                        <h4 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-code text-brand"></i> Query SQL</h4>
                        <p class="text-xs text-slate-500">
                            Usa alias (<code>AS codigo</code>, <code>AS nombre</code>, etc.) que coincidan con los campos destino, o configura el mapeo abajo.
                        </p>
                        <textarea wire:model="query" rows="6" spellcheck="false"
                                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono bg-slate-50"></textarea>
                    </div>

                    {{-- Mapeo (solo productos) --}}
                    @if($entidad === 'productos')
                        <div class="rounded-xl border-2 border-slate-200 p-4">
                            <h4 class="font-bold text-slate-800 text-sm mb-2"><i class="fa-solid fa-arrow-right-arrow-left text-brand"></i> Mapeo de columnas</h4>
                            <p class="text-xs text-slate-500 mb-3">Nombre de la columna del SELECT → campo del producto local.</p>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach(['codigo', 'nombre', 'categoria', 'precio_base', 'unidad', 'descripcion'] as $campo)
                                    <div>
                                        <label class="block text-[10px] uppercase font-bold text-slate-600 mb-1">{{ str_replace('_', ' ', $campo) }}</label>
                                        <input type="text" wire:model="mapeo.{{ $campo }}"
                                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-mono">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- 🚀 EXPORT DE PEDIDOS AL ERP --}}
                    <div class="rounded-xl border-2 border-blue-200 bg-blue-50 p-5 mb-4">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-solid fa-cloud-arrow-up text-blue-600 text-lg"></i>
                            <h4 class="font-bold text-slate-800">Exportar pedidos al ERP</h4>
                        </div>
                        <p class="text-xs text-slate-600 mb-3">
                            Si está activo, cuando un cliente confirme un pedido por WhatsApp, el sistema
                            ejecutará automáticamente un <code>INSERT INTO {{ $export_tabla ?: 'TblDocumentos' }}</code>
                            en esta base de datos con los datos del pedido (cédula, total, fecha, etc).
                        </p>

                        <label class="flex items-start gap-3 cursor-pointer rounded-xl border-2 p-3 transition bg-white mb-3
                                      {{ $exporta_pedidos ? 'border-blue-400' : 'border-slate-200' }}">
                            <input type="checkbox" wire:model.live="exporta_pedidos"
                                   class="mt-1 rounded border-slate-300 text-blue-600 h-5 w-5">
                            <div class="flex-1">
                                <div class="text-sm font-bold text-slate-800">
                                    📤 Exportar pedidos confirmados a esta BD
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Cada pedido nuevo se insertará en la tabla <code>{{ $export_tabla }}</code> del ERP.
                                </div>
                            </div>
                        </label>

                        @if($exporta_pedidos)
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tabla destino</label>
                                    <input type="text" wire:model="export_tabla"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">IntEmpresa</label>
                                    <input type="number" wire:model="export_empresa"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">IntTransaccion</label>
                                    <input type="text" wire:model="export_transaccion"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="009">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">IntBodega</label>
                                    <input type="number" wire:model="export_bodega"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">IntCartera</label>
                                    <input type="number" wire:model="export_cartera"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">StrUsuarioGra</label>
                                    <input type="text" wire:model="export_usuario"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="admin">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Consecutivo inicial</label>
                                    <input type="number" wire:model="export_consecutivo"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <p class="text-[10px] text-slate-500 mt-1">Si está vacío, calcula MAX+1</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">IntAno</label>
                                    <input type="number" wire:model="export_ano"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">IntPeriodo</label>
                                    <input type="number" wire:model="export_periodo"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">StrPlazo</label>
                                    <input type="text" wire:model="export_plazo"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">StrSucursal</label>
                                    <input type="text" wire:model="export_sucursal"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">StrCcosto</label>
                                    <input type="text" wire:model="export_ccosto"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">StrSubCcosto</label>
                                    <input type="text" wire:model="export_subccosto"
                                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                            </div>

                            {{-- 🔧 TOGGLE: desactivar triggers --}}
                            <div class="mt-3 rounded-lg bg-rose-50 border-2 border-rose-200 px-4 py-3">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model="export_disable_triggers"
                                           class="mt-1 rounded border-slate-300 text-rose-600 h-5 w-5">
                                    <div class="flex-1">
                                        <div class="text-sm font-bold text-rose-800 flex items-center gap-2">
                                            <i class="fa-solid fa-shield-halved"></i>
                                            Desactivar triggers durante el INSERT
                                        </div>
                                        <div class="text-[11px] text-rose-700 mt-1 leading-relaxed">
                                            Si tu ERP tiene triggers en <strong>{{ $export_tabla }}</strong> o
                                            <strong>{{ $export_detalle_tabla }}</strong> que validan terceros/saldos
                                            y abortan el INSERT (error <code>20018 transaction ended in trigger</code>),
                                            activa esto. El sistema ejecutará:
                                            <code class="bg-rose-100 px-1 rounded text-[10px]">DISABLE TRIGGER ALL</code>
                                            antes y <code class="bg-rose-100 px-1 rounded text-[10px]">ENABLE TRIGGER ALL</code>
                                            después del INSERT.<br>
                                            ⚠️ <strong>Requiere permiso ALTER en la tabla.</strong> Idealmente prefiere
                                            crear el cliente/producto en el ERP antes que desactivar triggers.
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] text-amber-800">
                                💡 <strong>Variables disponibles</strong> en cualquier campo (escríbelas con llaves):<br>

                                <strong>Cliente:</strong>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{cliente.cedula}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{cliente.nombre}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{cliente.telefono}</code><br>

                                <strong>Pedido:</strong>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{pedido.id}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{pedido.total}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{pedido.fecha}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{pedido.fecha_hora}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{consecutivo}</code><br>

                                <strong>Fecha actual:</strong>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{ano}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{mes}</code>
                                <code class="text-[10px] bg-amber-100 px-1 rounded mx-0.5">{dia}</code>
                                ← <strong>{mes} cambia automáticamente cada mes</strong> (1=enero, 12=diciembre)<br>

                                <em>Ej:</em> en <strong>IntPeriodo</strong> puedes poner <code>{mes}</code> y siempre tendrá el mes en curso sin actualizar manualmente.
                            </div>

                            {{-- 📋 DETALLE — TblDetalleDocumentos --}}
                            <div class="mt-4 pt-4 border-t-2 border-blue-200">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-list-ol text-blue-600"></i>
                                    <h5 class="font-bold text-slate-800 text-sm">Detalle (líneas del pedido)</h5>
                                </div>
                                <p class="text-[11px] text-slate-600 mb-3">
                                    Adicionalmente, ¿quieres insertar también <strong>UNA fila por cada producto</strong>
                                    en una tabla de detalle (TblDetalleDocumentos)?
                                </p>

                                <label class="flex items-start gap-3 cursor-pointer rounded-xl border-2 p-3 transition bg-white mb-2
                                              {{ $export_detalle_activo ? 'border-blue-400' : 'border-slate-200' }}">
                                    <input type="checkbox" wire:model.live="export_detalle_activo"
                                           class="mt-1 rounded border-slate-300 text-blue-600 h-5 w-5">
                                    <div class="flex-1">
                                        <div class="text-sm font-bold text-slate-800">📋 Insertar también el detalle</div>
                                        <div class="text-[11px] text-slate-500">
                                            Por cada producto del pedido, se hará un <code>INSERT INTO {{ $export_detalle_tabla }}</code>.
                                            Cantidad, código, precio unitario, etc., se llenan automáticamente.
                                        </div>
                                    </div>
                                </label>

                                @if($export_detalle_activo)
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                        <div class="md:col-span-3">
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tabla detalle</label>
                                            <input type="text" wire:model="export_detalle_tabla"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="TblDetalleDocumentos">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">StrProducto</label>
                                            <input type="text" wire:model="export_det_producto"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="{detalle.codigo}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">IntCantidad</label>
                                            <input type="text" wire:model="export_det_cantidad"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="{detalle.cantidad}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">StrUnidad</label>
                                            <input type="text" wire:model="export_det_unidad"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="{detalle.unidad}">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">IntValorUnitario</label>
                                            <input type="text" wire:model="export_det_valor_unitario"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="{detalle.precio}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">IntValorTotal</label>
                                            <input type="text" wire:model="export_det_valor_total"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="{detalle.subtotal}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">IntValorIva</label>
                                            <input type="text" wire:model="export_det_iva"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="0">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">IntVrImpuesto1</label>
                                            <input type="text" wire:model="export_det_impuesto1"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="0">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">StrSerie</label>
                                            <input type="text" wire:model="export_det_serie"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="0">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">IntPorDescuento</label>
                                            <input type="text" wire:model="export_det_por_descuento"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="1">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">IntValorDescuento</label>
                                            <input type="text" wire:model="export_det_valor_descuento"
                                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="0">
                                        </div>
                                    </div>

                                    <div class="mt-3 rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-[11px] text-emerald-800">
                                        ✓ <strong>Variables del detalle</strong>:<br>
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1">{detalle.codigo}</code>SKU/código
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.nombre}</code>nombre
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.cantidad}</code>cantidad
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.unidad}</code>Und/Kg<br>
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1">{detalle.precio}</code>precio unitario
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.subtotal}</code>cantidad × precio
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.numero}</code><strong>← StrSerie incremental (1,2,3...)</strong><br>
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1">{detalle.descuento_porcentaje}</code>
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.descuento_valor}</code>
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.iva}</code>
                                        <code class="text-[10px] bg-emerald-100 px-1 rounded mr-1 ml-2">{detalle.impuesto_consumo}</code><br>
                                        <strong>Tip:</strong> también puedes usar variables del header como <code class="text-[10px] bg-emerald-100 px-1 rounded">{cliente.cedula}</code>, <code class="text-[10px] bg-emerald-100 px-1 rounded">{consecutivo}</code>, <code class="text-[10px] bg-emerald-100 px-1 rounded">{pedido.fecha}</code>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Test --}}
                    <div class="rounded-xl border-2 border-dashed border-[#fbe9d7] bg-brand-soft/30 p-4">
                        <button wire:click="probarConexion"
                                wire:loading.attr="disabled"
                                wire:target="probarConexion"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-semibold px-5 py-2.5 text-sm transition disabled:opacity-50 shadow">
                            <i class="fa-solid fa-vial" wire:loading.remove wire:target="probarConexion"></i>
                            <i class="fa-solid fa-circle-notch fa-spin" wire:loading wire:target="probarConexion"></i>
                            Probar conexión y query
                        </button>

                        @if($testResult)
                            <div class="mt-3">
                                @if($testResult['ok'])
                                    <p class="text-sm font-semibold text-emerald-700"><i class="fa-solid fa-check-circle"></i> {{ $testResult['mensaje'] }}</p>
                                    @if(!empty($testResult['muestra']))
                                        @php
                                            $pg = $testResult['page'] ?? 1;
                                            $lp = $testResult['last_page'] ?? 1;
                                            $pp = $testResult['per_page'] ?? 25;
                                            $tot = $testResult['total'] ?? 0;
                                            $desde = ($pg - 1) * $pp + 1;
                                            $hasta = $desde + count($testResult['muestra']) - 1;
                                        @endphp

                                        {{-- Toolbar superior: total + selector page size --}}
                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                                            <div>
                                                @if($tot > 0)
                                                    Mostrando <strong>{{ $desde }}–{{ $hasta }}</strong> de <strong>{{ number_format($tot) }}</strong>
                                                @else
                                                    {{ count($testResult['muestra']) }} filas
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span>Filas por página:</span>
                                                @foreach([25, 50, 100, 200] as $sz)
                                                    <button type="button" wire:click="pruebaCambiarPerPage({{ $sz }})"
                                                            class="px-2 py-0.5 rounded {{ $pp === $sz ? 'bg-emerald-600 text-white font-bold' : 'bg-white border border-slate-200 hover:bg-slate-100' }}">
                                                        {{ $sz }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="mt-2 overflow-x-auto bg-white rounded-lg border border-slate-200 p-2 max-h-96 overflow-y-auto">
                                            <table class="w-full text-[11px]">
                                                <thead class="bg-slate-100 sticky top-0">
                                                    <tr>
                                                        @foreach(array_keys($testResult['muestra'][0] ?? []) as $col)
                                                            <th class="px-2 py-1 text-left font-bold">{{ $col }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($testResult['muestra'] as $row)
                                                        <tr>
                                                            @foreach($row as $val)
                                                                <td class="px-2 py-1 font-mono">{{ mb_strimwidth((string) $val, 0, 60, '…') }}</td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        {{-- Paginador --}}
                                        @if($lp > 1)
                                            <div class="mt-2 flex items-center justify-center gap-1 text-xs">
                                                <button type="button" wire:click="pruebaIrPagina(1)"
                                                        @disabled($pg <= 1)
                                                        class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40">
                                                    « Primera
                                                </button>
                                                <button type="button" wire:click="pruebaIrPagina({{ $pg - 1 }})"
                                                        @disabled($pg <= 1)
                                                        class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40">
                                                    ‹ Anterior
                                                </button>
                                                <span class="px-3 py-1 rounded bg-emerald-600 text-white font-bold">
                                                    {{ $pg }} / {{ $lp }}
                                                </span>
                                                <button type="button" wire:click="pruebaIrPagina({{ $pg + 1 }})"
                                                        @disabled($pg >= $lp)
                                                        class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40">
                                                    Siguiente ›
                                                </button>
                                                <button type="button" wire:click="pruebaIrPagina({{ $lp }})"
                                                        @disabled($pg >= $lp)
                                                        class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40">
                                                    Última »
                                                </button>
                                            </div>
                                        @endif
                                    @endif
                                @else
                                    <p class="text-sm font-semibold text-rose-700"><i class="fa-solid fa-triangle-exclamation"></i> Error: {{ $testResult['mensaje'] }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="px-6 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarModal"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button wire:click="guardar"
                            class="rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-5 py-2 text-sm font-bold text-white shadow-lg">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
