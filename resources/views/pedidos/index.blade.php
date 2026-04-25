<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Pedidos WhatsApp – Tiempo Real</title>

    <meta name="csrf-token" content="{{ csrf_token() }}" />

    @vite(['resources/css/app.css'])
    
    <!-- ✅ ECHO / REVERB -->
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0-rc2/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    
<script>
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: 'app-key',
        wsHost: 'pedidosonline.tecnobyte360.com',
        wsPort: 443,
        wssPort: 443,
        forceTLS: true,
       enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });

    console.log('✅ Echo configurado y listo');
</script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            padding: 0.75rem 1rem;
            color: #111827;
        }

        .app-shell {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
        }

        /* ─── HEADER SUPERIOR ───────── */

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .app-header-left {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .app-header-title-row {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .app-title-label {
            font-size: 1.3rem;
            font-weight: 600;
            color: #111827;
        }

        .app-header-dropdown {
            border: none;
            background: transparent;
            font-size: 1.3rem;
            font-weight: 600;
            color: #111827;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
        }

        .app-header-dropdown span {
            font-size: 0.9rem;
            color: #9ca3af;
        }

        .app-header-meta {
            font-size: 0.8rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .app-header-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #22c55e;
        }

        .app-header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-ghost,
        .btn-primary {
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 999px;
            padding: 0.45rem 0.9rem;
            border: 1px solid transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-ghost {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #374151;
        }

        .btn-ghost:hover {
            background: #e5e7eb;
        }

        .btn-primary {
            background: #111827;
            color: white;
            border-color: #111827;
        }

        .btn-primary:hover {
            background: #030712;
        }

        /* ─── TARJETA DE RESUMEN ───────── */

        .summary-card {
            background: #f9fafb;
            border-radius: 14px;
            padding: 0.9rem 1.1rem 1.1rem;
            border: 1px solid #e5e7eb;
            margin-bottom: 1rem;
            width: 100%;
        }

        .summary-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }

        .range-button {
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: white;
            font-size: 0.8rem;
            padding: 0.3rem 0.75rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            color: #374151;
        }

        .summary-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.75rem;
        }

        .summary-metric {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .metric-label {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .metric-value-row {
            display: flex;
            align-items: baseline;
            gap: 0.3rem;
        }

        .metric-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #111827;
        }

        .metric-trend {
            font-size: 0.75rem;
            color: #16a34a;
            display: inline-flex;
            align-items: center;
            gap: 0.18rem;
        }

        /* ─── CONTENEDOR TABLA ───────── */

        .table-card {
            background: white;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            width: 100%;
        }

        .filters-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.4rem 0.75rem 0.35rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            gap: 0.5rem;
        }

        .filters-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .filter-pill {
            border-radius: 999px;
            border: 1px solid transparent;
            background: transparent;
            padding: 0.3rem 0.7rem;
            font-size: 0.78rem;
            color: #4b5563;
            cursor: pointer;
        }

        .filter-pill:hover {
            background: #e5e7eb;
        }

        .filter-pill.active {
            background: #111827;
            color: white;
        }

        .filters-actions {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .icon-button {
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: white;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .table-header {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.8rem;
            color: #6b7280;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        thead {
            background: #f9fafb;
        }

        th {
            padding: 0.7rem 1rem;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.15s ease;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
            font-size: 0.86rem;
            color: #111827;
        }

        .id-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 0.8rem;
            font-weight: 500;
            color: #4b5563;
        }

        .cliente-cell {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .cliente-avatar {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
        }

        .cliente-name {
            font-weight: 500;
            color: #111827;
        }

        .telefono-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #15803d;
            font-size: 0.78rem;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
        }

        .productos-container {
            min-width: 260px;
        }

        .productos-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .producto-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.55rem;
            background: #f3f4f6;
            border-radius: 999px;
            font-size: 0.78rem;
        }

        .producto-cantidad {
            min-width: 20px;
            height: 20px;
            border-radius: 999px;
            background: #fef3c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #92400e;
        }

        .producto-unidad {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
        }

        .producto-nombre {
            font-size: 0.78rem;
            font-weight: 500;
        }

        .sede-badge,
        .hora-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #4b5563;
            white-space: nowrap;
        }

        .hora-badge {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .estado-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .estado-badge.confirmado {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bfdbfe;
        }

        .estado-badge.pendiente {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .estado-badge.completado {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .estado-badge.cancelado {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .total-cell {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .fecha-cell {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .fecha-hora {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            background: white;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.25);
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            max-width: 380px;
            transform: translateX(500px);
            transition: transform 0.4s cubic-bezier(0.68,-0.55,0.265,1.55);
            z-index: 1000;
            border-left: 4px solid #16a34a;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast-icon {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            background: #16a34a;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .toast-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .toast-message {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .toast-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 0.9rem;
        }

      .resumen-cell {
    max-width: 260px;
    max-height: 90px;          /* altura visible */
    overflow-y: auto;          /* ✅ scroll vertical */
    font-size: 0.8rem;
    color: #4b5563;
    line-height: 1.35;
    white-space: normal;       /* ✅ permite salto de línea */
}



        /* ─── RESPONSIVE ───────── */

        @media (max-width: 900px) {
            .summary-top {
                flex-direction: row;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0.5rem;
            }

            .app-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .app-header-actions {
                width: 100%;
                justify-content: flex-start;
            }

            table {
                min-width: 720px;
            }

            .toast {
                right: 1rem;
                left: 1rem;
                bottom: 1rem;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .filters-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .telefono-link {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="app-shell">

    <!-- HEADER SUPERIOR -->
    <header class="app-header">
        <div class="app-header-left">
            <div class="app-header-title-row">
                <span class="app-title-label">Pedidos:</span>
                <button class="app-header-dropdown">
                    Todas las sucursales
                    <span>▾</span>
                </button>
            </div>
            <div class="app-header-meta">
                <span class="app-header-dot"></span>
                <span>Sistema activo · Actualizado en tiempo real</span>
            </div>
        </div>

        <div class="app-header-actions">
            <button class="btn-ghost">Exportar</button>
            <button class="btn-ghost">Más acciones ▾</button>
            <button class="btn-primary">Crear pedido</button>
        </div>
    </header>

    <!-- TARJETA RESUMEN -->
    <section class="summary-card">
        <div class="summary-top">
            <button class="range-button">
                30 días ▾
            </button>
        </div>

        <div class="summary-metrics">
            <div class="summary-metric">
                <span class="metric-label">Pedidos</span>
                <div class="metric-value-row">
                    <span class="metric-value" id="count-total-top">{{ $pedidos->count() }}</span>
                </div>
                <span class="metric-trend">▲ 0%</span>
            </div>

            <div class="summary-metric">
                <span class="metric-label">Pedidos hoy</span>
                <div class="metric-value-row">
                    <span class="metric-value" id="count-today">
                        {{ $pedidos->where('fecha_pedido', '>=', now()->startOfDay())->count() }}
                    </span>
                </div>
                <span class="metric-trend">▲ 0%</span>
            </div>

            <div class="summary-metric">
                <span class="metric-label">Confirmados</span>
                <div class="metric-value-row">
                    <span class="metric-value" id="count-confirmed">
                        {{ $pedidos->where('estado', 'confirmado')->count() }}
                    </span>
                </div>
                <span class="metric-trend">▲ 0%</span>
            </div>

            <div class="summary-metric">
                <span class="metric-label">En proceso</span>
                <div class="metric-value-row">
                    <span class="metric-value" id="count-processing">
                        {{ $pedidos->whereIn('estado', ['pendiente', 'en_preparacion'])->count() }}
                    </span>
                </div>
                <span class="metric-trend">▲ 0%</span>
            </div>

            <div class="summary-metric">
                <span class="metric-label">Entregados</span>
                <div class="metric-value-row">
                    <span class="metric-value">
                        {{ $pedidos->where('estado', 'completado')->count() }}
                    </span>
                </div>
                <span class="metric-trend">▲ 0%</span>
            </div>

            <div class="summary-metric">
                <span class="metric-label">Cancelados</span>
                <div class="metric-value-row">
                    <span class="metric-value">
                        {{ $pedidos->where('estado', 'cancelado')->count() }}
                    </span>
                </div>
                <span class="metric-trend" style="color:#dc2626;">▼ 0%</span>
            </div>
        </div>
    </section>

    <!-- TABLA -->
    <section class="table-card">
        <!-- Filtros -->
        <div class="filters-row">
            <div class="filters-tabs">
                <button class="filter-pill active">Todo</button>
                <button class="filter-pill">No preparado</button>
                <button class="filter-pill">Sin pagar</button>
                <button class="filter-pill">Abierto</button>
                <button class="filter-pill">Cerrado</button>
                <button class="filter-pill">Automatizaciones</button>
                <button class="filter-pill">Entrega local</button>
            </div>
            <div class="filters-actions">
                <button class="icon-button">🔍</button>
                <button class="icon-button">⟳</button>
            </div>
        </div>

        <div class="table-header">
            Pedidos en tiempo real desde WhatsApp
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>Productos</th>
                    <th>Resumen</th> <!-- 👈 NUEVO -->
                    <th>Sede</th>
                    <th>Hora entrega</th>
                    <th>Estado</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody id="pedidos-tbody">
                @forelse($pedidos as $pedido)
                    <tr data-pedido-id="{{ $pedido->id }}">
                        <td>
                            <div class="id-badge">#{{ $pedido->id }}</div>
                        </td>
                        <td>
                            <div class="fecha-cell">
                                {{ $pedido->created_at->format('d/m/Y') }}
                                <div class="fecha-hora">{{ $pedido->created_at->format('H:i') }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="cliente-cell">
                                <div class="cliente-avatar">
                                    {{ strtoupper(substr($pedido->cliente_nombre, 0, 1)) }}
                                </div>
                                <span class="cliente-name">{{ $pedido->cliente_nombre }}</span>
                            </div>
                        </td>
                        <td>
                            <a href="https://wa.me/{{ $pedido->telefono }}" target="_blank" class="telefono-link">
                                {{ $pedido->telefono }}
                            </a>
                        </td>
                        <td>
                            <div class="productos-container">
                                <div class="productos-grid">
                                    @foreach($pedido->detalles as $detalle)
                                        <div class="producto-item">
                                    @php
$cantidad = rtrim(rtrim(number_format($detalle->cantidad, 2, ',', '.'), '0'), ',');
@endphp

<div class="producto-cantidad">{{ $cantidad }}</div>
<div class="producto-info">
    <span class="producto-unidad">
        {{ strtoupper($detalle->unidad) }}
    </span>
    <span class="producto-nombre">{{ $detalle->producto }}</span>
</div>

                                            <div class="producto-info">
                                                <span class="producto-unidad">{{ $detalle->unidad }}</span>
                                              
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                       <td>
   <div class="resumen-cell" title="{{ $pedido->resumen_conversacion }}">
    {{ $pedido->resumen_conversacion }}
</div>

</td>

                        <td>
                            <span class="sede-badge">
                                {{ $pedido->sede?->nombre ?? 'N/A' }}
                            </span>
                        </td>
                        <td>
                            <span class="hora-badge">
                                {{ $pedido->hora_entrega }}
                            </span>
                        </td>
                        <td>
                            @php
                                $estados = [
                                    'confirmado' => ['class' => 'confirmado', 'icon' => '●'],
                                    'pendiente' => ['class' => 'pendiente', 'icon' => '●'],
                                    'completado' => ['class' => 'completado', 'icon' => '●'],
                                    'cancelado' => ['class' => 'cancelado', 'icon' => '●'],
                                ];
                                $estado = $estados[$pedido->estado] ?? ['class' => 'pendiente', 'icon' => '●'];
                            @endphp
                            <span class="estado-badge {{ $estado['class'] }}">
                                <span style="font-size:0.6rem;">{{ $estado['icon'] }}</span>
                                <span>{{ ucfirst($pedido->estado) }}</span>
                            </span>
                        </td>
                        <td>
                            <div class="total-cell">
                                ${{ number_format($pedido->total, 0, ',', '.') }}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="empty-state">
                                No hay pedidos todavía. Cuando entren por WhatsApp aparecerán aquí.
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- TOAST -->
<div id="notification-toast" class="toast">
    <div class="toast-icon">
        <svg width="20" height="20" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </div>
    <div>
        <div class="toast-title">¡Nuevo pedido confirmado!</div>
        <div class="toast-message" id="toast-message"></div>
    </div>
    <button class="toast-close" onclick="closeToast()">✕</button>
</div>

<script>
    console.log('🔍 Iniciando sistema de pedidos...');

    if (!window.Echo) {
        console.error('❌ Echo NO está disponible');
    } else {
        console.log('✅ Echo disponible');
        
        window.Echo.channel('pedidos')
            .listen('.pedido.confirmado', (event) => {
                console.log('🎉 Nuevo pedido recibido:', event);
                
                updateCounters();
                addPedidoRow(event);
                showNotification(event);
                playNotificationSound();
            });

        console.log('✅ Escuchando canal: pedidos');
    }

    function addPedidoRow(pedido) {
        const tbody = document.getElementById('pedidos-tbody');
        
        if (tbody.querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }
        
        const estadoConfig = {
            'confirmado': { class: 'confirmado', icon: '●' },
            'pendiente':  { class: 'pendiente',  icon: '●' },
            'completado': { class: 'completado', icon: '●' },
            'cancelado':  { class: 'cancelado',  icon: '●' }
        };
        
        const estado = estadoConfig[pedido.estado] || { class: 'pendiente', icon: '●' };
        
        const productosHTML = pedido.detalles.map(d => 
            `<div class="producto-item">
                <div class="producto-cantidad">${d.cantidad}</div>
                <div class="producto-info">
                    <span class="producto-unidad">${d.unidad}</span>
                    <span class="producto-nombre">${d.producto}</span>
                </div>
            </div>`
        ).join('');

        const firstLetter = pedido.cliente_nombre.charAt(0).toUpperCase();
        const resumen = pedido.resumen_conversacion || '';
        const resumenCorto = resumen.length > 120 ? resumen.substring(0, 117) + '...' : resumen;
        const resumenTitle = resumen.replace(/"/g, '&quot;');
        
        const row = document.createElement('tr');
        row.dataset.pedidoId = pedido.id;
        
        row.innerHTML = `
            <td><div class="id-badge">#${pedido.id}</div></td>
            <td>
                <div class="fecha-cell">
                    ${pedido.created_at}
                </div>
            </td>
            <td>
                <div class="cliente-cell">
                    <div class="cliente-avatar">${firstLetter}</div>
                    <span class="cliente-name">${pedido.cliente_nombre}</span>
                </div>
            </td>
            <td>
                <a href="https://wa.me/${pedido.telefono}" target="_blank" class="telefono-link">
                    ${pedido.telefono}
                </a>
            </td>
            <td>
                <div class="productos-container">
                    <div class="productos-grid">${productosHTML}</div>
                </div>
            </td>
            <td>
                <div class="resumen-cell" title="${resumenTitle}">
                    ${resumenCorto}
                </div>
            </td>
            <td>
                <span class="sede-badge">${pedido.sede}</span>
            </td>
            <td>
                <span class="hora-badge">${pedido.hora_entrega}</span>
            </td>
            <td>
                <span class="estado-badge ${estado.class}">
                    <span style="font-size:0.6rem;">${estado.icon}</span>
                    <span>${pedido.estado.charAt(0).toUpperCase() + pedido.estado.slice(1)}</span>
                </span>
            </td>
            <td>
                <div class="total-cell">$${pedido.total}</div>
            </td>
        `;
        
        tbody.insertBefore(row, tbody.firstChild);
    }

    function showNotification(pedido) {
        const toast = document.getElementById('notification-toast');
        const message = document.getElementById('toast-message');
        
        message.textContent = `Pedido #${pedido.id} - ${pedido.cliente_nombre}`;
        
        toast.classList.add('show');
        
        setTimeout(() => {
            closeToast();
        }, 5000);
    }

    function closeToast() {
        const toast = document.getElementById('notification-toast');
        toast.classList.remove('show');
    }

    function updateCounters() {
        function bump(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const current = parseInt(el.textContent || '0');
            el.textContent = current + 1;
        }
        bump('count-today');
        bump('count-confirmed');
        bump('count-total-top');
    }

    function playNotificationSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const playTone = (frequency, startTime, duration) => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                oscillator.frequency.value = frequency;
                oscillator.type = 'sine';
                gainNode.gain.setValueAtTime(0.2, audioContext.currentTime + startTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + startTime + duration);
                oscillator.start(audioContext.currentTime + startTime);
                oscillator.stop(audioContext.currentTime + startTime + duration);
            };
            playTone(523.25, 0, 0.15);
            playTone(659.25, 0.1, 0.15);
            playTone(783.99, 0.2, 0.25);
        } catch (error) {
            console.log('No se pudo reproducir el sonido:', error);
        }
    }
</script>
</body>
</html>
