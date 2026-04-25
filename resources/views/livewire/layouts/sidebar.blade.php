<div>
    @php
        $tenantActivo = app(\App\Services\TenantManager::class)->current();
        $primario  = $tenantActivo?->color_primario   ?: '#d68643';
        $secundario= $tenantActivo?->color_secundario ?: '#a85f24';
        $brandName = $tenantActivo?->nombre ?: 'TecnoByte360';
        $brandSub  = $tenantActivo ? 'Panel del cliente' : 'Plataforma SaaS';
        $brandLogo = $tenantActivo?->logo_url;

        $u = auth()->user();
        $userName = $u?->name ?: 'Usuario';
        $iniciales = collect(explode(' ', trim($userName)))
            ->filter()
            ->take(2)
            ->map(fn ($s) => mb_strtoupper(mb_substr($s, 0, 1)))
            ->join('');
        $rolPrincipal = $u && method_exists($u, 'getRoleNames')
            ? ($u->getRoleNames()->first() ?: 'Miembro')
            : 'Miembro';
        $rolPrincipal = ucfirst(str_replace('-', ' ', (string) $rolPrincipal));

        $current = request()->route()?->getName();
        $enSubdominioTenant = $tenantActivo !== null;
        $esSuperAdmin = $u && $u->tenant_id === null && $u->hasRole('super-admin');
        $estaImpersonando = session()->has('tenant_imitado_id');
        $verSoloAdmin = $esSuperAdmin && !$estaImpersonando;

        $sectionsRaw = [
            [
                'title' => 'Principal',
                'items' => [
                    ['name' => 'Pedidos',      'icon' => 'fa-bag-shopping', 'route' => 'pedidos.index',      'badge' => null,  'permission' => 'pedidos.ver'],
                    ['name' => 'Chat en vivo', 'icon' => 'fa-headset',      'route' => 'chat.index',         'badge' => null,  'permission' => 'chat.usar'],
                    ['name' => 'Despachos',    'icon' => 'fa-paper-plane',  'route' => 'despachos.index',    'badge' => null,  'permission' => 'despachos.gestionar'],
                ],
            ],
            [
                'title' => 'Catálogo',
                'items' => [
                    ['name' => 'Productos',     'icon' => 'fa-box',          'route' => 'productos.index',     'badge' => null, 'permission' => 'productos.ver'],
                    ['name' => 'Categorías',    'icon' => 'fa-layer-group',  'route' => 'categorias.index',    'badge' => null, 'permission' => 'categorias.gestionar'],
                    ['name' => 'Cortes',        'icon' => 'fa-scissors',     'route' => 'cortes.index',        'badge' => null, 'permission' => 'productos.ver'],
                    ['name' => 'Promociones',   'icon' => 'fa-tags',         'route' => 'promociones.index',   'badge' => null, 'permission' => 'promociones.gestionar'],
                    ['name' => 'Importaciones', 'icon' => 'fa-file-import',  'route' => 'importaciones.index', 'badge' => null, 'permission' => 'productos.ver'],
                    ['name' => 'Integraciones', 'icon' => 'fa-plug',         'route' => 'integraciones.index', 'badge' => null, 'permission' => 'productos.ver', 'solo_super_admin' => true],
                ],
            ],
            [
                'title' => 'Operaciones',
                'items' => [
                    ['name' => 'Clientes',          'icon' => 'fa-users',              'route' => 'clientes.index',         'badge' => null, 'permission' => 'clientes.ver'],
                    ['name' => 'Conversaciones',    'icon' => 'fa-comments',           'route' => 'conversaciones.index',   'badge' => null, 'permission' => 'conversaciones.ver'],
                    ['name' => 'Usuarios internos', 'icon' => 'fa-user-shield',        'route' => 'usuarios-internos.index','badge' => null, 'permission' => 'conversaciones.ver'],
                    ['name' => 'Departamentos',     'icon' => 'fa-building-user',      'route' => 'departamentos.index',    'badge' => null, 'permission' => 'conversaciones.ver'],
                    ['name' => 'Campañas WhatsApp', 'icon' => 'fa-bullhorn',           'route' => 'campanas.index',         'badge' => null, 'permission' => 'conversaciones.ver'],
                    ['name' => 'Widgets de Chat',   'icon' => 'fa-code',               'route' => 'chat-widgets.index',     'badge' => null, 'permission' => 'conversaciones.ver', 'solo_super_admin' => true],
                    ['name' => 'Domiciliarios',     'icon' => 'fa-motorcycle',         'route' => 'domiciliarios.index',    'badge' => null, 'permission' => 'domiciliarios.gestionar'],
                    ['name' => 'Zonas',             'icon' => 'fa-map-location-dot',   'route' => 'zonas.index',            'badge' => null, 'permission' => 'zonas.gestionar'],
                    ['name' => 'Reportes',          'icon' => 'fa-chart-line',         'route' => 'reportes.index',         'badge' => null, 'permission' => 'reportes.ver'],
                    ['name' => 'ANS Tiempos',       'icon' => 'fa-stopwatch',          'route' => 'ans.index',              'badge' => null, 'permission' => 'ans.gestionar'],
                    ['name' => 'Bot WhatsApp',      'icon' => 'fa-robot',              'route' => 'configuracion.bot',      'badge' => null, 'permission' => 'bot.configurar', 'solo_super_admin' => true],
                    ['name' => 'Sedes',             'icon' => 'fa-shop',               'route' => 'sedes.index',            'badge' => null, 'permission' => 'sedes.gestionar'],
                ],
            ],
            [
                'title' => 'Sistema',
                'items' => [
                    ['name' => 'Alertas del bot', 'icon' => 'fa-triangle-exclamation', 'route' => 'alertas.index',
                        'badge' => (\Schema::hasTable('bot_alertas')
                            ? (\App\Models\BotAlerta::where('resuelta', false)->count() ?: null)
                            : null),
                        'permission' => 'alertas.ver', 'solo_super_admin' => true],
                    ['name' => 'Felicitaciones', 'icon' => 'fa-cake-candles', 'route' => 'felicitaciones.index', 'badge' => null, 'permission' => 'felicitaciones.ver'],
                    ['name' => 'Usuarios',       'icon' => 'fa-users-gear',   'route' => 'usuarios.index',       'badge' => null, 'permission' => 'usuarios.ver'],
                    ...($enSubdominioTenant ? [] : [
                        ['name' => 'Roles y permisos','icon' => 'fa-shield-halved','route' => 'roles.index', 'badge' => null, 'permission' => 'roles.gestionar'],
                    ]),
                ],
            ],
            [
                'title' => 'Super Admin',
                'items' => [
                    ['name' => 'Tenants',       'icon' => 'fa-building',           'route' => 'admin.tenants.index',       'badge' => null, 'permission' => 'tenants.gestionar'],
                    ['name' => 'Planes',        'icon' => 'fa-money-check-dollar', 'route' => 'admin.planes.index',        'badge' => null, 'permission' => 'planes.gestionar'],
                    ['name' => 'Suscripciones', 'icon' => 'fa-receipt',            'route' => 'admin.suscripciones.index', 'badge' => null, 'permission' => 'suscripciones.gestionar'],
                    ['name' => 'Pagos',         'icon' => 'fa-money-bills',        'route' => 'admin.pagos.index',         'badge' => null, 'permission' => 'pagos.gestionar'],
                    ['name' => 'Documentación', 'icon' => 'fa-book-open',          'route' => 'admin.documentacion',       'badge' => null, 'permission' => 'tenants.gestionar'],
                ],
            ],
        ];

        $sections = [];
        foreach ($sectionsRaw as $sec) {
            if ($sec['title'] === 'Super Admin' && $enSubdominioTenant) continue;
            if ($verSoloAdmin && $sec['title'] !== 'Super Admin') continue;

            $items = array_values(array_filter($sec['items'], function ($it) use ($u) {
                if (!empty($it['solo_super_admin'])) {
                    if (!$u || !$u->hasRole('super-admin')) return false;
                }
                return !$u || empty($it['permission']) || $u->can($it['permission']);
            }));
            if (count($items) > 0) {
                $sec['items'] = $items;
                $sections[] = $sec;
            }
        }
    @endphp

    <style>
        /* Sidebar — toma el color del tenant para sentirse parte de su marca.
           El fondo es un tinte oscuro del color primario, no negro puro,
           más amable a la vista en sesiones largas. */
        .app-sidebar {
            --brand-primary:   {{ $primario }};
            --brand-secondary: {{ $secundario }};

            /* Fondo: 88% slate + 12% del color del tenant → dark suave con tinte */
            --sb-bg:   color-mix(in srgb, var(--brand-primary) 12%, #1f242c);
            --sb-bg-2: color-mix(in srgb, var(--brand-secondary) 8%, #2a3038);

            --sb-line:      rgba(255,255,255,0.07);
            --sb-text:      rgba(255,255,255,0.72);
            --sb-text-soft: rgba(255,255,255,0.50);
            --sb-text-strong: #ffffff;
            --sb-hover:     rgba(255,255,255,0.06);
            --sb-active:    color-mix(in srgb, var(--brand-primary) 22%, transparent);

            background: linear-gradient(180deg, var(--sb-bg) 0%, var(--sb-bg-2) 100%);
            color: var(--sb-text);
        }
        .sb-brand-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.625rem;
            padding: 0.5rem 0.5rem 0;
        }
        .sb-brand-logo {
            width: 4.5rem;            /* 72px — protagonista */
            height: 4.5rem;
            border-radius: 1.125rem;  /* 18px */
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,
                color-mix(in srgb, var(--brand-primary) 22%, #ffffff15) 0%,
                color-mix(in srgb, var(--brand-secondary) 22%, #ffffff10) 100%);
            border: 1px solid rgba(255,255,255,0.10);
            padding: 0.5rem;
            flex-shrink: 0;
            box-shadow: 0 6px 20px -6px rgba(0,0,0,0.55),
                        inset 0 1px 0 rgba(255,255,255,0.10);
        }
        .sb-brand {
            color: var(--brand-primary);
            font-weight: 800;
            letter-spacing: -0.018em;
            font-size: 0.95rem;
            text-align: center;
            line-height: 1.2;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sb-brand-sub {
            font-size: 0.6875rem;
            color: var(--sb-text-soft);
            text-align: center;
            font-weight: 500;
            letter-spacing: 0.02em;
        }
        body.sidebar-collapsed #sb-collapsed-logo { display: flex !important; }
        body.sidebar-collapsed #sb-collapsed-logo .sb-brand-logo {
            width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; padding: 0.25rem;
        }
        .sb-collapse-btn {
            background: rgba(255,255,255,0.05);
            color: var(--sb-text);
            transition: all 0.15s ease;
        }
        .sb-collapse-btn:hover { background: rgba(255,255,255,0.10); color: var(--sb-text-strong); }

        .sb-user-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--sb-line);
            border-radius: 0.875rem;
        }
        .sb-avatar {
            width: 2.625rem;
            height: 2.625rem;
            border-radius: 999px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: white;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            flex-shrink: 0;
        }
        .sb-search {
            background: rgba(255,255,255,0.04);
            border: 1px solid transparent;
            color: var(--sb-text);
            transition: all 0.15s ease;
        }
        .sb-search::placeholder { color: var(--sb-text-soft); }
        .sb-search:focus {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.10);
            outline: none;
        }
        .sb-section-title {
            font-size: 0.6875rem;        /* 11px */
            font-weight: 600;
            color: var(--sb-text-soft);
            letter-spacing: 0.04em;
            text-transform: capitalize;
            padding: 0 0.875rem;
            margin-bottom: 0.375rem;
        }
        .sb-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.5rem 0.875rem;
            border-radius: 0.625rem;
            color: var(--sb-text);
            font-size: 0.8125rem;        /* 13px — compacto */
            font-weight: 500;
            transition: all 0.12s ease;
            position: relative;
            text-decoration: none;
        }
        .sb-item:hover {
            background: var(--sb-hover);
            color: var(--sb-text-strong);
        }
        .sb-item .sb-icon {
            width: 1.125rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--sb-text-soft);
            transition: color 0.12s ease;
        }
        .sb-item:hover .sb-icon { color: var(--sb-text-strong); }

        .sb-item.is-active {
            background: var(--sb-active);
            color: var(--sb-text-strong);
            font-weight: 600;
        }
        .sb-item.is-active::before {
            content: '';
            position: absolute;
            left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 70%;
            background: var(--brand-primary);
            border-radius: 0 3px 3px 0;
            box-shadow: 0 0 12px color-mix(in srgb, var(--brand-primary) 60%, transparent);
        }
        .sb-item.is-active .sb-icon { color: var(--brand-primary); }

        .sb-badge {
            margin-left: auto;
            background: var(--brand-primary);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 0.0625rem 0.4375rem;
            border-radius: 999px;
            min-width: 1.25rem;
            text-align: center;
        }
        .sb-chevron {
            margin-left: auto;
            font-size: 0.625rem;
            color: var(--sb-text-soft);
        }

        .sb-footer-item {
            display: flex; align-items: center; gap: 0.625rem;
            padding: 0.5rem 0.625rem;
            border-radius: 0.625rem;
            color: var(--sb-text);
            font-size: 0.8125rem;
            transition: all 0.12s ease;
        }
        .sb-footer-item:hover { background: var(--sb-hover); color: var(--sb-text-strong); }

        /* Cuando body.sidebar-collapsed está activo */
        body.sidebar-collapsed .app-sidebar {
            width: 4.5rem;
        }
        body.sidebar-collapsed .app-sidebar .sb-collapsible { display: none; }
        body.sidebar-collapsed .app-sidebar .sb-item { justify-content: center; padding: 0.625rem; }
        body.sidebar-collapsed .app-sidebar .sb-section-title { display: none; }
        body.sidebar-collapsed main { padding-left: 4.5rem !important; }

        /* Custom scrollbar */
        .app-sidebar nav::-webkit-scrollbar { width: 6px; }
        .app-sidebar nav::-webkit-scrollbar-track { background: transparent; }
        .app-sidebar nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }
        .app-sidebar nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.16); }
    </style>

    {{-- ╔═══ SIDEBAR DESKTOP ═══╗ --}}
    <aside class="app-sidebar fixed inset-y-0 left-0 z-40 hidden lg:flex w-64 flex-col"
           x-data="{ q: '' }">

        {{-- HEADER: collapse arriba a la derecha --}}
        <div class="flex items-center justify-end px-4 pt-3 pb-1 sb-collapsible">
            <button onclick="document.body.classList.toggle('sidebar-collapsed')"
                    class="sb-collapse-btn h-7 w-7 inline-flex items-center justify-center rounded-lg"
                    title="Colapsar sidebar">
                <i class="fa-solid fa-angles-left text-[11px]"></i>
            </button>
        </div>

        {{-- BRAND: logo arriba grande, nombre y sub debajo --}}
        <div class="sb-brand-block sb-collapsible">
            <div class="sb-brand-logo">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain rounded-xl">
                @else
                    <span class="text-2xl font-extrabold text-white">
                        {{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}
                    </span>
                @endif
            </div>
            <div class="flex flex-col items-center gap-0.5">
                <span class="sb-brand">{{ $brandName }}</span>
                <span class="sb-brand-sub">{{ $brandSub }}</span>
            </div>
        </div>

        {{-- Mini logo cuando está colapsado --}}
        <div class="hidden flex-col items-center pt-3 pb-2" id="sb-collapsed-logo">
            <div class="sb-brand-logo">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain rounded-lg">
                @else
                    <span class="text-base font-extrabold text-white">{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}</span>
                @endif
            </div>
        </div>

        {{-- SEARCH --}}
        <div class="px-4 mt-4 sb-collapsible">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[12px]" style="color: var(--sb-text-soft);"></i>
                <input type="text" x-model="q" placeholder="Buscar…"
                       class="sb-search w-full rounded-xl pl-9 pr-3 py-2 text-[13px]">
            </div>
        </div>

        {{-- NAV --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5 mt-1">
            @foreach($sections as $section)
                <div x-show="q === '' || '{{ mb_strtolower($section['title']) }}'.includes(q.toLowerCase()) || [{!! collect($section['items'])->map(fn($i) => "'" . mb_strtolower($i['name']) . "'")->join(',') !!}].some(n => n.includes(q.toLowerCase()))">
                    <div class="sb-section-title sb-collapsible">{{ $section['title'] }}</div>
                    <div class="space-y-0.5">
                        @foreach($section['items'] as $item)
                            @php
                                $isActive = $item['route'] && $current === $item['route'];
                                $href     = $item['route'] ? route($item['route']) : '#';
                            @endphp
                            <a href="{{ $href }}"
                               x-show="q === '' || '{{ mb_strtolower($item['name']) }}'.includes(q.toLowerCase())"
                               class="sb-item {{ $isActive ? 'is-active' : '' }}"
                               title="{{ $item['name'] }}">
                                <i class="fa-solid {{ $item['icon'] }} sb-icon"></i>
                                <span class="sb-collapsible flex-1 truncate">{{ $item['name'] }}</span>
                                @if($item['badge'])
                                    <span class="sb-badge sb-collapsible">{{ $item['badge'] }}</span>
                                @elseif($isActive)
                                    <i class="fa-solid fa-chevron-right sb-chevron sb-collapsible"></i>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- FOOTER --}}
        <div class="border-t px-3 py-3 space-y-1" style="border-color: var(--sb-line);">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sb-footer-item w-full text-left">
                    <i class="fa-solid fa-arrow-right-from-bracket sb-icon"></i>
                    <span class="sb-collapsible">Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- ╔═══ MOBILE DRAWER ═══╗ --}}
    <aside id="mobile-sidebar" class="app-sidebar fixed inset-y-0 left-0 z-50 w-72 transform -translate-x-full transition-transform duration-300 flex flex-col lg:hidden">
        <div class="flex items-center justify-end px-4 pt-3 pb-1">
            <button onclick="document.getElementById('mobile-sidebar').classList.add('-translate-x-full'); document.getElementById('mobile-backdrop').classList.add('hidden');"
                    class="sb-collapse-btn h-7 w-7 inline-flex items-center justify-center rounded-lg">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>
        <div class="sb-brand-block">
            <div class="sb-brand-logo">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain rounded-xl">
                @else
                    <span class="text-2xl font-extrabold text-white">{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}</span>
                @endif
            </div>
            <div class="flex flex-col items-center gap-0.5">
                <span class="sb-brand">{{ $brandName }}</span>
                <span class="sb-brand-sub">{{ $brandSub }}</span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5 mt-4">
            @foreach($sections as $section)
                <div>
                    <div class="sb-section-title">{{ $section['title'] }}</div>
                    <div class="space-y-0.5">
                        @foreach($section['items'] as $item)
                            @php
                                $isActive = $item['route'] && $current === $item['route'];
                                $href     = $item['route'] ? route($item['route']) : '#';
                            @endphp
                            <a href="{{ $href }}" class="sb-item {{ $isActive ? 'is-active' : '' }}">
                                <i class="fa-solid {{ $item['icon'] }} sb-icon"></i>
                                <span class="flex-1 truncate">{{ $item['name'] }}</span>
                                @if($item['badge'])
                                    <span class="sb-badge">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="border-t px-3 py-3" style="border-color: var(--sb-line);">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sb-footer-item w-full text-left">
                    <i class="fa-solid fa-arrow-right-from-bracket sb-icon"></i>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <div id="mobile-backdrop"
         onclick="document.getElementById('mobile-sidebar').classList.add('-translate-x-full'); this.classList.add('hidden');"
         class="hidden fixed inset-0 z-40 bg-black/50 lg:hidden"></div>
</div>
