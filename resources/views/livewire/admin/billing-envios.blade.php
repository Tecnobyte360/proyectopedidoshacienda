<div class="p-4 md:p-6 max-w-7xl mx-auto space-y-5">

    {{-- HEADER --}}
    <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-lg">
                <i class="fa-solid fa-paper-plane text-xl"></i>
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-extrabold text-slate-800">Monitoreo de envíos SaaS</h1>
                <p class="text-sm text-slate-500">Historial de WhatsApps de facturación, recordatorios y suspensión enviados a tus tenants</p>
            </div>
            <select wire:model.live="rango" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="hoy">Hoy</option>
                <option value="7d">Últimos 7 días</option>
                <option value="30d">Últimos 30 días</option>
                <option value="mes">Mes en curso</option>
            </select>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-7 gap-3">
        <div class="bg-white border border-slate-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-slate-500 font-bold">Total</div>
            <div class="text-2xl font-bold text-slate-800">{{ $this->kpis['total'] }}</div>
        </div>
        <div class="bg-white border border-emerald-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-emerald-600 font-bold">Enviados ✓</div>
            <div class="text-2xl font-bold text-emerald-700">{{ $this->kpis['ok'] }}</div>
        </div>
        <div class="bg-white border border-rose-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-rose-600 font-bold">Fallidos ✗</div>
            <div class="text-2xl font-bold text-rose-700">{{ $this->kpis['fallidos'] }}</div>
        </div>
        <div class="bg-white border border-sky-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-sky-600 font-bold">🧾 Facturas</div>
            <div class="text-2xl font-bold text-sky-700">{{ $this->kpis['facturas'] }}</div>
        </div>
        <div class="bg-white border border-amber-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-amber-600 font-bold">⏰ Recordatorios</div>
            <div class="text-2xl font-bold text-amber-700">{{ $this->kpis['recordat'] }}</div>
        </div>
        <div class="bg-white border border-red-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-red-600 font-bold">🚫 Suspendido</div>
            <div class="text-2xl font-bold text-red-700">{{ $this->kpis['suspendido'] }}</div>
        </div>
        <div class="bg-white border border-violet-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-violet-600 font-bold">Tenants</div>
            <div class="text-2xl font-bold text-violet-700">{{ $this->kpis['tenants_afct'] }}</div>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <input type="text" wire:model.live.debounce.400ms="busqueda"
                   placeholder="Buscar por teléfono, mensaje o error..."
                   class="md:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">

            <select wire:model.live="filtroTenant" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos los tenants</option>
                @foreach($this->tenants as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                @endforeach
            </select>

            <select wire:model.live="filtroTipo" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos los tipos</option>
                @foreach(\App\Models\SaasBillingEnvio::TIPOS as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>

            <select wire:model.live="filtroOk" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Todos los resultados</option>
                <option value="1">✓ Solo exitosos</option>
                <option value="0">✗ Solo fallidos</option>
            </select>
        </div>
    </div>

    {{-- TABLA --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        @if($this->envios->isEmpty())
            <div class="p-10 text-center text-slate-400">
                <i class="fa-solid fa-paper-plane text-4xl mb-2 text-slate-300"></i>
                <p class="text-sm">Sin envíos en el período seleccionado.</p>
                <p class="text-xs mt-1">Los registros aparecen automáticamente cuando el cron envía recordatorios o facturas.</p>
            </div>
        @else
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold">
                    <tr>
                        <th class="px-3 py-2 text-center w-10">OK</th>
                        <th class="px-3 py-2 text-left w-40">Cuándo</th>
                        <th class="px-3 py-2 text-left">Tenant</th>
                        <th class="px-3 py-2 text-left w-32">Tipo · Etapa</th>
                        <th class="px-3 py-2 text-left w-32">Teléfono</th>
                        <th class="px-3 py-2 text-right w-28">Monto</th>
                        <th class="px-3 py-2 text-left">Mensaje / Error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($this->envios as $e)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-center">
                                @if($e->ok)
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </span>
                                @else
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="text-[11px] font-semibold text-slate-700">{{ $e->created_at->format('d/m H:i') }}</div>
                                <div class="text-[9px] text-slate-400">{{ $e->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="font-semibold text-slate-800">{{ $e->tenant?->nombre ?? '—' }}</div>
                                <div class="text-[9px] text-slate-400 font-mono">{{ $e->tenant?->slug }}</div>
                            </td>
                            <td class="px-3 py-2 align-top">
                                @php
                                    $colorTipo = match($e->tipo) {
                                        'factura'      => 'bg-sky-100 text-sky-700',
                                        'recordatorio' => 'bg-amber-100 text-amber-700',
                                        'suspendido'   => 'bg-rose-100 text-rose-700',
                                        default        => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold {{ $colorTipo }}">
                                    {{ $e->tipoLabel() }}
                                </span>
                                @if($e->etapa)
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ $e->etapaLabel() }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top font-mono text-[11px] text-slate-700">
                                @if($e->telefono)
                                    +{{ $e->telefono }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right align-top font-semibold text-slate-700">
                                @if($e->monto)
                                    ${{ number_format((float)$e->monto, 0, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">
                                @if($e->ok)
                                    <details class="cursor-pointer">
                                        <summary class="text-[11px] text-slate-600 hover:text-slate-900 line-clamp-1">
                                            {{ \Illuminate\Support\Str::limit(str_replace("\n", ' · ', $e->mensaje), 100) }}
                                        </summary>
                                        <pre class="mt-2 whitespace-pre-wrap text-[10px] bg-slate-50 rounded p-2 border border-slate-200">{{ $e->mensaje }}</pre>
                                        @if($e->link_pago)
                                            <a href="{{ $e->link_pago }}" target="_blank" class="inline-block mt-1 text-[10px] text-blue-600 hover:underline">
                                                <i class="fa-solid fa-link"></i> Ver link Wompi
                                            </a>
                                        @endif
                                    </details>
                                @else
                                    <div class="text-[11px] text-rose-700 font-semibold">
                                        ❌ {{ \Illuminate\Support\Str::limit($e->error ?? 'Sin detalle', 100) }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $this->envios->links() }}
            </div>
        @endif
    </div>
</div>
