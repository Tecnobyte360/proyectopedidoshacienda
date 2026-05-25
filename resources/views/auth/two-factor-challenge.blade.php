@php
    $cfg = null;
    try { $cfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}
    $brandName = $cfg->nombre ?: 'Kivox';
    $colorPrim = $cfg->color_primario ?: '#10b981';
    $colorSec  = $cfg->color_secundario ?: '#059669';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación 2FA · {{ $brandName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 bg-white">

    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">

            <div class="text-center px-8 pt-8 pb-6">
                <div class="inline-flex h-20 w-20 items-center justify-center rounded-3xl mb-4"
                     style="background: linear-gradient(135deg, {{ $colorPrim }}22, {{ $colorPrim }}44); color: {{ $colorPrim }};">
                    <i class="fa-solid fa-shield-halved text-3xl"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-800">Verificación de 2 pasos</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Ingresa el código de 6 dígitos de tu app autenticadora
                    <br><span class="text-xs">(Google Authenticator, Authy, 1Password...)</span>
                </p>
            </div>

            <div class="px-8 pb-8">
                @if($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-4 text-sm text-rose-700">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-5">
                    @csrf
                    <input type="text" name="code" required autofocus inputmode="numeric"
                           placeholder="000000" maxlength="10"
                           x-data x-init="$el.focus()"
                           autocomplete="one-time-code"
                           class="w-full text-center text-3xl font-extrabold tracking-[0.5em] py-4 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-current focus:ring-4"
                           style="color: {{ $colorPrim }};">

                    <button type="submit"
                            class="w-full rounded-2xl py-3.5 text-sm font-extrabold text-white transition shadow-lg hover:scale-[1.01]"
                            style="background: linear-gradient(135deg, {{ $colorPrim }}, {{ $colorSec }});">
                        <i class="fa-solid fa-check mr-2"></i>
                        Verificar
                    </button>
                </form>

                <div class="mt-6 text-center space-y-2">
                    <p class="text-[11px] text-slate-500">
                        ¿Perdiste tu dispositivo? Usa uno de tus <strong>códigos de respaldo</strong>
                        (formato <code class="bg-slate-100 px-1.5 py-0.5 rounded text-[10px]">XXXX-YYYY</code>) en el mismo campo.
                    </p>
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
