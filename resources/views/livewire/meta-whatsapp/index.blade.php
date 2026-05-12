<div class="p-6 space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
             style="background: linear-gradient(135deg, #25D366, #128C7E);">
            <i class="fa-brands fa-whatsapp text-xl"></i>
        </div>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Meta WhatsApp Cloud API</h1>
            <p class="text-xs text-slate-500">Integración oficial vía graph.facebook.com. Multi-tenant.</p>
        </div>
    </div>

    {{-- Tarjeta info webhook --}}
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        <p class="font-semibold mb-1"><i class="fa-solid fa-link mr-1"></i> URL del webhook (configúrala en Meta Developer Portal)</p>
        <code class="block bg-white rounded-lg px-3 py-2 text-xs text-slate-800 break-all">{{ $webhookUrl }}</code>
        <p class="text-[11px] mt-2 text-emerald-800">
            Usa el <em>Verify Token</em> de tu configuración. Suscríbete al campo <code class="bg-white px-1 rounded">messages</code>.
        </p>
    </div>

    {{-- Listado --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-700">Configuraciones</h2>
            <button wire:click="abrirModalCrear"
                    class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-bold text-white shadow">
                <i class="fa-solid fa-plus mr-1"></i> Nueva configuración
            </button>
        </div>

        @if($configs->isEmpty())
            <div class="p-8 text-center text-slate-500 text-sm">
                Aún no hay configuración Meta. Crea una para empezar a recibir / enviar mensajes por Meta Cloud API.
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Nombre</th>
                        <th class="px-4 py-2 text-left">Phone Number ID</th>
                        <th class="px-4 py-2 text-left">WABA ID</th>
                        <th class="px-4 py-2 text-left">API ver.</th>
                        <th class="px-4 py-2 text-center">Activo</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($configs as $c)
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-2 font-medium text-slate-800">{{ $c->display_name ?: '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ $c->phone_number_id }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ $c->waba_id ?: '—' }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $c->api_version }}</td>
                            <td class="px-4 py-2 text-center">
                                <button wire:click="toggleActivo({{ $c->id }})"
                                        class="px-2 py-1 text-xs rounded-full font-bold
                                            {{ $c->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500' }}">
                                    {{ $c->activo ? 'SI' : 'NO' }}
                                </button>
                            </td>
                            <td class="px-4 py-2 text-right space-x-1">
                                <button wire:click="abrirModalEditar({{ $c->id }})"
                                        class="text-slate-500 hover:text-emerald-600" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button wire:click="eliminar({{ $c->id }})"
                                        wire:confirm="¿Eliminar esta configuración?"
                                        class="text-slate-500 hover:text-rose-600" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Test envío --}}
    @if($configs->where('activo', true)->count() > 0)
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="text-sm font-bold text-slate-700 mb-2"><i class="fa-solid fa-paper-plane mr-1"></i> Probar envío</h3>
            <p class="text-xs text-slate-500 mb-3">Manda un mensaje de prueba al teléfono indicado usando la config activa.</p>
            <div class="flex gap-2">
                <input type="text" wire:model="telefonoPrueba" placeholder="573XXXXXXXXX (E.164 sin +)"
                       class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <button wire:click="probarEnvio({{ $configs->where('activo', true)->first()->id }})"
                        class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-bold text-white">
                    Enviar prueba
                </button>
            </div>
        </div>
    @endif

    {{-- Modal Crear/Editar --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden max-h-[95vh] flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between"
                     style="background: linear-gradient(135deg, #25D366, #128C7E);">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp"></i>
                        {{ $editandoId ? 'Editar configuración Meta' : 'Nueva configuración Meta' }}
                    </h3>
                    <button wire:click="cerrarModal" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre amigable</label>
                        <input type="text" wire:model="display_name" placeholder="Ej: La Hacienda Meta"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number ID <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="phone_number_id" placeholder="123456789012345"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                            @error('phone_number_id')<p class="text-rose-500 text-xs mt-1">{{ $message }}</p>@enderror
                            <p class="text-[10px] text-slate-400 mt-1">Meta → WA Business → Phone Numbers → ID</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">WABA ID</label>
                            <input type="text" wire:model="waba_id" placeholder="987654321098765"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                            <p class="text-[10px] text-slate-400 mt-1">WhatsApp Business Account ID (opcional pero recomendado)</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Access Token <span class="text-rose-500">*</span></label>
                        <textarea wire:model="access_token" rows="3"
                                  placeholder="EAAGm0PX4ZCpsBO..."
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"></textarea>
                        @error('access_token')<p class="text-rose-500 text-xs mt-1">{{ $message }}</p>@enderror
                        <p class="text-[10px] text-slate-400 mt-1">Usa un <strong>System User Token</strong> permanente (no temporal).</p>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">API version</label>
                            <input type="text" wire:model="api_version" placeholder="v20.0"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Idioma default</label>
                            <input type="text" wire:model="default_lang" placeholder="es"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="activo" class="rounded border-slate-300">
                                <span class="text-sm text-slate-700">Activar</span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <p class="text-xs font-semibold text-slate-700">🔐 Webhook</p>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Verify Token <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="verify_token"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                            <p class="text-[10px] text-slate-400 mt-1">Mete EXACTAMENTE este valor en Meta App → Webhooks → Verify Token.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">App Secret</label>
                            <input type="text" wire:model="app_secret"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                            <p class="text-[10px] text-slate-400 mt-1">Opcional. Para validar firma X-Hub-Signature-256.</p>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarModal"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button wire:click="guardar"
                            class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-bold text-white shadow">
                        <i class="fa-solid fa-save mr-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
