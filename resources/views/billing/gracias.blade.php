<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago — Kivox</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="/favicon.ico">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-100 flex items-center justify-center p-6">
    <div class="w-full max-w-lg">
        @php
            $color = match($estado) {
                'confirmado' => ['bg' => 'from-emerald-500 to-emerald-600', 'icon' => 'fa-check', 'titulo' => '¡Pago recibido!', 'sub' => 'Gracias, tu suscripción quedó al día.'],
                'rechazado'  => ['bg' => 'from-rose-500 to-rose-600',       'icon' => 'fa-xmark', 'titulo' => 'Pago rechazado',   'sub' => 'Wompi no pudo procesar el pago. Intenta de nuevo.'],
                default      => ['bg' => 'from-amber-400 to-amber-500',     'icon' => 'fa-hourglass-half', 'titulo' => 'Pago en proceso', 'sub' => 'Estamos confirmando con Wompi, recibirás un WhatsApp cuando se acredite.'],
            };
        @endphp

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            {{-- Header con icono grande --}}
            <div class="bg-gradient-to-br {{ $color['bg'] }} px-8 py-10 text-center text-white">
                <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm mb-4">
                    <i class="fa-solid {{ $color['icon'] }} text-4xl"></i>
                </div>
                <h1 class="text-3xl font-extrabold mb-1">{{ $color['titulo'] }}</h1>
                <p class="text-white/90 text-sm">{{ $color['sub'] }}</p>
            </div>

            {{-- Detalles del pago si lo encontramos --}}
            <div class="px-8 py-6">
                @if($pago)
                    <div class="space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                            <span class="text-xs text-slate-500 uppercase font-bold">Empresa</span>
                            <span class="text-sm font-bold text-slate-800">{{ $pago->tenant?->nombre ?? '—' }}</span>
                        </div>
                        @if($pago->suscripcion?->plan)
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                                <span class="text-xs text-slate-500 uppercase font-bold">Plan</span>
                                <span class="text-sm font-bold text-slate-800">{{ $pago->suscripcion->plan->nombre }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                            <span class="text-xs text-slate-500 uppercase font-bold">Monto</span>
                            <span class="text-lg font-extrabold text-emerald-600">${{ number_format((float) $pago->monto, 0, ',', '.') }} {{ $pago->moneda }}</span>
                        </div>
                        @if($pago->cubre_hasta)
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                                <span class="text-xs text-slate-500 uppercase font-bold">Cubre hasta</span>
                                <span class="text-sm font-bold text-slate-800">{{ $pago->cubre_hasta->format('d M Y') }}</span>
                            </div>
                        @endif
                        @if($pago->wompi_transaction_id)
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100">
                                <span class="text-xs text-slate-500 uppercase font-bold">Ref. transacción</span>
                                <span class="text-[11px] font-mono text-slate-600">{{ $pago->wompi_transaction_id }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-6 flex flex-col gap-2">
                    <a href="/" class="block w-full text-center py-3 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-sm transition">
                        Volver al inicio
                    </a>
                    @if($estado === 'pendiente' && $txId)
                        <button type="button" onclick="location.reload()"
                                class="block w-full text-center py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition">
                            <i class="fa-solid fa-rotate-right"></i> Verificar estado
                        </button>
                    @endif
                </div>

                <p class="mt-6 text-center text-[11px] text-slate-400">
                    Si tienes dudas, contáctanos:
                    <a href="mailto:comercial@tecnobyte360.com" class="underline">comercial@tecnobyte360.com</a>
                </p>
            </div>
        </div>

        {{-- Footer Kivox --}}
        <div class="text-center mt-6 text-xs text-slate-400">
            Procesado por <strong class="text-slate-600">Wompi</strong> · Plataforma <strong class="text-slate-600">Kivox</strong> by TecnoByte360
        </div>
    </div>
</body>
</html>
