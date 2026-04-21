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
        'clientes.index'       => ['Clientes',       'Quién compra, qué compra, cuánto compra',    'fa-users'],
        'conversaciones.index' => ['Conversaciones', 'Historial de cada chat con el bot',          'fa-comments'],
        'chat.index'           => ['Chat en vivo',   'Atiende clientes en tiempo real',            'fa-headset'],
        'alertas.index'        => ['Alertas del bot','Errores operativos: OpenAI, WhatsApp',       'fa-triangle-exclamation'],
        'felicitaciones.index' => ['Felicitaciones',  'Historial de felicitaciones de cumpleaños',  'fa-cake-candles'],
        'sedes.index'          => ['Sedes',           'Puntos de atención y horarios',              'fa-shop'],
        'usuarios.index'       => ['Usuarios',        'Cuentas de acceso a la plataforma',          'fa-users-gear'],
        'roles.index'          => ['Roles y permisos','Define qué puede hacer cada rol',            'fa-shield-halved'],
        'admin.tenants.index'        => ['Tenants',         'Empresas cliente de la plataforma',         'fa-building'],
        'admin.planes.index'         => ['Planes',          'Catálogo de planes de suscripción',         'fa-money-check-dollar'],
        'admin.suscripciones.index'  => ['Suscripciones',   'Gestión de planes contratados por tenant',  'fa-receipt'],
        'admin.pagos.index'          => ['Pagos',           'Historial y registro manual de pagos',      'fa-money-bills'],
        'admin.documentacion'        => ['Documentación',   'Cómo funciona la plataforma SaaS',          'fa-book-open'],
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

{{-- 🎭 BANNER DE IMPERSONACIÓN — visible siempre que el super-admin esté "viendo como" un tenant --}}
@if($tenantImitado)
    <div class="fixed top-0 right-0 left-0 lg:left-64 z-40 bg-gradient-to-r from-violet-600 via-fuchsia-600 to-pink-600 text-white shadow-lg">
        <div class="flex items-center justify-between px-4 lg:px-8 py-2 gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="hidden sm:flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 backdrop-blur flex-shrink-0">
                    <i class="fa-solid fa-mask text-sm"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-wider text-white/80">Modo super-admin · viendo como</div>
                    <div class="text-sm font-extrabold truncate">{{ $tenantImitado->nombre }}</div>
                </div>
            </div>
            <a href="{{ route('admin.dejar-impersonar') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white text-violet-700 hover:bg-violet-50 font-bold px-4 py-2 text-sm shadow-md transition flex-shrink-0">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="hidden sm:inline">Volver al super-admin</span>
                <span class="sm:hidden">Salir</span>
            </a>
        </div>
    </div>
@endif

<header class="app-topbar fixed right-0 left-0 lg:left-64 z-30 h-20 border-b border-slate-200 bg-white/90 backdrop-blur-lg shadow-sm
               {{ $tenantImitado ? 'top-12 sm:top-12' : 'top-0' }}">
    <div class="flex h-full items-center justify-between px-4 lg:px-8 gap-4">

        {{-- IZQUIERDA: hamburguesa móvil + título --}}
        <div class="flex items-center gap-4 min-w-0">

            {{-- Botón hamburguesa solo en móvil/tablet --}}
            <button onclick="document.getElementById('mobile-sidebar').classList.remove('-translate-x-full'); document.getElementById('mobile-backdrop').classList.remove('hidden');"
                    class="lg:hidden flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="flex items-center gap-3 min-w-0">
                <div class="hidden md:flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#fbe9d7] to-[#f5d4ad] text-[#a85f24] shadow-sm">
                    <i class="fa-solid {{ $icono }} text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg md:text-xl font-extrabold text-slate-800 leading-tight truncate">
                        {{ $titulo }}
                    </h1>
                    <p class="hidden sm:block text-xs text-slate-500 truncate">{{ $subtitulo }}</p>
                </div>
            </div>
        </div>

        {{-- DERECHA: búsqueda + acciones + perfil --}}
        <div class="flex items-center gap-2 md:gap-3">

            {{-- Búsqueda global (hidden en móvil) --}}
            <div class="hidden lg:block relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                <input type="text"
                       placeholder="Buscar..."
                       class="w-64 rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-2.5 text-sm placeholder:text-slate-400 focus:border-[#d68643] focus:bg-white focus:ring-1 focus:ring-[#d68643] transition">
            </div>

            {{-- WhatsApp Status --}}
            <button class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 text-green-600 hover:bg-green-100 transition"
                    title="Conexión WhatsApp">
                <i class="fa-brands fa-whatsapp text-lg"></i>
                <span class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-green-500 ring-2 ring-white"></span>
            </button>

            {{-- Alertas del bot (campana con dropdown) --}}
            <livewire:alertas.badge />

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
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white text-sm font-bold shadow-sm">
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
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white font-bold">
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
