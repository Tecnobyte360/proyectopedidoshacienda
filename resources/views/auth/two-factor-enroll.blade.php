@php
    $cfg = null;
    try { $cfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}
    $brandName = $cfg->nombre ?: 'Kivox';
    $colorPrim = $cfg->color_primario ?: '#10b981';
    $colorSec  = $cfg->color_secundario ?: '#059669';
    $logoUrl   = $cfg->logo_url ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar 2FA · {{ $brandName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-white">

    <div class="w-full max-w-xl">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">

            <div class="text-center px-8 pt-8 pb-2">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="mx-auto h-16 w-auto mb-3 object-contain">
                @endif
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-[11px] font-extrabold uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-lock"></i> Acción requerida
                </div>
                <h1 class="text-2xl font-extrabold text-slate-800">Activa tu autenticación en 2 pasos</h1>
                <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">
                    Tu administrador requiere que protejas tu cuenta con un segundo factor.
                    Escanea el QR con tu app autenticadora y verifica el código para continuar.
                </p>
            </div>

            <div class="px-8 pb-8 pt-4">
                @if($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-4 text-sm text-rose-700">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                {{-- PASO 1: Escanear QR --}}
                <div class="bg-slate-50 rounded-2xl p-5 mb-4">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full text-white font-bold text-xs flex-shrink-0" style="background: {{ $colorPrim }};">1</div>
                        <div>
                            <div class="text-sm font-bold text-slate-800">Escanea el código QR</div>
                            <div class="text-xs text-slate-500">Usa Google Authenticator, Authy, 1Password o similar</div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-center gap-4">
                        <div class="bg-white border-2 border-slate-200 rounded-xl p-3 flex-shrink-0">
                            <img src="{{ $qrUrl }}" alt="Código QR" class="w-44 h-44">
                        </div>
                        <div class="flex-1 text-center md:text-left">
                            <div class="text-[11px] font-bold uppercase text-slate-500 mb-1">¿No puedes escanear?</div>
                            <div class="text-xs text-slate-600 mb-2">Pega esta clave manualmente en tu app:</div>
                            <div class="bg-white border-2 border-dashed border-slate-300 rounded-lg p-3 font-mono text-sm font-bold text-slate-800 break-all select-all">
                                {{ chunk_split($secret, 4, ' ') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PASO 2: Verificar código --}}
                <div class="bg-slate-50 rounded-2xl p-5 mb-4">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full text-white font-bold text-xs flex-shrink-0" style="background: {{ $colorPrim }};">2</div>
                        <div>
                            <div class="text-sm font-bold text-slate-800">Ingresa el código de 6 dígitos</div>
                            <div class="text-xs text-slate-500">El código cambia cada 30 segundos</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('two-factor.enroll.confirm') }}" class="space-y-3">
                        @csrf
                        <input type="text" name="code" required autofocus inputmode="numeric"
                               placeholder="000000" maxlength="6" pattern="\d{6}"
                               autocomplete="one-time-code"
                               class="w-full text-center text-3xl font-extrabold tracking-[0.5em] py-4 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-current focus:ring-4"
                               style="color: {{ $colorPrim }};">

                        <button type="submit"
                                class="w-full rounded-2xl py-3.5 text-sm font-extrabold text-white transition shadow-lg hover:scale-[1.01]"
                                style="background: linear-gradient(135deg, {{ $colorPrim }}, {{ $colorSec }});">
                            <i class="fa-solid fa-shield-halved mr-2"></i>
                            Activar y continuar
                        </button>
                    </form>
                </div>

                <div class="text-center">
                    <form method="POST" action="/logout" class="inline">
                        @csrf
                        <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 font-semibold">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i> Cancelar y volver al login
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            © {{ date('Y') }} {{ $brandName }}
        </p>
    </div>
</body>
</html>
