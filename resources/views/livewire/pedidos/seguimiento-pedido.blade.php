<div
    id="seguimiento-pedido-root"
    data-codigo-seguimiento="{{ $pedido->codigo_seguimiento }}"
    class="tracking-page min-h-screen overflow-x-hidden bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.10),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(59,130,246,0.08),_transparent_25%),linear-gradient(180deg,_#f8fafc_0%,_#eef2f7_100%)] text-slate-800"
>
    <div id="seguimiento-estado-flash" class="status-flash hidden opacity-0">
        <div class="status-flash__icon">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <div>
            <p class="status-flash__title">Actualización en tiempo real</p>
            <p id="seguimiento-estado-flash-text" class="status-flash__text">El estado de tu pedido cambió.</p>
        </div>
    </div>

    @php
        $estados = [
            'nuevo' => [
                'label' => 'Pedido recibido',
                'icon' => 'fa-solid fa-bell',
                'color' => 'blue',
            ],
            'en_preparacion' => [
                'label' => 'En preparación',
                'icon' => 'fa-solid fa-utensils',
                'color' => 'amber',
            ],
            'repartidor_en_camino' => [
                'label' => 'Repartidor en camino',
                'icon' => 'fa-solid fa-motorcycle',
                'color' => 'violet',
            ],
            'entregado' => [
                'label' => 'Entregado',
                'icon' => 'fa-solid fa-circle-check',
                'color' => 'emerald',
            ],
            'cancelado' => [
                'label' => 'Cancelado',
                'icon' => 'fa-solid fa-ban',
                'color' => 'rose',
            ],
        ];

        $estadoActual = $pedido->estado ?? 'nuevo';

        $metaEstado = $estados[$estadoActual] ?? [
            'label' => ucfirst(str_replace('_', ' ', $estadoActual)),
            'icon' => 'fa-solid fa-circle',
            'color' => 'blue',
        ];

        $ordenEstados = ['nuevo', 'en_preparacion', 'repartidor_en_camino', 'entregado'];
        $indiceActual = array_search($estadoActual, $ordenEstados);

        $badgeEstadoClasses = match ($metaEstado['color']) {
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
            default => 'bg-sky-50 text-sky-700 ring-sky-200',
        };
    @endphp

    <div class="relative z-10 mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-10">

        {{-- HERO --}}
        <section class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/80 shadow-[0_30px_80px_rgba(15,23,42,0.08)] backdrop-blur-xl">
            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(16,185,129,0.08),transparent_40%,rgba(59,130,246,0.06))]"></div>
            <div class="absolute -top-20 -right-20 h-60 w-60 rounded-full bg-emerald-400/10 blur-3xl"></div>
            <div class="absolute -bottom-20 -left-20 h-60 w-60 rounded-full bg-sky-400/10 blur-3xl"></div>

            <div class="relative grid gap-6 px-6 py-7 md:grid-cols-[1fr_auto] md:px-8 md:py-8 xl:px-10">
                <div class="min-w-0">
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-white shadow-lg">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Seguimiento en tiempo real
                        </span>

                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold ring-1 {{ $badgeEstadoClasses }}">
                            <i class="{{ $metaEstado['icon'] }}"></i>
                            {{ $metaEstado['label'] }}
                        </span>
                    </div>

                    <h1 class="text-3xl font-black tracking-tight text-slate-900 md:text-5xl">
                        Hola, <span class="bg-gradient-to-r from-emerald-600 to-sky-600 bg-clip-text text-transparent">
                            {{ $pedido->cliente_nombre ?? 'Cliente' }}
                        </span> 👋
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 md:text-base">
                        {{ $metaEstado['label'] === 'Cancelado'
                            ? 'Tu pedido fue cancelado. Aquí puedes revisar el detalle completo y el historial de eventos registrados.'
                            : 'Estamos gestionando tu pedido y aquí podrás ver cada cambio de estado, el historial y el detalle de productos de forma clara y elegante.' }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <div class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div class="leading-tight">
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Pedido</div>
                                <div>#{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</div>
                            </div>
                        </div>

                        <div class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                <i class="fa-solid fa-mobile-screen-button"></i>
                            </div>
                            <div class="leading-tight">
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Canal</div>
                                <div>{{ ucfirst($pedido->canal ?? 'Whatsapp') }}</div>
                            </div>
                        </div>

                        <div class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600">
                                <i class="fa-regular fa-calendar"></i>
                            </div>
                            <div class="leading-tight">
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Fecha</div>
                                <div>{{ optional($pedido->fecha_pedido ?? $pedido->created_at)->format('d/m/Y') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-start md:justify-end">
                    <div class="min-w-[260px] rounded-[1.75rem] border border-slate-200 bg-slate-950 px-6 py-5 text-white shadow-[0_25px_60px_rgba(15,23,42,0.25)]">
                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-white/50">Total del pedido</p>
                        <p class="mt-2 text-4xl font-black tracking-tight">
                            ${{ number_format($pedido->total, 0, ',', '.') }}
                        </p>

                        <div class="mt-5 space-y-3 text-sm text-white/75">
                            <div class="flex items-center justify-between border-b border-white/10 pb-2">
                                <span>Estado actual</span>
                                <span class="font-semibold text-white">{{ $metaEstado['label'] }}</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-white/10 pb-2">
                                <span>Código seguimiento</span>
                                <span class="font-semibold text-white">{{ $pedido->codigo_seguimiento }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Canal</span>
                                <span class="font-semibold text-white">{{ ucfirst($pedido->canal ?? 'whatsapp') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- PROGRESO --}}
        @if ($estadoActual !== 'cancelado')
            <section class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-950 shadow-[0_25px_70px_rgba(15,23,42,0.16)]">
                <div class="border-b border-white/10 px-6 py-5 md:px-8">
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-white/45">Estado del pedido</p>
                    <h2 class="mt-2 text-xl font-bold text-white md:text-2xl">Progreso en tiempo real</h2>
                    <p class="mt-1 text-sm text-white/55">Visualiza en qué etapa va tu pedido.</p>
                </div>

                <div class="px-4 py-6 sm:px-6 md:px-8">
                    <div class="steps-track">
                        @foreach ($ordenEstados as $i => $estadoPaso)
                            @php
                                $paso = $estados[$estadoPaso];
                                $completado = $indiceActual !== false && $i < $indiceActual;
                                $activo = $estadoPaso === $estadoActual;
                            @endphp

                            <div class="step-item {{ $completado ? 'completed' : '' }} {{ $activo ? 'active' : '' }}">
                                <div class="step-line-bg"></div>

                                <div class="step-icon-wrap">
                                    <i class="{{ $paso['icon'] }}"></i>
                                </div>

                                <div class="mt-4 text-center">
                                    <p class="step-label">{{ $paso['label'] }}</p>
                                    <span class="step-mini">
                                        {{ $activo ? 'Actual' : ($completado ? 'Completado' : 'Pendiente') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- GRID --}}
        <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            {{-- HISTORIAL --}}
            <div class="overflow-hidden rounded-[2rem] border border-white/70 bg-white/85 shadow-[0_25px_70px_rgba(15,23,42,0.07)] backdrop-blur-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 md:px-8">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Trazabilidad</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Historial del pedido</h2>
                    </div>

                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                        {{ $historial->count() }} eventos
                    </span>
                </div>

                <div class="px-6 py-6 md:px-8">
                    <div class="timeline">
                        @forelse($historial->sortByDesc('fecha_evento') as $item)
                            @php
                                $color = $estados[$item->estado_nuevo]['color'] ?? 'blue';
                                $icon = $estados[$item->estado_nuevo]['icon'] ?? 'fa-solid fa-circle';
                                $titulo = $item->titulo ?: ($estados[$item->estado_nuevo]['label'] ?? ucfirst(str_replace('_', ' ', $item->estado_nuevo)));
                            @endphp

                            <div class="timeline-item">
                                <div class="timeline-left">
                                    <div class="timeline-dot {{ $color }}">
                                        <i class="{{ $icon }}"></i>
                                    </div>
                                    <div class="timeline-line"></div>
                                </div>

                                <div class="timeline-card">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="tl-title">{{ $titulo }}</p>
                                            @if ($item->descripcion)
                                                <p class="tl-desc">{{ $item->descripcion }}</p>
                                            @endif
                                        </div>

                                        <span class="tl-badge">
                                            <i class="fa-regular fa-clock"></i>
                                            {{ optional($item->fecha_evento)->format('d/m/Y · h:i a') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center">
                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-sm text-slate-400">
                                    <i class="fa-regular fa-clock text-2xl"></i>
                                </div>
                                <h3 class="mt-4 text-lg font-bold text-slate-800">Sin movimientos</h3>
                                <p class="mt-2 text-sm text-slate-500">
                                    Todavía no hay actualizaciones registradas para este pedido.
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- DERECHA --}}
            <div class="space-y-6">
                {{-- PRODUCTOS --}}
                <div class="overflow-hidden rounded-[2rem] border border-white/70 bg-white/85 shadow-[0_25px_70px_rgba(15,23,42,0.07)] backdrop-blur-xl">
                    <div class="border-b border-slate-100 px-6 py-5">
                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Detalle</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Productos del pedido</h2>
                    </div>

                    <div class="space-y-3 p-5">
                        @forelse($pedido->detalles as $detalle)
                            <div class="product-row">
                                <div class="product-icon">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-slate-900">
                                        {{ $detalle->producto }}
                                    </p>
                                    <p class="mt-1 text-xs font-medium text-slate-500">
                                        {{ rtrim(rtrim(number_format($detalle->cantidad, 3, '.', ''), '0'), '.') }}
                                        {{ $detalle->unidad }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-sm font-black tracking-tight text-slate-900">
                                        ${{ number_format($detalle->subtotal, 0, ',', '.') }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-6 text-sm text-slate-500">
                                No hay productos registrados.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- NOTAS --}}
                @if ($pedido->notas)
                    <div class="overflow-hidden rounded-[2rem] border border-amber-200/70 bg-gradient-to-br from-amber-50 to-orange-50 shadow-[0_20px_50px_rgba(251,191,36,0.10)]">
                        <div class="flex items-center gap-3 border-b border-amber-100 px-6 py-5">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-amber-500 shadow-sm">
                                <i class="fa-solid fa-note-sticky"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-amber-500">Observaciones</p>
                                <h2 class="text-lg font-bold text-slate-900">Notas del pedido</h2>
                            </div>
                        </div>

                        <div class="px-6 py-5">
                            <p class="text-sm leading-7 text-slate-700">{{ $pedido->notas }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>

    @push('styles')
        <style>
            *,:before,:after{box-sizing:border-box}

            .tracking-page{
                font-family:'Inter',sans-serif;
                position:relative;
            }

            .tracking-page::before{
                content:'';
                position:fixed;
                inset:0;
                pointer-events:none;
                z-index:0;
                opacity:.18;
                background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='1'/%3E%3C/svg%3E");
            }

            .status-flash{
                position:fixed;
                top:20px;
                right:20px;
                z-index:80;
                display:flex;
                align-items:center;
                gap:12px;
                min-width:290px;
                max-width:380px;
                padding:14px 16px;
                border-radius:20px;
                border:1px solid rgba(16,185,129,.18);
                background:rgba(255,255,255,.95);
                box-shadow:0 25px 60px rgba(15,23,42,.14);
                backdrop-filter:blur(18px);
                transition:opacity .35s ease, transform .35s ease;
                transform:translateY(0);
            }

            .status-flash.hidden{display:none}
            .status-flash.opacity-0{opacity:0;transform:translateY(-10px)}
            .status-flash.opacity-100{opacity:1;transform:translateY(0)}

            .status-flash__icon{
                width:44px;
                height:44px;
                border-radius:16px;
                display:flex;
                align-items:center;
                justify-content:center;
                background:rgba(16,185,129,.12);
                color:#10b981;
                flex-shrink:0;
            }

            .status-flash__title{
                margin:0;
                font-size:11px;
                font-weight:900;
                letter-spacing:.16em;
                text-transform:uppercase;
                color:#10b981;
            }

            .status-flash__text{
                margin:4px 0 0;
                font-size:13px;
                color:#0f172a;
                font-weight:600;
            }

            .steps-track{
                display:grid;
                grid-template-columns:repeat(4,minmax(0,1fr));
                gap:0;
            }

            .step-item{
                position:relative;
                display:flex;
                flex-direction:column;
                align-items:center;
                padding:0 8px;
            }

            .step-line-bg{
                position:absolute;
                top:21px;
                left:50%;
                width:100%;
                height:2px;
                background:rgba(255,255,255,.12);
                z-index:0;
            }

            .step-item:last-child .step-line-bg{display:none}
            .step-item.completed .step-line-bg{
                background:linear-gradient(90deg,#10b981,rgba(16,185,129,.15));
            }

            .step-icon-wrap{
                position:relative;
                z-index:1;
                width:44px;
                height:44px;
                border-radius:16px;
                display:flex;
                align-items:center;
                justify-content:center;
                border:1px solid rgba(255,255,255,.14);
                background:rgba(255,255,255,.05);
                color:rgba(255,255,255,.55);
                font-size:15px;
                transition:all .35s ease;
            }

            .step-item.completed .step-icon-wrap{
                background:rgba(16,185,129,.18);
                border-color:rgba(16,185,129,.38);
                color:#34d399;
            }

            .step-item.active .step-icon-wrap{
                background:#10b981;
                border-color:#10b981;
                color:#052e26;
                transform:scale(1.08);
                box-shadow:0 0 0 8px rgba(16,185,129,.10),0 0 28px rgba(16,185,129,.35);
            }

            .step-label{
                font-size:13px;
                font-weight:700;
                color:rgba(255,255,255,.88);
                line-height:1.4;
            }

            .step-mini{
                display:inline-block;
                margin-top:6px;
                font-size:10px;
                font-weight:800;
                letter-spacing:.14em;
                text-transform:uppercase;
                color:rgba(255,255,255,.40);
            }

            .step-item.active .step-mini{color:#6ee7b7}
            .step-item.completed .step-mini{color:#34d399}

            .timeline{
                display:flex;
                flex-direction:column;
                gap:18px;
            }

            .timeline-item{
                display:flex;
                gap:14px;
                align-items:flex-start;
            }

            .timeline-left{
                display:flex;
                flex-direction:column;
                align-items:center;
                flex-shrink:0;
            }

            .timeline-dot{
                width:34px;
                height:34px;
                border-radius:12px;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:12px;
                border:1px solid;
                box-shadow:0 8px 18px rgba(15,23,42,.05);
            }

            .timeline-dot.blue{background:rgba(59,130,246,.10);border-color:rgba(59,130,246,.20);color:#2563eb}
            .timeline-dot.amber{background:rgba(245,158,11,.10);border-color:rgba(245,158,11,.20);color:#d97706}
            .timeline-dot.violet{background:rgba(139,92,246,.10);border-color:rgba(139,92,246,.20);color:#7c3aed}
            .timeline-dot.emerald{background:rgba(16,185,129,.10);border-color:rgba(16,185,129,.20);color:#059669}
            .timeline-dot.rose{background:rgba(244,63,94,.10);border-color:rgba(244,63,94,.20);color:#e11d48}

            .timeline-line{
                width:2px;
                flex:1;
                min-height:26px;
                margin-top:8px;
                border-radius:999px;
                background:linear-gradient(180deg,rgba(148,163,184,.28),rgba(148,163,184,.08));
            }

            .timeline-item:last-child .timeline-line{display:none}

            .timeline-card{
                flex:1;
                border:1px solid rgba(148,163,184,.18);
                background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,250,252,.96));
                border-radius:20px;
                padding:16px 18px;
                box-shadow:0 14px 35px rgba(15,23,42,.05);
                transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
            }

            .timeline-card:hover{
                transform:translateY(-2px);
                box-shadow:0 22px 45px rgba(15,23,42,.08);
                border-color:rgba(148,163,184,.28);
            }

            .tl-title{
                font-size:15px;
                font-weight:800;
                color:#0f172a;
                margin:0 0 4px;
            }

            .tl-desc{
                font-size:13px;
                line-height:1.7;
                color:#475569;
                margin:0;
            }

            .tl-badge{
                display:inline-flex;
                align-items:center;
                gap:6px;
                white-space:nowrap;
                border-radius:999px;
                border:1px solid rgba(148,163,184,.18);
                background:#f8fafc;
                padding:7px 10px;
                font-size:11px;
                font-weight:800;
                letter-spacing:.04em;
                color:#64748b;
            }

            .product-row{
                display:flex;
                align-items:center;
                gap:14px;
                border:1px solid rgba(148,163,184,.16);
                background:linear-gradient(180deg,#fff,#f8fafc);
                border-radius:20px;
                padding:14px 15px;
                box-shadow:0 10px 24px rgba(15,23,42,.04);
                transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease;
            }

            .product-row:hover{
                transform:translateY(-2px);
                border-color:rgba(16,185,129,.24);
                box-shadow:0 18px 38px rgba(15,23,42,.08);
            }

            .product-icon{
                width:44px;
                height:44px;
                border-radius:16px;
                display:flex;
                align-items:center;
                justify-content:center;
                background:linear-gradient(135deg,rgba(16,185,129,.12),rgba(59,130,246,.10));
                color:#059669;
                flex-shrink:0;
            }

            @media (max-width: 900px){
                .steps-track{
                    grid-template-columns:1fr;
                    gap:18px;
                }

                .step-item{
                    align-items:flex-start;
                    text-align:left;
                    padding-left:0;
                }

                .step-line-bg{
                    display:none;
                }

                .step-item .mt-4{
                    margin-top:10px !important;
                    text-align:left !important;
                }
            }

            @media (max-width: 640px){
                .status-flash{
                    left:16px;
                    right:16px;
                    min-width:auto;
                    max-width:none;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.timeline-item').forEach((el, i) => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(16px)';
                    el.style.transition = 'opacity .5s ease, transform .5s ease';

                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, 180 + i * 90);
                });

                document.querySelectorAll('.product-row').forEach((el, i) => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(12px)';
                    el.style.transition = 'opacity .45s ease, transform .45s ease';

                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, 320 + i * 70);
                });

                const activeStep = document.querySelector('.step-item.active .step-icon-wrap');
                if (activeStep) {
                    setInterval(() => {
                        activeStep.style.boxShadow = '0 0 0 8px rgba(16,185,129,.12), 0 0 30px rgba(16,185,129,.38)';
                        setTimeout(() => {
                            activeStep.style.boxShadow = '0 0 0 8px rgba(16,185,129,.08), 0 0 18px rgba(16,185,129,.22)';
                        }, 900);
                    }, 1800);
                }
            });
        </script>
    @endpush
</div>