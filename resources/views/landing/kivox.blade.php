@php
    $cfg = null;
    try { $cfg = \App\Models\ConfiguracionPlataforma::actual(); } catch (\Throwable $e) {}
    $brand = $cfg->nombre ?: 'Kivox';
    $primario = $cfg->color_primario ?: '#10b981';
    $secundario = $cfg->color_secundario ?: '#059669';
    $logoUrl = $cfg->logo_url ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $brand }} · Vende más por WhatsApp con IA</title>
    <meta name="description" content="Plataforma SaaS multi-tenant para pedidos por WhatsApp con bot de IA, dashboard de ventas, gestión de domiciliarios y más.">
    <link rel="icon" href="{{ $cfg->favicon_url ?? '/favicon.ico' }}" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .gradient-text { background: linear-gradient(135deg, {{ $primario }}, {{ $secundario }}); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .btn-primary { background: linear-gradient(135deg, {{ $primario }}, {{ $secundario }}); transition: transform .15s, box-shadow .15s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -8px {{ $primario }}55; }
        .glow { background: radial-gradient(circle at 30% 50%, {{ $primario }}22, transparent 70%); }
    </style>
</head>
<body class="bg-white text-slate-800">

    {{-- NAV --}}
    <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-9 w-auto">
                @else
                    <div class="h-9 w-9 rounded-xl text-white flex items-center justify-center font-extrabold" style="background: linear-gradient(135deg, {{ $primario }}, {{ $secundario }});">K</div>
                @endif
                <span class="text-lg font-extrabold text-slate-800">{{ $brand }}</span>
            </a>
            <div class="hidden md:flex items-center gap-7 text-sm font-semibold text-slate-600">
                <a href="#features" class="hover:text-slate-900 transition">Funciones</a>
                <a href="#planes" class="hover:text-slate-900 transition">Planes</a>
                <a href="#faq" class="hover:text-slate-900 transition">FAQ</a>
                <a href="https://admin.{{ request()->getHost() }}/login" class="hover:text-slate-900 transition">Iniciar sesión</a>
            </div>
            <a href="#planes" class="btn-primary text-white font-bold text-sm px-5 py-2.5 rounded-xl shadow-md">
                Empezar gratis
            </a>
        </div>
    </nav>

    {{-- HERO --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 glow opacity-50 pointer-events-none"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-700 mb-6">
                        <i class="fa-solid fa-bolt"></i> Más de 1000 pedidos procesados al día
                    </div>
                    <h1 class="text-4xl md:text-6xl font-black text-slate-900 leading-tight tracking-tight">
                        Vende más por <span class="gradient-text">WhatsApp</span> con IA
                    </h1>
                    <p class="text-lg text-slate-600 mt-6 leading-relaxed max-w-xl">
                        {{ $brand }} es la plataforma todo-en-uno para que tu negocio reciba pedidos por WhatsApp,
                        atendidos por un bot inteligente que cobra, agenda, despacha y aprende de tus clientes.
                    </p>
                    <div class="flex flex-wrap items-center gap-4 mt-8">
                        <a href="#planes" class="btn-primary inline-flex items-center gap-2 text-white font-bold px-7 py-3.5 rounded-2xl shadow-lg">
                            <i class="fa-solid fa-rocket"></i> Empezar gratis
                        </a>
                        <a href="#features" class="inline-flex items-center gap-2 text-slate-700 font-bold px-5 py-3.5 rounded-2xl hover:bg-slate-100 transition">
                            <i class="fa-solid fa-circle-play"></i> Ver cómo funciona
                        </a>
                    </div>
                    <div class="flex items-center gap-6 mt-10 text-xs text-slate-500">
                        <span class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-500"></i> Sin tarjeta de crédito</span>
                        <span class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-500"></i> 14 días de prueba</span>
                        <span class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-500"></i> Cancela cuando quieras</span>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -inset-4 rounded-3xl opacity-30 blur-2xl" style="background: linear-gradient(135deg, {{ $primario }}, {{ $secundario }});"></div>
                    <div class="relative rounded-3xl bg-white border border-slate-200 shadow-2xl overflow-hidden">
                        {{-- Mockup de chat --}}
                        <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-3" style="background: linear-gradient(135deg, {{ $primario }}, {{ $secundario }});">
                            <div class="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center text-white text-sm font-bold">B</div>
                            <div class="text-white">
                                <div class="font-bold text-sm">Bot {{ $brand }}</div>
                                <div class="text-[10px] opacity-90 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span> En línea</div>
                            </div>
                            <div class="ml-auto text-white/80 text-xl"><i class="fa-brands fa-whatsapp"></i></div>
                        </div>
                        <div class="p-5 space-y-3 bg-slate-50 min-h-[340px]">
                            <div class="flex">
                                <div class="bg-white rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[80%] shadow-sm text-sm">
                                    Hola María, ¡qué bueno saludarte de nuevo! ¿Te pido lo de siempre? <span class="text-[10px] text-slate-400 ml-1">10:32</span>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <div class="text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%] shadow-sm text-sm" style="background: {{ $primario }};">
                                    Sí porfa, y agrégame 2 cervezas 🍻 <span class="text-[10px] text-white/70 ml-1">10:32 <i class="fa-solid fa-check-double"></i></span>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="bg-white rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%] shadow-sm text-sm">
                                    Listo, te resumo:<br>
                                    • 1x Pollo asado · $32.000<br>
                                    • 2x Cerveza · $14.000<br>
                                    • Domicilio: $4.000<br>
                                    <span class="font-bold">Total: $50.000</span><br>
                                    ¿Confirmas? <span class="text-[10px] text-slate-400 ml-1">10:33</span>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <div class="text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%] shadow-sm text-sm" style="background: {{ $primario }};">
                                    Sí confirmo <span class="text-[10px] text-white/70 ml-1">10:33 <i class="fa-solid fa-check-double"></i></span>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%] shadow-sm text-sm">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i> Pedido <strong>#1284</strong> creado. Te llega en 30-40 min. ¡Gracias! 🚀
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- KPIs SOCIAL PROOF --}}
    <section class="border-y border-slate-100 bg-slate-50/60 py-10">
        <div class="max-w-6xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div><div class="text-3xl font-black gradient-text">+1000</div><div class="text-xs text-slate-500 font-semibold mt-1">Pedidos/día</div></div>
            <div><div class="text-3xl font-black gradient-text">15min</div><div class="text-xs text-slate-500 font-semibold mt-1">Setup inicial</div></div>
            <div><div class="text-3xl font-black gradient-text">99.9%</div><div class="text-xs text-slate-500 font-semibold mt-1">Uptime</div></div>
            <div><div class="text-3xl font-black gradient-text">24/7</div><div class="text-xs text-slate-500 font-semibold mt-1">Bot atiende</div></div>
        </div>
    </section>

    {{-- FEATURES --}}
    <section id="features" class="py-20 md:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 mb-4">
                    <i class="fa-solid fa-layer-group"></i> Funciones
                </div>
                <h2 class="text-3xl md:text-5xl font-black text-slate-900 tracking-tight">
                    Todo lo que necesitas para <span class="gradient-text">vender más</span>
                </h2>
                <p class="text-lg text-slate-600 mt-5">
                    {{ $brand }} reúne bot IA, cobros, despachos y reportes en una sola plataforma.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @php
                    $features = [
                        ['icon' => 'fa-robot',          'color' => 'emerald', 'titulo' => 'Bot con IA',                     'desc' => 'Atiende a tus clientes 24/7, entiende lo que piden y arma pedidos con cobertura validada.'],
                        ['icon' => 'fa-cart-shopping', 'color' => 'sky',     'titulo' => 'Pedidos automatizados',          'desc' => 'El bot toma el pedido, calcula domicilio, valida cobertura y crea la orden sin intervención.'],
                        ['icon' => 'fa-credit-card',   'color' => 'violet',  'titulo' => 'Cobros con Wompi',               'desc' => 'Link de pago automático por WhatsApp. Confirmación al instante por webhook.'],
                        ['icon' => 'fa-motorcycle',    'color' => 'amber',   'titulo' => 'Despachos en vivo',              'desc' => 'Mapa con tus domiciliarios en tiempo real. Rutas, asignación inteligente y ETA.'],
                        ['icon' => 'fa-chart-line',    'color' => 'rose',    'titulo' => 'Dashboard de ventas',            'desc' => 'KPIs, top clientes, productos estrella y reportes que te ayudan a vender más.'],
                        ['icon' => 'fa-bullhorn',      'color' => 'orange',  'titulo' => 'Campañas masivas',                'desc' => 'Envía promociones a miles por WhatsApp con plantillas Meta aprobadas.'],
                    ];
                @endphp

                @foreach($features as $f)
                    <div class="group rounded-2xl bg-white border border-slate-200 p-6 hover:shadow-xl hover:border-{{ $f['color'] }}-300 transition">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-{{ $f['color'] }}-100 text-{{ $f['color'] }}-600 mb-4 group-hover:scale-110 transition">
                            <i class="fa-solid {{ $f['icon'] }} text-lg"></i>
                        </div>
                        <h3 class="font-extrabold text-slate-900 text-lg">{{ $f['titulo'] }}</h3>
                        <p class="text-sm text-slate-600 mt-2 leading-relaxed">{{ $f['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- PLANES --}}
    <section id="planes" class="py-20 md:py-28 bg-gradient-to-br from-slate-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-14">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 mb-4">
                    <i class="fa-solid fa-money-check-dollar"></i> Planes
                </div>
                <h2 class="text-3xl md:text-5xl font-black text-slate-900 tracking-tight">
                    Precio justo, <span class="gradient-text">cero sorpresas</span>
                </h2>
                <p class="text-lg text-slate-600 mt-5">14 días gratis · cancela cuando quieras.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                {{-- Básico --}}
                <div class="rounded-3xl bg-white border-2 border-slate-200 p-8 hover:shadow-xl transition">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Básico</div>
                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="text-5xl font-black text-slate-900">$199K</span>
                        <span class="text-sm text-slate-500">/mes</span>
                    </div>
                    <p class="text-sm text-slate-600 mt-4">Ideal para empezar a recibir pedidos por WhatsApp.</p>
                    <ul class="space-y-3 mt-6 text-sm text-slate-700">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Hasta 500 pedidos/mes</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> 1 número de WhatsApp</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Bot IA + dashboard</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Soporte por email</li>
                    </ul>
                    <a href="https://admin.{{ request()->getHost() }}/login" class="block text-center mt-8 py-3 rounded-xl border-2 border-slate-200 hover:border-slate-300 font-bold text-slate-700 transition">
                        Empezar prueba
                    </a>
                </div>

                {{-- Pro (destacado) --}}
                <div class="rounded-3xl p-8 hover:shadow-2xl transition relative overflow-hidden text-white" style="background: linear-gradient(135deg, {{ $primario }}, {{ $secundario }});">
                    <div class="absolute top-4 right-4 bg-white/20 backdrop-blur rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider">Más popular</div>
                    <div class="text-xs font-bold uppercase tracking-wider opacity-90">Pro</div>
                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="text-5xl font-black">$399K</span>
                        <span class="text-sm opacity-80">/mes</span>
                    </div>
                    <p class="text-sm opacity-90 mt-4">Para negocios con volumen y múltiples sedes.</p>
                    <ul class="space-y-3 mt-6 text-sm">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1"></i> Hasta 5.000 pedidos/mes</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1"></i> 3 números de WhatsApp</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1"></i> Despachos + mapa en vivo</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1"></i> Campañas masivas</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check mt-1"></i> Soporte prioritario</li>
                    </ul>
                    <a href="https://admin.{{ request()->getHost() }}/login" class="block text-center mt-8 py-3 rounded-xl bg-white text-slate-900 hover:bg-slate-100 font-extrabold transition">
                        Empezar prueba
                    </a>
                </div>

                {{-- Empresa --}}
                <div class="rounded-3xl bg-white border-2 border-slate-200 p-8 hover:shadow-xl transition">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Empresa</div>
                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="text-5xl font-black text-slate-900">$599K</span>
                        <span class="text-sm text-slate-500">/mes</span>
                    </div>
                    <p class="text-sm text-slate-600 mt-4">Volumen ilimitado, ERP integrado, dominio propio.</p>
                    <ul class="space-y-3 mt-6 text-sm text-slate-700">
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Pedidos ilimitados</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Integración ERP (SQL Server, etc.)</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Subdominio propio + branding</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> SLA 99.9% · 24/7</li>
                        <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Onboarding 1:1</li>
                    </ul>
                    <a href="https://admin.{{ request()->getHost() }}/login" class="block text-center mt-8 py-3 rounded-xl border-2 border-slate-200 hover:border-slate-300 font-bold text-slate-700 transition">
                        Contactar ventas
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="py-20 md:py-28">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-5xl font-black text-slate-900 tracking-tight">Preguntas <span class="gradient-text">frecuentes</span></h2>
            </div>
            <div class="space-y-3">
                @php
                    $faqs = [
                        ['q' => '¿Necesito tener WhatsApp Business?', 'a' => 'Sí. Empiezas con tu número actual de WhatsApp y nosotros lo conectamos a la plataforma. Si vas a escalar, te ayudamos a migrar a Meta WhatsApp Cloud API.'],
                        ['q' => '¿Cuánto demora el setup?', 'a' => '15-30 minutos. Creas tu cuenta, conectas tu WhatsApp, subes tus productos y listo. Te acompañamos en cada paso.'],
                        ['q' => '¿El bot reemplaza a mis vendedores?', 'a' => 'No. Atiende lo repetitivo (saludos, menú, pedidos básicos) y deriva a tu equipo lo importante. Ellos pueden retomar la conversación cuando quieran.'],
                        ['q' => '¿Cómo cobro a mis clientes?', 'a' => 'Integramos Wompi (Colombia) para cobros automáticos por link. El bot envía el link, el cliente paga, y la plataforma confirma sola.'],
                        ['q' => '¿Puedo cancelar?', 'a' => 'Sí, en cualquier momento desde tu panel. Sin penalidades, sin letra chica.'],
                    ];
                @endphp

                @foreach($faqs as $f)
                    <details class="group rounded-2xl bg-white border border-slate-200 p-5 hover:border-slate-300 transition">
                        <summary class="flex items-center justify-between cursor-pointer font-bold text-slate-800">
                            {{ $f['q'] }}
                            <i class="fa-solid fa-plus group-open:rotate-45 transition text-slate-400"></i>
                        </summary>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed">{{ $f['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA FINAL --}}
    <section class="py-20">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl p-12 md:p-16 text-center text-white relative overflow-hidden" style="background: linear-gradient(135deg, {{ $primario }}, {{ $secundario }});">
                <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 28px 28px;"></div>
                <div class="relative">
                    <h2 class="text-3xl md:text-5xl font-black tracking-tight">¿Listo para vender más?</h2>
                    <p class="text-lg opacity-90 mt-4 max-w-xl mx-auto">Empieza gratis hoy. Sin tarjeta de crédito. 14 días para probar todo.</p>
                    <a href="https://admin.{{ request()->getHost() }}/login" class="inline-flex items-center gap-2 mt-8 bg-white text-slate-900 font-extrabold px-8 py-4 rounded-2xl shadow-2xl hover:scale-105 transition">
                        <i class="fa-solid fa-rocket"></i> Empezar prueba gratis
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="border-t border-slate-100 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-slate-500">
            <div class="flex items-center gap-2">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" class="h-7 w-auto opacity-70">
                @endif
                <span>© {{ date('Y') }} {{ $brand }}. Todos los derechos reservados.</span>
            </div>
            <div class="flex items-center gap-5">
                <a href="https://admin.{{ request()->getHost() }}/login" class="hover:text-slate-800 transition">Acceder</a>
                <a href="#" class="hover:text-slate-800 transition">Términos</a>
                <a href="#" class="hover:text-slate-800 transition">Privacidad</a>
            </div>
        </div>
    </footer>

</body>
</html>
