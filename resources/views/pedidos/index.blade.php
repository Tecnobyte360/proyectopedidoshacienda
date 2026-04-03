<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos · Delivery</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css'])

    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0-rc2/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:        #0a0c12;
            --surface:   #12151f;
            --card:      #181c28;
            --border:    rgba(255,255,255,0.07);
            --border-hi: rgba(255,165,50,0.35);

            --accent:    #ff8c00;
            --accent2:   #ffb347;
            --glow:      rgba(255,140,0,0.18);

            --nuevo:     #3b82f6;
            --proceso:   #f59e0b;
            --despachado:#a855f7;
            --entregado: #22c55e;

            --text:      #f0f2f8;
            --muted:     #6b7280;
            --sub:       #9ca3af;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 28px 20px 60px;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient background blobs */
        body::before {
            content: '';
            position: fixed;
            top: -160px;
            left: -160px;
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, rgba(255,140,0,0.08) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -120px;
            right: -120px;
            width: 440px;
            height: 440px;
            background: radial-gradient(circle, rgba(59,130,246,0.07) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
        }

        /* ── HEADER ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 32px;
            flex-wrap: wrap;
            animation: fadeDown .5s ease both;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            box-shadow: 0 0 24px var(--glow);
            flex-shrink: 0;
        }

        .header h1 {
            font-family: 'Syne', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.03em;
            line-height: 1;
        }

        .header p {
            font-size: 0.93rem;
            color: var(--muted);
            margin-top: 5px;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.25);
            color: #4ade80;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            align-self: center;
        }

        .live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 8px #22c55e;
            animation: pulse-green 1.8s ease infinite;
        }

        /* ── STATS STRIP ── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 28px;
            animation: fadeDown .5s .1s ease both;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px 18px;
            position: relative;
            overflow: hidden;
            transition: border-color .2s;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 16px 16px 0 0;
        }

        .stat-card.s-nuevo::before   { background: var(--nuevo); }
        .stat-card.s-proceso::before { background: var(--proceso); }
        .stat-card.s-desp::before    { background: var(--despachado); }
        .stat-card.s-entr::before    { background: var(--entregado); }

        .stat-label {
            font-size: 0.78rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        .stat-num {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-card.s-nuevo   .stat-num { color: var(--nuevo); }
        .stat-card.s-proceso .stat-num { color: var(--proceso); }
        .stat-card.s-desp    .stat-num { color: var(--despachado); }
        .stat-card.s-entr    .stat-num { color: var(--entregado); }

        .stat-icon {
            position: absolute;
            right: 14px;
            bottom: 12px;
            font-size: 1.8rem;
            opacity: 0.18;
        }

        /* ── TOOLBAR ── */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            animation: fadeDown .5s .15s ease both;
        }

        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .tab-btn {
            text-decoration: none;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--muted);
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            transition: all .2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn:hover {
            border-color: rgba(255,140,0,0.3);
            color: var(--text);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-color: transparent;
            color: #0a0c12;
            font-weight: 700;
            box-shadow: 0 0 16px var(--glow);
        }

        .count-badge {
            background: rgba(0,0,0,0.18);
            border-radius: 6px;
            padding: 1px 6px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .tab-btn.active .count-badge {
            background: rgba(0,0,0,0.22);
            color: #0a0c12;
        }

        .filter-select {
            appearance: none;
            -webkit-appearance: none;
            border: 1px solid var(--border);
            background: var(--card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 14px center;
            color: var(--sub);
            border-radius: 10px;
            padding: 10px 36px 10px 14px;
            font-size: 0.88rem;
            outline: none;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: border-color .2s;
        }

        .filter-select:hover {
            border-color: rgba(255,140,0,0.3);
            color: var(--text);
        }

        /* ── ORDERS LIST ── */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .order-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 0;
            display: flex;
            align-items: stretch;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s;
            animation: slideIn .35s ease both;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(0,0,0,0.4);
            border-color: rgba(255,140,0,0.2);
        }

        .order-card.active {
            border-color: var(--border-hi);
            box-shadow: 0 0 0 1px rgba(255,165,50,0.12), 0 8px 24px rgba(0,0,0,0.35);
        }

        /* Left accent stripe */
        .card-stripe {
            width: 5px;
            flex-shrink: 0;
        }

        .stripe-nuevo      { background: var(--nuevo); }
        .stripe-proceso    { background: var(--proceso); }
        .stripe-despachado { background: var(--despachado); }
        .stripe-entregado  { background: var(--entregado); }

        .card-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            gap: 20px;
        }

        .order-left {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .order-top {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .order-code {
            font-family: 'Syne', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--accent2);
            letter-spacing: 0.05em;
            background: rgba(255,140,0,0.08);
            padding: 3px 10px;
            border-radius: 6px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .status-dot-sm {
            width: 6px; height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-nuevo {
            background: rgba(59,130,246,0.12);
            color: #60a5fa;
        }
        .status-nuevo .status-dot-sm {
            background: var(--nuevo);
            box-shadow: 0 0 6px var(--nuevo);
            animation: pulse-blue 1.6s ease infinite;
        }

        .status-proceso {
            background: rgba(245,158,11,0.12);
            color: #fbbf24;
        }
        .status-proceso .status-dot-sm { background: var(--proceso); }

        .status-despachado {
            background: rgba(168,85,247,0.12);
            color: #c084fc;
        }
        .status-despachado .status-dot-sm { background: var(--despachado); }

        .status-entregado {
            background: rgba(34,197,94,0.12);
            color: #4ade80;
        }
        .status-entregado .status-dot-sm { background: var(--entregado); }

        .order-client {
            font-size: 1.1rem;
            color: var(--text);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .order-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            color: var(--muted);
        }

        /* Right section */
        .order-right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-shrink: 0;
        }

        .price-block {
            text-align: right;
        }

        .price {
            font-family: 'Syne', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .time {
            margin-top: 5px;
            font-size: 0.8rem;
            color: var(--muted);
            text-align: right;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,140,0,0.08);
            border: 1px solid rgba(255,140,0,0.15);
            color: var(--accent2);
            cursor: pointer;
            transition: all .2s;
            font-size: 1.1rem;
        }

        .action-btn:hover {
            background: rgba(255,140,0,0.18);
            transform: scale(1.08);
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
        }

        .empty-icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 1rem;
        }

        /* ── TOAST ── */
        .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            background: var(--surface);
            border: 1px solid rgba(255,140,0,0.3);
            border-radius: 16px;
            padding: 16px 18px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,140,0,0.08);
            min-width: 300px;
            z-index: 9999;
            transform: translateY(130px);
            opacity: 0;
            transition: all .4s cubic-bezier(.34,1.56,.64,1);
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .toast-title {
            font-weight: 700;
            color: var(--text);
            font-size: 0.95rem;
            margin-bottom: 3px;
        }

        .toast-message {
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-16px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        @keyframes pulse-green {
            0%,100% { box-shadow: 0 0 6px #22c55e; }
            50%      { box-shadow: 0 0 14px #22c55e; }
        }

        @keyframes pulse-blue {
            0%,100% { box-shadow: 0 0 4px var(--nuevo); }
            50%      { box-shadow: 0 0 12px var(--nuevo); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stats-strip { grid-template-columns: repeat(2,1fr); }
        }

        @media (max-width: 640px) {
            body { padding: 18px 12px 50px; }

            .header h1 { font-size: 1.7rem; }

            .stats-strip { grid-template-columns: repeat(2,1fr); gap: 8px; }

            .toolbar { flex-direction: column; align-items: stretch; }

            .tabs { width: 100%; }

            .filter-select { width: 100%; }

            .card-body {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .order-right {
                justify-content: space-between;
            }

            .price { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- HEADER -->
        <div class="header">
            <div class="header-brand">
                <div class="brand-icon">🛵</div>
                <div>
                    <h1>Pedidos</h1>
                    <p>Gestión y seguimiento en tiempo real</p>
                </div>
            </div>

            <div class="live-pill">
                <span class="live-dot"></span>
                En vivo
            </div>
        </div>

        @php
            $todos       = $pedidos->count();
            $nuevos      = $pedidos->where('estado', 'nuevo')->count();
            $enProceso   = $pedidos->where('estado', 'en_proceso')->count();
            $despachados = $pedidos->where('estado', 'despachado')->count();
            $entregados  = $pedidos->where('estado', 'entregado')->count();

            $tab  = request('estado', 'todos');
            $zona = request('zona', 'todas');

            $pedidosFiltrados = $pedidos;
            if ($tab !== 'todos')   { $pedidosFiltrados = $pedidosFiltrados->where('estado', $tab); }
            if ($zona !== 'todas')  { $pedidosFiltrados = $pedidosFiltrados->where('zona', $zona); }
        @endphp

        <!-- STATS STRIP -->
        <div class="stats-strip">
            <div class="stat-card s-nuevo">
                <div class="stat-label">Nuevos</div>
                <div class="stat-num">{{ $nuevos }}</div>
                <div class="stat-icon">🔔</div>
            </div>
            <div class="stat-card s-proceso">
                <div class="stat-label">En proceso</div>
                <div class="stat-num">{{ $enProceso }}</div>
                <div class="stat-icon">🍳</div>
            </div>
            <div class="stat-card s-desp">
                <div class="stat-label">Despachados</div>
                <div class="stat-num">{{ $despachados }}</div>
                <div class="stat-icon">🛵</div>
            </div>
            <div class="stat-card s-entr">
                <div class="stat-label">Entregados</div>
                <div class="stat-num">{{ $entregados }}</div>
                <div class="stat-icon">✅</div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="tabs">
                <a href="{{ request()->fullUrlWithQuery(['estado' => 'todos']) }}"
                   class="tab-btn {{ $tab === 'todos' ? 'active' : '' }}">
                    Todos <span class="count-badge">{{ $todos }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['estado' => 'nuevo']) }}"
                   class="tab-btn {{ $tab === 'nuevo' ? 'active' : '' }}">
                    🔔 Nuevos <span class="count-badge">{{ $nuevos }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['estado' => 'en_proceso']) }}"
                   class="tab-btn {{ $tab === 'en_proceso' ? 'active' : '' }}">
                    🍳 En proceso <span class="count-badge">{{ $enProceso }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['estado' => 'despachado']) }}"
                   class="tab-btn {{ $tab === 'despachado' ? 'active' : '' }}">
                    🛵 Despachados <span class="count-badge">{{ $despachados }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['estado' => 'entregado']) }}"
                   class="tab-btn {{ $tab === 'entregado' ? 'active' : '' }}">
                    ✅ Entregados <span class="count-badge">{{ $entregados }}</span>
                </a>
            </div>

            <form method="GET">
                <input type="hidden" name="estado" value="{{ $tab }}">
                <select name="zona" class="filter-select" onchange="this.form.submit()">
                    <option value="todas" {{ $zona === 'todas' ? 'selected' : '' }}>📍 Todas las zonas</option>
                    <option value="norte"  {{ $zona === 'norte'  ? 'selected' : '' }}>⬆️ Zona Norte</option>
                    <option value="sur"    {{ $zona === 'sur'    ? 'selected' : '' }}>⬇️ Zona Sur</option>
                    <option value="centro" {{ $zona === 'centro' ? 'selected' : '' }}>🎯 Zona Centro</option>
                </select>
            </form>
        </div>

        <!-- ORDERS LIST -->
        <div class="orders-list" id="orders-list">
            @forelse($pedidosFiltrados as $pedido)
                @php
                    $stripeClass = match($pedido->estado) {
                        'nuevo'      => 'stripe-nuevo',
                        'en_proceso' => 'stripe-proceso',
                        'despachado' => 'stripe-despachado',
                        'entregado'  => 'stripe-entregado',
                        default      => 'stripe-nuevo',
                    };

                    $badgeClass = match($pedido->estado) {
                        'nuevo'      => 'status-nuevo',
                        'en_proceso' => 'status-proceso',
                        'despachado' => 'status-despachado',
                        'entregado'  => 'status-entregado',
                        default      => 'status-nuevo',
                    };

                    $labelEstado = match($pedido->estado) {
                        'nuevo'      => 'Nuevo',
                        'en_proceso' => 'En Proceso',
                        'despachado' => 'Despachado',
                        'entregado'  => 'Entregado',
                        default      => ucfirst(str_replace('_', ' ', $pedido->estado)),
                    };
                @endphp

                <div class="order-card {{ $pedido->estado === 'nuevo' ? 'active' : '' }}" data-id="{{ $pedido->id }}">
                    <div class="card-stripe {{ $stripeClass }}"></div>
                    <div class="card-body">
                        <div class="order-left">
                            <div class="order-top">
                                <span class="order-code">PED-{{ str_pad($pedido->id, 3, '0', STR_PAD_LEFT) }}</span>
                                <span class="status-badge {{ $badgeClass }}">
                                    <span class="status-dot-sm"></span>
                                    {{ $labelEstado }}
                                </span>
                            </div>

                            <div class="order-client">{{ $pedido->cliente_nombre }}</div>

                            <div class="order-meta">
                                <span class="meta-chip">
                                    🕒 {{ \Carbon\Carbon::parse($pedido->created_at)->format('h:i a') }}
                                </span>
                                @if(isset($pedido->zona))
                                    <span class="meta-chip">
                                        📍 {{ ucfirst($pedido->zona) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="order-right">
                            <div class="price-block">
                                <div class="price">${{ number_format($pedido->total, 0, ',', '.') }}</div>
                                <div class="time">{{ \Carbon\Carbon::parse($pedido->created_at)->diffForHumans() }}</div>
                            </div>
                            <div class="action-btn" title="Ver detalle">›</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="empty-icon">🛵</div>
                    <p>Sin pedidos para mostrar en este momento.</p>
                </div>
            @endforelse
        </div>

    </div>

    <!-- TOAST -->
    <div class="toast" id="toast">
        <div class="toast-icon">🛵</div>
        <div>
            <div class="toast-title">¡Nuevo pedido!</div>
            <div class="toast-message" id="toast-message">Se ha recibido un nuevo pedido.</div>
        </div>
    </div>

    <script>
        window.Pusher = Pusher;

        const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: 'app-key',
            wsHost: isLocal ? '127.0.0.1' : 'pedidosonline.tecnobyte360.com',
            wsPort:  isLocal ? 8080 : 443,
            wssPort: isLocal ? 8080 : 443,
            forceTLS: !isLocal,
            enabledTransports: isLocal ? ['ws'] : ['ws', 'wss'],
            disableStats: true,
        });

        const ordersList   = document.getElementById('orders-list');
        const toast        = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');

        function formatMoney(value) {
            return new Intl.NumberFormat('es-CO').format(value);
        }

        function formatHour(dateString) {
            return new Date(dateString).toLocaleTimeString('es-CO', {
                hour: '2-digit', minute: '2-digit', hour12: true
            });
        }

        function getStatusLabel(estado) {
            return { nuevo: 'Nuevo', en_proceso: 'En Proceso', despachado: 'Despachado', entregado: 'Entregado' }[estado] || estado;
        }

        function getBadgeClass(estado) {
            return { nuevo: 'status-nuevo', en_proceso: 'status-proceso', despachado: 'status-despachado', entregado: 'status-entregado' }[estado] || 'status-nuevo';
        }

        function getStripeClass(estado) {
            return { nuevo: 'stripe-nuevo', en_proceso: 'stripe-proceso', despachado: 'stripe-despachado', entregado: 'stripe-entregado' }[estado] || 'stripe-nuevo';
        }

        function addOrderCard(pedido) {
            const code       = `PED-${String(pedido.id).padStart(3, '0')}`;
            const activeClass = pedido.estado === 'nuevo' ? 'active' : '';

            const card = document.createElement('div');
            card.className = `order-card ${activeClass}`;
            card.dataset.id = pedido.id;
            card.style.animationDelay = '0s';

            card.innerHTML = `
                <div class="card-stripe ${getStripeClass(pedido.estado)}"></div>
                <div class="card-body">
                    <div class="order-left">
                        <div class="order-top">
                            <span class="order-code">${code}</span>
                            <span class="status-badge ${getBadgeClass(pedido.estado)}">
                                <span class="status-dot-sm"></span>
                                ${getStatusLabel(pedido.estado)}
                            </span>
                        </div>
                        <div class="order-client">${pedido.cliente_nombre}</div>
                        <div class="order-meta">
                            <span class="meta-chip">🕒 ${formatHour(pedido.created_at)}</span>
                        </div>
                    </div>
                    <div class="order-right">
                        <div class="price-block">
                            <div class="price">$${formatMoney(pedido.total)}</div>
                            <div class="time">Ahora mismo</div>
                        </div>
                        <div class="action-btn" title="Ver detalle">›</div>
                    </div>
                </div>
            `;

            const emptyState = ordersList.querySelector('.empty-state');
            if (emptyState) ordersList.innerHTML = '';

            ordersList.prepend(card);
        }

        function showToast(message) {
            toastMessage.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 4500);
        }

        if (window.Echo) {
            window.Echo.channel('pedidos')
                .listen('.pedido.confirmado', (event) => {
                    addOrderCard(event);
                    showToast(`Pedido de ${event.cliente_nombre} agregado.`);
                });
        }
    </script>
</body>
</html>