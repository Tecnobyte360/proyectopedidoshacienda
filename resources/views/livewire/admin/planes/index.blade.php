<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg">
                        <i class="fa-solid fa-money-check-dollar text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Planes de Suscripción</h2>
                        <p class="text-sm text-slate-500">Define los precios, límites y features de cada plan</p>
                    </div>
                </div>
                <button wire:click="abrirModalCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nuevo plan
                </button>
            </div>
        </div>

        {{-- CARDS DE PLANES --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($planes as $plan)
                <div class="rounded-2xl bg-white border-2 {{ $plan->activo ? 'border-slate-200' : 'border-rose-200 opacity-75' }} shadow-sm overflow-hidden hover:shadow-lg transition flex flex-col">
                    {{-- Header --}}
                    <div class="p-5 border-b border-slate-100">
                        <div class="flex items-start justify-between mb-2">
                            <div class="min-w-0">
                                <h3 class="text-xl font-extrabold text-slate-800">{{ $plan->nombre }}</h3>
                                <p class="text-xs text-slate-500 font-mono">@/{{ $plan->codigo }}</p>
                            </div>
                            <div class="flex flex-col gap-1 items-end">
                                @if(!$plan->activo)
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">Inactivo</span>
                                @endif
                                @if(!$plan->publico)
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Oculto</span>
                                @endif
                            </div>
                        </div>

                        @if($plan->descripcion)
                            <p class="text-xs text-slate-500 mb-3">{{ $plan->descripcion }}</p>
                        @endif

                        <div class="space-y-1">
                            <div class="text-3xl font-extrabold text-slate-900">
                                {{ $plan->precioFormateado('mensual') }}
                                <span class="text-sm font-medium text-slate-500">/mes</span>
                            </div>
                            @if($plan->precio_anual > 0)
                                <div class="text-xs text-slate-500">
                                    o <strong>{{ $plan->precioFormateado('anual') }}</strong>/año
                                    @if($plan->ahorroAnual())
                                        <span class="text-emerald-600 font-bold">
                                            (ahorras ${{ number_format($plan->ahorroAnual(), 0, ',', '.') }})
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Features y límites --}}
                    <div class="p-5 space-y-3 flex-1">
                        @if(!empty($plan->caracteristicas_extra))
                            <ul class="space-y-1.5 text-sm">
                                @foreach($plan->caracteristicas_extra as $feat)
                                    <li class="flex items-start gap-2">
                                        <i class="fa-solid fa-circle-check text-emerald-500 mt-1 text-xs"></i>
                                        <span class="text-slate-700">{{ $feat }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="rounded-xl bg-slate-50 p-3 text-xs space-y-1">
                            <p class="font-bold text-slate-700 mb-1">Límites:</p>
                            <div class="grid grid-cols-2 gap-2 text-slate-600">
                                <div><i class="fa-solid fa-bag-shopping text-slate-400"></i> Pedidos: <b>{{ $plan->max_pedidos_mes ?? '∞' }}/mes</b></div>
                                <div><i class="fa-solid fa-users text-slate-400"></i> Usuarios: <b>{{ $plan->max_usuarios ?? '∞' }}</b></div>
                                <div><i class="fa-solid fa-shop text-slate-400"></i> Sedes: <b>{{ $plan->max_sedes ?? '∞' }}</b></div>
                                <div><i class="fa-solid fa-box text-slate-400"></i> Productos: <b>{{ $plan->max_productos ?? '∞' }}</b></div>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-500">
                            <i class="fa-solid fa-users-line"></i>
                            <strong>{{ $plan->suscripciones_activas_count }}</strong> suscripción(es) activa(s)
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="px-5 pb-5 flex gap-2">
                        <button wire:click="abrirModalEditar({{ $plan->id }})"
                                class="flex-1 text-xs font-bold px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                            <i class="fa-solid fa-pen-to-square"></i> Editar
                        </button>
                        <button wire:click="toggleActivo({{ $plan->id }})"
                                class="text-xs font-bold px-3 py-2 rounded-lg {{ $plan->activo ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} transition">
                            <i class="fa-solid {{ $plan->activo ? 'fa-pause' : 'fa-play' }}"></i>
                        </button>
                        <button wire:click="eliminar({{ $plan->id }})"
                                wire:confirm="¿Eliminar el plan '{{ $plan->nombre }}'?"
                                class="text-xs font-bold px-3 py-2 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-brand-soft/40 via-white to-white border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow">
                            <i class="fa-solid fa-money-check-dollar"></i>
                        </div>
                        <h3 class="text-lg font-extrabold text-slate-800">{{ $editandoId ? 'Editar plan' : 'Nuevo plan' }}</h3>
                    </div>
                    <button wire:click="cerrarModal" class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Código *</label>
                            <input type="text" wire:model="codigo" placeholder="basico"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                            @error('codigo') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="Plan Básico"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Descripción</label>
                            <input type="text" wire:model="descripcion" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Precio mensual</label>
                            <input type="number" step="1000" wire:model="precio_mensual"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Precio anual</label>
                            <input type="number" step="1000" wire:model="precio_anual"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                    </div>

                    <div class="rounded-xl border-2 border-slate-200 p-4 space-y-3">
                        <h4 class="font-bold text-sm text-slate-800">Límites <span class="text-xs text-slate-400 font-normal">(vacío = ilimitado)</span></h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Pedidos/mes</label>
                                <input type="number" wire:model="max_pedidos_mes" placeholder="∞" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Usuarios</label>
                                <input type="number" wire:model="max_usuarios" placeholder="∞" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Sedes</label>
                                <input type="number" wire:model="max_sedes" placeholder="∞" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Productos</label>
                                <input type="number" wire:model="max_productos" placeholder="∞" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Clientes</label>
                                <input type="number" wire:model="max_clientes" placeholder="∞" class="w-full rounded-lg border-slate-200 text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border-2 border-slate-200 p-4 space-y-2">
                        <h4 class="font-bold text-sm text-slate-800 mb-2">Features incluidas</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" wire:model="feature_whatsapp" class="rounded text-brand"> WhatsApp
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" wire:model="feature_ia" class="rounded text-brand"> Bot IA
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" wire:model="feature_reportes" class="rounded text-brand"> Reportes
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" wire:model="feature_multi_sede" class="rounded text-brand"> Multi-sede
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" wire:model="feature_api" class="rounded text-brand"> API REST
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">
                            Características destacadas <span class="text-xs text-slate-400 font-normal">(una por línea)</span>
                        </label>
                        <textarea wire:model="caracteristicas_extra_text" rows="5"
                                  placeholder="Hasta 3 sedes&#10;1.500 pedidos/mes&#10;Bot avanzado"
                                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="activo" class="rounded text-brand">
                            <span class="text-sm">Plan activo</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="publico" class="rounded text-brand">
                            <span class="text-sm">Visible públicamente</span>
                        </label>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Orden</label>
                            <input type="number" wire:model="orden" class="w-full rounded-lg border-slate-200 text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-6 py-2.5 text-sm font-bold text-white shadow-lg">
                            <i class="fa-solid fa-floppy-disk"></i> {{ $editandoId ? 'Actualizar plan' : 'Crear plan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
