@php
    $platformCfg = null;
    try { $platformCfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}

    $brandName  = $tenantBranding?->nombre ?: ($platformCfg->nombre ?: 'Kivox');
    $brandLogo  = $tenantBranding?->logo_url ?: ($platformCfg->logo_url ?? null);
    $colorPrim  = $tenantBranding?->color_primario ?: ($platformCfg->color_primario ?? '#10b981');
    $colorSec   = $tenantBranding?->color_secundario ?: ($platformCfg->color_secundario ?? '#059669');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña · {{ $brandName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media (max-width: 768px) { input { font-size: 16px !important; } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex">

    <div class="hidden lg:flex lg:w-1/2 relative items-center justify-center p-12 overflow-hidden"
         style="background: linear-gradient(135deg, {{ $colorPrim }} 0%, {{ $colorSec }} 100%);">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 24px 24px;"></div>
        <div class="relative text-center text-white">
            @if($brandLogo)
                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-24 w-auto mx-auto mb-6 drop-shadow-2xl">
            @else
                <div class="h-24 w-24 mx-auto rounded-3xl bg-white/20 backdrop-blur flex items-center justify-center mb-6">
                    <span class="text-5xl font-extrabold text-white">{{ strtoupper(substr($brandName, 0, 1)) }}</span>
                </div>
            @endif
            <h1 class="text-4xl font-extrabold tracking-tight mb-2">{{ $brandName }}</h1>
            <p class="text-white/85 text-sm uppercase tracking-[0.3em] font-bold">conecta · comunica · transforma</p>
        </div>
    </div>

    <div class="flex-1 flex items-center justify-center p-6 lg:p-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl mb-4"
                     style="background: linear-gradient(135deg, {{ $colorPrim }}22, {{ $colorPrim }}44); color: {{ $colorPrim }};">
                    <i class="fa-solid fa-key text-2xl"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-slate-800">Nueva contraseña</h2>
                <p class="text-sm text-slate-500 mt-1">Define una contraseña segura para tu cuenta.</p>
            </div>

            @if($errors->any())
                <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-5 text-sm text-rose-700">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="/reset-password" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Correo</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" name="email" value="{{ $email }}" required readonly
                               class="w-full rounded-2xl border border-slate-200 bg-slate-50 pl-12 pr-4 py-3 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                        Nueva contraseña
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="password" required minlength="8" autofocus
                               placeholder="Mínimo 8 caracteres"
                               class="w-full rounded-2xl border border-slate-200 pl-12 pr-4 py-3 text-sm focus:outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                        Confirmar contraseña
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="password_confirmation" required minlength="8"
                               placeholder="Repite la contraseña"
                               class="w-full rounded-2xl border border-slate-200 pl-12 pr-4 py-3 text-sm focus:outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                    </div>
                </div>

                <button type="submit"
                        class="w-full rounded-2xl py-3 text-sm font-extrabold text-white transition shadow-lg hover:scale-[1.01]"
                        style="background: linear-gradient(135deg, {{ $colorPrim }}, {{ $colorSec }});">
                    <i class="fa-solid fa-check mr-2"></i>
                    Guardar nueva contraseña
                </button>
            </form>

            <p class="text-center text-xs text-slate-400 mt-8">
                © {{ date('Y') }} {{ $brandName }} · Todos los derechos reservados
            </p>
        </div>
    </div>
</body>
</html>
