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
    <title>{{ $brand }} · Vende más por WhatsApp con IA</title>
    <meta name="description" content="Plataforma SaaS para recibir pedidos por WhatsApp atendidos por un bot inteligente. Dashboard de ventas, despachos en vivo, cobros automáticos.">
    <meta property="og:title" content="{{ $brand }} · Vende más por WhatsApp con IA">
    <meta property="og:description" content="El bot que vende por ti 24/7. Cobros, despachos y reportes en una sola plataforma.">
    <link rel="icon" href="{{ $cfg->favicon_url ?? '/favicon.ico' }}" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { -webkit-font-smoothing: antialiased; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }

        :root {
            --brand: {{ $primario }};
            --brand-2: {{ $secundario }};
        }

        .gradient-text {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            transition: all .25s cubic-bezier(.4,0,.2,1);
            box-shadow: 0 8px 20px -8px {{ $primario }}55;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -10px {{ $primario }}88;
        }

        /* ── Animated blobs background ── */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .35;
            animation: blob 20s infinite alternate;
        }
        @keyframes blob {
            0%   { transform: translate(0, 0) scale(1); }
            33%  { transform: translate(50px, -80px) scale(1.15); }
            66%  { transform: translate(-30px, 60px) scale(.85); }
            100% { transform: translate(20px, 20px) scale(1.05); }
        }

        /* ── Scroll fade-in ── */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity .8s, transform .8s;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ── Marquee de logos ── */
        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee-track {
            display: flex;
            gap: 4rem;
            animation: marquee 30s linear infinite;
            width: max-content;
        }

        /* ── Mockup floating ── */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-12px); }
        }
        .floating { animation: float 6s ease-in-out infinite; }

        /* ── Pulse rings ── */
        @keyframes pulse-ring {
            0%   { transform: scale(.8); opacity: 1; }
            100% { transform: scale(2.4); opacity: 0; }
        }
        .pulse-ring::before,
        .pulse-ring::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid white;
            animation: pulse-ring 2s cubic-bezier(.5, 0, .5, 1) infinite;
        }
        .pulse-ring::after { animation-delay: 1s; }

        /* ── Chat bubbles fade-in ── */
        .bubble-anim {
            opacity: 0;
            transform: translateY(20px);
            animation: bubble-in .5s forwards;
        }
        @keyframes bubble-in {
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── Dot pattern ── */
        .dot-pattern {
            background-image: radial-gradient(circle, rgba(0,0,0,.06) 1px, transparent 1px);
            background-size: 22px 22px;
        }

        /* ── Glassmorphism nav on scroll ── */
        .nav-blur { backdrop-filter: blur(20px) saturate(180%); }

        /* ── Modal video ── */
        .video-modal-enter { animation: zoomIn .35s ease; }
        @keyframes zoomIn {
            from { opacity: 0; transform: scale(.85); }
            to   { opacity: 1; transform: scale(1); }
        }

        /* ── Gradient border card ── */
        .gradient-border {
            position: relative;
            background: white;
            border-radius: 1.5rem;
        }
        .gradient-border::before {
            content: '';
            position: absolute;
            inset: -2px;
            background: linear-gradient(135deg, var(--brand), var(--brand-2), var(--brand));
            border-radius: 1.5rem;
            z-index: -1;
        }

        /* ── Scrollbar hide ── */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-white text-slate-800" x-data="{ scrolled: false, videoOpen: false }" x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 30)">

    {{-- ╔═══ NAV ═══╗ --}}
    <nav class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
         :class="scrolled ? 'bg-white/75 nav-blur shadow-sm border-b border-slate-100' : 'bg-transparent'">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 md:h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2.5">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-10 w-auto">
                @else
                    <div class="h-10 w-10 rounded-2xl text-white flex items-center justify-center font-black text-lg shadow-lg" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">K</div>
                @endif
                <span class="text-xl font-black tracking-tight text-slate-900">{{ $brand }}</span>
            </a>
            <div class="hidden md:flex items-center gap-8 text-sm font-bold text-slate-700">
                <a href="#funciona" class="hover:text-slate-900 transition">Cómo funciona</a>
                <a href="#features" class="hover:text-slate-900 transition">Funciones</a>
                <a href="#demo" class="hover:text-slate-900 transition">Ver demo</a>
                <a href="https://admin.{{ $host }}/login" class="hover:text-slate-900 transition">Iniciar sesión</a>
            </div>
            <a href="#cta" class="btn-primary text-white font-extrabold text-sm px-6 py-3 rounded-2xl">
                Empezar gratis <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
            </a>
        </div>
    </nav>

    {{-- ╔═══ HERO ═══╗ --}}
    <section class="relative pt-32 md:pt-40 pb-20 md:pb-32 overflow-hidden">
        {{-- Animated blobs --}}
        <div class="blob w-[500px] h-[500px] -top-32 -left-20" style="background: {{ $primario }};"></div>
        <div class="blob w-[600px] h-[600px] -bottom-40 -right-20" style="background: {{ $secundario }};" data-style="animation-delay: -10s"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white border border-slate-200 shadow-sm px-4 py-1.5 text-xs font-bold text-slate-700 mb-6">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background-color: {{ $primario }};"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2" style="background-color: {{ $primario }};"></span>
                        </span>
                        Nueva versión 2026 disponible
                    </div>

                    <h1 class="text-5xl md:text-7xl font-black text-slate-900 leading-[1.05] tracking-tight">
                        Vende más por<br>
                        <span class="inline-block">
                            <span class="gradient-text">WhatsApp</span>
                            <i class="fa-brands fa-whatsapp text-emerald-500 text-4xl md:text-6xl align-middle"></i>
                        </span><br>
                        con IA que cierra.
                    </h1>

                    <p class="text-lg md:text-xl text-slate-600 mt-7 leading-relaxed max-w-2xl mx-auto lg:mx-0">
                        El bot de <span class="font-bold text-slate-900">{{ $brand }}</span> atiende a tus clientes 24/7,
                        toma pedidos, cobra con link de pago, despacha y aprende.
                        Tú solo cuentas el dinero.
                    </p>

                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 mt-10">
                        <a href="#cta" class="btn-primary inline-flex items-center gap-2 text-white font-extrabold px-8 py-4 rounded-2xl text-base">
                            <i class="fa-solid fa-rocket"></i> Empieza gratis
                        </a>
                        <button @click="videoOpen = true" class="inline-flex items-center gap-3 text-slate-700 font-extrabold pl-2 pr-6 py-2 rounded-2xl hover:bg-slate-100 transition">
                            <span class="relative flex h-12 w-12 items-center justify-center rounded-full text-white shadow-lg pulse-ring" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                                <i class="fa-solid fa-play text-sm ml-0.5"></i>
                            </span>
                            Ver demo de 90 seg
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-x-6 gap-y-3 mt-10 text-xs text-slate-500 font-semibold">
                        <span class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-500"></i> Sin tarjeta de crédito</span>
                        <span class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-500"></i> Setup en 15 min</span>
                        <span class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-500"></i> Cancela cuando quieras</span>
                    </div>
                </div>

                {{-- Mockup chat flotante --}}
                <div class="lg:col-span-5 relative">
                    <div class="absolute -inset-6 rounded-[2rem] opacity-25 blur-3xl" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));"></div>

                    <div class="relative floating">
                        {{-- Card del bot --}}
                        <div class="relative rounded-[2rem] bg-white border border-slate-200 shadow-2xl overflow-hidden mx-auto max-w-md">
                            <div class="px-5 py-3 flex items-center gap-3 text-white" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                                <div class="h-11 w-11 rounded-full bg-white/20 backdrop-blur flex items-center justify-center font-extrabold">B</div>
                                <div class="flex-1">
                                    <div class="font-extrabold text-sm">Bot {{ $brand }}</div>
                                    <div class="text-[10px] opacity-90 flex items-center gap-1.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span> En línea · responde en 1s
                                    </div>
                                </div>
                                <i class="fa-brands fa-whatsapp text-2xl"></i>
                            </div>

                            <div class="p-5 space-y-3 bg-gradient-to-br from-slate-50 to-white min-h-[400px]">
                                <div class="flex bubble-anim" style="animation-delay: .3s">
                                    <div class="bg-white rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[80%] shadow-sm text-sm border border-slate-100">
                                        Hola María 👋 ¿Te pido lo de siempre?
                                        <div class="text-[10px] text-slate-400 mt-1">10:32</div>
                                    </div>
                                </div>
                                <div class="flex justify-end bubble-anim" style="animation-delay: .8s">
                                    <div class="text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%] shadow-sm text-sm" style="background: var(--brand);">
                                        Sí porfa, y 2 cervezas 🍻
                                        <div class="text-[10px] text-white/70 mt-1 text-right">10:32 · <i class="fa-solid fa-check-double"></i></div>
                                    </div>
                                </div>
                                <div class="flex bubble-anim" style="animation-delay: 1.4s">
                                    <div class="bg-white rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%] shadow-sm text-sm border border-slate-100">
                                        ✅ Listo, te resumo:<br>
                                        <span class="text-slate-600">• Pollo asado · $32.000<br>• 2x Cerveza · $14.000<br>• Domicilio · $4.000</span><br>
                                        <span class="font-black text-base">Total: $50.000</span><br><br>
                                        💳 Link de pago: <span class="gradient-text font-bold">wompi.co/...</span>
                                        <div class="text-[10px] text-slate-400 mt-1">10:33</div>
                                    </div>
                                </div>
                                <div class="flex bubble-anim" style="animation-delay: 2s">
                                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%] shadow-sm text-sm">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i> <strong>Pago confirmado</strong> · Pedido <strong>#1284</strong><br>
                                        <span class="text-slate-600 text-xs">Llega en 30-40 min 🚀</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Floating stats card --}}
                        <div class="hidden md:block absolute -bottom-4 -left-12 bg-white rounded-2xl shadow-2xl border border-slate-100 p-4 floating" style="animation-delay: -3s">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <i class="fa-solid fa-arrow-trend-up"></i>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Ventas hoy</div>
                                    <div class="text-xl font-black text-slate-900">$2.4M</div>
                                </div>
                            </div>
                        </div>

                        <div class="hidden md:block absolute -top-4 -right-8 bg-white rounded-2xl shadow-2xl border border-slate-100 p-4 floating" style="animation-delay: -1.5s">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center">
                                    <i class="fa-solid fa-bolt"></i>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Pedidos/hora</div>
                                    <div class="text-xl font-black text-slate-900">47</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ MARQUEE DE LOGOS ═══╗ --}}
    <section class="py-10 border-y border-slate-100 bg-slate-50/60 overflow-hidden">
        <div class="text-center text-xs font-bold uppercase tracking-widest text-slate-400 mb-6">
            Empresas que ya venden con {{ $brand }}
        </div>
        <div class="relative">
            <div class="marquee-track text-2xl md:text-3xl font-black text-slate-300">
                <span>Alimentos La Hacienda</span> <span class="text-slate-200">·</span>
                <span>Importadora AA</span> <span class="text-slate-200">·</span>
                <span>Guayacán Café</span> <span class="text-slate-200">·</span>
                <span>Doblamos SAS</span> <span class="text-slate-200">·</span>
                <span>TecnoByte 360</span> <span class="text-slate-200">·</span>
                <span>Alimentos La Hacienda</span> <span class="text-slate-200">·</span>
                <span>Importadora AA</span> <span class="text-slate-200">·</span>
                <span>Guayacán Café</span> <span class="text-slate-200">·</span>
                <span>Doblamos SAS</span> <span class="text-slate-200">·</span>
                <span>TecnoByte 360</span>
            </div>
        </div>
    </section>

    {{-- ╔═══ CÓMO FUNCIONA — 3 pasos ═══╗ --}}
    <section id="funciona" class="py-24 md:py-32 relative overflow-hidden">
        <div class="absolute inset-0 dot-pattern opacity-50"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <div class="inline-flex items-center gap-2 rounded-full bg-white border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 mb-4 shadow-sm">
                    <i class="fa-solid fa-route"></i> Cómo funciona
                </div>
                <h2 class="text-4xl md:text-6xl font-black text-slate-900 tracking-tight">
                    De cero a vender en <span class="gradient-text">15 minutos</span>
                </h2>
                <p class="text-lg text-slate-600 mt-5">
                    Sin servidores, sin programar. Solo conecta tu WhatsApp y empieza.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 md:gap-6">
                @php
                    $steps = [
                        ['num' => '01', 'icon' => 'fa-link', 'titulo' => 'Conecta tu WhatsApp', 'desc' => 'Vincula tu número actual con un código QR. Compatible con números personales o WhatsApp Business.', 'color' => 'emerald'],
                        ['num' => '02', 'icon' => 'fa-list-check', 'titulo' => 'Carga tus productos', 'desc' => 'Sube tu menú con fotos, precios y categorías. El bot aprende solo qué vendes y cómo lo describes.', 'color' => 'sky'],
                        ['num' => '03', 'icon' => 'fa-rocket', 'titulo' => 'A vender 24/7', 'desc' => 'El bot atiende, toma pedidos, cobra y despacha mientras tú duermes. Tu equipo solo confirma.', 'color' => 'violet'],
                    ];
                @endphp

                @foreach($steps as $i => $s)
                    <div class="relative reveal" style="transition-delay: {{ $i * 0.15 }}s">
                        @if(!$loop->last)
                            <div class="hidden md:block absolute top-12 left-[60%] right-[-20%] h-0.5">
                                <div class="h-full w-full" style="background: linear-gradient(90deg, {{ $primario }}50, transparent);"></div>
                            </div>
                        @endif

                        <div class="relative bg-white rounded-3xl border border-slate-200 p-8 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 group">
                            <div class="text-7xl font-black text-{{ $s['color'] }}-100 absolute -top-4 right-4 select-none group-hover:text-{{ $s['color'] }}-200 transition">{{ $s['num'] }}</div>

                            <div class="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-{{ $s['color'] }}-100 text-{{ $s['color'] }}-600 mb-5 group-hover:scale-110 group-hover:rotate-3 transition">
                                <i class="fa-solid {{ $s['icon'] }} text-2xl"></i>
                            </div>

                            <h3 class="text-xl font-extrabold text-slate-900 mb-2">{{ $s['titulo'] }}</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">{{ $s['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ╔═══ DEMO EN VIDEO ═══╗ --}}
    <section id="demo" class="py-24 md:py-32 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 relative overflow-hidden">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] rounded-full blur-3xl opacity-20" style="background: radial-gradient(circle, {{ $primario }}, transparent);"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-12 reveal">
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur border border-white/20 px-3 py-1 text-xs font-bold text-white/90 mb-4">
                    <i class="fa-solid fa-circle-play"></i> Demo en video
                </div>
                <h2 class="text-4xl md:text-6xl font-black text-white tracking-tight">
                    Mira <span class="gradient-text">{{ $brand }}</span> en acción
                </h2>
                <p class="text-lg text-white/70 mt-5">
                    90 segundos · sin sonido necesario · directo al grano.
                </p>
            </div>

            <div class="reveal">
                <div class="relative rounded-3xl overflow-hidden shadow-2xl group cursor-pointer" @click="videoOpen = true">
                    {{-- Poster: usamos un screenshot del dashboard como placeholder --}}
                    <div class="aspect-video w-full bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900 flex items-center justify-center relative overflow-hidden">
                        {{-- Animated grid pattern --}}
                        <div class="absolute inset-0 opacity-20" style="background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 50px 50px;"></div>

                        {{-- Mock dashboard preview --}}
                        <div class="absolute inset-12 hidden md:flex items-stretch gap-4 opacity-60 group-hover:opacity-80 transition">
                            <div class="flex-1 rounded-2xl bg-white/5 border border-white/10 backdrop-blur p-4">
                                <div class="h-3 w-16 rounded-full bg-white/30 mb-3"></div>
                                <div class="h-8 w-24 rounded bg-white/40 mb-4"></div>
                                <div class="space-y-2">
                                    <div class="h-2 w-full rounded bg-white/20"></div>
                                    <div class="h-2 w-3/4 rounded bg-white/20"></div>
                                </div>
                            </div>
                            <div class="flex-1 rounded-2xl bg-white/5 border border-white/10 backdrop-blur p-4">
                                <div class="h-3 w-20 rounded-full bg-white/30 mb-3"></div>
                                <div class="h-24 w-full rounded bg-gradient-to-tr from-white/20 to-white/5"></div>
                            </div>
                            <div class="flex-1 rounded-2xl bg-white/5 border border-white/10 backdrop-blur p-4">
                                <div class="h-3 w-14 rounded-full bg-white/30 mb-3"></div>
                                <div class="h-8 w-20 rounded bg-white/40 mb-4"></div>
                                <div class="h-2 w-full rounded bg-white/20"></div>
                            </div>
                        </div>

                        {{-- Play button --}}
                        <button class="relative z-10 flex h-24 w-24 items-center justify-center rounded-full text-white shadow-2xl group-hover:scale-110 transition pulse-ring" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                            <i class="fa-solid fa-play text-3xl ml-2"></i>
                        </button>

                        {{-- Bottom bar with stats --}}
                        <div class="absolute bottom-0 inset-x-0 p-6 bg-gradient-to-t from-black/80 to-transparent">
                            <div class="flex items-center justify-between text-white">
                                <div>
                                    <div class="text-xs font-semibold opacity-70">Tour completo de la plataforma</div>
                                    <div class="font-bold">Bot · Dashboard · Despachos · Cobros</div>
                                </div>
                                <div class="text-xs font-mono opacity-70">01:30</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ FEATURES con video corto en cada uno ═══╗ --}}
    <section id="features" class="py-24 md:py-32 bg-gradient-to-b from-white to-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                <div class="inline-flex items-center gap-2 rounded-full bg-white border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 mb-4 shadow-sm">
                    <i class="fa-solid fa-layer-group"></i> Funciones
                </div>
                <h2 class="text-4xl md:text-6xl font-black text-slate-900 tracking-tight">
                    Todo en un <span class="gradient-text">solo lugar</span>
                </h2>
                <p class="text-lg text-slate-600 mt-5">
                    Sin saltar entre 5 herramientas. {{ $brand }} hace todo de punta a punta.
                </p>
            </div>

            {{-- Feature 1: Hero --}}
            <div class="grid lg:grid-cols-2 gap-12 items-center mb-24 reveal">
                <div>
                    <span class="inline-block bg-emerald-100 text-emerald-700 text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full mb-4">
                        <i class="fa-solid fa-robot"></i> Bot con IA
                    </span>
                    <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900 leading-tight">
                        Un vendedor que <span class="gradient-text">no se cansa, no falta, no se queja</span>.
                    </h3>
                    <p class="text-slate-600 mt-4 leading-relaxed">
                        Entiende lo que tus clientes escriben (aunque tengan errores de ortografía), arma pedidos completos, valida cobertura por dirección, sugiere productos y cierra venta. Y si algo se complica, deriva al humano correcto.
                    </p>
                    <ul class="space-y-2 mt-6 text-sm text-slate-700">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-emerald-500 mt-1"></i> Powered by Claude (Anthropic) o GPT (OpenAI)</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-emerald-500 mt-1"></i> Aprende de cada conversación</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-emerald-500 mt-1"></i> Reconoce a clientes recurrentes</li>
                    </ul>
                </div>

                <div class="relative">
                    <div class="absolute -inset-4 rounded-3xl opacity-20 blur-2xl" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));"></div>
                    <div class="relative rounded-3xl bg-white border border-slate-200 shadow-2xl p-6 space-y-3">
                        <div class="flex items-center gap-2 text-xs text-slate-500 pb-2 border-b border-slate-100">
                            <i class="fa-solid fa-circle-info"></i> Conversación en tiempo real
                        </div>
                        <div class="flex"><div class="bg-slate-100 rounded-2xl rounded-tl-sm px-4 py-2 text-sm">Hola, quiero un pollo a la sancocho</div></div>
                        <div class="flex justify-end"><div class="text-white rounded-2xl rounded-tr-sm px-4 py-2 text-sm" style="background: var(--brand);">Claro, ¿prefieres pollo asado al carbón o sancochado? Tenemos ambos 🍗</div></div>
                        <div class="flex"><div class="bg-slate-100 rounded-2xl rounded-tl-sm px-4 py-2 text-sm">Sanchocho jajaja</div></div>
                        <div class="flex justify-end"><div class="text-white rounded-2xl rounded-tr-sm px-4 py-2 text-sm" style="background: var(--brand);">¡Listo! Sancocho de gallina 🍲. ¿Para 2 personas o más?</div></div>
                    </div>
                </div>
            </div>

            {{-- Feature 2: Dashboard --}}
            <div class="grid lg:grid-cols-2 gap-12 items-center mb-24 reveal">
                <div class="lg:order-2">
                    <span class="inline-block bg-sky-100 text-sky-700 text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full mb-4">
                        <i class="fa-solid fa-chart-line"></i> Dashboard
                    </span>
                    <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900 leading-tight">
                        Sabes cuánto vendes <span class="gradient-text">en tiempo real</span>.
                    </h3>
                    <p class="text-slate-600 mt-4 leading-relaxed">
                        Métricas que importan: ventas del día, productos más pedidos, clientes recurrentes, conversión del bot, ANS de tiempo de despacho. Todo bonito, todo claro, exportable a Excel.
                    </p>
                    <ul class="space-y-2 mt-6 text-sm text-slate-700">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-sky-500 mt-1"></i> Gráficos hermosos con ApexCharts</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-sky-500 mt-1"></i> KPIs de hoy / semana / mes</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-sky-500 mt-1"></i> Ranking de top clientes y productos</li>
                    </ul>
                </div>

                <div class="lg:order-1 relative">
                    <div class="absolute -inset-4 rounded-3xl opacity-20 blur-2xl bg-sky-400"></div>
                    <div class="relative rounded-3xl bg-white border border-slate-200 shadow-2xl p-6">
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3">
                                <div class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Ventas hoy</div>
                                <div class="text-2xl font-black text-emerald-700 mt-1">$2.4M</div>
                            </div>
                            <div class="bg-sky-50 border border-sky-100 rounded-xl p-3">
                                <div class="text-[10px] uppercase font-bold text-sky-600 tracking-wider">Pedidos</div>
                                <div class="text-2xl font-black text-sky-700 mt-1">147</div>
                            </div>
                            <div class="bg-violet-50 border border-violet-100 rounded-xl p-3">
                                <div class="text-[10px] uppercase font-bold text-violet-600 tracking-wider">Tickets</div>
                                <div class="text-2xl font-black text-violet-700 mt-1">$16K</div>
                            </div>
                        </div>
                        {{-- Fake chart --}}
                        <svg viewBox="0 0 300 120" class="w-full">
                            <defs>
                                <linearGradient id="grad-chart" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="{{ $primario }}" stop-opacity=".6"/>
                                    <stop offset="100%" stop-color="{{ $primario }}" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="M0,90 L30,75 L60,80 L90,60 L120,65 L150,40 L180,50 L210,30 L240,35 L270,20 L300,25 L300,120 L0,120 Z" fill="url(#grad-chart)"/>
                            <path d="M0,90 L30,75 L60,80 L90,60 L120,65 L150,40 L180,50 L210,30 L240,35 L270,20 L300,25" fill="none" stroke="{{ $primario }}" stroke-width="2.5"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Feature 3: Despachos --}}
            <div class="grid lg:grid-cols-2 gap-12 items-center reveal">
                <div>
                    <span class="inline-block bg-amber-100 text-amber-700 text-[11px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-full mb-4">
                        <i class="fa-solid fa-motorcycle"></i> Despachos en vivo
                    </span>
                    <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900 leading-tight">
                        Sabes dónde está <span class="gradient-text">cada moto</span>, ahora.
                    </h3>
                    <p class="text-slate-600 mt-4 leading-relaxed">
                        Mapa en vivo con tus domiciliarios, ruta planeada vs ruta real, ETA al cliente, reasignación con un click. Tu cliente recibe link de seguimiento por WhatsApp. Cero llamadas de "¿dónde está mi pedido?".
                    </p>
                </div>

                <div class="relative">
                    <div class="absolute -inset-4 rounded-3xl opacity-20 blur-2xl bg-amber-400"></div>
                    <div class="relative rounded-3xl bg-white border border-slate-200 shadow-2xl overflow-hidden h-72">
                        {{-- Fake map --}}
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 via-emerald-100/30 to-slate-100"></div>
                        <svg class="absolute inset-0 w-full h-full opacity-60" viewBox="0 0 400 300">
                            <path d="M0,150 Q100,100 200,140 T400,130" stroke="#cbd5e1" stroke-width="3" fill="none"/>
                            <path d="M0,80 Q120,60 220,100 T400,90" stroke="#cbd5e1" stroke-width="2" fill="none"/>
                            <path d="M50,250 Q150,200 280,240 T400,230" stroke="#cbd5e1" stroke-width="2" fill="none"/>
                            <path d="M150,0 L160,80 L155,180 L170,250 L165,300" stroke="#cbd5e1" stroke-width="2" fill="none"/>
                        </svg>

                        {{-- Bike pin 1 --}}
                        <div class="absolute top-12 left-20">
                            <div class="relative h-10 w-10 flex items-center justify-center rounded-full text-white shadow-lg pulse-ring" style="background: var(--brand);">
                                <i class="fa-solid fa-motorcycle text-xs"></i>
                            </div>
                        </div>
                        {{-- Bike pin 2 --}}
                        <div class="absolute bottom-16 right-24">
                            <div class="relative h-10 w-10 flex items-center justify-center rounded-full text-white shadow-lg bg-sky-500">
                                <i class="fa-solid fa-motorcycle text-xs"></i>
                            </div>
                        </div>
                        {{-- Bike pin 3 --}}
                        <div class="absolute top-32 right-20">
                            <div class="relative h-10 w-10 flex items-center justify-center rounded-full text-white shadow-lg bg-amber-500">
                                <i class="fa-solid fa-motorcycle text-xs"></i>
                            </div>
                        </div>

                        {{-- Status overlay --}}
                        <div class="absolute top-3 left-3 bg-white/95 backdrop-blur rounded-xl px-3 py-2 shadow text-xs font-bold text-slate-700 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            3 motos en ruta
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ STATS animadas ═══╗ --}}
    <section class="py-20 relative overflow-hidden">
        <div class="absolute inset-0" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));"></div>
        <div class="absolute inset-0 dot-pattern opacity-20"></div>

        <div class="relative max-w-6xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6 text-center text-white">
            <div class="reveal">
                <div class="text-5xl md:text-6xl font-black">+1K</div>
                <div class="text-sm opacity-90 mt-2 font-bold">Pedidos al día</div>
            </div>
            <div class="reveal" style="transition-delay: .1s">
                <div class="text-5xl md:text-6xl font-black">15min</div>
                <div class="text-sm opacity-90 mt-2 font-bold">Setup inicial</div>
            </div>
            <div class="reveal" style="transition-delay: .2s">
                <div class="text-5xl md:text-6xl font-black">99.9%</div>
                <div class="text-sm opacity-90 mt-2 font-bold">Uptime</div>
            </div>
            <div class="reveal" style="transition-delay: .3s">
                <div class="text-5xl md:text-6xl font-black">24/7</div>
                <div class="text-sm opacity-90 mt-2 font-bold">Bot atiende</div>
            </div>
        </div>
    </section>

    {{-- ╔═══ TESTIMONIOS ═══╗ --}}
    <section class="py-24 md:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                <div class="inline-flex items-center gap-2 rounded-full bg-white border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 mb-4 shadow-sm">
                    <i class="fa-solid fa-quote-left"></i> Clientes
                </div>
                <h2 class="text-4xl md:text-6xl font-black text-slate-900 tracking-tight">
                    Lo que dicen <span class="gradient-text">nuestros clientes</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                @php
                    $testimonios = [
                        ['nombre' => 'Stiven Madrid', 'cargo' => 'Director TI · TecnoByte', 'iniciales' => 'SM', 'color' => 'emerald', 'rating' => 5, 'texto' => 'Pasamos de tomar pedidos manualmente a 1.000+ por día con el bot. La inversión se pagó en 2 semanas.'],
                        ['nombre' => 'Estefanía Builes', 'cargo' => 'Gerente · La Hacienda', 'iniciales' => 'EB', 'color' => 'violet', 'rating' => 5, 'texto' => 'El dashboard y los reportes me dieron visibilidad que no tenía. Ahora sé qué vendo, a quién y a qué hora. Top.'],
                        ['nombre' => 'Hernán Posada', 'cargo' => 'CEO · Guayacán Café', 'iniciales' => 'HP', 'color' => 'amber', 'rating' => 5, 'texto' => 'El bot habla como nosotros. Los clientes ni se dan cuenta que es IA. Y cuando algo se complica, mi equipo retoma sin fricción.'],
                    ];
                @endphp

                @foreach($testimonios as $i => $t)
                    <div class="reveal rounded-3xl bg-white border border-slate-200 p-6 hover:shadow-2xl hover:-translate-y-1 transition-all" style="transition-delay: {{ $i * 0.1 }}s">
                        <div class="flex gap-0.5 mb-4">
                            @for($s = 0; $s < $t['rating']; $s++)
                                <i class="fa-solid fa-star text-amber-400 text-sm"></i>
                            @endfor
                        </div>
                        <p class="text-slate-700 leading-relaxed">"{{ $t['texto'] }}"</p>
                        <div class="flex items-center gap-3 mt-6 pt-4 border-t border-slate-100">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-{{ $t['color'] }}-400 to-{{ $t['color'] }}-600 text-white font-extrabold flex items-center justify-center">
                                {{ $t['iniciales'] }}
                            </div>
                            <div>
                                <div class="font-extrabold text-slate-900">{{ $t['nombre'] }}</div>
                                <div class="text-xs text-slate-500">{{ $t['cargo'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ╔═══ CTA FINAL ═══╗ --}}
    <section id="cta" class="py-24 md:py-32">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative rounded-[2.5rem] p-12 md:p-20 text-center text-white overflow-hidden shadow-2xl" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 28px 28px;"></div>

                {{-- floating shapes --}}
                <div class="absolute top-10 left-10 h-20 w-20 rounded-2xl bg-white/10 backdrop-blur rotate-12 floating"></div>
                <div class="absolute bottom-10 right-10 h-16 w-16 rounded-full bg-white/10 backdrop-blur floating" style="animation-delay: -3s"></div>

                <div class="relative">
                    <h2 class="text-4xl md:text-6xl font-black tracking-tight">
                        Empieza a vender más<br>desde hoy mismo.
                    </h2>
                    <p class="text-lg opacity-90 mt-6 max-w-xl mx-auto">
                        Sin tarjeta de crédito. Sin contratos. Sin sorpresas. Solo resultados.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-4 mt-10">
                        <a href="https://admin.{{ $host }}/login" class="inline-flex items-center gap-2 bg-white text-slate-900 font-extrabold px-8 py-4 rounded-2xl shadow-2xl hover:scale-105 transition text-base">
                            <i class="fa-solid fa-rocket"></i> Empezar ahora
                        </a>
                        <button @click="videoOpen = true" class="inline-flex items-center gap-2 text-white font-bold px-6 py-4 rounded-2xl border-2 border-white/30 hover:bg-white/10 transition">
                            <i class="fa-solid fa-circle-play"></i> Ver demo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ FOOTER ═══╗ --}}
    <footer class="border-t border-slate-100 py-12 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-10">
                <div>
                    <div class="flex items-center gap-2">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-9 w-auto">
                        @endif
                        <span class="text-xl font-black text-slate-900">{{ $brand }}</span>
                    </div>
                    <p class="text-sm text-slate-500 mt-4 max-w-xs">
                        La plataforma que convierte tu WhatsApp en una máquina de ventas.
                    </p>
                </div>
                <div>
                    <h4 class="font-extrabold text-slate-900 text-sm mb-3">Producto</h4>
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li><a href="#funciona" class="hover:text-slate-900 transition">Cómo funciona</a></li>
                        <li><a href="#features" class="hover:text-slate-900 transition">Funciones</a></li>
                        <li><a href="#demo" class="hover:text-slate-900 transition">Ver demo</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-extrabold text-slate-900 text-sm mb-3">Empresa</h4>
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li><a href="https://admin.{{ $host }}/login" class="hover:text-slate-900 transition">Iniciar sesión</a></li>
                        <li><a href="#cta" class="hover:text-slate-900 transition">Empezar gratis</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-extrabold text-slate-900 text-sm mb-3">Síguenos</h4>
                    <div class="flex gap-3">
                        <a href="#" class="h-9 w-9 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:border-slate-400 transition text-slate-600">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <a href="#" class="h-9 w-9 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:border-slate-400 transition text-slate-600">
                            <i class="fa-brands fa-facebook"></i>
                        </a>
                        <a href="#" class="h-9 w-9 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:border-slate-400 transition text-slate-600">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                        <a href="#" class="h-9 w-9 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:border-slate-400 transition text-slate-600">
                            <i class="fa-brands fa-linkedin"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-200 pt-6 text-xs text-slate-500 flex flex-col md:flex-row items-center justify-between gap-3">
                <span>© {{ date('Y') }} {{ $brand }}. Todos los derechos reservados.</span>
                <div class="flex gap-5">
                    <a href="#" class="hover:text-slate-800 transition">Términos</a>
                    <a href="#" class="hover:text-slate-800 transition">Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    {{-- ╔═══ MODAL DE VIDEO ═══╗ --}}
    <div x-show="videoOpen" x-cloak
         @keydown.escape.window="videoOpen = false"
         class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm flex items-center justify-center p-4"
         @click.self="videoOpen = false">
        <button @click="videoOpen = false" class="absolute top-6 right-6 h-12 w-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
        <div class="relative w-full max-w-5xl video-modal-enter">
            <div class="aspect-video rounded-2xl overflow-hidden bg-slate-900 shadow-2xl">
                {{-- Placeholder hasta que tengas el video real. Reemplaza el src por tu video de YouTube/Vimeo. --}}
                <div class="w-full h-full flex flex-col items-center justify-center text-white text-center px-6">
                    <div class="h-20 w-20 rounded-full flex items-center justify-center mb-6" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">
                        <i class="fa-solid fa-video text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-extrabold">Video demo en producción</h3>
                    <p class="text-white/70 mt-3 max-w-md">
                        Pronto cargamos aquí un tour de 90 seg de toda la plataforma.
                        Por ahora, agenda una demo en vivo personalizada.
                    </p>
                    <a href="https://wa.me/573216499744?text=Hola%20Kivox%2C%20quiero%20una%20demo" target="_blank" class="mt-6 btn-primary inline-flex items-center gap-2 text-white font-extrabold px-6 py-3 rounded-2xl">
                        <i class="fa-brands fa-whatsapp"></i> Agendar demo por WhatsApp
                    </a>
                </div>
                {{-- Cuando tengas video:
                <iframe src="https://www.youtube.com/embed/VIDEO_ID?autoplay=1" class="w-full h-full" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                --}}
            </div>
        </div>
    </div>

    {{-- Reveal on scroll --}}
    <script>
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
    </script>

</body>
</html>
