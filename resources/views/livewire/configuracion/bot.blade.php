<div class="px-4 lg:px-8 py-6"
     x-data="{ tab: window.localStorage.getItem('bot_cfg_tab') || 'general' }"
     x-init="$watch('tab', v => window.localStorage.setItem('bot_cfg_tab', v))">

    <div class="mb-6">
        <h2 class="text-3xl font-extrabold text-slate-800">Configuración del bot</h2>
        <p class="text-sm text-slate-500">Ajusta el comportamiento de la asesora IA.</p>
    </div>

    <form wire:submit.prevent="guardar" class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6 items-start">

        {{-- ╔═══ SIDEBAR DE NAVEGACIÓN ═══╗ --}}
        <aside class="lg:sticky lg:top-24 self-start space-y-1 rounded-2xl bg-white border border-slate-200 p-2 shadow-sm">
            @php
                $tabs = [
                    'general'    => ['Identidad y empresa',    'fa-user-tie',          'text-blue-600 bg-blue-50'],
                    'ia'         => ['Motor de IA',            'fa-brain',             'text-emerald-600 bg-emerald-50'],
                    'mensajes'   => ['Mensajes y media',       'fa-comments',          'text-cyan-600 bg-cyan-50'],
                    'derivacion' => ['Derivación a humanos',   'fa-headset',           'text-violet-600 bg-violet-50'],
                    'zonas'      => ['Zonas de cobertura',     'fa-map-location-dot',  'text-sky-600 bg-sky-50'],
                    'prompt'     => ['Prompt de la IA',        'fa-code',              'text-rose-600 bg-rose-50'],
                    'encuesta'   => ['Encuesta post-entrega',  'fa-star-half-stroke',  'text-amber-600 bg-amber-50'],
                    'notificaciones' => ['Notificaciones cliente', 'fa-bell',           'text-amber-600 bg-amber-50'],
                    'pagos'      => ['Pagos en línea',         'fa-credit-card',       'text-violet-600 bg-violet-50'],
                    'despachos'  => ['Despachos / Domiciliarios','fa-motorcycle',      'text-orange-600 bg-orange-50'],
                    'cumple'     => ['Felicitaciones',         'fa-cake-candles',      'text-pink-600 bg-pink-50'],
                ];
            @endphp

            @foreach($tabs as $key => [$label, $icon, $color])
                <button type="button"
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'"
                        class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition text-left">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $color }} flex-shrink-0">
                        <i class="fa-solid {{ $icon }} text-xs"></i>
                    </span>
                    <span class="flex-1 truncate">{{ $label }}</span>
                </button>
            @endforeach

            <div class="pt-2 mt-2 border-t border-slate-100">
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-dark px-4 py-2.5 text-sm font-bold text-white shadow transition">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar todo
                </button>
            </div>
        </aside>

        {{-- ╔═══ ÁREA DE CONTENIDO ═══╗ --}}
        <div class="space-y-6 min-w-0">

        {{-- IDENTIDAD --}}
        <section x-show="tab === 'general'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-user-tie"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Identidad de la asesora</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la asesora</label>
                    <input type="text" wire:model="nombre_asesora"
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    <p class="text-[11px] text-slate-400 mt-1">El bot se presentará con este nombre.</p>
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                        <div>
                            <div class="text-sm font-medium text-slate-700">Bot activo</div>
                            <div class="text-[11px] text-slate-500">Si lo apagas, no responde a nadie</div>
                        </div>
                        <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-brand h-5 w-5">
                    </label>
                </div>
            </div>
        </section>

        {{-- FUENTE DE PRODUCTOS --}}
        <section x-show="tab === 'mensajes'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-database"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Fuente de productos del bot</h3>
            </div>
            <p class="text-xs text-slate-500 mb-4">
                Decide de dónde el bot lee el catálogo cuando atiende a los clientes.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                <label class="flex items-start gap-3 cursor-pointer rounded-2xl border-2 p-4 transition {{ $fuente_productos === 'tabla' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50' }}">
                    <input type="radio" wire:model.live="fuente_productos" value="tabla" class="mt-1 text-emerald-600">
                    <div class="flex-1">
                        <div class="font-bold text-sm text-slate-800">
                            <i class="fa-solid fa-table-list text-slate-600 mr-1"></i> Tabla local de productos
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            Usa los productos que cargas manualmente en <strong>/productos</strong>. Control total.
                        </div>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer rounded-2xl border-2 p-4 transition {{ $fuente_productos === 'integracion' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50' }}">
                    <input type="radio" wire:model.live="fuente_productos" value="integracion" class="mt-1 text-emerald-600">
                    <div class="flex-1">
                        <div class="font-bold text-sm text-slate-800">
                            <i class="fa-solid fa-bolt text-emerald-600 mr-1"></i> Integración LIVE (híbrido)
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            <strong>Precio/disponibilidad</strong> del ERP en tiempo real, <strong>enriquecido</strong> con cortes, fotos, palabras clave, destacados y sedes de la tabla local (match por código).
                        </div>
                    </div>
                </label>
            </div>

            @if ($fuente_productos === 'integracion')
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Integración a usar</label>
                        <select wire:model="integracion_productos_id"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">— Selecciona —</option>
                            @foreach ($integracionesProductos ?? [] as $i)
                                <option value="{{ $i->id }}">{{ $i->nombre }} ({{ strtoupper($i->tipo) }})</option>
                            @endforeach
                        </select>
                        @if (($integracionesProductos ?? collect())->isEmpty())
                            <p class="text-xs text-amber-600 mt-1">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                No hay integraciones de productos activas. Crea una en <a href="/integraciones" class="underline">/integraciones</a>.
                            </p>
                        @endif
                    </div>

                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-xs text-emerald-800 space-y-2">
                        <p>
                            <i class="fa-solid fa-bolt mr-1"></i>
                            <strong>Modo LIVE híbrido + fusión.</strong> El bot lee del ERP (cache 30s) y combina con productos locales que no estén en el ERP.
                        </p>
                        <p class="text-[11px] opacity-80">
                            ✅ Precio + disponibilidad del ERP en tiempo real.<br>
                            ✅ Match por código → enriquece con cortes, fotos, palabras clave, destacados y sedes.<br>
                            ✅ Productos solo en <code>/productos</code> también se incluyen.<br>
                        </p>
                    </div>

                    <div class="border-t border-slate-200 pt-3 mt-3">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-bold text-slate-800">
                                <i class="fa-solid fa-eye mr-1"></i> Vista previa del catálogo del bot
                            </h4>
                            <button type="button" wire:click="verCatalogoBot"
                                    wire:loading.attr="disabled" wire:target="verCatalogoBot"
                                    class="rounded-xl bg-slate-700 hover:bg-slate-800 text-white text-xs font-semibold px-3 py-1.5 disabled:opacity-50">
                                <span wire:loading.remove wire:target="verCatalogoBot">
                                    <i class="fa-solid fa-magnifying-glass mr-1"></i> Ver lo que ve el bot
                                </span>
                                <span wire:loading wire:target="verCatalogoBot">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Cargando...
                                </span>
                            </button>
                        </div>
                        @if ($catalogoPreview !== null)
                            @if ($catalogoPreviewMeta)
                                <div class="text-xs text-slate-600 mb-2 flex flex-wrap gap-2">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5">
                                        <strong>{{ $catalogoPreviewMeta['total'] }}</strong> productos en total
                                    </span>
                                    @foreach ($catalogoPreviewMeta['fuentes'] ?? [] as $fuente => $cnt)
                                        @php
                                            $color = match($fuente) {
                                                'erp+local' => 'bg-emerald-100 text-emerald-700',
                                                'solo_erp'  => 'bg-blue-100 text-blue-700',
                                                'solo_local'=> 'bg-amber-100 text-amber-700',
                                                default     => 'bg-slate-100 text-slate-700',
                                            };
                                        @endphp
                                        <span class="rounded-md px-2 py-0.5 {{ $color }}">
                                            {{ $fuente }}: {{ $cnt }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <pre class="rounded-xl bg-slate-900 text-emerald-100 text-[11px] p-3 overflow-x-auto max-h-96 whitespace-pre-wrap font-mono">{{ $catalogoPreview }}</pre>
                        @else
                            <p class="text-xs text-slate-400">Click en "Ver lo que ve el bot" para inspeccionar el catálogo formateado tal cual lo recibe la IA.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- MODO AGENTE (RAG con tools) --}}
            <div class="mt-6 pt-6 border-t border-slate-200">
                <label class="flex items-start gap-4 cursor-pointer rounded-2xl border-2 p-4 transition {{ $bot_modo_agente ? 'border-brand bg-brand-soft' : 'border-slate-200 hover:bg-slate-50' }}">
                    <input type="checkbox" wire:model.live="bot_modo_agente"
                           class="mt-1 rounded border-slate-300 text-brand h-6 w-6">
                    <div class="flex-1">
                        <div class="font-bold text-base text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-robot text-brand"></i>
                            Modo agente (recomendado para catálogos grandes)
                        </div>
                        <div class="text-xs text-slate-600 mt-1">
                            En vez de meterle al bot el catálogo completo en cada mensaje, le damos <strong>tools</strong> para que él consulte solo lo que necesita. Reduce ~80% de tokens y mejora precisión.
                        </div>
                        @if ($bot_modo_agente)
                            <div class="mt-3 rounded-xl bg-white border border-brand/30 p-3 text-xs text-slate-700 space-y-1">
                                <p class="font-semibold text-brand mb-1">Tools disponibles para el agente:</p>
                                <p>🔍 <code>buscar_productos(query, categoria?)</code> — busca por nombre/código/keywords</p>
                                <p>📂 <code>listar_categorias()</code> — lista todas las categorías con conteo</p>
                                <p>🗂️ <code>productos_de_categoria(categoria)</code> — items de una categoría</p>
                                <p>📦 <code>info_producto(codigo)</code> — detalle + cortes + foto</p>
                                <p>⭐ <code>productos_destacados()</code> — top destacados + promociones</p>
                            </div>
                        @endif
                    </div>
                </label>
            </div>

            {{-- SOLICITAR CÉDULA AL CLIENTE --}}
            <div class="mt-6 pt-6 border-t border-slate-200">
                <label class="flex items-start gap-4 cursor-pointer rounded-2xl border-2 p-4 transition {{ $pedir_cedula ? 'border-amber-400 bg-amber-50' : 'border-slate-200 hover:bg-slate-50' }}">
                    <input type="checkbox" wire:model.live="pedir_cedula"
                           class="mt-1 rounded border-slate-300 text-amber-600 h-6 w-6">
                    <div class="flex-1">
                        <div class="font-bold text-base text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-id-card text-amber-600"></i>
                            Solicitar cédula al cliente
                        </div>
                        <div class="text-xs text-slate-600 mt-1">
                            Si lo activas, el bot le pedirá la cédula al cliente. Útil para validar contra el ERP, registrar usuarios o personalizar la atención.
                        </div>
                    </div>
                </label>

                @if ($pedir_cedula)
                    <div class="mt-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 space-y-4">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="cedula_obligatoria"
                                   class="mt-1 rounded border-slate-300 text-amber-600 h-5 w-5">
                            <div>
                                <div class="text-sm font-bold text-slate-800">Hacerla obligatoria</div>
                                <div class="text-[11px] text-slate-500">El bot la pedirá ANTES de tomar pedidos o dar info detallada. Si está OFF, solo la pide cuando le parezca útil.</div>
                            </div>
                        </label>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Cómo presentarlo al cliente (opcional)</label>
                            <textarea wire:model="cedula_descripcion" rows="2"
                                      placeholder='Ej: "¿Me regalas tu cédula para registrarte y darte mejor atención?"'
                                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white"></textarea>
                            <p class="text-[11px] text-slate-400 mt-1">Si lo dejas vacío, el bot improvisa una frase natural.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Consulta a usar cuando la obtenga (opcional)
                            </label>
                            <select wire:model="cedula_consulta_id"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white">
                                <option value="">— Solo guardar la cédula, no consultar —</option>
                                @foreach ($consultasDisponibles ?? [] as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->nombre_publico }} ({{ \App\Models\IntegracionConsulta::TIPOS[$c->tipo] ?? $c->tipo }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Si seleccionas una consulta, el bot la llamará con la cédula como parámetro para buscar al cliente en tu ERP. La consulta debe tener un parámetro llamado <code>cedula</code>.
                            </p>
                            @if (($consultasDisponibles ?? collect())->isEmpty())
                                <p class="text-[11px] text-amber-700 mt-1">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    No hay consultas disponibles. Crea una en <a href="/integraciones" class="underline font-semibold">/integraciones</a> y márcala como "disponible para el bot".
                                </p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- FILTROS DEL CATÁLOGO --}}
            <div class="mt-6 pt-6 border-t border-slate-200">
                <h4 class="text-base font-bold text-slate-800 mb-1">
                    <i class="fa-solid fa-filter text-indigo-600 mr-1"></i> Filtros del catálogo del bot
                    @if ($bot_modo_agente)
                        <span class="ml-2 text-[10px] font-semibold uppercase rounded bg-brand-soft text-brand px-2 py-0.5">
                            También aplica al modo agente
                        </span>
                    @endif
                </h4>
                <p class="text-xs text-slate-500 mb-4">
                    Limita lo que el bot ve. Esencial cuando el ERP tiene MUCHOS productos no-comida (insumos, bolsas, impuestos, etc.) que saturan al LLM.
                </p>

                <label class="flex items-start gap-3 mb-4 cursor-pointer rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                    <input type="checkbox" wire:model="excluir_productos_sin_precio"
                           class="mt-1 rounded border-slate-300 text-indigo-600 h-5 w-5">
                    <div>
                        <div class="text-sm font-bold text-slate-800">Ocultar productos sin precio (precio = $0)</div>
                        <div class="text-xs text-slate-500">Recomendado. Filtra ítems internos, raw materials, ajustes contables.</div>
                    </div>
                </label>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-slate-700">Categorías a excluir</label>
                        <button type="button" wire:click="cargarSugerenciasExclusion"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                            <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Cargar sugerencias
                        </button>
                    </div>
                    <textarea wire:model="categorias_excluidas_bot_str" rows="6"
                              placeholder="GENERAL&#10;SERVICIOS Y OTROS&#10;INSUMOS Y MP&#10;BOLSAS Y EMPAQUES&#10;EMBUTIDOS"
                              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Una categoría por línea. Coincide por nombre exacto (case-insensitive). Las verás listadas tal cual aparecen en el preview del catálogo.
                    </p>
                </div>
            </div>
        </section>

        {{-- ENVÍO DE IMÁGENES --}}
        <section x-show="tab === 'mensajes'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                    <i class="fa-solid fa-camera"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Envío de imágenes de productos</h3>
            </div>

            <div class="space-y-4">

                {{-- Toggle principal --}}
                <label class="inline-flex items-start gap-3 cursor-pointer w-full justify-between rounded-xl border-2 border-purple-200 bg-purple-50/50 p-4 hover:bg-purple-50 transition">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-slate-800 mb-1">
                            🖼️ Enviar imágenes de productos por WhatsApp
                        </div>
                        <div class="text-xs text-slate-600">
                            Cuando esté activo, la IA podrá enviarle al cliente las fotos de los productos del catálogo.
                            Útil cuando el cliente pregunta "tienes foto?" o duda entre opciones.
                        </div>
                    </div>
                    <input type="checkbox" wire:model.live="enviar_imagenes_productos"
                           class="mt-1 rounded border-slate-300 text-brand h-6 w-6">
                </label>

                @if($enviar_imagenes_productos)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ml-2 pl-4 border-l-2 border-purple-200">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Máximo de imágenes por mensaje
                            </label>
                            <input type="number" wire:model="max_imagenes_por_mensaje" min="1" max="10"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            <p class="text-[11px] text-slate-400 mt-1">Recomendado: 2-3 (no saturar al cliente).</p>
                        </div>

                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                                <div>
                                    <div class="text-sm font-medium text-slate-700">Mostrar destacados al saludar</div>
                                    <div class="text-[11px] text-slate-500">Envía 1-2 fotos al iniciar la charla</div>
                                </div>
                                <input type="checkbox" wire:model="enviar_imagen_destacados" class="rounded border-slate-300 text-brand h-5 w-5">
                            </label>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                        <i class="fa-solid fa-circle-info mt-0.5"></i>
                        <div>
                            <strong>Importante:</strong> los productos deben tener una <code>URL de imagen</code> configurada en el catálogo.
                            Productos sin imagen serán ignorados.
                            <a href="{{ route('productos.index') }}" class="text-amber-900 underline ml-1">Ir a productos</a>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- ╔═══ TRANSCRIPCIÓN DE AUDIOS (WHISPER) ═══╗ --}}
        <section x-show="tab === 'mensajes'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-microphone"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-800">Notas de voz del cliente</h3>
                    <p class="text-xs text-slate-500">Transcribe automáticamente los audios que envíe el cliente y los procesa como texto.</p>
                </div>
            </div>

            <label class="inline-flex items-start gap-3 cursor-pointer w-full justify-between rounded-xl border-2 border-emerald-200 bg-emerald-50/50 p-4 hover:bg-emerald-50 transition">
                <div class="flex-1">
                    <div class="text-sm font-bold text-slate-800 mb-1">
                        🎤 Transcribir notas de voz con Whisper (OpenAI)
                    </div>
                    <div class="text-xs text-slate-600">
                        Cuando el cliente mande un audio en vez de texto, el bot lo transcribe y responde igual que si hubiera escrito.
                        Perfecto para clientes que prefieren hablar antes que escribir.
                    </div>
                </div>
                <input type="checkbox" wire:model.live="transcribir_audios"
                       class="mt-1 rounded border-slate-300 text-brand h-6 w-6">
            </label>

            <div class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                <i class="fa-solid fa-circle-info mt-0.5"></i>
                <div>
                    <strong>Costo aproximado:</strong> Whisper cobra ~$0.006 USD por minuto de audio.
                    Un audio típico de WhatsApp (10-20 seg) cuesta menos de $0.002 USD. Requiere
                    <code>OPENAI_API_KEY</code> configurada en el .env.
                </div>
            </div>
        </section>

        {{-- ╔═══ AGRUPACIÓN DE MENSAJES (DEBOUNCE) ═══╗ --}}
        <section x-show="tab === 'mensajes'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600">
                    <i class="fa-solid fa-comments"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-800">Agrupar mensajes seguidos del cliente</h3>
                    <p class="text-xs text-slate-500">Evita responder a cada mensaje aislado — espera a que el cliente termine de escribir.</p>
                </div>
            </div>

            <div class="space-y-4">

                <label class="inline-flex items-start gap-3 cursor-pointer w-full justify-between rounded-xl border-2 border-cyan-200 bg-cyan-50/40 p-4 hover:bg-cyan-50/70 transition">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-slate-800 mb-1">
                            🧩 Esperar antes de responder
                        </div>
                        <div class="text-xs text-slate-600 leading-relaxed">
                            Si el cliente manda <strong>"Hola"</strong> + <strong>"Quiero pollo"</strong> + <strong>"Para mañana"</strong>
                            en 3 mensajes seguidos, el bot espera unos segundos, los agrupa
                            y responde UNA sola vez con todo el contexto. Mucho más natural.
                        </div>
                    </div>
                    <input type="checkbox" wire:model.live="agrupar_mensajes_activo"
                           class="mt-1 rounded border-slate-300 text-brand h-6 w-6">
                </label>

                @if($agrupar_mensajes_activo)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ml-2 pl-4 border-l-2 border-cyan-200">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Segundos a esperar
                                <span class="text-xs text-slate-400">({{ $agrupar_mensajes_segundos }}s)</span>
                            </label>
                            <input type="range" wire:model.live="agrupar_mensajes_segundos"
                                   min="1" max="15" step="1"
                                   class="w-full">
                            <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                                <span>1s (rápido)</span>
                                <span>15s (relajado)</span>
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600">
                            <i class="fa-solid fa-lightbulb text-amber-500 mr-1"></i>
                            <strong>Recomendación:</strong> 4-6 segundos.
                            Menos puede cortar al cliente, más se siente lento.
                        </div>
                    </div>

                    <div class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                        <i class="fa-solid fa-circle-info mt-0.5"></i>
                        <div>
                            <strong>Cómo funciona:</strong> cuando llega un mensaje, el bot lo guarda en un buffer
                            y espera N segundos. Si llega otro mensaje del MISMO cliente en ese tiempo, los agrupa.
                            Solo el último mensaje "gana" y procesa toda la conversación junta.
                            Garantiza que <strong>nunca</strong> se mezclen pedidos de clientes distintos
                            (cada cliente tiene su propio buffer aislado por teléfono).
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- MOTOR IA --}}
        <section x-show="tab === 'ia'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-brain"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Motor de IA (OpenAI)</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Modelo</label>
                    <select wire:model="modelo_openai"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        @foreach($modelosDisponibles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Temperatura
                        <span class="text-[10px] text-slate-400 font-normal">({{ $temperatura }})</span>
                    </label>
                    <input type="range" wire:model.live="temperatura" min="0" max="2" step="0.05"
                           class="w-full">
                    <div class="flex justify-between text-[10px] text-slate-400">
                        <span>0 (preciso)</span>
                        <span>2 (creativo)</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Max tokens por respuesta</label>
                    <input type="number" wire:model="max_tokens" min="100" max="4000" step="100"
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                        <span class="text-sm font-medium text-slate-700">Saludar con promos vigentes</span>
                        <input type="checkbox" wire:model="saludar_con_promociones" class="rounded border-slate-300 text-brand h-5 w-5">
                    </label>
                </div>
            </div>
        </section>

        {{-- DERIVACIÓN AUTOMÁTICA POR IA --}}
        <section x-show="tab === 'derivacion'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                    <i class="fa-solid fa-headset"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-800">Derivación automática por IA</h3>
                    <p class="text-xs text-slate-500">Cuando la IA detecte clientes molestos o fuera del alcance del bot, deriva al departamento correspondiente.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                    <div>
                        <span class="text-sm font-semibold text-slate-700">Activar derivación</span>
                        <p class="text-[11px] text-slate-500">Expone la función a la IA.</p>
                    </div>
                    <input type="checkbox" wire:model="derivacion_activa" class="rounded border-slate-300 text-brand h-5 w-5">
                </label>

                <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                    <div>
                        <span class="text-sm font-semibold text-slate-700">Red de seguridad</span>
                        <p class="text-[11px] text-slate-500">Si la IA dice "voy a derivar" sin hacerlo, derivamos nosotros.</p>
                    </div>
                    <input type="checkbox" wire:model="derivacion_fallback_activa" class="rounded border-slate-300 text-brand h-5 w-5">
                </label>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">
                    Instrucciones que recibe la IA (descripción de la función)
                </label>
                <textarea wire:model="derivacion_instrucciones_ia" rows="10"
                          placeholder="Deja vacío para usar la plantilla por defecto."
                          class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-mono leading-relaxed focus:border-brand focus:ring-brand"></textarea>
                <p class="text-[11px] text-slate-500 mt-1">
                    Este texto es el que define CUÁNDO y CÓMO la IA decide derivar. Edítalo para ajustar criterio (ej. ser más estricta o más permisiva).
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Frases de detección (fallback)
                    </label>
                    <textarea wire:model="derivacion_frases_deteccion" rows="4"
                              placeholder="voy a derivar, voy a transferir, te paso con, ..."
                              class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-mono focus:border-brand focus:ring-brand"></textarea>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Si el texto del bot contiene alguna de estas (separadas por coma), asumimos que está "intentando derivar" y forzamos la derivación.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Departamento por defecto (fallback)
                    </label>
                    <select wire:model="derivacion_departamento_fallback_id"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        <option value="">— Primer departamento activo —</option>
                        @foreach(\App\Models\Departamento::where('activo', true)->orderBy('nombre')->get() as $d)
                            <option value="{{ $d->id }}">{{ $d->icono_emoji }} {{ $d->nombre }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Al activarse el fallback, usamos este departamento. Si vacío, se usa el primero activo.
                    </p>
                </div>
            </div>
        </section>

        {{-- INFO EMPRESA --}}
        <section x-show="tab === 'general'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                    <i class="fa-solid fa-building"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-800">Información de la empresa</h3>
                    <p class="text-xs text-slate-500">Lo que la IA sabe sobre tu negocio. Se inyecta como variable <code class="bg-slate-100 px-1 rounded">{empresa}</code> en el prompt.</p>
                </div>
            </div>

            <textarea wire:model="info_empresa" rows="6"
                      placeholder="Ej:&#10;Alimentos La Hacienda&#10;- Más de 25 años de experiencia.&#10;- Ubicada en Bello, Antioquia.&#10;- Calidad, frescura y servicio al cliente."
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono leading-relaxed focus:border-brand focus:ring-brand"></textarea>
            <p class="text-[11px] text-slate-400 mt-1">
                Describe brevemente: nombre, ubicación, años de experiencia, valores, servicios. La IA usará esto cuando el cliente pregunte por la empresa.
            </p>
        </section>

        {{-- ZONAS DE COBERTURA DEL BOT --}}
        <section x-show="tab === 'zonas'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <i class="fa-solid fa-map-location-dot"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-800">Zonas de cobertura del bot</h3>
                    <p class="text-xs text-slate-500">
                        Selecciona las zonas con las que el bot debe trabajar para validar cobertura
                        e inyectarlas en la variable <code class="bg-slate-100 px-1 rounded">{zonas}</code> del prompt.
                        Si no seleccionas ninguna, se usan <strong>todas las activas</strong>.
                    </p>
                </div>
            </div>

            @if($zonasDisponibles->isEmpty())
                <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    No hay zonas activas configuradas para este tenant.
                    <a href="{{ url('/zonas-cobertura') }}" class="underline font-medium">Crea o activa zonas</a> primero.
                </div>
            @else
                <div class="flex items-center gap-2 mb-3">
                    <button type="button"
                            wire:click="$set('bot_zonas_ids', {{ json_encode($zonasDisponibles->pluck('id')->all()) }})"
                            class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">
                        <i class="fa-solid fa-check-double mr-1"></i> Seleccionar todas
                    </button>
                    <button type="button"
                            wire:click="$set('bot_zonas_ids', [])"
                            class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">
                        <i class="fa-solid fa-eraser mr-1"></i> Limpiar (usar todas)
                    </button>
                    <span class="text-xs text-slate-500 ml-auto">
                        {{ count($bot_zonas_ids) }} de {{ $zonasDisponibles->count() }} seleccionadas
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-96 overflow-y-auto pr-1">
                    @foreach($zonasDisponibles as $z)
                        <label class="flex items-start gap-2 px-3 py-2 rounded-xl border border-slate-200 hover:border-sky-300 hover:bg-sky-50/30 cursor-pointer transition">
                            <input type="checkbox"
                                   value="{{ $z->id }}"
                                   wire:model="bot_zonas_ids"
                                   class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500 h-4 w-4">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-slate-800 truncate">{{ $z->nombre }}</div>
                                @if($z->sede)
                                    <div class="text-[11px] text-slate-500 truncate">
                                        <i class="fa-solid fa-store text-[10px]"></i> {{ $z->sede->nombre }}
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400">Todas las sedes</div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- BIENVENIDA --}}
        <section x-show="tab === 'general'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-message"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Frase personalizada de bienvenida (opcional)</h3>
            </div>

            <textarea wire:model="frase_bienvenida" rows="2"
                      placeholder="Ej: ¡Hola! Bienvenido a La Hacienda 🥩 ¿qué te provoca hoy?"
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand"></textarea>
            <p class="text-[11px] text-slate-400 mt-1">Si la dejas vacía, la IA improvisa el saludo según la hora y el cliente.</p>
        </section>

        {{-- ╔═══ EDITOR DE PROMPT PERSONALIZADO ═══╗ --}}
        {{-- INSTRUCCIONES EXTRA (se SUMAN al prompt, no reemplazan) --}}
        <section x-show="tab === 'prompt'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-plus-minus"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-800">Instrucciones extra al prompt</h3>
                    <p class="text-xs text-slate-500">
                        Reglas adicionales que se <strong>agregan</strong> al final del prompt (no lo reemplazan).
                        Perfecto para reglas específicas de tu negocio sin tocar la plantilla base.
                    </p>
                </div>
            </div>

            <textarea wire:model="instrucciones_extra" rows="10"
                      placeholder="Ejemplos:
• Nunca ofrezcas entregas los domingos porque estamos cerrados.
• Si el cliente pregunta por la sede de Envigado, aclara que la atendemos solo los jueves.
• Para pedidos sobre 500k ofrece siempre el 5% de descuento.
• Usa la palabra 'parcero' de forma natural, es parte de nuestra marca."
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono leading-relaxed focus:border-brand focus:ring-brand"></textarea>
            <div class="flex items-start gap-3 mt-2">
                <p class="text-[11px] text-slate-500 flex-1">
                    💡 <strong>Cómo funciona:</strong> cuando el bot recibe un mensaje, primero carga el prompt base (o tu prompt personalizado si lo activaste abajo), y al final agrega estas instrucciones bajo el título "🔧 REGLAS ADICIONALES DE ESTE NEGOCIO". La IA las lee igual que el resto del prompt.
                </p>
                <p class="text-[11px] text-slate-500 flex-shrink-0">
                    Variables: <code class="bg-slate-100 px-1 rounded">{nombre_asesora}</code>,
                    <code class="bg-slate-100 px-1 rounded">{fecha_actual}</code>, etc.
                </p>
            </div>
            @error('instrucciones_extra')
                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </section>

        <section x-show="tab === 'prompt'" x-cloak class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <i class="fa-solid fa-code"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-slate-800">Prompt personalizado del bot</h3>
                    <p class="text-xs text-slate-500">Edita exactamente lo que ve la IA — usa las variables del panel.</p>
                </div>
            </div>

            {{-- Toggle activación --}}
            <label class="inline-flex items-start gap-3 cursor-pointer w-full justify-between rounded-xl border-2 border-rose-200 bg-rose-50/30 p-4 hover:bg-rose-50/60 transition mb-4">
                <div class="flex-1">
                    <div class="text-sm font-bold text-slate-800 mb-1">
                        ⚡ Usar prompt personalizado
                    </div>
                    <div class="text-xs text-slate-600">
                        Si lo activas, la IA usará TU prompt en lugar del de fábrica.
                        Asegúrate de incluir todas las variables necesarias para que el bot funcione bien.
                    </div>
                </div>
                <input type="checkbox" wire:model.live="usar_prompt_personalizado"
                       class="mt-1 rounded border-slate-300 text-brand h-6 w-6">
            </label>

            @if($usar_prompt_personalizado)
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {{-- Editor textarea --}}
                    <div class="lg:col-span-2">
                        <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                            <label class="text-sm font-medium text-slate-700">
                                <i class="fa-solid fa-pen-to-square text-brand mr-1"></i>
                                Tu prompt
                            </label>
                            <div class="flex items-center gap-3">
                                {{-- Carga plantilla GENERICA (dinamica, sin hardcode) --}}
                                <button type="button"
                                        @click.prevent="$dispatch('confirm-show', {
                                            title: 'Cargar plantilla genérica dinámica',
                                            message: '✨ Plantilla 100% dinámica: usa solo variables del tenant ({tenant_nombre}, {ciudad}, {tipo_negocio}, etc). Funciona out-of-the-box con cualquier negocio. Reemplaza tu prompt actual (NO guarda hasta que pulses Guardar).',
                                            confirmText: 'Sí, cargar genérica',
                                            type: 'primary',
                                            onConfirm: () => $wire.cargarPlantillaGenerica(),
                                        })"
                                        class="text-[11px] font-bold text-brand hover:text-brand-dark hover:underline">
                                    <i class="fa-solid fa-wand-sparkles mr-1"></i> Cargar plantilla genérica
                                </button>

                                {{-- Carga + edita (no guarda) --}}
                                <button type="button"
                                        @click.prevent="$dispatch('confirm-show', {
                                            title: 'Cargar plantilla por defecto',
                                            message: 'Reemplazará el contenido del editor (NO guarda). Tendrás que hacer click en Guardar configuración después.',
                                            confirmText: 'Sí, cargar',
                                            type: 'primary',
                                            onConfirm: () => $wire.cargarPlantillaPorDefecto(),
                                        })"
                                        class="text-[11px] font-semibold text-brand hover:underline">
                                    <i class="fa-solid fa-rotate-left mr-1"></i> Por defecto (legacy)
                                </button>

                                {{-- Sincroniza Y guarda automáticamente --}}
                                <button type="button"
                                        @click.prevent="$dispatch('confirm-show', {
                                            title: 'Sincronizar con plantilla de fábrica',
                                            message: 'Reemplaza Y guarda inmediatamente el prompt con la versión más reciente del código. Úsalo cuando se actualicen las reglas del bot.',
                                            confirmText: 'Sincronizar y guardar',
                                            type: 'success',
                                            onConfirm: () => $wire.sincronizarConPlantillaDeFabrica(),
                                        })"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-white bg-emerald-500 hover:bg-emerald-600 px-3 py-1.5 rounded-lg transition shadow-sm">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    Sincronizar y guardar
                                </button>
                            </div>
                        </div>

                        {{-- Toggle vista bloques / vista plana --}}
                        <div class="flex items-center gap-2 mb-3 p-2 rounded-xl bg-slate-100">
                            <button type="button" wire:click="$set('vistaPorBloques', true); $call('sincronizarPromptABloques')"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold transition
                                        {{ $vistaPorBloques ? 'bg-white text-slate-800 shadow' : 'text-slate-500 hover:text-slate-700' }}">
                                <i class="fa-solid fa-layer-group"></i> Por bloques
                            </button>
                            <button type="button" wire:click="$set('vistaPorBloques', false); $call('sincronizarBloquesAPrompt')"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold transition
                                        {{ !$vistaPorBloques ? 'bg-white text-slate-800 shadow' : 'text-slate-500 hover:text-slate-700' }}">
                                <i class="fa-solid fa-code"></i> Vista plana
                            </button>
                        </div>

                        @if($vistaPorBloques)
                            {{-- ╔═══ EDITOR POR BLOQUES ═══╗ --}}
                            <div class="space-y-2" x-data="{ abierto: 0 }">
                                @foreach($bloquesPrompt as $idx => $bloque)
                                    @php
                                        // Pre-formato del contenido para resaltar variables {xxx}
                                        $contenidoHtml = preg_replace(
                                            '/\{([a-z_]+)\}/i',
                                            '<span class="bloque-var">$0</span>',
                                            e($bloque['contenido'] ?? '')
                                        );
                                        $iconos = [
                                            'IDENTIDAD' => 'fa-user-tie',
                                            'CONTEXTO' => 'fa-circle-info',
                                            'EMPRESA' => 'fa-building',
                                            'CATÁLOGO' => 'fa-box',
                                            'PROMOCIONES' => 'fa-tags',
                                            'HORARIOS' => 'fa-clock',
                                            'HORARIOS Y ZONAS' => 'fa-clock',
                                            'ZONAS' => 'fa-map-location-dot',
                                            'ZONAS DE COBERTURA' => 'fa-map-location-dot',
                                            'ANS' => 'fa-stopwatch',
                                            'REGLAS' => 'fa-shield-halved',
                                            'REGLAS BÁSICAS' => 'fa-shield-halved',
                                        ];
                                        $tituloUp = mb_strtoupper(trim($bloque['titulo'] ?? ''));
                                        $icono = $iconos[$tituloUp] ?? 'fa-puzzle-piece';
                                    @endphp

                                    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden" wire:key="bloque-{{ $idx }}">
                                        {{-- Header del bloque --}}
                                        <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b border-slate-100">
                                            <button type="button" @click="abierto = (abierto === {{ $idx }} ? -1 : {{ $idx }})"
                                                    class="w-5 h-5 rounded text-slate-400 hover:text-slate-700 transition">
                                                <i class="fa-solid fa-chevron-down text-[11px] transition" :class="abierto === {{ $idx }} ? 'rotate-180' : ''"></i>
                                            </button>

                                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-50 text-purple-600 flex-shrink-0">
                                                <i class="fa-solid {{ $icono }} text-xs"></i>
                                            </span>

                                            <input type="text"
                                                   wire:model.lazy="bloquesPrompt.{{ $idx }}.titulo"
                                                   class="flex-1 bg-transparent border-0 text-sm font-bold text-slate-800 focus:outline-none focus:ring-0 px-0">

                                            {{-- Acciones --}}
                                            <button type="button" wire:click="moverBloque({{ $idx }}, -1)" title="Subir"
                                                    class="text-slate-400 hover:text-slate-700 px-1.5 py-1 rounded transition disabled:opacity-30"
                                                    @if($idx === 0) disabled @endif>
                                                <i class="fa-solid fa-arrow-up text-xs"></i>
                                            </button>
                                            <button type="button" wire:click="moverBloque({{ $idx }}, 1)" title="Bajar"
                                                    class="text-slate-400 hover:text-slate-700 px-1.5 py-1 rounded transition disabled:opacity-30"
                                                    @if($idx === count($bloquesPrompt) - 1) disabled @endif>
                                                <i class="fa-solid fa-arrow-down text-xs"></i>
                                            </button>
                                            <button type="button"
                                                    @click.prevent="$dispatch('confirm-show', { message: 'Eliminar bloque {{ $bloque['titulo'] }}?', type: 'danger', onConfirm: () => $wire.eliminarBloque({{ $idx }}) })"
                                                    title="Eliminar"
                                                    class="text-rose-400 hover:text-rose-600 px-1.5 py-1 rounded transition">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </div>

                                        {{-- Cuerpo del bloque (contraído por defecto excepto el activo) --}}
                                        <div x-show="abierto === {{ $idx }}" x-collapse>
                                            <div class="p-3">
                                                <textarea wire:model.lazy="bloquesPrompt.{{ $idx }}.contenido"
                                                          rows="6"
                                                          placeholder="Contenido del bloque…"
                                                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono leading-relaxed focus:border-purple-400 focus:ring-1 focus:ring-purple-100 bloque-textarea"
                                                          spellcheck="false"></textarea>
                                                <div class="text-[10px] text-slate-400 mt-1.5 flex items-center justify-between">
                                                    <span>{{ strlen($bloque['contenido'] ?? '') }} chars</span>
                                                    <span class="text-purple-600">
                                                        <i class="fa-solid fa-circle-info"></i> Las {variables} se reemplazan automáticamente al enviar al cliente.
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Preview compacto cuando está cerrado --}}
                                        <div x-show="abierto !== {{ $idx }}" class="px-3 py-2">
                                            <div class="text-[11px] text-slate-500 line-clamp-2 bloque-preview">
                                                {!! $contenidoHtml ?: '<span class="text-slate-300 italic">(vacío)</span>' !!}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <button type="button" wire:click="agregarBloque"
                                        class="w-full rounded-xl border-2 border-dashed border-slate-300 hover:border-purple-400 hover:bg-purple-50/30 px-4 py-3 text-sm font-semibold text-slate-500 hover:text-purple-700 transition">
                                    <i class="fa-solid fa-plus mr-1"></i> Añadir bloque
                                </button>
                            </div>

                            <style>
                                .bloque-var {
                                    background: linear-gradient(135deg, #ede9fe, #ddd6fe);
                                    color: #6d28d9;
                                    padding: 1px 5px;
                                    border-radius: 4px;
                                    font-family: ui-monospace, monospace;
                                    font-size: 0.92em;
                                    font-weight: 600;
                                    border: 1px solid #c4b5fd;
                                }
                                .bloque-textarea { tab-size: 2; }
                            </style>
                        @else
                            {{-- ╔═══ VISTA PLANA (textarea original) ═══╗ --}}
                            <textarea wire:model="system_prompt" rows="22"
                                      placeholder="Escribe tu prompt aquí, usando {variables} del panel derecho..."
                                      class="w-full rounded-xl border border-slate-200 px-4 py-3 text-xs font-mono leading-relaxed focus:border-rose-400 focus:ring-2 focus:ring-rose-100"
                                      spellcheck="false"></textarea>
                        @endif

                        @error('system_prompt')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror

                        <div class="text-[10px] text-slate-400 mt-2 flex items-center gap-3">
                            <span>{{ strlen($system_prompt) }} / 20.000 caracteres</span>
                            <span>·</span>
                            <span>~{{ ceil(strlen($system_prompt) / 4) }} tokens estimados</span>
                            @if($vistaPorBloques)
                                <span>·</span>
                                <span>{{ count($bloquesPrompt) }} bloques</span>
                            @endif
                        </div>
                    </div>

                    {{-- Panel de variables --}}
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fa-solid fa-puzzle-piece text-purple-500 mr-1"></i>
                            Variables disponibles
                        </label>
                        <p class="text-[10px] text-slate-500 mb-2">
                            Click para copiar. Pega donde necesites en el prompt.
                        </p>

                        <div class="space-y-1.5 max-h-[440px] overflow-y-auto pr-1">
                            @foreach($variablesDisponibles as $v)
                                <button type="button"
                                        x-data
                                        @click="
                                            navigator.clipboard.writeText('{{ '{' . $v['key'] . '}' }}');
                                            $el.querySelector('.copy-status').textContent = '✓ Copiado';
                                            setTimeout(() => $el.querySelector('.copy-status').textContent = '', 1200);
                                        "
                                        class="group block w-full text-left rounded-lg border border-slate-200 bg-white hover:bg-purple-50 hover:border-purple-300 transition px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <code class="text-[11px] font-mono font-bold text-purple-700">
                                            {{ '{' . $v['key'] . '}' }}
                                        </code>
                                        <span class="copy-status text-[10px] text-emerald-600 font-semibold"></span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ $v['descripcion'] }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info mt-0.5"></i>
                    <div>
                        <strong>Tips para un buen prompt:</strong>
                        <ul class="list-disc ml-4 mt-1 space-y-0.5">
                            <li>SIEMPRE incluye <code>{catalogo}</code> para que la IA conozca tus productos.</li>
                            <li>Incluye <code>{zonas}</code> para que valide cobertura.</li>
                            <li>Si activas imágenes, incluye <code>{nota_imagenes}</code> para que la IA sepa cuándo usarlas.</li>
                            <li>Define reglas claras de cuándo llamar a <code>confirmar_pedido</code>.</li>
                            <li>Las variables se reemplazan al construir cada conversación.</li>
                        </ul>
                    </div>
                </div>
            @else
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600">
                    <i class="fa-solid fa-info-circle text-slate-400 mr-2"></i>
                    Estás usando el prompt de fábrica. Activa el toggle de arriba para personalizarlo.
                </div>
            @endif
        </section>

        {{-- ─────────────────────────────────────────────────────────────
             🎂 FELICITACIONES DE CUMPLEAÑOS
             ───────────────────────────────────────────────────────────── --}}
        {{-- ENCUESTA POST-ENTREGA --}}
        <section x-show="tab === 'encuesta'" x-cloak class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 text-xl">
                    <i class="fa-solid fa-star-half-stroke"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800">Encuesta post-entrega</h3>
                    <p class="text-xs text-slate-500">
                        Cuando un pedido se marca como ENTREGADO, el cliente recibe un mensaje de WhatsApp con un link para calificar el proceso y al domiciliario.
                        Ver respuestas en
                        <a href="{{ route('encuestas.index') }}" class="text-amber-700 underline font-medium">/encuestas</a>.
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="encuesta_activa"
                           class="mt-1 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">Enviar encuesta automáticamente</div>
                        <div class="text-xs text-slate-500">Al pasar el pedido a estado "Entregado".</div>
                    </div>
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Esperar antes de enviar (minutos)</label>
                        <input type="number" wire:model="encuesta_delay_minutos" min="0" max="1440"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                        <p class="text-[11px] text-slate-500 mt-1">0 = inmediato. Recomendado: 10–30 min para que el cliente termine de comer.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mensaje de encuesta (plantilla)</label>
                    <textarea wire:model="encuesta_mensaje" rows="6" maxlength="2000"
                              placeholder="Si lo dejas vacío, se usa una plantilla por defecto."
                              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-amber-400 focus:ring-2 focus:ring-amber-100"></textarea>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Variables disponibles: <code>{nombre}</code>, <code>{nombre_completo}</code>, <code>{domiciliario}</code>, <code>{pedido}</code>, <code>{url}</code> (link a la encuesta).
                    </p>
                </div>
            </div>
        </section>

        {{-- ╔═══ NOTIFICACIONES AL CLIENTE ═══╗ --}}
        <section x-show="tab === 'notificaciones'" x-cloak class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 text-xl">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800">Notificaciones al cliente por WhatsApp</h3>
                    <p class="text-xs text-slate-500">
                        Activa o desactiva cada mensaje que el cliente recibe durante el ciclo del pedido.
                        Si lo desactivas, el bot omite ese mensaje automáticamente.
                    </p>
                </div>
            </div>

            <div class="space-y-3" x-data="{ abierto: '' }">

                {{-- Card especial: mensaje de confirmación del pedido (siempre activo, sin delay) --}}
                <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/60 to-white shadow-sm hover:shadow-md transition overflow-hidden">
                    <div class="flex items-center gap-4 p-4">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl text-white shadow-md flex-shrink-0"
                              style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fa-solid fa-clipboard-check text-lg"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-bold text-slate-800">Pedido confirmado</span>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                    <i class="fa-solid fa-bolt text-[8px]"></i> SIEMPRE
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5">Resumen + total + link. Parte del flujo, no se puede desactivar.</div>
                        </div>
                        <button type="button" @click="abierto = (abierto === 'confirmado' ? '' : 'confirmado')"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50 px-3 py-2 text-xs font-bold text-slate-700 transition shadow-sm">
                            <i class="fa-regular fa-pen-to-square text-emerald-600"></i>
                            <span>Editar</span>
                            <i class="fa-solid text-[10px] text-slate-400" :class="abierto === 'confirmado' ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        </button>
                    </div>
                    <div x-show="abierto === 'confirmado'" x-cloak class="border-t border-emerald-200 bg-white p-4 space-y-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                <i class="fa-regular fa-pen-to-square text-emerald-600"></i> Plantilla del mensaje de confirmación
                            </label>
                            <x-emoji-picker target="textarea-notif-confirmado" />
                            <textarea id="textarea-notif-confirmado" wire:model.lazy="notif_pedido_confirmado_mensaje" rows="14"
                                      class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono leading-relaxed focus:border-emerald-400 focus:ring-1 focus:ring-emerald-100"></textarea>
                            <p class="text-[10px] text-slate-500 mt-1">
                                Variables: <code>{nombre}</code> <code>{nombre_completo}</code> <code>{pedido}</code> <code>{productos}</code> <code>{direccion}</code> <code>{barrio}</code> <code>{telefono_contacto}</code> <code>{total}</code> <code>{beneficio}</code> <code>{bloque_pago}</code> <code>{link_seguimiento}</code>
                            </p>
                            <p class="text-[10px] text-slate-500 mt-0.5">
                                <strong>{productos}</strong> es el listado formateado (multilínea con cantidades).
                                <strong>{beneficio}</strong> aparece solo si se aplicó envío gratis.
                                <strong>{bloque_pago}</strong> aparece solo si Wompi está activado.
                            </p>
                        </div>
                    </div>
                </div>

                @php
                    // [slug, titulo, icon FA, color base, descripcion, gradient_from, gradient_to]
                    $notifs = [
                        ['en_preparacion', 'En preparación',       'fa-utensils',         'amber',   'Pedido pasa a "en preparación".',     '#f59e0b', '#d97706'],
                        ['en_camino',      'En camino con código', 'fa-truck-fast',       'violet',  'Sale el domiciliario.',                '#8b5cf6', '#6d28d9'],
                        ['entregado',      'Pedido entregado',     'fa-circle-check',     'emerald', 'Se marca como entregado.',             '#10b981', '#059669'],
                        ['pago_aprobado',  'Pago aprobado',        'fa-shield-halved',    'blue',    'Webhook Wompi confirma el pago.',      '#3b82f6', '#2563eb'],
                        ['pago_rechazado', 'Pago rechazado',       'fa-triangle-exclamation','rose', 'El pago falla, link para reintentar.', '#f43f5e', '#e11d48'],
                    ];
                @endphp

                @foreach($notifs as [$slug, $titulo, $icon, $color, $cuando, $gradFrom, $gradTo])
                    @php
                        $keyActivo  = "notif_{$slug}_activa";
                        $keyMensaje = "notif_{$slug}_mensaje";
                        $keyDelay   = "notif_{$slug}_delay";
                        $valActivo  = $$keyActivo;
                        $valDelay   = $$keyDelay;
                    @endphp
                    <div class="rounded-2xl border transition overflow-hidden shadow-sm hover:shadow-md
                                {{ $valActivo
                                    ? "border-{$color}-200 bg-gradient-to-br from-{$color}-50/60 to-white"
                                    : 'border-slate-200 bg-white opacity-75' }}">

                        {{-- Header: icono + título + estado + delay + expandir --}}
                        <div class="flex items-center gap-4 p-4">
                            {{-- Icono con gradiente (apagado si está OFF) --}}
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl text-white shadow-md flex-shrink-0 transition"
                                  style="background: {{ $valActivo ? "linear-gradient(135deg, {$gradFrom}, {$gradTo})" : 'linear-gradient(135deg, #cbd5e1, #94a3b8)' }};">
                                <i class="fa-solid {{ $icon }} text-lg"></i>
                            </span>

                            {{-- Toggle estilo switch --}}
                            <label class="inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" wire:model.live="{{ $keyActivo }}" class="sr-only peer">
                                <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-{{ $color }}-500"></div>
                            </label>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-bold text-slate-800">{{ $titulo }}</span>
                                    @if($valActivo)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                            <i class="fa-solid fa-circle text-[6px] text-emerald-500 animate-pulse"></i> ACTIVO
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 bg-slate-200 px-2 py-0.5 rounded-full">
                                            <i class="fa-solid fa-power-off text-[8px]"></i> APAGADO
                                        </span>
                                    @endif
                                    @if($valDelay > 0)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full" title="Demora antes de enviar">
                                            <i class="fa-regular fa-clock text-[9px]"></i> {{ $valDelay }}s
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ $cuando }}</div>
                            </div>

                            <button type="button" @click="abierto = (abierto === '{{ $slug }}' ? '' : '{{ $slug }}')"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 hover:border-{{ $color }}-300 hover:bg-{{ $color }}-50 px-3 py-2 text-xs font-bold text-slate-700 transition shadow-sm flex-shrink-0">
                                <i class="fa-regular fa-pen-to-square text-{{ $color }}-600"></i>
                                <span class="hidden sm:inline">Editar</span>
                                <i class="fa-solid text-[10px] text-slate-400 transition" :class="abierto === '{{ $slug }}' ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </button>
                        </div>

                        {{-- Cuerpo expandible: textarea + delay --}}
                        <div x-show="abierto === '{{ $slug }}'" x-cloak class="border-t border-slate-200 bg-white p-4 space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">
                                    <i class="fa-regular fa-pen-to-square text-{{ $color }}-600"></i> Plantilla del mensaje
                                </label>
                                <x-emoji-picker :target="'textarea-notif-' . $slug" />
                                <textarea id="textarea-notif-{{ $slug }}" wire:model.lazy="{{ $keyMensaje }}" rows="5"
                                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono leading-relaxed focus:border-{{ $color }}-400 focus:ring-1 focus:ring-{{ $color }}-100"
                                          placeholder="Escribe el mensaje que recibirá el cliente..."></textarea>
                                <p class="text-[10px] text-slate-500 mt-1">
                                    Variables: <code>{nombre}</code> <code>{nombre_completo}</code> <code>{pedido}</code> <code>{total}</code> <code>{token}</code> <code>{direccion}</code> <code>{barrio}</code> <code>{link_pago}</code> <code>{link_seguimiento}</code>
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">⏱️ Demora antes de enviar (segundos)</label>
                                <input type="number" wire:model.lazy="{{ $keyDelay }}" min="0" max="86400"
                                       class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                                <p class="text-[10px] text-slate-500 mt-1">
                                    0 = inmediato. Útil para "reordenar": ej. pago aprobado con 0s, encuesta con 120s, etc. Máx 86400 (24h).
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 rounded-xl bg-blue-50 border border-blue-100 p-3 text-xs text-blue-800 flex items-start gap-2">
                <i class="fa-solid fa-circle-info mt-0.5"></i>
                <div>
                    <strong>Tip:</strong> el mensaje del bot al confirmar el pedido (con resumen + total + link de pago) es parte
                    del flujo conversacional y no se controla aquí — siempre se envía. La encuesta post-entrega tiene
                    su propio toggle en la pestaña "Encuesta post-entrega".
                </div>
            </div>
        </section>

        {{-- ╔═══ PAGOS EN LINEA (WOMPI) ═══╗ --}}
        <section x-show="tab === 'pagos'" x-cloak class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600 text-xl">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800">Pagos en línea por WhatsApp</h3>
                    <p class="text-xs text-slate-500">
                        Controla si el bot incluye un link de pago Wompi cuando el pedido se confirma.
                        Las llaves de Wompi se configuran por tenant en
                        <a href="{{ route('admin.tenants.index') }}" class="text-violet-700 underline">admin → tenants</a>.
                    </p>
                </div>
            </div>

            <label class="flex items-start gap-3 cursor-pointer rounded-xl border-2 p-4 transition
                          {{ $enviar_link_pago ? 'border-violet-300 bg-violet-50/40' : 'border-slate-200 bg-white' }}">
                <input type="checkbox" wire:model="enviar_link_pago"
                       class="mt-1 rounded border-slate-300 text-violet-600 h-5 w-5">
                <div class="flex-1">
                    <div class="text-sm font-bold text-slate-800 mb-1">
                        💳 Enviar link de pago al confirmar el pedido
                    </div>
                    <div class="text-xs text-slate-600 leading-relaxed">
                        Si está activo, después del resumen del pedido, el bot agrega:
                        <em>"💳 Paga ahora con tarjeta, Nequi o PSE: {link de Wompi}"</em>.
                        El cliente puede pagar online o seguir con pago contra entrega.
                        Si lo desactivas, el bot solo confirma el pedido sin link.
                    </div>
                </div>
            </label>

            @php
                $tenantActual = app(\App\Services\TenantManager::class)->current();
                $tieneWompi = $tenantActual?->tieneWompi() ?? false;
            @endphp

            @if(!$tieneWompi)
                <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800 flex items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                    <div>
                        <strong>Wompi no está configurado para este tenant.</strong>
                        Aunque marques este check, el link no se enviará hasta que el super-admin
                        registre las llaves de Wompi. Pídele que las configure en
                        <em>Admin → Tenants → editar tenant → bloque "Pasarela de pagos · Wompi"</em>.
                    </div>
                </div>
            @else
                <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-xs text-emerald-800 flex items-start gap-2">
                    <i class="fa-solid fa-circle-check mt-0.5"></i>
                    <div>
                        Wompi configurado en modo <strong>{{ ucfirst($tenantActual->wompi_modo ?: 'sandbox') }}</strong>.
                        Los pagos aprobados se ven en
                        <a href="{{ route('pagos.index') }}" class="underline font-bold">/pagos</a>
                        y al cliente le llega un WhatsApp de confirmación automáticamente.
                    </div>
                </div>
            @endif
        </section>

        {{-- ╔═══ DESPACHOS / DOMICILIARIOS ═══╗ --}}
        <section x-show="tab === 'despachos'" x-cloak class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600 text-xl">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800">Asignación de domiciliarios</h3>
                    <p class="text-xs text-slate-500">
                        Decide si los pedidos se asignan automáticamente a un domiciliario
                        o si tu operador los asigna manualmente desde
                        <a href="{{ route('despachos.index') }}" class="text-orange-700 underline">/despachos</a>.
                    </p>
                </div>
            </div>

            {{-- Toggle principal --}}
            <label class="flex items-start gap-3 cursor-pointer rounded-xl border-2 p-4 transition mb-4
                          {{ $auto_asignar_domiciliario ? 'border-orange-300 bg-orange-50/40' : 'border-slate-200 bg-white' }}">
                <input type="checkbox" wire:model.live="auto_asignar_domiciliario"
                       class="mt-1 rounded border-slate-300 text-orange-600 h-5 w-5">
                <div class="flex-1">
                    <div class="text-sm font-bold text-slate-800 mb-1">
                        🛵 Asignar domiciliario automáticamente
                    </div>
                    <div class="text-xs text-slate-600 leading-relaxed">
                        Si está activo, cuando el pedido entra al estado configurado abajo,
                        el sistema selecciona automáticamente al domiciliario más adecuado
                        según el criterio elegido. Tu operador puede sobreescribir la
                        asignación desde /despachos si lo necesita.
                    </div>
                </div>
            </label>

            @if($auto_asignar_domiciliario)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ml-2 pl-4 border-l-2 border-orange-200">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Criterio de asignación</label>
                        <select wire:model="criterio_asignacion"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-orange-400 focus:ring-2 focus:ring-orange-100 bg-white">
                            <option value="balanceado">⚖️ Balanceado por carga (recomendado)</option>
                            <option value="rotacion">🔄 Rotación (round-robin)</option>
                            <option value="cercania">📍 Cercanía al cliente</option>
                        </select>
                        <div class="mt-2 text-[11px] text-slate-500 space-y-1">
                            <p><strong>Balanceado:</strong> al que tenga MENOS pedidos en curso. Reparte la carga equitativamente.</p>
                            <p><strong>Rotación:</strong> al que lleva más tiempo sin recibir pedido. Justo en frecuencia.</p>
                            <p><strong>Cercanía:</strong> al más cercano (requiere coordenadas; si faltan, usa balanceado).</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Cuándo asignar</label>
                        <select wire:model="asignar_en_estado"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-orange-400 focus:ring-2 focus:ring-orange-100 bg-white">
                            <option value="nuevo">Al confirmar el pedido (estado "Nuevo")</option>
                            <option value="en_preparacion">Cuando pasa a "En preparación" (recomendado)</option>
                            <option value="repartidor_en_camino">Cuando pasa a "En camino"</option>
                        </select>
                        <p class="mt-2 text-[11px] text-slate-500">
                            "En preparación" es lo común — para cuando esté listo el pedido,
                            el domiciliario ya está asignado y avisado.
                        </p>
                    </div>
                </div>

                {{-- Info de domiciliarios actuales --}}
                @php
                    $domis = \App\Models\Domiciliario::where('activo', true)->get();
                    $totalDomis = $domis->count();
                    $disponibles = $domis->whereIn('estado', ['disponible', 'en_ruta'])->count();
                @endphp

                <div class="mt-4 rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-800 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info mt-0.5"></i>
                    <div>
                        <strong>Estado actual:</strong>
                        {{ $totalDomis }} domiciliario(s) activo(s),
                        <strong>{{ $disponibles }}</strong> disponible(s) para asignación.
                        @if($disponibles === 0)
                            <span class="text-amber-700 font-bold">⚠️ Sin domiciliarios disponibles los pedidos quedan sin asignar (el operador deberá hacerlo manualmente).</span>
                        @endif
                        Gestiona tu equipo en
                        <a href="{{ route('domiciliarios.index') }}" class="underline font-bold">/domiciliarios</a>.
                    </div>
                </div>
            @else
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600">
                    <i class="fa-solid fa-hand-pointer text-slate-400 mr-2"></i>
                    Asignación <strong>manual</strong> activa. Los pedidos llegan a
                    <a href="{{ route('despachos.index') }}" class="text-brand-secondary underline">/despachos</a>
                    y tu operador escoge a qué domiciliario asignarlos.
                </div>
            @endif
        </section>

        <section x-show="tab === 'cumple'" x-cloak class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-pink-50 text-pink-600 text-xl">
                    <i class="fa-solid fa-cake-candles"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800">Felicitaciones de cumpleaños</h3>
                    <p class="text-xs text-slate-500">El sistema envía un mensaje automático a los clientes cuyo cumpleaños es hoy.</p>
                </div>
            </div>

            <div class="space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="cumpleanos_activo"
                           class="mt-1 rounded border-slate-300 text-pink-500 focus:ring-pink-400">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">Enviar felicitaciones automáticamente</div>
                        <div class="text-xs text-slate-500">Cuando un cliente cumpla años, el bot le envía un mensaje desde WhatsApp.</div>
                    </div>
                </label>

                {{-- Fila 1: cuándo enviar --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Hora de envío</label>
                        <input type="time" wire:model="cumpleanos_hora"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        <p class="text-xs text-slate-500 mt-1">Hora local (Bogotá). Se revisa cada minuto.</p>
                        @error('cumpleanos_hora') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Días de anticipación</label>
                        <select wire:model="cumpleanos_dias_anticipacion"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            <option value="0">El mismo día</option>
                            <option value="1">1 día antes</option>
                            <option value="2">2 días antes</option>
                            <option value="3">3 días antes</option>
                            <option value="7">Una semana antes</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Cuándo enviarle el mensaje relativo a su cumpleaños.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reintentos si falla</label>
                        <select wire:model="cumpleanos_reintentos_max"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            <option value="0">Sin reintentos</option>
                            <option value="1">1 reintento</option>
                            <option value="2">2 reintentos</option>
                            <option value="3">3 reintentos</option>
                            <option value="5">5 reintentos</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Si WhatsApp falla, cuántas veces reintentar.</p>
                    </div>
                </div>

                {{-- Fila 2: ventana horaria --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Ventana permitida — desde
                        </label>
                        <input type="time" wire:model="cumpleanos_ventana_desde"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Ventana permitida — hasta
                        </label>
                        <input type="time" wire:model="cumpleanos_ventana_hasta"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    </div>
                </div>
                <p class="text-xs text-slate-500 -mt-2">
                    <i class="fa-solid fa-shield-halved text-emerald-500 mr-1"></i>
                    Protección anti-madrugada: si por error se configura una hora fuera de esta ventana, el sistema no envía.
                </p>

                {{-- Fila 2a: vigencia del beneficio de envío gratis --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        <i class="fa-solid fa-gift text-pink-500"></i> Vigencia del beneficio (envío gratis)
                    </label>
                    <input type="number" min="1" max="30" wire:model="cumpleanos_dias_vigencia_beneficio"
                           class="w-40 rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                    <span class="text-sm text-slate-600 ml-2">día(s) a partir del envío del mensaje</span>
                    <p class="text-xs text-slate-500 mt-1">
                        Cuando se envía la felicitación, se le otorga al cliente envío gratis por N días. Si pide en ese rango, el sistema aplica el descuento automáticamente.
                    </p>
                </div>

                {{-- Fila 2b: conexión de WhatsApp por defecto --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                        Conexión de WhatsApp por defecto
                    </label>
                    <select wire:model="connection_id_default"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                        <option value="">— Automática (la que haya usado el cliente) —</option>
                        @foreach($conexionesDetectadas as $cid)
                            <option value="{{ $cid }}">WhatsApp #{{ $cid }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500 mt-1">
                        Se usa cuando el cliente nunca ha escrito (no tiene conversación previa). Si el cliente ya tiene conversación, se usa SIEMPRE la misma línea por donde se contactaron antes.
                    </p>
                </div>

                {{-- Fila 3: días de la semana --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Días permitidos de la semana</label>
                    <div class="flex flex-wrap gap-2">
                        @php
                            $nombresDias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
                        @endphp
                        @foreach($nombresDias as $i => $nombre)
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none rounded-xl border-2 px-4 py-2 transition
                                          {{ ($cumpleanos_dias_semana_arr[$i] ?? true) ? 'border-pink-500 bg-pink-50 text-pink-700' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300' }}">
                                <input type="checkbox" wire:model.live="cumpleanos_dias_semana_arr.{{ $i }}" class="hidden">
                                <span class="font-semibold text-sm">{{ $nombre }}</span>
                                @if($cumpleanos_dias_semana_arr[$i] ?? true)
                                    <i class="fa-solid fa-check text-pink-500 text-xs"></i>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        Si un cliente cumple años en un día no permitido, el mensaje se posterga al siguiente día habilitado.
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-slate-700">Mensaje de felicitación</label>
                        <button type="button" wire:click="cargarPlantillaCumpleanosDefault"
                                class="text-xs font-semibold text-brand-secondary hover:underline">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Restaurar plantilla
                        </button>
                    </div>
                    <textarea wire:model="cumpleanos_mensaje" rows="8"
                              class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono focus:border-brand focus:ring-brand"></textarea>
                    @error('cumpleanos_mensaje') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    <div class="mt-2 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600">
                        <strong class="text-slate-800">Variables disponibles:</strong>
                        <code class="mx-1 px-1.5 py-0.5 bg-white rounded border border-slate-200">{nombre}</code>
                        primer nombre del cliente,
                        <code class="mx-1 px-1.5 py-0.5 bg-white rounded border border-slate-200">{nombre_completo}</code>
                        nombre completo.
                    </div>
                </div>

                {{-- ── Cumpleañeros de HOY + envío manual ─────────────────── --}}
                <div class="rounded-xl border-2 border-dashed border-pink-200 bg-pink-50/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <div>
                            <h4 class="text-sm font-bold text-slate-800">
                                <i class="fa-solid fa-gift text-pink-500"></i> Cumpleañeros de hoy
                                <span class="ml-2 inline-flex items-center justify-center h-5 min-w-5 rounded-full bg-pink-500 text-white text-[11px] font-bold px-2">
                                    {{ count($this->cumpleanerosHoy) }}
                                </span>
                            </h4>
                            <p class="text-xs text-slate-500">Clientes cuyo cumpleaños es hoy. Puedes enviarles el mensaje manualmente sin esperar al horario programado.</p>
                        </div>

                        @if(count($this->cumpleanerosHoy) > 0)
                            <button type="button"
                                    wire:click="enviarFelicitacionesDeHoy"
                                    wire:confirm="¿Enviar AHORA la felicitación a todos los cumpleañeros de hoy pendientes?"
                                    wire:loading.attr="disabled"
                                    wire:target="enviarFelicitacionesDeHoy,enviarFelicitacionManual"
                                    class="inline-flex items-center gap-2 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold px-4 py-2 transition disabled:opacity-60 disabled:cursor-wait">
                                <span wire:loading.remove wire:target="enviarFelicitacionesDeHoy">
                                    <i class="fa-solid fa-paper-plane mr-1"></i> Enviar a todos ahora
                                </span>
                                <span wire:loading wire:target="enviarFelicitacionesDeHoy">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Enviando…
                                </span>
                            </button>
                        @endif
                    </div>

                    @if(count($this->cumpleanerosHoy) === 0)
                        <div class="text-center py-6 text-sm text-slate-500">
                            🎈 No hay cumpleañeros hoy. Vuelve mañana.
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach($this->cumpleanerosHoy as $c)
                                @php
                                    $yaEnviado = $c->ultima_felicitacion_anio === (int) now()->format('Y');
                                @endphp
                                <div class="flex items-center justify-between gap-3 rounded-lg bg-white border border-pink-100 px-3 py-2">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-slate-800 truncate">{{ $c->nombre }}</div>
                                        <div class="text-xs text-slate-500 font-mono">{{ $c->telefono_normalizado }}</div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($yaEnviado)
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                                ✅ Ya felicitado hoy
                                            </span>
                                        @endif
                                        <button type="button"
                                                wire:click="enviarFelicitacionManual({{ $c->id }})"
                                                wire:confirm="¿Enviar AHORA la felicitación a {{ $c->nombre }}?"
                                                wire:loading.attr="disabled"
                                                wire:target="enviarFelicitacionManual({{ $c->id }})"
                                                class="text-xs font-semibold px-3 py-1.5 rounded-lg
                                                       {{ $yaEnviado ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-pink-500 text-white hover:bg-pink-600' }}
                                                       transition disabled:opacity-60 disabled:cursor-wait">
                                            <span wire:loading.remove wire:target="enviarFelicitacionManual({{ $c->id }})">
                                                <i class="fa-solid fa-paper-plane mr-1"></i>
                                                {{ $yaEnviado ? 'Reenviar' : 'Enviar ahora' }}
                                            </span>
                                            <span wire:loading wire:target="enviarFelicitacionManual({{ $c->id }})">
                                                <i class="fa-solid fa-spinner fa-spin"></i>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 text-center">
                        <a href="{{ route('felicitaciones.index') }}"
                           class="text-xs font-semibold text-brand-secondary hover:underline">
                            Ver historial completo de felicitaciones →
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- BOTÓN GUARDAR (también en el sidebar) --}}
        <div class="flex justify-end pt-4">
            <button type="submit"
                    class="rounded-2xl bg-brand px-8 py-3 text-white font-bold shadow hover:bg-brand-dark transition">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar configuración
            </button>
        </div>

        </div>{{-- /área de contenido --}}
    </form>
</div>
