<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripción vencida — {{ $tenant->nombre }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style> body { font-family: 'Inter', system-ui, sans-serif; } </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-red-50 via-orange-50 to-rose-50 flex items-center justify-center p-6">
    <div class="w-full max-w-2xl">

        {{-- Logout discreto arriba a la derecha --}}
        <div class="flex justify-end mb-3">
            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 font-semibold inline-flex items-center gap-1">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
                </button>
            </form>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden ring-1 ring-red-100">
            {{-- Header rojo --}}
            <div class="bg-gradient-to-br from-red-500 to-rose-600 px-8 py-10 text-center text-white">
                <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm mb-4">
                    <i class="fa-solid fa-lock text-4xl"></i>
                </div>
                <h1 class="text-3xl font-extrabold mb-1">Tu suscripción está vencida</h1>
                <p class="text-white/90 text-sm">
                    Hola <strong>{{ $tenant->nombre }}</strong>, tu acceso a Kivox está temporalmente bloqueado.
                </p>
                @if($diasMora > 0)
                    <p class="text-white/80 text-xs mt-2">Venció hace {{ $diasMora }} día{{ $diasMora === 1 ? '' : 's' }}</p>
                @endif
            </div>

            {{-- Detalles --}}
            <div class="px-8 py-6 space-y-4">

                @if($suscripcion)
                    <div class="bg-slate-50 rounded-2xl p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">Plan</span>
                            <span class="font-bold text-slate-800">{{ $suscripcion->plan?->nombre ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">Ciclo</span>
                            <span class="font-bold text-slate-800">{{ ucfirst($suscripcion->ciclo) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">Fecha de vencimiento</span>
                            <span class="font-bold text-slate-800">{{ $suscripcion->fecha_fin?->format('d/m/Y') ?? '—' }}</span>
                        </div>
                        <div class="border-t border-slate-200 my-2"></div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-600 font-semibold">Monto a pagar</span>
                            <span class="text-3xl font-extrabold text-rose-600">
                                ${{ number_format((float)($pago?->monto ?? $suscripcion->monto), 0, ',', '.') }}
                                <span class="text-sm text-slate-500 font-normal">COP</span>
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Botón pagar GRANDE --}}
                @if($linkPago)
                    <a href="{{ $linkPago }}" target="_blank"
                       class="block w-full text-center py-5 rounded-2xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-extrabold text-lg shadow-xl transition-all hover:scale-[1.02]">
                        <i class="fa-solid fa-credit-card mr-2"></i>
                        Pagar ahora con Wompi
                    </a>
                    <p class="text-center text-[11px] text-slate-500">
                        Te abriremos el checkout seguro de Wompi en una pestaña nueva.
                        Después del pago, recarga esta página y tu acceso quedará activo.
                    </p>
                @else
                    <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        No pudimos generar el link de pago. Por favor contáctanos:
                        <a href="mailto:comercial@tecnobyte360.com" class="font-bold underline">comercial@tecnobyte360.com</a>
                    </div>
                @endif

                <div class="border-t border-slate-200 pt-4">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        💡 <strong>¿Por qué veo esta pantalla?</strong><br>
                        Tu suscripción mensual venció y entraste en período de mora. Una vez paguemos
                        la mensualidad, recuperarás acceso completo a la plataforma de forma automática.
                        Mientras tanto puedes cerrar sesión o pagar para reactivar.
                    </p>
                </div>

                <div class="flex items-center justify-center gap-3 text-[11px] text-slate-400 pt-2">
                    <span>¿Dudas?</span>
                    <a href="mailto:comercial@tecnobyte360.com" class="underline hover:text-slate-700">
                        comercial@tecnobyte360.com
                    </a>
                    <span>·</span>
                    <a href="https://wa.me/573164170900" target="_blank" class="underline hover:text-slate-700">
                        WhatsApp soporte
                    </a>
                </div>
            </div>
        </div>

        <div class="text-center mt-6 text-xs text-slate-400">
            Plataforma <strong class="text-slate-600">Kivox</strong> by TecnoByte360
        </div>
    </div>
</body>
</html>
