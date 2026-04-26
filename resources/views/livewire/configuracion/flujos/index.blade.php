<div class="px-4 lg:px-8 py-6">

    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Flujos de conversación</h2>
            <p class="text-sm text-slate-500">Diseña cómo el bot encadena departamentos, condiciones y acciones de forma visual.</p>
        </div>
        <button wire:click="nuevo"
                class="inline-flex items-center gap-2 rounded-2xl bg-brand hover:bg-brand-dark text-white font-bold px-5 py-2.5 shadow transition">
            <i class="fa-solid fa-plus"></i> Nuevo flujo
        </button>
    </div>

    {{-- LISTA DE FLUJOS --}}
    @if($flujos->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-10 text-center">
            <i class="fa-solid fa-diagram-project text-5xl text-slate-300 mb-4"></i>
            <h3 class="text-lg font-bold text-slate-700 mb-1">Aún no tienes flujos</h3>
            <p class="text-sm text-slate-500 mb-4">Crea tu primer flujo para enrutar conversaciones automáticamente.</p>
            <button wire:click="nuevo" class="inline-flex items-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold px-5 py-2.5">
                <i class="fa-solid fa-plus"></i> Crear primer flujo
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($flujos as $f)
                @php
                    $totalNodos = is_array($f->grafo['drawflow']['Home']['data'] ?? null)
                        ? count($f->grafo['drawflow']['Home']['data'])
                        : 0;
                @endphp
                <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-slate-800 truncate">{{ $f->nombre }}</h3>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $f->activo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $f->activo ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                                @if($f->prioridad > 0)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                        Prioridad {{ $f->prioridad }}
                                    </span>
                                @endif
                            </div>
                            @if($f->descripcion)
                                <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $f->descripcion }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-slate-500 mb-4">
                        <span><i class="fa-solid fa-circle-nodes text-slate-400 mr-1"></i> {{ $totalNodos }} nodo(s)</span>
                        <span>·</span>
                        <span>{{ $f->updated_at?->diffForHumans() }}</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button wire:click="editar({{ $f->id }})"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-xs font-bold px-3 py-2 transition">
                            <i class="fa-solid fa-pen-to-square"></i> Editar
                        </button>
                        <button wire:click="toggleActivo({{ $f->id }})"
                                title="{{ $f->activo ? 'Desactivar' : 'Activar' }}"
                                class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-2 transition">
                            <i class="fa-solid {{ $f->activo ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                        </button>
                        <button wire:click="duplicar({{ $f->id }})"
                                title="Duplicar"
                                class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-2 transition">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                        <button wire:click="eliminar({{ $f->id }})"
                                wire:confirm="¿Eliminar el flujo '{{ $f->nombre }}'?"
                                title="Eliminar"
                                class="rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold px-3 py-2 transition">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ╔═══ MODAL EDITOR ═══╗ --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm overflow-hidden"
             wire:click.self="cerrarModal">
            <div class="absolute inset-2 lg:inset-4 bg-white rounded-2xl shadow-2xl flex flex-col" @click.stop>

                {{-- Header del modal --}}
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3 bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                            <i class="fa-solid fa-diagram-project"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <input type="text" wire:model.lazy="nombre"
                                   placeholder="Nombre del flujo"
                                   class="w-full bg-transparent border-0 text-lg font-bold text-slate-800 focus:outline-none focus:ring-0 px-0 truncate">
                            <input type="text" wire:model.lazy="descripcion"
                                   placeholder="Descripción breve (opcional)"
                                   class="w-full bg-transparent border-0 text-xs text-slate-500 focus:outline-none focus:ring-0 px-0 truncate">
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <label class="inline-flex items-center gap-2 cursor-pointer rounded-lg bg-slate-100 px-3 py-1.5">
                            <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-emerald-500">
                            <span class="text-xs font-bold text-slate-700">Activo</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5">
                            <span class="text-xs font-bold text-slate-700">Prioridad</span>
                            <input type="number" wire:model="prioridad" min="0" max="1000"
                                   class="w-16 bg-white rounded border border-slate-200 px-2 py-0.5 text-xs">
                        </label>
                        <button type="button" onclick="window.guardarFlujo()"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-xs font-bold px-4 py-2 shadow transition">
                            <i class="fa-solid fa-floppy-disk"></i> Guardar
                        </button>
                        <button wire:click="cerrarModal"
                                class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 transition">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                {{-- Cuerpo del modal: toolbox + canvas --}}
                <div class="flex-1 flex min-h-0">

                    {{-- Toolbox --}}
                    <aside class="w-56 flex-shrink-0 border-r border-slate-200 bg-slate-50 p-3 overflow-y-auto">
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-2 px-1 tracking-wider">Arrastra al canvas</p>

                        @php
                            $bloques = [
                                ['Inicio', 'trigger', 'fa-play', 'bg-emerald-100 text-emerald-700', 'Punto de entrada'],
                                ['Si contiene…', 'cond_palabras', 'fa-keyboard', 'bg-cyan-100 text-cyan-700', 'Detecta palabras clave'],
                                ['Intención IA', 'cond_intencion', 'fa-brain', 'bg-violet-100 text-violet-700', 'IA analiza intención'],
                                ['Horario sede', 'cond_horario', 'fa-clock', 'bg-amber-100 text-amber-700', 'Sede abierta o cerrada'],
                                ['Cliente nuevo', 'cond_cliente', 'fa-user-plus', 'bg-blue-100 text-blue-700', 'Primera vez vs recurrente'],
                                ['Derivar', 'accion_derivar', 'fa-headset', 'bg-rose-100 text-rose-700', 'Pasa a un departamento'],
                                ['Mensaje', 'accion_mensaje', 'fa-comment', 'bg-pink-100 text-pink-700', 'Responde texto fijo'],
                                ['Etiquetar', 'accion_etiquetar', 'fa-tag', 'bg-slate-200 text-slate-700', 'Marca la conversación'],
                                ['Esperar', 'accion_esperar', 'fa-hourglass-half', 'bg-orange-100 text-orange-700', 'Espera N min sin respuesta'],
                                ['Fin', 'fin', 'fa-flag-checkered', 'bg-slate-700 text-white', 'Termina el flujo'],
                            ];
                        @endphp

                        @foreach($bloques as [$label, $tipo, $icon, $color, $desc])
                            <div class="drag-node group flex items-center gap-2 rounded-xl bg-white border border-slate-200 hover:border-purple-300 hover:shadow-sm px-2.5 py-2 mb-1.5 cursor-grab active:cursor-grabbing transition"
                                 draggable="true"
                                 data-tipo="{{ $tipo }}"
                                 data-label="{{ $label }}">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $color }} flex-shrink-0">
                                    <i class="fa-solid {{ $icon }} text-xs"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold text-slate-800 truncate">{{ $label }}</div>
                                    <div class="text-[10px] text-slate-500 truncate">{{ $desc }}</div>
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-4 rounded-xl bg-blue-50 border border-blue-100 p-3 text-[11px] text-blue-800">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            <strong>Tip:</strong> conecta los puntos de salida (derecha) de un bloque con el de entrada (izquierda) del siguiente. Las condiciones tienen 2 salidas: arriba = SÍ, abajo = NO.
                        </div>
                    </aside>

                    {{-- Canvas --}}
                    <div class="flex-1 relative bg-[radial-gradient(circle,#e2e8f0_1px,transparent_1px)] [background-size:18px_18px] overflow-hidden">
                        <div id="drawflow" class="w-full h-full"></div>

                        <div class="absolute bottom-3 right-3 flex flex-col gap-1.5">
                            <button onclick="window.flujoEditor.zoom_in()" title="Acercar"
                                    class="h-9 w-9 rounded-lg bg-white shadow border border-slate-200 hover:bg-slate-50 text-slate-700">
                                <i class="fa-solid fa-magnifying-glass-plus text-xs"></i>
                            </button>
                            <button onclick="window.flujoEditor.zoom_out()" title="Alejar"
                                    class="h-9 w-9 rounded-lg bg-white shadow border border-slate-200 hover:bg-slate-50 text-slate-700">
                                <i class="fa-solid fa-magnifying-glass-minus text-xs"></i>
                            </button>
                            <button onclick="window.flujoEditor.zoom_reset()" title="Resetear"
                                    class="h-9 w-9 rounded-lg bg-white shadow border border-slate-200 hover:bg-slate-50 text-slate-700">
                                <i class="fa-solid fa-expand text-xs"></i>
                            </button>
                        </div>

                        <div class="absolute bottom-3 left-3 px-3 py-1.5 rounded-full bg-white shadow border border-slate-200 text-[10px] text-slate-500">
                            <i class="fa-solid fa-circle text-emerald-400 text-[6px] mr-1"></i>
                            Doble click sobre un nodo para configurarlo
                        </div>
                    </div>

                    {{-- Inspector de nodo seleccionado --}}
                    <aside class="w-72 flex-shrink-0 border-l border-slate-200 bg-white p-4 overflow-y-auto" id="flujo-inspector">
                        <p class="text-[10px] uppercase font-bold text-slate-500 mb-2 tracking-wider">Configuración del nodo</p>
                        <div id="inspector-content" class="text-xs text-slate-500 italic">
                            <i class="fa-solid fa-arrow-pointer mr-1"></i>
                            Haz click en un nodo del canvas para ver y editar sus propiedades aquí.
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        {{-- ╔═══ Drawflow CDN ═══╗ --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.css">
        <script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.js"></script>

        <style>
            #drawflow .drawflow-node {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                box-shadow: 0 2px 6px rgba(0,0,0,.04);
                padding: 0;
                min-width: 200px;
            }
            #drawflow .drawflow-node.selected {
                border-color: #8b5cf6;
                box-shadow: 0 0 0 3px rgba(139,92,246,.15);
            }
            .nodo-header {
                display: flex; align-items: center; gap: 8px;
                padding: 8px 10px; border-bottom: 1px solid #f1f5f9;
                font-weight: 700; font-size: 12px; color: #0f172a;
            }
            .nodo-icon {
                display: inline-flex; align-items: center; justify-content: center;
                width: 24px; height: 24px; border-radius: 6px; font-size: 11px;
            }
            .nodo-body {
                padding: 8px 10px; font-size: 11px; color: #64748b;
            }
            #drawflow .connection .main-path { stroke: #94a3b8; stroke-width: 2; }
            #drawflow .connection .main-path:hover { stroke: #8b5cf6; }
            #drawflow .input, #drawflow .output {
                background: #cbd5e1; border-color: #cbd5e1; height: 14px; width: 14px;
            }
            #drawflow .output:hover, #drawflow .input:hover { background: #8b5cf6; }
        </style>

        <script>
            (function() {
                if (window.__flujoEditorInited) return;
                window.__flujoEditorInited = true;

                const departamentos = @json($departamentos->map(fn($d)=>['id'=>$d->id,'nombre'=>$d->nombre])->all());

                const TIPOS = {
                    trigger:          { color: '#10b981', icon: 'fa-play',           bg:'#d1fae5', label:'Inicio',       in:0, out:1 },
                    cond_palabras:    { color: '#06b6d4', icon: 'fa-keyboard',       bg:'#cffafe', label:'Si contiene',  in:1, out:2 },
                    cond_intencion:   { color: '#8b5cf6', icon: 'fa-brain',          bg:'#ede9fe', label:'Intención IA', in:1, out:2 },
                    cond_horario:     { color: '#f59e0b', icon: 'fa-clock',          bg:'#fef3c7', label:'Horario sede', in:1, out:2 },
                    cond_cliente:     { color: '#3b82f6', icon: 'fa-user-plus',      bg:'#dbeafe', label:'Cliente nuevo',in:1, out:2 },
                    accion_derivar:   { color: '#f43f5e', icon: 'fa-headset',        bg:'#ffe4e6', label:'Derivar',      in:1, out:1 },
                    accion_mensaje:   { color: '#ec4899', icon: 'fa-comment',        bg:'#fce7f3', label:'Mensaje',      in:1, out:1 },
                    accion_etiquetar: { color: '#475569', icon: 'fa-tag',            bg:'#e2e8f0', label:'Etiquetar',    in:1, out:1 },
                    accion_esperar:   { color: '#ea580c', icon: 'fa-hourglass-half', bg:'#ffedd5', label:'Esperar',      in:1, out:1 },
                    fin:              { color: '#1e293b', icon: 'fa-flag-checkered', bg:'#cbd5e1', label:'Fin',          in:1, out:0 },
                };

                let editor = null;
                let nodoSeleccionado = null;

                function htmlNodo(tipo, label, data) {
                    const T = TIPOS[tipo] || TIPOS.fin;
                    const realLabel = data?.label || label || T.label;
                    const sub = subtituloNodo(tipo, data);

                    return `
                        <div>
                            <div class="nodo-header">
                                <span class="nodo-icon" style="background:${T.bg};color:${T.color}">
                                    <i class="fa-solid ${T.icon}"></i>
                                </span>
                                <span>${escape(realLabel)}</span>
                            </div>
                            <div class="nodo-body">${escape(sub)}</div>
                        </div>
                    `;
                }

                function subtituloNodo(tipo, data) {
                    if (!data) return '—';
                    switch (tipo) {
                        case 'cond_palabras':    return data.palabras ? `Palabras: ${data.palabras}` : 'Define palabras…';
                        case 'cond_intencion':   return data.intencion ? `Intención: ${data.intencion}` : 'Define intención…';
                        case 'cond_horario':     return data.estado || 'abierta';
                        case 'cond_cliente':     return data.tipo === 'recurrente' ? 'Si es recurrente' : 'Si es nuevo';
                        case 'accion_derivar': {
                            const d = departamentos.find(x => x.id === Number(data.departamento_id));
                            return d ? `→ ${d.nombre}` : 'Elige departamento…';
                        }
                        case 'accion_mensaje':   return data.mensaje ? `"${data.mensaje.slice(0,40)}…"` : 'Define mensaje…';
                        case 'accion_etiquetar': return data.etiqueta ? `#${data.etiqueta}` : 'Define etiqueta…';
                        case 'accion_esperar':   return data.minutos ? `Esperar ${data.minutos} min` : 'Define tiempo…';
                        default: return data.descripcion || '';
                    }
                }

                function escape(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

                function addNodo(tipo, x, y, dataInicial = {}) {
                    const T = TIPOS[tipo] || TIPOS.fin;
                    const data = { tipo, label: T.label, ...dataInicial };
                    const html = htmlNodo(tipo, T.label, data);
                    const id = editor.addNode(tipo, T.in, T.out, x, y, tipo, data, html);
                    return id;
                }

                function refrescarNodo(id) {
                    const n = editor.getNodeFromId(id);
                    if (!n) return;
                    const T = TIPOS[n.data.tipo] || TIPOS.fin;
                    const html = htmlNodo(n.data.tipo, n.data.label, n.data);
                    editor.updateNodeDataFromId(id, n.data);
                    document.querySelector(`#node-${id} .drawflow_content_node`).innerHTML = html;
                }

                function renderInspector(nodeId) {
                    const cont = document.getElementById('inspector-content');
                    if (!nodeId) {
                        cont.innerHTML = `<div class="text-xs text-slate-500 italic"><i class="fa-solid fa-arrow-pointer mr-1"></i>Haz click en un nodo para configurarlo.</div>`;
                        return;
                    }
                    const n = editor.getNodeFromId(nodeId);
                    if (!n) return;
                    const tipo = n.data.tipo;
                    const data = n.data;

                    let html = `
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                                <span class="nodo-icon" style="background:${TIPOS[tipo]?.bg};color:${TIPOS[tipo]?.color}">
                                    <i class="fa-solid ${TIPOS[tipo]?.icon}"></i>
                                </span>
                                <span class="text-sm font-bold text-slate-800">${TIPOS[tipo]?.label || tipo}</span>
                                <button onclick="window.borrarNodo(${nodeId})" class="ml-auto text-rose-500 hover:text-rose-700" title="Eliminar nodo">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Etiqueta</label>
                                <input type="text" value="${escape(data.label || '')}" data-key="label"
                                       class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                            </div>
                    `;

                    switch (tipo) {
                        case 'cond_palabras':
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Palabras (separadas por coma)</label>
                                    <textarea data-key="palabras" rows="3" placeholder="hoja de vida, empleo, vacante"
                                              class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">${escape(data.palabras || '')}</textarea>
                                    <p class="text-[10px] text-slate-400 mt-1">Si el mensaje del cliente contiene CUALQUIERA, sale por SÍ.</p>
                                </div>
                                <div>
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" data-key="case_sensitive" ${data.case_sensitive ? 'checked' : ''}>
                                        <span class="text-xs">Distinguir mayúsculas/minúsculas</span>
                                    </label>
                                </div>
                            `;
                            break;
                        case 'cond_intencion':
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Intención a detectar</label>
                                    <input type="text" value="${escape(data.intencion || '')}" data-key="intencion"
                                           placeholder="cliente molesto, quiere cotizar mayorista..."
                                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                    <p class="text-[10px] text-slate-400 mt-1">La IA evalúa si el mensaje encaja con esta intención (sí/no).</p>
                                </div>
                            `;
                            break;
                        case 'cond_horario':
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Sale por SÍ cuando la sede está…</label>
                                    <select data-key="estado" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                        <option value="abierta" ${data.estado === 'abierta' ? 'selected' : ''}>ABIERTA</option>
                                        <option value="cerrada" ${data.estado === 'cerrada' ? 'selected' : ''}>CERRADA</option>
                                    </select>
                                </div>
                            `;
                            break;
                        case 'cond_cliente':
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Sale por SÍ si el cliente es…</label>
                                    <select data-key="tipo" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                        <option value="nuevo" ${data.tipo !== 'recurrente' ? 'selected' : ''}>NUEVO (primera vez)</option>
                                        <option value="recurrente" ${data.tipo === 'recurrente' ? 'selected' : ''}>RECURRENTE (ya compró)</option>
                                    </select>
                                </div>
                            `;
                            break;
                        case 'accion_derivar': {
                            const opts = departamentos.map(d => `<option value="${d.id}" ${Number(data.departamento_id) === d.id ? 'selected' : ''}>${escape(d.nombre)}</option>`).join('');
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Departamento</label>
                                    <select data-key="departamento_id" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                        <option value="">— Elige —</option>
                                        ${opts}
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Razón (registrada en el log)</label>
                                    <input type="text" value="${escape(data.razon || '')}" data-key="razon"
                                           placeholder="Detectado por flujo X"
                                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                </div>
                            `;
                            break;
                        }
                        case 'accion_mensaje':
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Mensaje a enviar</label>
                                    <textarea data-key="mensaje" rows="5"
                                              placeholder="Hola {nombre}, ¿en qué te puedo ayudar?"
                                              class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">${escape(data.mensaje || '')}</textarea>
                                    <p class="text-[10px] text-slate-400 mt-1">Variables: {nombre}, {sede}, {hora}, {fecha}.</p>
                                </div>
                            `;
                            break;
                        case 'accion_etiquetar':
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Etiqueta</label>
                                    <input type="text" value="${escape(data.etiqueta || '')}" data-key="etiqueta"
                                           placeholder="cotizacion-mayorista"
                                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                </div>
                            `;
                            break;
                        case 'accion_esperar':
                            html += `
                                <div>
                                    <label class="block text-[10px] uppercase font-bold text-slate-500 mb-1">Esperar (minutos sin respuesta del cliente)</label>
                                    <input type="number" min="1" max="1440" value="${Number(data.minutos) || 5}" data-key="minutos"
                                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                    <p class="text-[10px] text-slate-400 mt-1">Si pasa este tiempo sin que el cliente conteste, ejecuta el siguiente nodo (escalar, mensaje recordatorio, etc.).</p>
                                </div>
                            `;
                            break;
                    }
                    html += `</div>`;
                    cont.innerHTML = html;

                    // Bind inputs
                    cont.querySelectorAll('[data-key]').forEach(el => {
                        el.addEventListener('input', () => {
                            const key = el.dataset.key;
                            const val = el.type === 'checkbox' ? el.checked : el.value;
                            const node = editor.getNodeFromId(nodeId);
                            node.data[key] = val;
                            editor.updateNodeDataFromId(nodeId, node.data);
                            refrescarNodo(nodeId);
                        });
                    });
                }

                function init() {
                    if (typeof Drawflow === 'undefined') {
                        setTimeout(init, 100);
                        return;
                    }
                    const container = document.getElementById('drawflow');
                    if (!container) return;

                    editor = new Drawflow(container);
                    editor.reroute = true;
                    editor.start();
                    window.flujoEditor = editor;

                    // Drag-and-drop desde toolbox
                    document.querySelectorAll('.drag-node').forEach(el => {
                        el.addEventListener('dragstart', e => {
                            e.dataTransfer.setData('tipo', el.dataset.tipo);
                        });
                    });
                    container.addEventListener('dragover', e => e.preventDefault());
                    container.addEventListener('drop', e => {
                        e.preventDefault();
                        const tipo = e.dataTransfer.getData('tipo');
                        if (!tipo) return;
                        const rect = container.getBoundingClientRect();
                        const zoom = editor.zoom;
                        const x = (e.clientX - rect.left) / zoom - editor.canvas_x / zoom;
                        const y = (e.clientY - rect.top) / zoom - editor.canvas_y / zoom;
                        addNodo(tipo, x, y);
                    });

                    // Click sobre nodo → inspector
                    editor.on('nodeSelected', id => {
                        nodoSeleccionado = id;
                        renderInspector(id);
                    });
                    editor.on('nodeUnselected', () => {
                        nodoSeleccionado = null;
                        renderInspector(null);
                    });
                    editor.on('nodeRemoved', () => {
                        nodoSeleccionado = null;
                        renderInspector(null);
                    });

                    cargarGrafo(@json($grafo));
                }

                function cargarGrafo(grafo) {
                    if (!editor) return;
                    if (grafo && grafo.drawflow && Object.keys(grafo.drawflow.Home?.data || {}).length > 0) {
                        editor.import(grafo);
                        // Re-render HTML de cada nodo (porque al importar se pierden los handlers visuales)
                        Object.keys(grafo.drawflow.Home.data).forEach(id => refrescarNodo(Number(id)));
                    } else {
                        editor.clear();
                        // Insertar nodo de inicio por defecto
                        addNodo('trigger', 60, 60);
                    }
                }

                window.borrarNodo = function(id) {
                    if (confirm('¿Eliminar este nodo?')) {
                        editor.removeNodeId('node-' + id);
                    }
                };

                window.guardarFlujo = function() {
                    if (!editor) return;
                    const grafo = editor.export();
                    @this.call('guardar', grafo);
                };

                document.addEventListener('livewire:initialized', () => {
                    init();
                    Livewire.on('flujo-cargado', payload => {
                        const grafo = payload.grafo || (payload[0] && payload[0].grafo);
                        cargarGrafo(grafo);
                    });
                });

                // Si ya está livewire inicializado al insertar el modal
                if (window.Livewire) init();
            })();
        </script>
    @endif
</div>
