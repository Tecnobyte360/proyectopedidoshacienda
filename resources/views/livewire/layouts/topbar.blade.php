@php
    $current = request()->route()?->getName();

    $titulos = [
        'pedidos.index'        => ['Pedidos',        'Gestión de pedidos en tiempo real',          'fa-bag-shopping'],
        'despachos.index'      => ['Despachos',      'Asigna pedidos a domiciliarios por zona',    'fa-paper-plane'],
        'productos.index'      => ['Productos',      'Catálogo disponible para ventas y bot',      'fa-box'],
        'categorias.index'     => ['Categorías',     'Organiza tus productos',                     'fa-layer-group'],
        'promociones.index'    => ['Promociones',    'Descuentos, combos y precios especiales',    'fa-tags'],
        'domiciliarios.index'  => ['Domiciliarios',  'Gestión de repartidores',                    'fa-motorcycle'],
        'zonas.index'          => ['Zonas',          'Cobertura y barrios atendidos',              'fa-map-location-dot'],
        'reportes.index'       => ['Reportes',       'Métricas de ventas y operaciones',           'fa-chart-line'],
        'ans.index'            => ['ANS Tiempos',    'Configuración de tiempos por estado',        'fa-stopwatch'],
        'configuracion.bot'    => ['Bot WhatsApp',   'Comportamiento de la asesora IA',            'fa-robot'],
        'pagos.index'          => ['Pagos',          'Transacciones de Wompi',                     'fa-credit-card'],
        'rutas.index'          => ['Rutas',          'Pedidos asignados con ruta optimizada',      'fa-route'],
        'clientes.index'       => ['Clientes',       'Quién compra, qué compra, cuánto compra',    'fa-users'],
        'conversaciones.index' => ['Conversaciones', 'Historial de cada chat con el bot',          'fa-comments'],
        'chat.index'           => ['Chat en vivo',   'Atiende clientes en tiempo real',            'fa-headset'],
        'alertas.index'        => ['Alertas del bot','Errores operativos: OpenAI, WhatsApp',       'fa-triangle-exclamation'],
        'felicitaciones.index' => ['Felicitaciones',  'Historial de felicitaciones de cumpleaños',  'fa-cake-candles'],
        'encuestas.index'      => ['Encuestas',       'Calificaciones del proceso y domiciliarios', 'fa-star-half-stroke'],
        'sedes.index'          => ['Sedes',           'Puntos de atención y horarios',              'fa-shop'],
        'usuarios.index'       => ['Usuarios',        'Cuentas de acceso a la plataforma',          'fa-users-gear'],
        'roles.index'          => ['Roles y permisos','Define qué puede hacer cada rol',            'fa-shield-halved'],
        'admin.tenants.index'        => ['Tenants',         'Empresas cliente de la plataforma',         'fa-building'],
        'admin.planes.index'         => ['Planes',          'Catálogo de planes de suscripción',         'fa-money-check-dollar'],
        'admin.suscripciones.index'  => ['Suscripciones',   'Gestión de planes contratados por tenant',  'fa-receipt'],
        'admin.pagos.index'          => ['Pagos',           'Historial y registro manual de pagos',      'fa-money-bills'],
        'admin.documentacion'        => ['Documentación',   'Cómo funciona la plataforma SaaS',          'fa-book-open'],
        'admin.configuracion-plataforma' => ['Branding plataforma', 'Nombre, logo y colores de TecnoByte360', 'fa-palette'],
        'pedidos.seguimiento'  => ['Seguimiento',    'Estado del pedido',                          'fa-route'],
    ];

    [$titulo, $subtitulo, $icono] = $titulos[$current] ?? ['Panel', 'Bienvenido', 'fa-house'];

    // 🎭 Detectar modo impersonación
    $tenantImitadoId = session('tenant_imitado_id');
    $tenantImitado = $tenantImitadoId
        ? app(\App\Services\TenantManager::class)->withoutTenant(
            fn () => \App\Models\Tenant::find($tenantImitadoId)
          )
        : null;
@endphp

