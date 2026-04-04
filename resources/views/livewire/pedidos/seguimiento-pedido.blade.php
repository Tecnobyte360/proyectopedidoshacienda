<div class="tracking-page min-h-screen bg-[#070d1a] text-white overflow-x-hidden">
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

    @php
        $estados = [
            'nuevo' => ['label' => 'Pedido recibido', 'icon' => 'fa-solid fa-bell', 'color' => 'blue'],
            'en_preparacion' => ['label' => 'En preparación', 'icon' => 'fa-solid fa-utensils', 'color' => 'amber'],
            'repartidor_en_camino' => ['label' => 'Repartidor en camino', 'icon' => 'fa-solid fa-motorcycle', 'color' => 'violet'],
            'recogido' => ['label' => 'Recogido', 'icon' => 'fa-solid fa-box-open', 'color' => 'indigo'],
            'entregado' => ['label' => 'Entregado', 'icon' => 'fa-solid fa-circle-check', 'color' => 'emerald'],
            'cancelado' => ['label' => 'Cancelado', 'icon' => 'fa-solid fa-ban', 'color' => 'rose'],
        ];

        $estadoActual = $pedido->estado ?? 'nuevo';
        $metaEstado = $estados[$estadoActual] ?? ['label' => ucfirst(str_replace('_', ' ', $estadoActual)), 'icon' => 'fa-solid fa-circle', 'color' => 'blue'];

        $ordenEstados = ['nuevo', 'en_preparacion', 'repartidor_en_camino', 'recogido', 'entregado'];
        $indiceActual = array_search($estadoActual, $ordenEstados);
        $cantidadItems = $pedido->detalles->count();
        $telefono = $pedido->telefono_contacto ?? $pedido->telefono_whatsapp ?? $pedido->telefono;
    @endphp

    <div class="page">

        <div class="glass header">
            <div>
                <div class="header-eyebrow">
                    <span class="dot"></span>
                    Seguimiento en tiempo real
                </div>

                <h1>Hola, <span>{{ $pedido->cliente_nombre ?? 'Cliente' }}</span></h1>

                <p class="header-sub">
                    {{ $metaEstado['label'] === 'Cancelado'
                        ? 'Tu pedido fue cancelado. Aquí puedes consultar el detalle.'
                        : 'Aquí puedes ver cada actualización de tu pedido en tiempo real.' }}
                </p>

                <div class="header-badges">
                    <span class="badge badge-status">
                        <i class="{{ $metaEstado['icon'] }}" style="font-size:10px;"></i>
                        {{ $metaEstado['label'] }}
                    </span>

                    <span class="badge badge-id">
                        <i class="fa-solid fa-hashtag" style="font-size:9px;"></i>
                        PED-{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}
                    </span>
                </div>
            </div>

            <div class="header-right">
                <span class="total-label">Total del pedido</span>
                <span class="total-display">${{ number_format($pedido->total, 0, ',', '.') }}</span>
                <span class="total-label" style="margin-top:4px;">
                    {{ ucfirst($pedido->canal ?? 'whatsapp') }} · {{ optional($pedido->fecha_pedido ?? $pedido->created_at)->format('d/m/Y') }}
                </span>
            </div>
        </div>

        <div class="stats-row">
            <div class="glass stat-card">
                <p class="stat-label">Pedido</p>
                <p class="stat-value">#{{ str_pad($pedido->id, 5, '0', STR_PAD_LEFT) }}</p>
                <p class="stat-sub">Código interno</p>
            </div>

            <div class="glass stat-card">
                <p class="stat-label">Fecha</p>
                <p class="stat-value">{{ optional($pedido->fecha_pedido ?? $pedido->created_at)->format('d/m/Y') }}</p>
                <p class="stat-sub">{{ optional($pedido->fecha_pedido ?? $pedido->created_at)->format('h:i a') }}</p>
            </div>

            <div class="glass stat-card">
                <p class="stat-label">Contacto</p>
                <p class="stat-value" style="font-size:1rem;">{{ $telefono ?: 'No registrado' }}</p>
                <p class="stat-sub">WhatsApp</p>
            </div>

            <div class="glass stat-card">
                <p class="stat-label">Productos</p>
                <p class="stat-value">{{ $cantidadItems }} ítem{{ $cantidadItems === 1 ? '' : 's' }}</p>
                <p class="stat-sub">En la orden</p>
            </div>
        </div>

        @if($estadoActual !== 'cancelado')
            <div class="glass progress-section">
                <p class="section-title">Progreso del pedido</p>

                <div class="steps-track">
                    @foreach($ordenEstados as $i => $estadoPaso)
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
                            $titulo = $item->titulo ?: ($estados[$item->estado_nuevo]['label'] ?? ucfirst(str_replace('_', ' ', $item->estado_nuevo)));
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

                                @if($item->descripcion)
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
                                        {{ rtrim(rtrim(number_format($detalle->cantidad, 3, '.', ''), '0'), '.') }} {{ $detalle->unidad }}
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

                <div class="glass summary-panel">
                    <h2 class="panel-title">Resumen</h2>

                    <div class="summary-rows">
                        <div class="summary-row">
                            <span class="summary-key">Cliente</span>
                            <span class="summary-val">{{ $pedido->cliente_nombre ?? 'No registrado' }}</span>
                        </div>

                        <div class="summary-row">
                            <span class="summary-key">Canal</span>
                            <span class="summary-val">{{ ucfirst($pedido->canal ?? 'whatsapp') }}</span>
                        </div>

                        <div class="summary-row">
                            <span class="summary-key">Estado</span>
                            <span class="summary-val">{{ $metaEstado['label'] }}</span>
                        </div>

                        <div class="summary-row">
                            <span class="summary-key">Productos</span>
                            <span class="summary-val">{{ $cantidadItems }} ítem{{ $cantidadItems === 1 ? '' : 's' }}</span>
                        </div>
                    </div>

                    <div class="total-row">
                        <span class="total-row-label">Total</span>
                        <span class="total-row-val">${{ number_format($pedido->total, 0, ',', '.') }}</span>
                    </div>
                </div>

                @if($pedido->notas)
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
            *,:before,:after{box-sizing:border-box}
            :root{
                --bg:#070d1a;--surface:rgba(255,255,255,.035);--surface-hover:rgba(255,255,255,.065);
                --border:rgba(255,255,255,.07);--border-strong:rgba(255,255,255,.12);
                --text-primary:#f0f4ff;--text-secondary:rgba(200,210,240,.65);--text-muted:rgba(180,195,230,.38);
                --accent:#10d48e;--accent-glow:rgba(16,212,142,.25);--accent-dim:rgba(16,212,142,.12);
                --blue:#4a9eff;--amber:#f5a623;--violet:#a78bfa;--indigo:#818cf8;--emerald:#10d48e;--rose:#fb7185;
            }
            .tracking-page{font-family:'DM Sans',sans-serif;position:relative}
            .tracking-page::before{content:'';position:fixed;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");pointer-events:none;z-index:0;opacity:.4}
            .bg-glow{position:fixed;border-radius:50%;filter:blur(120px);pointer-events:none;z-index:0}
            .bg-glow-1{width:600px;height:600px;top:-200px;left:-150px;background:radial-gradient(circle,rgba(16,212,142,.06) 0%,transparent 70%)}
            .bg-glow-2{width:500px;height:500px;bottom:-100px;right:-100px;background:radial-gradient(circle,rgba(74,158,255,.07) 0%,transparent 70%)}
            .page{position:relative;z-index:1;max-width:1240px;margin:0 auto;padding:2.5rem 1.5rem 4rem}
            .glass{background:var(--surface);border:1px solid var(--border);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-radius:20px;transition:border-color .3s,background .3s}
            .glass:hover{border-color:var(--border-strong);background:var(--surface-hover)}
            .header{display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:center;padding:2rem 2.5rem;margin-bottom:1.5rem;animation:fadeUp .6s ease both;position:relative;overflow:hidden}
            .header::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(16,212,142,.04) 0%,transparent 60%);border-radius:inherit}
            .header-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--accent);margin-bottom:.75rem}
            .header-eyebrow .dot{width:6px;height:6px;border-radius:50%;background:var(--accent);box-shadow:0 0 8px var(--accent);animation:pulse-dot 2s infinite}
            .header h1{font-family:'Syne',sans-serif;font-size:clamp(1.8rem,4vw,2.8rem);font-weight:800;color:var(--text-primary);line-height:1.1;letter-spacing:-.02em}
            .header h1 span{color:var(--accent)}
            .header-sub{font-size:.9rem;color:var(--text-secondary);margin-top:.5rem;max-width:420px;line-height:1.6}
            .header-badges{display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap}
            .badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:100px;font-size:11.5px;font-weight:600;letter-spacing:.06em;border:1px solid}
            .badge-status{background:var(--accent-dim);border-color:rgba(16,212,142,.25);color:var(--accent)}
            .badge-id{background:rgba(255,255,255,.04);border-color:var(--border);color:var(--text-secondary)}
            .header-right{display:flex;flex-direction:column;align-items:flex-end;gap:.5rem}
            .total-display{font-family:'Syne',sans-serif;font-size:2.6rem;font-weight:800;color:var(--text-primary);letter-spacing:-.03em;line-height:1}
            .total-label{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--text-muted);text-align:right}
            .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;animation:fadeUp .6s .08s ease both}
            .stat-card{padding:1.25rem 1.5rem}
            .stat-label{font-size:10.5px;font-weight:600;letter-spacing:.2em;text-transform:uppercase;color:var(--text-muted);margin-bottom:.5rem}
            .stat-value{font-family:'Syne',sans-serif;font-size:1.35rem;font-weight:700;color:var(--text-primary)}
            .stat-sub{font-size:12px;color:var(--text-muted);margin-top:.2rem}
            .progress-section{padding:2rem 2.5rem;margin-bottom:1.5rem;animation:fadeUp .6s .15s ease both}
            .section-title{font-family:'Syne',sans-serif;font-size:.8rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2.25rem}
            .steps-track{display:flex;align-items:flex-start;gap:0;position:relative}
            .step-item{flex:1;display:flex;flex-direction:column;align-items:center;position:relative}
            .step-item:not(:last-child)::after{content:'';position:absolute;top:20px;left:50%;width:100%;height:2px;background:var(--border-strong);z-index:0}
            .step-item.completed:not(:last-child)::after{background:linear-gradient(90deg,var(--accent),rgba(16,212,142,.4))}
            .step-icon-wrap{position:relative;z-index:1;width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:14px;border:1.5px solid var(--border-strong);background:rgba(255,255,255,.04);color:var(--text-muted);transition:all .4s ease}
            .step-item.completed .step-icon-wrap{background:rgba(16,212,142,.12);border-color:rgba(16,212,142,.35);color:var(--accent)}
            .step-item.active .step-icon-wrap{background:var(--accent);border-color:var(--accent);color:#070d1a;box-shadow:0 0 0 6px var(--accent-dim),0 0 20px var(--accent-glow);transform:scale(1.12)}
            .step-label{margin-top:.75rem;font-size:11px;font-weight:600;text-align:center;color:var(--text-muted);letter-spacing:.04em;max-width:90px;line-height:1.35}
            .step-item.completed .step-label{color:var(--accent)}
            .step-item.active .step-label{color:var(--text-primary)}
            .step-indicator{width:6px;height:6px;border-radius:50%;background:transparent;margin-top:.4rem}
            .step-item.active .step-indicator{background:var(--accent);box-shadow:0 0 6px var(--accent);animation:pulse-dot 1.8s infinite}
            .body-grid{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;animation:fadeUp .6s .22s ease both}
            .history-panel{padding:2rem 2.5rem}
            .panel-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.75rem}
            .panel-title{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:var(--text-primary)}
            .panel-count{font-size:11.5px;font-weight:600;letter-spacing:.1em;color:var(--text-muted);background:rgba(255,255,255,.05);border:1px solid var(--border);padding:4px 12px;border-radius:100px}
            .timeline{display:flex;flex-direction:column}
            .timeline-item{display:flex;gap:1rem;padding-bottom:1.5rem;position:relative}
            .timeline-item:not(:last-child) .timeline-line{display:block}
            .timeline-left{display:flex;flex-direction:column;align-items:center;flex-shrink:0}
            .timeline-dot{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;border:1px solid}
            .timeline-dot.blue{background:rgba(74,158,255,.12);border-color:rgba(74,158,255,.25);color:var(--blue)}
            .timeline-dot.amber{background:rgba(245,166,35,.12);border-color:rgba(245,166,35,.25);color:var(--amber)}
            .timeline-dot.violet{background:rgba(167,139,250,.12);border-color:rgba(167,139,250,.25);color:var(--violet)}
            .timeline-dot.indigo{background:rgba(129,140,248,.12);border-color:rgba(129,140,248,.25);color:var(--indigo)}
            .timeline-dot.emerald{background:rgba(16,212,142,.12);border-color:rgba(16,212,142,.25);color:var(--emerald)}
            .timeline-dot.rose{background:rgba(251,113,133,.12);border-color:rgba(251,113,133,.25);color:var(--rose)}
            .timeline-line{flex:1;width:1px;background:var(--border);min-height:16px;display:none;margin:4px 0}
            .timeline-body{flex:1;padding:.9rem 1.1rem;border-radius:14px;background:rgba(255,255,255,.025);border:1px solid var(--border);transition:background .25s,border-color .25s}
            .timeline-body:hover{background:rgba(255,255,255,.05);border-color:var(--border-strong)}
            .tl-title{font-size:13.5px;font-weight:600;color:var(--text-primary);margin-bottom:.25rem}
            .tl-desc{font-size:12.5px;color:var(--text-secondary);line-height:1.55}
            .tl-time{display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--text-muted);margin-top:.5rem}
            .right-col{display:flex;flex-direction:column;gap:1.25rem}
            .products-panel,.summary-panel,.notes-panel{padding:1.75rem}
            .product-list{display:flex;flex-direction:column;gap:.65rem;margin-top:1.25rem}
            .product-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1rem;border-radius:12px;background:rgba(255,255,255,.025);border:1px solid var(--border);transition:background .2s,border-color .2s}
            .product-row:hover{background:rgba(255,255,255,.05);border-color:var(--border-strong)}
            .product-icon{width:34px;height:34px;border-radius:9px;background:var(--accent-dim);border:1px solid rgba(16,212,142,.2);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--accent);flex-shrink:0}
            .product-name{font-size:13px;font-weight:600;color:var(--text-primary)}
            .product-qty{font-size:11.5px;color:var(--text-muted);margin-top:2px}
            .product-price{font-family:'Syne',sans-serif;font-size:13.5px;font-weight:700;color:var(--text-primary);white-space:nowrap}
            .summary-rows{display:flex;flex-direction:column;margin-top:1.25rem}
            .summary-row{display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;border-bottom:1px solid var(--border);font-size:13px}
            .summary-row:last-child{border:none}
            .summary-key{color:var(--text-secondary)}
            .summary-val{font-weight:600;color:var(--text-primary)}
            .total-row{display:flex;justify-content:space-between;align-items:center;padding:1.1rem 1.25rem;background:var(--accent-dim);border:1px solid rgba(16,212,142,.2);border-radius:14px;margin-top:1rem}
            .total-row-label{font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--accent)}
            .total-row-val{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:800;color:var(--accent)}
            .notes-text{font-size:13px;color:var(--text-secondary);line-height:1.65;margin-top:.75rem}
            @keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
            @keyframes pulse-dot{0%,100%{box-shadow:0 0 0 0 rgba(16,212,142,.5)}50%{box-shadow:0 0 0 6px rgba(16,212,142,0)}}
            @media (max-width:900px){.body-grid{grid-template-columns:1fr}.stats-row{grid-template-columns:repeat(2,1fr)}.header{grid-template-columns:1fr}.header-right{align-items:flex-start}}
            @media (max-width:580px){.stats-row{grid-template-columns:1fr 1fr}.header,.progress-section,.history-panel{padding:1.5rem}.products-panel,.summary-panel,.notes-panel{padding:1.25rem}}
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
                        activeStep.style.boxShadow = '0 0 0 6px rgba(16,212,142,0.2), 0 0 28px rgba(16,212,142,0.35)';
                        setTimeout(() => {
                            activeStep.style.boxShadow = '0 0 0 6px rgba(16,212,142,0.06), 0 0 14px rgba(16,212,142,0.2)';
                        }, 900);
                    }, 1800);
                }
            });
        </script>
    @endpush
</div>