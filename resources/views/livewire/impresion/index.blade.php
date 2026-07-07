<div class="px-6 lg:px-10 py-8" wire:poll.5s>

    <div class="mb-6">
        <h2 class="text-3xl font-extrabold text-slate-800">
            <i class="fa-solid fa-print text-brand"></i> Impresoras
        </h2>
        <p class="text-sm text-slate-500">Envía tickets a la impresora conectada al PC — desde la tablet o cualquier equipo.</p>
    </div>

    @forelse($impresoras as $imp)
        @php
            $enLinea = $imp->enLinea();
        @endphp
        <div class="rounded-2xl bg-white shadow border border-slate-200 p-5 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand/10 text-brand text-xl">
                        <i class="fa-solid fa-print"></i>
                    </div>
                    <div>
                        <div class="font-bold text-slate-800">{{ $imp->nombre }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $imp->printer_name ?: 'Sin nombre de impresora' }}
                            @if($imp->pc_nombre) · {{ $imp->pc_nombre }} @endif
                        </div>
                        <div class="mt-1">
                            @if($enLinea)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[11px] font-bold">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Agente en línea
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-200 text-slate-600 px-2 py-0.5 text-[11px] font-bold"
                                      title="El agente no ha reportado en el último minuto">
                                    <i class="fa-solid fa-plug-circle-xmark text-[10px]"></i>
                                    Agente desconectado
                                    @if($imp->ultima_conexion_at) · visto {{ $imp->ultima_conexion_at->diffForHumans() }} @endif
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <button wire:click="enviarPrueba({{ $imp->id }})"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand hover:opacity-90 text-white font-bold px-5 py-3 transition shadow disabled:opacity-50">
                    <i class="fa-solid fa-paper-plane"></i>
                    Imprimir prueba
                </button>
            </div>

            @unless($enLinea)
                <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs px-3 py-2">
                    ⚠️ El agente de este PC no está reportando. Verifica que el servicio
                    <b>Kivox Print Agent</b> esté corriendo en {{ $imp->pc_nombre ?: 'el PC de la impresora' }}.
                    Igual puedes enviar la prueba: saldrá apenas el agente vuelva.
                </div>
            @endunless
        </div>
    @empty
        <div class="rounded-2xl bg-white p-10 text-center text-slate-400 shadow">
            <i class="fa-solid fa-print text-4xl mb-3 block opacity-50"></i>
            <p class="text-sm">No hay impresoras registradas para este comercio.</p>
        </div>
    @endforelse

    {{-- Cola / historial reciente --}}
    <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden mt-6">
        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50">
            <h3 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-list-check text-slate-500"></i> Últimos trabajos</h3>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($trabajos as $t)
                @php
                    $badge = match($t->estado) {
                        'impreso'  => 'bg-emerald-100 text-emerald-700',
                        'enviado'  => 'bg-blue-100 text-blue-700',
                        'error'    => 'bg-rose-100 text-rose-700',
                        default    => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <div class="px-5 py-3 flex items-center justify-between gap-3">
                    <div class="text-sm text-slate-700">
                        #{{ $t->id }} · <span class="capitalize">{{ $t->tipo }}</span>
                        <span class="text-xs text-slate-400">{{ $t->created_at->format('d/m h:i a') }}</span>
                        @if($t->error)<span class="text-xs text-rose-500"> · {{ \Illuminate\Support\Str::limit($t->error, 40) }}</span>@endif
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $badge }}">
                        {{ ucfirst($t->estado) }}
                    </span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-slate-400 text-sm">Aún no se ha enviado ningún trabajo.</div>
            @endforelse
        </div>
    </div>
</div>
