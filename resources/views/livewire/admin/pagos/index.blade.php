<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg">
                        <i class="fa-solid fa-money-bills text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Pagos recibidos</h2>
                        <p class="text-sm text-slate-500">Registro manual de pagos de tus tenants</p>
                    </div>
                </div>
                <button wire:click="abrirModalCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-circle-dollar-to-slot"></i> Registrar pago
                </button>
            </div>
        </div>

        {{-- KPIS --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white border border-emerald-200 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-emerald-600 font-bold">Hoy</p>
                <p class="text-2xl font-extrabold text-emerald-700 mt-1">${{ number_format($kpis['hoy'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-blue-200 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-blue-600 font-bold">Este mes</p>
                <p class="text-2xl font-extrabold text-blue-700 mt-1">${{ number_format($kpis['mes'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-violet-200 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-violet-600 font-bold">Este año</p>
                <p class="text-2xl font-extrabold text-violet-700 mt-1">${{ number_format($kpis['anio'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-amber-200 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-amber-600 font-bold">Pendientes</p>
                <p class="text-2xl font-extrabold text-amber-700 mt-1">{{ $kpis['pendientes'] }}</p>
            </div>
        </div>

        {{-- FILTROS --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar por tenant..."
                   class="rounded-xl border-slate-200 text-sm">
            <select wire:model.live="filtroEstado" class="rounded-xl border-slate-200 text-sm">
                <option value="todos">Todos los estados</option>
                <option value="pendiente">Pendientes</option>
                <option value="confirmado">Confirmados</option>
                <option value="rechazado">Rechazados</option>
            </select>
            <select wire:model.live="filtroMetodo" class="rounded-xl border-slate-200 text-sm">
                <option value="todos">Todos los métodos</option>
                @foreach(\App\Models\Pago::METODOS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
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
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Monto</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Método</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Cubre</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Fecha</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</th>
                            <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($pagos as $p)
                            <tr class="hover:bg-amber-50/30">
                                <td class="px-4 py-3.5 font-bold text-slate-800">{{ $p->tenant?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-xs">
                                    @if($p->suscripcion?->plan)
                                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-violet-100 text-violet-700">
                                            {{ $p->suscripcion->plan->nombre }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 font-extrabold text-emerald-700">
                                    ${{ number_format($p->monto, 0, ',', '.') }}
                                    <span class="text-[10px] text-slate-500">{{ $p->moneda }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-700">{{ $p->metodoLabel() }}
                                    @if($p->referencia)
                                        <div class="text-[10px] text-slate-400 font-mono">#{{ $p->referencia }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-600">
                                    @if($p->cubre_desde && $p->cubre_hasta)
                                        {{ $p->cubre_desde->format('d/m') }} → {{ $p->cubre_hasta->format('d/m/Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-700">
                                    {{ $p->fecha_pago?->format('d/m/Y') }}
                                    @if($p->registradoPor)
                                        <div class="text-[10px] text-slate-400">por {{ $p->registradoPor->name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @php
                                        $colorEst = ['confirmado' => 'bg-emerald-100 text-emerald-700', 'pendiente' => 'bg-amber-100 text-amber-700', 'rechazado' => 'bg-rose-100 text-rose-700'][$p->estado] ?? 'bg-slate-100';
                                    @endphp
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $colorEst }}">
                                        {{ ucfirst($p->estado) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    @if($p->comprobante_url)
                                        <a href="{{ $p->comprobante_url }}" target="_blank" title="Ver comprobante"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600">
                                            <i class="fa-solid fa-receipt text-xs"></i>
                                        </a>
                                    @endif
                                    <button wire:click="abrirModalEditar({{ $p->id }})"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    <button wire:click="eliminar({{ $p->id }})"
                                            wire:confirm="¿Eliminar este pago?"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-8 text-slate-400">Sin pagos registrados</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $pagos->links() }}</div>
        </div>
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white border-b border-slate-100">
                    <h3 class="text-lg font-extrabold text-slate-800">{{ $editandoId ? 'Editar pago' : 'Registrar nuevo pago' }}</h3>
                    <button wire:click="cerrarModal" class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Tenant *</label>
                        <select wire:model.live="tenant_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— Selecciona —</option>
                            @foreach($tenants as $t)
                                <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                        @error('tenant_id') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Monto *</label>
                            <input type="number" step="1000" wire:model="monto" class="w-full rounded-xl border-slate-200 text-sm">
                            @error('monto') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Moneda *</label>
                            <input type="text" wire:model="moneda" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Método *</label>
                            <select wire:model="metodo" class="w-full rounded-xl border-slate-200 text-sm">
                                @foreach(\App\Models\Pago::METODOS as $k => $l)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Referencia</label>
                            <input type="text" wire:model="referencia" placeholder="# transacción"
                                   class="w-full rounded-xl border-slate-200 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Fecha de pago *</label>
                            <input type="date" wire:model="fecha_pago" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado *</label>
                            <select wire:model="estado" class="w-full rounded-xl border-slate-200 text-sm">
                                <option value="confirmado">Confirmado</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="rechazado">Rechazado</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Cubre desde</label>
                            <input type="date" wire:model="cubre_desde" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Cubre hasta</label>
                            <input type="date" wire:model="cubre_hasta" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">URL del comprobante (opcional)</label>
                        <input type="url" wire:model="comprobante_url" placeholder="https://..."
                               class="w-full rounded-xl border-slate-200 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas</label>
                        <textarea wire:model="notas" rows="2" class="w-full rounded-xl border-slate-200 text-sm"></textarea>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl bg-emerald-50 border border-emerald-200 p-3 cursor-pointer">
                        <input type="checkbox" wire:model="renovar_suscripcion" class="mt-0.5 rounded text-emerald-500">
                        <div>
                            <div class="text-sm font-bold text-emerald-900">Renovar suscripción automáticamente</div>
                            <div class="text-xs text-emerald-700">Si está marcado, al guardar este pago la fecha_fin de la suscripción se actualiza a "cubre hasta" y se reactiva el tenant.</div>
                        </div>
                    </label>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg">
                            <i class="fa-solid fa-floppy-disk mr-1"></i> {{ $editandoId ? 'Actualizar pago' : 'Registrar pago' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
