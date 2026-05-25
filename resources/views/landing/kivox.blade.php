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
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $brand }} · Próximamente</title>
    <meta name="description" content="Estamos construyendo algo increíble. Pronto disponible.">
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
        body { font-family: 'Inter', sans-serif; letter-spacing: -0.011em; overflow: hidden; }

        .display { font-family: 'Inter', sans-serif; letter-spacing: -0.04em; line-height: 0.95; font-weight: 800; }
        .serif { font-family: 'Instrument Serif', serif; font-style: italic; letter-spacing: -0.02em; font-weight: 400; }
        .gradient-text {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }

        /* ── Animated mesh background ── */
        .mesh-bg {
            position: absolute; inset: 0;
            background:
                radial-gradient(at 20% 30%, {{ $primario }}30 0px, transparent 50%),
                radial-gradient(at 80% 70%, {{ $secundario }}30 0px, transparent 50%),
                radial-gradient(at 50% 50%, {{ $primario }}15 0px, transparent 40%);
            animation: mesh 20s ease-in-out infinite;
        }
        @keyframes mesh {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50%      { transform: scale(1.1) rotate(2deg); }
        }

        /* ── Floating particles ── */
        .particle {
            position: absolute; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            opacity: .1; pointer-events: none;
            animation: float-particle 15s infinite ease-in-out;
        }
        @keyframes float-particle {
            0%, 100% { transform: translate(0, 0); }
            33%      { transform: translate(80px, -60px); }
            66%      { transform: translate(-50px, 40px); }
        }

        /* ── Grid pattern ── */
        .grid-pattern {
            background-image:
                linear-gradient(rgba(10,10,10,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(10,10,10,.04) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 70%);
        }

        /* ── Pulse ring ── */
        @keyframes pulse-ring {
            0%   { transform: scale(.85); opacity: .8; }
            100% { transform: scale(1.8); opacity: 0; }
        }
        .pulse-ring::before, .pulse-ring::after {
            content: ''; position: absolute; inset: 0; border-radius: 50%;
            border: 2px solid {{ $primario }};
            animation: pulse-ring 2.5s cubic-bezier(.4,0,.2,1) infinite;
        }
        .pulse-ring::after { animation-delay: 1.25s; }

        /* ── Reveal sequence ── */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in-1 { opacity: 0; animation: fade-up .8s .2s forwards; }
        .fade-in-2 { opacity: 0; animation: fade-up .8s .5s forwards; }
        .fade-in-3 { opacity: 0; animation: fade-up .8s .8s forwards; }
        .fade-in-4 { opacity: 0; animation: fade-up .8s 1.1s forwards; }
        .fade-in-5 { opacity: 0; animation: fade-up .8s 1.4s forwards; }

        /* ── Subtle shimmer on logo ── */
        @keyframes shimmer {
            0%   { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .shimmer { animation: shimmer 20s linear infinite; }

        /* ── Btn ── */
        .btn-primary {
            background: var(--ink); color: white;
            transition: all .3s cubic-bezier(.4,0,.2,1);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 20px 40px -10px rgba(0,0,0,.4); }
    </style>
</head>
<body class="bg-[#fafaf9] text-[var(--ink)] relative min-h-screen">

    {{-- Background layers --}}
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-pattern"></div>

    {{-- Floating decorative particles --}}
    <div class="particle w-32 h-32 top-1/4 left-[10%] blur-2xl" style="animation-delay: 0s"></div>
    <div class="particle w-24 h-24 top-2/3 right-[15%] blur-2xl" style="animation-delay: -5s"></div>
    <div class="particle w-40 h-40 bottom-[15%] left-[20%] blur-3xl" style="animation-delay: -10s"></div>
    <div class="particle w-20 h-20 top-[10%] right-[25%] blur-2xl" style="animation-delay: -8s"></div>

    {{-- Content --}}
    <div class="relative z-10 min-h-screen flex flex-col">

        {{-- Top nav minimal --}}
        <header class="flex items-center justify-between px-6 lg:px-12 py-6 fade-in-1">
            <div class="flex items-center gap-2.5">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-9 w-auto">
                @else
                    <div class="h-9 w-9 rounded-xl bg-[var(--ink)] text-white flex items-center justify-center font-black">K</div>
                @endif
                <span class="text-[17px] font-bold tracking-tight">{{ $brand }}</span>
            </div>

            <a href="https://admin.{{ $host }}/login" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-[var(--ink)]/70 hover:text-[var(--ink)] transition">
                Iniciar sesión <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </header>

        {{-- Hero --}}
        <main class="flex-1 flex items-center justify-center px-6 lg:px-12 py-12">
            <div class="max-w-4xl text-center">

                {{-- Badge --}}
                <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-white border border-[var(--ink)]/10 shadow-sm text-[12px] font-semibold text-[var(--ink)]/80 mb-8 fade-in-1">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background: {{ $primario }};"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2" style="background: {{ $primario }};"></span>
                    </span>
                    Construyendo algo increíble
                </div>

                {{-- Big icon with shimmer --}}
                <div class="fade-in-2 mb-8 flex justify-center">
                    <div class="relative">
                        <div class="absolute inset-0 shimmer">
                            <div class="absolute -inset-4 rounded-full opacity-20 blur-2xl" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));"></div>
                        </div>
                        <div class="relative h-24 w-24 rounded-3xl bg-white border border-[var(--ink)]/10 shadow-2xl flex items-center justify-center">
                            <i class="fa-solid fa-screwdriver-wrench text-[var(--brand)] text-4xl"></i>
                        </div>
                    </div>
                </div>

                {{-- Headline --}}
                <h1 class="display text-[56px] sm:text-[80px] lg:text-[112px] fade-in-3">
                    Estamos en<br>
                    <span class="serif text-[var(--brand)]">mantenimiento</span>.
                </h1>

                <p class="text-[17px] lg:text-[20px] text-[var(--ink)]/60 mt-8 max-w-2xl mx-auto leading-relaxed fade-in-4">
                    Estamos puliendo cada detalle para darte la mejor experiencia.
                    Volvemos en muy poco tiempo con sorpresas.
                </p>

                {{-- CTAs --}}
                <div class="flex flex-wrap items-center justify-center gap-3 mt-12 fade-in-5">
                    <a href="https://wa.me/573216499744?text=Hola%20Kivox%2C%20quiero%20saber%20cuando%20vuelven" target="_blank"
                       class="btn-primary inline-flex items-center gap-2 text-[15px] font-semibold px-6 py-3.5 rounded-full">
                        <i class="fa-brands fa-whatsapp text-[var(--brand)]"></i> Avísame cuando vuelvan
                    </a>
                    <a href="https://admin.{{ $host }}/login"
                       class="inline-flex items-center gap-2 text-[15px] font-semibold px-6 py-3.5 rounded-full bg-white border border-[var(--ink)]/10 hover:border-[var(--ink)] transition">
                        Acceder al panel <i class="fa-solid fa-arrow-right text-[11px]"></i>
                    </a>
                </div>

                {{-- Status --}}
                <div class="mt-16 fade-in-5">
                    <div class="inline-flex items-center gap-6 text-[12px] text-[var(--ink)]/60">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-[var(--brand)] text-[10px]"></i>
                            Sistema operativo
                        </div>
                        <div class="h-3 w-px bg-[var(--ink)]/15"></div>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-circle text-amber-500 text-[8px]"></i>
                            Landing en mejora
                        </div>
                        <div class="h-3 w-px bg-[var(--ink)]/15"></div>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-[var(--brand)] text-[10px]"></i>
                            App de clientes activa
                        </div>
                    </div>
                </div>
            </div>
        </main>

        {{-- Footer --}}
        <footer class="px-6 lg:px-12 py-6 fade-in-5">
            <div class="flex flex-col md:flex-row items-center justify-between gap-3 text-[12px] text-[var(--ink)]/50">
                <span>© {{ date('Y') }} {{ $brand }}. Todos los derechos reservados.</span>
                <div class="flex items-center gap-2">
                    @foreach(['instagram','linkedin','whatsapp'] as $s)
                        <a href="#" class="h-8 w-8 flex items-center justify-center rounded-full border border-[var(--ink)]/10 hover:border-[var(--ink)]/40 hover:bg-white transition">
                            <i class="fa-brands fa-{{ $s }} text-[12px]"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </footer>
    </div>

</body>
</html>
