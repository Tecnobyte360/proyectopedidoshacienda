<div wire:poll.{{ in_array($status, ['qrcode', 'pairing']) ? '5000ms' : '30000ms' }}="verificarEstado">
    @php
        $config = match ($status) {
            'connected' => [
                'bg' => 'bg-emerald-50 border-emerald-200',
                'dot' => 'bg-emerald-500',
                'pulse' => 'bg-emerald-400',
                'text' => 'text-emerald-700',
                'sub' => 'text-emerald-600',
                'badge' => 'bg-emerald-100 text-emerald-700',
                'ping' => true,
            ],
            'qrcode', 'pairing' => [
                'bg' => 'bg-amber-50 border-amber-200',
                'dot' => 'bg-amber-500',
                'pulse' => 'bg-amber-400',
                'text' => 'text-amber-700',
                'sub' => 'text-amber-600',
                'badge' => 'bg-amber-100 text-amber-700',
                'ping' => true,
            ],
            'disconnected', 'timeout', 'not_connected' => [
                'bg' => 'bg-rose-50 border-rose-200',
                'dot' => 'bg-rose-500',
                'pulse' => 'bg-rose-400',
                'text' => 'text-rose-700',
                'sub' => 'text-rose-600',
                'badge' => 'bg-rose-100 text-rose-700',
                'ping' => false,
            ],
            default => [
                'bg' => 'bg-slate-50 border-slate-200',
                'dot' => 'bg-slate-400',
                'pulse' => 'bg-slate-300',
                'text' => 'text-slate-600',
                'sub' => 'text-slate-400',
                'badge' => 'bg-slate-100 text-slate-500',
                'ping' => false,
            ],
        };

        $qrHash = !blank($qrCode) ? md5($qrCode) : 'sin-qr';
    @endphp

    <div class="flex items-center justify-between rounded-xl border {{ $config['bg'] }} px-4 py-2.5 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-100 bg-white shadow-sm">
                <i class="fa-brands fa-whatsapp text-base
                    {{ $status === 'connected'
                        ? 'text-emerald-500'
                        : (in_array($status, ['qrcode', 'pairing'])
                            ? 'text-amber-500'
                            : (in_array($status, ['disconnected', 'timeout', 'not_connected'])
                                ? 'text-rose-400'
                                : 'text-slate-400')) }}">
                </i>
            </div>

            <div class="relative flex h-2.5 w-2.5 shrink-0">
                @if ($config['ping'])
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $config['pulse'] }} opacity-60"></span>
                @endif
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $config['dot'] }}"></span>
            </div>

            <div class="leading-tight">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold {{ $config['text'] }}">WhatsApp</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $config['badge'] }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                @if ($phoneNumber)
                    <p class="mt-0.5 text-[11px] {{ $config['sub'] }}">{{ $phoneNumber }}</p>
                @endif

                @if ($disconnectReason)
                    <p class="mt-0.5 text-[11px] {{ $config['sub'] }}">
                        Motivo: {{ $disconnectReason }}
                    </p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($lastChecked)
                <span class="hidden text-[11px] text-slate-400 sm:block">
                    Verificado: {{ $lastChecked }}
                </span>
            @endif

            @if (in_array($status, ['qrcode', 'pairing']) && $qrCode)
                <button
                    wire:click="$toggle('showQr')"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-700 transition hover:bg-amber-200">
                    <i class="fa-solid fa-qrcode text-[11px]"></i>
                    {{ $showQr ? 'Ocultar QR' : 'Ver QR' }}
                </button>
            @endif

            @if (in_array($status, ['disconnected', 'error', 'qrcode', 'pairing', 'timeout', 'not_connected']))
                <button
                    wire:click="solicitarNuevoQr"
                    wire:loading.attr="disabled"
                    wire:target="solicitarNuevoQr"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 shadow-sm transition hover:bg-slate-50 disabled:opacity-50">
                    <i class="fa-solid fa-rotate-right text-[11px]" wire:loading.class="fa-spin" wire:target="solicitarNuevoQr"></i>
                    <span wire:loading.remove wire:target="solicitarNuevoQr">Nuevo QR</span>
                    <span wire:loading wire:target="solicitarNuevoQr">Generando...</span>
                </button>
            @endif

            <button
                wire:click="verificarEstado"
                wire:loading.attr="disabled"
                wire:target="verificarEstado"
                type="button"
                title="Verificar ahora"
                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:bg-slate-50 disabled:opacity-50">
                <i class="fa-solid fa-rotate-right text-xs" wire:loading.class="fa-spin" wire:target="verificarEstado"></i>
            </button>
        </div>
    </div>

    @if ($showQr && $qrCode && in_array($status, ['qrcode', 'pairing']))
        <div
            wire:key="qr-panel-{{ $qrHash }}"
            class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-start sm:gap-6">

                <div class="flex shrink-0 flex-col items-center gap-2">
                    <div class="rounded-xl border-4 border-white bg-white p-2 shadow-md">
                        <img
                            wire:key="qr-image-{{ $qrHash }}"
                            src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=4&data={{ urlencode($qrCode) }}"
                            alt="QR WhatsApp"
                            width="200"
                            height="200"
                            class="rounded-lg"
                        >
                    </div>

                    <span class="text-[10px] font-semibold uppercase tracking-wide text-amber-600">
                        Escanea con WhatsApp
                    </span>
                </div>

                <div class="flex-1">
                    <h4 class="text-sm font-bold text-amber-800">¿Cómo reconectar?</h4>

                    <ol class="mt-2 space-y-1.5 text-xs text-amber-700">
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">1</span>
                            Abre WhatsApp en tu teléfono.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">2</span>
                            Ve a <strong>Dispositivos vinculados</strong> → <strong>Vincular dispositivo</strong>.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">3</span>
                            Escanea el código QR que aparece a la izquierda.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">4</span>
                            El sistema verificará automáticamente la reconexión.
                        </li>
                    </ol>

                    <div class="mt-3 flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white/70 px-3 py-2 text-[11px] text-amber-700">
                        <i class="fa-solid fa-circle-info text-amber-500"></i>
                        El QR expira en pocos minutos. Si expiró, presiona <strong class="ml-1">Nuevo QR</strong>.
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>