<header class="app-topbar fixed top-0 right-0 left-0 lg:left-64 z-30 h-16 border-b border-slate-200/70 bg-white/85 backdrop-blur-xl">
    <div class="flex h-full items-center justify-between px-4 lg:px-6 gap-4">

        {{-- IZQUIERDA: hamburguesa móvil + título --}}
        <div class="flex items-center gap-3 min-w-0">

            {{-- Botón hamburguesa solo en móvil/tablet --}}
            <button onclick="document.getElementById('mobile-sidebar').classList.remove('-translate-x-full'); document.getElementById('mobile-backdrop').classList.remove('hidden');"
                    class="lg:hidden flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                <i class="fa-solid fa-bars text-sm"></i>
            </button>

            <div class="flex items-center gap-2.5 min-w-0">
                <div class="hidden md:flex h-9 w-9 items-center justify-center rounded-xl"
                     style="background: linear-gradient(135deg, var(--brand-soft), var(--brand-soft-2)); color: var(--brand-secondary);">
                    <i class="fa-solid {{ $icono }} text-sm"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-[15px] md:text-base font-bold text-slate-900 leading-tight truncate tracking-tight">
                        {{ $titulo }}
                    </h1>
                    <p class="hidden sm:block text-xs text-slate-500 truncate">{{ $subtitulo }}</p>
                </div>
            </div>
        </div>

        {{-- DERECHA: switcher de empresa + perfil --}}
        <div class="flex items-center gap-2 md:gap-3">

            {{-- 🎭 Switcher de empresa (solo super-admin impersonando) --}}
            @if($tenantImitado)
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open"
                            class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-2 transition shadow-sm">
                        @if($tenantImitado->logo_url)
                            <img src="{{ $tenantImitado->logo_url }}" alt="" class="h-8 w-8 rounded-lg object-cover">
                        @else
                            <div class="h-8 w-8 rounded-lg flex items-center justify-center text-white font-bold text-xs"
                                 style="background: linear-gradient(135deg, {{ $tenantImitado->color_primario ?: '#d68643' }}, {{ $tenantImitado->color_secundario ?: '#a85f24' }});">
                                {{ mb_substr($tenantImitado->nombre, 0, 1) }}
                            </div>
                        @endif
                        <div class="hidden md:block text-left">
                            <div class="text-[10px] uppercase tracking-wide text-slate-500 font-bold">Viendo como</div>
                            <div class="text-sm font-bold text-slate-800 leading-tight truncate max-w-[160px]">{{ $tenantImitado->nombre }}</div>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                    </button>

                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 mt-2 w-72 rounded-2xl border border-slate-200 bg-white shadow-2xl z-50 overflow-hidden"
                         style="display: none;">

                        <div class="px-4 py-3 bg-gradient-to-r from-amber-50 to-white border-b border-slate-100">
                            <p class="text-[10px] uppercase font-bold text-amber-700 tracking-wider">
                                <i class="fa-solid fa-mask"></i> Modo impersonación
                            </p>
                            <p class="text-xs text-slate-600 mt-0.5">Estás operando como esta empresa.</p>
                        </div>

                        <a href="{{ route('admin.tenants.index') }}"
                           class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-shuffle text-violet-500 w-5"></i>
                            <span class="flex-1">Cambiar a otra empresa</span>
                        </a>

                        <button type="button"
                                onclick="window.salirDeImpersonacion(this)"
                                data-csrf="{{ csrf_token() }}"
                                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-rose-600 hover:bg-rose-50 border-t border-slate-100 text-left">
                            <i class="fa-solid fa-arrow-left-long text-rose-500 w-5"></i>
                            <span class="flex-1">Volver al super-admin</span>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Separador --}}
            <div class="hidden md:block h-8 w-px bg-slate-200"></div>

            {{-- Avatar / usuario con dropdown --}}
            @php
                $u = auth()->user();
                $userName = $u?->name ?? 'Invitado';
                $userIniciales = $u?->iniciales() ?: 'U';
                $userRol = $u?->rolPrincipal() ?? 'sin rol';
                $userSede = $u?->sede?->nombre ?? 'Sede principal';
            @endphp

            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open"
                        class="flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-slate-100 transition">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white text-sm font-bold shadow-sm">
                        {{ $userIniciales }}
                    </div>
                    <div class="hidden md:block text-left">
                        <div class="text-sm font-semibold text-slate-800 leading-tight">
                            {{ $userName }}
                        </div>
                        <div class="text-xs text-slate-500 leading-tight capitalize">
                            {{ $userRol }} · {{ $userSede }}
                        </div>
                    </div>
                    <i class="hidden md:block fa-solid fa-chevron-down text-xs text-slate-400 ml-1"></i>
                </button>

                {{-- Dropdown --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute right-0 mt-2 w-64 rounded-2xl border border-slate-200 bg-white shadow-2xl z-50 overflow-hidden"
                     style="display: none;">

                    <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white font-bold">
                                {{ $userIniciales }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold text-slate-800 truncate">{{ $userName }}</div>
                                <div class="text-xs text-slate-500 truncate">{{ $u?->email }}</div>
                                <div class="text-[10px] mt-0.5">
                                    <span class="inline-block bg-violet-100 text-violet-700 font-semibold px-2 py-0.5 rounded-full">{{ $userRol }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @can('usuarios.ver')
                        <a href="{{ route('usuarios.index') }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-users-gear text-slate-400 w-5"></i>
                            Gestionar usuarios
                        </a>
                    @endcan

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 border-t border-slate-100">
                            <i class="fa-solid fa-arrow-right-from-bracket text-rose-500 w-5"></i>
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
