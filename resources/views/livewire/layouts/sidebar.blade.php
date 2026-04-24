<div>
    {{-- Wrapper Livewire requiere UN solo root element --}}

    @php
        // 🎨 Branding dinámico: si hay tenant activo, usar sus colores y logo
        $tenantActivo = app(\App\Services\TenantManager::class)->current();
        $bgFrom    = $tenantActivo?->color_primario   ?: '#c97a36';
        $bgTo      = $tenantActivo?->color_secundario ?: '#a85f24';
        $brandName = $tenantActivo?->nombre ?: 'TecnoByte360';
        $brandSub  = $tenantActivo ? 'Panel del cliente' : 'Plataforma SaaS';
        $brandLogo = $tenantActivo?->logo_url;
    @endphp

    <aside class="app-sidebar fixed inset-y-0 left-0 z-40 hidden lg:flex w-64 flex-col text-white shadow-2xl"
           style="background: linear-gradient(to bottom, {{ $bgFrom }}, {{ $bgTo }});">

        {{-- LOGO / BRAND --}}
        <div class="flex h-20 items-center gap-3 border-b border-white/10 px-5">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 shadow-lg backdrop-blur overflow-hidden flex-shrink-0">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="logo" class="h-full w-full object-contain">
                @else
                    <i class="fa-solid fa-utensils text-lg text-white"></i>
                @endif
            </div>
            <div class="min-w-0">
                <div class="truncate text-sm font-extrabold leading-tight">{{ $brandName }}</div>
                <div class="truncate text-xs font-medium text-white/70 leading-tight">{{ $brandSub }}</div>
            </div>
        </div>

        @php
            $current = request()->route()?->getName();
            $u = auth()->user();
            // 🔒 Si estamos en un subdominio de tenant, NUNCA mostrar la sección Super Admin,
            // aunque el usuario logueado sea super-admin (caso raro de seguridad).
            $enSubdominioTenant = $tenantActivo !== null;

            // 🌟 SUPER-ADMIN: si está sin impersonar, NO ve secciones operativas
            // (Pedidos, Chat, Productos, etc). Solo ve sección "Super Admin".
            // Cuando hace "Ver como" (impersonación), sí ve los menús del tenant.
            $esSuperAdmin = $u && $u->tenant_id === null && $u->hasRole('super-admin');
            $estaImpersonando = session()->has('tenant_imitado_id');
            $verSoloAdmin = $esSuperAdmin && !$estaImpersonando;

            // Cada item incluye 'permission' — el sidebar lo filtra automáticamente.
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
                        ['name' => 'Promociones',   'icon' => 'fa-tags',         'route' => 'promociones.index',   'badge' => null, 'permission' => 'promociones.gestionar'],
                        ['name' => 'Importaciones', 'icon' => 'fa-file-import',  'route' => 'importaciones.index', 'badge' => null, 'permission' => 'productos.ver'],
                        ['name' => 'Integraciones', 'icon' => 'fa-plug',         'route' => 'integraciones.index', 'badge' => null, 'permission' => 'productos.ver'],
                    ],
                ],
                [
                    'title' => 'Operaciones',
                    'items' => [
                        ['name' => 'Clientes',       'icon' => 'fa-users',              'route' => 'clientes.index',      'badge' => null, 'permission' => 'clientes.ver'],
                        ['name' => 'Conversaciones', 'icon' => 'fa-comments',           'route' => 'conversaciones.index','badge' => null, 'permission' => 'conversaciones.ver'],
                        ['name' => 'Domiciliarios', 'icon' => 'fa-motorcycle',          'route' => 'domiciliarios.index', 'badge' => null, 'permission' => 'domiciliarios.gestionar'],
                        ['name' => 'Zonas',         'icon' => 'fa-map-location-dot',    'route' => 'zonas.index',         'badge' => null, 'permission' => 'zonas.gestionar'],
                        ['name' => 'Reportes',      'icon' => 'fa-chart-line',          'route' => 'reportes.index',      'badge' => null, 'permission' => 'reportes.ver'],
                        ['name' => 'ANS Tiempos',   'icon' => 'fa-stopwatch',           'route' => 'ans.index',           'badge' => null, 'permission' => 'ans.gestionar'],
                        ['name' => 'Bot WhatsApp',  'icon' => 'fa-robot',               'route' => 'configuracion.bot',   'badge' => null, 'permission' => 'bot.configurar'],
                        ['name' => 'Sedes',         'icon' => 'fa-shop',                'route' => 'sedes.index',         'badge' => null, 'permission' => 'sedes.gestionar'],
                    ],
                ],
                [
                    'title' => 'Sistema',
                    'items' => [
                        ['name' => 'Alertas del bot', 'icon' => 'fa-triangle-exclamation', 'route' => 'alertas.index',
                            'badge' => (\Schema::hasTable('bot_alertas')
                                ? (\App\Models\BotAlerta::where('resuelta', false)->count() ?: null)
                                : null),
                            'permission' => 'alertas.ver'],
                        ['name' => 'Felicitaciones', 'icon' => 'fa-cake-candles', 'route' => 'felicitaciones.index', 'badge' => null, 'permission' => 'felicitaciones.ver'],
                        ['name' => 'Usuarios',       'icon' => 'fa-users-gear',   'route' => 'usuarios.index',       'badge' => null, 'permission' => 'usuarios.ver'],
                        // Roles globales — solo visible desde dominio principal (NO en subdominios de tenant)
                        ...($enSubdominioTenant ? [] : [
                            ['name' => 'Roles y permisos','icon' => 'fa-shield-halved','route' => 'roles.index', 'badge' => null, 'permission' => 'roles.gestionar'],
                        ]),
                    ],
                ],
                [
                    'title' => '⭐ Super Admin',
                    'items' => [
                        ['name' => 'Tenants',       'icon' => 'fa-building',           'route' => 'admin.tenants.index',       'badge' => null, 'permission' => 'tenants.gestionar'],
                        ['name' => 'Planes',        'icon' => 'fa-money-check-dollar', 'route' => 'admin.planes.index',        'badge' => null, 'permission' => 'planes.gestionar'],
                        ['name' => 'Suscripciones', 'icon' => 'fa-receipt',            'route' => 'admin.suscripciones.index', 'badge' => null, 'permission' => 'suscripciones.gestionar'],
                        ['name' => 'Pagos',         'icon' => 'fa-money-bills',        'route' => 'admin.pagos.index',         'badge' => null, 'permission' => 'pagos.gestionar'],
                        ['name' => 'Documentación', 'icon' => 'fa-book-open',          'route' => 'admin.documentacion',       'badge' => null, 'permission' => 'tenants.gestionar'],
                    ],
                ],
            ];

            // Filtrar items por permisos del usuario y secciones vacías
            $sections = [];
            foreach ($sectionsRaw as $sec) {
                // 🔒 Sección Super Admin SOLO en el dominio principal (NUNCA en subdominios de cliente)
                if ($sec['title'] === '⭐ Super Admin' && $enSubdominioTenant) {
                    continue;
                }
                // Si es super-admin sin impersonar, SOLO mostrar la sección "Super Admin"
                if ($verSoloAdmin && $sec['title'] !== '⭐ Super Admin') {
                    continue;
                }

                $items = array_values(array_filter($sec['items'], function ($it) use ($u) {
                    return !$u || empty($it['permission']) || $u->can($it['permission']);
                }));
                if (count($items) > 0) {
                    $sec['items'] = $items;
                    $sections[] = $sec;
                }
            }
        @endphp

        {{-- NAV --}}
        <nav class="flex-1 overflow-y-auto px-3 py-5 space-y-6">
            @foreach($sections as $section)
                <div>
                    <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-widest text-white/50">
                        {{ $section['title'] }}
                    </div>

                    <div class="space-y-1">
                        @foreach($section['items'] as $item)
                            @php
                                $isActive = $item['route'] && $current === $item['route'];
                                $href     = $item['route'] ? route($item['route']) : '#';
                                $disabled = !$item['route'];
                            @endphp

                            <a href="{{ $href }}"
                               @if($disabled) onclick="event.preventDefault()" @endif
                               class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                                      {{ $isActive
                                          ? 'bg-white text-[#a85f24] shadow-lg'
                                          : ($disabled
                                              ? 'text-white/40 cursor-not-allowed'
                                              : 'text-white/80 hover:bg-white/15 hover:text-white') }}">

                                <span class="flex h-8 w-8 items-center justify-center rounded-lg
                                             {{ $isActive ? 'bg-[#fbe9d7] text-[#a85f24]' : 'bg-white/10 group-hover:bg-white/20' }}">
                                    <i class="fa-solid {{ $item['icon'] }} text-sm"></i>
                                </span>

                                <span class="flex-1 truncate">{{ $item['name'] }}</span>

                                @if($item['badge'])
                                    <span class="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-bold uppercase">
                                        {{ $item['badge'] }}
                                    </span>
                                @endif

                                @if($isActive)
                                    <i class="fa-solid fa-chevron-right text-xs text-[#a85f24]"></i>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- FOOTER --}}
        <div class="border-t border-white/10 p-3">
            <a href="#"
               class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/15 hover:text-white transition">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 group-hover:bg-white/20">
                    <i class="fa-solid fa-gear text-sm"></i>
                </span>
                <span>Configuración</span>
            </a>
        </div>
    </aside>

    {{-- DRAWER MOBILE — visible solo cuando se activa con el botón hamburguesa --}}
    <aside id="mobile-sidebar"
           class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full transition-transform duration-300
                  flex flex-col text-white shadow-2xl lg:hidden"
           style="background: linear-gradient(to bottom, {{ $bgFrom }}, {{ $bgTo }});">

        <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 overflow-hidden">
                    @if($brandLogo)
                        <img src="{{ $brandLogo }}" alt="logo" class="h-full w-full object-contain">
                    @else
                        <i class="fa-solid fa-utensils text-lg"></i>
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-extrabold leading-tight">{{ $brandName }}</div>
                    <div class="text-xs font-medium text-white/70 leading-tight">{{ $brandSub }}</div>
                </div>
            </div>
            <button onclick="document.getElementById('mobile-sidebar').classList.add('-translate-x-full'); document.getElementById('mobile-backdrop').classList.add('hidden');"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-5 space-y-6">
            @foreach($sections as $section)
                <div>
                    <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-widest text-white/50">
                        {{ $section['title'] }}
                    </div>
                    <div class="space-y-1">
                        @foreach($section['items'] as $item)
                            @php
                                $isActive = $item['route'] && $current === $item['route'];
                                $href     = $item['route'] ? route($item['route']) : '#';
                            @endphp
                            <a href="{{ $href }}"
                               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                                      {{ $isActive ? 'bg-white text-[#a85f24] shadow' : 'text-white/80 hover:bg-white/15' }}">
                                <i class="fa-solid {{ $item['icon'] }} w-6 text-center"></i>
                                <span>{{ $item['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- BACKDROP MOBILE --}}
    <div id="mobile-backdrop"
         onclick="document.getElementById('mobile-sidebar').classList.add('-translate-x-full'); this.classList.add('hidden');"
         class="hidden fixed inset-0 z-40 bg-black/50 lg:hidden"></div>
</div>
