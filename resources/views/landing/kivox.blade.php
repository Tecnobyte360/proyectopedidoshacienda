@php
    $cfg = null;
    try { $cfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}
    $brand      = $cfg->nombre ?: 'Kivox';
    $primario   = $cfg->color_primario ?: '#10b981';
    $secundario = $cfg->color_secundario ?: '#059669';
    $logoUrl    = $cfg->logo_url ?? null;
    $faviconUrl = $cfg->favicon_url ?? $logoUrl;
    $host       = request()->getHost();

    // Frases del manifiesto (para animarlas una a una)
    $manifiesto = [
        'Imagina un vendedor que nunca duerme.',
        'Que conoce a tus clientes por su nombre.',
        'Que toma pedidos a las tres de la mañana.',
        'Que cobra. Que despacha. Que aprende.',
        'Que vende mientras tú vives.',
    ];
    $features = [
        'Inteligencia Artificial',
        'Integrado con Meta y WhatsApp Business',
        'Pedidos automáticos · Cobros en segundos',
        'Domiciliarios en tiempo real · Reportes claros',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $brand }} · Algo grande está por venir</title>
    <meta name="robots" content="noindex,nofollow">

    @if($faviconUrl)
        <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @endif

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    {{-- ✨ Sora (similar a KIVOX) + Instrument Serif (cursivas) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand: {{ $primario }};
            --brand-2: {{ $secundario }};
        }
        * { -webkit-font-smoothing: antialiased; }
        html, body {
            font-family: 'Sora', system-ui, sans-serif;
            letter-spacing: -0.02em;
            background: #050507;
            color: white;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
        }
        .display { font-family: 'Sora', sans-serif; font-weight: 800; letter-spacing: -0.04em; line-height: 0.95; }
        .serif   { font-family: 'Instrument Serif', serif; font-style: italic; letter-spacing: -0.02em; font-weight: 400; }
        .gradient-text {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }

        /* ── Mesh gradient animado ── */
        .mesh-bg {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(at 25% 30%, {{ $primario }}45 0px, transparent 50%),
                radial-gradient(at 75% 70%, {{ $secundario }}40 0px, transparent 50%),
                radial-gradient(at 50% 50%, {{ $primario }}1A 0px, transparent 40%);
            animation: mesh 22s ease-in-out infinite;
        }
        @keyframes mesh {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50%      { transform: scale(1.15) rotate(2deg); }
        }

        /* ── Grain ── */
        .grain::after {
            content: ''; position: fixed; inset: 0; z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
            opacity: 0.05; pointer-events: none; mix-blend-mode: overlay;
        }

        .grid-pattern {
            position: fixed; inset: 0; z-index: 1;
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 70px 70px;
            mask-image: radial-gradient(ellipse at center, black 25%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 25%, transparent 70%);
            pointer-events: none;
        }

        .particle {
            position: fixed; border-radius: 50%; z-index: 0;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            opacity: .10; pointer-events: none; filter: blur(50px);
            animation: float 25s infinite ease-in-out;
        }
        @keyframes float {
            0%, 100% { transform: translate(0,0) scale(1); }
            33%      { transform: translate(80px,-60px) scale(1.15); }
            66%      { transform: translate(-50px,40px) scale(.9); }
        }

        /* ── Fade-in sequence ── */
        @keyframes fade-up { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .fade-1 { opacity: 0; animation: fade-up 1s .15s forwards; }
        .fade-2 { opacity: 0; animation: fade-up 1s .45s forwards; }
        .fade-3 { opacity: 0; animation: fade-up 1s .75s forwards; }
        .fade-4 { opacity: 0; animation: fade-up 1s 1.05s forwards; }
        .fade-5 { opacity: 0; animation: fade-up 1s 1.35s forwards; }
        .fade-6 { opacity: 0; animation: fade-up 1s 1.65s forwards; }
        .fade-7 { opacity: 0; animation: fade-up 1s 2s forwards; }
        .fade-8 { opacity: 0; animation: fade-up 1s 2.35s forwards; }

        /* ── Cascade text reveal (cada línea aparece secuencialmente) ── */
        .cascade { opacity: 0; transform: translateY(20px); animation: fade-up .8s forwards; }

        /* ── Pulse rings ── */
        @keyframes pulse-ring {
            0%   { transform: scale(.85); opacity: .85; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .pulse-ring::before, .pulse-ring::after {
            content: ''; position: absolute; inset: 0; border-radius: 50%;
            border: 2px solid {{ $primario }};
            animation: pulse-ring 2.5s cubic-bezier(.4,0,.2,1) infinite;
        }
        .pulse-ring::after { animation-delay: 1.25s; }

        @keyframes levitate { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .levitate { animation: levitate 6s ease-in-out infinite; }

        @keyframes glow-pulse {
            0%, 100% { opacity: .4; transform: scale(1); }
            50%      { opacity: .7; transform: scale(1.1); }
        }
        .glow-pulse { animation: glow-pulse 4s ease-in-out infinite; }

        @keyframes shimmer-text {
            0%   { background-position: -200% 0; }
            100% { background-position:  200% 0; }
        }
        .shimmer-text {
            background: linear-gradient(90deg, white 0%, white 35%, {{ $primario }} 50%, white 65%, white 100%);
            background-size: 200% 100%;
            -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: shimmer-text 4s linear infinite;
        }

        .bar {
            display: inline-block; width: 3px; margin: 0 2px;
            background: linear-gradient(180deg, var(--brand), var(--brand-2));
            border-radius: 2px;
        }
        .playing .bar { animation: bounce 1s ease-in-out infinite; }
        .bar:nth-child(1){animation-delay:-.0s}.bar:nth-child(2){animation-delay:-.2s}
        .bar:nth-child(3){animation-delay:-.4s}.bar:nth-child(4){animation-delay:-.6s}
        .bar:nth-child(5){animation-delay:-.8s}.bar:nth-child(6){animation-delay:-.3s}
        .bar:nth-child(7){animation-delay:-.5s}.bar:nth-child(8){animation-delay:-.7s}
        .bar:nth-child(9){animation-delay:-.9s}.bar:nth-child(10){animation-delay:-.1s}
        .bar:nth-child(11){animation-delay:-.4s}.bar:nth-child(12){animation-delay:-.6s}
        @keyframes bounce { 0%, 100% { height: 8px } 50% { height: 36px } }
        .bar.idle { height: 8px; transition: height .3s; }

        /* ── Word highlight ── */
        .highlight {
            background: linear-gradient(120deg, transparent 0%, transparent 60%, {{ $primario }}40 60%, {{ $primario }}40 100%);
            background-size: 250% 100%;
            background-position: 100% 0;
            padding: 0 4px; border-radius: 4px;
            animation: highlight-in 1.2s ease forwards;
            animation-delay: 2.8s;
        }
        @keyframes highlight-in {
            to { background-position: 0 0; }
        }

        [x-cloak] { display: none !important; }

        /* ── Feature pills ── */
        .pill {
            background: rgba(255,255,255,.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.10);
        }
    </style>
</head>
<body x-data="{
    playing: false, loading: false, audio: null,
    init() {
        this.audio = new Audio('/audio/maintenance.mp3?v={{ time() }}');
        this.audio.preload = 'auto';
        this.audio.addEventListener('ended', () => this.playing = false);
        this.audio.addEventListener('pause', () => this.playing = false);
        this.audio.addEventListener('play',  () => { this.playing = true; this.loading = false; });
        this.audio.addEventListener('playing',() => this.loading = false);
        this.audio.addEventListener('waiting',() => this.loading = true);
    },
    async toggle() {
        if (this.playing) { this.audio.pause(); return; }
        this.loading = true;
        try { await this.audio.play(); } catch (e) { this.loading = false; }
    }
}" class="relative grain">

    <div class="mesh-bg"></div>
    <div class="grid-pattern"></div>
    <div class="particle w-80 h-80 sm:w-96 sm:h-96 top-[5%] left-[-5%]"  style="animation-delay: 0s"></div>
    <div class="particle w-72 h-72 sm:w-80 sm:h-80 top-[40%] right-[-5%]" style="animation-delay: -8s"></div>
    <div class="particle w-64 h-64 sm:w-72 sm:h-72 bottom-[5%] left-[25%]" style="animation-delay: -14s"></div>

    {{-- ── Header con logo + acceder ── --}}
    <header class="relative z-20 flex items-center justify-between px-4 sm:px-6 lg:px-10 py-4 sm:py-5 fade-1">
        <div class="flex items-center gap-2.5">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-8 sm:h-10 w-auto object-contain"
                     onerror="this.outerHTML='<div class=\'h-10 w-10 rounded-xl bg-gradient-to-br from-[var(--brand)] to-[var(--brand-2)] text-white flex items-center justify-center font-black\'>K</div>'">
            @else
                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-[var(--brand)] to-[var(--brand-2)] text-white flex items-center justify-center font-black">K</div>
            @endif
            <span class="text-[16px] sm:text-[18px] font-bold tracking-tight">{{ $brand }}</span>
        </div>
        <a href="https://admin.{{ $host }}/login"
           class="text-[12px] sm:text-[13px] font-medium text-white/50 hover:text-white transition inline-flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
            Acceder
        </a>
    </header>

    {{-- ── Contenido central ── --}}
    <main class="relative z-10 px-5 sm:px-6 py-12 sm:py-16 text-center">

        {{-- LOGO GIGANTE con efectos --}}
        <div class="fade-2 flex justify-center mb-10 sm:mb-14">
            <div class="relative levitate">
                <div class="absolute inset-0 -m-16 sm:-m-20 lg:-m-28 rounded-full blur-3xl glow-pulse pointer-events-none"
                     style="background: radial-gradient(circle, {{ $primario }} 0%, transparent 65%);"></div>

                <div class="absolute inset-0 -m-6 sm:-m-8 lg:-m-10 rounded-full border border-white/15 animate-[spin_25s_linear_infinite] pointer-events-none"></div>
                <div class="absolute inset-0 -m-14 sm:-m-18 lg:-m-24 rounded-full border border-white/8 animate-[spin_45s_linear_infinite_reverse] pointer-events-none"></div>
                <div class="absolute inset-0 -m-24 sm:-m-32 lg:-m-44 rounded-full border border-white/5 animate-[spin_70s_linear_infinite] pointer-events-none"></div>

                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}"
                         class="relative h-28 sm:h-36 md:h-44 lg:h-52 xl:h-60 w-auto object-contain"
                         style="filter: drop-shadow(0 0 30px {{ $primario }}99) drop-shadow(0 0 60px {{ $primario }}55);"
                         onerror="this.outerHTML='<div class=\'relative h-32 sm:h-44 lg:h-56 w-32 sm:w-44 lg:w-56 rounded-[2rem] bg-gradient-to-br from-[var(--brand)] to-[var(--brand-2)] text-white flex items-center justify-center font-black text-6xl sm:text-7xl lg:text-9xl shadow-2xl\' style=\'box-shadow: 0 0 60px {{ $primario }}80;\'>K</div>'">
                @else
                    <div class="relative h-32 sm:h-44 lg:h-56 w-32 sm:w-44 lg:w-56 rounded-[2rem] bg-gradient-to-br from-[var(--brand)] to-[var(--brand-2)] text-white flex items-center justify-center font-black text-6xl sm:text-7xl lg:text-9xl shadow-2xl"
                         style="box-shadow: 0 0 60px {{ $primario }}80;">K</div>
                @endif
            </div>
        </div>

        {{-- Badge --}}
        <div class="fade-3 inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 backdrop-blur border border-white/10 mb-5 sm:mb-6">
            <span class="relative flex h-1.5 w-1.5">
                <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background: {{ $primario }};"></span>
                <span class="relative inline-flex rounded-full h-1.5 w-1.5" style="background: {{ $primario }};"></span>
            </span>
            <span class="text-[10px] sm:text-[11px] uppercase tracking-[0.25em] font-bold gradient-text">Coming soon</span>
        </div>

        {{-- Headline --}}
        <h1 class="fade-3 display text-[40px] xs:text-[48px] sm:text-[64px] md:text-[80px] lg:text-[96px] xl:text-[112px] leading-[0.95] max-w-5xl mx-auto">
            <span class="block text-white">Algo grande</span>
            <span class="block serif shimmer-text" style="font-size: 1.05em;">está por venir</span>
        </h1>

        {{-- ── MANIFIESTO ── --}}
        <div class="mt-16 sm:mt-24 lg:mt-32 max-w-3xl mx-auto">
            <div class="fade-4 text-[10px] sm:text-[11px] uppercase tracking-[0.3em] font-bold text-white/40 mb-8">
                — El manifiesto —
            </div>

            <div class="serif text-[22px] sm:text-[32px] lg:text-[44px] leading-[1.25] text-white/90 space-y-3 sm:space-y-4">
                @foreach($manifiesto as $i => $linea)
                    <p class="cascade" style="animation-delay: {{ 1.5 + $i * 0.35 }}s;">{{ $linea }}</p>
                @endforeach
            </div>

            <p class="cascade serif text-[28px] sm:text-[44px] lg:text-[60px] mt-10 sm:mt-14"
               style="animation-delay: 3.5s">
                Eso es <span class="text-[var(--brand)] font-semibold not-italic" style="font-family: 'Sora', sans-serif;">Kivox</span>.
            </p>

            <div class="cascade mt-8 sm:mt-12" style="animation-delay: 4s">
                <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 max-w-2xl mx-auto">
                    @foreach($features as $f)
                        <div class="pill rounded-full px-3 sm:px-4 py-1.5 sm:py-2 text-[11px] sm:text-[12px] font-medium text-white/80">
                            <i class="fa-solid fa-circle-check text-[var(--brand)] text-[9px] mr-1"></i>
                            {{ $f }}
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="cascade text-[16px] sm:text-[20px] lg:text-[24px] text-white/70 mt-10 sm:mt-14 leading-relaxed"
               style="animation-delay: 4.5s">
                Tu negocio nunca volverá a ser el mismo.<br>
                <span class="text-white font-semibold">Bienvenido al futuro de las ventas conversacionales.</span>
            </p>

            <p class="cascade display text-[24px] sm:text-[32px] lg:text-[44px] mt-8 sm:mt-10"
               style="animation-delay: 5s">
                <span class="text-white">Kivox.</span>
                <span class="serif text-[var(--brand)]">Vende más, conversando.</span>
            </p>
        </div>

        {{-- AUDIO PLAYER --}}
        <div class="mt-16 sm:mt-20 fade-7">
            <p class="text-[11px] sm:text-[12px] uppercase tracking-[0.25em] text-white/40 font-semibold mb-5">
                <i class="fa-solid fa-headphones"></i> Escucha el manifiesto en voz
            </p>
            <button @click="toggle()"
                    :class="playing ? 'playing scale-105 border-white/30' : ''"
                    class="group relative inline-flex items-center gap-4 sm:gap-5 px-5 sm:px-6 py-3 sm:py-3.5 rounded-full bg-white/5 backdrop-blur border border-white/10 hover:border-white/25 transition-all">

                <div class="relative">
                    <div class="absolute -inset-1.5 rounded-full opacity-40 blur-lg" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));"></div>
                    <div class="relative flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-full text-white pulse-ring"
                         style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                        <i x-show="!playing && !loading" class="fa-solid fa-play text-[13px] ml-0.5"></i>
                        <i x-show="playing && !loading" x-cloak class="fa-solid fa-pause text-[13px]"></i>
                        <i x-show="loading" x-cloak class="fa-solid fa-spinner fa-spin text-[13px]"></i>
                    </div>
                </div>

                <div class="flex items-center h-11">
                    @for($i = 0; $i < 12; $i++)
                        <span class="bar idle {{ $i >= 7 ? 'hidden sm:inline-block' : '' }}"></span>
                    @endfor
                </div>

                <span class="text-[12px] sm:text-[13px] font-semibold text-white/85 whitespace-nowrap">
                    <span x-show="!playing && !loading">Reproducir</span>
                    <span x-show="playing && !loading" x-cloak>Reproduciendo</span>
                    <span x-show="loading" x-cloak>Cargando…</span>
                </span>
            </button>
        </div>

        <footer class="mt-16 sm:mt-20 fade-8 text-[10px] sm:text-[11px] text-white/30 tracking-[0.15em] uppercase">
            © {{ date('Y') }} {{ $brand }} · Hecho en Colombia
        </footer>
    </main>

</body>
</html>
