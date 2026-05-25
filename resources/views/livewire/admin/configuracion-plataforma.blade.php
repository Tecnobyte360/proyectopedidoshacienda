<div>
    <div class="mb-5">
        <h2 class="text-2xl font-extrabold text-slate-800">Configuración de la plataforma</h2>
        <p class="text-sm text-slate-500">Branding global de TecnoByte360 (lo que ven super-admins y dominio principal).</p>
    </div>

    <form wire:submit.prevent="guardar" class="space-y-5">

        {{-- IDENTIDAD --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl"
                      style="background: var(--brand-soft); color: var(--brand-secondary);">
                    <i class="fa-solid fa-building"></i>
                </span>
                <h3 class="text-base font-bold text-slate-800">Identidad</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre de la plataforma</label>
                    <input type="text" wire:model="nombre" maxlength="80"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Subtítulo</label>
                    <input type="text" wire:model="subtitulo" maxlength="120" placeholder="Plataforma SaaS"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
            </div>
        </section>

        {{-- LOGO --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5"
                 x-data="{
                    dataUrl: @entangle('logo_data_url').live,
                    nombre:  @entangle('logo_nombre').live,
                    error: '',
                    pick(e) {
                        const file = e.target.files && e.target.files[0];
                        if (!file) return;
                        this.error = '';
                        if (!file.type.startsWith('image/')) { this.error = 'No es una imagen válida.'; return; }
                        if (file.size > 2 * 1024 * 1024) { this.error = 'Imagen muy grande (máx 2MB).'; return; }
                        const reader = new FileReader();
                        reader.onload = () => { this.dataUrl = reader.result; this.nombre = file.name; };
                        reader.readAsDataURL(file);
                    },
                 }">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-image"></i>
                </span>
                <h3 class="text-base font-bold text-slate-800">Logo</h3>
            </div>

            <div class="flex items-start gap-4">
                <div class="h-20 w-20 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
                    <template x-if="dataUrl">
                        <img :src="dataUrl" class="h-full w-full object-contain">
                    </template>
                    <template x-if="!dataUrl">
                        @if($logo_url_actual)
                            <img src="{{ $logo_url_actual }}" class="h-full w-full object-contain">
                        @else
                            <i class="fa-solid fa-image text-slate-300 text-xl"></i>
                        @endif
                    </template>
                </div>
                <div class="flex-1">
                    <label class="inline-flex items-center gap-2 rounded-xl bg-brand-soft border border-brand-soft-2 text-brand-secondary px-3 py-2 text-sm font-semibold cursor-pointer hover:opacity-90">
                        <i class="fa-solid fa-upload"></i>
                        <span>Seleccionar logo</span>
                        <input type="file" accept="image/*" @change="pick($event)" class="hidden">
                    </label>
                    <p class="text-[11px] text-slate-500 mt-1.5">PNG, JPG, SVG o WebP. Máx 2MB. Lo verás en el sidebar y login.</p>
                    <p x-show="nombre" x-cloak class="text-[11px] text-emerald-600 mt-1" x-text="'Listo: ' + nombre"></p>
                    <p x-show="error" x-cloak class="text-[11px] text-rose-600 mt-1" x-text="error"></p>
                </div>
            </div>
        </section>

        {{-- COLORES --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                    <i class="fa-solid fa-palette"></i>
                </span>
                <h3 class="text-base font-bold text-slate-800">Colores de la plataforma</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50">
                    <input type="color" wire:model.live="color_primario"
                           class="h-12 w-12 rounded-lg border border-slate-200 cursor-pointer" style="padding: 2px;">
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">Primario</div>
                        <input type="text" wire:model.live="color_primario" maxlength="7"
                               class="w-full mt-0.5 rounded-lg border border-slate-200 px-2 py-1 text-xs font-mono uppercase">
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50">
                    <input type="color" wire:model.live="color_secundario"
                           class="h-12 w-12 rounded-lg border border-slate-200 cursor-pointer" style="padding: 2px;">
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">Secundario</div>
                        <input type="text" wire:model.live="color_secundario" maxlength="7"
                               class="w-full mt-0.5 rounded-lg border border-slate-200 px-2 py-1 text-xs font-mono uppercase">
                    </div>
                </div>
            </div>

            <div class="mt-3 rounded-xl border border-slate-200 overflow-hidden">
                <div class="text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 bg-slate-100 text-slate-500">Vista previa</div>
                <div class="p-4 flex items-center gap-3"
                     style="background: linear-gradient(135deg,
                        color-mix(in srgb, {{ $color_primario }} 14%, white),
                        #ffffff 60%,
                        color-mix(in srgb, {{ $color_secundario }} 12%, white));">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-white shadow-md flex-shrink-0"
                         style="background: linear-gradient(135deg, {{ $color_primario }}, {{ $color_secundario }});">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold" style="color: {{ $color_primario }};">{{ $nombre ?: 'TecnoByte360' }}</div>
                        <div class="text-[11px] text-slate-500">{{ $subtitulo ?: 'Plataforma SaaS' }}</div>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-bold text-white shadow"
                            style="background: linear-gradient(135deg, {{ $color_primario }}, {{ $color_secundario }});">
                        Botón
                    </button>
                </div>
            </div>

            {{-- Presets --}}
            @php
                $presets = [
                    'Cálidos / Tierra' => [
                        ['Naranja Hacienda', '#d68643', '#a85f24'],
                        ['Marrón Café',      '#a05a2c', '#7d4421'],
                        ['Cobre',            '#c2693e', '#8e4a26'],
                        ['Terracota',        '#c1502a', '#92381b'],
                        ['Mostaza',          '#ca8a04', '#854d0e'],
                        ['Caramelo',         '#b45309', '#78350f'],
                        ['Bronce',           '#92400e', '#451a03'],
                        ['Tabaco',           '#78350f', '#451a03'],
                    ],
                    'Verdes' => [
                        ['Esmeralda',        '#10b981', '#047857'],
                        ['Menta',            '#34d399', '#059669'],
                        ['Lima',             '#84cc16', '#3f6212'],
                        ['Bosque',           '#15803d', '#14532d'],
                        ['Oliva',            '#65a30d', '#365314'],
                        ['Mar',              '#0d9488', '#115e59'],
                        ['Aguacate',         '#3f6212', '#1a2e05'],
                        ['Pino',             '#166534', '#052e16'],
                    ],
                    'Azules' => [
                        ['Océano',           '#2563eb', '#1e40af'],
                        ['Real',             '#1d4ed8', '#1e3a8a'],
                        ['Cielo',            '#0ea5e9', '#0369a1'],
                        ['Cian',             '#06b6d4', '#0e7490'],
                        ['Turquesa',         '#14b8a6', '#0f766e'],
                        ['Medianoche',       '#1e3a8a', '#0c1e4d'],
                        ['Celeste',          '#38bdf8', '#075985'],
                        ['Marino',           '#1e40af', '#172554'],
                    ],
                    'Violetas / Rosas' => [
                        ['Violeta',          '#8b5cf6', '#6d28d9'],
                        ['Lavanda',          '#a78bfa', '#7c3aed'],
                        ['Púrpura',          '#a855f7', '#7e22ce'],
                        ['Índigo',           '#6366f1', '#4338ca'],
                        ['Coral',            '#f43f5e', '#be123c'],
                        ['Fucsia',           '#ec4899', '#9d174d'],
                        ['Magenta',          '#c026d3', '#86198f'],
                        ['Rojo cereza',      '#dc2626', '#991b1b'],
                    ],
                    'Neutros' => [
                        ['Slate',            '#475569', '#1e293b'],
                        ['Grafito',          '#374151', '#111827'],
                        ['Carbón',           '#1f2937', '#030712'],
                        ['Niebla',           '#64748b', '#334155'],
                        ['Pizarra',          '#52525b', '#18181b'],
                        ['Topo',             '#78716c', '#292524'],
                        ['Antracita',        '#27272a', '#09090b'],
                        ['Plata',            '#94a3b8', '#475569'],
                    ],
                ];
            @endphp

            <div class="mt-4">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <i class="fa-solid fa-swatchbook"></i> Presets ({{ collect($presets)->flatten(1)->count() }})
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 max-h-64 overflow-y-auto">
                    @foreach($presets as $grupo => $items)
                        <div class="border-b last:border-b-0 border-slate-200 p-2.5">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">{{ $grupo }}</div>
                            <div class="grid grid-cols-8 gap-1.5">
                                @foreach($items as [$nm, $p, $s])
                                    <button type="button"
                                            @click="$wire.set('color_primario', '{{ $p }}'); $wire.set('color_secundario', '{{ $s }}');"
                                            title="{{ $nm }} ({{ $p }} → {{ $s }})"
                                            class="group relative h-9 rounded-md border-2 border-white shadow-sm ring-1 ring-slate-200 hover:ring-2 hover:ring-slate-500 hover:scale-110 transition-all"
                                            style="background: linear-gradient(135deg, {{ $p }}, {{ $s }});">
                                        @if($color_primario === $p && $color_secundario === $s)
                                            <i class="fa-solid fa-check absolute inset-0 m-auto text-white text-xs drop-shadow"></i>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- WHATSAPP / TECNOBYTEAPP --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-brands fa-whatsapp"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-slate-800">Cuenta superadmin de TecnoByteApp</h3>
                    <p class="text-xs text-slate-500">
                        Credenciales del SuperAdmin que administra todas las conexiones WhatsApp de los tenants.
                        Si un tenant tiene su propia cuenta TecnoByteApp, las suyas tienen prioridad.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Email TecnoByteApp</label>
                    <input type="email" wire:model="whatsapp_admin_email" placeholder="superadmin@tudominio.com"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Password TecnoByteApp</label>
                    <input type="password" wire:model="whatsapp_admin_password" placeholder="••••••••"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">API URL</label>
                    <input type="text" wire:model="whatsapp_api_base_url" placeholder="https://wa-api.tecnobyteapp.com:1422"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
            </div>

            <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] text-amber-800">
                <i class="fa-solid fa-circle-info"></i>
                Estas credenciales se usan para autenticar las llamadas a TecnoByteApp en nombre de cualquier tenant
                que no tenga sus propias credenciales. Cada tenant solo necesita su <code>connection_id</code>.
                Las credenciales NO se muestran en el modal de tenant (más seguro).
            </div>
        </section>

        {{-- 💳 WOMPI DEL DUEÑO KIVOX (para cobrar mensualidades a tenants) --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-credit-card"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-slate-800">Wompi — Cobro a tenants (SaaS)</h3>
                    <p class="text-xs text-slate-500">
                        Credenciales Wompi de <strong>TU empresa (Kivox / TecnoByte360)</strong>.
                        Sirven para cobrarle la mensualidad a Hacienda, Guayacán y demás tenants.
                        NO confundir con las credenciales Wompi de cada tenant (que están en /admin/tenants editar).
                    </p>
                </div>
                <button type="button" wire:click="probarWompiSaas"
                        class="text-xs px-3 py-1.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-800 font-bold inline-flex items-center gap-1">
                    <i class="fa-solid fa-plug"></i> Probar conexión
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Modo</label>
                    <select wire:model="saas_wompi_modo"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        <option value="sandbox"><i class="fa-solid fa-flask"></i> Sandbox (pruebas)</option>
                        <option value="produccion"><i class="fa-solid fa-rocket"></i> Producción (cobros reales)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Redirect URL</label>
                    <input type="url" wire:model="saas_wompi_redirect_url"
                           placeholder="https://admin.kivox.co/billing/gracias"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Public Key
                        <span class="text-slate-400 font-normal">(pub_prod_… o pub_test_…)</span>
                    </label>
                    <input type="text" wire:model="saas_wompi_public_key"
                           placeholder="pub_prod_xxxxxxxxxxxxxxxx"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Private Key
                        <span class="text-slate-400 font-normal">(prv_prod_… o prv_test_…)</span>
                    </label>
                    <input type="password" wire:model="saas_wompi_private_key"
                           placeholder="prv_prod_xxxxxxxxxxxxxxxx"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Integrity Secret</label>
                    <input type="password" wire:model="saas_wompi_integrity_secret"
                           placeholder="prod_integrity_xxxxxxxx"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Events Secret</label>
                    <input type="password" wire:model="saas_wompi_events_secret"
                           placeholder="prod_events_xxxxxxxx"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
            </div>

            <div class="mt-3 rounded-lg bg-sky-50 border border-sky-200 px-3 py-2.5 text-[11px] text-sky-900 space-y-1">
                <p class="font-bold"><i class="fa-solid fa-circle-info"></i> Cómo obtener estas llaves:</p>
                <ol class="list-decimal list-inside ml-2 space-y-0.5">
                    <li>Entra a tu cuenta Wompi: <a href="https://comercios.wompi.co" target="_blank" class="underline">comercios.wompi.co</a></li>
                    <li>Menú lateral → <strong>Configuración</strong> → <strong>API</strong></li>
                    <li>Copia las 4 llaves (Public, Private, Integrity, Events)</li>
                    <li>En la sección <strong>Webhooks</strong>, agrega esta URL:<br>
                        <code class="bg-white px-1.5 py-0.5 rounded">https://admin.kivox.co/api/saas-billing/wompi/webhook</code>
                        — suscríbete al evento <code>transaction.updated</code>
                    </li>
                </ol>
            </div>
        </section>

        {{-- ⚙️ POLÍTICA DE COBROS SAAS --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-slate-800">Política de cobros y morosidad</h3>
                    <p class="text-xs text-slate-500">
                        Define cuándo se factura y cuántos días esperas antes de suspender un tenant moroso.
                        Los crons leen estos valores en cada ejecución.
                    </p>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="saas_billing_activo"
                           class="h-5 w-5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400">
                    <span class="text-sm font-bold {{ $saas_billing_activo ? 'text-emerald-700' : 'text-rose-600' }}">
                        {!! $saas_billing_activo ? '<i class="fa-solid fa-check"></i> Activo' : '<i class="fa-solid fa-pause"></i> Pausado' !!}
                    </span>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Días ANTES del vencimiento para facturar
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" min="1" max="60" wire:model="saas_dias_antes_factura"
                               class="w-24 rounded-xl border border-slate-200 px-3 py-2 text-sm text-center font-bold">
                        <span class="text-xs text-slate-500">días antes</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Cuándo crear el Pago pendiente + mandar el link Wompi.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Días de GRACIA tras vencimiento
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="number" min="0" max="60" wire:model="saas_dias_gracia"
                               class="w-24 rounded-xl border border-slate-200 px-3 py-2 text-sm text-center font-bold">
                        <span class="text-xs text-slate-500">días de gracia → suspender</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Cuánto tiempo esperar tras vencer antes de bloquear el acceso del tenant.</p>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-bold text-slate-700 mb-2">
                    Recordatorios escalonados (activa los que quieras enviar)
                </label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <label class="flex items-start gap-2 p-3 rounded-xl border border-slate-200 hover:border-amber-300 cursor-pointer transition">
                        <input type="checkbox" wire:model="saas_aviso_preaviso" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                        <div>
                            <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-calendar-days"></i> Pre-aviso</div>
                            <div class="text-[10px] text-slate-500">3 días antes "vence en 3 días"</div>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 p-3 rounded-xl border border-slate-200 hover:border-amber-300 cursor-pointer transition">
                        <input type="checkbox" wire:model="saas_aviso_vence_hoy" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                        <div>
                            <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-clock"></i> Vence hoy</div>
                            <div class="text-[10px] text-slate-500">Día 0 — recordatorio urgente</div>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 p-3 rounded-xl border border-slate-200 hover:border-amber-300 cursor-pointer transition">
                        <input type="checkbox" wire:model="saas_aviso_vencio_ayer" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                        <div>
                            <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-triangle-exclamation"></i> Venció ayer</div>
                            <div class="text-[10px] text-slate-500">Día +1 — primer atraso</div>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 p-3 rounded-xl border border-slate-200 hover:border-rose-300 cursor-pointer transition">
                        <input type="checkbox" wire:model="saas_aviso_urgencia" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-rose-500 focus:ring-rose-400">
                        <div>
                            <div class="text-xs font-bold text-slate-800"><i class="fa-solid fa-bell"></i> Urgencia</div>
                            <div class="text-[10px] text-slate-500">Día +3 "será suspendido pronto"</div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- 📅 Horarios de envío POR DÍA DE LA SEMANA --}}
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50/30 p-4">
                <div class="flex items-start gap-2 mb-3">
                    <i class="fa-solid fa-calendar-week text-amber-600 mt-0.5"></i>
                    <div class="flex-1">
                        <label class="block text-sm font-bold text-amber-900">
                            Horarios de envío por día de la semana
                        </label>
                        <p class="text-[11px] text-amber-800/80 mt-0.5">
                            Define qué horarios usar para cada día. Si un día está vacío, no se envía nada ese día.
                            Útil para no molestar fines de semana o solo enviar en horas específicas.
                        </p>
                    </div>
                </div>

                {{-- Input compartido para agregar horas --}}
                <div class="flex items-center gap-2 flex-wrap bg-white rounded-xl border border-amber-200 p-3 mb-3">
                    <span class="text-[11px] text-slate-500 font-semibold">Hora a agregar:</span>
                    <input type="time" wire:model.live="nuevaHora"
                           class="rounded-lg border border-amber-300 px-3 py-1.5 text-sm w-32 focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                    <span class="text-[10px] text-slate-400">↑ usa los botones <strong>+</strong> de cada día</span>
                </div>

                {{-- Grid de días --}}
                @php
                    $diasLabels = [
                        'lun' => ['nombre' => 'Lunes', 'corta' => 'Lun', 'icon' => 'L'],
                        'mar' => ['nombre' => 'Martes', 'corta' => 'Mar', 'icon' => 'M'],
                        'mie' => ['nombre' => 'Miércoles', 'corta' => 'Mié', 'icon' => 'M'],
                        'jue' => ['nombre' => 'Jueves', 'corta' => 'Jue', 'icon' => 'J'],
                        'vie' => ['nombre' => 'Viernes', 'corta' => 'Vie', 'icon' => 'V'],
                        'sab' => ['nombre' => 'Sábado', 'corta' => 'Sáb', 'icon' => 'S'],
                        'dom' => ['nombre' => 'Domingo', 'corta' => 'Dom', 'icon' => 'D'],
                    ];
                @endphp
                <div class="space-y-2">
                    @foreach($diasLabels as $dia => $info)
                        @php
                            $horasDia = $saas_horas_envio[$dia] ?? [];
                            $activo = count($horasDia) > 0;
                            $esFinSemana = in_array($dia, ['sab','dom'], true);
                        @endphp
                        <div class="flex items-center gap-3 rounded-xl border p-3 {{ $activo ? 'bg-white border-amber-300' : ($esFinSemana ? 'bg-slate-50/50 border-slate-200' : 'bg-slate-50 border-slate-200') }}">

                            {{-- Día con icono --}}
                            <div class="flex items-center gap-2 w-28 flex-shrink-0">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg font-extrabold text-sm
                                            {{ $activo ? 'bg-gradient-to-br from-brand to-brand-dark text-white' : 'bg-slate-200 text-slate-500' }}">
                                    {{ $info['icon'] }}
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">{{ $info['nombre'] }}</div>
                                    <div class="text-[10px] {{ $activo ? 'text-emerald-600' : 'text-slate-400' }} font-semibold">
                                        {{ $activo ? count($horasDia) . ' envío' . (count($horasDia) === 1 ? '' : 's') : 'Inactivo' }}
                                    </div>
                                </div>
                            </div>

                            {{-- Chips de horas --}}
                            <div class="flex-1 flex flex-wrap gap-1.5 min-h-[28px] items-center">
                                @if(empty($horasDia))
                                    <span class="text-[10px] text-slate-400 italic">Sin horarios — no se enviará nada este día</span>
                                @else
                                    @foreach($horasDia as $idx => $h)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-soft border border-amber-300 px-2.5 py-0.5 text-xs font-bold text-brand-dark">
                                            <i class="fa-solid fa-clock text-[9px]"></i>
                                            {{ $h }}
                                            <button type="button" wire:click="quitarHoraDia('{{ $dia }}', {{ $idx }})"
                                                    class="ml-0.5 text-brand-dark/60 hover:text-rose-600 transition">
                                                <i class="fa-solid fa-xmark text-[10px]"></i>
                                            </button>
                                        </span>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Acciones --}}
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <button type="button" wire:click="agregarHoraDia('{{ $dia }}')"
                                        title="Agregar hora seleccionada arriba"
                                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-brand hover:bg-brand-dark text-white text-xs transition">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                @if($activo)
                                    <button type="button" wire:click="limpiarDia('{{ $dia }}')"
                                            title="Limpiar todos los horarios de este día"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-slate-100 hover:bg-rose-100 hover:text-rose-600 text-slate-500 text-xs transition">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                @endif
                                <div x-data="{open: false}" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                            title="Copiar estos horarios a otros días"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs transition">
                                        <i class="fa-solid fa-copy"></i>
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute right-0 top-full mt-1 w-52 bg-white rounded-xl shadow-lg ring-1 ring-slate-200 z-10 overflow-hidden">
                                        <button type="button" @click="open = false" wire:click="copiarADiasLaborales('{{ $dia }}')"
                                                class="w-full text-left px-3 py-2 text-xs hover:bg-amber-50 border-b border-slate-100">
                                            <i class="fa-solid fa-briefcase text-amber-500"></i>
                                            Copiar a lunes-viernes
                                        </button>
                                        <button type="button" @click="open = false" wire:click="copiarATodosLosDias('{{ $dia }}')"
                                                class="w-full text-left px-3 py-2 text-xs hover:bg-amber-50">
                                            <i class="fa-solid fa-calendar text-amber-500"></i>
                                            Copiar a TODOS los días
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @php
                    $totalSemana = array_sum(array_map('count', $saas_horas_envio));
                    $diasActivos = count(array_filter($saas_horas_envio, fn($a) => count($a) > 0));
                @endphp
                <p class="text-[11px] text-amber-900 bg-amber-200/30 rounded-lg px-3 py-2 mt-3 font-semibold">
                    <i class="fa-solid fa-paper-plane"></i> <strong>Resumen semanal:</strong> {{ $totalSemana }} envío(s) totales en {{ $diasActivos }} día(s) activo(s).
                    {!! $totalSemana === 0 ? '<i class="fa-solid fa-triangle-exclamation"></i> Actualmente NADIE recibirá recordatorios.' : '' !!}
                </p>
            </div>

            {{-- Visualización tipo timeline --}}
            <div class="mt-5 rounded-xl bg-slate-50 border border-slate-200 p-4">
                <p class="text-xs font-bold text-slate-700 mb-3"><i class="fa-solid fa-location-dot"></i> Línea de tiempo de un cobro</p>
                <div class="relative">
                    <div class="absolute top-3 left-0 right-0 h-0.5 bg-slate-300"></div>
                    <div class="relative flex justify-between text-center text-[10px]">
                        <div class="flex flex-col items-center">
                            <div class="h-6 w-6 rounded-full bg-blue-500 text-white flex items-center justify-center text-[10px] font-bold relative z-10"><i class="fa-solid fa-envelope-open"></i></div>
                            <div class="mt-1 font-bold text-slate-700">Día -{{ $saas_dias_antes_factura }}</div>
                            <div class="text-slate-500">Factura<br>+ link</div>
                        </div>
                        @if($saas_aviso_preaviso)
                        <div class="flex flex-col items-center">
                            <div class="h-6 w-6 rounded-full bg-amber-400 text-white flex items-center justify-center text-[10px] font-bold relative z-10"><i class="fa-solid fa-calendar-days"></i></div>
                            <div class="mt-1 font-bold text-slate-700">Día -3</div>
                            <div class="text-slate-500">Pre-aviso</div>
                        </div>
                        @endif
                        @if($saas_aviso_vence_hoy)
                        <div class="flex flex-col items-center">
                            <div class="h-6 w-6 rounded-full bg-orange-500 text-white flex items-center justify-center text-[10px] font-bold relative z-10"><i class="fa-solid fa-clock"></i></div>
                            <div class="mt-1 font-bold text-slate-700">Día 0</div>
                            <div class="text-slate-500">Vence hoy</div>
                        </div>
                        @endif
                        @if($saas_aviso_vencio_ayer)
                        <div class="flex flex-col items-center">
                            <div class="h-6 w-6 rounded-full bg-orange-600 text-white flex items-center justify-center text-[10px] font-bold relative z-10"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="mt-1 font-bold text-slate-700">Día +1</div>
                            <div class="text-slate-500">Venció</div>
                        </div>
                        @endif
                        @if($saas_aviso_urgencia)
                        <div class="flex flex-col items-center">
                            <div class="h-6 w-6 rounded-full bg-rose-500 text-white flex items-center justify-center text-[10px] font-bold relative z-10"><i class="fa-solid fa-bell"></i></div>
                            <div class="mt-1 font-bold text-slate-700">Día +3</div>
                            <div class="text-slate-500">Urgencia</div>
                        </div>
                        @endif
                        <div class="flex flex-col items-center">
                            <div class="h-6 w-6 rounded-full bg-rose-700 text-white flex items-center justify-center text-[10px] font-bold relative z-10"><i class="fa-solid fa-ban"></i></div>
                            <div class="mt-1 font-bold text-rose-700">Día +{{ $saas_dias_gracia }}</div>
                            <div class="text-rose-600">SUSPENDIDO</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- GESTOR DE CONEXIONES --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-plug"></i>
                </span>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-slate-800">Conexiones WhatsApp</h3>
                    <p class="text-xs text-slate-500">
                        Lista de números WhatsApp en TecnoByteApp y a qué tenant los asignaste.
                        Tú gestionas esto, los tenants solo usan lo que les asignas.
                    </p>
                </div>
                <button type="button" wire:click="$refresh"
                        class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                    <i class="fa-solid fa-rotate-right"></i> Refrescar
                </button>
            </div>

            @if(empty($conexiones))
                <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-6 text-sm text-slate-500 text-center">
                    @if(empty($whatsapp_admin_email) || empty($whatsapp_admin_password))
                        <i class="fa-solid fa-circle-info"></i> Configura primero el email/password del superadmin TecnoByteApp arriba y guarda.
                    @else
                        <i class="fa-solid fa-circle-exclamation"></i> No se pudieron listar conexiones. Verifica que las credenciales sean correctas.
                    @endif
                </div>
            @else
                <p class="text-xs text-slate-500 mb-2">
                    <i class="fa-solid fa-eye"></i> Total: <strong>{{ count($conexiones) }}</strong> conexión(es) listadas — incluye conexiones del superadmin y de cada tenant con cuenta propia.
                </p>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-3 py-2.5">ID</th>
                                <th class="px-3 py-2.5">Conexión</th>
                                <th class="px-3 py-2.5">Estado</th>
                                <th class="px-3 py-2.5">Visto por</th>
                                <th class="px-3 py-2.5">Asignar a tenant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($conexiones as $c)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-3 py-2.5 font-mono text-xs text-slate-600">
                                        #{{ $c['id'] }}
                                        @if($c['ownerId'])
                                            <div class="text-[10px] text-slate-400">owner {{ $c['ownerId'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            @if($c['esMeta'] ?? false)
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 text-white text-[10px] font-extrabold flex-shrink-0" title="Meta Cloud API oficial">M</span>
                                            @else
                                                <i class="fa-brands fa-whatsapp text-emerald-500 text-base"></i>
                                            @endif
                                            <div class="min-w-0">
                                                <div class="font-semibold text-slate-800 text-[13px] truncate">{{ $c['name'] ?: 'Sin nombre' }}</div>
                                                <div class="text-[11px] text-slate-500">
                                                    {{ $c['phoneNumber'] ?: '(sin número)' }}
                                                    @if($c['isDefault'] && !($c['esMeta'] ?? false))
                                                        <span class="ml-1 text-amber-600 font-bold"><i class="fa-solid fa-star"></i> Default</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @php
                                            $colores = match($c['status']) {
                                                'CONNECTED'   => 'bg-emerald-100 text-emerald-700',
                                                'PAIRING','QRCODE','OPENING' => 'bg-amber-100 text-amber-700',
                                                'TIMEOUT','DISCONNECTED','NOT_CONNECTED','ERROR' => 'bg-rose-100 text-rose-700',
                                                default       => 'bg-slate-100 text-slate-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $colores }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                            {{ $c['status'] }}
                                        </span>
                                        @if(($c['esMeta'] ?? false) && !empty($c['errorMeta']))
                                            <div class="text-[9px] text-rose-600 mt-1 max-w-[200px]" title="{{ $c['errorMeta'] }}">
                                                <i class="fa-solid fa-circle-exclamation"></i>
                                                {{ \Illuminate\Support\Str::limit($c['errorMeta'], 60) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($c['vistoPor'] ?? [] as $tag)
                                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-[10px] px-2 py-0.5">
                                                    {{ $tag }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if($c['esMeta'] ?? false)
                                            {{-- Meta: asignación viene de meta_whatsapp_configs.tenant_id, no editable aquí --}}
                                            <div class="text-[11px] text-slate-700 font-semibold">
                                                {{ $c['asignadoA']['nombre'] ?? '—' }}
                                            </div>
                                            <div class="text-[10px] text-slate-400">
                                                <i class="fa-solid fa-lock"></i> Editar en /admin/tenants
                                            </div>
                                        @else
                                            <select
                                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-brand focus:ring-2 focus:ring-brand/20"
                                                onchange="@this.call('asignarConexion', {{ $c['id'] }}, this.value === '' ? null : parseInt(this.value))">
                                                <option value="">— Sin asignar —</option>
                                                @foreach($tenants as $t)
                                                    <option value="{{ $t->id }}"
                                                        {{ ($c['asignadoA']['id'] ?? null) == $t->id ? 'selected' : '' }}>
                                                        {{ $t->nombre }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if($c['asignadoA'])
                                                <div class="text-[10px] text-emerald-600 mt-0.5 truncate">
                                                    <i class="fa-solid fa-check"></i> {{ $c['asignadoA']['nombre'] }}
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 rounded-lg bg-blue-50 border border-blue-200 px-3 py-2 text-[11px] text-blue-800">
                    <i class="fa-solid fa-lightbulb"></i>
                    <strong>Tip:</strong> Si una conexión está QRCODE/DISCONNECTED, ve al panel de TecnoByteApp y reconéctala.
                    Cada conexión solo puede estar asignada a UN tenant a la vez.
                </div>
            @endif
        </section>

        {{-- CONTACTO / SOPORTE --}}
        <section class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-headset"></i>
                </span>
                <h3 class="text-base font-bold text-slate-800">Datos de soporte</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Email de soporte</label>
                    <input type="email" wire:model="email_soporte" placeholder="soporte@tecnobyte360.com"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Teléfono</label>
                    <input type="text" wire:model="telefono_soporte" placeholder="+57 300 1234567"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Sitio web</label>
                    <input type="url" wire:model="sitio_web" placeholder="https://tecnobyte360.com"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white font-bold px-5 py-2.5 text-sm shadow transition">
                <i class="fa-solid fa-save"></i> Guardar configuración
            </button>
        </div>
    </form>
</div>
