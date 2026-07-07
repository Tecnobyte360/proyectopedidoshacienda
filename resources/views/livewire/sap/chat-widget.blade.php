<div>
@if ($activo)
    @verbatim
    <style>
        [x-cloak] { display:none !important; }
        @keyframes iasap-in { from { opacity:0; transform: translateY(8px) scale(.98);} to { opacity:1; transform:none;} }
        @keyframes iasap-glow { 0%,100%{ box-shadow:0 0 0 0 rgba(16,185,129,.45);} 50%{ box-shadow:0 0 0 10px rgba(16,185,129,0);} }
        .iasap-msg { animation: iasap-in .3s cubic-bezier(.2,.8,.2,1) both; }
        .iasap-launch { animation: iasap-glow 2.4s ease-out infinite; }
        /* Pasos del agente en vivo */
        .astep { display:flex; align-items:center; gap:9px; font-size:12.5px; font-weight:600; color:#64748b; opacity:0; transform:translateY(4px); animation: astepIn .35s ease forwards; }
        .astep + .astep { margin-top:8px; }
        .astep.done { color:#0f172a; }
        .astep .dot { position:relative; width:16px; height:16px; flex:none; }
        .astep .dot .sp { position:absolute; inset:0; border:2px solid #d1fae5; border-top-color:#10b981; border-radius:50%; animation: aspin .7s linear infinite; }
        .astep .dot .ok { position:absolute; inset:0; display:grid; place-items:center; color:#10b981; font-size:12px; opacity:0; }
        .as1 { animation-delay:.05s; } .as2 { animation-delay:1.15s; } .as3 { animation-delay:2.3s; }
        .as1 .sp { animation: aspin .7s linear infinite, aOut 0s linear 1.15s forwards; }
        .as1 .ok { animation: aIn .25s ease 1.15s forwards; }
        .as2 .sp { animation: aspin .7s linear infinite, aOut 0s linear 2.3s forwards; }
        .as2 .ok { animation: aIn .25s ease 2.3s forwards; }
        @keyframes astepIn { to { opacity:1; transform:none; } }
        @keyframes aspin { to { transform: rotate(360deg); } }
        @keyframes aOut { to { opacity:0; } }
        @keyframes aIn { to { opacity:1; } }
        .iasap-scroll::-webkit-scrollbar { width:6px; }
        .iasap-scroll::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:99px; }
        .md-body { font-size:13.5px; line-height:1.6; color:#334155; }
        .md-body > :first-child { margin-top:0; } .md-body > :last-child { margin-bottom:0; }
        .md-body p { margin:.45rem 0; }
        .md-body strong { font-weight:700; color:#0f172a; }
        .md-body h1,.md-body h2,.md-body h3 { font-weight:800; color:#0f172a; margin:.8rem 0 .4rem; line-height:1.25; font-size:.95rem; }
        .md-body ul,.md-body ol { margin:.45rem 0; padding-left:1.15rem; }
        .md-body ul { list-style:disc; } .md-body ol { list-style:decimal; }
        .md-body li { margin:.15rem 0; } .md-body li::marker { color:#10b981; font-weight:700; }
        .md-body a { color:#0b8659; text-decoration:underline; }
        .md-body code { background:#f1f5f9; border-radius:6px; padding:1px 5px; font-size:.85em; }
        .md-body table { width:100%; border-collapse:collapse; margin:.5rem 0; font-size:12px; display:block; overflow-x:auto; }
        .md-body th,.md-body td { border:1px solid #e5e7eb; padding:5px 8px; text-align:left; }
        .md-body th { background:#f0fdf4; color:#065f46; font-weight:700; }
    </style>
    @endverbatim

    <div x-data="{ open: @entangle('abierto') }" class="fixed bottom-5 right-5 z-[9998] flex flex-col items-end gap-3"
         style="font-family:'Plus Jakarta Sans',system-ui,sans-serif">

        {{-- POPUP --}}
        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="w-[92vw] max-w-[380px] h-[560px] max-h-[75vh] flex flex-col rounded-3xl bg-white border border-slate-200 shadow-[0_24px_70px_-15px_rgba(6,78,59,.4)] overflow-hidden">

            {{-- Header --}}
            <div class="relative flex items-center gap-3 px-4 py-3.5 bg-gradient-to-br from-emerald-600 to-emerald-700 text-white overflow-hidden">
                <div class="absolute -top-10 -right-6 h-28 w-28 rounded-full bg-white/10 blur-xl"></div>
                <span class="relative flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 backdrop-blur text-white">
                    <i class="fa-solid fa-robot"></i>
                </span>
                <div class="relative">
                    <b class="block text-sm leading-tight">IA SAP · Ventas</b>
                    <span class="text-[11px] text-emerald-100 inline-flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-lime-300 animate-pulse"></span> Conectado a SAP en vivo
                    </span>
                </div>
                <button @click="open=false" class="relative ml-auto h-8 w-8 grid place-items-center rounded-lg hover:bg-white/15 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            {{-- Mensajes --}}
            <div class="iasap-scroll flex-1 overflow-y-auto px-3.5 py-4 space-y-3.5 bg-gradient-to-b from-slate-50 to-white"
                 x-data x-effect="$wire.mensajes; $nextTick(() => $el.scrollTo({top:$el.scrollHeight, behavior:'smooth'}))">

                @forelse ($mensajes as $m)
                    <div class="iasap-msg flex gap-2 max-w-[94%] {{ $m['role'] === 'user' ? 'ml-auto flex-row-reverse' : '' }}">
                        <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-white text-xs shadow
                            {{ $m['role'] === 'user' ? 'bg-slate-800' : 'bg-gradient-to-br from-emerald-500 to-emerald-700' }}">
                            <i class="fa-solid {{ $m['role'] === 'user' ? 'fa-user' : 'fa-robot' }}"></i>
                        </span>
                        <div class="min-w-0">
                            @if ($m['role'] === 'user')
                                <div class="rounded-2xl rounded-tr-sm px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-wrap bg-gradient-to-br from-emerald-600 to-emerald-700 text-white shadow">{{ $m['content'] }}</div>
                            @else
                                <div class="rounded-2xl rounded-tl-sm px-3.5 py-2.5 bg-white border border-slate-200 shadow-sm">
                                    <div class="md-body">{!! $m['html'] ?? e($m['content']) !!}</div>
                                </div>
                                @if (!empty($m['tools']))
                                    <div class="mt-1 ml-1 text-[10px] text-slate-400 font-semibold">
                                        <i class="fa-solid fa-plug text-emerald-500"></i> SAP · {{ implode(' · ', $m['tools']) }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 iasap-msg">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 text-lg">
                            <i class="fa-solid fa-comments-dollar"></i>
                        </div>
                        <p class="text-slate-600 text-sm font-semibold">¿En qué te ayudo?</p>
                        <p class="text-slate-400 text-xs mt-0.5">Cotizaciones y estados de pedidos, desde SAP.</p>
                        <div class="flex flex-col gap-2 mt-4 px-2">
                            <button wire:click="preguntar('¿Cómo van las cotizaciones de los últimos 30 días? ¿Cuántas se ganaron y cuántas se perdieron?')"
                                class="text-xs font-semibold text-left bg-white border border-slate-200 rounded-xl px-3 py-2.5 hover:border-emerald-400 hover:text-emerald-700 transition">
                                <i class="fa-solid fa-file-invoice-dollar text-emerald-500 mr-1.5"></i> Cotizaciones del mes
                            </button>
                            <button wire:click="preguntar('¿Qué pedidos abiertos están vencidos con cantidades pendientes por despachar?')"
                                class="text-xs font-semibold text-left bg-white border border-slate-200 rounded-xl px-3 py-2.5 hover:border-emerald-400 hover:text-emerald-700 transition">
                                <i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1.5"></i> Pedidos vencidos
                            </button>
                        </div>
                    </div>
                @endforelse

                <div wire:loading.flex wire:target="enviar,preguntar" class="iasap-msg gap-2">
                    <span class="iasap-launch flex h-7 w-7 flex-none items-center justify-center rounded-lg text-white text-xs bg-gradient-to-br from-emerald-500 to-emerald-700 shadow">
                        <i class="fa-solid fa-robot"></i>
                    </span>
                    <div class="rounded-2xl rounded-tl-sm px-4 py-3 bg-white border border-slate-200 shadow-sm min-w-[190px]">
                        <div class="astep as1"><span class="dot"><span class="sp"></span><i class="ok fa-solid fa-check"></i></span> Analizando tu solicitud</div>
                        <div class="astep as2"><span class="dot"><span class="sp"></span><i class="ok fa-solid fa-check"></i></span> Consultando SAP en vivo</div>
                        <div class="astep as3"><span class="dot"><span class="sp"></span><i class="ok fa-solid fa-check"></i></span> Procesando resultados</div>
                    </div>
                </div>
            </div>

            {{-- Entrada --}}
            <form wire:submit.prevent="enviar" class="border-t border-slate-100 p-2.5 bg-white">
                <div class="flex items-center gap-2 rounded-2xl border border-slate-300 bg-slate-50 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/25 focus-within:bg-white transition px-1.5 py-1">
                    <input type="text" wire:model="entrada" autocomplete="off"
                        placeholder="Escribe tu pregunta…"
                        class="flex-1 bg-transparent border-0 focus:ring-0 focus:outline-none px-2.5 py-1.5 text-sm"
                        wire:loading.attr="disabled" wire:target="enviar,preguntar">
                    <button type="submit" wire:loading.attr="disabled" wire:target="enviar,preguntar"
                        class="inline-flex items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 hover:from-emerald-600 hover:to-emerald-800 text-white h-9 w-9 shadow-lg shadow-emerald-500/30 transition disabled:opacity-50 active:scale-95">
                        <i class="fa-solid fa-arrow-up" wire:loading.remove wire:target="enviar,preguntar"></i>
                        <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="enviar,preguntar"></i>
                    </button>
                </div>
            </form>
        </div>

        {{-- LAUNCHER --}}
        <button x-show="!open" x-cloak @click="open=true"
                class="iasap-launch group flex items-center gap-2 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-700 text-white h-14 pl-4 pr-5 shadow-xl shadow-emerald-600/30 hover:scale-105 transition">
            <i class="fa-solid fa-robot text-lg"></i>
            <span class="font-bold text-sm">IA SAP</span>
        </button>
    </div>
@endif
</div>
