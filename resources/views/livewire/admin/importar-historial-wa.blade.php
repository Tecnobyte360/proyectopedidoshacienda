<div class="max-w-7xl mx-auto p-6 space-y-6">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex items-start gap-4">
            <div class="bg-white/20 rounded-xl p-3 backdrop-blur">
                <i class="fa-brands fa-whatsapp text-3xl"></i>
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-bold">Importar histórico de WhatsApp · masivo</h1>
                <p class="text-emerald-50 text-sm mt-1">
                    Arrastra TODOS los <code class="bg-white/20 px-1.5 py-0.5 rounded">.txt</code>
                    que exportaste desde la app WA Business del celular. Cada archivo = un cliente.
                    Tras analizarlos, ajustas teléfono y le das al botón final para importar todos.
                </p>
            </div>
        </div>
    </div>

    {{-- Guía rápida --}}
    <details class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <summary class="cursor-pointer font-semibold text-amber-900 text-sm">
            <i class="fa-solid fa-circle-info mr-1"></i>
            ¿Cómo exporto los chats desde el cel?
        </summary>
        <div class="mt-3 space-y-2 text-sm text-amber-900">
            <p><strong>Android:</strong> Chat → ⋮ → Más → <strong>Exportar chat</strong> → Sin medios.</p>
            <p><strong>iPhone:</strong> Toca el nombre del contacto arriba → <strong>Exportar chat</strong> → Sin multimedia.</p>
            <p>Envíate los .txt por correo o Google Drive, descárgalos y arrastra todos aquí.
               <strong>Tip:</strong> guarda los contactos en el cel ANTES de exportar para que vengan con nombre.</p>
            <p class="text-xs italic">Si tienes muchos chats (cientos), considera Backuptrans
                (~30 USD) para exportar todo en lote desde el cel: <a href="https://www.backuptrans.com/whatsapp-transfer.html" target="_blank" class="underline">link</a>.</p>
        </div>
    </details>

    {{-- Tenant + país --}}
    <div class="bg-white border border-slate-200 rounded-xl p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        @if(auth()->user()->can('tenants.gestionar') && $tenants->count() > 1)
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Tenant destino</label>
                <select wire:model.live="tenantSlug"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    @foreach($tenants as $t)
                        <option value="{{ $t->slug }}">{{ $t->nombre }} ({{ $t->slug }})</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">País (por defecto)</label>
            <select wire:model="paisCodigo"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="+57">🇨🇴 +57</option>
                <option value="+52">🇲🇽 +52</option>
                <option value="+1">🇺🇸 +1</option>
                <option value="+34">🇪🇸 +34</option>
                <option value="+54">🇦🇷 +54</option>
                <option value="+51">🇵🇪 +51</option>
                <option value="+593">🇪🇨 +593</option>
                <option value="+56">🇨🇱 +56</option>
            </select>
        </div>
    </div>

    {{-- Drag&drop multi-archivo --}}
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm"
         x-data="{
             leyendo: 0,
             leerArchivos(filesList) {
                 if (!filesList || !filesList.length) return;
                 this.leyendo = filesList.length;
                 [...filesList].forEach(f => {
                     if (!f.name.toLowerCase().endsWith('.txt')) {
                         this.leyendo--;
                         return;
                     }
                     const r = new FileReader();
                     r.onload = (ev) => {
                         @this.call('procesarArchivo', f.name, ev.target.result)
                             .finally(() => this.leyendo--);
                     };
                     r.onerror = () => { this.leyendo--; };
                     r.readAsText(f, 'UTF-8');
                 });
             }
         }"
         @dragover.prevent
         @drop.prevent="leerArchivos($event.dataTransfer.files)">

        <label class="flex flex-col items-center justify-center border-2 border-dashed border-emerald-300 hover:border-emerald-500 hover:bg-emerald-50/50 rounded-xl p-10 cursor-pointer transition">
            <i class="fa-solid fa-cloud-arrow-up text-5xl text-emerald-500 mb-3"></i>
            <span class="text-base font-semibold text-slate-700">Arrastra los .txt aquí o haz click para elegir varios</span>
            <span class="text-xs text-slate-500 mt-1">Acepta selección múltiple — todos a la vez</span>
            <input type="file" accept=".txt,text/plain" multiple class="hidden"
                   @change="leerArchivos($event.target.files); $event.target.value = ''">
        </label>

        <div x-show="leyendo > 0" x-cloak class="mt-3 text-center text-sm text-emerald-700">
            <i class="fa-solid fa-spinner fa-spin"></i>
            Procesando <span x-text="leyendo"></span> archivo(s)...
        </div>
    </div>

    @if($error)
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $error }}
        </div>
    @endif

    @if($aviso)
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ $aviso }}
        </div>
    @endif

    {{-- KPIs + acciones --}}
    @if(count($archivos) > 0)
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="bg-slate-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-slate-800">{{ $kpis['archivos'] }}</div>
                    <div class="text-[10px] uppercase font-bold text-slate-600">Archivos</div>
                </div>
                <div class="bg-emerald-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-emerald-700">{{ number_format($kpis['mensajes_total']) }}</div>
                    <div class="text-[10px] uppercase font-bold text-emerald-800">Mensajes total</div>
                </div>
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-blue-700">{{ $kpis['listos'] }}</div>
                    <div class="text-[10px] uppercase font-bold text-blue-800">Listos p/ importar</div>
                </div>
                <div class="bg-violet-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-violet-700">{{ $kpis['importados'] }}</div>
                    <div class="text-[10px] uppercase font-bold text-violet-800">Ya importados</div>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="button" wire:click="importarTodos" wire:loading.attr="disabled"
                        @disabled($kpis['listos'] === 0)
                        class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="importarTodos">
                        <i class="fa-solid fa-database"></i> Importar todos ({{ $kpis['listos'] }} pendientes)
                    </span>
                    <span wire:loading wire:target="importarTodos">
                        <i class="fa-solid fa-spinner fa-spin"></i> Importando... no cierres la ventana
                    </span>
                </button>
                <button type="button" wire:click="limpiarTodo"
                        wire:confirm="¿Limpiar toda la lista? (no borra lo ya importado de la BD)"
                        class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 rounded-xl transition">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>

        {{-- Tabla de archivos --}}
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[10px] uppercase font-bold text-slate-600">
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Archivo</th>
                            <th class="px-3 py-2 text-right">Msgs</th>
                            <th class="px-3 py-2">Rango</th>
                            <th class="px-3 py-2 min-w-[180px]">Tú eres</th>
                            <th class="px-3 py-2 min-w-[160px]">Teléfono cliente</th>
                            <th class="px-3 py-2 min-w-[160px]">Nombre cliente</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($archivos as $idx => $a)
                            <tr class="{{ $a['estado'] === 'importado' ? 'bg-emerald-50/50' : ($a['estado'] === 'error' ? 'bg-rose-50/50' : '') }}">
                                {{-- Estado --}}
                                <td class="px-3 py-2 align-top">
                                    @if($a['estado'] === 'importado')
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                            <i class="fa-solid fa-check"></i> OK ({{ $a['resultado']['insertados'] ?? 0 }})
                                        </span>
                                    @elseif($a['estado'] === 'error')
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full"
                                              title="{{ $a['error'] }}">
                                            <i class="fa-solid fa-xmark"></i> Error
                                        </span>
                                    @elseif($a['estado'] === 'omitido')
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full"
                                              title="{{ $a['error'] }}">
                                            <i class="fa-solid fa-forward"></i> Omitido
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                                            <i class="fa-solid fa-clock"></i> Pendiente
                                        </span>
                                    @endif
                                </td>

                                {{-- Archivo --}}
                                <td class="px-3 py-2 align-top">
                                    <div class="font-mono text-xs text-slate-700 truncate max-w-[220px]" title="{{ $a['nombre'] }}">{{ $a['nombre'] }}</div>
                                    <div class="text-[10px] text-slate-400">{{ number_format($a['tamano']/1024, 1) }} KB</div>
                                </td>

                                {{-- Total mensajes --}}
                                <td class="px-3 py-2 align-top text-right font-mono text-xs">
                                    {{ number_format($a['total']) }}
                                </td>

                                {{-- Rango --}}
                                <td class="px-3 py-2 align-top text-[10px] text-slate-500 font-mono">
                                    {{ $a['rango_from'] ? \Carbon\Carbon::parse($a['rango_from'])->format('d/m/y') : '—' }}
                                    <br>→ {{ $a['rango_to'] ? \Carbon\Carbon::parse($a['rango_to'])->format('d/m/y') : '—' }}
                                </td>

                                {{-- Tú eres --}}
                                <td class="px-3 py-2 align-top">
                                    @if(count($a['participantes']) <= 1)
                                        <span class="text-[11px] text-slate-400 italic">Solo 1 participante</span>
                                    @else
                                        <select wire:model="archivos.{{ $idx }}.autorYo"
                                                @disabled($a['estado'] === 'importado')
                                                class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs">
                                            <option value="">— Elige —</option>
                                            @foreach($a['participantes'] as $p)
                                                <option value="{{ $p }}">{{ Str::limit($p, 30) }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>

                                {{-- Teléfono --}}
                                <td class="px-3 py-2 align-top">
                                    <input type="text" wire:model="archivos.{{ $idx }}.telefono"
                                           @disabled($a['estado'] === 'importado')
                                           placeholder="573001234567"
                                           class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs font-mono">
                                </td>

                                {{-- Nombre --}}
                                <td class="px-3 py-2 align-top">
                                    <input type="text" wire:model="archivos.{{ $idx }}.nombreCliente"
                                           @disabled($a['estado'] === 'importado')
                                           class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs">
                                </td>

                                {{-- Quitar --}}
                                <td class="px-3 py-2 align-top">
                                    <button type="button" wire:click="removerArchivo({{ $idx }})"
                                            class="text-slate-400 hover:text-rose-600 transition"
                                            title="Quitar de la lista">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </td>
                            </tr>

                            {{-- Error o resultado debajo --}}
                            @if($a['estado'] === 'error' && $a['error'])
                                <tr class="bg-rose-50/30">
                                    <td colspan="8" class="px-3 py-1 text-[11px] text-rose-700">
                                        <i class="fa-solid fa-triangle-exclamation"></i> {{ $a['error'] }}
                                    </td>
                                </tr>
                            @endif

                            @if($a['estado'] === 'importado' && !empty($a['resultado']))
                                <tr class="bg-emerald-50/30">
                                    <td colspan="8" class="px-3 py-1 text-[11px] text-emerald-700">
                                        <i class="fa-solid fa-check"></i>
                                        Cliente {{ $a['resultado']['cliente_creado'] ? 'creado' : 'existía' }},
                                        conversación {{ $a['resultado']['conv_creada'] ? 'creada' : 'existía' }},
                                        {{ $a['resultado']['insertados'] }} mensajes insertados,
                                        {{ $a['resultado']['omitidos'] }} duplicados.
                                        <a href="{{ route('chat.index') }}?conv={{ $a['resultado']['conversacion_id'] }}"
                                           class="underline font-bold ml-2">Ver chat →</a>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
