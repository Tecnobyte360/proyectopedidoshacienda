<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Seguimiento del pedido' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    @php
        $tenant = isset($pedido) && $pedido?->tenant_id
            ? \App\Models\Tenant::withoutGlobalScopes()->find($pedido->tenant_id)
            : null;
        $primario   = $tenant?->color_primario   ?: '#d68643';
        $secundario = $tenant?->color_secundario ?: '#a85f24';
    @endphp

    <style>
        :root {
            --brand-primary:   {{ $primario }};
            --brand-secondary: {{ $secundario }};
        }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            color: #1e293b;
            background:
                linear-gradient(135deg,
                    color-mix(in srgb, var(--brand-primary) 14%, white) 0%,
                    #ffffff 50%,
                    color-mix(in srgb, var(--brand-secondary) 12%, white) 100%);
        }
        .public-header {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid #e2e8f0;
        }
        .public-container { max-width: 48rem; margin: 0 auto; padding-left: 1rem; padding-right: 1rem; }
        .public-row { display: flex; align-items: center; gap: 0.75rem; }
        .public-pad-y { padding-top: 1rem; padding-bottom: 1rem; }
        .brand-badge {
            height: 2.5rem; width: 2.5rem; border-radius: 0.75rem;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.125rem; flex-shrink: 0;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
        }
        .brand-logo {
            height: 2.5rem; width: 2.5rem; border-radius: 0.75rem; object-fit: contain;
            background: #fff; border: 1px solid #f1f5f9; flex-shrink: 0;
        }
        .public-title {
            font-weight: 800; color: #1e293b; margin: 0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .public-subtitle { font-size: 0.75rem; color: #64748b; margin: 0; }
        .public-main { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        .public-footer {
            padding-top: 1.5rem; padding-bottom: 1.5rem;
            text-align: center; font-size: 0.75rem; color: #94a3b8;
        }
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body>

    <header class="public-header">
        <div class="public-container public-row public-pad-y">
            @if($tenant?->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->nombre }}" class="brand-logo">
            @else
                <div class="brand-badge">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
            @endif
            <div style="flex:1; min-width:0;">
                <h1 class="public-title">{{ $tenant?->nombre ?? 'Seguimiento de pedido' }}</h1>
                <p class="public-subtitle">Estado de tu pedido en tiempo real</p>
            </div>
        </div>
    </header>

    <main class="public-container public-main">
        {{ $slot }}
    </main>

    <footer class="public-container public-footer">
        © {{ date('Y') }} {{ $tenant?->nombre ?? 'TecnoByte360' }} · Powered by TecnoByte360
    </footer>

    @livewireScripts
    @stack('scripts')
</body>
</html>
