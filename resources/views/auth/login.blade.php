@php
    $brandName = $tenantBranding?->nombre ?: 'TecnoByte360';
    $brandLogo = $tenantBranding?->logo_url;
    $colorPrim = $tenantBranding?->color_primario ?: '#d68643';
    $colorSec  = $tenantBranding?->color_secundario ?: '#a85f24';
    $tagline   = $tenantBranding?->tagline ?: 'Plataforma de gestión de pedidos';

    // Soporta hex sin "#"
    $hex = ltrim($colorPrim, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    [$r,$g,$b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    $rgbPrim = "{$r},{$g},{$b}";

    $hex2 = ltrim($colorSec, '#');
    if (strlen($hex2) === 3) $hex2 = $hex2[0].$hex2[0].$hex2[1].$hex2[1].$hex2[2].$hex2[2];
    [$r2,$g2,$b2] = [hexdec(substr($hex2,0,2)), hexdec(substr($hex2,2,2)), hexdec(substr($hex2,4,2))];
    $rgbSec = "{$r2},{$g2},{$b2}";

    $emailDomain = $tenantBranding?->slug ? $tenantBranding->slug . '.com' : 'empresa.com';

    $platformCfg = null;
    try { $platformCfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}
    $faviconUrl = $tenantBranding?->favicon_url ?: ($platformCfg->favicon_url ?? null);
    if (!$faviconUrl) {
        $inicial = mb_strtoupper(mb_substr($brandName, 0, 1));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
             . '<rect width="64" height="64" rx="14" fill="' . htmlspecialchars($colorPrim) . '"/>'
             . '<text x="50%" y="50%" font-family="system-ui,sans-serif" font-size="34" font-weight="800" fill="white" '
             . 'text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inicial) . '</text></svg>';
        $faviconUrl = 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    $features = [
        ['icon' => 'fa-chart-line',    'label' => 'Reportes',     'sublabel' => 'en tiempo real'],
        ['icon' => 'fa-bag-shopping',  'label' => 'Pedidos',      'sublabel' => 'organizados'],
        ['icon' => 'fa-truck-fast',    'label' => 'Despachos',    'sublabel' => 'optimizados'],
        ['icon' => 'fa-user-shield',   'label' => 'Usuarios',     'sublabel' => 'y permisos'],
    ];
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --brand-prim: {{ $colorPrim }};
            --brand-sec:  {{ $colorSec }};
            --brand-rgb:  {{ $rgbPrim }};
            --brand-rgb2: {{ $rgbSec }};
        }
        * { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        html, body { height: 100%; }
        body {
            background: #f8fafc;
            overflow-x: hidden;
        }

        /* ═══════ COLUMNA IZQUIERDA — Brand Side ═══════ */
        .brand-side {
            background:
                radial-gradient(circle at 15% 20%, rgba(var(--brand-rgb), 0.35) 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(var(--brand-rgb2), 0.30) 0%, transparent 50%),
                linear-gradient(135deg, rgba(var(--brand-rgb), 0.15) 0%, rgba(var(--brand-rgb2), 0.10) 100%);
            position: relative;
            overflow: hidden;
        }

        /* Arco curvo decorativo grande */
        .brand-arc {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .brand-arc-1 {
            top: -25%;
            right: -30%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle at 30% 30%, rgba(var(--brand-rgb), 0.25) 0%, rgba(var(--brand-rgb), 0.05) 50%, transparent 80%);
        }
        .brand-arc-2 {
            bottom: -20%;
            left: -25%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle at 70% 70%, rgba(var(--brand-rgb2), 0.30) 0%, rgba(var(--brand-rgb2), 0.05) 50%, transparent 80%);
        }
        .brand-arc-3 {
            top: 30%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(255,255,255,0.6) 0%, transparent 70%);
        }

        /* Patrón de puntos */
        .dots {
            background-image: radial-gradient(rgba(var(--brand-rgb), 0.55) 1.8px, transparent 1.8px);
            background-size: 18px 18px;
        }

        /* Líneas decorativas */
        .grid-lines::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(var(--brand-rgb), 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(var(--brand-rgb), 0.05) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(circle at center, black 0%, transparent 70%);
        }

        /* Tarjetas de features con glassmorphism */
        .feature-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s ease, background .25s ease;
        }
        .feature-card:hover {
            transform: translateY(-4px) scale(1.02);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 20px 40px -10px rgba(var(--brand-rgb), 0.35);
        }
        .feature-card-icon {
            background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));
            box-shadow: 0 8px 20px -4px rgba(var(--brand-rgb), 0.5);
        }

        /* Stat strip */
        .stat-strip {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.8);
        }

        /* ═══════ COLUMNA DERECHA — Login Card ═══════ */
        .login-side {
            background:
                radial-gradient(circle at 50% 0%, rgba(var(--brand-rgb), 0.06) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
        }

        .login-card {
            box-shadow:
                0 0 0 1px rgba(0,0,0,0.04),
                0 30px 60px -15px rgba(var(--brand-rgb), 0.15),
                0 10px 25px -5px rgba(0,0,0,0.08);
        }

        /* Input con focus brand-aware */
        .brand-input {
            transition: all .15s ease;
            background: #ffffff;
        }
        .brand-input:focus {
            border-color: var(--brand-prim);
            box-shadow: 0 0 0 4px rgba(var(--brand-rgb), 0.12);
            outline: none;
            background: #ffffff;
        }
        .brand-input:hover:not(:focus) {
            border-color: rgba(var(--brand-rgb), 0.4);
        }

        /* Botón principal con shimmer + lift */
        .brand-btn {
            background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));
            position: relative;
            overflow: hidden;
            transition: transform .15s ease, box-shadow .15s ease;
            box-shadow: 0 10px 25px -5px rgba(var(--brand-rgb), 0.45);
        }
        .brand-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(var(--brand-rgb), 0.55);
        }
        .brand-btn:active { transform: translateY(0); }
        .brand-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform .6s ease;
        }
        .brand-btn:hover::after { transform: translateX(100%); }

        /* Animaciones de entrada */
        @keyframes fadeUp { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity:0 } to { opacity:1 } }
        @keyframes float { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }
        @keyframes pulse-glow {
            0%,100% { box-shadow: 0 8px 20px -4px rgba(var(--brand-rgb), 0.5); }
            50%     { box-shadow: 0 8px 30px -2px rgba(var(--brand-rgb), 0.8); }
        }
        .fade-up { animation: fadeUp .6s cubic-bezier(.4,0,.2,1) both; }
        .fade-in { animation: fadeIn .5s ease-out both; }
        .float-slow { animation: float 4s ease-in-out infinite; }
        .pulse-glow { animation: pulse-glow 3s ease-in-out infinite; }
        .d-1 { animation-delay: .1s }
        .d-2 { animation-delay: .2s }
        .d-3 { animation-delay: .3s }
        .d-4 { animation-delay: .4s }
        .d-5 { animation-delay: .5s }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Badge "online" */
        .live-dot {
            position: relative;
        }
        .live-dot::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: rgb(34, 197, 94);
            opacity: 0.3;
            animation: ping 2s cubic-bezier(0,0,.2,1) infinite;
        }
        @keyframes ping {
            75%, 100% { transform: scale(2); opacity: 0; }
        }
    </style>
