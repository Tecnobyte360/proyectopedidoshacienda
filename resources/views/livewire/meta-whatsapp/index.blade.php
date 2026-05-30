<div class="p-6 space-y-5">
    {{-- Header --}}
    <div class="flex items-center gap-3 flex-wrap">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white"
             style="background: linear-gradient(135deg, #25D366, #128C7E);">
            <i class="fa-brands fa-whatsapp text-2xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-bold text-slate-800">WhatsApp · Cloud API (Meta)</h1>
            <p class="text-xs text-slate-500">Configura credenciales, plantillas y disparadores desde aquí.</p>
        </div>
        <x-tenant-view-selector />
    </div>

    {{-- Tabs --}}
    @php
        $tabs = [
            'configuracion' => ['icon' => 'fa-key',          'label' => 'Configuración'],
            'plantillas'    => ['icon' => 'fa-file-lines',   'label' => 'Plantillas'],
            'disparadores'  => ['icon' => 'fa-bolt',         'label' => 'Disparadores'],
            'envio_prueba'  => ['icon' => 'fa-paper-plane',  'label' => 'Envío prueba'],
            'historial'     => ['icon' => 'fa-clock-rotate-left', 'label' => 'Historial'],
        ];
    @endphp
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        @foreach($tabs as $key => $t)
            <button wire:click="setTab('{{ $key }}')"
                    class="flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition
                        {{ $tab === $key
                            ? 'bg-emerald-600 text-white shadow'
                            : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                <i class="fa-solid {{ $t['icon'] }}"></i> {{ $t['label'] }}
            </button>
        @endforeach
    </div>

    {{-- ── TAB CONFIGURACIÓN ───────────────────────────────── --}}
    @if($tab === 'configuracion')
        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                <i class="fa-solid fa-key text-emerald-600"></i> Credenciales de Meta
            </h2>

            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-900">
                <p><strong>Webhook URL para Meta:</strong> <code class="bg-white px-1 rounded">{{ $webhookUrl }}</code></p>
                <p><strong>Verify token:</strong> el valor que pongas en <em>Webhook secret</em>.</p>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Token de acceso (Bearer)</label>
                <input type="password" wire:model="access_token"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                @error('access_token')<p class="text-rose-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Phone Number ID</label>
                    <input type="text" wire:model="phone_number_id"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                    @error('phone_number_id')<p class="text-rose-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">WABA ID (opcional)</label>
                    <input type="text" wire:model="waba_id"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">API Version</label>
                    <input type="text" wire:model="api_version"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Idioma por defecto</label>
                    <input type="text" wire:model="default_lang"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Código país default</label>
                    <input type="text" wire:model="codigo_pais"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Webhook secret (verify_token)</label>
                    <input type="text" wire:model="verify_token"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                </div>
            </div>

            <div class="grid grid-cols-[1fr_auto] gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">App secret (firma webhook)</label>
                    <input type="password" wire:model="app_secret"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer pb-2">
                    <input type="checkbox" wire:model="activo" class="rounded border-slate-300">
                    <span class="text-sm text-slate-700">Integración activa</span>
                </label>
            </div>

            <div class="flex gap-2 pt-2">
                <button wire:click="guardarConfig"
                        class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-5 py-2 text-sm font-bold text-white shadow">
                    <i class="fa-solid fa-save mr-1"></i> Guardar
                </button>
                <button wire:click="setTab('envio_prueba')"
                        class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-5 py-2 text-sm font-semibold text-slate-700">
                    <i class="fa-solid fa-bolt mr-1"></i> Probar
                </button>
            </div>
        </div>
    @endif

    {{-- ── TAB PLANTILLAS ──────────────────────────────────── --}}
    @if($tab === 'plantillas')
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-700">Plantillas (templates)</h2>
                    <p class="text-[11px] text-slate-500">Aprobadas en Meta. Usadas para mensajes fuera de la ventana de 24h.</p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="sincronizarPlantillas"
                            class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700">
                        <i class="fa-solid fa-rotate mr-1"></i> Sincronizar con Meta
                    </button>
                    <button wire:click="abrirPlantillaCrear"
                            class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow">
                        <i class="fa-solid fa-plus mr-1"></i> Nueva plantilla
                    </button>
                </div>
            </div>

            @if($plantillas->isEmpty())
                <div class="p-8 text-center text-slate-500 text-sm">
                    Aún no hay plantillas. Sincroniza desde Meta o crea una manualmente.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Nombre</th>
                            <th class="px-4 py-2 text-left">Idioma</th>
                            <th class="px-4 py-2 text-left">Categoría</th>
                            <th class="px-4 py-2 text-left">Estado</th>
                            <th class="px-4 py-2 text-center">Vars</th>
                            <th class="px-4 py-2 text-center">Activa</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($plantillas as $p)
                            <tr class="border-t border-slate-100 hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ $p->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $p->idioma }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $p->categoria }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    @php $colors = ['aprobada' => 'emerald', 'rechazada' => 'rose', 'pendiente' => 'amber', 'borrador' => 'slate'][$p->estado] ?? 'slate'; @endphp
                                    <span class="px-2 py-0.5 rounded bg-{{ $colors }}-100 text-{{ $colors }}-700">{{ $p->estado }}</span>
                                </td>
                                <td class="px-4 py-2 text-center text-xs">{{ $p->num_variables }}</td>
                                <td class="px-4 py-2 text-center">
                                    <button wire:click="toggleActivaPlantilla({{ $p->id }})"
                                            class="px-2 py-0.5 text-xs rounded-full font-bold
                                                {{ $p->activa ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500' }}">
                                        {{ $p->activa ? 'SI' : 'NO' }}
                                    </button>
                                </td>
                                <td class="px-4 py-2 text-right space-x-1">
                                    <button wire:click="abrirPlantillaEditar({{ $p->id }})" class="text-slate-500 hover:text-emerald-600" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button wire:click="eliminarPlantilla({{ $p->id }})" wire:confirm="¿Eliminar plantilla?" class="text-slate-500 hover:text-rose-600" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @if($p->body_preview)
                                <tr class="border-t border-slate-50 bg-slate-50/40">
                                    <td colspan="7" class="px-4 py-2 text-xs text-slate-500 whitespace-pre-wrap">{{ $p->body_preview }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- ── TAB DISPARADORES ────────────────────────────────── --}}
    @if($tab === 'disparadores')
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-700">Disparadores</h2>
                    <p class="text-[11px] text-slate-500">Vincula un evento del sistema (ej: pedido_entregado) con una plantilla.</p>
                </div>
                <button wire:click="abrirDisparadorCrear"
                        class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow">
                    <i class="fa-solid fa-plus mr-1"></i> Nuevo disparador
                </button>
            </div>

            @if($disparadores->isEmpty())
                <div class="p-8 text-center text-slate-500 text-sm">
                    Sin disparadores configurados.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Evento</th>
                            <th class="px-4 py-2 text-left">Plantilla</th>
                            <th class="px-4 py-2 text-left">Variables map</th>
                            <th class="px-4 py-2 text-center">Activo</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($disparadores as $d)
                            <tr class="border-t border-slate-100 hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ $d->evento }}</td>
                                <td class="px-4 py-2 text-xs">
                                    {{ $d->plantilla?->nombre ?? '—' }}
                                    <span class="text-slate-400">({{ $d->plantilla?->idioma ?? '?' }})</span>
                                </td>
                                <td class="px-4 py-2 text-[10px] text-slate-500 font-mono">
                                    {{ $d->variables_map ? json_encode($d->variables_map, JSON_UNESCAPED_UNICODE) : '—' }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button wire:click="toggleActivoDisparador({{ $d->id }})"
                                            class="px-2 py-0.5 text-xs rounded-full font-bold
                                                {{ $d->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500' }}">
                                        {{ $d->activo ? 'SI' : 'NO' }}
                                    </button>
                                </td>
                                <td class="px-4 py-2 text-right space-x-1">
                                    <button wire:click="abrirDisparadorEditar({{ $d->id }})" class="text-slate-500 hover:text-emerald-600">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button wire:click="eliminarDisparador({{ $d->id }})" wire:confirm="¿Eliminar disparador?" class="text-slate-500 hover:text-rose-600">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- ── TAB ENVÍO PRUEBA ────────────────────────────────── --}}
    @if($tab === 'envio_prueba')
        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <h2 class="text-sm font-bold text-slate-700"><i class="fa-solid fa-paper-plane text-emerald-600 mr-1"></i> Envío de prueba</h2>

            <div class="flex gap-2">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="prueba_modo" value="texto" class="text-emerald-600">
                    <span class="text-sm">Texto libre</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer ml-4">
                    <input type="radio" wire:model.live="prueba_modo" value="plantilla" class="text-emerald-600">
                    <span class="text-sm">Plantilla aprobada</span>
                </label>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Teléfono (E.164 sin +)</label>
                <input type="text" wire:model="prueba_telefono" placeholder="573216499744"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
            </div>

            @if($prueba_modo === 'texto')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mensaje</label>
                    <textarea wire:model="prueba_mensaje" rows="3" placeholder="Hola, este es un mensaje de prueba…"
                              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                    <p class="text-[10px] text-slate-400 mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Solo funciona si el cliente escribió en las últimas 24h (Meta lo exige).</p>
                </div>
            @else
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Plantilla</label>
                    <select wire:model.live="prueba_plantilla_id"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="">— Selecciona —</option>
                        @foreach($plantillas->where('activa', true) as $p)
                            <option value="{{ $p->id }}">{{ $p->nombre }} ({{ $p->idioma }})</option>
                        @endforeach
                    </select>
                </div>

                @if($prueba_plantilla_id && count($prueba_variables) > 0)
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-slate-700">Variables ({{ count($prueba_variables) }})</label>
                        @foreach($prueba_variables as $idx => $valor)
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono text-slate-500 w-12"><?php echo '{{' . $idx . '}}'; ?></span>
                                <input type="text" wire:model="prueba_variables.{{ $idx }}" placeholder="Valor"
                                       class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            <button wire:click="enviarPrueba"
                    class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-5 py-2 text-sm font-bold text-white shadow">
                <i class="fa-solid fa-paper-plane mr-1"></i> Enviar prueba
            </button>
        </div>
    @endif

    {{-- ── TAB HISTORIAL ───────────────────────────────────── --}}
    @if($tab === 'historial')
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100">
                <h2 class="text-sm font-bold text-slate-700"><i class="fa-solid fa-clock-rotate-left text-emerald-600 mr-1"></i> Últimos 100 mensajes vía Meta</h2>
            </div>
            @if($historial->isEmpty())
                <div class="p-8 text-center text-slate-500 text-sm">
                    Aún no hay mensajes enviados/recibidos por Meta.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Fecha</th>
                            <th class="px-4 py-2 text-left">Rol</th>
                            <th class="px-4 py-2 text-left">Contenido</th>
                            <th class="px-4 py-2 text-center">Ack</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($historial as $m)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-2 text-xs text-slate-500 whitespace-nowrap">{{ $m->created_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span class="px-2 py-0.5 rounded {{ $m->rol === 'user' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ $m->rol }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-700">{{ \Illuminate\Support\Str::limit($m->contenido, 100) }}</td>
                                <td class="px-4 py-2 text-center text-xs">{{ $m->ack }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- ─── MODAL PLANTILLA ─── --}}
    @if($modalPlantilla)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="$set('modalPlantilla', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden max-h-[95vh] flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between"
                     style="background: linear-gradient(135deg, #25D366, #128C7E);">
                    <h3 class="text-base font-bold text-white">
                        {{ $plantilla_id ? 'Editar plantilla' : 'Nueva plantilla' }}
                    </h3>
                    <button wire:click="$set('modalPlantilla', false)" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                </div>
                <div class="p-5 space-y-3 overflow-y-auto">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre (snake_case) <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="tpl_nombre" placeholder="pedido_recibido"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                            @error('tpl_nombre')<p class="text-rose-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Idioma</label>
                            <input type="text" wire:model="tpl_idioma" placeholder="es"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Categoría</label>
                            <select wire:model="tpl_categoria" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option>UTILITY</option><option>MARKETING</option><option>AUTHENTICATION</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Estado en Meta</label>
                            <select wire:model="tpl_estado" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="borrador">borrador</option>
                                <option value="pendiente">pendiente</option>
                                <option value="aprobada">aprobada</option>
                                <option value="rechazada">rechazada</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="tpl_descripcion" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Body (con placeholders <?php echo '{{1}}, {{2}}'; ?>, etc.)</label>
                        <textarea wire:model="tpl_body" rows="5"
                                  placeholder="Hola {{1}}, tu pedido {{2}} fue recibido."
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Footer (opcional)</label>
                        <input type="text" wire:model="tpl_footer" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="tpl_activa">
                        <span class="text-sm">Plantilla activa</span>
                    </label>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="$set('modalPlantilla', false)"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">Cancelar</button>
                    <button wire:click="guardarPlantilla"
                            class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ─── MODAL DISPARADOR ─── --}}
    @if($modalDisparador)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="$set('modalDisparador', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100"
                     style="background: linear-gradient(135deg, #25D366, #128C7E);">
                    <h3 class="text-base font-bold text-white">{{ $disp_id ? 'Editar disparador' : 'Nuevo disparador' }}</h3>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Evento (snake_case) <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="disp_evento" list="eventos-sugeridos" placeholder="pedido_entregado"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                        <datalist id="eventos-sugeridos">
                            @foreach($eventosSugeridos as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </datalist>
                        @error('disp_evento')<p class="text-rose-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Plantilla <span class="text-rose-500">*</span></label>
                        <select wire:model="disp_plantilla_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">— Selecciona —</option>
                            @foreach($plantillas->where('activa', true) as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre }} ({{ $p->idioma }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Variables map (JSON)</label>
                        <textarea wire:model="disp_variables_map" rows="3"
                                  placeholder='{"1": "{cliente_nombre}", "2": "{total}"}'
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono"></textarea>
                        @error('disp_variables_map')<p class="text-rose-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="disp_descripcion" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="disp_activo">
                        <span class="text-sm">Activo</span>
                    </label>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="$set('modalDisparador', false)"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">Cancelar</button>
                    <button wire:click="guardarDisparador"
                            class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</div>
