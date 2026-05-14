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

    // Features mostradas en la columna izquierda — personalizables vía tenant si se quiere
    $features = $tenantBranding?->login_features ?? [
        ['icon' => 'fa-chart-line',   'label' => 'Reportes en tiempo real'],
        ['icon' => 'fa-bag-shopping', 'label' => 'Gestión de pedidos'],
        ['icon' => 'fa-user-shield',  'label' => 'Control de usuarios'],
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
            --brand-sec: {{ $colorSec }};
            --brand-rgb: {{ $rgbPrim }};
        }
        * { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }

        body {
            background:
                radial-gradient(circle at 0% 0%, rgba(var(--brand-rgb), 0.18) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(var(--brand-rgb), 0.12) 0%, transparent 50%),
                linear-gradient(135deg, #fff7ed 0%, #ffffff 50%, #fef3e2 100%);
            min-height: 100vh;
        }

        /* Patrón decorativo de puntos */
        .dots-pattern {
            background-image: radial-gradient(rgba(var(--brand-rgb), 0.4) 1.5px, transparent 1.5px);
            background-size: 14px 14px;
        }

        /* Círculo decorativo gigante */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            pointer-events: none;
        }

        /* Input con foco brand-aware */
        .brand-input {
            transition: all .15s ease;
        }
        .brand-input:focus {
            border-color: var(--brand-prim);
            box-shadow: 0 0 0 4px rgba(var(--brand-rgb), 0.15);
            outline: none;
        }

        /* Botón con shimmer al hover */
        .brand-btn {
            background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));
            position: relative;
            overflow: hidden;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .brand-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 30px rgba(var(--brand-rgb), 0.4);
        }
        .brand-btn:active { transform: translateY(0); }
        .brand-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,0.25) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform .6s ease;
        }
        .brand-btn:hover::after { transform: translateX(100%); }

        /* Feature pills con hover */
        .feature-pill {
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .feature-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(var(--brand-rgb), 0.2);
        }

        /* Animación entrada */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp .5s ease-out both; }
        .fade-up-1 { animation-delay: .1s; }
        .fade-up-2 { animation-delay: .2s; }
        .fade-up-3 { animation-delay: .3s; }
    </style>
