<div class="min-h-screen bg-slate-50/50">
    @php
        // Clases reutilizables para inputs (Tailwind v4 ya no estiliza inputs nativos)
        $inputCls = 'w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 px-3.5 py-2.5 shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none disabled:bg-slate-50 disabled:text-slate-500';
        $inputClsIcon = 'w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 pl-10 pr-3.5 py-2.5 shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
        $labelCls = 'block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5';
        $cardCls = 'rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden';
    @endphp

    <div class="px-4 lg:px-8 py-6 max-w-5xl mx-auto pb-32">

        {{-- ═══════════════════════════════════════════════════════════════
             HEADER profesional
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
            <div class="h-1 bg-gradient-to-r from-emerald-500 to-cyan-500"></div>
            <div class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shadow-md">
                        <i class="fa-solid fa-cart-plus text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                            <i class="fa-solid fa-pen-to-square text-[9px]"></i>
                            Pedido manual
                        </div>
                        <h2 class="mt-1.5 text-xl sm:text-2xl font-bold tracking-tight text-slate-800 truncate">
                            @if($conversacionId)
                                Cerrar pedido de conversación #{{ $conversacionId }}
                            @else
                                Crear pedido manual
                            @endif
                        </h2>
                        <p class="text-sm text-slate-500 mt-0.5">
                            @if($conversacionId)
                                <i class="fa-solid fa-comments text-emerald-500 mr-1"></i>
                                Cerrando pedido pre-cargado desde chat
                            @else
                                <i class="fa-solid fa-circle-info text-slate-400 mr-1"></i>
                                Crea un pedido sin pasar por el bot (admin/operador)
                            @endif
                        </p>
                    </div>
                </div>
                <a href="{{ route('pedidos.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-800 transition shadow-sm shrink-0">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                    Volver
                </a>
            </div>
        </div>

        {{-- Mensajes de Livewire --}}
        @if (session()->has('error'))
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg mt-0.5"></i>
                <div class="text-sm text-rose-700 font-medium">{{ session('error') }}</div>
            </div>
        @endif
        @if (session()->has('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 flex items-start gap-3">
                <i class="fa-solid fa-circle-check text-emerald-500 text-lg mt-0.5"></i>
                <div class="text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
            </div>
        @endif

        <form wire:submit.prevent="crearPedido" class="space-y-5">

            {{-- ═══════════════════════════════════════════════════════════
                 👤 CLIENTE
                 ═══════════════════════════════════════════════════════════ --}}
            <div class="{{ $cardCls }}">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50/50 to-transparent">
                    <h3 class="flex items-center gap-2.5 text-sm font-bold text-slate-700">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <span>Datos del cliente</span>
                    </h3>
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-blue-700">
                        <i class="fa-solid fa-asterisk text-[8px]"></i>
                        Requerido
                    </span>
                </div>
                <div class="p-5 grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelCls }}">
                            <i class="fa-solid fa-phone text-slate-400 mr-1"></i>
                            Teléfono <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-brands fa-whatsapp absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500"></i>
                            <input type="text" wire:model="telefono" placeholder="573XXXXXXXXX"
                                   class="{{ $inputClsIcon }}">
                        </div>
                        @error('telefono') <p class="mt-1 text-xs text-rose-600"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">
                            <i class="fa-solid fa-id-card text-slate-400 mr-1"></i>
                            Cédula
                        </label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <i class="fa-solid fa-fingerprint absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" wire:model="cedula" placeholder="1007767612"
                                       class="{{ $inputClsIcon }}">
                            </div>
                            <button type="button" wire:click="buscarPorCedula"
                                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 text-sm font-bold shadow-sm transition shrink-0">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <span class="hidden sm:inline">Buscar</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">
                            <i class="fa-solid fa-signature text-slate-400 mr-1"></i>
                            Nombre completo <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" wire:model="nombre_cliente" placeholder="Edgar Madrid"
                                   class="{{ $inputClsIcon }}">
                        </div>
                        @error('nombre_cliente') <p class="mt-1 text-xs text-rose-600"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">
                            <i class="fa-solid fa-envelope text-slate-400 mr-1"></i>
                            Email
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-at absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="email" wire:model="email" placeholder="cliente@email.com"
                                   class="{{ $inputClsIcon }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════
                 🛒 PRODUCTOS
                 ═══════════════════════════════════════════════════════════ --}}
            <div class="{{ $cardCls }}">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50/50 to-transparent">
                    <h3 class="flex items-center gap-2.5 text-sm font-bold text-slate-700">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </span>
                        <span>Productos del pedido</span>
                    </h3>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                        <i class="fa-solid fa-box text-[9px]"></i>
                        {{ count($productos ?? []) }} {{ count($productos ?? []) === 1 ? 'item' : 'items' }}
                    </span>
                </div>

                <div class="p-5">
                    <div class="relative mb-4">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" wire:model.live.debounce.300ms="busquedaProducto"
                               placeholder="Buscar producto por nombre o código..."
                               class="w-full rounded-xl border border-slate-300 bg-white text-sm pl-11 pr-4 py-3 shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">

                        @if($productosCatalogo->isNotEmpty())
                            <div class="absolute left-0 right-0 top-full mt-2 z-20 rounded-xl border border-slate-200 bg-white shadow-2xl max-h-72 overflow-y-auto">
                                @foreach($productosCatalogo as $p)
                                    <button type="button"
                                            wire:key="prod-{{ $p->codigo }}"
                                            x-on:click="$wire.call('agregarProducto', '{{ $p->codigo }}')"
                                            class="w-full text-left px-4 py-3 hover:bg-emerald-50 active:bg-emerald-100 flex items-center justify-between gap-3 border-b border-slate-100 last:border-b-0 transition">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                                <i class="fa-solid fa-drumstick-bite text-sm"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-slate-800 truncate">{{ $p->nombre }}</div>
                                                <div class="text-[11px] text-slate-500 font-mono">
                                                    <i class="fa-solid fa-barcode mr-1"></i>{{ $p->codigo }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="text-sm font-bold text-emerald-600">
                                                ${{ number_format($p->precio_base ?? 0, 0, ',', '.') }}
                                            </div>
                                            <div class="text-[10px] text-slate-400 uppercase tracking-wider">por {{ $p->unidad }}</div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if(empty($productos))
                        <div class="rounded-xl bg-slate-50 border-2 border-dashed border-slate-300 p-8 text-center">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm mb-3">
                                <i class="fa-solid fa-box-open text-2xl"></i>
                            </div>
                            <p class="text-sm font-semibold text-slate-600">Sin productos</p>
                            <p class="text-xs text-slate-400 mt-1">Busca arriba para agregar productos al pedido</p>
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50">
                                    <tr class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                        <th class="text-left px-3 py-2.5">
                                            <i class="fa-solid fa-tag mr-1"></i>Producto
                                        </th>
                                        <th class="text-center px-2 py-2.5 w-24">
                                            <i class="fa-solid fa-hashtag mr-1"></i>Cant
                                        </th>
                                        <th class="text-center px-2 py-2.5 w-24">
                                            <i class="fa-solid fa-ruler mr-1"></i>Unidad
                                        </th>
                                        <th class="text-right px-2 py-2.5 w-28">
                                            <i class="fa-solid fa-dollar-sign mr-1"></i>Precio
                                        </th>
                                        <th class="text-right px-3 py-2.5 w-28">
                                            <i class="fa-solid fa-coins mr-1"></i>Subtotal
                                        </th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($productos as $idx => $p)
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-3 py-2.5">
                                                <input type="text" wire:model="productos.{{ $idx }}.nombre"
                                                       class="w-full rounded-lg border border-slate-200 bg-white text-sm px-2.5 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none">
                                            </td>
                                            <td class="px-2 py-2.5">
                                                <input type="number" step="0.01" min="0.01"
                                                       wire:model.lazy="productos.{{ $idx }}.cantidad"
                                                       class="w-full rounded-lg border border-slate-200 bg-white text-sm text-center px-2 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none">
                                            </td>
                                            <td class="px-2 py-2.5">
                                                <input type="text" wire:model.lazy="productos.{{ $idx }}.unidad"
                                                       class="w-full rounded-lg border border-slate-200 bg-white text-xs text-center px-1 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none">
                                            </td>
                                            <td class="px-2 py-2.5">
                                                <input type="number" step="1" min="0"
                                                       wire:model.lazy="productos.{{ $idx }}.precio"
                                                       class="w-full rounded-lg border border-slate-200 bg-white text-sm text-right px-2 py-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none">
                                            </td>
                                            <td class="px-3 py-2.5 text-right font-semibold text-slate-800">
                                                ${{ number_format(($p['cantidad'] ?? 0) * ($p['precio'] ?? 0), 0, ',', '.') }}
                                            </td>
                                            <td class="px-2 py-2.5 text-center">
                                                <button type="button" wire:click="eliminarProducto({{ $idx }})"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                                        title="Eliminar">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                                    <tr>
                                        <td colspan="4" class="px-3 py-3 text-right font-bold text-slate-700 uppercase tracking-wider text-xs">
                                            <i class="fa-solid fa-circle-dollar-to-slot mr-1"></i>Total del pedido
                                        </td>
                                        <td class="px-3 py-3 text-right text-lg font-extrabold text-emerald-600">
                                            ${{ number_format($this->total, 0, ',', '.') }}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════
                 🚚 ENTREGA
                 ═══════════════════════════════════════════════════════════ --}}
            <div class="{{ $cardCls }}">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-cyan-50/50 to-transparent">
                    <h3 class="flex items-center gap-2.5 text-sm font-bold text-slate-700">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-100 text-cyan-600">
                            <i class="fa-solid fa-truck-fast"></i>
                        </span>
                        <span>Método de entrega</span>
                    </h3>
                </div>

                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                        <label class="flex items-center gap-3 cursor-pointer rounded-xl border-2 p-4 transition
                                     {{ $metodo_entrega === 'recoger' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                            <input type="radio" wire:model.live="metodo_entrega" value="recoger" class="sr-only">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg
                                {{ $metodo_entrega === 'recoger' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500' }}">
                                <i class="fa-solid fa-store"></i>
                            </span>
                            <div class="flex-1">
                                <div class="text-sm font-bold text-slate-800">Cliente recoge</div>
                                <div class="text-xs text-slate-500">Pasa a recoger en sede</div>
                            </div>
                            @if($metodo_entrega === 'recoger')
                                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                            @endif
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer rounded-xl border-2 p-4 transition
                                     {{ $metodo_entrega === 'domicilio' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                            <input type="radio" wire:model.live="metodo_entrega" value="domicilio" class="sr-only">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg
                                {{ $metodo_entrega === 'domicilio' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500' }}">
                                <i class="fa-solid fa-motorcycle"></i>
                            </span>
                            <div class="flex-1">
                                <div class="text-sm font-bold text-slate-800">Despacho a domicilio</div>
                                <div class="text-xs text-slate-500">Llevamos tu pedido</div>
                            </div>
                            @if($metodo_entrega === 'domicilio')
                                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                            @endif
                        </label>
                    </div>

                    @if($metodo_entrega === 'recoger')
                        <div>
                            <label class="{{ $labelCls }}">
                                <i class="fa-solid fa-shop text-slate-400 mr-1"></i>
                                Sede de recogida <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-map-pin absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 z-10"></i>
                                <select wire:model="sede_id" class="{{ $inputClsIcon }} appearance-none cursor-pointer">
                                    <option value="">— Selecciona sede —</option>
                                    @foreach($sedes as $s)
                                        <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                    @endforeach
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                            @error('sede_id') <p class="mt-1 text-xs text-rose-600"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="{{ $labelCls }}">
                                    <i class="fa-solid fa-location-dot text-slate-400 mr-1"></i>
                                    Dirección <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <i class="fa-solid fa-house absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 z-10"></i>
                                    <input type="text" wire:model="direccion"
                                           id="pedido-direccion-input"
                                           @if($gmapsKey) x-data x-init="window.initPedidoDireccionAutocomplete && window.initPedidoDireccionAutocomplete($el)" autocomplete="off" @endif
                                           placeholder="Empieza a escribir la dirección…"
                                           class="{{ $inputClsIcon }}">
                                </div>
                                @if($gmapsKey)
                                    <p class="mt-1 text-[11px] text-slate-400">
                                        <i class="fa-brands fa-google text-[10px]"></i> Escribe y elige una sugerencia de Google Maps
                                    </p>
                                @endif
                                @error('direccion') <p class="mt-1 text-xs text-rose-600"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelCls }}">
                                    <i class="fa-solid fa-map text-slate-400 mr-1"></i>
                                    Barrio
                                </label>
                                <div class="relative">
                                    <i class="fa-solid fa-tree-city absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="text" wire:model="barrio"
                                           placeholder="Prado"
                                           class="{{ $inputClsIcon }}">
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════
                 💳 PAGO Y EXTRAS
                 ═══════════════════════════════════════════════════════════ --}}
            <div class="{{ $cardCls }}">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-violet-50/50 to-transparent">
                    <h3 class="flex items-center gap-2.5 text-sm font-bold text-slate-700">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600">
                            <i class="fa-solid fa-credit-card"></i>
                        </span>
                        <span>Pago y observaciones</span>
                    </h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $labelCls }}">
                                <i class="fa-solid fa-money-bill-wave text-slate-400 mr-1"></i>
                                Método de pago
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-wallet absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 z-10"></i>
                                <select wire:model="metodo_pago" class="{{ $inputClsIcon }} appearance-none cursor-pointer">
                                    <option value="efectivo"><i class="fa-solid fa-money-bill"></i> Efectivo contra entrega</option>
                                    <option value="tarjeta"><i class="fa-solid fa-credit-card"></i> Tarjeta</option>
                                    <option value="transferencia"><i class="fa-solid fa-building-columns"></i> Transferencia / PSE</option>
                                    <option value="wompi"><i class="fa-solid fa-bolt"></i> Link Wompi</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">
                                <i class="fa-solid fa-ticket text-slate-400 mr-1"></i>
                                Cupón de descuento <span class="text-slate-400 normal-case font-normal">(opcional)</span>
                            </label>
                            <div class="relative">
                                <i class="fa-solid fa-percent absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" wire:model="cupon"
                                       placeholder="EJ: BIENVENIDA10"
                                       class="{{ $inputClsIcon }} uppercase">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">
                            <i class="fa-solid fa-note-sticky text-slate-400 mr-1"></i>
                            Notas internas
                        </label>
                        <textarea wire:model="notas" rows="3"
                                  class="{{ $inputCls }}"
                                  placeholder="Notas internas o instrucciones para el pedido..."></textarea>
                    </div>
                </div>
            </div>
        </form>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         FOOTER STICKY con total + botón crear
         ═══════════════════════════════════════════════════════════════════ --}}
    <div class="fixed bottom-0 left-0 right-0 z-30 bg-white border-t border-slate-200 shadow-2xl">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="hidden sm:flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shadow-md shrink-0">
                    <i class="fa-solid fa-coins text-lg"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total a pagar</div>
                    <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 truncate">
                        ${{ number_format($this->total, 0, ',', '.') }}
                    </div>
                </div>
            </div>
            <button type="submit" form="" wire:click="crearPedido"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold px-6 sm:px-8 py-3 sm:py-3.5 shadow-lg shadow-emerald-500/30 transition disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
                <span wire:loading.remove wire:target="crearPedido">
                    <i class="fa-solid fa-circle-check mr-1"></i>
                    Crear pedido
                </span>
                <span wire:loading wire:target="crearPedido" class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    Creando...
                </span>
            </button>
        </div>
    </div>

    {{-- 🗺️ Autocompletado de dirección con Google Maps Places --}}
    @if($gmapsKey)
        <script>
            // Define la función global que el input llama vía x-init.
            window.initPedidoDireccionAutocomplete = function (input) {
                if (!input || input.dataset.acInit === '1') return;

                const arrancar = () => {
                    if (!(window.google && google.maps && google.maps.places)) return false;
                    input.dataset.acInit = '1';

                    const ac = new google.maps.places.Autocomplete(input, {
                        componentRestrictions: { country: 'co' },
                        fields: ['formatted_address', 'address_components', 'name'],
                        types: ['address'],
                    });

                    ac.addListener('place_changed', () => {
                        const place = ac.getPlace();
                        const direccion = place.formatted_address || place.name || input.value;

                        // Extraer el barrio (sublocality / neighborhood) si viene.
                        let barrio = '';
                        (place.address_components || []).forEach(c => {
                            if (c.types.includes('sublocality') ||
                                c.types.includes('sublocality_level_1') ||
                                c.types.includes('neighborhood')) {
                                barrio = c.long_name;
                            }
                        });

                        // Empujar a Livewire.
                        const cmp = window.Livewire && Livewire.find(
                            input.closest('[wire\\:id]')?.getAttribute('wire:id')
                        );
                        if (cmp) {
                            cmp.set('direccion', direccion);
                            if (barrio) cmp.set('barrio', barrio);
                        } else {
                            input.value = direccion;
                            input.dispatchEvent(new Event('input'));
                        }
                    });

                    // Evitar que Enter envíe el formulario al elegir sugerencia.
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') e.preventDefault();
                    });
                    return true;
                };

                if (!arrancar()) {
                    const iv = setInterval(() => { if (arrancar()) clearInterval(iv); }, 300);
                    setTimeout(() => clearInterval(iv), 10000);
                }
            };
        </script>
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $gmapsKey }}&libraries=places&language=es&region=CO"
                async defer
                onload="(function(){ const el = document.getElementById('pedido-direccion-input'); if (el && window.initPedidoDireccionAutocomplete) window.initPedidoDireccionAutocomplete(el); })()"></script>
    @endif
</div>
