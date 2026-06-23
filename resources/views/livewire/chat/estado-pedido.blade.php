<div class="px-4 lg:px-8 py-6" wire:poll.5s="cargar">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('chat.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-chevron-left"></i> Volver al chat
            </a>
            <h2 class="text-2xl font-extrabold text-slate-800 mt-2">
                <i class="fa-solid fa-clipboard-list text-emerald-600"></i>
                Estado estructurado del pedido
            </h2>
            <p class="text-sm text-slate-500">
                Cliente: <strong>{{ $conversacion->telefono_visible }}</strong>
                @if($conversacion->cliente_id)
                    · {{ $conversacion->cliente?->nombre }}
                @endif
            </p>
        </div>

        <div class="flex gap-2">
            <button wire:click="cargar"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition">
                <i class="fa-solid fa-rotate"></i> Refrescar
            </button>
            <button wire:click="resetear"
                    wire:confirm="¿Resetear todo el estado del pedido? El bot empezará limpio."
                    class="inline-flex items-center gap-2 rounded-xl bg-rose-100 hover:bg-rose-200 text-rose-700 px-4 py-2 text-sm font-semibold transition">
                <i class="fa-solid fa-eraser"></i> Reset estado
            </button>
        </div>
    </div>

    @php
        $pasoColores = [
            'inicio'         => 'bg-slate-100 text-slate-700',
            'producto'       => 'bg-blue-100 text-blue-700',
            'entrega'        => 'bg-cyan-100 text-cyan-700',
            'identificacion' => 'bg-amber-100 text-amber-700',
            'confirmacion'   => 'bg-violet-100 text-violet-700',
            'confirmado'     => 'bg-emerald-100 text-emerald-700',
            'abandonado'     => 'bg-rose-100 text-rose-700',
        ];
        $pasoColor = $pasoColores[$estado->paso_actual] ?? 'bg-slate-100 text-slate-700';
        $faltantes = $estado->camposFaltantes();
        $completo = $estado->estaCompleto();
    @endphp

    {{-- Paso actual --}}
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Paso actual</p>
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-bold {{ $pasoColor }} mt-1">
                    {{ ucfirst($estado->paso_actual) }}
                </span>
            </div>
            <div class="text-right">
                @if($completo)
                    <span class="inline-flex items-center gap-1 text-emerald-600 font-bold text-sm">
                        <i class="fa-solid fa-circle-check"></i> Datos completos
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-amber-600 font-bold text-sm">
                        <i class="fa-solid fa-triangle-exclamation"></i> {{ count($faltantes) }} dato(s) faltante(s)
                    </span>
                @endif
            </div>
        </div>

        @if(!empty($faltantes))
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                <strong>Falta:</strong> {{ implode(', ', $faltantes) }}
            </div>
        @endif

        @if($estado->pedido_id)
            <div class="rounded-xl bg-emerald-50 border border-emerald-300 p-3 text-sm text-emerald-800 mt-3">
                <i class="fa-solid fa-check-double mr-1"></i>
                <strong>Pedido #{{ $estado->pedido_id }}</strong> creado el
                {{ $estado->confirmado_at?->format('d/m/Y H:i') }}
            </div>
        @endif
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        {{-- 🛒 Productos --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-800 mb-3">
                <i class="fa-solid fa-cart-shopping text-blue-600 mr-1"></i> Productos
            </h3>
            @if(!empty($estado->productos))
                <ul class="space-y-1.5 text-sm">
                    @foreach($estado->productos as $p)
                        <li class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                            <span class="font-medium">{{ $p['name'] ?? '?' }}</span>
                            <span class="text-slate-600 text-xs">
                                {{ $p['quantity'] ?? 1 }} {{ $p['unit'] ?? '' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-slate-400 italic">— Sin productos seleccionados —</p>
            @endif
        </div>

        {{-- 🚚 Entrega --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-800 mb-3">
                <i class="fa-solid fa-truck text-cyan-600 mr-1"></i> Entrega
            </h3>
            @if($estado->metodo_entrega)
                <p class="text-sm">
                    <strong>Método:</strong>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold">{{ $estado->metodo_entrega }}</span>
                </p>
                @if($estado->metodo_entrega === 'domicilio')
                    <p class="text-sm mt-1.5"><strong>Dirección:</strong> {{ $estado->direccion ?: '—' }}</p>
                    @if($estado->barrio)<p class="text-sm"><strong>Barrio:</strong> {{ $estado->barrio }}</p>@endif
                    @if($estado->ciudad)<p class="text-sm"><strong>Ciudad:</strong> {{ $estado->ciudad }}</p>@endif
                    <p class="text-sm mt-1.5">
                        <strong>Cobertura:</strong>
                        @if($estado->cobertura_validada)
                            <span class="text-emerald-600"><i class="fa-solid fa-circle-check"></i> Validada</span>
                        @else
                            <span class="text-rose-600"><i class="fa-solid fa-circle-xmark"></i> No validada</span>
                        @endif
                    </p>
                    @if($estado->distancia_km)
                        <p class="text-xs text-slate-500 mt-1">{{ $estado->distancia_km }} km · ${{ number_format($estado->costo_envio ?: 0, 0, ',', '.') }} envío</p>
                    @endif
                @elseif($estado->metodo_entrega === 'recoger')
                    <p class="text-sm mt-1.5">
                        <strong>Sede:</strong>
                        {{ $estado->sede?->nombre ?: ($estado->sede_id ? "ID #{$estado->sede_id}" : '—') }}
                    </p>
                @endif
            @else
                <p class="text-sm text-slate-400 italic">— Sin método definido —</p>
            @endif
        </div>

        {{-- 👤 Identificación --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-800 mb-3">
                <i class="fa-solid fa-id-card text-amber-600 mr-1"></i> Identificación
            </h3>
            <p class="text-sm"><strong>Cédula:</strong> {{ $estado->cedula ?: '—' }}</p>
            <p class="text-sm"><strong>Nombre:</strong> {{ $estado->nombre_cliente ?: '—' }}</p>
            <p class="text-sm"><strong>Teléfono:</strong> {{ $estado->telefono ?: '—' }}</p>
            @if($estado->email)<p class="text-sm"><strong>Email:</strong> {{ $estado->email }}</p>@endif
            <p class="text-sm mt-1.5">
                <strong>En ERP:</strong>
                @if($estado->cliente_existe_erp)
                    <span class="text-emerald-600"><i class="fa-solid fa-circle-check"></i> Sí</span>
                @else
                    <span class="text-slate-500">No verificado / no existe</span>
                @endif
            </p>
        </div>

        {{-- 💳 Pago + extras --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-800 mb-3">
                <i class="fa-solid fa-credit-card text-violet-600 mr-1"></i> Pago / extras
            </h3>
            <p class="text-sm"><strong>Método de pago:</strong> {{ $estado->metodo_pago ?: '—' }}</p>
            @if($estado->cupon_code)<p class="text-sm"><strong>Cupón:</strong> {{ $estado->cupon_code }}</p>@endif
            @if($estado->notas)
                <p class="text-sm mt-1.5"><strong>Notas:</strong></p>
                <p class="text-xs text-slate-600 italic">{{ $estado->notas }}</p>
            @endif
        </div>
    </div>

    {{-- Validaciones realizadas --}}
    @if(!empty($estado->validaciones))
        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 shadow-sm mt-6">
            <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">
                <i class="fa-solid fa-clipboard-check mr-1"></i> Validaciones registradas
            </p>
            <div class="flex flex-wrap gap-2">
                @foreach($estado->validaciones as $clave => $valor)
                    <span class="rounded-full bg-white border border-slate-200 px-3 py-1 text-xs">
                        {{ $clave }}:
                        @if($valor)
                            <span class="text-emerald-600 font-bold"><i class="fa-solid fa-check"></i></span>
                        @else
                            <span class="text-rose-600 font-bold"><i class="fa-solid fa-xmark"></i></span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <p class="text-xs text-slate-400 mt-6 text-center">
        <i class="fa-solid fa-arrows-rotate"></i> Auto-refresh cada 5 segundos · Última actualización:
        {{ $estado->updated_at?->format('d/m/Y H:i:s') }}
    </p>
</div>
