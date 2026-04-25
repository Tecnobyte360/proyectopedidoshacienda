<div class="relative" x-data="{ open: @entangle('abierto') }" @click.outside="open = false; $wire.cerrar()" wire:poll.30s="refrescar">
    {{-- Botón de la campana --}}
    <button type="button"
            wire:click="toggle"
            @click="if(open){ $wire.marcarVistas() }"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl
                   {{ $criticas > 0 ? 'bg-rose-50 text-rose-600 hover:bg-rose-100' : ($noResueltas > 0 ? 'bg-amber-50 text-amber-600 hover:bg-amber-100' : 'bg-slate-100 text-slate-700 hover:bg-slate-200') }}
                   transition"
            title="Alertas del bot">
        <i class="fa-solid {{ $criticas > 0 ? 'fa-triangle-exclamation' : 'fa-bell' }}"></i>

        @if($noResueltas > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full
                         {{ $criticas > 0 ? 'bg-rose-500 animate-pulse' : 'bg-amber-500' }}
                         px-1 text-[10px] font-bold text-white ring-2 ring-white">
                {{ $noResueltas > 99 ? '99+' : $noResueltas }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="absolute right-0 mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white shadow-2xl z-50 overflow-hidden"
         style="display: none;">

        <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-800">🚨 Alertas del bot</h3>
                <p class="text-xs text-slate-500">
                    @if($noResueltas === 0)
                        Todo en orden
                    @else
                        {{ $noResueltas }} sin resolver{{ $criticas > 0 ? ' · '.$criticas.' críticas' : '' }}
                    @endif
                </p>
            </div>
            <a href="{{ route('alertas.index') }}" class="text-xs font-semibold text-brand-secondary hover:underline">
                Ver todas
            </a>
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
            @forelse($ultimas as $a)
                <a href="{{ route('alertas.index') }}#alerta-{{ $a->id }}"
                   class="flex gap-3 px-4 py-3 hover:bg-slate-50 transition {{ $a->resuelta ? 'opacity-60' : '' }}">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl text-base shrink-0
                                @if($a->severidad === 'critica') bg-rose-50
                                @elseif($a->severidad === 'warning') bg-amber-50
                                @else bg-blue-50
                                @endif">
                        {{ $a->icono() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $a->titulo }}</p>
                            @if($a->ocurrencias > 1)
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                    ×{{ $a->ocurrencias }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 line-clamp-2 mt-0.5">{{ $a->mensaje }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">
                            {{ $a->ultima_ocurrencia_at?->diffForHumans() ?? $a->created_at->diffForHumans() }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center text-slate-400 text-sm">
                    <i class="fa-solid fa-circle-check text-2xl mb-2 text-emerald-400"></i>
                    <p>Sin alertas. Todo funcionando bien 👌</p>
                </div>
            @endforelse
        </div>

        @if($ultimas->isNotEmpty())
            <div class="border-t border-slate-100 bg-slate-50 px-4 py-2">
                <a href="{{ route('alertas.index') }}" class="block text-center text-xs font-semibold text-slate-600 hover:text-brand-secondary">
                    Gestionar todas las alertas →
                </a>
            </div>
        @endif
    </div>
</div>
