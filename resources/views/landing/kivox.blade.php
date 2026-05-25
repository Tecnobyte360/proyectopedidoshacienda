@php
    $cfg = null;
    try { $cfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}
    $brand = $cfg->nombre ?: 'Kivox';
    $primario = $cfg->color_primario ?: '#10b981';
    $secundario = $cfg->color_secundario ?: '#059669';
    $logoUrl = $cfg->logo_url ?? null;
    $host = request()->getHost();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $brand }} · Próximamente</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="{{ $cfg->favicon_url ?? '/favicon.ico' }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: {{ $primario }};
            --brand-2: {{ $secundario }};
            --ink: #0a0a0a;
        }
        * { -webkit-font-smoothing: antialiased; }
        body { font-family: 'Inter', sans-serif; letter-spacing: -0.011em; overflow: hidden; background: #0a0a0a; color: white; }
        .display { font-family: 'Inter', sans-serif; letter-spacing: -0.04em; line-height: 0.92; font-weight: 800; }
        .serif { font-family: 'Instrument Serif', serif; font-style: italic; letter-spacing: -0.02em; font-weight: 400; }
        .gradient-text {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }

        /* ── Mesh gradient background ── */
        .mesh-bg {
            position: absolute; inset: 0;
            background:
                radial-gradient(at 20% 30%, {{ $primario }}40 0px, transparent 50%),
                radial-gradient(at 80% 70%, {{ $secundario }}40 0px, transparent 50%),
                radial-gradient(at 50% 50%, {{ $primario }}20 0px, transparent 40%);
            animation: mesh 20s ease-in-out infinite;
        }
        @keyframes mesh {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50%      { transform: scale(1.15) rotate(3deg); }
        }

        /* ── Grain texture ── */
        .grain::after {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
            opacity: 0.06; pointer-events: none; mix-blend-mode: overlay;
        }

        /* ── Grid pattern ── */
        .grid-pattern {
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 80px 80px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
        }

        /* ── Particles ── */
        .particle {
            position: absolute; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            opacity: .12; pointer-events: none; filter: blur(40px);
            animation: float 25s infinite ease-in-out;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%      { transform: translate(100px, -80px) scale(1.2); }
            66%      { transform: translate(-60px, 50px) scale(.9); }
        }

        /* ── Reveal sequence ── */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-1 { opacity: 0; animation: fade-up 1s .1s forwards; }
        .fade-2 { opacity: 0; animation: fade-up 1s .4s forwards; }
        .fade-3 { opacity: 0; animation: fade-up 1s .7s forwards; }
        .fade-4 { opacity: 0; animation: fade-up 1s 1s forwards; }
        .fade-5 { opacity: 0; animation: fade-up 1s 1.3s forwards; }

        /* ── Play button pulse ── */
        @keyframes pulse-ring {
            0%   { transform: scale(.85); opacity: .9; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .pulse-ring::before, .pulse-ring::after {
            content: ''; position: absolute; inset: 0; border-radius: 50%;
            border: 2px solid {{ $primario }};
            animation: pulse-ring 2.5s cubic-bezier(.4,0,.2,1) infinite;
        }
        .pulse-ring::after { animation-delay: 1.25s; }

        /* ── Audio bars equalizer ── */
        .bar {
            display: inline-block;
            width: 3px;
            margin: 0 2px;
            background: linear-gradient(180deg, var(--brand), var(--brand-2));
            border-radius: 2px;
            transform-origin: center;
        }
        .playing .bar { animation: bounce 1s ease-in-out infinite; }
        .bar:nth-child(1)  { animation-delay: -0.0s; }
        .bar:nth-child(2)  { animation-delay: -0.2s; }
        .bar:nth-child(3)  { animation-delay: -0.4s; }
        .bar:nth-child(4)  { animation-delay: -0.6s; }
        .bar:nth-child(5)  { animation-delay: -0.8s; }
        .bar:nth-child(6)  { animation-delay: -0.3s; }
        .bar:nth-child(7)  { animation-delay: -0.5s; }
        .bar:nth-child(8)  { animation-delay: -0.7s; }
        .bar:nth-child(9)  { animation-delay: -0.9s; }
        .bar:nth-child(10) { animation-delay: -0.1s; }
        .bar:nth-child(11) { animation-delay: -0.4s; }
        .bar:nth-child(12) { animation-delay: -0.6s; }
        .bar:nth-child(13) { animation-delay: -0.2s; }
        .bar:nth-child(14) { animation-delay: -0.8s; }
        .bar:nth-child(15) { animation-delay: -0.5s; }
        @keyframes bounce {
            0%, 100% { height: 12px; }
            50%      { height: 60px; }
        }
        .bar.idle { height: 12px; transition: height .3s; }

        /* ── Big animated text ── */
        @keyframes text-glow {
            0%, 100% { text-shadow: 0 0 20px {{ $primario }}40, 0 0 40px {{ $primario }}20; }
            50%      { text-shadow: 0 0 30px {{ $primario }}80, 0 0 60px {{ $primario }}40; }
        }
        .glow-text { animation: text-glow 4s ease-in-out infinite; }

        /* ── Marquee de palabras ── */
        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee {
            display: flex; gap: 4rem;
            animation: marquee 40s linear infinite;
            width: max-content;
            white-space: nowrap;
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body x-data="{
    playing: false,
    loading: false,
    audio: null,
    error: null,
    init() {
        this.audio = new Audio('/audio/maintenance.mp3');
        this.audio.preload = 'auto';
        this.audio.addEventListener('ended', () => this.playing = false);
        this.audio.addEventListener('pause', () => this.playing = false);
        this.audio.addEventListener('play',  () => { this.playing = true; this.loading = false; });
        this.audio.addEventListener('playing', () => this.loading = false);
        this.audio.addEventListener('waiting', () => this.loading = true);
        this.audio.addEventListener('error', (e) => { this.error = 'No se pudo cargar el audio'; console.error('audio error', e); });
    },
    async toggle() {
        if (this.playing) { this.audio.pause(); return; }
        this.loading = true;
        try {
            await this.audio.play();
        } catch (err) {
            console.error('play error', err);
            this.error = 'Tu navegador bloqueó el audio. Refresca y vuelve a intentar.';
            this.loading = false;
        }
    }
}" class="relative grain">

    {{-- Background layers --}}
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-pattern"></div>

    <div class="particle w-96 h-96 top-[10%] left-[5%]" style="animation-delay: 0s"></div>
    <div class="particle w-80 h-80 top-[50%] right-[5%]" style="animation-delay: -7s"></div>
    <div class="particle w-72 h-72 bottom-[10%] left-[30%]" style="animation-delay: -14s"></div>

    {{-- Content --}}
    <div class="relative z-10 min-h-screen flex flex-col">

        {{-- Top nav --}}
        <header class="flex items-center justify-between px-6 lg:px-12 py-6 fade-1">
            <div class="flex items-center gap-2.5">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-10 w-auto">
                @else
                    <div class="h-10 w-10 rounded-xl bg-white text-[var(--ink)] flex items-center justify-center font-black">K</div>
                @endif
                <span class="text-[18px] font-bold tracking-tight text-white">{{ $brand }}</span>
            </div>

            <a href="https://admin.{{ $host }}/login" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-white/70 hover:text-white transition">
                Iniciar sesión <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </header>

        {{-- Hero --}}
        <main class="flex-1 flex items-center justify-center px-6 lg:px-12 py-8">
            <div class="max-w-5xl w-full text-center">

                {{-- Badge --}}
                <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-white/5 backdrop-blur border border-white/10 text-[12px] font-semibold text-white/90 mb-10 fade-1">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background: {{ $primario }};"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2" style="background: {{ $primario }};"></span>
                    </span>
                    Trabajando en algo grande
                </div>

                {{-- LOGO GIGANTE EN EL CENTRO --}}
                <div class="fade-2 flex flex-col items-center justify-center my-8 lg:my-12">
                    <div class="relative">
                        {{-- Glow detrás del logo --}}
                        <div class="absolute inset-0 -m-16 rounded-full opacity-40 blur-3xl pointer-events-none" style="background: radial-gradient(circle, {{ $primario }} 0%, transparent 60%);"></div>

                        {{-- Anillos orbitando --}}
                        <div class="absolute inset-0 -m-12 rounded-full border border-white/10 animate-[spin_30s_linear_infinite] pointer-events-none"></div>
                        <div class="absolute inset-0 -m-20 rounded-full border border-white/5 animate-[spin_45s_linear_infinite_reverse] pointer-events-none"></div>

                        {{-- LOGO --}}
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $brand }}"
                                 class="relative h-40 lg:h-56 xl:h-64 w-auto object-contain drop-shadow-[0_0_40px_{{ $primario }}80]"
                                 style="filter: drop-shadow(0 0 30px {{ $primario }}80) drop-shadow(0 0 60px {{ $primario }}40);">
                        @else
                            <div class="relative h-48 lg:h-64 w-48 lg:w-64 rounded-3xl bg-gradient-to-br from-[var(--brand)] to-[var(--brand-2)] text-white flex items-center justify-center font-black text-7xl lg:text-9xl shadow-2xl"
                                 style="box-shadow: 0 0 60px {{ $primario }}80;">K</div>
                        @endif
                    </div>

                    {{-- Brand name + tagline --}}
                    <div class="mt-8">
                        <div class="display text-[44px] lg:text-[64px] tracking-tight">
                            <span class="text-white">Pronto</span>
                            <span class="serif text-[var(--brand)]">volvemos</span>
                        </div>
                        <p class="text-[14px] text-white/50 mt-2 max-w-md mx-auto">
                            Estamos puliendo cada detalle para darte la mejor experiencia.
                        </p>
                    </div>
                </div>

                {{-- Audio CTA gigante --}}
                <div class="mt-8 fade-3">
                    <p class="text-[13px] uppercase tracking-[0.3em] text-white/50 font-semibold mb-6">
                        <i class="fa-solid fa-headphones"></i> Escucha el mensaje de {{ $brand }}
                    </p>

                    <button @click="toggle()"
                            :class="playing ? 'playing scale-105' : ''"
                            class="group relative inline-flex items-center gap-6 px-8 py-5 rounded-full bg-white/5 backdrop-blur border border-white/10 hover:border-white/30 transition-all">

                        {{-- Play / Pause button --}}
                        <div class="relative">
                            <div class="absolute -inset-2 rounded-full opacity-30 blur-xl" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));"></div>
                            <div class="relative flex h-16 w-16 items-center justify-center rounded-full text-white shadow-2xl pulse-ring"
                                 style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                                <i x-show="!playing && !loading" class="fa-solid fa-play text-xl ml-1"></i>
                                <i x-show="playing && !loading" x-cloak class="fa-solid fa-pause text-xl"></i>
                                <i x-show="loading" x-cloak class="fa-solid fa-spinner fa-spin text-xl"></i>
                            </div>
                        </div>

                        {{-- Equalizer bars --}}
                        <div class="flex items-center h-16">
                            @for($i = 0; $i < 15; $i++)
                                <span class="bar idle"></span>
                            @endfor
                        </div>

                        <div class="text-left">
                            <div class="text-[15px] font-bold text-white">
                                <span x-show="!playing && !loading && !error">Reproducir mensaje</span>
                                <span x-show="playing && !loading" x-cloak>Reproduciendo…</span>
                                <span x-show="loading" x-cloak>Cargando…</span>
                                <span x-show="error" x-cloak class="text-rose-400" x-text="error"></span>
                            </div>
                            <div class="text-[11px] text-white/50">~15 segundos · voz natural IA</div>
                        </div>
                    </button>

                    {{-- Fallback descarga si autoplay falla --}}
                    <div class="mt-3">
                        <a href="/audio/maintenance.mp3" download class="text-[11px] text-white/40 hover:text-white/70 transition underline">
                            ¿No suena? Descargar mp3
                        </a>
                    </div>
                </div>

                {{-- CTAs --}}
                <div class="flex flex-wrap items-center justify-center gap-3 mt-14 fade-4">
                    <a href="https://wa.me/573216499744?text=Hola%20Kivox%2C%20quiero%20saber%20cuando%20vuelven" target="_blank"
                       class="inline-flex items-center gap-2 bg-white text-[var(--ink)] font-semibold text-[14px] px-5 py-3 rounded-full hover:scale-105 transition">
                        <i class="fa-brands fa-whatsapp text-emerald-600"></i> Hablar por WhatsApp
                    </a>
                    <a href="https://admin.{{ $host }}/login"
                       class="inline-flex items-center gap-2 text-white/80 hover:text-white font-medium text-[14px] px-5 py-3 rounded-full border border-white/15 hover:border-white/40 transition">
                        Acceder al panel <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        </main>

        {{-- Marquee bottom --}}
        <div class="border-t border-white/5 py-5 overflow-hidden fade-5">
            <div class="marquee text-[14px] uppercase tracking-[0.3em] text-white/30 font-semibold">
                <span>Volvemos pronto</span>
                <span style="color: var(--brand)">●</span>
                <span>Estamos puliendo detalles</span>
                <span style="color: var(--brand)">●</span>
                <span>Coming soon</span>
                <span style="color: var(--brand)">●</span>
                <span>Stay tuned</span>
                <span style="color: var(--brand)">●</span>
                <span>{{ $brand }}</span>
                <span style="color: var(--brand)">●</span>
                <span>Volvemos pronto</span>
                <span style="color: var(--brand)">●</span>
                <span>Estamos puliendo detalles</span>
                <span style="color: var(--brand)">●</span>
                <span>Coming soon</span>
                <span style="color: var(--brand)">●</span>
                <span>Stay tuned</span>
                <span style="color: var(--brand)">●</span>
                <span>{{ $brand }}</span>
            </div>
        </div>

        {{-- Footer mini --}}
        <footer class="px-6 lg:px-12 py-4 fade-5">
            <div class="flex items-center justify-between text-[11px] text-white/40">
                <span>© {{ date('Y') }} {{ $brand }}</span>
                <span>Hecho en Colombia 🇨🇴</span>
            </div>
        </footer>
    </div>

</body>
</html>
