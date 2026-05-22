@php
    // Cargar config global de la plataforma (singleton — usado cuando NO hay tenant subdomain)
    $platformCfg = null;
    try { $platformCfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}

    // Si estamos en un subdominio de tenant → mostrar branding del tenant
    // Si no → mostrar branding de la plataforma (Kivox)
    $brandName  = $tenantBranding?->nombre ?: ($platformCfg->nombre ?: 'Kivox');
    $brandLogo  = $tenantBranding?->logo_url ?: ($platformCfg->logo_url ?? null);
    $colorPrim  = $tenantBranding?->color_primario ?: ($platformCfg->color_primario ?? '#10b981');
    $colorSec   = $tenantBranding?->color_secundario ?: ($platformCfg->color_secundario ?? '#059669');
    $subtitulo  = $tenantBranding ? 'Plataforma de gestión de pedidos' : ($platformCfg->subtitulo ?? 'Plataforma SaaS');
    $emailDomain = $tenantBranding?->slug ? $tenantBranding->slug . '.com' : 'empresa.com';

    $faviconUrl = $tenantBranding?->favicon_url ?: ($platformCfg->favicon_url ?? null);
    if (!$faviconUrl) {
        $inicial = mb_strtoupper(mb_substr($brandName, 0, 1));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
             . '<rect width="64" height="64" rx="14" fill="' . htmlspecialchars($colorPrim) . '"/>'
             . '<text x="50%" y="50%" font-family="system-ui,sans-serif" font-size="34" font-weight="800" fill="white" '
             . 'text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inicial) . '</text></svg>';
        $faviconUrl = 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | {{ $brandName }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-white">

    <div class="min-h-screen grid lg:grid-cols-2">

        {{-- ═══════════════ MITAD IZQUIERDA: SOLO LOGO ═══════════════ --}}
        <div class="relative hidden lg:flex flex-col items-center justify-center overflow-hidden"
             style="background: linear-gradient(135deg, {{ $colorPrim }} 0%, {{ $colorSec }} 100%);">

            {{-- Orbes decorativos suaves (sin patrón de puntos) --}}
            <div class="absolute -top-40 -left-40 w-[36rem] h-[36rem] bg-white/15 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -right-40 w-[40rem] h-[40rem] bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute top-1/4 right-1/4 w-72 h-72 bg-white/8 rounded-full blur-2xl"></div>

            {{-- Logo grande centrado SIN fondo --}}
            <div class="relative z-10 flex items-center justify-center">
                @if($brandLogo)
                    <div class="relative">
                        {{-- Glow blanco difuso detrás del logo --}}
                        <div class="absolute inset-0 bg-white/30 rounded-full blur-3xl scale-110"></div>
                        {{-- Logo sin card --}}
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}"
                             class="relative h-80 w-auto max-w-[22rem] object-contain"
                             style="filter: drop-shadow(0 18px 28px rgba(0,0,0,0.28));">
                    </div>
                @else
                    <div class="relative text-center">
                        <div class="absolute inset-0 bg-white/30 rounded-full blur-3xl scale-110"></div>
                        <div class="relative">
                            <i class="fa-solid fa-utensils text-white text-9xl mb-6 drop-shadow-2xl"></i>
                            <h1 class="text-5xl font-extrabold text-white drop-shadow-lg tracking-tight">{{ $brandName }}</h1>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Footer mínimo --}}
            <p class="absolute bottom-8 text-xs text-white/70 font-medium tracking-wide">
                © {{ date('Y') }} {{ $brandName }}
            </p>
        </div>

        {{-- ═══════════════ MITAD DERECHA: LOGIN ═══════════════ --}}
        <div class="flex items-center justify-center px-6 py-12 bg-white">
            <div class="w-full max-w-md">

                {{-- Logo (visible en todos los tamaños) --}}
                <div class="text-center mb-8">
                    @if($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}"
                             class="h-20 w-auto mx-auto mb-4">
                    @else
                        <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl text-white shadow-md mb-4"
                             style="background: linear-gradient(135deg, {{ $colorPrim }}, {{ $colorSec }});">
                            <i class="fa-solid fa-utensils text-2xl"></i>
                        </div>
                    @endif
                    <h2 class="text-2xl font-extrabold text-slate-800">Iniciar sesión</h2>
                    <p class="text-sm text-slate-500 mt-1">Ingresa con tus credenciales para continuar.</p>
                </div>

                @if($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-5 text-sm text-rose-700">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="/login" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                            Correo electrónico
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                                   style="--tw-ring-color: {{ $colorPrim }}33;"
                                   class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm focus:ring-2 focus:outline-none transition"
                                   onfocus="this.style.borderColor='{{ $colorPrim }}';"
                                   onblur="this.style.borderColor='';"
                                   placeholder="{{ 'tucorreo@' . $emailDomain }}">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                            Contraseña
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="password" name="password" required id="pwdInput"
                                   class="w-full rounded-xl border border-slate-200 pl-11 pr-12 py-3 text-sm focus:ring-2 focus:outline-none transition"
                                   style="--tw-ring-color: {{ $colorPrim }}33;"
                                   onfocus="this.style.borderColor='{{ $colorPrim }}';"
                                   onblur="this.style.borderColor='';"
                                   placeholder="••••••••">
                            <button type="button" onclick="const i=document.getElementById('pwdInput');i.type=i.type==='password'?'text':'password';this.querySelector('i').className='fa-solid '+(i.type==='password'?'fa-eye':'fa-eye-slash')+' text-sm';"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-2 rounded-lg hover:bg-slate-50">
                                <i class="fa-solid fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                                   class="rounded border-slate-300"
                                   style="color: {{ $colorPrim }};">
                            <span class="text-sm text-slate-600">Recordarme</span>
                        </label>
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl text-white font-bold py-3.5 transition shadow-lg hover:shadow-xl active:scale-[0.99]"
                            style="background: linear-gradient(135deg, {{ $colorPrim }}, {{ $colorSec }});">
                        <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i>
                        Iniciar sesión
                    </button>
                </form>

                <p class="text-center text-xs text-slate-400 mt-8 lg:hidden">
                    © {{ date('Y') }} {{ $brandName }} · Todos los derechos reservados
                </p>
            </div>
        </div>
    </div>

</body>
</html>