</head>
<body>

    <div class="min-h-screen grid lg:grid-cols-5">

        {{-- ═══════════════════════════════════════════════════════════════
             COLUMNA IZQUIERDA — BRAND SIDE (3/5 en desktop)
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="brand-side hidden lg:flex lg:col-span-3 relative grid-lines">

            {{-- Arcos curvos de fondo --}}
            <div class="brand-arc brand-arc-1"></div>
            <div class="brand-arc brand-arc-2"></div>
            <div class="brand-arc brand-arc-3 float-slow"></div>

            {{-- Patrones de puntos decorativos --}}
            <div class="dots absolute top-16 right-20 w-32 h-32 opacity-70"></div>
            <div class="dots absolute bottom-32 left-16 w-24 h-24 opacity-60"></div>
            <div class="dots absolute top-1/2 right-1/3 w-16 h-16 opacity-50"></div>

            {{-- Contenido --}}
            <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 w-full">

                {{-- Header: Logo + Marca --}}
                <div class="fade-up">
                    <div class="flex items-center gap-4">
                        @if($brandLogo)
                            <div class="h-16 w-16 rounded-2xl bg-white shadow-xl overflow-hidden border-2 border-white pulse-glow">
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain">
                            </div>
                        @else
                            <div class="h-16 w-16 rounded-2xl flex items-center justify-center text-white shadow-xl pulse-glow"
                                 style="background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));">
                                <i class="fa-solid fa-utensils text-2xl"></i>
                            </div>
                        @endif
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 leading-tight">{{ $brandName }}</h1>
                            <p class="text-sm text-slate-600 font-medium">{{ $tagline }}</p>
                        </div>
                    </div>
                </div>

                {{-- Hero central --}}
                <div class="my-auto py-12">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/70 backdrop-blur-sm border border-white/80 shadow-sm fade-up d-1 mb-6">
                        <span class="live-dot inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-xs font-bold text-slate-700">Sistema operativo · 24/7</span>
                    </div>

                    <h2 class="text-5xl xl:text-6xl font-black text-slate-900 leading-[1.05] mb-6 fade-up d-2 tracking-tight">
                        Gestiona tus<br>
                        pedidos<br>
                        <span class="gradient-text">de forma inteligente</span>
                    </h2>

                    <p class="text-lg text-slate-600 max-w-lg leading-relaxed fade-up d-3">
                        Todo lo que necesitas para administrar tu negocio en un solo lugar.
                        <strong class="text-slate-800">Pedidos, clientes, despachos y reportes</strong> — sin complicaciones.
                    </p>

                    {{-- Stat strip --}}
                    <div class="stat-strip mt-8 rounded-2xl p-1.5 flex gap-1 fade-up d-3 max-w-lg">
                        <div class="flex-1 px-4 py-3 text-center">
                            <div class="text-2xl font-black gradient-text">99.9%</div>
                            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-500">Uptime</div>
                        </div>
                        <div class="w-px bg-slate-200/80"></div>
                        <div class="flex-1 px-4 py-3 text-center">
                            <div class="text-2xl font-black gradient-text">&lt;1s</div>
                            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-500">Respuesta</div>
                        </div>
                        <div class="w-px bg-slate-200/80"></div>
                        <div class="flex-1 px-4 py-3 text-center">
                            <div class="text-2xl font-black gradient-text">24/7</div>
                            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-500">Soporte</div>
                        </div>
                    </div>
                </div>

                {{-- Feature cards en grid 2x2 --}}
                <div class="grid grid-cols-2 gap-3 max-w-lg fade-up d-4">
                    @foreach($features as $f)
                        <div class="feature-card rounded-2xl p-4 flex items-center gap-3">
                            <div class="feature-card-icon h-11 w-11 rounded-xl flex items-center justify-center text-white shrink-0">
                                <i class="fa-solid {{ $f['icon'] }}"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-900 leading-tight">{{ $f['label'] }}</div>
                                <div class="text-xs text-slate-500 leading-tight">{{ $f['sublabel'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             COLUMNA DERECHA — LOGIN SIDE (2/5 en desktop)
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="login-side lg:col-span-2 flex items-center justify-center p-6 sm:p-10 lg:p-12 relative">

            {{-- Decoración esquina --}}
            <div class="absolute top-0 right-0 w-64 h-64 rounded-full opacity-30 pointer-events-none"
                 style="background: radial-gradient(circle, rgba(var(--brand-rgb), 0.4), transparent 70%); transform: translate(30%, -30%);"></div>

            <div class="w-full max-w-md relative">

                {{-- Mobile brand --}}
                <div class="lg:hidden text-center mb-8 fade-up">
                    @if($brandLogo)
                        <div class="inline-flex h-20 w-20 items-center justify-center rounded-2xl bg-white shadow-xl mb-3 overflow-hidden border-2 border-slate-100">
                            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain">
                        </div>
                    @else
                        <div class="inline-flex h-20 w-20 items-center justify-center rounded-2xl text-white shadow-xl mb-3"
                             style="background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));">
                            <i class="fa-solid fa-utensils text-3xl"></i>
                        </div>
                    @endif
                    <h1 class="text-2xl font-black text-slate-900">{{ $brandName }}</h1>
                    <p class="text-sm text-slate-500">{{ $tagline }}</p>
                </div>

                <div class="login-card bg-white rounded-3xl p-8 sm:p-10 border border-slate-100/80 fade-up d-1 relative">

                    {{-- Header card --}}
                    <div class="mb-8">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 mb-4">
                            <i class="fa-solid fa-shield-halved text-xs" style="color: var(--brand-prim)"></i>
                            <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Acceso seguro</span>
                        </div>
                        <h2 class="text-3xl font-black text-slate-900 mb-2">Bienvenido</h2>
                        <p class="text-sm text-slate-500">Ingresa con tus credenciales para continuar</p>
                    </div>

                    @if($errors->any())
                        <div class="rounded-xl bg-rose-50 border border-rose-200 p-3.5 mb-5 text-sm text-rose-700 flex items-start gap-2 fade-in">
                            <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    @if(session('status'))
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3.5 mb-5 text-sm text-emerald-700 flex items-start gap-2 fade-in">
                            <i class="fa-solid fa-circle-check mt-0.5 shrink-0"></i>
                            <div>{{ session('status') }}</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">
                                Correo electrónico
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i class="fa-solid fa-envelope text-sm"></i>
                                </span>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                                       class="brand-input w-full rounded-xl border-2 border-slate-200 pl-11 pr-4 py-3.5 text-sm font-medium text-slate-900"
                                       placeholder="{{ 'tucorreo@' . $emailDomain }}">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="block text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    Contraseña
                                </label>
                                @if(\Illuminate\Support\Facades\Route::has('password.request'))
                                    <a href="{{ route('password.request') }}"
                                       class="text-xs font-bold hover:underline"
                                       style="color: var(--brand-prim);">¿Olvidaste tu contraseña?</a>
                                @endif
                            </div>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i class="fa-solid fa-lock text-sm"></i>
                                </span>
                                <input type="password" name="password" id="password" required
                                       class="brand-input w-full rounded-xl border-2 border-slate-200 pl-11 pr-12 py-3.5 text-sm font-medium text-slate-900"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePwd()"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 h-9 w-9 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition flex items-center justify-center"
                                        aria-label="Mostrar contraseña">
                                    <i id="pwdIcon" class="fa-regular fa-eye-slash text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-2 border-slate-300 focus:ring-2 focus:ring-offset-0"
                                   style="accent-color: var(--brand-prim);">
                            <span class="text-sm text-slate-700 group-hover:text-slate-900 transition">Recordarme en este equipo</span>
                        </label>

                        <button type="submit"
                                class="brand-btn w-full rounded-xl text-white font-bold py-4 text-sm tracking-wide">
                            Entrar
                            <i class="fa-solid fa-arrow-right ml-1"></i>
                        </button>
                    </form>

                    {{-- Divisor --}}
                    <div class="flex items-center gap-3 my-6">
                        <div class="flex-1 h-px bg-slate-200"></div>
                        <span class="text-[10px] uppercase tracking-widest font-bold text-slate-400">o</span>
                        <div class="flex-1 h-px bg-slate-200"></div>
                    </div>

                    {{-- Soporte --}}
                    <div class="flex items-center justify-center gap-2 text-sm text-slate-500">
                        <i class="fa-solid fa-headset" style="color: var(--brand-prim)"></i>
                        <span>¿Problemas para entrar?</span>
                        <a href="mailto:soporte@tecnobyte360.com"
                           class="font-bold hover:underline"
                           style="color: var(--brand-prim);">Contactar soporte</a>
                    </div>
                </div>

                <p class="text-center text-xs text-slate-400 mt-6 fade-up d-2">
                    © {{ date('Y') }} <span class="font-semibold text-slate-500">{{ $brandName }}</span> · Todos los derechos reservados
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePwd() {
            const i = document.getElementById('password');
            const icon = document.getElementById('pwdIcon');
            if (i.type === 'password') { i.type = 'text'; icon.className = 'fa-regular fa-eye text-sm'; }
            else { i.type = 'password'; icon.className = 'fa-regular fa-eye-slash text-sm'; }
        }
    </script>
</body>
</html>
