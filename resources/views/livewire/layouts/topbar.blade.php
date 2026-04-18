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
        'pedidos.seguimiento'  => ['Seguimiento',    'Estado del pedido',                          'fa-route'],
    ];

    [$titulo, $subtitulo, $icono] = $titulos[$current] ?? ['Panel', 'Bienvenido', 'fa-house'];
@endphp

<header class="app-topbar fixed top-0 right-0 left-0 lg:left-64 z-30 h-20 border-b border-slate-200 bg-white/90 backdrop-blur-lg shadow-sm">
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

            {{-- Notificaciones --}}
            <button class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition"
                    title="Notificaciones">
                <i class="fa-solid fa-bell"></i>
                <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                    3
                </span>
            </button>

            {{-- Separador --}}
            <div class="hidden md:block h-8 w-px bg-slate-200"></div>

            {{-- Avatar / usuario --}}
            <button class="flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-slate-100 transition">
                @php
                    $userName = auth()->user()?->name ?? 'Administrador';
                @endphp
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white text-sm font-bold shadow-sm">
                    {{ strtoupper(substr($userName, 0, 1)) }}
                </div>
                <div class="hidden md:block text-left">
                    <div class="text-sm font-semibold text-slate-800 leading-tight">
                        {{ $userName }}
                    </div>
                    <div class="text-xs text-slate-500 leading-tight">
                        Sede principal
                    </div>
                </div>
                <i class="hidden md:block fa-solid fa-chevron-down text-xs text-slate-400 ml-1"></i>
            </button>
        </div>
    </div>
</header>
