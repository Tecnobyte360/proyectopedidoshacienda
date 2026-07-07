<div class="p-4 sm:p-6" wire:key="ia-sap-chat">
    @verbatim
    <style>
        @keyframes iasap-in { from { opacity:0; transform: translateY(10px) scale(.98); } to { opacity:1; transform:none; } }
        @keyframes iasap-glow { 0%,100% { box-shadow:0 0 0 0 rgba(16,185,129,.35);} 50%{ box-shadow:0 0 0 8px rgba(16,185,129,0);} }
        @keyframes iasap-float { 0%,100%{ transform:translateY(0);} 50%{ transform:translateY(-6px);} }
        .iasap-msg { animation: iasap-in .35s cubic-bezier(.2,.8,.2,1) both; }
        .astep { display:flex; align-items:center; gap:9px; font-size:13.5px; font-weight:600; color:#64748b; opacity:0; transform:translateY(4px); animation: astepIn .35s ease forwards; }
        .astep + .astep { margin-top:9px; }
        .astep .dot { position:relative; width:17px; height:17px; flex:none; }
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
        .iasap-bot-av { animation: iasap-glow 2.4s ease-out infinite; }
        .iasap-scroll::-webkit-scrollbar { width:8px; }
        .iasap-scroll::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:99px; }
        .iasap-hero-ic { animation: iasap-float 3.5s ease-in-out infinite; }
        /* Markdown de las respuestas */
        .md-body { font-size:14px; line-height:1.6; color:#334155; }
        .md-body > :first-child { margin-top:0; }
        .md-body > :last-child { margin-bottom:0; }
        .md-body p { margin:.5rem 0; }
        .md-body strong { font-weight:700; color:#0f172a; }
        .md-body h1,.md-body h2,.md-body h3 { font-weight:800; color:#0f172a; margin:.9rem 0 .5rem; line-height:1.25; }
        .md-body h1 { font-size:1.05rem; } .md-body h2 { font-size:1rem; } .md-body h3 { font-size:.95rem; }
        .md-body ul,.md-body ol { margin:.5rem 0; padding-left:1.25rem; }
        .md-body ul { list-style:disc; } .md-body ol { list-style:decimal; }
        .md-body li { margin:.2rem 0; }
        .md-body li::marker { color:#10b981; font-weight:700; }
        .md-body a { color:#0b8659; text-decoration:underline; }
        .md-body code { background:#f1f5f9; border-radius:6px; padding:1px 6px; font-size:.85em; }
        .md-body table { width:100%; border-collapse:collapse; margin:.6rem 0; font-size:13px; display:block; overflow-x:auto; }
        .md-body th,.md-body td { border:1px solid #e5e7eb; padding:6px 10px; text-align:left; }
        .md-body th { background:#f0fdf4; color:#065f46; font-weight:700; }
        .md-body hr { border:0; border-top:1px solid #e5e7eb; margin:.8rem 0; }
    </style>
    @endverbatim

    <div class="mx-auto max-w-4xl flex flex-col rounded-3xl bg-white border border-slate-200 shadow-[0_20px_60px_-20px_rgba(6,78,59,.25)] overflow-hidden"
         style="height:calc(100vh - 130px)">

        {{-- Encabezado --}}
        <div class="relative flex items-center gap-3 px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50 via-white to-white overflow-hidden">
            <div class="absolute -top-16 -right-10 h-40 w-40 rounded-full bg-emerald-200/40 blur-2xl"></div>
            <span class="iasap-bot-av relative flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg">
                <i class="fa-solid fa-robot"></i>
            </span>
            <div class="relative">
                <h2 class="font-extrabold text-slate-800 leading-tight">IA SAP · Ventas</h2>
                <p class="text-xs text-slate-500">Consulta tus cotizaciones y pedidos directamente de SAP, en tiempo real.</p>
            </div>
            <span class="relative ml-auto inline-flex items-center gap-2 text-[11px] font-bold text-emerald-700 bg-white/70 backdrop-blur border border-emerald-200 rounded-full px-3 py-1.5 shadow-sm">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> SAP EN VIVO
            </span>
        </div>

        {{-- Mensajes --}}
        <div class="iasap-scroll flex-1 overflow-y-auto px-4 sm:px-6 py-6 space-y-5 bg-gradient-to-b from-slate-50 to-white"
             x-data x-effect="$wire.mensajes; $nextTick(() => $el.scrollTo({top:$el.scrollHeight, behavior:'smooth'}))">

            @forelse ($mensajes as $m)
                <div class="iasap-msg flex gap-3 max-w-[92%] {{ $m['role'] === 'user' ? 'ml-auto flex-row-reverse' : '' }}">
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl text-white text-sm shadow
                        {{ $m['role'] === 'user' ? 'bg-slate-800' : 'bg-gradient-to-br from-emerald-500 to-emerald-700' }}">
                        <i class="fa-solid {{ $m['role'] === 'user' ? 'fa-user' : 'fa-robot' }}"></i>
                    </span>
                    <div class="min-w-0">
                        @if ($m['role'] === 'user')
                            <div class="rounded-2xl rounded-tr-sm px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap bg-gradient-to-br from-emerald-600 to-emerald-700 text-white shadow-md">{{ $m['content'] }}</div>
                        @else
                            <div class="rounded-2xl rounded-tl-sm px-4 py-3 bg-white border border-slate-200 shadow-sm">
                                <div class="md-body">{!! $m['html'] ?? e($m['content']) !!}</div>
                            </div>
                            @if (!empty($m['tools']))
                                <div class="mt-1.5 ml-1 text-[11px] text-slate-400 font-semibold">
                                    <i class="fa-solid fa-plug text-emerald-500"></i> Consultó SAP · {{ implode(' · ', $m['tools']) }}
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-10 iasap-msg">
                    <div class="iasap-hero-ic mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white text-2xl shadow-xl shadow-emerald-500/25">
                        <i class="fa-solid fa-comments-dollar"></i>
                    </div>
                    <h3 class="font-bold text-slate-800">¿En qué te ayudo hoy?</h3>
                    <p class="text-slate-500 text-sm mt-1">Pregúntame sobre <b class="text-slate-700">cotizaciones</b> y <b class="text-slate-700">estados de pedidos</b>.</p>
                    <div class="flex flex-wrap gap-2 justify-center mt-5">
                        <button wire:click="preguntar('¿Cómo van las cotizaciones de los últimos 30 días? ¿Cuántas se ganaron y cuántas se perdieron?')"
                            class="group text-xs font-semibold bg-white border border-slate-200 rounded-full px-4 py-2.5 hover:border-emerald-400 hover:text-emerald-700 hover:-translate-y-0.5 transition shadow-sm">
                            <i class="fa-solid fa-file-invoice-dollar text-emerald-500 mr-1"></i> Cotizaciones del mes
                        </button>
                        <button wire:click="preguntar('¿Qué pedidos abiertos están vencidos con cantidades pendientes por despachar?')"
                            class="group text-xs font-semibold bg-white border border-slate-200 rounded-full px-4 py-2.5 hover:border-emerald-400 hover:text-emerald-700 hover:-translate-y-0.5 transition shadow-sm">
                            <i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1"></i> Pedidos vencidos
                        </button>
                        <button wire:click="preguntar('Muéstrame los pedidos abiertos con cantidades retrasadas por despachar o facturar')"
                            class="group text-xs font-semibold bg-white border border-slate-200 rounded-full px-4 py-2.5 hover:border-emerald-400 hover:text-emerald-700 hover:-translate-y-0.5 transition shadow-sm">
                            <i class="fa-solid fa-truck-fast text-emerald-500 mr-1"></i> Atrasos de despacho
                        </button>
                    </div>
                </div>
            @endforelse

            {{-- "pensando" --}}
            <div wire:loading.flex wire:target="enviar,preguntar" class="iasap-msg gap-3">
                <span class="iasap-bot-av flex h-9 w-9 flex-none items-center justify-center rounded-xl text-white text-sm bg-gradient-to-br from-emerald-500 to-emerald-700 shadow">
                    <i class="fa-solid fa-robot"></i>
                </span>
                <div class="rounded-2xl rounded-tl-sm px-4 py-3.5 bg-white border border-slate-200 shadow-sm min-w-[210px]">
                    <div class="astep as1"><span class="dot"><span class="sp"></span><i class="ok fa-solid fa-check"></i></span> Analizando tu solicitud</div>
                    <div class="astep as2"><span class="dot"><span class="sp"></span><i class="ok fa-solid fa-check"></i></span> Consultando SAP en vivo</div>
                    <div class="astep as3"><span class="dot"><span class="sp"></span><i class="ok fa-solid fa-check"></i></span> Procesando resultados</div>
                </div>
            </div>
        </div>

        {{-- Entrada --}}
        <form wire:submit.prevent="enviar" class="border-t border-slate-100 p-3 sm:p-4 bg-white">
            <div class="flex items-center gap-2 rounded-2xl border border-slate-300 bg-slate-50 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/25 focus-within:bg-white transition px-2 py-1.5">
                <input type="text" wire:model="entrada" autocomplete="off" autofocus
                    placeholder="Escribe tu pregunta sobre ventas…"
                    class="flex-1 bg-transparent border-0 focus:ring-0 focus:outline-none px-3 py-2 text-sm"
                    wire:loading.attr="disabled" wire:target="enviar,preguntar">
                <button type="submit" wire:loading.attr="disabled" wire:target="enviar,preguntar"
                    class="inline-flex items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 hover:from-emerald-600 hover:to-emerald-800 text-white h-10 w-10 shadow-lg shadow-emerald-500/30 transition disabled:opacity-50 active:scale-95">
                    <i class="fa-solid fa-arrow-up" wire:loading.remove wire:target="enviar,preguntar"></i>
                    <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="enviar,preguntar"></i>
                </button>
            </div>
        </form>
    </div>
</div>
