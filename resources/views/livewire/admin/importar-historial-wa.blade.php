<div class="max-w-5xl mx-auto p-6 space-y-6">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-lg">
        <div class="flex items-start gap-4">
            <div class="bg-white/20 rounded-xl p-3 backdrop-blur">
                <i class="fa-brands fa-whatsapp text-3xl"></i>
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-bold">Importar historial de WhatsApp</h1>
                <p class="text-emerald-50 text-sm mt-1">
                    Sube los archivos <code class="bg-white/20 px-1.5 py-0.5 rounded">.txt</code>
                    exportados desde la app WhatsApp Business del celular y los pegamos como
                    conversaciones en Kivox, conservando fechas, autores y multimedia (placeholder).
                </p>
            </div>
        </div>
    </div>

    {{-- Guía rápida --}}
    <details class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <summary class="cursor-pointer font-semibold text-amber-900 text-sm">
            <i class="fa-solid fa-circle-info mr-1"></i>
            ¿Cómo exporto un chat desde el celular?
        </summary>
        <div class="mt-3 space-y-2 text-sm text-amber-900">
            <p><strong>Android:</strong> Abre el chat → ⋮ (3 puntos) → <strong>Más</strong> → <strong>Exportar chat</strong> → elige <strong>Sin medios</strong> (más rápido).</p>
            <p><strong>iPhone:</strong> Abre el chat → toca el nombre del contacto arriba → <strong>Exportar chat</strong> → <strong>Sin multimedia</strong>.</p>
            <p>Te llega un <code>.txt</code> por email/Drive/WhatsApp. Lo descargas y lo subes aquí. <strong>Un archivo = un cliente.</strong></p>
        </div>
    </details>

    {{-- Tenant selector (super-admin) --}}
    @if(auth()->user()->can('tenants.gestionar') && $tenants->count() > 1)
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Tenant destino</label>
            <select wire:model.live="tenantSlug"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                @foreach($tenants as $t)
                    <option value="{{ $t->slug }}">{{ $t->nombre }} ({{ $t->slug }})</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Paso 1 — Subir / pegar --}}
    <div class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4 shadow-sm"
         x-data="{
             leerArchivo(e) {
                 const f = e.target.files[0];
                 if (!f) return;
                 const r = new FileReader();
                 r.onload = (ev) => {
                     @this.set('textoExport', ev.target.result);
                     @this.set('nombreArchivo', f.name);
                     // Auto-rellenar nombre cliente del nombre del archivo:
                     //   'WhatsApp Chat con Juan Pérez.txt' → 'Juan Pérez'
                     const m = f.name.match(/(?:Chat con|Chat with|Chat de)\s+(.+?)\.txt$/i);
                     if (m) @this.set('nombreCliente', m[1].trim());
                 };
                 r.readAsText(f, 'UTF-8');
             }
         }">
        <div class="flex items-center gap-2">
            <span class="flex items-center justify-center h-7 w-7 rounded-full bg-emerald-500 text-white text-sm font-bold">1</span>
            <h2 class="font-bold text-slate-800">Sube el archivo o pega el texto</h2>
        </div>

        <label class="flex flex-col items-center justify-center border-2 border-dashed border-emerald-300 hover:border-emerald-500 hover:bg-emerald-50/50 rounded-xl p-8 cursor-pointer transition">
            <i class="fa-solid fa-cloud-arrow-up text-4xl text-emerald-500 mb-2"></i>
            <span class="text-sm font-semibold text-slate-700">Arrastra un .txt o haz click para elegir</span>
            <span class="text-xs text-slate-500 mt-1">Solo se procesa en tu navegador antes de enviarse</span>
            <input type="file" accept=".txt,text/plain" class="hidden" @change="leerArchivo($event)">
        </label>

        @if($nombreArchivo)
            <div class="flex items-center gap-2 text-xs text-slate-600 bg-slate-50 rounded-lg px-3 py-2">
                <i class="fa-solid fa-file-lines text-emerald-600"></i>
                <span class="font-mono">{{ $nombreArchivo }}</span>
                <span class="text-slate-400">·</span>
                <span>{{ number_format(mb_strlen($textoExport)) }} caracteres</span>
            </div>
        @endif

        <details class="text-xs text-slate-500">
            <summary class="cursor-pointer">O pega el contenido directamente</summary>
            <textarea wire:model="textoExport" rows="6"
                      placeholder="12/03/24, 10:45 - Juan Pérez: Hola, quiero pedir...&#10;12/03/24, 10:46 - Tú: Claro, ¿qué te gustaría?"
                      class="w-full mt-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono"></textarea>
        </details>

        <button type="button" wire:click="analizar" wire:loading.attr="disabled"
                @disabled(trim($textoExport) === '')
                class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
            <span wire:loading.remove wire:target="analizar"><i class="fa-solid fa-magnifying-glass-chart"></i> Analizar contenido</span>
            <span wire:loading wire:target="analizar"><i class="fa-solid fa-spinner fa-spin"></i> Analizando...</span>
        </button>
    </div>

    {{-- Error --}}
    @if($error)
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl px-4 py-3 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $error }}
        </div>
    @endif

    {{-- Paso 2 — Análisis + asignación --}}
    @if(!empty($analisis['mensajes']))
        <div class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="flex items-center justify-center h-7 w-7 rounded-full bg-emerald-500 text-white text-sm font-bold">2</span>
                <h2 class="font-bold text-slate-800">Verifica y asigna al cliente</h2>
            </div>

            {{-- KPIs del análisis --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-emerald-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-emerald-700">{{ number_format($analisis['total']) }}</div>
                    <div class="text-[10px] text-emerald-800 uppercase font-bold">Mensajes</div>
                </div>
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-blue-700">{{ count($analisis['participantes']) }}</div>
                    <div class="text-[10px] text-blue-800 uppercase font-bold">Participantes</div>
                </div>
                <div class="bg-violet-50 rounded-xl p-3 text-center">
                    <div class="text-[10px] font-bold text-violet-800 uppercase">Rango</div>
                    <div class="text-[11px] text-violet-700 font-mono leading-tight mt-1">
                        {{ \Carbon\Carbon::parse($analisis['rango']['from'])->format('d/m/y') }}
                        →
                        {{ \Carbon\Carbon::parse($analisis['rango']['to'])->format('d/m/y') }}
                    </div>
                </div>
            </div>

            {{-- Quién soy yo --}}
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-2">
                    ¿Cuál de estos eres tú (el negocio)?
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($analisis['participantes'] as $p)
                        <label class="flex items-center gap-2 rounded-xl border-2 p-3 cursor-pointer transition {{ $autorYo === $p ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:border-slate-300' }}">
                            <input type="radio" wire:model.live="autorYo" value="{{ $p }}"
                                   class="text-emerald-500">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-slate-800 truncate">{{ $p }}</div>
                                <div class="text-[10px] text-slate-500">
                                    @php $cuenta = collect($analisis['mensajes'])->where('autor', $p)->count(); @endphp
                                    {{ $cuenta }} mensajes
                                </div>
                            </div>
                            @if($autorYo === $p)
                                <span class="text-[10px] bg-emerald-500 text-white px-2 py-0.5 rounded-full font-bold">YO</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Datos del cliente --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">País</label>
                    <select wire:model="paisCodigo"
                            class="w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                        <option value="+57">🇨🇴 +57</option>
                        <option value="+52">🇲🇽 +52</option>
                        <option value="+1">🇺🇸 +1</option>
                        <option value="+34">🇪🇸 +34</option>
                        <option value="+54">🇦🇷 +54</option>
                        <option value="+593">🇪🇨 +593</option>
                        <option value="+51">🇵🇪 +51</option>
                        <option value="+56">🇨🇱 +56</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Teléfono del cliente (sin código país)</label>
                    <input type="text" wire:model="telefonoCliente"
                           placeholder="3001234567"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nombre del cliente (opcional, lo usa si no existe en BD)</label>
                    <input type="text" wire:model="nombreCliente"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                </div>
            </div>

            {{-- Preview primeros mensajes --}}
            <details class="bg-slate-50 rounded-xl p-3">
                <summary class="cursor-pointer text-xs font-bold text-slate-700">👁️ Vista previa (primeros 8 mensajes)</summary>
                <div class="mt-3 space-y-1.5 text-xs">
                    @foreach(array_slice($analisis['mensajes'], 0, 8) as $m)
                        <div class="flex gap-2">
                            <span class="text-slate-400 font-mono shrink-0">{{ \Carbon\Carbon::parse($m['fecha'])->format('d/m H:i') }}</span>
                            <span class="font-semibold {{ $autorYo === $m['autor'] ? 'text-emerald-700' : 'text-slate-700' }} shrink-0">{{ $m['autor'] }}:</span>
                            <span class="text-slate-600 truncate">
                                @if($m['tipo'] !== 'text')
                                    <span class="text-[10px] uppercase bg-slate-200 px-1.5 rounded">{{ $m['tipo'] }}</span>
                                @endif
                                {{ mb_substr($m['contenido'], 0, 140) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </details>

            <button type="button" wire:click="importar" wire:loading.attr="disabled"
                    @disabled(empty($autorYo) || empty($telefonoCliente))
                    class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="importar"><i class="fa-solid fa-database"></i> Importar a Kivox</span>
                <span wire:loading wire:target="importar"><i class="fa-solid fa-spinner fa-spin"></i> Importando...</span>
            </button>
        </div>
    @endif

    {{-- Paso 3 — Resultado --}}
    @if(!empty($resultado))
        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-300 rounded-2xl p-6 space-y-3">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-2xl"></i>
                <h2 class="font-bold text-slate-800 text-lg">¡Importación completa!</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl p-3 text-center">
                    <div class="text-xl font-bold text-emerald-700">{{ $resultado['insertados'] }}</div>
                    <div class="text-[10px] uppercase font-bold text-slate-600">Insertados</div>
                </div>
                <div class="bg-white rounded-xl p-3 text-center">
                    <div class="text-xl font-bold text-amber-600">{{ $resultado['omitidos'] }}</div>
                    <div class="text-[10px] uppercase font-bold text-slate-600">Duplicados</div>
                </div>
                <div class="bg-white rounded-xl p-3 text-center">
                    <div class="text-xl font-bold text-blue-700">{{ $resultado['cliente_creado'] ? 'NUEVO' : 'EXISTÍA' }}</div>
                    <div class="text-[10px] uppercase font-bold text-slate-600">Cliente</div>
                </div>
                <div class="bg-white rounded-xl p-3 text-center">
                    <div class="text-xl font-bold text-violet-700">{{ $resultado['conv_creada'] ? 'NUEVA' : 'EXISTÍA' }}</div>
                    <div class="text-[10px] uppercase font-bold text-slate-600">Conversación</div>
                </div>
            </div>
            <div class="flex gap-2 pt-2">
                <a href="{{ route('chat.index') }}?conv={{ $resultado['conversacion_id'] }}"
                   class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold py-2.5 rounded-xl text-center transition">
                    <i class="fa-solid fa-comments mr-1"></i> Ver conversación
                </a>
                <button type="button" wire:click="limpiar"
                        class="bg-slate-200 hover:bg-slate-300 text-slate-800 text-sm font-bold py-2.5 px-4 rounded-xl transition">
                    <i class="fa-solid fa-plus"></i> Importar otro
                </button>
            </div>
        </div>
    @endif
</div>
