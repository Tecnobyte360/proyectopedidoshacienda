<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6">
        <a href="{{ route('integraciones.index') }}" class="text-xs text-slate-500 hover:text-slate-800">
            <i class="fa-solid fa-arrow-left mr-1"></i> Volver a integraciones
        </a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-slate-800">
                    <i class="fa-solid fa-database text-purple-600 mr-2"></i>
                    Consultas de {{ $integracion->nombre }}
                </h2>
                <p class="text-sm text-slate-500">
                    Cada consulta SQL guardada se puede ejecutar desde aquí o exponerse como tool del bot agente.
                </p>
            </div>
            <button wire:click="abrirCrear"
                    class="rounded-2xl bg-purple-600 px-5 py-3 text-white font-semibold shadow hover:bg-purple-700 transition">
                <i class="fa-solid fa-plus mr-2"></i> Nueva consulta
            </button>
        </div>
    </div>

    {{-- LISTADO --}}
    <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">
        @if ($consultas->isEmpty())
            <div class="p-12 text-center">
                <i class="fa-solid fa-database text-5xl text-slate-300 mb-4"></i>
                <h3 class="text-lg font-bold text-slate-700 mb-1">Sin consultas guardadas</h3>
                <p class="text-sm text-slate-500 mb-4">Crea tu primera consulta — puede ser para clientes, productos, ventas, etc.</p>
                <button wire:click="abrirCrear" class="rounded-xl bg-purple-600 text-white px-4 py-2 text-sm font-semibold hover:bg-purple-700">
                    <i class="fa-solid fa-plus mr-1"></i> Crear consulta
                </button>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Nombre</th>
                        <th class="px-4 py-3 text-left">Tipo</th>
                        <th class="px-4 py-3 text-center">Params</th>
                        <th class="px-4 py-3 text-center">Bot</th>
                        <th class="px-4 py-3 text-center">Activa</th>
                        <th class="px-4 py-3 text-right">Ejecuciones</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($consultas as $c)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800">{{ $c->nombre_publico }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $c->nombreTool() }}</div>
                                @if ($c->descripcion)
                                    <div class="text-xs text-slate-500 mt-1">{{ \Illuminate\Support\Str::limit($c->descripcion, 80) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs">{{ \App\Models\IntegracionConsulta::TIPOS[$c->tipo] ?? $c->tipo }}</span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-slate-600">
                                {{ count($c->parametros ?? []) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleBot({{ $c->id }})"
                                        class="text-xl {{ $c->usar_en_bot ? 'text-purple-600' : 'text-slate-300 hover:text-slate-500' }}"
                                        title="{{ $c->usar_en_bot ? 'Disponible para el bot' : 'No disponible para el bot' }}">
                                    <i class="fa-solid fa-robot"></i>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleActiva({{ $c->id }})"
                                        class="text-xl {{ $c->activa ? 'text-emerald-600' : 'text-slate-300' }}"
                                        title="{{ $c->activa ? 'Activa' : 'Inactiva' }}">
                                    <i class="fa-solid fa-{{ $c->activa ? 'circle-check' : 'circle' }}"></i>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">
                                {{ number_format($c->total_ejecuciones) }}
                                @if ($c->ultima_ejecucion_at)
                                    <div class="text-[10px] text-slate-400">{{ $c->ultima_ejecucion_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right space-x-1">
                                <button wire:click="abrirProbar({{ $c->id }})"
                                        class="text-blue-600 hover:text-blue-800" title="Probar">
                                    <i class="fa-solid fa-play"></i>
                                </button>
                                <button wire:click="abrirEditar({{ $c->id }})"
                                        class="text-slate-600 hover:text-slate-900" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button wire:click="eliminar({{ $c->id }})"
                                        wire:confirm="¿Eliminar esta consulta? No se puede deshacer."
                                        class="text-rose-600 hover:text-rose-800" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- MODAL CREAR/EDITAR --}}
    @if ($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="$set('modalAbierto', false)">
            <div class="w-full max-w-3xl rounded-3xl bg-white shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold text-slate-800">
                        <i class="fa-solid fa-database text-purple-600 mr-1"></i>
                        {{ $editandoId ? 'Editar' : 'Nueva' }} consulta
                    </h3>
                    <button wire:click="$set('modalAbierto', false)" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <div class="overflow-y-auto p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nombre técnico (snake_case)</label>
                            <input type="text" wire:model="nombre" placeholder="buscar_cliente_por_cedula"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                            @error('nombre') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tipo</label>
                            <select wire:model="tipo" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                @foreach (\App\Models\IntegracionConsulta::TIPOS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nombre público (display)</label>
                        <input type="text" wire:model="nombre_publico" placeholder="Buscar cliente por cédula"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        @error('nombre_publico') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Descripción (qué hace — el bot la usa para decidir cuándo llamarla)</label>
                        <textarea wire:model="descripcion" rows="2" placeholder="Busca un cliente en el ERP por su número de cédula y devuelve su info de contacto."
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Query SQL</label>
                        <textarea wire:model="query_sql" rows="6" placeholder="SELECT id, nombre, telefono, email FROM clientes WHERE cedula = :cedula"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono"></textarea>
                        <p class="text-[11px] text-slate-500 mt-1">
                            💡 Usa <code>:nombre</code> para parámetros nombrados (recomendado) o <code>?</code> para posicionales.
                        </p>
                    </div>

                    {{-- Parametros --}}
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-bold text-slate-700">Parámetros</label>
                            <button type="button" wire:click="agregarParametro"
                                    class="text-xs font-semibold text-purple-600 hover:text-purple-800">
                                <i class="fa-solid fa-plus mr-1"></i> Agregar parámetro
                            </button>
                        </div>
                        @if (empty($parametros))
                            <p class="text-[11px] text-slate-400">Sin parámetros. La consulta se ejecuta tal cual.</p>
                        @else
                            <div class="space-y-2">
                                @foreach ($parametros as $i => $p)
                                    <div class="grid grid-cols-12 gap-2" wire:key="param-{{ $i }}">
                                        <input type="text" wire:model="parametros.{{ $i }}.nombre" placeholder="cedula"
                                               class="col-span-3 rounded-lg border border-slate-200 px-2 py-1 text-xs font-mono">
                                        <select wire:model="parametros.{{ $i }}.tipo"
                                                class="col-span-2 rounded-lg border border-slate-200 px-2 py-1 text-xs">
                                            <option value="string">texto</option>
                                            <option value="number">número</option>
                                            <option value="date">fecha</option>
                                            <option value="boolean">bool</option>
                                        </select>
                                        <input type="text" wire:model="parametros.{{ $i }}.descripcion" placeholder="Cédula del cliente"
                                               class="col-span-6 rounded-lg border border-slate-200 px-2 py-1 text-xs">
                                        <button type="button" wire:click="eliminarParametro({{ $i }})"
                                                class="col-span-1 text-rose-600 hover:text-rose-800">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                            <input type="checkbox" wire:model="usar_en_bot" class="mt-1 rounded text-purple-600">
                            <div>
                                <div class="text-sm font-bold text-slate-800">🤖 Disponible para el bot</div>
                                <div class="text-[11px] text-slate-500">El agente puede llamarla como tool.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                            <input type="checkbox" wire:model="activa" class="mt-1 rounded text-emerald-600">
                            <div>
                                <div class="text-sm font-bold text-slate-800">✓ Consulta activa</div>
                                <div class="text-[11px] text-slate-500">Si está inactiva no se puede ejecutar.</div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="px-6 py-3 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
                    <button wire:click="$set('modalAbierto', false)" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                        Cancelar
                    </button>
                    <button wire:click="guardar" class="rounded-xl bg-purple-600 hover:bg-purple-700 px-5 py-2 text-sm font-bold text-white shadow">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL PROBAR --}}
    @if ($consultaProbando)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="cerrarProbar">
            <div class="w-full max-w-3xl rounded-3xl bg-white shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-800">
                            <i class="fa-solid fa-play text-blue-600 mr-1"></i> Probar: {{ $consultaProbando->nombre_publico }}
                        </h3>
                        <p class="text-[11px] text-slate-500 font-mono">{{ $consultaProbando->nombreTool() }}</p>
                    </div>
                    <button wire:click="cerrarProbar" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                <div class="overflow-y-auto p-6 space-y-4">
                    @if (count($consultaProbando->parametros ?? []) > 0)
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                            <p class="text-xs font-bold text-slate-700 mb-2">Parámetros de prueba:</p>
                            <div class="space-y-2">
                                @foreach ($consultaProbando->parametros as $p)
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-0.5">
                                            {{ $p['nombre'] }} <span class="text-slate-400">({{ $p['tipo'] ?? 'string' }})</span>
                                        </label>
                                        <input type="text" wire:model="paramsPrueba.{{ $p['nombre'] }}"
                                               placeholder="{{ $p['descripcion'] ?? '' }}"
                                               class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs font-mono">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-slate-500">Esta consulta no tiene parámetros — se ejecuta directo.</p>
                    @endif

                    <button wire:click="ejecutarPrueba"
                            class="rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2 text-sm font-bold text-white shadow">
                        <i class="fa-solid fa-play mr-1"></i> Ejecutar
                    </button>

                    @if ($resultadoPrueba !== null)
                        @if ($resultadoPrueba['ok'])
                            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3">
                                <p class="text-sm font-bold text-emerald-800">
                                    ✓ {{ $resultadoPrueba['total'] }} filas
                                </p>
                            </div>
                            @if (!empty($resultadoPrueba['filas']))
                                <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-80 overflow-y-auto">
                                    <table class="w-full text-[11px]">
                                        <thead class="bg-slate-100 sticky top-0">
                                            <tr>
                                                @foreach ($resultadoPrueba['columnas'] ?? [] as $col)
                                                    <th class="px-2 py-1 text-left font-bold">{{ $col }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($resultadoPrueba['filas'] as $row)
                                                <tr>
                                                    @foreach ($row as $val)
                                                        <td class="px-2 py-1 font-mono">{{ \Illuminate\Support\Str::limit((string) $val, 60) }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <div class="rounded-xl bg-rose-50 border border-rose-200 p-3">
                                <p class="text-sm font-bold text-rose-700"><i class="fa-solid fa-circle-xmark mr-1"></i> Error</p>
                                <p class="text-xs text-rose-600 mt-1 break-all">{{ $resultadoPrueba['error'] ?? 'Error desconocido' }}</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
