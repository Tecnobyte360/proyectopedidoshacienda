<div class="p-4 md:p-6 space-y-5">

    {{-- HEADER --}}
    <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-lg">
                <i class="fa-solid fa-paper-plane text-xl"></i>
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-extrabold text-slate-800">Monitoreo de envíos SaaS</h1>
                <p class="text-sm text-slate-500">
                    <i class="fa-solid fa-circle-info text-slate-400"></i>
                    Historial de WhatsApps de facturación, recordatorios y suspensión enviados a tus tenants
                </p>
            </div>
            <select wire:model.live="rango"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                <option value="hoy">Hoy</option>
                <option value="7d">Últimos 7 días</option>
                <option value="30d">Últimos 30 días</option>
                <option value="mes">Mes en curso</option>
            </select>
        </div>
    </div>

    {{-- KPIs con FontAwesome --}}
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-9 gap-3">

        <div class="bg-white border border-slate-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-slate-500 font-bold tracking-wider">Total</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                    <i class="fa-solid fa-database text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $this->kpis['total'] }}</div>
        </div>

        <div class="bg-white border border-emerald-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-emerald-600 font-bold tracking-wider">Enviados</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                    <i class="fa-solid fa-circle-check text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-emerald-700">{{ $this->kpis['ok'] }}</div>
        </div>

        <div class="bg-white border border-rose-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-rose-600 font-bold tracking-wider">Fallidos</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 text-rose-600">
                    <i class="fa-solid fa-circle-xmark text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-rose-700">{{ $this->kpis['fallidos'] }}</div>
        </div>

        <div class="bg-white border border-sky-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-sky-600 font-bold tracking-wider">Facturas</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                    <i class="fa-solid fa-file-invoice-dollar text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-sky-700">{{ $this->kpis['facturas'] }}</div>
        </div>

        <div class="bg-white border border-amber-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-amber-600 font-bold tracking-wider">Recordatorios</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                    <i class="fa-solid fa-bell text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-amber-700">{{ $this->kpis['recordat'] }}</div>
        </div>

        <div class="bg-white border border-red-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-red-600 font-bold tracking-wider">Suspendido</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-red-600">
                    <i class="fa-solid fa-lock text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-red-700">{{ $this->kpis['suspendido'] }}</div>
        </div>

        <div class="bg-white border border-emerald-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-emerald-600 font-bold tracking-wider">WhatsApp</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                    <i class="fa-brands fa-whatsapp text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-emerald-700">{{ $this->kpis['whatsapp'] }}</div>
        </div>

        <div class="bg-white border border-blue-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-blue-600 font-bold tracking-wider">Emails</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                    <i class="fa-solid fa-envelope text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-blue-700">{{ $this->kpis['emails'] }}</div>
        </div>

        <div class="bg-white border border-violet-200 rounded-2xl p-4 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase text-violet-600 font-bold tracking-wider">Tenants</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600">
                    <i class="fa-solid fa-building text-sm"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-violet-700">{{ $this->kpis['tenants_afct'] }}</div>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
        <div class="flex items-center gap-2 mb-3">
            <i class="fa-solid fa-filter text-slate-400"></i>
            <span class="text-sm font-bold text-slate-700">Filtros</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="md:col-span-2 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" wire:model.live.debounce.400ms="busqueda"
                       placeholder="Buscar por teléfono, mensaje o error..."
                       class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
            </div>

            <div class="relative">
                <i class="fa-solid fa-building absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <select wire:model.live="filtroTenant"
                        class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-sm appearance-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="">Todos los tenants</option>
                    @foreach($this->tenants as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="relative">
                <i class="fa-solid fa-tag absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <select wire:model.live="filtroTipo"
                        class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-sm appearance-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="">Todos los tipos</option>
                    @foreach(\App\Models\SaasBillingEnvio::TIPOS as $k => $v)
                        <option value="{{ $k }}">{{ ucfirst($k) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="relative">
                <i class="fa-solid fa-circle-check absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <select wire:model.live="filtroOk"
                        class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-sm appearance-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="">Todos los resultados</option>
                    <option value="1">Solo exitosos</option>
                    <option value="0">Solo fallidos</option>
                </select>
            </div>

            <div class="relative">
                <i class="fa-solid fa-paper-plane absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <select wire:model.live="filtroCanal"
                        class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-sm appearance-none focus:border-brand focus:ring-2 focus:ring-brand/20">
                    <option value="">Todos los canales</option>
                    <option value="whatsapp">📱 Solo WhatsApp</option>
                    <option value="email">📧 Solo Email</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Banner informativo sobre auto-reintento --}}
    <div class="rounded-xl bg-sky-50 border border-sky-200 p-3 text-xs text-sky-800 flex items-start gap-2">
        <i class="fa-solid fa-circle-info text-sky-500 mt-0.5 text-base"></i>
        <div>
            <strong>¿Cómo funcionan los reintentos?</strong>
            Los envíos fallidos se reintentan automáticamente en el siguiente horario configurado
            (revisa <code class="bg-white px-1.5 rounded">/admin/configuracion-plataforma</code> → "Horarios de envío").
            También puedes reintentar manualmente con el botón
            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-bold">
                <i class="fa-solid fa-rotate-right"></i> Reintentar
            </span>
            en cada fila fallida.
        </div>
    </div>

    {{-- TABLA --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        @if($this->envios->isEmpty())
            <div class="p-16 text-center">
                <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-4">
                    <i class="fa-solid fa-paper-plane text-3xl"></i>
                </div>
                <p class="text-base font-bold text-slate-700">Sin envíos en el período seleccionado</p>
                <p class="text-xs text-slate-500 mt-1">Los registros aparecen automáticamente cuando el cron envía recordatorios o facturas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-center w-12"><i class="fa-solid fa-check-double"></i></th>
                            <th class="px-4 py-3 text-left w-40"><i class="fa-solid fa-clock mr-1"></i> Cuándo</th>
                            <th class="px-4 py-3 text-left"><i class="fa-solid fa-building mr-1"></i> Tenant</th>
                            <th class="px-4 py-3 text-left w-44"><i class="fa-solid fa-tag mr-1"></i> Tipo · Etapa</th>
                            <th class="px-4 py-3 text-left w-56"><i class="fa-solid fa-paper-plane mr-1"></i> Canal · Destinatario</th>
                            <th class="px-4 py-3 text-right w-28"><i class="fa-solid fa-coins mr-1"></i> Monto</th>
                            <th class="px-4 py-3 text-left"><i class="fa-solid fa-message mr-1"></i> Mensaje / Error</th>
                            <th class="px-4 py-3 text-center w-32"><i class="fa-solid fa-rotate-right mr-1"></i> Reintentos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($this->envios as $e)
                            @php
                                $iconoTipo = match($e->tipo) {
                                    'factura'      => 'fa-file-invoice-dollar',
                                    'recordatorio' => 'fa-bell',
                                    'suspendido'   => 'fa-lock',
                                    default        => 'fa-circle-info',
                                };
                                $colorTipo = match($e->tipo) {
                                    'factura'      => 'bg-sky-100 text-sky-700 border-sky-200',
                                    'recordatorio' => 'bg-amber-100 text-amber-700 border-amber-200',
                                    'suspendido'   => 'bg-red-100 text-red-700 border-red-200',
                                    default        => 'bg-slate-100 text-slate-600 border-slate-200',
                                };
                                $iconoEtapa = match($e->etapa) {
                                    'factura'      => 'fa-file-invoice',
                                    'preaviso'     => 'fa-calendar-day',
                                    'vence_hoy'    => 'fa-hourglass-half',
                                    'vencio_ayer'  => 'fa-triangle-exclamation',
                                    'urgencia'     => 'fa-fire',
                                    'suspendido'   => 'fa-ban',
                                    default        => 'fa-circle',
                                };
                                $labelEtapa = match($e->etapa) {
                                    'factura'      => 'Factura nueva',
                                    'preaviso'     => 'Preaviso (-3d)',
                                    'vence_hoy'    => 'Vence hoy',
                                    'vencio_ayer'  => 'Venció ayer',
                                    'urgencia'     => 'Urgencia (+3d)',
                                    'suspendido'   => 'Suspendido',
                                    default        => '—',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="px-4 py-3 text-center">
                                    @if($e->ok)
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 ring-2 ring-emerald-50">
                                            <i class="fa-solid fa-check text-sm"></i>
                                        </span>
                                    @else
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 text-rose-700 ring-2 ring-rose-50">
                                            <i class="fa-solid fa-xmark text-sm"></i>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="text-xs font-bold text-slate-800">{{ $e->created_at->format('d/m H:i') }}</div>
                                    <div class="text-[10px] text-slate-400">
                                        <i class="fa-regular fa-clock"></i> {{ $e->created_at->diffForHumans() }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600 flex-shrink-0">
                                            <i class="fa-solid fa-building text-xs"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800">{{ $e->tenant?->nombre ?? '—' }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ $e->tenant?->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold border {{ $colorTipo }}">
                                        <i class="fa-solid {{ $iconoTipo }}"></i>
                                        {{ ucfirst($e->tipo) }}
                                    </span>
                                    @if($e->etapa)
                                        <div class="text-[10px] text-slate-600 mt-1.5 flex items-center gap-1.5">
                                            <i class="fa-solid {{ $iconoEtapa }} text-slate-400"></i>
                                            <span>{{ $labelEtapa }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @php
                                        $canal = $e->canal ?: 'whatsapp';
                                        $destino = $e->telefono;
                                        $esEmail = $canal === 'email' || (is_string($destino) && str_contains($destino, '@'));
                                    @endphp
                                    @if(!$destino)
                                        <span class="text-slate-400 text-xs inline-flex items-center gap-1">
                                            <i class="fa-solid fa-circle-question"></i> sin destinatario
                                        </span>
                                    @elseif($esEmail)
                                        <div class="inline-flex items-center gap-2">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-blue-600 flex-shrink-0" title="Email">
                                                <i class="fa-solid fa-envelope text-xs"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="text-[9px] uppercase tracking-wider text-blue-600 font-bold">Email</div>
                                                <div class="text-xs text-slate-700 break-all">{{ $destino }}</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="inline-flex items-center gap-2">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 flex-shrink-0" title="WhatsApp">
                                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="text-[9px] uppercase tracking-wider text-emerald-600 font-bold">WhatsApp</div>
                                                <div class="text-xs text-slate-700 font-mono">+{{ $destino }}</div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    @if($e->monto)
                                        <div class="font-bold text-slate-800">${{ number_format((float)$e->monto, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $e->moneda }}</div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top max-w-md">
                                    @if($e->ok)
                                        <details class="cursor-pointer group">
                                            <summary class="text-xs text-slate-600 hover:text-slate-900 line-clamp-1 list-none flex items-center gap-1">
                                                <i class="fa-solid fa-chevron-right text-[8px] text-slate-400 group-open:rotate-90 transition"></i>
                                                <span>{{ \Illuminate\Support\Str::limit(str_replace("\n", ' · ', $e->mensaje), 100) }}</span>
                                            </summary>
                                            <div class="mt-2 space-y-2">
                                                <pre class="whitespace-pre-wrap text-[11px] bg-slate-50 rounded-lg p-3 border border-slate-200 font-sans leading-relaxed">{{ $e->mensaje }}</pre>
                                                @if($e->link_pago)
                                                    <a href="{{ $e->link_pago }}" target="_blank"
                                                       class="inline-flex items-center gap-1.5 text-[11px] text-blue-600 hover:text-blue-800 font-semibold">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                        Abrir link Wompi
                                                    </a>
                                                @endif
                                            </div>
                                        </details>
                                    @else
                                        <div class="flex items-start gap-1.5 text-xs text-rose-700 font-semibold">
                                            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                                            <span>{{ \Illuminate\Support\Str::limit($e->error ?? 'Sin detalle del error', 200) }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center align-top">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                            <i class="fa-solid fa-rotate-right"></i>
                                            {{ $e->intentos ?? 1 }} {{ ($e->intentos ?? 1) === 1 ? 'intento' : 'intentos' }}
                                        </span>
                                        @if($e->ultimo_intento_at)
                                            <span class="text-[9px] text-slate-400">
                                                último: {{ $e->ultimo_intento_at->diffForHumans() }}
                                            </span>
                                        @endif
                                        @if(!$e->ok)
                                            <button type="button"
                                                    onclick="confirmarReintento({{ $e->id }}, '{{ addslashes($e->tenant?->nombre ?? 'tenant') }}', '{{ $e->telefono }}', {{ $e->intentos ?? 1 }})"
                                                    class="mt-1 inline-flex items-center gap-1 rounded-lg bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 text-[11px] font-bold shadow-sm transition">
                                                <i class="fa-solid fa-rotate-right"></i>
                                                Reintentar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $this->envios->links() }}
            </div>
        @endif
    </div>

    {{-- 🎨 Script para confirmación y feedback estilo SweetAlert2 --}}
    <script>
        // Confirmar reintento con modal bonito
        window.confirmarReintento = function (envioId, tenantNombre, telefono, intentos) {
            Swal.fire({
                title: '¿Reintentar envío?',
                html: `
                    <div style="text-align: left; font-size: 14px; line-height: 1.6;">
                        <div style="background: #f1f5f9; border-radius: 12px; padding: 16px; margin: 12px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <span style="color: #64748b;">Tenant:</span>
                                <strong style="color: #0f172a;">${tenantNombre}</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <span style="color: #64748b;">Teléfono:</span>
                                <code style="background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 12px;">+${telefono || '(sin tel)'}</code>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #64748b;">Intentos previos:</span>
                                <span style="background: #fef3c7; color: #92400e; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;">${intentos}</span>
                            </div>
                        </div>
                        <p style="color: #64748b; font-size: 13px;">Se enviará el mismo mensaje al mismo destinatario. Si vuelve a fallar, podrás ver el error actualizado.</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-paper-plane"></i> Sí, reintentar ahora',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                customClass: { confirmButton: 'swal2-confirm', cancelButton: 'swal2-cancel' },
            }).then(result => {
                if (!result.isConfirmed) return;

                // Modal de loading mientras procesa
                Swal.fire({
                    title: 'Enviando...',
                    html: `
                        <div style="text-align: center; padding: 20px 0;">
                            <div style="display: inline-block; width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                <i class="fa-solid fa-paper-plane" style="font-size: 28px; color: white; animation: pulse 1.5s infinite;"></i>
                            </div>
                            <p style="color: #64748b; font-size: 13px;">Reintentando envío a <strong>+${telefono}</strong>...</p>
                            <div style="margin-top: 12px;">
                                <div style="height: 4px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                                    <div style="height: 100%; background: linear-gradient(90deg, #3b82f6, #06b6d4); border-radius: 999px; animation: barProgress 2s ease-in-out infinite;"></div>
                                </div>
                            </div>
                        </div>
                        <style>
                            @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
                            @keyframes barProgress { 0% { width: 0%; margin-left: 0; } 50% { width: 70%; margin-left: 15%; } 100% { width: 0%; margin-left: 100%; } }
                        </style>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                });

                // Llamar al método Livewire
                @this.call('reintentar', envioId);
            });
        };

        // Escuchar el resultado del reintento desde Livewire
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('reintento-resultado', payload => {
                const data = Array.isArray(payload) ? payload[0] : payload;
                if (data.ok) {
                    Swal.fire({
                        title: '¡Enviado!',
                        html: `
                            <div style="text-align: center; padding: 10px 0;">
                                <div style="display: inline-block; width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 10px 30px rgba(16,185,129,0.3);">
                                    <i class="fa-solid fa-check" style="font-size: 36px; color: white;"></i>
                                </div>
                                <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                                    Mensaje entregado correctamente a<br>
                                    <strong style="color: #0f172a; font-size: 16px;">+${data.telefono || ''}</strong>
                                </p>
                                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 10px 14px; margin-top: 14px; font-size: 12px; color: #065f46;">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Intentos totales: ${data.intentos}
                                </div>
                            </div>
                        `,
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true,
                    });
                } else {
                    Swal.fire({
                        title: 'Falló de nuevo',
                        html: `
                            <div style="text-align: left; padding: 10px 0;">
                                <div style="display: flex; gap: 12px; align-items: flex-start; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 14px; margin-bottom: 12px;">
                                    <div style="flex-shrink: 0; width: 38px; height: 38px; border-radius: 10px; background: #fee2e2; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-circle-exclamation" style="color: #dc2626; font-size: 18px;"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <p style="margin: 0; font-weight: 700; color: #991b1b; font-size: 13px;">Detalle del error</p>
                                        <p style="margin: 4px 0 0; color: #7f1d1d; font-size: 12px; line-height: 1.5; word-break: break-word;">
                                            ${data.error || 'Sin detalle disponible'}
                                        </p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b;">
                                    <span><i class="fa-solid fa-phone"></i> +${data.telefono || ''}</span>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 999px; font-weight: 700;">
                                        ${data.intentos} intento${data.intentos === 1 ? '' : 's'}
                                    </span>
                                </div>
                                <p style="color: #64748b; font-size: 11px; margin-top: 12px; line-height: 1.5;">
                                    💡 <strong>Sugerencia:</strong> revisa el tenant en <code style="background:#f1f5f9; padding:2px 6px; border-radius:4px;">/admin/tenants</code> o el panel <code style="background:#f1f5f9; padding:2px 6px; border-radius:4px;">/admin/configuracion-plataforma</code> → Conexiones WhatsApp.
                                </p>
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonText: 'Entendido',
                        customClass: { confirmButton: 'swal2-confirm' },
                    });
                }
            });
        });
    </script>
</div>
