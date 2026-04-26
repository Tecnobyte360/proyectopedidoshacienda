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
                                            <i class="fa-brands fa-whatsapp text-emerald-500"></i>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-slate-800 text-[13px] truncate">{{ $c['name'] ?: 'Sin nombre' }}</div>
                                                <div class="text-[11px] text-slate-500">
                                                    {{ $c['phoneNumber'] ?: '(sin número)' }}
                                                    @if($c['isDefault']) <span class="ml-1 text-amber-600 font-bold">★ Default</span> @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @php
                                            $colores = match($c['status']) {
                                                'CONNECTED'   => 'bg-emerald-100 text-emerald-700',
                                                'PAIRING','QRCODE','OPENING' => 'bg-amber-100 text-amber-700',
                                                'TIMEOUT','DISCONNECTED','NOT_CONNECTED' => 'bg-rose-100 text-rose-700',
                                                default       => 'bg-slate-100 text-slate-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $colores }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                            {{ $c['status'] }}
                                        </span>
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
                                                ✓ {{ $c['asignadoA']['nombre'] }}
                                            </div>
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
