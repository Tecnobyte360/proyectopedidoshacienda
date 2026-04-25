<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg">
                        <i class="fa-solid fa-receipt text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Suscripciones</h2>
                        <p class="text-sm text-slate-500">Gestión de planes contratados por cada tenant</p>
                    </div>
                </div>
                <button wire:click="abrirModalCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nueva suscripción
                </button>
            </div>
        </div>

        {{-- KPIS BILLING --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Total</p>
                <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ $kpis['total'] }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-emerald-200 p-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-emerald-600 font-bold">Activas</p>
                <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ $kpis['activas'] }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-violet-200 p-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-violet-600 font-bold">MRR</p>
                <p class="text-2xl font-extrabold text-violet-700 mt-1">${{ number_format($kpis['mrr'], 0, ',', '.') }}</p>
                <p class="text-[10px] text-slate-500">por mes</p>
            </div>
            <div class="rounded-2xl bg-white border border-blue-200 p-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-blue-600 font-bold">ARR</p>
                <p class="text-2xl font-extrabold text-blue-700 mt-1">${{ number_format($kpis['arr'], 0, ',', '.') }}</p>
                <p class="text-[10px] text-slate-500">anual</p>
            </div>
            <div class="rounded-2xl bg-white border border-amber-200 p-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-amber-600 font-bold">Por vencer</p>
                <p class="text-2xl font-extrabold text-amber-700 mt-1">{{ $kpis['por_vencer'] }}</p>
                <p class="text-[10px] text-slate-500">7 días</p>
            </div>
            <div class="rounded-2xl bg-white border border-rose-200 p-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-wider text-rose-600 font-bold">Vencidas</p>
                <p class="text-2xl font-extrabold text-rose-700 mt-1">{{ $kpis['vencidas'] }}</p>
            </div>
        </div>

        {{-- FILTROS --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" wire:model.live.debounce.400ms="busqueda"
                       placeholder="Buscar por tenant..."
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20">
            </div>
            <select wire:model.live="filtroEstado" class="rounded-xl border-slate-200 text-sm">
                <option value="todas">Todos los estados</option>
                <option value="activa">Activas</option>
                <option value="en_trial">En trial</option>
                <option value="suspendida">Suspendidas</option>
                <option value="cancelada">Canceladas</option>
                <option value="expirada">Expiradas</option>
            </select>
        </div>

        {{-- TABLA --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Tenant</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Plan</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Ciclo</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Monto</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Vence</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                            <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($suscripciones as $s)
                            <tr class="hover:bg-amber-50/30">
                                <td class="px-4 py-3.5 font-bold text-slate-800">{{ $s->tenant?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="text-xs font-bold px-2 py-1 rounded-full bg-violet-100 text-violet-700">
                                        {{ $s->plan?->nombre ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-xs capitalize text-slate-600">{{ $s->ciclo }}</td>
                                <td class="px-4 py-3.5 font-bold text-slate-800">
                                    ${{ number_format($s->monto, 0, ',', '.') }} {{ $s->moneda }}
                                </td>
                                <td class="px-4 py-3.5 text-xs">
                                    @php $dias = $s->diasParaVencer(); @endphp
                                    <div class="text-slate-700">{{ $s->fecha_fin?->format('d/m/Y') }}</div>
                                    @if($dias !== null)
                                        @if($dias < 0)
                                            <span class="text-rose-600 font-bold">Vencida hace {{ abs($dias) }}d</span>
                                        @elseif($dias <= 7)
                                            <span class="text-amber-600 font-bold">En {{ $dias }}d</span>
                                        @else
                                            <span class="text-slate-500">En {{ $dias }}d</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @php
                                        $colorEstado = [
                                            'activa'    => 'bg-emerald-100 text-emerald-700',
                                            'en_trial'  => 'bg-blue-100 text-blue-700',
                                            'suspendida'=> 'bg-amber-100 text-amber-700',
                                            'cancelada' => 'bg-rose-100 text-rose-700',
                                            'expirada'  => 'bg-slate-100 text-slate-600',
                                        ][$s->estado] ?? 'bg-slate-100 text-slate-600';
                                    @endphp
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $colorEstado }}">
                                        {{ ucfirst(str_replace('_', ' ', $s->estado)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <button wire:click="abrirModalEditar({{ $s->id }})"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    @if(in_array($s->estado, ['activa', 'en_trial']))
                                        <button wire:click="cancelar({{ $s->id }})"
                                                wire:confirm="¿Cancelar esta suscripción? El tenant quedará suspendido."
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600">
                                            <i class="fa-solid fa-ban text-xs"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-8 text-slate-400">Sin suscripciones</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $suscripciones->links() }}</div>
        </div>
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-brand-soft/40 via-white to-white border-b border-slate-100">
                    <h3 class="text-lg font-extrabold text-slate-800">{{ $editandoId ? 'Editar suscripción' : 'Nueva suscripción' }}</h3>
                    <button wire:click="cerrarModal" class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Tenant *</label>
                            <select wire:model="tenant_id" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="">— Selecciona —</option>
                                @foreach($tenants as $t)
                                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                @endforeach
                            </select>
                            @error('tenant_id') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Plan *</label>
                            <select wire:model.live="plan_id" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="">— Selecciona —</option>
                                @foreach($planes as $p)
                                    <option value="{{ $p->id }}">{{ $p->nombre }} ({{ $p->precioFormateado() }}/mes)</option>
                                @endforeach
                            </select>
                            @error('plan_id') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Ciclo *</label>
                            <select wire:model.live="ciclo" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="mensual">Mensual</option>
                                <option value="anual">Anual</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado *</label>
                            <select wire:model="estado" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="activa">Activa</option>
                                <option value="en_trial">En trial</option>
                                <option value="suspendida">Suspendida</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Monto *</label>
                            <input type="number" step="1000" wire:model="monto" class="w-full rounded-xl border-slate-200 text-sm">
                            <p class="text-xs text-slate-500 mt-1">Auto-completado del plan, puedes ajustarlo</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Moneda *</label>
                            <input type="text" wire:model="moneda" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Fecha inicio *</label>
                            <input type="date" wire:model="fecha_inicio" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Fecha fin (vencimiento) *</label>
                            <input type="date" wire:model="fecha_fin" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas</label>
                        <textarea wire:model="notas" rows="2" class="w-full rounded-xl border-slate-200 text-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-6 py-2.5 text-sm font-bold text-white shadow-lg">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> {{ $editandoId ? 'Actualizar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
