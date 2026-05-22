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
<body class="h-screen bg-white overflow-hidden">

    <div class="h-screen grid lg:grid-cols-2 overflow-hidden">

        {{-- ═══════════════ MITAD IZQUIERDA: HERO IMAGE ═══════════════ --}}
        @php
            // Imagen hero personalizada (composición completa con logo + tagline + efectos)
            // Si existe, llena todo el panel sin overlays sintéticos.
            $heroImage = $tenantBranding?->login_hero_url
                ?? $platformCfg?->login_hero_url
                ?? null;
        @endphp

        @if($heroImage)
            {{-- ⭐ Modo HERO IMAGE: usa la imagen completa diseñada (logo + tagline + efectos) --}}
            <div class="relative hidden lg:block overflow-hidden">
                <img src="{{ $heroImage }}" alt="{{ $brandName }}"
                     class="absolute inset-0 w-full h-full object-cover">
            </div>
        @else
            {{-- Modo automático: gradient verde + logo + efectos generados --}}
            <div class="relative hidden lg:flex flex-col items-center justify-center overflow-hidden"
                 style="background: linear-gradient(135deg, {{ $colorPrim }} 0%, {{ $colorSec }} 50%, #065f46 100%);">

                {{-- Anillo circular VISIBLE alrededor del logo (borde + glow) --}}
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[46rem] h-[46rem] rounded-full border-2 border-white/15"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[40rem] h-[40rem] rounded-full"
                     style="background: radial-gradient(circle, transparent 42%, rgba(255,255,255,0.12) 49%, transparent 56%);"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[36rem] h-[36rem] rounded-full"
                     style="background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 65%);"></div>

                {{-- Arco de luz BRILLANTE en la esquina inferior izquierda --}}
                <div class="absolute -bottom-48 -left-48 w-[44rem] h-[44rem] rounded-full"
                     style="background: radial-gradient(circle at 65% 35%, rgba(187,247,208,0.55) 0%, rgba(134,239,172,0.3) 25%, rgba(74,222,128,0.15) 45%, transparent 70%);"></div>

                {{-- Curva de luz lateral inferior izquierda --}}
                <div class="absolute bottom-0 left-0 w-[20rem] h-[20rem]"
                     style="background: radial-gradient(ellipse at bottom left, rgba(220,252,231,0.4) 0%, transparent 60%);"></div>

                {{-- Dots decorativos esquina superior izquierda — patrón pequeño --}}
                <div class="absolute top-12 left-12">
                    <svg width="140" height="140" xmlns="http://www.w3.org/2000/svg">
                        @for($i = 0; $i < 7; $i++)
                            @for($j = 0; $j < 7; $j++)
                                @php
                                    // Opacidad: más fuerte arriba-izquierda, se difumina hacia abajo-derecha
                                    $opa = max(0, 0.7 - (($i + $j) * 0.07));
                                @endphp
                                @if($opa > 0.05)
                                    <circle cx="{{ 8 + $i * 18 }}" cy="{{ 8 + $j * 18 }}" r="1.8" fill="white" opacity="{{ $opa }}"/>
                                @endif
                            @endfor
                        @endfor
                    </svg>
                </div>

                {{-- Dots decorativos esquina inferior derecha — patrón en arco/cluster --}}
                <div class="absolute bottom-8 right-8">
                    <svg width="180" height="180" xmlns="http://www.w3.org/2000/svg">
                        @for($i = 0; $i < 9; $i++)
                            @for($j = 0; $j < 9; $j++)
                                @php
                                    // Opacidad: más fuerte abajo-derecha, se difumina hacia arriba-izquierda
                                    $opa = max(0, 0.65 - ((8 - $i + 8 - $j) * 0.05));
                                @endphp
                                @if($opa > 0.05)
                                    <circle cx="{{ 8 + $i * 18 }}" cy="{{ 8 + $j * 18 }}" r="1.8" fill="white" opacity="{{ $opa }}"/>
                                @endif
                            @endfor
                        @endfor
                    </svg>
                </div>

                {{-- Pequeño cluster de dots adicional centro-bajo --}}
                <div class="absolute bottom-32 left-1/3 opacity-30">
                    <svg width="80" height="80" xmlns="http://www.w3.org/2000/svg">
                        @for($i = 0; $i < 4; $i++)
                            @for($j = 0; $j < 4; $j++)
                                <circle cx="{{ 6 + $i * 20 }}" cy="{{ 6 + $j * 20 }}" r="1.5" fill="white" opacity="{{ 0.4 - ($i + $j) * 0.05 }}"/>
                            @endfor
                        @endfor
                    </svg>
                </div>

                {{-- LOGO + TAGLINE --}}
                <div class="relative z-10 flex flex-col items-center justify-center px-8 text-center h-full">
                    @if($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}"
                             class="max-h-[58vh] w-auto max-w-[24rem] object-contain"
                             style="filter: drop-shadow(0 25px 50px rgba(0,0,0,0.5)) drop-shadow(0 0 40px rgba(255,255,255,0.25));">
                    @else
                        <div class="relative">
                            <i class="fa-solid fa-utensils text-white text-9xl mb-6 drop-shadow-2xl"></i>
                            <h1 class="text-6xl font-extrabold text-white drop-shadow-lg tracking-tight">{{ $brandName }}</h1>
                        </div>
                    @endif

                    <p class="-mt-4 text-sm lg:text-base text-white font-bold tracking-[0.4em] uppercase drop-shadow-md">
                        Conecta · Comunica · Transforma
                    </p>
                </div>
            </div>
        @endif

        {{-- ═══════════════ MITAD DERECHA: LOGIN ═══════════════ --}}
        <div class="flex items-center justify-center px-6 py-8 bg-white overflow-y-auto">
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

                {{-- Divisor + link de recuperar contraseña --}}
                <div class="mt-6 flex items-center gap-3">
                    <span class="h-px flex-1 bg-slate-200"></span>
                    <i class="fa-regular fa-circle text-[10px] text-slate-300"></i>
                    <span class="h-px flex-1 bg-slate-200"></span>
                </div>

                <div class="mt-4 text-center">
                    <a href="#" onclick="alert('Contacta al administrador para restablecer tu contraseña.'); return false;"
                       class="inline-flex items-center gap-2 text-sm font-semibold transition hover:underline"
                       style="color: {{ $colorPrim }};">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <p class="text-center text-xs text-slate-400 mt-8 lg:hidden">
                    © {{ date('Y') }} {{ $brandName }} · Todos los derechos reservados
                </p>
            </div>
        </div>
    </div>

</body>
</html>
