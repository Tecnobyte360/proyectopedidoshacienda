<div>
    {{-- Wrapper Livewire requiere UN solo root element --}}

    <aside class="app-sidebar fixed inset-y-0 left-0 z-40 hidden lg:flex w-64 flex-col bg-gradient-to-b from-[#c97a36] to-[#a85f24] text-white shadow-2xl">

        {{-- LOGO / BRAND --}}
        <div class="flex h-20 items-center gap-3 border-b border-white/10 px-5">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 shadow-lg backdrop-blur">
                <i class="fa-solid fa-utensils text-lg text-white"></i>
            </div>
            <div class="min-w-0">
                <div class="truncate text-sm font-extrabold leading-tight">Alimentos</div>
                <div class="truncate text-xs font-medium text-white/70 leading-tight">La Hacienda</div>
            </div>
        </div>

        @php
            $current = request()->route()?->getName();

            $sections = [
                [
                    'title' => 'Principal',
                    'items' => [
                        ['name' => 'Pedidos',      'icon' => 'fa-bag-shopping', 'route' => 'pedidos.index',      'badge' => null],
                        ['name' => 'Chat en vivo', 'icon' => 'fa-headset',      'route' => 'chat.index',         'badge' => 'NEW'],
                        ['name' => 'Despachos',    'icon' => 'fa-paper-plane',  'route' => 'despachos.index',    'badge' => null],
                    ],
                ],
                [
                    'title' => 'Catálogo',
                    'items' => [
                        ['name' => 'Productos',   'icon' => 'fa-box',          'route' => 'productos.index',    'badge' => null],
                        ['name' => 'Categorías',  'icon' => 'fa-layer-group',  'route' => 'categorias.index',   'badge' => null],
                        ['name' => 'Promociones', 'icon' => 'fa-tags',         'route' => 'promociones.index',  'badge' => null],
                    ],
                ],
                [
                    'title' => 'Operaciones',
                    'items' => [
                        ['name' => 'Clientes',       'icon' => 'fa-users',              'route' => 'clientes.index',      'badge' => null],
                        ['name' => 'Conversaciones', 'icon' => 'fa-comments',           'route' => 'conversaciones.index', 'badge' => null],
                        ['name' => 'Domiciliarios', 'icon' => 'fa-motorcycle',         'route' => 'domiciliarios.index', 'badge' => null],
                        ['name' => 'Zonas',         'icon' => 'fa-map-location-dot',   'route' => 'zonas.index',         'badge' => null],
                        ['name' => 'Reportes',      'icon' => 'fa-chart-line',         'route' => 'reportes.index',      'badge' => null],
                        ['name' => 'ANS Tiempos',   'icon' => 'fa-stopwatch',          'route' => 'ans.index',           'badge' => null],
                        ['name' => 'Bot WhatsApp',  'icon' => 'fa-robot',              'route' => 'configuracion.bot',   'badge' => null],
                    ],
                ],
                [
                    'title' => 'Sistema',
                    'items' => [
                        ['name' => 'Alertas del bot', 'icon' => 'fa-triangle-exclamation', 'route' => 'alertas.index',
                            'badge' => (\Schema::hasTable('bot_alertas')
                                ? (\App\Models\BotAlerta::where('resuelta', false)->count() ?: null)
                                : null)],
                    ],
                ],
            ];
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
                  flex flex-col bg-gradient-to-b from-[#c97a36] to-[#a85f24] text-white shadow-2xl lg:hidden">

        <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15">
                    <i class="fa-solid fa-utensils text-lg"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-extrabold leading-tight">Alimentos</div>
                    <div class="text-xs font-medium text-white/70 leading-tight">La Hacienda</div>
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
