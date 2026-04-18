<div class="p-4 lg:p-8 space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                🚨 Alertas del bot
            </h2>
            <p class="text-sm text-slate-500">
                Errores operativos: OpenAI, WhatsApp, Reverb. El sistema deduplica errores repetidos.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($totales['no_resueltas'] > 0)
                <button wire:click="resolverTodas"
                        wire:confirm="¿Marcar TODAS las alertas no resueltas como resueltas?"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 transition shadow-sm">
                    <i class="fa-solid fa-check-double"></i>
                    Resolver todas
                </button>
            @endif
            <button wire:click="limpiarResueltas"
                    wire:confirm="¿Eliminar definitivamente todas las alertas ya resueltas?"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold px-4 py-2 transition">
                <i class="fa-solid fa-broom"></i>
                Limpiar resueltas
            </button>
        </div>
    </div>

    {{-- Métricas --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Total</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800">{{ $totales['total'] }}</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Sin resolver</p>
            <p class="mt-1 text-2xl font-extrabold {{ $totales['no_resueltas'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                {{ $totales['no_resueltas'] }}
            </p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Críticas</p>
            <p class="mt-1 text-2xl font-extrabold {{ $totales['criticas'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                {{ $totales['criticas'] }}
            </p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Warnings</p>
            <p class="mt-1 text-2xl font-extrabold {{ $totales['warnings'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                {{ $totales['warnings'] }}
            </p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="rounded-2xl bg-white border border-slate-200 p-4">
        <div class="grid md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Estado</label>
                <select wire:model.live="filtroEstado"
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    <option value="no_resueltas">Sin resolver</option>
                    <option value="resueltas">Resueltas</option>
                    <option value="todas">Todas</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Severidad</label>
                <select wire:model.live="filtroSeveridad"
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    <option value="todas">Todas</option>
                    <option value="critica">🔴 Crítica</option>
                    <option value="warning">🟡 Warning</option>
                    <option value="info">🔵 Info</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo</label>
                <select wire:model.live="filtroTipo"
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    @foreach($tipos as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar</label>
                <input type="text" wire:model.live.debounce.400ms="busqueda"
                       placeholder="Título o mensaje..."
                       class="w-full rounded-xl border-slate-200 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
            </div>
        </div>
    </div>

    {{-- Lista --}}
    <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
        @if($alertas->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <i class="fa-solid fa-circle-check text-5xl text-emerald-400 mb-3"></i>
                <p class="text-lg font-semibold text-slate-600">Sin alertas que coincidan</p>
                <p class="text-sm">El bot está funcionando bien o no hay registros para los filtros seleccionados.</p>
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach($alertas as $a)
                    <li id="alerta-{{ $a->id }}"
                        class="px-4 py-4 hover:bg-slate-50 transition {{ $a->resuelta ? 'opacity-70' : '' }}">
                        <div class="flex gap-3 items-start">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg
                                        @if($a->severidad === 'critica') bg-rose-50
                                        @elseif($a->severidad === 'warning') bg-amber-50
                                        @else bg-blue-50
                                        @endif">
                                {{ $a->icono() }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-800">{{ $a->titulo }}</h3>

                                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full
                                                 @if($a->severidad === 'critica') bg-rose-100 text-rose-700
                                                 @elseif($a->severidad === 'warning') bg-amber-100 text-amber-700
                                                 @else bg-blue-100 text-blue-700
                                                 @endif">
                                        {{ $a->severidad }}
                                    </span>

                                    @if($a->codigo_http)
                                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                            HTTP {{ $a->codigo_http }}
                                        </span>
                                    @endif

                                    @if($a->ocurrencias > 1)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-800 text-white">
                                            ×{{ $a->ocurrencias }}
                                        </span>
                                    @endif

                                    @if($a->resuelta)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                                            ✓ Resuelta
                                        </span>
                                    @endif
                                </div>

                                <p class="text-sm text-slate-600 mt-1 whitespace-pre-line">{{ $a->mensaje }}</p>

                                <div class="text-[11px] text-slate-400 mt-2 flex flex-wrap gap-x-3 gap-y-1">
                                    <span><i class="fa-regular fa-clock"></i> {{ $a->ultima_ocurrencia_at?->diffForHumans() ?? $a->created_at->diffForHumans() }}</span>
                                    <span>·</span>
                                    <span>Creada: {{ $a->created_at->format('d/m/Y H:i') }}</span>
                                    @if($a->resuelta && $a->resuelta_at)
                                        <span>·</span>
                                        <span>Resuelta {{ $a->resuelta_at->diffForHumans() }} por {{ $a->resuelta_por }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5 shrink-0">
                                <button wire:click="abrirDetalle({{ $a->id }})"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                @if(!$a->resuelta)
                                    <button wire:click="resolver({{ $a->id }})"
                                            class="text-xs px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                @else
                                    <button wire:click="reabrir({{ $a->id }})"
                                            class="text-xs px-3 py-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 font-semibold">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                @endif
                                <button wire:click="eliminar({{ $a->id }})"
                                        wire:confirm="¿Eliminar esta alerta definitivamente?"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-semibold">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $alertas->links() }}
            </div>
        @endif
    </div>

    {{-- Modal detalle --}}
    @if($detalle)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
             wire:click.self="cerrarDetalle">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl text-lg
                                    @if($detalle->severidad === 'critica') bg-rose-50
                                    @elseif($detalle->severidad === 'warning') bg-amber-50
                                    @else bg-blue-50
                                    @endif">
                            {{ $detalle->icono() }}
                        </div>
                        <h3 class="font-bold text-slate-800 truncate">{{ $detalle->titulo }}</h3>
                    </div>
                    <button wire:click="cerrarDetalle" class="text-slate-400 hover:text-slate-700 text-xl">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="px-5 py-4 overflow-y-auto space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide mb-1">Mensaje</p>
                        <p class="text-sm text-slate-700 whitespace-pre-line">{{ $detalle->mensaje }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Tipo</p>
                            <p class="text-slate-700 font-mono">{{ $detalle->tipo }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Severidad</p>
                            <p class="text-slate-700">{{ $detalle->severidad }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Código HTTP</p>
                            <p class="text-slate-700">{{ $detalle->codigo_http ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Ocurrencias</p>
                            <p class="text-slate-700">{{ $detalle->ocurrencias }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Primera vez</p>
                            <p class="text-slate-700">{{ $detalle->created_at->format('d/m/Y H:i:s') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide">Última vez</p>
                            <p class="text-slate-700">{{ $detalle->ultima_ocurrencia_at?->format('d/m/Y H:i:s') ?? '—' }}</p>
                        </div>
                    </div>

                    @if($detalle->contexto)
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 tracking-wide mb-1">Contexto</p>
                            <pre class="text-[11px] bg-slate-900 text-emerald-300 rounded-xl p-3 overflow-x-auto max-h-64">{{ json_encode($detalle->contexto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif
                </div>

                <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
                    @if(!$detalle->resuelta)
                        <button wire:click="resolver({{ $detalle->id }})"
                                class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                            <i class="fa-solid fa-check mr-1"></i> Marcar resuelta
                        </button>
                    @else
                        <button wire:click="reabrir({{ $detalle->id }})"
                                class="px-4 py-2 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-700 text-sm font-semibold">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Reabrir
                        </button>
                    @endif
                    <button wire:click="cerrarDetalle"
                            class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
