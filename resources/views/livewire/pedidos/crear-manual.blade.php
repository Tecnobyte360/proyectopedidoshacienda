<div class="min-h-screen bg-slate-50/50">
    @php
        // Clases reutilizables para inputs (Tailwind v4 ya no estiliza inputs nativos)
        $inputCls = 'w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 px-3.5 py-2.5 shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none disabled:bg-slate-50 disabled:text-slate-500';
        $inputClsIcon = 'w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 pl-10 pr-3.5 py-2.5 shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
        $labelCls = 'block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5';
        $cardCls = 'rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden';
    @endphp

    <div class="px-4 lg:px-8 py-6 w-full max-w-[1600px] mx-auto pb-32">

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
                        {{-- 🌍 Selector de país (propio, sin CDN) + número --}}
                        <div x-data="telefonoPais(@js($telefono))" x-init="init()" class="flex gap-2">
                            {{-- Selector de país con bandera --}}
                            <div class="relative shrink-0">
                                <select x-model="indicativo" @change="sync()"
                                        class="appearance-none rounded-xl border border-slate-300 bg-white text-sm py-3 pl-3 pr-8 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none cursor-pointer">
                                    <option value="57">🇨🇴 +57</option>
                                    <option value="1">🇺🇸 +1</option>
                                    <option value="58">🇻🇪 +58</option>
                                    <option value="593">🇪🇨 +593</option>
                                    <option value="51">🇵🇪 +51</option>
                                    <option value="52">🇲🇽 +52</option>
                                    <option value="34">🇪🇸 +34</option>
                                    <option value="56">🇨🇱 +56</option>
                                    <option value="54">🇦🇷 +54</option>
                                    <option value="507">🇵🇦 +507</option>
                                </select>
                                <i class="fa-solid fa-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                            </div>
                            {{-- Número sin indicativo --}}
                            <div class="relative flex-1">
                                <i class="fa-brands fa-whatsapp absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500"></i>
                                <input type="tel" x-model="numero" @input="sync()"
                                       placeholder="300 123 4567"
                                       class="w-full rounded-xl border border-slate-300 bg-white text-sm pl-10 pr-4 py-3 shadow-sm transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                            </div>
                        </div>
                        @error('telefono') <p class="mt-1 text-xs text-rose-600"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</p> @enderror

                        {{-- 🏷️ Marca: el teléfono vino del ERP, verificar --}}
                        @if($telefonoDesdeErp)
                            <p class="mt-1 text-[11px] text-amber-600 flex items-center gap-1">
                                <i class="fa-solid fa-database text-[10px]"></i>
                                Traído del ERP — <strong>verifícalo</strong> antes de guardar.
                            </p>
                        @endif

                        {{-- ⚠️ Alerta: el teléfono parece inválido --}}
                        @php $telMotivo = $this->telefonoSospechoso(); @endphp
                        @if($telMotivo !== '')
                            <p class="mt-1 text-[11px] text-rose-600 flex items-center gap-1">
                                <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                {{ $telMotivo }} El cliente no recibirá notificaciones.
                            </p>
                        @endif
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
                                    wire:loading.attr="disabled" wire:target="buscarPorCedula"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 text-sm font-bold shadow-sm transition shrink-0 disabled:opacity-80 disabled:cursor-wait min-w-[110px]">
                                {{-- Estado normal --}}
                                <span wire:loading.remove wire:target="buscarPorCedula" class="inline-flex items-center gap-2">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <span class="hidden sm:inline">Buscar</span>
                                </span>
                                {{-- Spinner mientras busca --}}
                                <span wire:loading.flex wire:target="buscarPorCedula" class="items-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <span class="hidden sm:inline">Buscando…</span>
                                </span>
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
                                                ${{ number_format(((float) ($p['cantidad'] ?? 0)) * ((float) ($p['precio'] ?? 0)), 0, ',', '.') }}
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
            {{-- Sin overflow-hidden para que el dropdown de direcciones (Google)
                 no quede recortado por la tarjeta. --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm relative z-20">
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
                                @if($gmapsKey)
                                    <div x-data="direccionAutocomplete(@js($gmapsKey))" class="relative">
                                        <i class="fa-solid fa-house absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 z-10"></i>
                                        <input type="text"
                                               x-model="texto"
                                               @input.debounce.350ms="buscar()"
                                               @focus="if(sugerencias.length) abierto=true"
                                               @keydown.escape="abierto=false"
                                               autocomplete="off"
                                               placeholder="Empieza a escribir la dirección…"
                                               class="{{ $inputClsIcon }}">
                                        {{-- Dropdown de sugerencias --}}
                                        <div x-show="abierto && sugerencias.length" x-cloak
                                             @click.outside="abierto=false"
                                             class="absolute left-0 right-0 top-full mt-1 z-50 rounded-xl border border-slate-200 bg-white shadow-2xl max-h-80 overflow-y-auto">
                                            <template x-for="(s, i) in sugerencias" :key="i">
                                                <button type="button" @click="elegir(s)"
                                                        class="w-full text-left px-4 py-2.5 hover:bg-emerald-50 border-b border-slate-100 last:border-0 flex items-start gap-2.5">
                                                    <i class="fa-solid fa-location-dot text-emerald-500 mt-1 text-[12px] shrink-0"></i>
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-semibold text-slate-800 truncate" x-text="s.principal"></span>
                                                        <span class="block text-[11px] text-slate-500 truncate" x-text="s.secundario"></span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-400">
                                        <i class="fa-brands fa-google text-[10px]"></i> Escribe y elige una sugerencia de Google Maps
                                    </p>
                                @else
                                    <div class="relative">
                                        <i class="fa-solid fa-house absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 z-10"></i>
                                        <input type="text" wire:model="direccion"
                                               placeholder="Cra 50 #63B-48"
                                               class="{{ $inputClsIcon }}">
                                    </div>
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

                        {{-- 🚚 Costo de envío (lo define el operador) --}}
                        <div class="mt-4">
                            <label class="{{ $labelCls }}">
                                <i class="fa-solid fa-truck text-slate-400 mr-1"></i>
                                Costo de envío
                            </label>
                            <div class="flex items-center gap-2 max-w-md" @if($gmapsKey) x-data="envioCalculador(@js($gmapsKey))" @endif>
                                <div class="relative flex-1 max-w-xs">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-semibold">$</span>
                                    <input type="number" step="500" min="0" wire:model.live="costo_envio"
                                           placeholder="0"
                                           class="w-full rounded-xl border border-slate-300 bg-white text-sm pl-7 pr-3.5 py-2.5 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                                </div>
                                @if($gmapsKey)
                                    <button type="button" @click="calcular()" x-bind:disabled="cargando"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-sm font-semibold transition shrink-0 disabled:opacity-70 disabled:cursor-wait">
                                        <i class="fa-solid fa-route" x-show="!cargando"></i>
                                        <i class="fa-solid fa-spinner fa-spin" x-show="cargando" x-cloak></i>
                                        <span x-text="cargando ? 'Calculando…' : 'Calcular'"></span>
                                    </button>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">
                                @if($envioDistanciaKm)
                                    <span class="text-emerald-600 font-medium"><i class="fa-solid fa-route"></i> Calculado por distancia: ~{{ $envioDistanciaKm }} km</span> · podés modificarlo.
                                @else
                                    Se calcula solo al elegir la dirección de Google Maps, o con el botón <b>Calcular</b>. Podés modificarlo.
                                @endif
                            </p>
                        </div>

                        {{-- 🛵 Domiciliario (híbrido: sistema sugiere, operador confirma) --}}
                        <div class="mt-4">
                            <label class="{{ $labelCls }}">
                                <i class="fa-solid fa-motorcycle text-slate-400 mr-1"></i>
                                Domiciliario <span class="text-slate-400 normal-case font-normal">(opcional)</span>
                            </label>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="relative flex-1">
                                    <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 z-10"></i>
                                    <select wire:model.live="domiciliario_id" class="{{ $inputClsIcon }} appearance-none cursor-pointer">
                                        <option value="">— Sin asignar (el sistema lo asigna) —</option>
                                        @foreach($domiciliarios as $d)
                                            <option value="{{ $d->id }}">
                                                {{ $d->nombre }} ({{ $d->estado === 'disponible' ? 'disponible' : 'en ruta' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                                <button type="button" wire:click="sugerirDomiciliario"
                                        wire:loading.attr="disabled" wire:target="sugerirDomiciliario"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2.5 text-sm font-semibold transition shrink-0">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    Sugerir mejor
                                </button>
                            </div>
                            @if($domiciliarioSugerido)
                                <p class="mt-1 text-[11px] text-cyan-600 flex items-center gap-1">
                                    <i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i>
                                    Sugerido por el sistema — podés cambiarlo.
                                </p>
                            @else
                                <p class="mt-1 text-[11px] text-slate-400">
                                    Si lo dejás sin asignar, el sistema elige el mejor al crear el pedido.
                                </p>
                            @endif
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
                                    <option value="efectivo">Efectivo contra entrega</option>
                                    <option value="transferencia">Transferencia / PSE</option>
                                    @if($tieneWompi)
                                        <option value="wompi">💳 Link de pago — Wompi</option>
                                    @endif
                                    @if($tieneBold)
                                        <option value="bold">💳 Link de pago — Bold</option>
                                    @endif
                                    @if(!$tieneWompi && !$tieneBold)
                                        <option value="tarjeta">Tarjeta (manual)</option>
                                    @endif
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

        {{-- 💳 Link de pago generado tras crear el pedido --}}
        @if($linkPagoGenerado)
            <div class="mt-5 rounded-2xl border-2 border-emerald-300 bg-emerald-50/60 p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500 text-white">
                        <i class="fa-solid fa-link"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-emerald-800">Link de pago generado</h3>
                        <p class="text-[12px] text-emerald-600">Pedido #{{ $pedidoCreadoId }} — compártelo con el cliente</p>
                    </div>
                </div>
                <div x-data="{ link: @js($linkPagoGenerado), copiado: false }" class="flex flex-col sm:flex-row gap-2">
                    <input type="text" readonly :value="link"
                           class="flex-1 rounded-xl border border-emerald-300 bg-white text-sm px-3.5 py-2.5 text-slate-700 font-mono">
                    <button type="button"
                            @click="navigator.clipboard.writeText(link); copiado=true; setTimeout(()=>copiado=false,2000)"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 transition shrink-0">
                        <i class="fa-solid" :class="copiado ? 'fa-check' : 'fa-copy'"></i>
                        <span x-text="copiado ? '¡Copiado!' : 'Copiar'"></span>
                    </button>
                    <a :href="link" target="_blank"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-white hover:bg-emerald-50 text-emerald-700 text-sm font-semibold px-4 py-2.5 transition shrink-0">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir
                    </a>
                </div>
                <div class="mt-3 flex items-center justify-end">
                    <a href="{{ route('pedidos.index') }}"
                       class="text-sm font-semibold text-slate-600 hover:text-slate-800">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Ir a Gestión de pedidos
                    </a>
                </div>
            </div>
        @endif

    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         FOOTER STICKY con total + botón crear
         ═══════════════════════════════════════════════════════════════════ --}}
    <div class="fixed bottom-0 left-0 right-0 z-30 bg-white border-t border-slate-200 shadow-2xl">
        <div class="w-full max-w-[1600px] mx-auto px-4 lg:px-8 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="hidden sm:flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shadow-md shrink-0">
                    <i class="fa-solid fa-coins text-lg"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total a pagar</div>
                    <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 truncate">
                        ${{ number_format($this->total, 0, ',', '.') }}
                    </div>
                    @if($metodo_entrega === 'domicilio' && $this->envio > 0)
                        <div class="text-[10px] text-slate-400">
                            Productos ${{ number_format($this->subtotalProductos, 0, ',', '.') }} + Envío ${{ number_format($this->envio, 0, ',', '.') }}
                        </div>
                    @endif
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

    {{-- 🗺️ Autocompletado de dirección con Places API (New) vía REST.
         No usa el SDK google.maps.places.Autocomplete (bloqueado para
         cuentas nuevas desde marzo 2025). Llama directo al endpoint REST. --}}
    @once
        <script>
            function direccionAutocomplete(apiKey) {
                return {
                    apiKey: apiKey,
                    texto: '',
                    sugerencias: [],
                    abierto: false,
                    sessionToken: null,
                    init() {
                        // Sincronizar con el valor inicial de Livewire (si lo hay).
                        this.texto = this.$wire.get('direccion') || '';
                        this.$wire.$watch('direccion', (val) => {
                            if ((val || '') !== this.texto) this.texto = val || '';
                        });
                    },
                    nuevoToken() {
                        // Un token UUID por sesión de búsqueda (lo pide Places New).
                        this.sessionToken = (crypto.randomUUID
                            ? crypto.randomUUID()
                            : ('t' + Date.now() + Math.random().toString(16).slice(2)));
                    },
                    async buscar() {
                        // Mantener el campo direccion en Livewire mientras escribe.
                        this.$wire.set('direccion', this.texto, false);

                        const q = (this.texto || '').trim();
                        if (q.length < 3) { this.sugerencias = []; this.abierto = false; return; }
                        if (!this.sessionToken) this.nuevoToken();

                        try {
                            const resp = await fetch('https://places.googleapis.com/v1/places:autocomplete', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Goog-Api-Key': this.apiKey,
                                },
                                body: JSON.stringify({
                                    input: q,
                                    languageCode: 'es',
                                    regionCode: 'CO',
                                    sessionToken: this.sessionToken,
                                }),
                            });
                            const data = await resp.json();
                            if (data.error) { console.warn('Places New error:', data.error.message); this.sugerencias = []; return; }
                            this.sugerencias = (data.suggestions || [])
                                .filter(s => s.placePrediction)
                                .map(s => {
                                    const p = s.placePrediction;
                                    const sf = p.structuredFormat || {};
                                    return {
                                        texto:      p.text?.text || '',                 // completo (lo que se guarda)
                                        principal:  sf.mainText?.text || p.text?.text || '', // calle
                                        secundario: sf.secondaryText?.text || '',        // ciudad/barrio
                                        placeId:    p.placeId,
                                    };
                                });
                            this.abierto = this.sugerencias.length > 0;
                        } catch (e) {
                            console.warn('Places New fetch falló:', e);
                            this.sugerencias = [];
                        }
                    },
                    async elegir(s) {
                        this.texto = s.texto;
                        this.$wire.set('direccion', s.texto);
                        this.abierto = false;
                        this.sugerencias = [];

                        // Pedir detalles para extraer el barrio.
                        try {
                            const resp = await fetch(
                                'https://places.googleapis.com/v1/places/' + s.placeId +
                                '?languageCode=es&sessionToken=' + this.sessionToken,
                                { headers: {
                                    'X-Goog-Api-Key': this.apiKey,
                                    'X-Goog-FieldMask': 'addressComponents,formattedAddress,location',
                                } }
                            );
                            const d = await resp.json();
                            let barrio = '';
                            (d.addressComponents || []).forEach(c => {
                                const t = c.types || [];
                                if (t.includes('sublocality') || t.includes('sublocality_level_1') || t.includes('neighborhood')) {
                                    barrio = c.longText || c.shortText || '';
                                }
                            });
                            if (barrio) this.$wire.set('barrio', barrio);

                            // 🚚 Coordenadas → calcular costo de envío por lejanía.
                            if (d.location && d.location.latitude && d.location.longitude) {
                                this.$wire.calcularEnvio(d.location.latitude, d.location.longitude);
                            }
                        } catch (e) { /* sin barrio/coords, no pasa nada */ }

                        this.sessionToken = null; // cerrar sesión de búsqueda
                    },
                };
            }

            // 🚚 Calcula el costo de envío geolocalizando la dirección ESCRITA
            //    (venga del ERP, de Google o tecleada). Usa Text Search (New)
            //    para obtener coordenadas y luego pide el cálculo al servidor.
            function envioCalculador(apiKey) {
                return {
                    apiKey: apiKey,
                    cargando: false,
                    async calcular() {
                        const dir = (this.$wire.get('direccion') || '').trim();
                        if (dir.length < 5) {
                            this.$wire.dispatch('notify', { type: 'warning', message: 'Escribe primero la dirección del cliente.' });
                            return;
                        }
                        this.cargando = true;
                        try {
                            const resp = await fetch('https://places.googleapis.com/v1/places:searchText', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Goog-Api-Key': this.apiKey,
                                    'X-Goog-FieldMask': 'places.location,places.formattedAddress',
                                },
                                body: JSON.stringify({ textQuery: dir, languageCode: 'es', regionCode: 'CO' }),
                            });
                            const d = await resp.json();
                            if (d.error) { console.warn('Text Search error:', d.error.message); }
                            const loc = d.places && d.places[0] && d.places[0].location;
                            if (loc && loc.latitude && loc.longitude) {
                                await this.$wire.calcularEnvio(loc.latitude, loc.longitude);
                            } else {
                                this.$wire.dispatch('notify', { type: 'warning', message: 'No pude ubicar esa dirección en el mapa. Ajústala y reintenta, o escribe el costo a mano.' });
                            }
                        } catch (e) {
                            console.warn('envioCalculador falló:', e);
                            this.$wire.dispatch('notify', { type: 'error', message: 'No se pudo calcular el envío. Escribe el costo a mano.' });
                        }
                        this.cargando = false;
                    },
                };
            }
        </script>
    @endonce

    {{-- 🌍 Selector de país propio (sin CDN) para el teléfono --}}
    @once
        <script>
            function telefonoPais(valorInicial) {
                // Indicativos conocidos, del más largo al más corto para detectar bien.
                const INDICATIVOS = ['593','591','507','506','502','598','51','58','57','56','55','54','52','34','1'];
                return {
                    indicativo: '57',
                    numero: '',
                    init() {
                        let v = (valorInicial || '').replace(/\D+/g, '');
                        if (v) {
                            // Detectar indicativo si el número ya viene con código país.
                            const hit = INDICATIVOS.find(c => v.startsWith(c) && v.length > 10);
                            if (hit) { this.indicativo = hit; this.numero = v.slice(hit.length); }
                            else { this.numero = v; } // 10 dígitos → local, default CO
                        }
                        this.sync();
                        // Si Livewire cambia el teléfono (ej. buscar por cédula), re-sincronizar.
                        this.$wire.$watch('telefono', (val) => {
                            const limpio = (val || '').replace(/\D+/g, '');
                            const actual = this.indicativo + this.numero;
                            if (limpio && limpio !== actual) {
                                const hit = INDICATIVOS.find(c => limpio.startsWith(c) && limpio.length > 10);
                                if (hit) { this.indicativo = hit; this.numero = limpio.slice(hit.length); }
                                else { this.numero = limpio; }
                            }
                        });
                    },
                    sync() {
                        const limpio = (this.numero || '').replace(/\D+/g, '');
                        // 3er arg = false → DIFERIDO: no hace roundtrip al servidor en
                        // cada tecla (eso causaba que el número "saltara" mientras se
                        // escribía). El valor viaja al crear el pedido.
                        this.$wire.set('telefono', limpio ? (this.indicativo + limpio) : '', false);
                    },
                };
            }
        </script>
    @endonce
</div>
