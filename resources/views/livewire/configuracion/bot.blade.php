<div class="px-6 lg:px-10 py-8 max-w-4xl mx-auto">

    <div class="mb-6">
        <h2 class="text-3xl font-extrabold text-slate-800">Configuración del bot</h2>
        <p class="text-sm text-slate-500">Ajusta el comportamiento de Sofía y la IA.</p>
    </div>

    <form wire:submit.prevent="guardar" class="space-y-6">

        {{-- IDENTIDAD --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
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
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    <p class="text-[11px] text-slate-400 mt-1">El bot se presentará con este nombre.</p>
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                        <div>
                            <div class="text-sm font-medium text-slate-700">Bot activo</div>
                            <div class="text-[11px] text-slate-500">Si lo apagas, no responde a nadie</div>
                        </div>
                        <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-[#d68643] h-5 w-5">
                    </label>
                </div>
            </div>
        </section>

        {{-- ENVÍO DE IMÁGENES --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
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
                           class="mt-1 rounded border-slate-300 text-[#d68643] h-6 w-6">
                </label>

                @if($enviar_imagenes_productos)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ml-2 pl-4 border-l-2 border-purple-200">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Máximo de imágenes por mensaje
                            </label>
                            <input type="number" wire:model="max_imagenes_por_mensaje" min="1" max="10"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                            <p class="text-[11px] text-slate-400 mt-1">Recomendado: 2-3 (no saturar al cliente).</p>
                        </div>

                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                                <div>
                                    <div class="text-sm font-medium text-slate-700">Mostrar destacados al saludar</div>
                                    <div class="text-[11px] text-slate-500">Envía 1-2 fotos al iniciar la charla</div>
                                </div>
                                <input type="checkbox" wire:model="enviar_imagen_destacados" class="rounded border-slate-300 text-[#d68643] h-5 w-5">
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

        {{-- ╔═══ AGRUPACIÓN DE MENSAJES (DEBOUNCE) ═══╗ --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
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
                           class="mt-1 rounded border-slate-300 text-[#d68643] h-6 w-6">
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
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
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
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
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
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                        <span class="text-sm font-medium text-slate-700">Saludar con promos vigentes</span>
                        <input type="checkbox" wire:model="saludar_con_promociones" class="rounded border-slate-300 text-[#d68643] h-5 w-5">
                    </label>
                </div>
            </div>
        </section>

        {{-- INFO EMPRESA --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
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
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono leading-relaxed focus:border-[#d68643] focus:ring-[#d68643]"></textarea>
            <p class="text-[11px] text-slate-400 mt-1">
                Describe brevemente: nombre, ubicación, años de experiencia, valores, servicios. La IA usará esto cuando el cliente pregunte por la empresa.
            </p>
        </section>

        {{-- BIENVENIDA --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-message"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Frase personalizada de bienvenida (opcional)</h3>
            </div>

            <textarea wire:model="frase_bienvenida" rows="2"
                      placeholder="Ej: ¡Hola! Bienvenido a La Hacienda 🥩 ¿qué te provoca hoy?"
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]"></textarea>
            <p class="text-[11px] text-slate-400 mt-1">Si la dejas vacía, la IA improvisa el saludo según la hora y el cliente.</p>
        </section>

        {{-- ╔═══ EDITOR DE PROMPT PERSONALIZADO ═══╗ --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
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
                       class="mt-1 rounded border-slate-300 text-[#d68643] h-6 w-6">
            </label>

            @if($usar_prompt_personalizado)
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {{-- Editor textarea --}}
                    <div class="lg:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-slate-700">
                                <i class="fa-solid fa-pen-to-square text-[#d68643] mr-1"></i>
                                Tu prompt
                            </label>
                            <button type="button"
                                    @click.prevent="$dispatch('confirm-show', {
                                        title: 'Cargar plantilla por defecto',
                                        message: 'Reemplazará el contenido actual del editor. ¿Seguro?',
                                        confirmText: 'Sí, reemplazar',
                                        type: 'primary',
                                        onConfirm: () => $wire.cargarPlantillaPorDefecto(),
                                    })"
                                    class="text-[11px] font-semibold text-[#d68643] hover:underline">
                                <i class="fa-solid fa-rotate-left mr-1"></i> Cargar plantilla por defecto
                            </button>
                        </div>

                        <textarea wire:model="system_prompt" rows="22"
                                  placeholder="Escribe tu prompt aquí, usando {variables} del panel derecho..."
                                  class="w-full rounded-xl border border-slate-200 px-4 py-3 text-xs font-mono leading-relaxed focus:border-rose-400 focus:ring-2 focus:ring-rose-100"
                                  spellcheck="false"></textarea>

                        @error('system_prompt')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror

                        <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-3">
                            <span>{{ strlen($system_prompt) }} / 20.000 caracteres</span>
                            <span>·</span>
                            <span>~{{ ceil(strlen($system_prompt) / 4) }} tokens estimados</span>
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
        <section class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-pink-50 text-pink-600 text-xl">
                    🎂
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
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        <p class="text-xs text-slate-500 mt-1">Hora local (Bogotá). Se revisa cada minuto.</p>
                        @error('cumpleanos_hora') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Días de anticipación</label>
                        <select wire:model="cumpleanos_dias_anticipacion"
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                            <option value="0">El mismo día 🎂</option>
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
                                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
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
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Ventana permitida — hasta
                        </label>
                        <input type="time" wire:model="cumpleanos_ventana_hasta"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    </div>
                </div>
                <p class="text-xs text-slate-500 -mt-2">
                    <i class="fa-solid fa-shield-halved text-emerald-500 mr-1"></i>
                    Protección anti-madrugada: si por error se configura una hora fuera de esta ventana, el sistema no envía.
                </p>

                {{-- Fila 2b: conexión de WhatsApp por defecto --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                        Conexión de WhatsApp por defecto
                    </label>
                    <select wire:model="connection_id_default"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
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
                                class="text-xs font-semibold text-[#a85f24] hover:underline">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Restaurar plantilla
                        </button>
                    </div>
                    <textarea wire:model="cumpleanos_mensaje" rows="8"
                              class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono focus:border-[#d68643] focus:ring-[#d68643]"></textarea>
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
                                🎁 Cumpleañeros de hoy
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
                           class="text-xs font-semibold text-[#a85f24] hover:underline">
                            Ver historial completo de felicitaciones →
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- BOTÓN GUARDAR --}}
        <div class="flex justify-end pt-4">
            <button type="submit"
                    class="rounded-2xl bg-[#d68643] px-8 py-3 text-white font-bold shadow hover:bg-[#c97a36] transition">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar configuración
            </button>
        </div>
    </form>
</div>
