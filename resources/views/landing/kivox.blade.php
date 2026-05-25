@php
    $cfg = null;
    try { $cfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}
    $brand = $cfg->nombre ?: 'Kivox';
    $primario = $cfg->color_primario ?: '#10b981';
    $secundario = $cfg->color_secundario ?: '#059669';
    $logoUrl = $cfg->logo_url ?? null;
    $faviconUrl = $cfg->favicon_url ?? $logoUrl;
    $host = request()->getHost();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $brand }} · Algo grande está por venir</title>
    <meta name="robots" content="noindex,nofollow">

    {{-- 🪄 Favicon de la plataforma --}}
    @if($faviconUrl)
        <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @endif

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    {{-- ✨ Tipografía: Bricolage Grotesque (similar al logo KIVOX) + Instrument Serif (cursivas) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand: {{ $primario }};
            --brand-2: {{ $secundario }};
            --ink: #050507;
        }
        * { -webkit-font-smoothing: antialiased; }
        html, body {
            font-family: 'Bricolage Grotesque', system-ui, sans-serif;
            letter-spacing: -0.022em;
            background: #050507;
            color: white;
            min-height: 100dvh;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .display { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; letter-spacing: -0.04em; line-height: 0.95; }
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
            50%      { transform: scale(1.18) rotate(3deg); }
        }

        /* ── Grain ── */
        .grain::after {
            content: ''; position: fixed; inset: 0; z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
            opacity: 0.05; pointer-events: none; mix-blend-mode: overlay;
        }

        /* ── Grid pattern radial ── */
        .grid-pattern {
            position: fixed; inset: 0; z-index: 1;
            background-image:
                linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px);
            background-size: 70px 70px;
            mask-image: radial-gradient(ellipse at center, black 25%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 25%, transparent 70%);
            pointer-events: none;
        }

        /* ── Particles ── */
        .particle {
            position: fixed; border-radius: 50%; z-index: 0;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            opacity: .10; pointer-events: none; filter: blur(50px);
            animation: float 25s infinite ease-in-out;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%      { transform: translate(80px, -60px) scale(1.15); }
            66%      { transform: translate(-50px, 40px) scale(.9); }
        }

        /* ── Fade-in sequence ── */
        @keyframes fade-up { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        .fade-1 { opacity: 0; animation: fade-up 1s .2s forwards; }
        .fade-2 { opacity: 0; animation: fade-up 1s .6s forwards; }
        .fade-3 { opacity: 0; animation: fade-up 1s 1s forwards; }
        .fade-4 { opacity: 0; animation: fade-up 1s 1.4s forwards; }

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

        /* ── Logo levitando ── */
        @keyframes levitate {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-12px); }
        }
        .levitate { animation: levitate 6s ease-in-out infinite; }

        /* ── Brillo del logo pulsante ── */
        @keyframes glow-pulse {
            0%, 100% { opacity: .4; transform: scale(1); }
            50%      { opacity: .7; transform: scale(1.1); }
        }
        .glow-pulse { animation: glow-pulse 4s ease-in-out infinite; }

        /* ── Text shimmer ── */
        @keyframes shimmer-text {
            0%   { background-position: -200% 0; }
            100% { background-position:  200% 0; }
        }
        .shimmer-text {
            background: linear-gradient(90deg, white 0%, white 35%, {{ $primario }} 50%, white 65%, white 100%);
            background-size: 200% 100%;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: shimmer-text 4s linear infinite;
        }

        /* ── Equalizer bars ── */
        .bar {
            display: inline-block; width: 3px; margin: 0 2px;
            background: linear-gradient(180deg, var(--brand), var(--brand-2));
            border-radius: 2px; transform-origin: center;
        }
        .playing .bar { animation: bounce 1s ease-in-out infinite; }
        .bar:nth-child(1) { animation-delay: -.0s } .bar:nth-child(2) { animation-delay: -.2s }
        .bar:nth-child(3) { animation-delay: -.4s } .bar:nth-child(4) { animation-delay: -.6s }
        .bar:nth-child(5) { animation-delay: -.8s } .bar:nth-child(6) { animation-delay: -.3s }
        .bar:nth-child(7) { animation-delay: -.5s } .bar:nth-child(8) { animation-delay: -.7s }
        .bar:nth-child(9) { animation-delay: -.9s } .bar:nth-child(10){ animation-delay: -.1s }
        .bar:nth-child(11){ animation-delay: -.4s } .bar:nth-child(12){ animation-delay: -.6s }
        @keyframes bounce { 0%, 100% { height: 8px } 50% { height: 40px } }
        .bar.idle { height: 8px; transition: height .3s; }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="{
    playing: false, loading: false, audio: null,
    init() {
        this.audio = new Audio('/audio/maintenance.mp3');
        this.audio.preload = 'auto';
        this.audio.addEventListener('ended',  () => this.playing = false);
        this.audio.addEventListener('pause',  () => this.playing = false);
        this.audio.addEventListener('play',   () => { this.playing = true; this.loading = false; });
        this.audio.addEventListener('waiting',() => this.loading = true);
        this.audio.addEventListener('playing',() => this.loading = false);
    },
    async toggle() {
        if (this.playing) { this.audio.pause(); return; }
        this.loading = true;
        try { await this.audio.play(); }
        catch (err) { this.loading = false; }
    }
}" class="relative grain">

    {{-- Background effects --}}
    <div class="mesh-bg"></div>
    <div class="grid-pattern"></div>

    <div class="particle w-80 h-80 sm:w-96 sm:h-96 top-[5%] left-[-5%]"  style="animation-delay: 0s"></div>
    <div class="particle w-72 h-72 sm:w-80 sm:h-80 top-[40%] right-[-5%]" style="animation-delay: -8s"></div>
    <div class="particle w-64 h-64 sm:w-72 sm:h-72 bottom-[5%] left-[25%]" style="animation-delay: -14s"></div>

    {{-- ── Login link discreto en esquina superior derecha ── --}}
    <a href="https://admin.{{ $host }}/login"
       class="absolute top-4 right-4 sm:top-6 sm:right-6 z-20 fade-1 text-[12px] sm:text-[13px] font-medium text-white/40 hover:text-white/80 transition inline-flex items-center gap-1.5">
        <i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i>
        Acceder
    </a>

    {{-- ── Contenido central ── --}}
    <main class="relative z-10 min-h-[100dvh] min-h-screen flex flex-col items-center justify-center px-5 sm:px-6 py-12 text-center">

        {{-- LOGO con efectos --}}
        <div class="relative fade-1 levitate">
            {{-- Glow pulsante detrás --}}
            <div class="absolute inset-0 -m-16 sm:-m-20 lg:-m-28 rounded-full blur-3xl glow-pulse pointer-events-none"
                 style="background: radial-gradient(circle, {{ $primario }} 0%, transparent 65%);"></div>

            {{-- Anillos orbitando (3 capas, distintos tamaños y velocidades) --}}
            <div class="absolute inset-0 -m-6 sm:-m-8 lg:-m-10 rounded-full border border-white/15 animate-[spin_25s_linear_infinite] pointer-events-none"></div>
            <div class="absolute inset-0 -m-14 sm:-m-18 lg:-m-24 rounded-full border border-white/8 animate-[spin_45s_linear_infinite_reverse] pointer-events-none"></div>
            <div class="absolute inset-0 -m-24 sm:-m-32 lg:-m-44 rounded-full border border-white/5 animate-[spin_70s_linear_infinite] pointer-events-none"></div>

            {{-- LOGO --}}
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brand }}"
                     class="relative h-32 xs:h-36 sm:h-44 md:h-52 lg:h-60 xl:h-72 w-auto object-contain"
                     style="filter: drop-shadow(0 0 30px {{ $primario }}AA) drop-shadow(0 0 60px {{ $primario }}55);">
            @else
                <div class="relative h-36 sm:h-48 lg:h-64 w-36 sm:w-48 lg:w-64 rounded-[2rem] bg-gradient-to-br from-[var(--brand)] to-[var(--brand-2)] text-white flex items-center justify-center font-black text-6xl sm:text-8xl lg:text-9xl shadow-2xl"
                     style="box-shadow: 0 0 80px {{ $primario }}80;">K</div>
            @endif
        </div>

        {{-- Mensaje principal: ALGO GRANDE ESTÁ POR VENIR --}}
        <div class="mt-12 sm:mt-16 lg:mt-20 fade-2 max-w-3xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 backdrop-blur border border-white/10 mb-5 sm:mb-6">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background: {{ $primario }};"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5" style="background: {{ $primario }};"></span>
                </span>
                <span class="text-[10px] sm:text-[11px] uppercase tracking-[0.25em] font-bold gradient-text">
                    Coming soon
                </span>
            </div>

            <h1 class="display text-[40px] xs:text-[48px] sm:text-[64px] md:text-[80px] lg:text-[96px] xl:text-[112px] leading-[0.95]">
                <span class="block text-white">Algo grande</span>
                <span class="block serif shimmer-text" style="font-size: 1.05em;">está por venir</span>
            </h1>
        </div>

        {{-- Audio reproductor minimalista --}}
        <div class="mt-10 sm:mt-14 fade-3">
            <button @click="toggle()"
                    :class="playing ? 'playing scale-105 border-white/30' : ''"
                    class="group relative inline-flex items-center gap-4 sm:gap-5 px-5 sm:px-6 py-3 sm:py-3.5 rounded-full bg-white/5 backdrop-blur border border-white/10 hover:border-white/25 transition-all">

                <div class="relative">
                    <div class="absolute -inset-1.5 rounded-full opacity-40 blur-lg" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));"></div>
                    <div class="relative flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-full text-white pulse-ring"
                         style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                        <i x-show="!playing && !loading" class="fa-solid fa-play text-[12px] ml-0.5"></i>
                        <i x-show="playing && !loading" x-cloak class="fa-solid fa-pause text-[12px]"></i>
                        <i x-show="loading" x-cloak class="fa-solid fa-spinner fa-spin text-[12px]"></i>
                    </div>
                </div>

                <div class="flex items-center h-10 sm:h-11">
                    @for($i = 0; $i < 12; $i++)
                        <span class="bar idle {{ $i >= 7 ? 'hidden sm:inline-block' : '' }}"></span>
                    @endfor
                </div>

                <span class="text-[12px] sm:text-[13px] font-semibold text-white/80 whitespace-nowrap">
                    <span x-show="!playing && !loading">▸ Escuchar el manifiesto</span>
                    <span x-show="playing && !loading" x-cloak>Reproduciendo…</span>
                    <span x-show="loading" x-cloak>Cargando…</span>
                </span>
            </button>
        </div>

        {{-- Footer minimalista --}}
        <div class="absolute bottom-4 sm:bottom-6 inset-x-0 fade-4">
            <p class="text-center text-[10px] sm:text-[11px] text-white/30 tracking-[0.15em] uppercase">
                © {{ date('Y') }} {{ $brand }}
            </p>
        </div>
    </main>

</body>
</html>
