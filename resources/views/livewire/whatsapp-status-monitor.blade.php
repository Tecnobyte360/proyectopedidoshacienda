@php
    $tenant = app(\App\Services\TenantManager::class)->current();
    $usaMeta = $tenant && $tenant->proveedorWhatsappResuelto() === \App\Models\Tenant::WA_PROVIDER_META;
@endphp

{{-- ⚠️ Livewire 3 exige UN solo root. Envolvemos todo aquí y dentro
     condicionamos qué variante mostrar. Si tenant=Meta, el wire:poll
     no aplica (no hay sesión que monitorear). --}}
<div @if(!$usaMeta) wire:poll.{{ in_array($status, ['qrcode', 'pairing']) ? '5000ms' : '30000ms' }}="verificarEstado" @endif>

@if($usaMeta)
    {{-- 🟢 Tenant usa Meta Cloud API: no hay QR ni sesión que monitorear --}}
    <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-emerald-100 bg-white shadow-sm">
                <i class="fa-brands fa-whatsapp text-base text-emerald-500"></i>
            </div>
            <div class="leading-tight">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-emerald-700">WhatsApp · Meta Cloud API</span>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Activa</span>
                </div>
                <p class="mt-0.5 text-[11px] text-emerald-600">Sin sesión que monitorear (servicio cloud oficial)</p>
            </div>
        </div>
    </div>
@else
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

            @if ($status === 'connected')
                <button
                    wire:click="forzarReconexion"
                    wire:loading.attr="disabled"
                    wire:target="forzarReconexion"
                    onclick="return confirm('¿Forzar reconexión? Se cerrará la sesión actual y deberás escanear un nuevo QR.')"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-600 shadow-sm transition hover:bg-rose-100 disabled:opacity-50">
                    <i class="fa-solid fa-power-off text-[11px]" wire:loading.class="fa-spin" wire:target="forzarReconexion"></i>
                    <span wire:loading.remove wire:target="forzarReconexion">Forzar reconexión</span>
                    <span wire:loading wire:target="forzarReconexion">Cerrando…</span>
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

    {{-- 📱 MODAL FLOTANTE del QR — no rompe el layout, aparece como overlay --}}
    @if ($showQr && $qrCode && in_array($status, ['qrcode', 'pairing']))
        <div
            wire:key="qr-modal-{{ $qrHash }}"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4"
            wire:click.self="$set('showQr', false)">

            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden"
                 wire:click.stop>

                {{-- Header del modal --}}
                <div class="flex items-center justify-between border-b border-amber-100 bg-gradient-to-r from-amber-50 to-orange-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-white shadow">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-amber-900">Vincular WhatsApp</h3>
                            <p class="text-[11px] text-amber-700">Escanea con tu teléfono</p>
                        </div>
                    </div>
                    <button
                        wire:click="$set('showQr', false)"
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                {{-- Contenido --}}
                <div class="p-5">
                    <div class="flex flex-col items-center gap-3">
                        <div class="rounded-xl border-4 border-white bg-white p-2 shadow-md ring-1 ring-amber-200">
                            <img
                                wire:key="qr-image-{{ $qrHash }}"
                                src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=2&data={{ urlencode($qrCode) }}"
                                alt="QR WhatsApp"
                                width="180"
                                height="180"
                                class="rounded-md"
                            >
                        </div>

                        <span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                            Escanea con WhatsApp
                        </span>
                    </div>

                    {{-- Pasos compactos --}}
                    <ol class="mt-4 space-y-1.5 text-[12px] text-slate-700">
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">1</span>
                            <span>Abre WhatsApp en tu teléfono</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">2</span>
                            <span><strong>Dispositivos vinculados</strong> → <strong>Vincular dispositivo</strong></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white">3</span>
                            <span>Apunta la cámara al QR</span>
                        </li>
                    </ol>

                    <div class="mt-4 flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">
                        <i class="fa-solid fa-circle-info text-amber-500"></i>
                        <span>El QR expira en pocos minutos. Si expiró, presiona <strong>Nuevo QR</strong>.</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif

</div>{{-- Root unico Livewire 3 --}}