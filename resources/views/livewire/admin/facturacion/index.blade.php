<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-lg">
                        <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Facturación Electrónica</h2>
                        <p class="text-sm text-slate-500">Habilita empresas emisoras (DIAN) y expón la API por API key</p>
                    </div>
                </div>
                <button wire:click="nuevoEmisor"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Habilitar empresa
                </button>
            </div>
        </div>

        {{-- AVISO FASE --}}
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800 flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <div>
                <strong>Fase 1 — Configuración.</strong> Aquí registras la identidad fiscal, las credenciales
                DIAN, el certificado (cifrado) y la resolución de cada empresa, y obtienes su API key.
                El <strong>motor de emisión</strong> (XML UBL 2.1 → firma → CUFE → envío a la DIAN) es la
                siguiente fase; se activa cuando cargues el <em>Software ID</em> y el certificado reales.
            </div>
        </div>

        {{-- LISTA DE EMISORES --}}
        @if($emisores->isEmpty())
            <div class="rounded-2xl bg-white border-2 border-dashed border-slate-200 p-10 text-center text-slate-500">
                <i class="fa-solid fa-building-circle-check text-3xl mb-3 text-slate-300"></i>
                <p class="font-semibold">Aún no hay empresas emisoras.</p>
                <p class="text-sm">Pulsa <strong>Habilitar empresa</strong> para configurar la primera.</p>
            </div>
        @else
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                @foreach($emisores as $e)
                    <div class="rounded-2xl bg-white border-2 {{ $e->activa ? 'border-slate-200' : 'border-rose-200 opacity-80' }} shadow-sm overflow-hidden">
                        {{-- Cabecera emisor --}}
                        <div class="p-5 border-b border-slate-100">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-lg font-extrabold text-slate-800 truncate">{{ $e->razon_social }}</h3>
                                        @if($e->ambiente === 'produccion')
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Producción</span>
                                        @else
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">Habilitación</span>
                                        @endif
                                        @if(!$e->activa)
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Inactivo</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        NIT <strong>{{ $e->nit }}{{ $e->dv ? '-'.$e->dv : '' }}</strong>
                                        · <span class="font-mono">{{ $e->tenant_nombre }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button wire:click="toggleActiva({{ $e->id }})"
                                            class="text-xs px-2.5 py-1.5 rounded-lg {{ $e->activa ? 'bg-rose-50 text-rose-600 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' }} font-semibold transition">
                                        {{ $e->activa ? 'Desactivar' : 'Activar' }}
                                    </button>
                                    <button wire:click="editarEmisor({{ $e->id }})"
                                            class="text-xs px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold transition">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </button>
                                </div>
                            </div>

                            {{-- Estado credenciales --}}
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid {{ $e->software_id ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-slate-300' }}"></i>
                                    Software ID {{ $e->software_id ? 'cargado' : 'pendiente' }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid {{ $e->certificado_path ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-slate-300' }}"></i>
                                    Certificado {{ $e->certificado_path ? 'cargado' : 'pendiente' }}
                                </div>
                            </div>
                        </div>

                        {{-- API key --}}
                        <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-[11px] uppercase font-bold text-slate-400 mb-0.5">API key del emisor</p>
                                    <code class="text-xs text-slate-700 break-all">{{ $e->api_key }}</code>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button onclick="navigator.clipboard.writeText('{{ $e->api_key }}')"
                                            class="text-xs px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold transition">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <button wire:click="regenerarApiKey({{ $e->id }})"
                                            wire:confirm="¿Regenerar la API key? El software que ya la usa dejará de autenticar."
                                            class="text-xs px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-amber-600 hover:bg-amber-50 font-semibold transition">
                                        <i class="fa-solid fa-rotate"></i> Regenerar
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Resoluciones --}}
                        <div class="p-5">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-bold text-slate-700">Resoluciones de numeración</h4>
                                <button wire:click="nuevaResolucion({{ $e->tenant_id }})"
                                        class="text-xs px-2.5 py-1 rounded-lg bg-brand-soft text-brand-dark hover:bg-brand-soft/70 font-semibold transition">
                                    <i class="fa-solid fa-plus"></i> Agregar
                                </button>
                            </div>
                            @if($e->resoluciones->isEmpty())
                                <p class="text-xs text-slate-400 italic">Sin resolución. La API no podrá numerar facturas hasta que agregues una.</p>
                            @else
                                <div class="space-y-1.5">
                                    @foreach($e->resoluciones as $r)
                                        <div class="flex items-center justify-between gap-2 text-xs bg-slate-50 rounded-lg px-3 py-2">
                                            <div class="min-w-0">
                                                <span class="font-semibold text-slate-700">{{ $r->tipo_documento }}</span>
                                                <span class="font-mono">{{ $r->prefijo }}{{ $r->numero_desde }}–{{ $r->prefijo }}{{ $r->numero_hasta }}</span>
                                                <span class="text-slate-400">· usado hasta {{ $r->numero_actual }}</span>
                                                @if(!$r->activa)<span class="text-rose-500 font-semibold">(inactiva)</span>@endif
                                            </div>
                                            <button wire:click="editarResolucion({{ $r->id }})"
                                                    class="shrink-0 text-slate-400 hover:text-slate-700">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══════════════ MODAL EMISOR ══════════════ --}}
    @if($modalEmisor)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50" wire:click.self="$set('modalEmisor', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold text-slate-800">
                        {{ $configId ? 'Editar emisor' : 'Habilitar empresa emisora' }}
                    </h3>
                    <button wire:click="$set('modalEmisor', false)" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>

                <div class="p-6 space-y-5">
                    {{-- 🪤 Señuelos: Chrome/gestor de claves rellena ESTOS y deja limpios los reales --}}
                    <input type="text" name="fakeusernameremembered" autocomplete="username" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;width:0">
                    <input type="password" name="fakepasswordremembered" autocomplete="new-password" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;width:0">

                    {{-- Tenant --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Empresa (tenant)</label>
                        <select wire:model="tenantId" @if($configId) disabled @endif
                                class="w-full rounded-xl border-slate-200 text-sm {{ $configId ? 'bg-slate-100' : '' }}">
                            <option value="">— Selecciona —</option>
                            @foreach($tenants as $t)
                                <option value="{{ $t->id }}">{{ $t->nombre }} (#{{ $t->id }})</option>
                            @endforeach
                        </select>
                        @error('tenantId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    {{-- Identidad fiscal --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">NIT</label>
                            <input wire:model="nit" autocomplete="off" class="w-full rounded-xl border-slate-200 text-sm" placeholder="900123456">
                            @error('nit') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">DV</label>
                            <input wire:model="dv" autocomplete="off" class="w-full rounded-xl border-slate-200 text-sm" placeholder="7">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Razón social</label>
                        <input wire:model="razon_social" autocomplete="off" class="w-full rounded-xl border-slate-200 text-sm">
                        @error('razon_social') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo de persona</label>
                            <select wire:model="tipo_persona" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="juridica">Jurídica</option>
                                <option value="natural">Natural</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Cód. municipio (DANE)</label>
                            <input wire:model="municipio_codigo" autocomplete="off" class="w-full rounded-xl border-slate-200 text-sm" placeholder="05001">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Responsabilidades fiscales <span class="text-slate-400 normal-case">(una por línea, ej. O-13, R-99-PN)</span></label>
                        <textarea wire:model="responsabilidades_text" rows="2" class="w-full rounded-xl border-slate-200 text-sm font-mono"></textarea>
                    </div>

                    <hr class="border-slate-100">

                    {{-- DIAN --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Ambiente DIAN</label>
                            <select wire:model="ambiente" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="habilitacion">Habilitación (pruebas)</option>
                                <option value="produccion">Producción</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Set de pruebas (TestSetId)</label>
                            <input wire:model="test_set_id" autocomplete="off" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Software ID</label>
                            <input wire:model="software_id" autocomplete="off" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Software PIN</label>
                            <input wire:model="software_pin" type="password" autocomplete="new-password" placeholder="{{ $configId ? '•••• (sin cambios)' : '' }}" class="w-full rounded-xl border-slate-200 text-sm">
                            <span class="text-[10px] text-slate-400">Se guarda cifrado. Déjalo vacío para no cambiarlo.</span>
                        </div>
                    </div>

                    <hr class="border-slate-100">

                    {{-- Certificado --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Certificado .p12 / .pfx</label>
                            <input type="file" wire:model="certificado" accept=".p12,.pfx" class="w-full text-xs">
                            <span class="text-[10px] text-slate-400">Se almacena fuera del webroot. Nunca se expone ni entra a git.</span>
                            @error('certificado') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            <div wire:loading wire:target="certificado" class="text-[10px] text-indigo-500 mt-1">Subiendo…</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Contraseña certificado</label>
                            <input wire:model="certificado_password" type="password" autocomplete="new-password" placeholder="{{ $configId ? '•••• (sin cambios)' : '' }}" class="w-full rounded-xl border-slate-200 text-sm">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1 mt-2">Vence</label>
                            <input wire:model="certificado_vence_at" type="date" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="activa" class="rounded border-slate-300 text-brand">
                        Emisor activo (la API acepta facturas)
                    </label>
                </div>

                <div class="sticky bottom-0 bg-white border-t border-slate-100 px-6 py-4 flex justify-end gap-2">
                    <button wire:click="$set('modalEmisor', false)" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-semibold text-sm hover:bg-slate-200">Cancelar</button>
                    <button wire:click="guardarEmisor" wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary text-white font-bold text-sm hover:from-brand-dark hover:to-brand-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="guardarEmisor">Guardar</span>
                        <span wire:loading wire:target="guardarEmisor">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════ MODAL RESOLUCIÓN ══════════════ --}}
    @if($modalResolucion)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50" wire:click.self="$set('modalResolucion', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold text-slate-800">{{ $resolucionId ? 'Editar resolución' : 'Nueva resolución' }}</h3>
                    <button wire:click="$set('modalResolucion', false)" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo documento</label>
                            <select wire:model="res_tipo_documento" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="factura">Factura</option>
                                <option value="nota_credito">Nota crédito</option>
                                <option value="nota_debito">Nota débito</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Prefijo</label>
                            <input wire:model="res_prefijo" class="w-full rounded-xl border-slate-200 text-sm" placeholder="SETP / FE">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1"># Resolución DIAN</label>
                        <input wire:model="res_numero_resolucion" class="w-full rounded-xl border-slate-200 text-sm" placeholder="18760000001">
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Desde</label>
                            <input wire:model="res_numero_desde" type="number" class="w-full rounded-xl border-slate-200 text-sm">
                            @error('res_numero_desde') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hasta</label>
                            <input wire:model="res_numero_hasta" type="number" class="w-full rounded-xl border-slate-200 text-sm">
                            @error('res_numero_hasta') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Actual</label>
                            <input wire:model="res_numero_actual" type="number" class="w-full rounded-xl border-slate-200 text-sm" placeholder="{{ ($res_numero_desde ?? 1) - 1 }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Vigente desde</label>
                            <input wire:model="res_fecha_desde" type="date" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Vigente hasta</label>
                            <input wire:model="res_fecha_hasta" type="date" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Clave técnica <span class="text-slate-400 normal-case">(para el CUFE — cifrada)</span></label>
                        <input wire:model="res_clave_tecnica" type="password" placeholder="{{ $resolucionId ? '•••• (sin cambios)' : '' }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="res_activa" class="rounded border-slate-300 text-brand">
                        Resolución activa
                    </label>
                </div>
                <div class="border-t border-slate-100 px-6 py-4 flex justify-end gap-2">
                    <button wire:click="$set('modalResolucion', false)" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-semibold text-sm hover:bg-slate-200">Cancelar</button>
                    <button wire:click="guardarResolucion" wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary text-white font-bold text-sm hover:from-brand-dark hover:to-brand-dark disabled:opacity-60">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
