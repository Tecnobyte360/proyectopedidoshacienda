<div class="p-4 md:p-6 w-full">

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-coins text-amber-500"></i>
                Costos Meta WhatsApp
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Lo que Meta te está cobrando por usar la API (real-time desde webhooks).
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <x-tenant-view-selector :tenants="$this->tenantes" :selected="$tenantViewId" model="tenantViewId" />
            <select wire:model.live="rango" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs">
                <option value="hoy">Hoy</option>
                <option value="7d">Últimos 7 días</option>
                <option value="30d">Últimos 30 días</option>
                <option value="mes">Mes en curso</option>
            </select>
        </div>
    </div>

    {{-- KPI principal: cuánto vas a pagar este mes --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-4 text-white">
            <div class="text-[11px] uppercase opacity-80 font-bold">USD a pagar</div>
            <div class="text-3xl font-bold">${{ number_format($this->kpis['total_usd'], 4) }}</div>
            <div class="text-[11px] opacity-90 mt-1">
                ≈ ${{ number_format($this->kpis['total_cop'], 0, ',', '.') }} COP
            </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-[11px] uppercase text-slate-500 font-bold">Conversaciones facturables</div>
            <div class="text-3xl font-bold text-slate-800">{{ $this->kpis['total'] }}</div>
            <div class="text-[10px] text-slate-500 mt-1">
                {{ $this->kpis['desde']->format('d/M') }} → {{ $this->kpis['hasta']->format('d/M') }}
            </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-[11px] uppercase text-slate-500 font-bold">Promedio diario</div>
            <div class="text-3xl font-bold text-slate-800">
                ${{ number_format($this->kpis['total_usd'] / max(1, $this->kpis['hasta']->diffInDays($this->kpis['desde']) ?: 1), 4) }}
            </div>
            <div class="text-[10px] text-slate-500 mt-1">USD/día</div>
        </div>
    </div>

    {{-- Desglose por categoría --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-white border border-emerald-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-emerald-600 font-bold">Service (gratis)</div>
            <div class="text-xl font-bold text-emerald-700">{{ $this->kpis['service'] }}</div>
            <div class="text-[9px] text-slate-500">$0.00 USD</div>
        </div>
        <div class="bg-white border border-sky-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-sky-600 font-bold">Utility</div>
            <div class="text-xl font-bold text-sky-700">{{ $this->kpis['utility'] }}</div>
            <div class="text-[9px] text-slate-500">${{ number_format($this->kpis['utility'] * 0.008, 4) }} USD</div>
        </div>
        <div class="bg-white border border-rose-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-rose-600 font-bold">Marketing</div>
            <div class="text-xl font-bold text-rose-700">{{ $this->kpis['marketing'] }}</div>
            <div class="text-[9px] text-slate-500">${{ number_format($this->kpis['marketing'] * 0.0265, 4) }} USD</div>
        </div>
        <div class="bg-white border border-violet-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-violet-600 font-bold">Auth (OTP)</div>
            <div class="text-xl font-bold text-violet-700">{{ $this->kpis['auth'] }}</div>
            <div class="text-[9px] text-slate-500">${{ number_format($this->kpis['auth'] * 0.008, 4) }} USD</div>
        </div>
    </div>

    @if($this->kpis['total'] === 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 mb-4 text-xs text-amber-800 flex items-start gap-2">
            <i class="fa-solid fa-circle-info text-amber-500 mt-0.5"></i>
            <div>
                <strong>Aún no hay eventos de billing registrados en este rango.</strong>
                Si ya estás operando con Meta, los eventos aparecerán apenas reciban el próximo webhook de estado.
                Mientras la app esté en <em>modo desarrollo</em>, la mayoría de conversaciones llegan como
                <em>service / free_customer_service</em> (gratis) — verás el volumen aunque el costo sea $0.
            </div>
        </div>
    @endif

    {{-- Top plantillas + Top clientes lado a lado --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-3 py-2 border-b border-slate-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-ranking-star text-amber-500"></i>
                    Top plantillas/tipos por costo
                </h3>
            </div>
            @if($this->topPlantillas->isEmpty())
                <div class="p-6 text-center text-slate-400 text-xs">Sin datos</div>
            @else
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                        <tr>
                            <th class="px-2 py-1.5 text-left">Tipo / Origen</th>
                            <th class="px-2 py-1.5 text-center w-20">Conv.</th>
                            <th class="px-2 py-1.5 text-right w-24">USD</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($this->topPlantillas as $p)
                            <tr class="hover:bg-amber-50/30">
                                <td class="px-2 py-1.5 font-mono text-slate-700">{{ $p->origin_type ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-center">{{ $p->cnt }}</td>
                                <td class="px-2 py-1.5 text-right font-semibold text-amber-700">${{ number_format($p->usd, 4) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-3 py-2 border-b border-slate-100 bg-slate-50">
                <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <i class="fa-solid fa-users text-sky-500"></i>
                    Top clientes (conversaciones)
                </h3>
            </div>
            @if($this->topClientes->isEmpty())
                <div class="p-6 text-center text-slate-400 text-xs">Sin datos</div>
            @else
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                        <tr>
                            <th class="px-2 py-1.5 text-left">Teléfono</th>
                            <th class="px-2 py-1.5 text-center w-20">Conv.</th>
                            <th class="px-2 py-1.5 text-right w-24">USD</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($this->topClientes as $c)
                            <tr class="hover:bg-sky-50/30">
                                <td class="px-2 py-1.5 font-mono text-slate-700">+{{ $c->telefono }}</td>
                                <td class="px-2 py-1.5 text-center">{{ $c->cnt }}</td>
                                <td class="px-2 py-1.5 text-right font-semibold text-sky-700">${{ number_format($c->usd, 4) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Cheat sheet de precios --}}
    <div class="mt-6 bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-600">
        <p class="font-bold text-slate-700 mb-2"><i class="fa-solid fa-book-open"></i> Cómo cobra Meta (Colombia)</p>
        <ul class="space-y-1 list-disc list-inside">
            <li><strong>Service</strong>: cliente escribe primero → todo dentro de 24h es <span class="text-emerald-600 font-bold">GRATIS</span></li>
            <li><strong>Utility</strong>: tú inicias con plantilla utility (confirmación, recordatorio, OTP) → <span class="font-bold">~$0.0080 USD</span> por conversación 24h</li>
            <li><strong>Authentication</strong>: códigos OTP → <span class="font-bold">~$0.0080 USD</span></li>
            <li><strong>Marketing</strong>: campañas promocionales → <span class="font-bold">~$0.0265 USD</span> por conversación 24h</li>
            <li>Una conversación = ventana de 24h, sin importar cuántos mensajes haya dentro</li>
        </ul>
    </div>
</div>
