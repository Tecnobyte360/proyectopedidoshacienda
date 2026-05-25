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
    <title>{{ $brand }} — Sales infrastructure for WhatsApp commerce</title>
    <meta name="description" content="The complete commerce platform for businesses selling through WhatsApp. AI sales agent, real-time dashboard, dispatch tracking, automated payments.">
    <meta property="og:title" content="{{ $brand }} — Sales infrastructure for WhatsApp">
    <link rel="icon" href="{{ $cfg->favicon_url ?? '/favicon.ico' }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: {{ $primario }};
            --brand-2: {{ $secundario }};
            --ink: #0a0a0a;
            --ink-soft: #1a1a1a;
            --paper: #fafaf9;
            --line: #e7e5e4;
        }
        * { -webkit-font-smoothing: antialiased; }
        html, body { background: var(--paper); color: var(--ink); }
        body { font-family: 'Inter', sans-serif; font-feature-settings: "ss01", "cv11"; letter-spacing: -0.011em; overflow-x: hidden; }

        .display { font-family: 'Inter', sans-serif; letter-spacing: -0.04em; line-height: 0.95; font-weight: 800; }
        .serif { font-family: 'Instrument Serif', serif; font-style: italic; letter-spacing: -0.02em; font-weight: 400; }

        .gradient-text { background: linear-gradient(135deg, var(--brand), var(--brand-2)); -webkit-background-clip: text; background-clip: text; color: transparent; }

        .btn-primary {
            background: var(--ink);
            color: white;
            transition: all .25s cubic-bezier(.4,0,.2,1);
            box-shadow: 0 1px 0 rgba(255,255,255,.1) inset, 0 1px 2px rgba(0,0,0,.4);
        }
        .btn-primary:hover { background: #000; transform: translateY(-1px); box-shadow: 0 10px 30px -10px rgba(0,0,0,.4); }

        .btn-ghost {
            background: white;
            color: var(--ink);
            border: 1px solid var(--line);
            transition: all .25s cubic-bezier(.4,0,.2,1);
        }
        .btn-ghost:hover { border-color: var(--ink); }

        /* ── Premium dot grid background ── */
        .dot-grid {
            background-image: radial-gradient(circle, rgba(10,10,10,.07) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* ── Glass card ── */
        .glass {
            background: rgba(255,255,255,.6);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255,255,255,.4);
        }
        .glass-dark {
            background: rgba(255,255,255,.05);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255,255,255,.1);
        }

        /* ── Section grid background ── */
        .grid-bg {
            background-image:
                linear-gradient(to right, rgba(10,10,10,.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(10,10,10,.04) 1px, transparent 1px);
            background-size: 80px 80px;
        }

        /* ── Reveal on scroll ── */
        .reveal { opacity: 0; transform: translateY(20px); transition: opacity 1s, transform 1s; }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        /* ── Marquee ── */
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee-track { display: flex; gap: 5rem; animation: marquee 40s linear infinite; width: max-content; align-items: center; }

        /* ── Chat bubble in ── */
        .bubble { opacity: 0; transform: translateY(8px); animation: bubble-in .6s ease forwards; }
        @keyframes bubble-in { to { opacity: 1; transform: translateY(0); } }

        /* ── Glow under hero card ── */
        .glow-bottom {
            position: absolute; inset: auto 0 -20% 0; height: 80%;
            background: radial-gradient(ellipse at center, {{ $primario }}30 0%, transparent 60%);
            pointer-events: none; z-index: -1;
        }

        /* ── Subtle pulse ── */
        @keyframes subtle-pulse {
            0%, 100% { opacity: 1; }
            50%      { opacity: .6; }
        }
        .subtle-pulse { animation: subtle-pulse 3s ease-in-out infinite; }

        /* ── Bento ── */
        .bento {
            background: white;
            border: 1px solid var(--line);
            border-radius: 1.5rem;
            overflow: hidden;
            transition: all .3s;
        }
        .bento:hover { border-color: var(--ink); transform: translateY(-2px); }

        /* ── Custom select numbers for steps ── */
        .step-num {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            font-size: 5rem;
            line-height: 1;
            background: linear-gradient(180deg, var(--brand) 0%, var(--brand-2) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* ── Underline animado ── */
        .link-underline { position: relative; }
        .link-underline::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -2px; height: 1px;
            background: currentColor; transform: scaleX(0); transform-origin: right; transition: transform .35s;
        }
        .link-underline:hover::after { transform: scaleX(1); transform-origin: left; }

        [x-cloak] { display: none !important; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { scrollbar-width: none; }
    </style>
</head>
<body x-data="{ scrolled: false, videoOpen: false, menuOpen: false }"
      x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 20)">

    {{-- ╔═══ NAV ═══╗ --}}
    <header class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
            :class="scrolled ? 'glass shadow-[0_1px_0_rgba(0,0,0,.06)] py-3' : 'bg-transparent py-5'">
        <div class="max-w-[1280px] mx-auto px-6 lg:px-8 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2.5">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-9 w-auto">
                @else
                    <div class="h-9 w-9 rounded-xl bg-[var(--ink)] text-white flex items-center justify-center font-black">K</div>
                @endif
                <span class="text-[17px] font-bold tracking-tight">{{ $brand }}</span>
            </a>

            <nav class="hidden lg:flex items-center gap-9 text-[14px] font-medium text-[var(--ink-soft)]">
                <a href="#producto" class="link-underline">Producto</a>
                <a href="#funciona" class="link-underline">Cómo funciona</a>
                <a href="#casos" class="link-underline">Casos de uso</a>
                <a href="#demo" class="link-underline">Demo</a>
            </nav>

            <div class="flex items-center gap-3">
                <a href="https://admin.{{ $host }}/login" class="hidden md:inline-flex text-[14px] font-medium text-[var(--ink-soft)] px-4 py-2 link-underline">
                    Iniciar sesión
                </a>
                <a href="#cta" class="btn-primary inline-flex items-center gap-1.5 text-[14px] font-semibold px-4 py-2.5 rounded-full">
                    Empezar gratis
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>
    </header>

    {{-- ╔═══ HERO ═══╗ --}}
    <section class="relative pt-36 lg:pt-44 pb-24 lg:pb-32 overflow-hidden">
        <div class="absolute inset-0 dot-grid opacity-60 pointer-events-none"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] rounded-full opacity-30 blur-3xl pointer-events-none" style="background: radial-gradient(circle, {{ $primario }}40 0%, transparent 60%);"></div>

        <div class="relative max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white border border-[var(--line)] shadow-sm text-[12px] font-medium text-[var(--ink-soft)] mb-8">
                    <span class="flex h-1.5 w-1.5 rounded-full" style="background: {{ $primario }};"></span>
                    Nuevo · Llamadas Meta WhatsApp Cloud API
                    <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </div>

                <h1 class="display text-[64px] sm:text-[88px] lg:text-[112px]">
                    Vende más,<br>
                    <span class="serif text-[var(--brand)]">conversando</span>.
                </h1>

                <p class="text-[18px] lg:text-[20px] text-[var(--ink-soft)] mt-8 max-w-2xl mx-auto leading-relaxed font-normal">
                    {{ $brand }} es la infraestructura de ventas para negocios que viven en WhatsApp.
                    IA que cierra, cobros automáticos, despachos en vivo y reportes que sí entiendes. Todo, una sola plataforma.
                </p>

                <div class="flex flex-wrap items-center justify-center gap-3 mt-10">
                    <a href="#cta" class="btn-primary inline-flex items-center gap-2 text-[15px] font-semibold px-6 py-3.5 rounded-full">
                        Empezar gratis <i class="fa-solid fa-arrow-right text-[11px]"></i>
                    </a>
                    <button @click="videoOpen = true" class="btn-ghost inline-flex items-center gap-2 text-[15px] font-semibold px-6 py-3.5 rounded-full">
                        <i class="fa-solid fa-circle-play text-[var(--brand)]"></i> Ver demo
                    </button>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-2 mt-10 text-[12px] text-[var(--ink-soft)]/70">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-[10px]" style="color: {{ $primario }};"></i> Sin tarjeta</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-[10px]" style="color: {{ $primario }};"></i> Setup en 15 minutos</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check text-[10px]" style="color: {{ $primario }};"></i> Cancela cuando quieras</span>
                </div>
            </div>

            {{-- HERO MOCKUP --}}
            <div class="relative mt-20 lg:mt-24">
                <div class="glow-bottom"></div>

                <div class="relative mx-auto max-w-[1100px]">
                    <div class="rounded-[1.5rem] bg-white border border-[var(--line)] shadow-[0_50px_120px_-30px_rgba(0,0,0,.25)] overflow-hidden">
                        {{-- Browser bar --}}
                        <div class="flex items-center gap-2 px-4 py-3 border-b border-[var(--line)] bg-[#fafaf9]">
                            <div class="flex gap-1.5">
                                <div class="h-3 w-3 rounded-full bg-[#ff5f57]"></div>
                                <div class="h-3 w-3 rounded-full bg-[#febc2e]"></div>
                                <div class="h-3 w-3 rounded-full bg-[#28c840]"></div>
                            </div>
                            <div class="flex-1 flex justify-center">
                                <div class="bg-white border border-[var(--line)] rounded-md px-4 py-1 text-[11px] text-[var(--ink-soft)]/60 font-mono">
                                    https://app.{{ $host }}
                                </div>
                            </div>
                        </div>

                        {{-- App preview: split chat + dashboard --}}
                        <div class="grid grid-cols-1 lg:grid-cols-[340px_1fr] min-h-[480px]">
                            {{-- Chat panel --}}
                            <div class="border-r border-[var(--line)] bg-white">
                                <div class="px-4 py-3 border-b border-[var(--line)] flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full text-white flex items-center justify-center font-bold text-sm" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">M</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13px] font-bold truncate">María Restrepo</div>
                                        <div class="text-[10px] text-[var(--ink-soft)]/60 flex items-center gap-1.5">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> en línea
                                        </div>
                                    </div>
                                    <i class="fa-brands fa-whatsapp text-emerald-500"></i>
                                </div>

                                <div class="p-4 space-y-2.5 bg-[#fafaf9]/40 h-[420px] overflow-hidden">
                                    <div class="flex bubble" style="animation-delay: .4s">
                                        <div class="bg-white rounded-2xl rounded-tl-md px-3 py-2 max-w-[85%] text-[12px] shadow-sm border border-[var(--line)]">
                                            Hola María, ¿pedimos lo de siempre?
                                            <div class="text-[9px] text-[var(--ink-soft)]/50 mt-0.5">10:32</div>
                                        </div>
                                    </div>
                                    <div class="flex justify-end bubble" style="animation-delay: .9s">
                                        <div class="text-white rounded-2xl rounded-tr-md px-3 py-2 max-w-[85%] text-[12px] shadow-sm" style="background: var(--brand);">
                                            Sí, y agrégame 2 cervezas
                                            <div class="text-[9px] text-white/70 mt-0.5 text-right">10:32 · <i class="fa-solid fa-check-double text-[8px]"></i></div>
                                        </div>
                                    </div>
                                    <div class="flex bubble" style="animation-delay: 1.5s">
                                        <div class="bg-white rounded-2xl rounded-tl-md px-3 py-2 max-w-[88%] text-[12px] shadow-sm border border-[var(--line)]">
                                            Listo, tu orden:<br>
                                            <span class="text-[var(--ink-soft)]/80">Pollo asado · $32.000<br>2× Cerveza · $14.000<br>Domicilio · $4.000</span><br>
                                            <strong>Total: $50.000</strong><br>
                                            <span class="text-emerald-600">Link de pago →</span>
                                            <div class="text-[9px] text-[var(--ink-soft)]/50 mt-0.5">10:33</div>
                                        </div>
                                    </div>
                                    <div class="flex bubble" style="animation-delay: 2.1s">
                                        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl rounded-tl-md px-3 py-2 max-w-[88%] text-[12px] shadow-sm">
                                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Pago confirmado · Pedido #1284
                                            <div class="text-[9px] text-[var(--ink-soft)]/50 mt-0.5">10:33</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Dashboard preview --}}
                            <div class="p-6 bg-[#fafaf9]/40">
                                <div class="flex items-center justify-between mb-5">
                                    <div>
                                        <div class="text-[10px] uppercase font-bold text-[var(--ink-soft)]/60 tracking-wider">Resumen</div>
                                        <div class="text-2xl font-bold">Hoy, 24 mayo</div>
                                    </div>
                                    <div class="flex items-center gap-1 text-[10px] text-[var(--ink-soft)]/60 bg-white border border-[var(--line)] rounded-md px-2 py-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 subtle-pulse"></span> En vivo
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-3 mb-5">
                                    <div class="bg-white border border-[var(--line)] rounded-xl p-3">
                                        <div class="text-[10px] uppercase font-bold text-[var(--ink-soft)]/60 tracking-wider">Ventas</div>
                                        <div class="text-xl font-bold mt-1">$2.4M</div>
                                        <div class="text-[10px] text-emerald-600 mt-0.5 font-semibold"><i class="fa-solid fa-arrow-up text-[8px]"></i> 18%</div>
                                    </div>
                                    <div class="bg-white border border-[var(--line)] rounded-xl p-3">
                                        <div class="text-[10px] uppercase font-bold text-[var(--ink-soft)]/60 tracking-wider">Pedidos</div>
                                        <div class="text-xl font-bold mt-1">147</div>
                                        <div class="text-[10px] text-emerald-600 mt-0.5 font-semibold"><i class="fa-solid fa-arrow-up text-[8px]"></i> 12%</div>
                                    </div>
                                    <div class="bg-white border border-[var(--line)] rounded-xl p-3">
                                        <div class="text-[10px] uppercase font-bold text-[var(--ink-soft)]/60 tracking-wider">Ticket</div>
                                        <div class="text-xl font-bold mt-1">$16K</div>
                                        <div class="text-[10px] text-emerald-600 mt-0.5 font-semibold"><i class="fa-solid fa-arrow-up text-[8px]"></i> 5%</div>
                                    </div>
                                </div>

                                <div class="bg-white border border-[var(--line)] rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="text-[11px] font-bold">Ventas últimas 24h</div>
                                        <div class="flex gap-1">
                                            <span class="text-[9px] px-1.5 py-0.5 rounded bg-[var(--ink)] text-white font-medium">24h</span>
                                            <span class="text-[9px] px-1.5 py-0.5 rounded text-[var(--ink-soft)]/60 font-medium">7d</span>
                                            <span class="text-[9px] px-1.5 py-0.5 rounded text-[var(--ink-soft)]/60 font-medium">30d</span>
                                        </div>
                                    </div>
                                    <svg viewBox="0 0 400 130" class="w-full">
                                        <defs>
                                            <linearGradient id="hero-chart" x1="0" x2="0" y1="0" y2="1">
                                                <stop offset="0%" stop-color="{{ $primario }}" stop-opacity=".4"/>
                                                <stop offset="100%" stop-color="{{ $primario }}" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <path d="M0,100 L40,85 L80,90 L120,70 L160,75 L200,55 L240,65 L280,40 L320,45 L360,25 L400,30 L400,130 L0,130 Z" fill="url(#hero-chart)"/>
                                        <path d="M0,100 L40,85 L80,90 L120,70 L160,75 L200,55 L240,65 L280,40 L320,45 L360,25 L400,30" fill="none" stroke="{{ $primario }}" stroke-width="2"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating badge --}}
                    <div class="absolute -top-4 right-6 lg:right-12 bg-white border border-[var(--line)] rounded-full px-3 py-1.5 shadow-lg flex items-center gap-2 text-[11px] font-semibold">
                        <span class="flex h-2 w-2 rounded-full bg-emerald-500 subtle-pulse"></span>
                        Atendiendo a 47 clientes ahora
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ MARQUEE de logos ═══╗ --}}
    <section class="py-12 border-y border-[var(--line)] bg-white">
        <div class="text-center text-[11px] uppercase font-semibold tracking-[0.2em] text-[var(--ink-soft)]/50 mb-7">
            Empresas que ya operan con {{ $brand }}
        </div>
        <div class="overflow-hidden">
            <div class="marquee-track text-[28px] lg:text-[36px] font-bold text-[var(--ink)]/15 whitespace-nowrap">
                <span>Alimentos La Hacienda</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>Importadora AA</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>Guayacán Café</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>Doblamos SAS</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>TecnoByte 360</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>Alimentos La Hacienda</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>Importadora AA</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>Guayacán Café</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>Doblamos SAS</span>
                <span style="color: var(--brand); opacity: .2">●</span>
                <span>TecnoByte 360</span>
            </div>
        </div>
    </section>

    {{-- ╔═══ BENTO de funciones (Apple style) ═══╗ --}}
    <section id="producto" class="py-28 lg:py-36 relative">
        <div class="max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="max-w-3xl mb-16 reveal">
                <div class="text-[12px] uppercase font-semibold tracking-[0.2em] text-[var(--brand)] mb-4">Producto</div>
                <h2 class="display text-[44px] lg:text-[64px]">
                    Una plataforma,<br>
                    <span class="serif">todo el ciclo</span> de venta.
                </h2>
                <p class="text-[17px] text-[var(--ink-soft)] mt-6 leading-relaxed max-w-2xl">
                    Desde el primer "hola" hasta la moto en la puerta. Sin saltar entre cinco apps,
                    sin perder datos, sin contratar tres herramientas.
                </p>
            </div>

            {{-- Bento grid 4 col --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Big card: Bot --}}
                <div class="bento lg:col-span-2 lg:row-span-2 relative p-8 lg:p-10 reveal min-h-[420px]">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold mb-5">
                        <i class="fa-solid fa-robot text-[10px]"></i> Inteligencia Artificial
                    </div>
                    <h3 class="display text-[36px] lg:text-[44px]">
                        Un bot que <span class="serif text-[var(--brand)]">cierra</span>,<br>
                        no que repite.
                    </h3>
                    <p class="text-[15px] text-[var(--ink-soft)] mt-4 max-w-md leading-relaxed">
                        Powered by Claude y GPT. Entiende intención, valida cobertura,
                        sugiere upsells y cierra venta. Aprende de cada conversación.
                    </p>

                    {{-- Mini chat anim --}}
                    <div class="absolute bottom-0 right-0 w-full max-w-md p-6 lg:p-8 space-y-2 opacity-100">
                        <div class="flex justify-end bubble" style="animation-delay: .5s">
                            <div class="bg-white border border-[var(--line)] rounded-2xl rounded-tr-md px-3 py-2 text-[12px] shadow-sm max-w-[80%]">
                                Quiero un pollo a la sancocho 😅
                            </div>
                        </div>
                        <div class="flex bubble" style="animation-delay: 1s">
                            <div class="text-white rounded-2xl rounded-tl-md px-3 py-2 text-[12px] shadow-sm max-w-[80%]" style="background: var(--brand);">
                                Claro 🍲 ¿prefieres sancocho o pollo asado?
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Despachos --}}
                <div class="bento relative p-7 reveal min-h-[200px]">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-50 text-amber-700 text-[11px] font-semibold mb-4">
                        <i class="fa-solid fa-motorcycle text-[10px]"></i> Despachos
                    </div>
                    <h3 class="display text-[22px]">Mapa en vivo de tus motos.</h3>
                    <div class="mt-5 relative h-24 rounded-xl bg-gradient-to-br from-emerald-50 via-emerald-100/40 to-slate-100 overflow-hidden border border-[var(--line)]">
                        <svg class="absolute inset-0 w-full h-full opacity-50" viewBox="0 0 200 100">
                            <path d="M0,60 Q60,40 120,55 T200,50" stroke="#cbd5e1" stroke-width="1.5" fill="none"/>
                            <path d="M0,30 Q80,20 140,40 T200,35" stroke="#cbd5e1" stroke-width="1" fill="none"/>
                        </svg>
                        <div class="absolute top-3 left-8 h-6 w-6 rounded-full bg-[var(--brand)] text-white flex items-center justify-center subtle-pulse"><i class="fa-solid fa-motorcycle text-[8px]"></i></div>
                        <div class="absolute bottom-3 right-12 h-6 w-6 rounded-full bg-sky-500 text-white flex items-center justify-center"><i class="fa-solid fa-motorcycle text-[8px]"></i></div>
                        <div class="absolute top-10 right-6 h-6 w-6 rounded-full bg-amber-500 text-white flex items-center justify-center"><i class="fa-solid fa-motorcycle text-[8px]"></i></div>
                    </div>
                </div>

                {{-- Cobros --}}
                <div class="bento p-7 reveal min-h-[200px]">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-50 text-violet-700 text-[11px] font-semibold mb-4">
                        <i class="fa-solid fa-credit-card text-[10px]"></i> Pagos
                    </div>
                    <h3 class="display text-[22px]">Cobros automáticos por link.</h3>
                    <div class="mt-5 bg-[var(--ink)] rounded-xl p-4 text-white">
                        <div class="text-[10px] uppercase font-bold tracking-wider opacity-60">Pedido #1284</div>
                        <div class="text-2xl font-bold mt-1">$50.000 COP</div>
                        <div class="mt-3 inline-flex items-center gap-1.5 text-[10px] bg-emerald-500 text-white px-2 py-1 rounded-full font-semibold">
                            <i class="fa-solid fa-check-circle text-[8px]"></i> Confirmado vía Wompi
                        </div>
                    </div>
                </div>

                {{-- Reportes --}}
                <div class="bento lg:col-span-2 relative p-7 reveal min-h-[220px] overflow-hidden">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-50 text-sky-700 text-[11px] font-semibold mb-4">
                        <i class="fa-solid fa-chart-line text-[10px]"></i> Reportes
                    </div>
                    <h3 class="display text-[28px]">
                        Sabes qué vendes, a quién y cuándo. <span class="serif text-[var(--ink-soft)]/60">en tiempo real.</span>
                    </h3>

                    <div class="mt-6 grid grid-cols-3 gap-2">
                        <div class="bg-[var(--paper)] border border-[var(--line)] rounded-lg p-3">
                            <div class="text-[9px] uppercase font-bold text-[var(--ink-soft)]/60 tracking-wider">MRR</div>
                            <div class="text-base font-bold mt-1">$48.2M</div>
                        </div>
                        <div class="bg-[var(--paper)] border border-[var(--line)] rounded-lg p-3">
                            <div class="text-[9px] uppercase font-bold text-[var(--ink-soft)]/60 tracking-wider">Activos</div>
                            <div class="text-base font-bold mt-1">312</div>
                        </div>
                        <div class="bg-[var(--paper)] border border-[var(--line)] rounded-lg p-3">
                            <div class="text-[9px] uppercase font-bold text-[var(--ink-soft)]/60 tracking-wider">Churn</div>
                            <div class="text-base font-bold mt-1">2.1%</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ╔═══ NUMBERS / Stats elegantes ═══╗ --}}
    <section class="py-24 lg:py-32 bg-[var(--ink)] text-white relative overflow-hidden">
        <div class="absolute inset-0 grid-bg opacity-[0.05]"></div>
        <div class="absolute top-0 left-1/4 w-[500px] h-[500px] rounded-full blur-3xl opacity-30" style="background: radial-gradient(circle, {{ $primario }} 0%, transparent 60%);"></div>

        <div class="relative max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="max-w-2xl mb-16 reveal">
                <div class="text-[12px] uppercase font-semibold tracking-[0.2em] text-[var(--brand)] mb-4">Resultados</div>
                <h2 class="display text-[44px] lg:text-[64px] text-white">
                    Las ventas <span class="serif">hablan</span>.
                </h2>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-white/10">
                <div class="bg-[var(--ink)] p-8 lg:p-10 reveal">
                    <div class="display text-[56px] lg:text-[72px] gradient-text">1K+</div>
                    <div class="text-[13px] text-white/60 mt-2 font-medium">Pedidos diarios</div>
                </div>
                <div class="bg-[var(--ink)] p-8 lg:p-10 reveal" style="transition-delay: .1s">
                    <div class="display text-[56px] lg:text-[72px] gradient-text">15<span class="text-[36px]">min</span></div>
                    <div class="text-[13px] text-white/60 mt-2 font-medium">Setup inicial</div>
                </div>
                <div class="bg-[var(--ink)] p-8 lg:p-10 reveal" style="transition-delay: .2s">
                    <div class="display text-[56px] lg:text-[72px] gradient-text">99.9<span class="text-[36px]">%</span></div>
                    <div class="text-[13px] text-white/60 mt-2 font-medium">Uptime SLA</div>
                </div>
                <div class="bg-[var(--ink)] p-8 lg:p-10 reveal" style="transition-delay: .3s">
                    <div class="display text-[56px] lg:text-[72px] gradient-text">24/7</div>
                    <div class="text-[13px] text-white/60 mt-2 font-medium">Atención del bot</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ CÓMO FUNCIONA ═══╗ --}}
    <section id="funciona" class="py-28 lg:py-36 relative">
        <div class="max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="max-w-3xl mb-20 reveal">
                <div class="text-[12px] uppercase font-semibold tracking-[0.2em] text-[var(--brand)] mb-4">Cómo funciona</div>
                <h2 class="display text-[44px] lg:text-[64px]">
                    Tres pasos.<br>
                    <span class="serif">Cero fricción.</span>
                </h2>
            </div>

            <div class="grid lg:grid-cols-3 gap-12 lg:gap-6">
                @php
                    $steps = [
                        ['num' => '01', 'titulo' => 'Conectas tu WhatsApp', 'desc' => 'Vinculas tu número actual escaneando un QR. Compatible con cualquier WhatsApp Business o personal. Sin migrar contactos, sin perder historial.'],
                        ['num' => '02', 'titulo' => 'Subes tu menú', 'desc' => 'Cargas productos con foto y precio. El bot aprende cómo los llamas, sus sinónimos, las combos populares. En 10 minutos ya conoce tu negocio.'],
                        ['num' => '03', 'titulo' => 'Empiezas a vender', 'desc' => 'El bot toma pedidos 24/7, los cobra con Wompi y los manda a despachos. Tu equipo solo confirma y entrega. Tú solo cuentas el dinero.'],
                    ];
                @endphp

                @foreach($steps as $i => $s)
                    <div class="reveal" style="transition-delay: {{ $i * 0.1 }}s">
                        <div class="step-num">{{ $s['num'] }}</div>
                        <div class="h-px w-full bg-[var(--line)] my-6"></div>
                        <h3 class="text-[24px] font-bold tracking-tight">{{ $s['titulo'] }}</h3>
                        <p class="text-[15px] text-[var(--ink-soft)] mt-3 leading-relaxed">{{ $s['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ╔═══ CASOS DE USO por industria ═══╗ --}}
    <section id="casos" class="py-28 lg:py-36 bg-white border-y border-[var(--line)]">
        <div class="max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="max-w-3xl mb-16 reveal">
                <div class="text-[12px] uppercase font-semibold tracking-[0.2em] text-[var(--brand)] mb-4">Para quién es</div>
                <h2 class="display text-[44px] lg:text-[64px]">
                    Negocios que <span class="serif">venden</span><br>conversando.
                </h2>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                    $industrias = [
                        ['icon' => 'fa-utensils', 'nombre' => 'Restaurantes', 'desc' => 'Toma pedidos, valida cobertura, asigna domiciliario y cobra. Sin app de delivery.'],
                        ['icon' => 'fa-shop', 'nombre' => 'Tiendas físicas', 'desc' => 'Convierte tu WhatsApp en e-commerce. Cliente pide, paga y le llega.'],
                        ['icon' => 'fa-warehouse', 'nombre' => 'Mayoristas', 'desc' => 'Pedidos B2B con integración a tu ERP (SQL Server, SAP, Siesa, World Office).'],
                        ['icon' => 'fa-gift', 'nombre' => 'Servicios', 'desc' => 'Reserva citas, cobra anticipos, recuerda al cliente. Todo automático.'],
                    ];
                @endphp

                @foreach($industrias as $i => $ind)
                    <div class="reveal p-6 rounded-2xl border border-[var(--line)] hover:bg-[var(--paper)] transition" style="transition-delay: {{ $i * 0.08 }}s">
                        <div class="h-12 w-12 rounded-xl bg-[var(--paper)] border border-[var(--line)] flex items-center justify-center text-[var(--brand)] mb-4">
                            <i class="fa-solid {{ $ind['icon'] }} text-lg"></i>
                        </div>
                        <h3 class="font-bold text-[17px]">{{ $ind['nombre'] }}</h3>
                        <p class="text-[13px] text-[var(--ink-soft)] mt-2 leading-relaxed">{{ $ind['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ╔═══ DEMO VIDEO ═══╗ --}}
    <section id="demo" class="py-28 lg:py-36">
        <div class="max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-12 reveal">
                <div class="text-[12px] uppercase font-semibold tracking-[0.2em] text-[var(--brand)] mb-4">Demo</div>
                <h2 class="display text-[44px] lg:text-[64px]">
                    Mira {{ $brand }} en <span class="serif">acción</span>.
                </h2>
                <p class="text-[16px] text-[var(--ink-soft)] mt-5">90 segundos. Sin tecnicismos. Directo al grano.</p>
            </div>

            <div class="reveal">
                <button @click="videoOpen = true" class="block w-full group cursor-pointer">
                    <div class="relative rounded-2xl overflow-hidden shadow-[0_30px_80px_-20px_rgba(0,0,0,.4)] aspect-video bg-[var(--ink)]">
                        <div class="absolute inset-0 grid-bg opacity-[0.05]"></div>
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[600px] rounded-full blur-3xl opacity-30" style="background: radial-gradient(circle, {{ $primario }} 0%, transparent 60%);"></div>

                        {{-- Centered play --}}
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="relative flex h-24 w-24 lg:h-32 lg:w-32 items-center justify-center rounded-full bg-white shadow-2xl group-hover:scale-110 transition">
                                <i class="fa-solid fa-play text-[var(--ink)] text-2xl lg:text-3xl ml-1.5"></i>
                            </div>
                        </div>

                        {{-- Caption --}}
                        <div class="absolute bottom-0 inset-x-0 p-8 bg-gradient-to-t from-black/80 to-transparent text-white">
                            <div class="flex items-end justify-between">
                                <div>
                                    <div class="text-[12px] uppercase font-bold tracking-wider opacity-70">Tour completo</div>
                                    <div class="text-xl font-bold mt-1">Bot · Dashboard · Despachos · Cobros</div>
                                </div>
                                <div class="text-[12px] font-mono opacity-70">01:30</div>
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </section>

    {{-- ╔═══ TESTIMONIO BIG ═══╗ --}}
    <section class="py-28 lg:py-36 bg-[var(--paper)] border-y border-[var(--line)]">
        <div class="max-w-[1024px] mx-auto px-6 lg:px-8 text-center reveal">
            <i class="fa-solid fa-quote-left text-5xl text-[var(--brand)]/30"></i>
            <blockquote class="serif text-[32px] lg:text-[48px] leading-tight text-[var(--ink)] mt-8">
                "Pasamos de tomar pedidos manualmente a <em>1.000+ al día</em>.
                El bot habla como nosotros, los clientes ni se dan cuenta. La inversión se pagó en 2 semanas."
            </blockquote>
            <div class="flex items-center justify-center gap-4 mt-10">
                <div class="h-14 w-14 rounded-full text-white font-bold flex items-center justify-center text-lg" style="background: linear-gradient(135deg, var(--brand), var(--brand-2));">SM</div>
                <div class="text-left">
                    <div class="font-bold">Stiven Madrid Londoño</div>
                    <div class="text-[13px] text-[var(--ink-soft)]/70">Director de TI · TecnoByte 360</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ CTA FINAL ═══╗ --}}
    <section id="cta" class="py-28 lg:py-36">
        <div class="max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="relative rounded-3xl bg-[var(--ink)] text-white p-12 lg:p-20 overflow-hidden">
                <div class="absolute inset-0 grid-bg opacity-[0.05]"></div>
                <div class="absolute top-0 right-0 w-[500px] h-[500px] rounded-full blur-3xl opacity-30" style="background: radial-gradient(circle, {{ $primario }} 0%, transparent 60%);"></div>

                <div class="relative max-w-2xl">
                    <h2 class="display text-[44px] lg:text-[80px]">
                        Empieza a vender<br>
                        <span class="serif">desde hoy</span>.
                    </h2>
                    <p class="text-[17px] text-white/70 mt-6 leading-relaxed">
                        Sin tarjeta de crédito. Sin contratos. Sin sorpresas.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 mt-10">
                        <a href="https://admin.{{ $host }}/login" class="inline-flex items-center gap-2 bg-white text-[var(--ink)] font-semibold text-[15px] px-6 py-3.5 rounded-full hover:scale-105 transition">
                            Empezar gratis <i class="fa-solid fa-arrow-right text-[11px]"></i>
                        </a>
                        <a href="https://wa.me/573216499744?text=Hola%20Kivox%2C%20quiero%20una%20demo" target="_blank" class="inline-flex items-center gap-2 text-white font-semibold text-[15px] px-6 py-3.5 rounded-full border border-white/20 hover:bg-white/10 transition">
                            <i class="fa-brands fa-whatsapp text-[var(--brand)]"></i> Hablar con ventas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔═══ FOOTER ═══╗ --}}
    <footer class="border-t border-[var(--line)] py-16 bg-white">
        <div class="max-w-[1280px] mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-12 gap-12 mb-12">
                <div class="lg:col-span-5">
                    <div class="flex items-center gap-2 mb-5">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-9 w-auto">
                        @else
                            <div class="h-9 w-9 rounded-xl bg-[var(--ink)] text-white flex items-center justify-center font-black">K</div>
                        @endif
                        <span class="text-lg font-bold">{{ $brand }}</span>
                    </div>
                    <p class="text-[14px] text-[var(--ink-soft)] max-w-sm leading-relaxed">
                        La infraestructura de ventas para negocios que viven en WhatsApp. Hecho con cariño en Colombia 🇨🇴.
                    </p>
                    <div class="flex gap-2 mt-6">
                        @foreach(['instagram','linkedin','whatsapp','facebook'] as $s)
                            <a href="#" class="h-9 w-9 flex items-center justify-center rounded-full border border-[var(--line)] hover:border-[var(--ink)] hover:bg-[var(--ink)] hover:text-white transition text-[var(--ink-soft)]">
                                <i class="fa-brands fa-{{ $s }} text-sm"></i>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="lg:col-span-7 grid grid-cols-2 md:grid-cols-3 gap-8">
                    <div>
                        <div class="text-[11px] uppercase font-bold tracking-[0.15em] text-[var(--ink-soft)]/60 mb-4">Producto</div>
                        <ul class="space-y-3 text-[14px] text-[var(--ink)]">
                            <li><a href="#producto" class="hover:underline">Funciones</a></li>
                            <li><a href="#funciona" class="hover:underline">Cómo funciona</a></li>
                            <li><a href="#casos" class="hover:underline">Casos de uso</a></li>
                            <li><a href="#demo" class="hover:underline">Ver demo</a></li>
                        </ul>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase font-bold tracking-[0.15em] text-[var(--ink-soft)]/60 mb-4">Empresa</div>
                        <ul class="space-y-3 text-[14px] text-[var(--ink)]">
                            <li><a href="https://admin.{{ $host }}/login" class="hover:underline">Iniciar sesión</a></li>
                            <li><a href="#cta" class="hover:underline">Empezar gratis</a></li>
                            <li><a href="https://wa.me/573216499744" target="_blank" class="hover:underline">Contactar ventas</a></li>
                        </ul>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase font-bold tracking-[0.15em] text-[var(--ink-soft)]/60 mb-4">Legal</div>
                        <ul class="space-y-3 text-[14px] text-[var(--ink)]">
                            <li><a href="#" class="hover:underline">Términos</a></li>
                            <li><a href="#" class="hover:underline">Privacidad</a></li>
                            <li><a href="#" class="hover:underline">Cookies</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="pt-8 border-t border-[var(--line)] flex flex-col md:flex-row items-center justify-between gap-4 text-[12px] text-[var(--ink-soft)]/60">
                <span>© {{ date('Y') }} {{ $brand }}. Todos los derechos reservados.</span>
                <span>Diseñado en Medellín, hecho en Colombia.</span>
            </div>
        </div>
    </footer>

    {{-- ╔═══ MODAL VIDEO ═══╗ --}}
    <div x-show="videoOpen" x-cloak x-transition.opacity
         @keydown.escape.window="videoOpen = false"
         class="fixed inset-0 z-[100] bg-[var(--ink)]/95 backdrop-blur flex items-center justify-center p-4"
         @click.self="videoOpen = false">
        <button @click="videoOpen = false" class="absolute top-6 right-6 h-11 w-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
        <div class="relative w-full max-w-5xl">
            <div class="aspect-video rounded-2xl overflow-hidden bg-[var(--ink-soft)] border border-white/10 shadow-2xl">
                <div class="w-full h-full flex flex-col items-center justify-center text-white text-center px-6">
                    <div class="h-20 w-20 rounded-full bg-white/10 flex items-center justify-center mb-6">
                        <i class="fa-solid fa-video text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold">Video demo en producción</h3>
                    <p class="text-white/60 mt-3 max-w-md text-[14px]">
                        Estamos grabando el tour final. Mientras tanto, agenda una demo personalizada con nuestro equipo.
                    </p>
                    <a href="https://wa.me/573216499744?text=Hola%20Kivox%2C%20quiero%20una%20demo" target="_blank" class="mt-7 inline-flex items-center gap-2 bg-white text-[var(--ink)] font-semibold px-6 py-3 rounded-full hover:scale-105 transition">
                        <i class="fa-brands fa-whatsapp text-emerald-600"></i> Agendar por WhatsApp
                    </a>
                </div>
                {{-- Cuando tengas el video, reemplazar con:
                <iframe src="https://www.youtube.com/embed/VIDEO_ID?autoplay=1&rel=0" class="w-full h-full" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                --}}
            </div>
        </div>
    </div>

    <script>
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.08 });
        document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
    </script>
</body>
</html>
