<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Clientes</h2>
            <p class="text-sm text-slate-500">Conoce a quién atiende tu bot. Cada cliente y su historial.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="abrirModalImportarWa"
                    class="rounded-2xl bg-emerald-600 px-5 py-3 text-white font-semibold shadow hover:bg-emerald-700 transition">
                <i class="fa-brands fa-whatsapp mr-2"></i> Importar de WhatsApp
            </button>

            <button wire:click="abrirModalCrear"
                    class="rounded-2xl bg-brand px-5 py-3 text-white font-semibold shadow hover:bg-brand-dark transition">
                <i class="fa-solid fa-user-plus mr-2"></i> Nuevo cliente
            </button>
        </div>
    </div>

    {{-- MODAL IMPORTAR WHATSAPP --}}
    @if ($modalImportarWa)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
             wire:key="modal-import-wa">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-800">
                            <i class="fa-brands fa-whatsapp text-emerald-600 mr-1"></i>
                            Importar contactos de WhatsApp
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">
                            Tres formas de traer tus contactos al tenant.
                        </p>
                    </div>
                    <button wire:click="cerrarModalImportarWa" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                @if (!$resultadoImportWa && !$diagnosticoApi && !$resultadoSyncFull)
                    {{-- TABS --}}
                    <div class="flex gap-1 mb-5 border-b border-slate-200">
                        <button wire:click="$set('tabImport', 'api')"
                                class="px-4 py-2 text-sm font-semibold border-b-2 transition {{ $tabImport === 'api' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                            <i class="fa-solid fa-cloud-arrow-down mr-1"></i> Desde la API
                        </button>
                        <button wire:click="$set('tabImport', 'archivo')"
                                class="px-4 py-2 text-sm font-semibold border-b-2 transition {{ $tabImport === 'archivo' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                            <i class="fa-solid fa-file-arrow-up mr-1"></i> Subir archivo
                        </button>
                        <button wire:click="$set('tabImport', 'diagnostico')"
                                class="px-4 py-2 text-sm font-semibold border-b-2 transition {{ $tabImport === 'diagnostico' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                            <i class="fa-solid fa-stethoscope mr-1"></i> Diagnóstico API
                        </button>
                    </div>

                    @if ($tabImport === 'api')
                        {{-- OPCION FUERTE: Sincronizar historial completo --}}
                        <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 p-5 text-white mb-4">
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-rotate text-2xl mt-1"></i>
                                <div class="flex-1">
                                    <h4 class="font-extrabold text-base">Sincronizar TODO el historial</h4>
                                    <p class="text-xs opacity-90 mt-1 mb-3">
                                        Trae <strong>todos los chats, contactos con foto, y mensajes</strong> del WhatsApp del tenant directo a la plataforma. Puede tardar minutos si hay muchos chats.
                                    </p>
                                    <button wire:click="sincronizarHistorial"
                                            wire:loading.attr="disabled" wire:target="sincronizarHistorial"
                                            class="rounded-xl bg-white text-emerald-700 px-4 py-2 text-sm font-bold shadow hover:bg-emerald-50 disabled:opacity-50">
                                        <span wire:loading.remove wire:target="sincronizarHistorial">
                                            <i class="fa-solid fa-bolt mr-1"></i> Sincronizar ahora
                                        </span>
                                        <span wire:loading wire:target="sincronizarHistorial">
                                            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Sincronizando...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="text-center text-xs text-slate-400 my-3">— o solo contactos —</div>

                        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800 mb-3">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            Importa solo la lista de contactos (sin chats ni mensajes).
                        </div>

                        <label class="flex items-start gap-3 mb-4 cursor-pointer">
                            <input type="checkbox" wire:model="actualizarExistentes"
                                   class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm text-slate-700">
                                <strong>Actualizar existentes</strong>
                                <span class="block text-xs text-slate-500">Completa nombre/foto si están vacíos.</span>
                            </span>
                        </label>

                        <div class="flex justify-end gap-2">
                            <button wire:click="cerrarModalImportarWa"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                                Cancelar
                            </button>
                            <button wire:click="importarContactosWhatsapp"
                                    wire:loading.attr="disabled"
                                    wire:target="importarContactosWhatsapp"
                                    class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-slate-800 disabled:opacity-50">
                                <span wire:loading.remove wire:target="importarContactosWhatsapp">
                                    <i class="fa-solid fa-cloud-arrow-down mr-1"></i> Solo contactos
                                </span>
                                <span wire:loading wire:target="importarContactosWhatsapp">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Importando...
                                </span>
                            </button>
                        </div>
                    @elseif ($tabImport === 'archivo')
                        <div class="rounded-2xl bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800 mb-4">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            Sube un <strong>.vcf</strong> (export desde el celular: Contactos → Exportar) o un <strong>.csv</strong> de Google Contacts.
                        </div>

                        <label class="block mb-4">
                            <span class="block text-xs font-semibold text-slate-600 mb-1">Archivo (.vcf o .csv, máx 10 MB)</span>
                            <input type="file" wire:model="archivoContactos"
                                   accept=".vcf,.csv,.txt"
                                   class="block w-full text-sm border border-slate-300 rounded-xl p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:text-white file:px-3 file:py-1 file:text-sm file:font-semibold">
                            @error('archivoContactos') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </label>

                        <label class="flex items-start gap-3 mb-5 cursor-pointer">
                            <input type="checkbox" wire:model="actualizarExistentes"
                                   class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm text-slate-700"><strong>Actualizar existentes</strong></span>
                        </label>

                        <div class="flex justify-end gap-2">
                            <button wire:click="cerrarModalImportarWa"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                                Cancelar
                            </button>
                            <button wire:click="importarDesdeArchivo"
                                    wire:loading.attr="disabled" wire:target="importarDesdeArchivo,archivoContactos"
                                    class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="importarDesdeArchivo,archivoContactos">
                                    <i class="fa-solid fa-file-arrow-up mr-1"></i> Subir e importar
                                </span>
                                <span wire:loading wire:target="importarDesdeArchivo,archivoContactos">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Procesando...
                                </span>
                            </button>
                        </div>
                    @else
                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-700 mb-4">
                            <i class="fa-solid fa-stethoscope mr-1"></i>
                            Prueba <strong>todos los endpoints conocidos</strong> de la API de WhatsApp y reporta qué responde cada uno. Útil para descubrir el path correcto de tu wrapper.
                        </div>
                        <div class="flex justify-end gap-2">
                            <button wire:click="cerrarModalImportarWa"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                                Cancelar
                            </button>
                            <button wire:click="ejecutarDiagnosticoApi"
                                    wire:loading.attr="disabled" wire:target="ejecutarDiagnosticoApi"
                                    class="rounded-xl bg-slate-700 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-slate-800 disabled:opacity-50">
                                <span wire:loading.remove wire:target="ejecutarDiagnosticoApi">
                                    <i class="fa-solid fa-magnifying-glass mr-1"></i> Ejecutar diagnóstico
                                </span>
                                <span wire:loading wire:target="ejecutarDiagnosticoApi">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Probando...
                                </span>
                            </button>
                        </div>
                    @endif
                @elseif ($resultadoSyncFull)
                    @if (isset($resultadoSyncFull['error']))
                        <div class="rounded-2xl bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                            <p class="font-semibold mb-1"><i class="fa-solid fa-circle-xmark mr-1"></i> Error</p>
                            <p>{{ $resultadoSyncFull['error'] }}</p>
                        </div>
                    @else
                        <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white p-4 mb-4">
                            <div class="text-xs opacity-90 mb-1">✅ Sincronización completada</div>
                            <div class="text-3xl font-extrabold">{{ $resultadoSyncFull['tickets_procesados'] ?? 0 }} chats</div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mb-3">
                            <div class="rounded-xl bg-emerald-50 p-3 text-center">
                                <div class="text-xl font-extrabold text-emerald-700">{{ $resultadoSyncFull['clientes_creados'] ?? 0 }}</div>
                                <div class="text-[10px] text-emerald-600">Clientes nuevos</div>
                            </div>
                            <div class="rounded-xl bg-blue-50 p-3 text-center">
                                <div class="text-xl font-extrabold text-blue-700">{{ $resultadoSyncFull['clientes_actualizados'] ?? 0 }}</div>
                                <div class="text-[10px] text-blue-600">Clientes actualizados</div>
                            </div>
                            <div class="rounded-xl bg-purple-50 p-3 text-center">
                                <div class="text-xl font-extrabold text-purple-700">{{ $resultadoSyncFull['mensajes_imp'] ?? 0 }}</div>
                                <div class="text-[10px] text-purple-600">Mensajes importados</div>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-3 text-center">
                                <div class="text-xl font-extrabold text-emerald-700">{{ $resultadoSyncFull['conv_creadas'] ?? 0 }}</div>
                                <div class="text-[10px] text-emerald-600">Conversaciones nuevas</div>
                            </div>
                            <div class="rounded-xl bg-blue-50 p-3 text-center">
                                <div class="text-xl font-extrabold text-blue-700">{{ $resultadoSyncFull['conv_actualizadas'] ?? 0 }}</div>
                                <div class="text-[10px] text-blue-600">Conversaciones actualizadas</div>
                            </div>
                            @if (($resultadoSyncFull['errores'] ?? 0) > 0)
                                <div class="rounded-xl bg-red-50 p-3 text-center">
                                    <div class="text-xl font-extrabold text-red-700">{{ $resultadoSyncFull['errores'] }}</div>
                                    <div class="text-[10px] text-red-600">Con error</div>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div class="flex justify-end">
                        <button wire:click="cerrarModalImportarWa"
                                class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                            <i class="fa-solid fa-check mr-1"></i> Listo
                        </button>
                    </div>
                @elseif ($diagnosticoApi)
                    @if (isset($diagnosticoApi['error']))
                        <div class="rounded-2xl bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                            <p class="font-semibold mb-1"><i class="fa-solid fa-circle-xmark mr-1"></i> Error</p>
                            <p>{{ $diagnosticoApi['error'] }}</p>
                        </div>
                    @else
                        <div class="text-xs text-slate-500 mb-2">
                            <strong>Base:</strong> {{ $diagnosticoApi['base'] }} •
                            <strong>connection_id:</strong> {{ $diagnosticoApi['connection_id'] }} •
                            <strong>{{ $diagnosticoApi['exitosos'] }}</strong>/{{ $diagnosticoApi['total_probados'] }} OK
                        </div>
                        <div class="max-h-96 overflow-y-auto rounded-xl border border-slate-200">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr class="text-left text-slate-600">
                                        <th class="p-2">Status</th>
                                        <th class="p-2">Path</th>
                                        <th class="p-2">Preview</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($diagnosticoApi['resultados'] as $r)
                                        @php
                                            $st = $r['status'];
                                            $cls = is_int($st) && $st >= 200 && $st < 300 ? 'bg-emerald-100 text-emerald-800'
                                                : (is_int($st) && $st >= 400 && $st < 500 ? 'bg-amber-50 text-amber-700'
                                                : 'bg-red-50 text-red-700');
                                        @endphp
                                        <tr class="border-t border-slate-100">
                                            <td class="p-2"><span class="inline-block rounded px-2 py-0.5 font-mono {{ $cls }}">{{ $st }}</span></td>
                                            <td class="p-2 font-mono break-all">{{ $r['path'] }}</td>
                                            <td class="p-2 text-slate-500 break-all">{{ \Illuminate\Support\Str::limit($r['preview'], 80) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-xs text-slate-500 mt-3">
                            👉 Pásame los paths que respondieron <strong>200</strong> con JSON y construyo el sync de historial completo.
                        </div>
                    @endif
                    <div class="mt-4 flex justify-end gap-2">
                        <button wire:click="$set('diagnosticoApi', null)"
                                class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                            Volver
                        </button>
                        <button wire:click="cerrarModalImportarWa"
                                class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                            Cerrar
                        </button>
                    </div>
                @elseif (isset($resultadoImportWa['error']))
                    <div class="rounded-2xl bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                        <p class="font-semibold mb-1"><i class="fa-solid fa-circle-xmark mr-1"></i> Error</p>
                        <p class="break-all">{{ $resultadoImportWa['error'] }}</p>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button wire:click="cerrarModalImportarWa"
                                class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">
                            Cerrar
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="rounded-2xl bg-slate-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-slate-800">{{ $resultadoImportWa['total'] ?? 0 }}</div>
                            <div class="text-xs text-slate-500">Total leídos</div>
                        </div>
                        <div class="rounded-2xl bg-emerald-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-emerald-700">{{ $resultadoImportWa['creados'] ?? 0 }}</div>
                            <div class="text-xs text-emerald-600">Creados</div>
                        </div>
                        <div class="rounded-2xl bg-blue-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-blue-700">{{ $resultadoImportWa['actualizados'] ?? 0 }}</div>
                            <div class="text-xs text-blue-600">Actualizados</div>
                        </div>
                        <div class="rounded-2xl bg-amber-50 p-3 text-center">
                            <div class="text-2xl font-extrabold text-amber-700">{{ $resultadoImportWa['omitidos'] ?? 0 }}</div>
                            <div class="text-xs text-amber-600">Omitidos</div>
                        </div>
                    </div>

                    @if (($resultadoImportWa['conv_vinculadas'] ?? 0) + ($resultadoImportWa['conv_creadas'] ?? 0) + ($resultadoImportWa['mensajes_imp'] ?? 0) > 0)
                        <div class="rounded-2xl bg-purple-50 border border-purple-200 p-3 mb-3">
                            <div class="text-xs font-bold text-purple-700 mb-2">
                                <i class="fa-solid fa-comments mr-1"></i> Conversaciones
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <div class="text-lg font-extrabold text-purple-800">{{ $resultadoImportWa['conv_vinculadas'] ?? 0 }}</div>
                                    <div class="text-[10px] text-purple-600 leading-tight">Vinculadas a cliente</div>
                                </div>
                                <div>
                                    <div class="text-lg font-extrabold text-purple-800">{{ $resultadoImportWa['conv_creadas'] ?? 0 }}</div>
                                    <div class="text-[10px] text-purple-600 leading-tight">Creadas (nuevas)</div>
                                </div>
                                <div>
                                    <div class="text-lg font-extrabold text-purple-800">{{ $resultadoImportWa['mensajes_imp'] ?? 0 }}</div>
                                    <div class="text-[10px] text-purple-600 leading-tight">Mensajes importados</div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if (($resultadoImportWa['fuente'] ?? '') === 'local')
                        <div class="rounded-xl bg-blue-50 border border-blue-200 p-3 text-xs text-blue-700 mb-3">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            La API de TecnoByteApp no expuso endpoint de contactos, así que se importaron las personas que ya tienen <strong>conversación con el bot</strong> de este tenant.
                        </div>
                    @endif
                    @if (($resultadoImportWa['errores'] ?? 0) > 0)
                        <div class="rounded-xl bg-red-50 p-2 text-center text-sm text-red-700 mb-4">
                            {{ $resultadoImportWa['errores'] }} errores (revisa el log)
                        </div>
                    @endif
                    <div class="flex justify-end">
                        <button wire:click="cerrarModalImportarWa"
                                class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                            <i class="fa-solid fa-check mr-1"></i> Listo
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- KPIS --}}
    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totales['total'] }}</div>
                    <div class="text-xs text-slate-500">Total clientes</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totales['activos'] }}</div>
                    <div class="text-xs text-slate-500">Activos</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-medal"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold text-slate-800">{{ $totales['recurrentes'] }}</div>
                    <div class="text-xs text-slate-500">Recurrentes (≥2)</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-brand to-brand-secondary p-4 shadow text-white">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
                <div>
                    <div class="text-2xl font-extrabold">${{ number_format($totales['gastoTotal'], 0, ',', '.') }}</div>
                    <div class="text-xs opacity-80">Gasto histórico</div>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Buscar por nombre, teléfono, email, barrio..."
               class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">

        <select wire:model.live="filtroEstado"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">
            <option value="todos">Todos los estados</option>
            <option value="activos">Solo activos</option>
            <option value="inactivos">Solo inactivos</option>
            <option value="recurrentes">Recurrentes (2+ pedidos)</option>
            <option value="nuevos">Nuevos (0-1 pedidos)</option>
        </select>

        <select wire:model.live="orden"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-brand focus:ring-brand">
            <option value="recientes">Más recientes</option>
            <option value="mayor_gasto">Mayor gasto</option>
            <option value="mas_pedidos">Más pedidos</option>
            <option value="nombre">Por nombre A-Z</option>
        </select>
    </div>

    {{-- TABLA --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cliente</th>
                        <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden md:table-cell">Contacto</th>
                        <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden lg:table-cell">Ubicación</th>
                        <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-slate-500">Pedidos</th>
                        <th class="px-3 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden md:table-cell">Total gastado</th>
                        <th class="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500 hidden xl:table-cell">Último</th>
                        <th class="px-3 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($clientes as $cli)
                        @php
                            $iniciales = collect(explode(' ', trim($cli->nombre)))
                                ->filter()->take(2)
                                ->map(fn($p) => mb_substr($p, 0, 1))
                                ->implode('');
                        @endphp

                        <tr class="hover:bg-amber-50/30 transition">
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white font-bold text-sm">
                                        {{ $iniciales ?: 'CL' }}
                                    </div>
                                    <div class="min-w-0 max-w-[180px]">
                                        <div class="font-semibold text-slate-800 truncate">{{ $cli->nombre }}</div>
                                        <div class="text-[10px] text-slate-500 md:hidden truncate">
                                            {{ $cli->pais_codigo }} {{ $cli->telefono }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 py-3 hidden md:table-cell">
                                <div class="text-sm font-mono text-slate-700">{{ $cli->pais_codigo }} {{ $cli->telefono }}</div>
                                @if($cli->email)
                                    <div class="text-[10px] text-slate-500 truncate max-w-[160px]">{{ $cli->email }}</div>
                                @endif
                            </td>

                            <td class="px-3 py-3 hidden lg:table-cell">
                                @if($cli->zonaCobertura)
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs">
                                        <span class="h-2 w-2 rounded-full" style="background-color: {{ $cli->zonaCobertura->color }}"></span>
                                        {{ $cli->zonaCobertura->nombre }}
                                    </span>
                                @endif
                                @if($cli->barrio)
                                    <div class="text-[11px] text-slate-500 truncate max-w-[140px] mt-0.5">{{ $cli->barrio }}</div>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-center">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                    {{ $cli->total_pedidos }}
                                </span>
                            </td>

                            <td class="px-3 py-3 text-right hidden md:table-cell">
                                <div class="font-bold text-slate-800">${{ number_format($cli->total_gastado, 0, ',', '.') }}</div>
                                @if($cli->ticket_promedio > 0)
                                    <div class="text-[10px] text-slate-500">avg ${{ number_format($cli->ticket_promedio, 0, ',', '.') }}</div>
                                @endif
                            </td>

                            <td class="px-3 py-3 hidden xl:table-cell">
                                @if($cli->fecha_ultimo_pedido)
                                    <div class="text-xs text-slate-700">{{ $cli->fecha_ultimo_pedido->diffForHumans() }}</div>
                                @else
                                    <span class="text-xs text-slate-400 italic">Nunca</span>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button wire:click="verCliente({{ $cli->id }})"
                                            class="rounded-lg p-2 text-slate-500 hover:bg-blue-50 hover:text-blue-600 transition" title="Ver perfil">
                                        <i class="fa-solid fa-eye text-sm"></i>
                                    </button>
                                    <button wire:click="abrirModalEditar({{ $cli->id }})"
                                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition" title="Editar">
                                        <i class="fa-solid fa-pen-to-square text-sm"></i>
                                    </button>
                                    @if($cli->whatsappUrl())
                                        <a href="{{ $cli->whatsappUrl() }}" target="_blank"
                                           class="rounded-lg p-2 text-green-500 hover:bg-green-50 transition" title="WhatsApp">
                                            <i class="fa-brands fa-whatsapp text-sm"></i>
                                        </a>
                                    @endif
                                    <button @click.prevent="$dispatch('confirm-show', {
                                                title: 'Eliminar cliente',
                                                message: 'Esta acción no se puede deshacer. ¿Eliminar a {{ addslashes($cli->nombre) }}?',
                                                confirmText: 'Sí, eliminar',
                                                type: 'danger',
                                                onConfirm: () => $wire.eliminar({{ $cli->id }}),
                                            })"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-500 transition" title="Eliminar">
                                        <i class="fa-solid fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-slate-400">
                                    <i class="fa-solid fa-users-slash text-xl"></i>
                                </div>
                                <h3 class="mt-3 text-base font-semibold text-slate-700">Sin clientes</h3>
                                <p class="mt-1 text-sm text-slate-500">Aún no hay clientes registrados. Llegarán solos cuando alguien escriba al bot.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-4 py-3">
            {{ $clientes->links() }}
        </div>
    </div>

    {{-- ╔═══ MODAL DE PERFIL ═══╗ --}}
    @if($clienteVer)
        @php
            $iniVer = collect(explode(' ', trim($clienteVer->nombre)))
                ->filter()->take(2)
                ->map(fn($p) => mb_substr($p, 0, 1))
                ->implode('');
        @endphp
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 overflow-y-auto"
             wire:click.self="cerrarVer"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">

            <div class="w-full sm:max-w-3xl rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl my-0 sm:my-8 max-h-[95vh] flex flex-col">

                {{-- Header del perfil --}}
                <div class="relative bg-gradient-to-br from-brand to-brand-secondary text-white px-6 py-6 sm:rounded-t-2xl">
                    <button wire:click="cerrarVer"
                            class="absolute top-4 right-4 flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 backdrop-blur text-2xl font-bold">
                            {{ $iniVer ?: 'CL' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-2xl font-bold truncate">{{ $clienteVer->nombre }}</h3>
                            <div class="text-xs text-white/80 mt-1">
                                <i class="fa-solid fa-phone mr-1"></i>
                                <span class="font-mono">{{ $clienteVer->pais_codigo }} {{ $clienteVer->telefono }}</span>
                                @if($clienteVer->canal_origen)
                                    <span class="ml-2 rounded-full bg-white/20 px-2 py-0.5 text-[10px] uppercase">{{ $clienteVer->canal_origen }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Stats inline --}}
                    <div class="grid grid-cols-3 gap-3 mt-5">
                        <div class="rounded-xl bg-white/15 backdrop-blur p-3">
                            <div class="text-2xl font-extrabold">{{ $clienteVer->total_pedidos }}</div>
                            <div class="text-[10px] opacity-80 uppercase">Pedidos</div>
                        </div>
                        <div class="rounded-xl bg-white/15 backdrop-blur p-3">
                            <div class="text-2xl font-extrabold">${{ number_format($clienteVer->total_gastado, 0, ',', '.') }}</div>
                            <div class="text-[10px] opacity-80 uppercase">Total gastado</div>
                        </div>
                        <div class="rounded-xl bg-white/15 backdrop-blur p-3">
                            <div class="text-2xl font-extrabold">${{ number_format($clienteVer->ticket_promedio, 0, ',', '.') }}</div>
                            <div class="text-[10px] opacity-80 uppercase">Ticket avg</div>
                        </div>
                    </div>
                </div>

                {{-- Body con scroll --}}
                <div class="px-6 py-5 overflow-y-auto space-y-5">

                    {{-- Datos --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        @if($clienteVer->email)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Email</div>
                                <div class="text-slate-800 truncate">{{ $clienteVer->email }}</div>
                            </div>
                        @endif

                        @if($clienteVer->direccion_principal)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Dirección habitual</div>
                                <div class="text-slate-800">{{ $clienteVer->direccion_principal }}</div>
                            </div>
                        @endif

                        @if($clienteVer->barrio)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Barrio</div>
                                <div class="text-slate-800">{{ $clienteVer->barrio }}</div>
                            </div>
                        @endif

                        @if($clienteVer->zonaCobertura)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Zona</div>
                                <div class="text-slate-800 inline-flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full" style="background-color: {{ $clienteVer->zonaCobertura->color }}"></span>
                                    {{ $clienteVer->zonaCobertura->nombre }}
                                </div>
                            </div>
                        @endif

                        @if($clienteVer->fecha_primer_pedido)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Cliente desde</div>
                                <div class="text-slate-800">{{ $clienteVer->fecha_primer_pedido->format('d/m/Y') }}</div>
                            </div>
                        @endif

                        @if($clienteVer->fecha_ultimo_pedido)
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Último pedido</div>
                                <div class="text-slate-800">{{ $clienteVer->fecha_ultimo_pedido->diffForHumans() }}</div>
                            </div>
                        @endif
                    </div>

                    {{-- Notas internas --}}
                    @if($clienteVer->notas_internas)
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3">
                            <div class="text-[10px] font-bold uppercase text-amber-700 mb-1">
                                <i class="fa-solid fa-note-sticky mr-1"></i> Nota interna
                            </div>
                            <div class="text-sm text-amber-900">{{ $clienteVer->notas_internas }}</div>
                        </div>
                    @endif

                    {{-- Historial de pedidos --}}
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-brand"></i>
                            Historial de pedidos ({{ $clienteVer->pedidos->count() }})
                        </h4>

                        @if($clienteVer->pedidos->isEmpty())
                            <div class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500">
                                <i class="fa-solid fa-inbox text-2xl mb-2 block text-slate-300"></i>
                                Sin pedidos aún.
                            </div>
                        @else
                            <div class="space-y-2 max-h-80 overflow-y-auto">
                                @foreach($clienteVer->pedidos->take(20) as $p)
                                    @php
                                        $estCfg = match($p->estado) {
                                            'nuevo'                => ['bg-blue-100 text-blue-700', 'Nuevo'],
                                            'en_preparacion'       => ['bg-amber-100 text-amber-700', 'En proceso'],
                                            'repartidor_en_camino' => ['bg-violet-100 text-violet-700', 'Despachado'],
                                            'entregado'            => ['bg-emerald-100 text-emerald-700', 'Entregado'],
                                            'cancelado'            => ['bg-rose-100 text-rose-700', 'Cancelado'],
                                            default                => ['bg-slate-100 text-slate-700', $p->estado],
                                        };
                                    @endphp
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] font-mono text-slate-500">#{{ str_pad($p->id, 3, '0', STR_PAD_LEFT) }}</span>
                                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $estCfg[0] }}">{{ $estCfg[1] }}</span>
                                            </div>
                                            <div class="text-xs text-slate-500 mt-0.5">{{ $p->fecha_pedido?->format('d/m/Y h:i a') }}</div>
                                            @if($p->detalles->count())
                                                <div class="text-[11px] text-slate-600 mt-0.5 truncate">
                                                    {{ $p->detalles->take(3)->map(fn($d) => rtrim(rtrim(number_format($d->cantidad, 2, ',', '.'), '0'), ',') . ' ' . $d->unidad . ' ' . $d->producto)->join(', ') }}
                                                    @if($p->detalles->count() > 3) +{{ $p->detalles->count() - 3 }} @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-bold text-slate-800">${{ number_format($p->total, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Footer del perfil --}}
                <div class="border-t border-slate-100 px-6 py-4 flex flex-col-reverse sm:flex-row gap-2 justify-between">
                    <button wire:click="recalcular({{ $clienteVer->id }})"
                            class="text-xs text-slate-600 hover:underline">
                        <i class="fa-solid fa-rotate-right mr-1"></i> Recalcular métricas
                    </button>
                    <div class="flex gap-2">
                        <button wire:click="cerrarVer"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cerrar
                        </button>
                        <button wire:click="abrirModalEditar({{ $clienteVer->id }})"
                                class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-dark">
                            <i class="fa-solid fa-pen-to-square mr-1"></i> Editar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ╔═══ MODAL EDITAR/CREAR ═══╗ --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4 overflow-y-auto"
             wire:click.self="cerrarModal"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">

            <div class="w-full sm:max-w-2xl rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl my-0 sm:my-8 max-h-[95vh] flex flex-col">

                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 sticky top-0 bg-white rounded-t-2xl">
                    <h3 class="text-lg font-bold text-slate-800">
                        {{ $editandoId ? 'Editar cliente' : 'Nuevo cliente' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4 overflow-y-auto">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                        <input type="text" wire:model="nombre"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono *</label>
                        <div class="flex gap-2">
                            <select wire:model="pais_codigo"
                                    class="w-32 rounded-xl border border-slate-200 px-2 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                @foreach($paises as $p)
                                    <option value="{{ $p['codigo'] }}">{{ $p['flag'] }} {{ $p['codigo'] }}</option>
                                @endforeach
                            </select>
                            <input type="tel" wire:model="telefono" inputmode="numeric" placeholder="3001234567"
                                   class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                        @error('telefono') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email (opcional)</label>
                            <input type="email" wire:model="email"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                🎂 Fecha de nacimiento
                                <span class="text-xs text-slate-400 font-normal">(opcional)</span>
                            </label>
                            <input type="date" wire:model="fecha_nacimiento" max="{{ date('Y-m-d') }}"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            @error('fecha_nacimiento') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Dirección habitual</label>
                            <input type="text" wire:model="direccion_principal"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Barrio</label>
                            <input type="text" wire:model="barrio"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Zona de cobertura</label>
                        <select wire:model="zona_cobertura_id"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            <option value="">Sin asignar</option>
                            @foreach($zonas as $z)
                                <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Notas internas
                            <span class="text-xs text-slate-400 font-normal">(no las ve el cliente, las lee la IA)</span>
                        </label>
                        <textarea wire:model="notas_internas" rows="3"
                                  placeholder="Ej: alérgico al maní, paga siempre en efectivo, prefiere la pechuga sin piel..."
                                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand"></textarea>
                    </div>

                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-brand">
                        <span class="text-sm text-slate-700">Cliente activo</span>
                    </label>

                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">
                            Guardar cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
