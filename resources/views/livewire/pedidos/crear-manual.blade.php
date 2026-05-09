<div class="px-4 lg:px-8 py-6 max-w-5xl mx-auto">

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-800 flex items-center gap-2">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500 text-white shadow">
                    <i class="fa-solid fa-cart-plus"></i>
                </span>
                Crear pedido manual
            </h2>
            <p class="text-sm text-slate-500 mt-1">
                @if($conversacionId)
                    Cerrando pedido para la conversación #{{ $conversacionId }}
                @else
                    Crea un pedido sin pasar por el bot (admin/operador)
                @endif
            </p>
        </div>
        <a href="{{ route('pedidos.index') }}" class="text-sm text-slate-500 hover:text-slate-700 mt-3">
            <i class="fa-solid fa-chevron-left"></i> Volver a pedidos
        </a>
    </div>

    <form wire:submit.prevent="crearPedido" class="space-y-5">

        {{-- 👤 Cliente --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">
                <i class="fa-solid fa-user text-blue-600"></i> Cliente
            </h3>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-medium text-slate-600">Teléfono *</label>
                    <input type="text" wire:model="telefono" placeholder="573XXXXXXXXX"
                           class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Cédula</label>
                    <div class="flex gap-1">
                        <input type="text" wire:model="cedula"
                               class="flex-1 rounded-xl border-slate-300 text-sm">
                        <button type="button" wire:click="buscarPorCedula"
                                class="rounded-xl bg-slate-100 hover:bg-slate-200 px-3 text-xs font-bold text-slate-700">
                            🔍
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Nombre completo *</label>
                    <input type="text" wire:model="nombre_cliente"
                           class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Email</label>
                    <input type="email" wire:model="email"
                           class="w-full rounded-xl border-slate-300 text-sm">
                </div>
            </div>
        </div>

        {{-- 🛒 Productos --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">
                <i class="fa-solid fa-cart-shopping text-blue-600"></i> Productos
            </h3>

            <div class="relative mb-3">
                <input type="text" wire:model.live.debounce.300ms="busquedaProducto"
                       placeholder="🔎 Buscar producto por nombre o código..."
                       class="w-full rounded-xl border-slate-300 text-sm">
                @if($productosCatalogo->isNotEmpty())
                    <div class="absolute left-0 right-0 top-full mt-1 z-10 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                        @foreach($productosCatalogo as $p)
                            <button type="button"
                                    wire:click="agregarProducto({{ $p->id }})"
                                    class="w-full text-left px-4 py-2 hover:bg-emerald-50 flex items-center justify-between border-b border-slate-100 last:border-b-0">
                                <div>
                                    <div class="text-sm font-semibold text-slate-800">{{ $p->nombre }}</div>
                                    <div class="text-xs text-slate-500">{{ $p->codigo }}</div>
                                </div>
                                <div class="text-sm font-bold text-emerald-600">
                                    ${{ number_format($p->precio_base ?? 0, 0, ',', '.') }} / {{ $p->unidad }}
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(empty($productos))
                <div class="rounded-xl bg-slate-50 border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">
                    <i class="fa-solid fa-box-open text-2xl mb-2"></i>
                    <p>Sin productos. Busca arriba para agregar.</p>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                        <tr class="border-b border-slate-200">
                            <th class="text-left py-2">Producto</th>
                            <th class="text-center py-2 w-24">Cantidad</th>
                            <th class="text-center py-2 w-24">Unidad</th>
                            <th class="text-right py-2 w-28">Precio unit</th>
                            <th class="text-right py-2 w-28">Subtotal</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productos as $idx => $p)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">
                                    <input type="text" wire:model="productos.{{ $idx }}.nombre"
                                           class="w-full rounded-lg border-slate-200 text-sm">
                                </td>
                                <td class="py-2 text-center">
                                    <input type="number" step="0.01" min="0.01"
                                           wire:model.lazy="productos.{{ $idx }}.cantidad"
                                           class="w-20 rounded-lg border-slate-200 text-sm text-center">
                                </td>
                                <td class="py-2 text-center">
                                    <input type="text" wire:model.lazy="productos.{{ $idx }}.unidad"
                                           class="w-20 rounded-lg border-slate-200 text-xs text-center">
                                </td>
                                <td class="py-2 text-right">
                                    <input type="number" step="1" min="0"
                                           wire:model.lazy="productos.{{ $idx }}.precio"
                                           class="w-24 rounded-lg border-slate-200 text-sm text-right">
                                </td>
                                <td class="py-2 text-right font-semibold">
                                    ${{ number_format(($p['cantidad'] ?? 0) * ($p['precio'] ?? 0), 0, ',', '.') }}
                                </td>
                                <td class="py-2 text-center">
                                    <button type="button" wire:click="eliminarProducto({{ $idx }})"
                                            class="text-rose-500 hover:text-rose-700">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="py-3 text-right font-bold text-slate-700">Total:</td>
                            <td class="py-3 text-right text-lg font-extrabold text-emerald-600">
                                ${{ number_format($this->total, 0, ',', '.') }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>

        {{-- 🚚 Entrega --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">
                <i class="fa-solid fa-truck text-cyan-600"></i> Entrega
            </h3>

            <div class="grid grid-cols-2 gap-2 mb-4">
                <label class="flex items-center gap-2 cursor-pointer rounded-xl border-2 p-3 transition
                             {{ $metodo_entrega === 'recoger' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200' }}">
                    <input type="radio" wire:model.live="metodo_entrega" value="recoger" class="text-emerald-600">
                    <span class="text-sm font-semibold">🏪 Cliente recoge</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer rounded-xl border-2 p-3 transition
                             {{ $metodo_entrega === 'domicilio' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200' }}">
                    <input type="radio" wire:model.live="metodo_entrega" value="domicilio" class="text-emerald-600">
                    <span class="text-sm font-semibold">🚚 Despacho</span>
                </label>
            </div>

            @if($metodo_entrega === 'recoger')
                <div>
                    <label class="text-xs font-medium text-slate-600">Sede *</label>
                    <select wire:model="sede_id" class="w-full rounded-xl border-slate-300 text-sm">
                        <option value="">— Selecciona sede —</option>
                        @foreach($sedes as $s)
                            <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="grid md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="text-xs font-medium text-slate-600">Dirección *</label>
                        <input type="text" wire:model="direccion"
                               class="w-full rounded-xl border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600">Barrio</label>
                        <input type="text" wire:model="barrio"
                               class="w-full rounded-xl border-slate-300 text-sm">
                    </div>
                </div>
            @endif
        </div>

        {{-- 💳 Pago / extras --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3">
                <i class="fa-solid fa-credit-card text-violet-600"></i> Pago / extras
            </h3>
            <div class="grid md:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="text-xs font-medium text-slate-600">Método de pago</label>
                    <select wire:model="metodo_pago" class="w-full rounded-xl border-slate-300 text-sm">
                        <option value="efectivo">Efectivo contra entrega</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia / PSE</option>
                        <option value="wompi">Link Wompi</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">Cupón (opcional)</label>
                    <input type="text" wire:model="cupon"
                           class="w-full rounded-xl border-slate-300 text-sm">
                </div>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Notas</label>
                <textarea wire:model="notas" rows="2"
                          class="w-full rounded-xl border-slate-300 text-sm"
                          placeholder="Notas internas o instrucciones para el pedido"></textarea>
            </div>
        </div>

        {{-- Botón crear --}}
        <div class="sticky bottom-0 bg-white border-t border-slate-200 -mx-4 lg:-mx-8 px-4 lg:px-8 py-4 flex items-center justify-between">
            <div class="text-sm text-slate-500">
                Total: <span class="font-extrabold text-2xl text-emerald-600">${{ number_format($this->total, 0, ',', '.') }}</span>
            </div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-8 py-3 shadow-lg transition disabled:opacity-50">
                <i class="fa-solid fa-check mr-2"></i>
                Crear pedido
                <span wire:loading wire:target="crearPedido"><i class="fa-solid fa-spinner fa-spin ml-1"></i></span>
            </button>
        </div>
    </form>
</div>
