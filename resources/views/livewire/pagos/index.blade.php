<div class="px-6 lg:px-10 py-8" wire:poll.30s="actualizar">

    {{-- HEADER + FILTROS --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800">Pagos</h2>
            <p class="text-sm text-slate-500">Transacciones de Wompi de tus pedidos.</p>
        </div>

        <div class="inline-flex items-center rounded-xl bg-white shadow p-1">
            @foreach(['hoy' => 'Hoy', 'semana' => '7 días', 'mes' => '30 días', 'trimestre' => '90 días', 'todo' => 'Todo'] as $key => $label)
                <button wire:click="$set('rango', '{{ $key }}')"
                        class="px-4 py-2 text-xs font-semibold rounded-lg transition
                              {{ $rango === $key ? 'bg-brand text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @php $k = $this->kpis; @endphp

    {{-- KPIs --}}
    <div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="rounded-2xl p-5 text-white shadow-lg"
             style="background: linear-gradient(135deg, var(--brand, #7c3aed), var(--brand-secondary, #a855f7));">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Cobrado</span>
                <i class="fa-solid fa-circle-check text-2xl opacity-60"></i>
            </div>
            <div class="text-3xl font-extrabold">${{ number_format($k['total_cobrado'], 0, ',', '.') }}</div>
            <div class="text-xs opacity-80 mt-1">{{ $k['count_aprobado'] }} pagos aprobados</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pendiente</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">${{ number_format($k['total_pendiente'], 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $k['count_pendiente'] }} pedidos sin pagar</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rechazados</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $k['count_rechazado'] }}</div>
            <div class="text-xs text-slate-500 mt-1">en este periodo</div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Conversión</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-percent"></i>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-slate-800">{{ $k['tasa_conversion'] }}%</div>
            <div class="text-xs text-emerald-600 mt-1 font-semibold">aprobados sobre total</div>
        </div>
    </div>

    {{-- Métodos de pago --}}
    @if(!empty($this->metodosPago))
        <div class="rounded-2xl bg-white p-5 shadow mb-6">
            <h3 class="font-bold text-slate-800 mb-3 text-sm">Métodos de pago utilizados</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($this->metodosPago as $m)
                    @php
                        $iconos = [
                            'CARD' => ['fa-credit-card', 'bg-blue-100 text-blue-700'],
                            'NEQUI' => ['fa-mobile-screen', 'bg-pink-100 text-pink-700'],
                            'PSE' => ['fa-building-columns', 'bg-emerald-100 text-emerald-700'],
                            'BANCOLOMBIA_TRANSFER' => ['fa-building-columns', 'bg-amber-100 text-amber-700'],
                            'BANCOLOMBIA_COLLECT' => ['fa-receipt', 'bg-amber-100 text-amber-700'],
                        ];
                        [$icon, $color] = $iconos[$m['metodo']] ?? ['fa-money-bill', 'bg-slate-100 text-slate-700'];
                    @endphp
                    <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $color }}">
                            <i class="fa-solid {{ $icon }} text-xs"></i>
                        </span>
                        <div>
                            <div class="text-xs font-bold text-slate-800">{{ ucfirst(str_replace('_', ' ', strtolower($m['metodo']))) }}</div>
                            <div class="text-[10px] text-slate-500">{{ $m['total'] }} pagos · ${{ number_format($m['monto'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filtros + búsqueda --}}
    <div class="rounded-2xl bg-white shadow p-4 mb-4 flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.500ms="busqueda"
               placeholder="Buscar por referencia, transaction id, cliente, teléfono o # pedido..."
               class="flex-1 min-w-[260px] rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">

        <select wire:model.live="estado"
                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
            <option value="">Todos los estados</option>
            <option value="aprobado">Aprobado</option>
            <option value="pendiente">Pendiente</option>
            <option value="rechazado">Rechazado</option>
            <option value="fallido">Fallido</option>
            <option value="reembolsado">Reembolsado</option>
        </select>
    </div>

    {{-- Tabla --}}
    <div class="rounded-2xl bg-white shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase font-bold">
                    <tr>
                        <th class="px-4 py-3 text-left">Pedido</th>
                        <th class="px-4 py-3 text-left">Cliente</th>
                        <th class="px-4 py-3 text-left">Referencia</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-left">Estado pago</th>
                        <th class="px-4 py-3 text-left">Método</th>
                        <th class="px-4 py-3 text-left">Pagado</th>
                        <th class="px-4 py-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pagos as $p)
                        @php
                            $colores = [
                                'aprobado'    => 'bg-emerald-100 text-emerald-700',
                                'pendiente'   => 'bg-amber-100 text-amber-700',
                                'rechazado'   => 'bg-red-100 text-red-700',
                                'fallido'     => 'bg-red-100 text-red-700',
                                'reembolsado' => 'bg-slate-200 text-slate-700',
                            ];
                            $colorEstado = $colores[$p->estado_pago] ?? 'bg-slate-100 text-slate-600';
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800">#{{ $p->id }}</div>
                                <div class="text-[10px] text-slate-400">{{ $p->fecha_pedido?->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-700 truncate max-w-[160px]">{{ $p->cliente_nombre }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $p->telefono_whatsapp }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <code class="text-[11px] text-slate-600">{{ $p->wompi_reference }}</code>
                                @if($p->wompi_transaction_id)
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">tx: {{ $p->wompi_transaction_id }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-slate-800">
                                ${{ number_format((float) $p->total, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold {{ $colorEstado }}">
                                    {{ ucfirst($p->estado_pago ?? 'pendiente') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $p->pago_metodo ? ucfirst(str_replace('_', ' ', strtolower($p->pago_metodo))) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $p->pagado_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($p->codigo_seguimiento)
                                    <a href="{{ url('/seguimiento-pedido/' . $p->codigo_seguimiento) }}" target="_blank"
                                       class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700"
                                       title="Ver seguimiento">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </a>
                                @endif
                                @if($p->wompi_reference)
                                    <button type="button"
                                            wire:click="sincronizarConWompi({{ $p->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="sincronizarConWompi({{ $p->id }})"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 ml-1 disabled:opacity-50"
                                            title="Sincronizar estado con Wompi (usar cuando el webhook no llegó)">
                                        <span wire:loading.remove wire:target="sincronizarConWompi({{ $p->id }})">
                                            <i class="fa-solid fa-arrows-rotate text-xs"></i>
                                        </span>
                                        <span wire:loading wire:target="sincronizarConWompi({{ $p->id }})">
                                            <i class="fa-solid fa-spinner fa-spin text-xs"></i>
                                        </span>
                                    </button>
                                @endif

                                @if(in_array($p->estado_pago, ['pendiente', 'rechazado', 'fallido']))
                                    <a href="{{ $p->urlPagoWompi() }}" target="_blank"
                                       class="inline-flex items-center justify-center h-8 px-3 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-[11px] font-bold ml-1"
                                       title="Abrir link de pago">
                                        💳 Pagar
                                    </a>
                                    <button type="button"
                                            wire:click="regenerarLink({{ $p->id }})"
                                            wire:confirm="Generar una nueva referencia de pago? Esto invalida el link anterior."
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 ml-1"
                                            title="Generar nuevo link (rotar referencia)">
                                        <i class="fa-solid fa-rotate text-xs"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-circle-dollar-to-slot text-4xl mb-2 block opacity-50"></i>
                                <p class="text-sm">No hay pagos en este periodo.</p>
                                <p class="text-[11px] mt-1">Los pagos aparecen cuando un cliente paga via Wompi.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pagos->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $pagos->links() }}
            </div>
        @endif
    </div>

</div>