</head>
<body class="relative overflow-hidden">

    {{-- Blobs decorativos --}}
    <div class="blob" style="background: var(--brand-prim); width: 500px; height: 500px; top: -200px; left: -200px;"></div>
    <div class="blob" style="background: var(--brand-sec); width: 400px; height: 400px; bottom: -150px; right: -150px;"></div>

    <div class="relative min-h-screen flex items-center justify-center p-4 lg:p-8">
        <div class="w-full max-w-6xl grid lg:grid-cols-2 gap-8 lg:gap-16 items-center">

            {{-- ───────── COLUMNA IZQUIERDA: Brand + Marketing ───────── --}}
            <div class="hidden lg:block relative fade-up">
                {{-- Patrón de puntos decorativo --}}
                <div class="dots-pattern absolute -top-4 -left-2 w-24 h-24 opacity-60"></div>
                <div class="dots-pattern absolute top-32 right-12 w-16 h-16 opacity-50"></div>

                {{-- Logo + Nombre marca --}}
                <div class="flex items-center gap-3 mb-12 relative">
                    @if($brandLogo)
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-lg overflow-hidden border border-slate-100">
                            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain">
                        </div>
                    @else
                        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-lg"
                             style="background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));">
                            <i class="fa-solid fa-utensils text-xl"></i>
                        </div>
                    @endif
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 leading-tight">{{ $brandName }}</h1>
                        <p class="text-sm text-slate-500">{{ $tagline }}</p>
                    </div>
                </div>

                {{-- Headline --}}
                <h2 class="text-4xl xl:text-5xl font-black text-slate-900 leading-tight mb-4 fade-up fade-up-1">
                    Gestiona tus pedidos<br>
                    <span style="background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec)); -webkit-background-clip: text; background-clip: text; color: transparent;">
                        de forma inteligente
                    </span>
                </h2>

                <p class="text-base text-slate-600 mb-10 max-w-md fade-up fade-up-2">
                    Todo lo que necesitas para administrar tu negocio en un solo lugar. Pedidos, clientes, despachos y reportes — sin complicaciones.
                </p>

                {{-- Feature pills --}}
                <div class="flex flex-wrap gap-3 fade-up fade-up-3">
                    @foreach($features as $f)
                        <div class="feature-pill flex items-center gap-3 bg-white/80 backdrop-blur-sm rounded-2xl px-4 py-3 shadow-sm border border-slate-100">
                            <div class="h-10 w-10 rounded-xl flex items-center justify-center text-white shadow-sm shrink-0"
                                 style="background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));">
                                <i class="fa-solid {{ $f['icon'] }}"></i>
                            </div>
                            <div class="text-xs font-semibold text-slate-700 leading-tight">{{ $f['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ───────── COLUMNA DERECHA: Login Card ───────── --}}
            <div class="fade-up fade-up-1">
                {{-- Logo mobile (cuando se oculta la columna izquierda) --}}
                <div class="lg:hidden text-center mb-6">
                    @if($brandLogo)
                        <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-lg mb-3 overflow-hidden border border-slate-100">
                            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain">
                        </div>
                    @else
                        <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl text-white shadow-lg mb-3"
                             style="background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));">
                            <i class="fa-solid fa-utensils text-2xl"></i>
                        </div>
                    @endif
                    <h1 class="text-2xl font-extrabold text-slate-900">{{ $brandName }}</h1>
                    <p class="text-sm text-slate-500">{{ $tagline }}</p>
                </div>

                <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10 border border-slate-100 relative">
                    {{-- Icono flotante encima del card --}}
                    <div class="absolute -top-7 left-1/2 -translate-x-1/2">
                        <div class="h-14 w-14 rounded-2xl flex items-center justify-center text-white shadow-xl"
                             style="background: linear-gradient(135deg, var(--brand-prim), var(--brand-sec));">
                            <i class="fa-solid fa-arrow-right-to-bracket text-xl"></i>
                        </div>
                    </div>

                    <div class="text-center mb-6 mt-4">
                        <h2 class="text-2xl font-extrabold text-slate-900">Iniciar sesión</h2>
                        <p class="text-sm text-slate-500 mt-1">Ingresa con tus credenciales para continuar</p>
                    </div>

                    @if($errors->any())
                        <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-4 text-sm text-rose-700 flex items-start gap-2">
                            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    @if(session('status'))
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 mb-4 text-sm text-emerald-700 flex items-start gap-2">
                            <i class="fa-solid fa-circle-check mt-0.5"></i>
                            <div>{{ session('status') }}</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wide">
                                <i class="fa-solid fa-envelope mr-1" style="color: var(--brand-prim);"></i>
                                Correo electrónico
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i class="fa-solid fa-envelope text-sm"></i>
                                </span>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                                       class="brand-input w-full rounded-xl border-2 border-slate-200 pl-11 pr-4 py-3 text-sm bg-slate-50/50"
                                       placeholder="{{ 'tucorreo@' . $emailDomain }}">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">
                                    <i class="fa-solid fa-lock mr-1" style="color: var(--brand-prim);"></i>
                                    Contraseña
                                </label>
                                @if(\Illuminate\Support\Facades\Route::has('password.request'))
                                    <a href="{{ route('password.request') }}"
                                       class="text-xs font-semibold hover:underline"
                                       style="color: var(--brand-prim);">¿Olvidaste tu contraseña?</a>
                                @endif
                            </div>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    <i class="fa-solid fa-lock text-sm"></i>
                                </span>
                                <input type="password" name="password" id="password" required
                                       class="brand-input w-full rounded-xl border-2 border-slate-200 pl-11 pr-12 py-3 text-sm bg-slate-50/50"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePwd()"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 h-8 w-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition flex items-center justify-center"
                                        aria-label="Mostrar contraseña">
                                    <i id="pwdIcon" class="fa-regular fa-eye-slash text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-slate-300 focus:ring-2"
                                   style="accent-color: var(--brand-prim);">
                            <span class="text-sm text-slate-600">Recordarme en este equipo</span>
                        </label>

                        <button type="submit"
                                class="brand-btn w-full rounded-xl text-white font-bold py-3.5 shadow-lg text-sm">
                            <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i>
                            Entrar
                        </button>
                    </form>

                    {{-- Footer del card --}}
                    <p class="text-center text-xs text-slate-500 mt-6">
                        ¿Necesitas ayuda?
                        <a href="mailto:soporte@tecnobyte360.com"
                           class="font-bold hover:underline"
                           style="color: var(--brand-prim);">Contacta a soporte técnico</a>.
                    </p>
                </div>

                <p class="text-center text-xs text-slate-400 mt-6">
                    © {{ date('Y') }} {{ $brandName }} · Todos los derechos reservados
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
