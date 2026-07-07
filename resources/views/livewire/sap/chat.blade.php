<div class="p-4 sm:p-6">
    <div class="mx-auto max-w-4xl flex flex-col rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden"
         style="height:calc(100vh - 130px)">

        {{-- Encabezado --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50 via-white to-white">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg">
                <i class="fa-solid fa-robot"></i>
            </span>
            <div>
                <h2 class="font-extrabold text-slate-800 leading-tight">IA SAP · Ventas</h2>
                <p class="text-xs text-slate-500">Consulta tus cotizaciones y pedidos directamente de SAP, en tiempo real.</p>
            </div>
            <span class="ml-auto inline-flex items-center gap-2 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-3 py-1">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> SAP
            </span>
        </div>

        {{-- Mensajes --}}
        <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-5 space-y-4 bg-slate-50/50"
             x-data x-effect="$wire.mensajes; $nextTick(() => $el.scrollTop = $el.scrollHeight)">

            @forelse ($mensajes as $m)
                <div class="flex gap-3 max-w-[90%] {{ $m['role'] === 'user' ? 'ml-auto flex-row-reverse' : '' }}">
                    <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-white text-xs
                        {{ $m['role'] === 'user' ? 'bg-slate-800' : 'bg-gradient-to-br from-emerald-500 to-emerald-700' }}">
                        <i class="fa-solid {{ $m['role'] === 'user' ? 'fa-user' : 'fa-robot' }}"></i>
                    </span>
                    <div>
                        <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap
                            {{ $m['role'] === 'user' ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-700' }}">{{ $m['content'] }}</div>
                        @if (!empty($m['tools']))
                            <div class="mt-1.5 text-[11px] text-slate-400 font-semibold">
                                <i class="fa-solid fa-plug text-emerald-500"></i> Consultó SAP: {{ implode(', ', $m['tools']) }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-10">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <i class="fa-solid fa-comments-dollar text-xl"></i>
                    </div>
                    <p class="text-slate-500 text-sm">Pregúntame sobre <b>cotizaciones</b> y <b>estados de pedidos</b>.</p>
                    <div class="flex flex-wrap gap-2 justify-center mt-4">
                        <button wire:click="preguntar('¿Cómo van las cotizaciones de los últimos 30 días? ¿Cuántas se ganaron y cuántas se perdieron?')"
                            class="text-xs font-semibold bg-white border border-slate-200 rounded-full px-4 py-2 hover:border-emerald-400 hover:text-emerald-700 transition">Cotizaciones del mes</button>
                        <button wire:click="preguntar('¿Qué pedidos abiertos están vencidos con cantidades pendientes por despachar?')"
                            class="text-xs font-semibold bg-white border border-slate-200 rounded-full px-4 py-2 hover:border-emerald-400 hover:text-emerald-700 transition">Pedidos vencidos</button>
                        <button wire:click="preguntar('Muéstrame los pedidos abiertos con cantidades retrasadas por despachar o facturar')"
                            class="text-xs font-semibold bg-white border border-slate-200 rounded-full px-4 py-2 hover:border-emerald-400 hover:text-emerald-700 transition">Atrasos de despacho</button>
                    </div>
                </div>
            @endforelse

            {{-- Indicador de "pensando" mientras corre enviar/preguntar --}}
            <div wire:loading wire:target="enviar,preguntar" class="flex gap-3">
                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-white text-xs bg-gradient-to-br from-emerald-500 to-emerald-700">
                    <i class="fa-solid fa-robot"></i>
                </span>
                <div class="rounded-2xl px-4 py-3 bg-white border border-slate-200">
                    <span class="inline-flex gap-1">
                        <span class="h-2 w-2 rounded-full bg-slate-300 animate-bounce" style="animation-delay:0s"></span>
                        <span class="h-2 w-2 rounded-full bg-slate-300 animate-bounce" style="animation-delay:.15s"></span>
                        <span class="h-2 w-2 rounded-full bg-slate-300 animate-bounce" style="animation-delay:.3s"></span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Entrada --}}
        <form wire:submit.prevent="enviar" class="border-t border-slate-100 p-3 sm:p-4 flex gap-2">
            <input type="text" wire:model="entrada" autocomplete="off"
                placeholder="Escribe tu pregunta sobre ventas…"
                class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none"
                wire:loading.attr="disabled" wire:target="enviar,preguntar">
            <button type="submit" wire:loading.attr="disabled" wire:target="enviar,preguntar"
                class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-700 hover:from-emerald-600 hover:to-emerald-800 text-white w-12 shadow-lg transition disabled:opacity-50">
                <i class="fa-solid fa-arrow-up"></i>
            </button>
        </form>
    </div>
</div>
