<div
    id="seguimiento-pedido-root"
    data-codigo-seguimiento="{{ $pedido->codigo_seguimiento }}"
    class="tracking-page min-h-screen bg-white text-slate-800 overflow-x-hidden"
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

    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

   @php
    $estados = [
        'nuevo' => [
            'label' => 'Pedido recibido',
            'icon' => 'fa-solid fa-bell',
            'color' => 'blue'
        ],

        'en_preparacion' => [
            'label' => 'En preparación',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'amber'
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
@endphp

    <div class="page">
        <div class="glass header">
            <div class="header-left">
                <h1 class="header-title">
                    Hola, <span>{{ $pedido->cliente_nombre ?? 'Cliente' }}</span> 👋
                </h1>

                <p class="header-desc">
                    {{ $metaEstado['label'] === 'Cancelado'
                        ? 'Tu pedido fue cancelado. Puedes revisar los detalles a continuación.'
                        : 'Estamos procesando tu pedido. Aquí verás cada avance en tiempo real.' }}
                </p>

                <div class="header-tags">
                    <div class="tag code">
                        <i class="fa-solid fa-receipt"></i>
                        Pedido #{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}
                    </div>
                </div>
            </div>

            <div class="header-right">
                <span class="total-label">Total del pedido</span>
                <span class="total-display">${{ number_format($pedido->total, 0, ',', '.') }}</span>
                <span class="total-label" style="margin-top:4px;">
                    {{ ucfirst($pedido->canal ?? 'whatsapp') }} ·
                    {{ optional($pedido->fecha_pedido ?? $pedido->created_at)->format('d/m/Y') }}
                </span>
            </div>
        </div>

        @if ($estadoActual !== 'cancelado')
            <div class="glass progress-section progress-dark">
                <p class="section-title">Progreso del pedido</p>

                <div class="steps-track">
                    @foreach ($ordenEstados as $i => $estadoPaso)
                        @php
                            $paso = $estados[$estadoPaso];
                            $completado = $indiceActual !== false && $i < $indiceActual;
                            $activo = $estadoPaso === $estadoActual;
                        @endphp

                        <div class="step-item {{ $completado ? 'completed' : '' }} {{ $activo ? 'active' : '' }}">
                            <div class="step-icon-wrap">
                                <i class="{{ $paso['icon'] }}"></i>
                            </div>
                            <p class="step-label">{{ $paso['label'] }}</p>
                            <div class="step-indicator"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="body-grid">
            <div class="glass history-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Historial del pedido</h2>
                    <span class="panel-count">{{ $historial->count() }} eventos</span>
                </div>

                <div class="timeline">
                    @forelse($historial->sortByDesc('fecha_evento') as $item)
                        @php
                            $color = $estados[$item->estado_nuevo]['color'] ?? 'blue';
                            $icon = $estados[$item->estado_nuevo]['icon'] ?? 'fa-solid fa-circle';
                            $titulo =
                                $item->titulo ?:
                                $estados[$item->estado_nuevo]['label'] ??
                                    ucfirst(str_replace('_', ' ', $item->estado_nuevo));
                        @endphp

                        <div class="timeline-item">
                            <div class="timeline-left">
                                <div class="timeline-dot {{ $color }}">
                                    <i class="{{ $icon }}"></i>
                                </div>
                                <div class="timeline-line"></div>
                            </div>

                            <div class="timeline-body">
                                <p class="tl-title">{{ $titulo }}</p>

                                @if ($item->descripcion)
                                    <p class="tl-desc">{{ $item->descripcion }}</p>
                                @endif

                                <div class="tl-time">
                                    <i class="fa-regular fa-clock" style="font-size:10px;"></i>
                                    {{ optional($item->fecha_evento)->format('d/m/Y · h:i a') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="timeline-item">
                            <div class="timeline-body">
                                <p class="tl-title">Sin movimientos</p>
                                <p class="tl-desc">Todavía no hay actualizaciones registradas para este pedido.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="right-col">
                <div class="glass products-panel">
                    <div class="panel-header" style="margin-bottom:0;">
                        <h2 class="panel-title">Productos</h2>
                    </div>

                    <div class="product-list">
                        @forelse($pedido->detalles as $detalle)
                            <div class="product-row">
                                <div class="product-icon">
                                    <i class="fa-solid fa-box"></i>
                                </div>

                                <div style="flex:1;">
                                    <p class="product-name">{{ $detalle->producto }}</p>
                                    <p class="product-qty">
                                        {{ rtrim(rtrim(number_format($detalle->cantidad, 3, '.', ''), '0'), '.') }}
                                        {{ $detalle->unidad }}
                                    </p>
                                </div>

                                <p class="product-price">${{ number_format($detalle->subtotal, 0, ',', '.') }}</p>
                            </div>
                        @empty
                            <div class="product-row">
                                <div style="flex:1;">
                                    <p class="product-name">No hay productos registrados</p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>

                @if ($pedido->notas)
                    <div class="glass notes-panel">
                        <h2 class="panel-title">Notas del pedido</h2>
                        <p class="notes-text">{{ $pedido->notas }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            *,
            :before,
            :after {
                box-sizing: border-box
            }

            :root {
                --bg: #ffffff;
                --surface: rgba(255, 255, 255, 0.9);
                --surface-hover: rgba(255, 255, 255, 1);
                --border: rgba(0, 0, 0, 0.08);
                --border-strong: rgba(0, 0, 0, 0.15);
                --text-primary: #0f172a;
                --text-secondary: rgba(15, 23, 42, 0.7);
                --text-muted: rgba(15, 23, 42, 0.5);
                --accent: #10b981;
                --accent-glow: rgba(16, 185, 129, 0.25);
                --accent-dim: rgba(16, 185, 129, 0.1);
            }

            .tracking-page {
                font-family: 'DM Sans', sans-serif;
                position: relative
            }

            .tracking-page::before {
                content: '';
                position: fixed;
                inset: 0;
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
                pointer-events: none;
                z-index: 0;
                opacity: .4
            }

            .status-flash {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 80;
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 280px;
                max-width: 360px;
                padding: 14px 16px;
                border-radius: 18px;
                border: 1px solid rgba(16, 185, 129, 0.2);
                background: rgba(255, 255, 255, 0.96);
                box-shadow: 0 22px 50px rgba(15, 23, 42, 0.16);
                backdrop-filter: blur(16px);
                transition: opacity .35s ease, transform .35s ease;
                transform: translateY(0);
            }

            .status-flash.hidden {
                display: none;
            }

            .status-flash.opacity-0 {
                opacity: 0;
                transform: translateY(-10px);
            }

            .status-flash.opacity-100 {
                opacity: 1;
                transform: translateY(0);
            }

            .status-flash__icon {
                width: 42px;
                height: 42px;
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(16, 185, 129, 0.12);
                color: #10b981;
                flex-shrink: 0;
            }

            .status-flash__title {
                margin: 0;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: .14em;
                text-transform: uppercase;
                color: #10b981;
            }

            .status-flash__text {
                margin: 4px 0 0;
                font-size: 13px;
                color: var(--text-primary);
                font-weight: 600;
            }

            .bg-glow {
                position: fixed;
                border-radius: 50%;
                filter: blur(120px);
                pointer-events: none;
                z-index: 0
            }

            .bg-glow-1 {
                width: 600px;
                height: 600px;
                top: -200px;
                left: -150px;
                background: radial-gradient(circle, rgba(16, 212, 142, .06) 0%, transparent 70%)
            }

            .bg-glow-2 {
                width: 500px;
                height: 500px;
                bottom: -100px;
                right: -100px;
                background: radial-gradient(circle, rgba(255, 255, 255, 0.07) 0%, transparent 70%)
            }

            .page {
                position: relative;
                z-index: 1;
                width: 100%;
                max-width: 100%;
                padding: 2.5rem 2rem 4rem;
            }

            .glass {
                background: var(--surface);
                border: 1px solid var(--border);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 20px;
                transition: border-color .3s, background .3s
            }

            .glass:hover {
                border-color: var(--border-strong);
                background: var(--surface-hover)
            }

            .header {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 2rem;
                align-items: center;
                padding: 2rem 2.5rem;
                margin-bottom: 1.5rem;
                animation: fadeUp .6s ease both;
                position: relative;
                overflow: hidden
            }

            .header::before {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(135deg, rgba(16, 212, 142, .04) 0%, transparent 60%);
                border-radius: inherit
            }

            .header h1 {
                font-family: 'Syne', sans-serif;
                font-size: clamp(1.8rem, 4vw, 2.8rem);
                font-weight: 800;
                color: var(--text-primary);
                line-height: 1.1;
                letter-spacing: -.02em
            }

            .header h1 span {
                color: var(--accent)
            }

            .header-right {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: .5rem
            }

            .total-display {
                font-family: 'Syne', sans-serif;
                font-size: 2.6rem;
                font-weight: 800;
                color: var(--text-primary);
                letter-spacing: -.03em;
                line-height: 1
            }

            .total-label {
                font-size: 11px;
                letter-spacing: .18em;
                text-transform: uppercase;
                color: var(--text-muted);
                text-align: right
            }

            .progress-section {
                padding: 2rem 2.5rem;
                margin-bottom: 1.5rem;
                animation: fadeUp .6s .15s ease both
            }

            .progress-dark {
                background: #0f172a;
                border: 1px solid rgba(255, 255, 255, 0.08);
            }

            .progress-dark .section-title,
            .progress-dark .step-label {
                color: rgba(255, 255, 255, 0.6);
            }

            .progress-dark:hover {
                background: #0f172a !important;
                border-color: rgba(255, 255, 255, 0.08);
            }

            .progress-dark .step-item:not(:last-child)::after {
                background: rgba(255, 255, 255, 0.15);
            }

            .progress-dark .step-icon-wrap {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.15);
                color: rgba(255, 255, 255, 0.6);
            }

            .progress-dark .step-item.completed .step-icon-wrap {
                background: rgba(16, 185, 129, 0.2);
                border-color: rgba(16, 185, 129, 0.4);
                color: #10b981;
            }

            .progress-dark .step-item.active .step-icon-wrap {
                background: #10b981;
                border-color: #10b981;
                color: #0f172a;
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.15), 0 0 20px rgba(16, 185, 129, 0.4);
            }

            .progress-dark .step-item.active .step-label {
                color: #ffffff;
            }

            .section-title {
                font-family: 'Syne', sans-serif;
                font-size: .8rem;
                font-weight: 700;
                letter-spacing: .22em;
                text-transform: uppercase;
                color: var(--text-muted);
                margin-bottom: 2.25rem
            }

            .steps-track {
                display: flex;
                align-items: flex-start;
                gap: 0;
                position: relative
            }

            .step-item {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                position: relative
            }

            .step-item:not(:last-child)::after {
                content: '';
                position: absolute;
                top: 20px;
                left: 50%;
                width: 100%;
                height: 2px;
                background: var(--border-strong);
                z-index: 0
            }

            .step-item.completed:not(:last-child)::after {
                background: linear-gradient(90deg, var(--accent), rgba(16, 212, 142, .4))
            }

            .step-icon-wrap {
                position: relative;
                z-index: 1;
                width: 40px;
                height: 40px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
                border: 1.5px solid var(--border-strong);
                background: rgba(255, 255, 255, .04);
                color: var(--text-muted);
                transition: all .4s ease
            }

            .step-item.completed .step-icon-wrap {
                background: rgba(16, 212, 142, .12);
                border-color: rgba(16, 212, 142, .35);
                color: var(--accent)
            }

            .step-item.active .step-icon-wrap {
                background: var(--accent);
                border-color: var(--accent);
                color: #070d1a;
                box-shadow: 0 0 0 6px var(--accent-dim), 0 0 20px var(--accent-glow);
                transform: scale(1.12)
            }

            .step-label {
                margin-top: .75rem;
                font-size: 11px;
                font-weight: 600;
                text-align: center;
                color: var(--text-muted);
                letter-spacing: .04em;
                max-width: 90px;
                line-height: 1.35
            }

            .step-item.completed .step-label {
                color: var(--accent)
            }

            .step-item.active .step-label {
                color: var(--text-primary)
            }

            .step-indicator {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: transparent;
                margin-top: .4rem
            }

            .step-item.active .step-indicator {
                background: var(--accent);
                box-shadow: 0 0 6px var(--accent);
                animation: pulse-dot 1.8s infinite
            }

            .body-grid {
                display: grid;
                grid-template-columns: 1fr 380px;
                gap: 1.5rem;
                animation: fadeUp .6s .22s ease both
            }

            .history-panel {
                padding: 2rem 2.5rem
            }

            .panel-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 1.75rem
            }

            .panel-title {
                font-family: 'Syne', sans-serif;
                font-size: 1.05rem;
                font-weight: 700;
                color: var(--text-primary)
            }

            .panel-count {
                font-size: 11.5px;
                font-weight: 600;
                letter-spacing: .1em;
                color: var(--text-muted);
                background: rgba(255, 255, 255, .05);
                border: 1px solid var(--border);
                padding: 4px 12px;
                border-radius: 100px
            }

            .timeline {
                display: flex;
                flex-direction: column
            }

            .timeline-item {
                display: flex;
                gap: 1rem;
                padding-bottom: 1.5rem;
                position: relative
            }

            .timeline-item:not(:last-child) .timeline-line {
                display: block
            }

            .timeline-left {
                display: flex;
                flex-direction: column;
                align-items: center;
                flex-shrink: 0
            }

            .timeline-dot {
                width: 30px;
                height: 30px;
                border-radius: 9px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                flex-shrink: 0;
                border: 1px solid
            }

            .timeline-dot.blue {
                background: rgba(74, 158, 255, .12);
                border-color: rgba(74, 158, 255, .25);
                color: #4a9eff
            }

            .timeline-dot.amber {
                background: rgba(245, 166, 35, .12);
                border-color: rgba(245, 166, 35, .25);
                color: #f5a623
            }

            .timeline-dot.violet {
                background: rgba(167, 139, 250, .12);
                border-color: rgba(167, 139, 250, .25);
                color: #a78bfa
            }

            .timeline-dot.indigo {
                background: rgba(129, 140, 248, .12);
                border-color: rgba(129, 140, 248, .25);
                color: #818cf8
            }

            .timeline-dot.emerald {
                background: rgba(16, 212, 142, .12);
                border-color: rgba(16, 212, 142, .25);
                color: #10d48e
            }

            .timeline-dot.rose {
                background: rgba(251, 113, 133, .12);
                border-color: rgba(251, 113, 133, .25);
                color: #fb7185
            }

            .timeline-line {
                flex: 1;
                width: 1px;
                background: var(--border);
                min-height: 16px;
                display: none;
                margin: 4px 0
            }

            .timeline-body {
                flex: 1;
                padding: .9rem 1.1rem;
                border-radius: 14px;
                background: rgba(255, 255, 255, .025);
                border: 1px solid var(--border);
                transition: background .25s, border-color .25s
            }

            .timeline-body:hover {
                background: rgba(255, 255, 255, .05);
                border-color: var(--border-strong)
            }

            .tl-title {
                font-size: 13.5px;
                font-weight: 600;
                color: var(--text-primary);
                margin-bottom: .25rem
            }

            .tl-desc {
                font-size: 12.5px;
                color: var(--text-secondary);
                line-height: 1.55
            }

            .tl-time {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 11.5px;
                color: var(--text-muted);
                margin-top: .5rem
            }

            .right-col {
                display: flex;
                flex-direction: column;
                gap: 1.25rem
            }

            .products-panel,
            .notes-panel {
                padding: 1.75rem
            }

            .product-list {
                display: flex;
                flex-direction: column;
                gap: .65rem;
                margin-top: 1.25rem
            }

            .product-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: .85rem 1rem;
                border-radius: 12px;
                background: rgba(255, 255, 255, .025);
                border: 1px solid var(--border);
                transition: background .2s, border-color .2s
            }

            .product-row:hover {
                background: rgba(255, 255, 255, .05);
                border-color: var(--border-strong)
            }

            .product-icon {
                width: 34px;
                height: 34px;
                border-radius: 9px;
                background: var(--accent-dim);
                border: 1px solid rgba(16, 212, 142, .2);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                color: var(--accent);
                flex-shrink: 0
            }

            .product-name {
                font-size: 13px;
                font-weight: 600;
                color: var(--text-primary)
            }

            .product-qty {
                font-size: 11.5px;
                color: var(--text-muted);
                margin-top: 2px
            }

            .product-price {
                font-family: 'Syne', sans-serif;
                font-size: 13.5px;
                font-weight: 700;
                color: var(--text-primary);
                white-space: nowrap
            }

            .notes-text {
                font-size: 13px;
                color: var(--text-secondary);
                line-height: 1.65;
                margin-top: .75rem
            }

            @keyframes fadeUp {
                from {
                    opacity: 0;
                    transform: translateY(22px)
                }

                to {
                    opacity: 1;
                    transform: translateY(0)
                }
            }

            @keyframes pulse-dot {
                0%, 100% {
                    box-shadow: 0 0 0 0 rgba(16, 212, 142, .5)
                }

                50% {
                    box-shadow: 0 0 0 6px rgba(16, 212, 142, 0)
                }
            }

            @media (max-width:900px) {
                .body-grid {
                    grid-template-columns: 1fr
                }

                .header {
                    grid-template-columns: 1fr
                }

                .header-right {
                    align-items: flex-start
                }
            }

            @media (max-width:580px) {
                .header,
                .progress-section,
                .history-panel {
                    padding: 1.5rem
                }

                .products-panel,
                .notes-panel {
                    padding: 1.25rem
                }

                .status-flash {
                    left: 16px;
                    right: 16px;
                    min-width: auto;
                    max-width: none;
                }
            }
        </style>
    @endpush

    @push('scripts')
       

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.timeline-item').forEach((el, i) => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(-16px)';
                    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateX(0)';
                    }, 350 + i * 110);
                });

                document.querySelectorAll('.product-row').forEach((el, i) => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(10px)';
                    el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, 500 + i * 80);
                });

                const activeStep = document.querySelector('.step-item.active .step-icon-wrap');
                if (activeStep) {
                    setInterval(() => {
                        activeStep.style.boxShadow =
                            '0 0 0 6px rgba(16,212,142,0.2), 0 0 28px rgba(16,212,142,0.35)';
                        setTimeout(() => {
                            activeStep.style.boxShadow =
                                '0 0 0 6px rgba(16,212,142,0.06), 0 0 14px rgba(16,212,142,0.2)';
                        }, 900);
                    }, 1800);
                }
            });
        </script>
    @endpush
</div>