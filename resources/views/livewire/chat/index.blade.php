<div class="h-[calc(100vh-5rem)] flex flex-col lg:flex-row bg-slate-100 overflow-x-hidden w-full max-w-full"
     wire:poll.2s="refrescar">

    @php $cfgBot = \App\Models\ConfiguracionBot::actual(); @endphp

    {{-- Bot apagado global → la pill bonita ahora vive en el topbar (resources/views/livewire/layouts/topbar.blade.php) --}}

    {{-- ╔═══ COLUMNA IZQUIERDA: lista de conversaciones ═══╗
         En móvil: solo se ve si NO hay chat activo (al seleccionar uno se oculta).
         En desktop (lg+): siempre visible. --}}
    <aside class="w-full lg:w-96 flex-shrink-0 bg-white border-r border-slate-200 flex-col
                  {{ $conversacionActivaId ? 'hidden lg:flex' : 'flex' }}">

        {{-- Header --}}
        <div class="p-4 border-b border-slate-200 bg-gradient-to-br from-brand to-brand-secondary text-white">
            <div class="flex items-center justify-between gap-2 min-w-0">
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-bold flex items-center gap-2 whitespace-nowrap">
                        <i class="fa-solid fa-comments"></i>
                        <span class="truncate">Chat en vivo</span>
                    </h2>
                    <p class="text-[11px] text-white/80 truncate">Atiende clientes en tiempo real</p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button wire:click="sincronizarHistorial"
                            wire:loading.attr="disabled"
                            wire:target="sincronizarHistorial"
                            title="Sincronizar historial de WhatsApp"
                            class="inline-flex items-center justify-center h-8 w-8 lg:w-auto lg:px-2.5 lg:gap-1 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur text-[11px] font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="sincronizarHistorial" class="inline-flex items-center gap-1">
                            <i class="fa-solid fa-arrows-rotate text-xs"></i>
                            <span class="hidden lg:inline">Sincronizar</span>
                        </span>
                        <span wire:loading wire:target="sincronizarHistorial" class="inline-flex items-center gap-1">
                            <i class="fa-solid fa-spinner fa-spin text-xs"></i>
                            <span class="hidden lg:inline">…</span>
                        </span>
                    </button>
                    <button wire:click="abrirEstadoModal"
                            title="Publicar estado de WhatsApp"
                            class="inline-flex items-center justify-center h-8 w-8 lg:w-auto lg:px-2.5 lg:gap-1 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur text-[11px] font-semibold transition">
                        <i class="fa-solid fa-circle-plus text-xs"></i>
                        <span class="hidden lg:inline">Estado</span>
                    </button>
                    <button wire:click="abrirNuevoChat"
                            title="Iniciar chat con un número nuevo"
                            class="inline-flex items-center justify-center h-8 w-8 lg:w-auto lg:px-2.5 lg:gap-1 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur text-[11px] font-semibold transition">
                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                        <span class="hidden lg:inline">Nuevo</span>
                    </button>
                </div>
            </div>

            {{-- Resultado de la última sincronización --}}
            @if (!empty($resultadoSyncHistorial))
                <div class="mt-2 rounded-lg bg-white/15 backdrop-blur px-3 py-2 text-[11px] flex items-center gap-2">
                    @if (!empty($resultadoSyncHistorial['error']))
                        <i class="fa-solid fa-circle-exclamation text-rose-200"></i>
                        <span class="font-medium">{{ $resultadoSyncHistorial['error'] }}</span>
                    @else
                        <i class="fa-solid fa-circle-check text-emerald-200"></i>
                        <span class="font-medium">
                            {{ $resultadoSyncHistorial['tickets_procesados'] ?? 0 }} chats ·
                            {{ $resultadoSyncHistorial['clientes_creados'] ?? 0 }} clientes nuevos ·
                            {{ $resultadoSyncHistorial['mensajes_imp'] ?? 0 }} mensajes
                        </span>
                        <button wire:click="$set('resultadoSyncHistorial', null)" class="ml-auto text-white/60 hover:text-white">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Filtros --}}
        <div class="p-3 border-b border-slate-200 space-y-2">
            <input type="text" wire:model.live.debounce.400ms="busqueda"
                   id="chat-search-input"
                   placeholder="Buscar cliente o teléfono..."
                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-brand">

            <div class="flex gap-1">
                @foreach([
                    'todas'     => ['Todas', 'fa-list'],
                    'activa'    => ['Activas', 'fa-circle-dot'],
                    'humano'    => ['Humano', 'fa-user'],
                    'bot'       => ['Bot', 'fa-robot'],
                    'internos'  => ['Internos', 'fa-user-shield'],
                ] as $key => [$label, $icon])
                    <button wire:click="$set('filtroEstado', '{{ $key }}')"
                            class="flex-1 rounded-lg px-2 py-1.5 text-xs font-semibold transition
                                  {{ $filtroEstado === $key ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <i class="fa-solid {{ $icon }} mr-0.5"></i> {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Chips estilo WhatsApp: No leídos + Favoritos --}}
            <div class="flex flex-wrap gap-1.5 mt-1">
                <button wire:click="$set('filtroEstado', 'todas')"
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold transition
                              {{ $filtroEstado === 'todas' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    Todos
                </button>
                <button wire:click="$set('filtroEstado', 'no_leidos')"
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold transition
                              {{ $filtroEstado === 'no_leidos' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    No leídos
                    @if($totalNoLeidos > 0)
                        <span class="inline-flex items-center justify-center min-w-[18px] h-4 px-1 rounded-full {{ $filtroEstado === 'no_leidos' ? 'bg-white text-emerald-700' : 'bg-emerald-500 text-white' }} text-[10px] font-bold">
                            {{ $totalNoLeidos > 99 ? '99+' : $totalNoLeidos }}
                        </span>
                    @endif
                </button>
                <button wire:click="$set('filtroEstado', 'favoritos')"
                        class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold transition
                              {{ $filtroEstado === 'favoritos' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    <i class="fa-solid fa-thumbtack text-[10px]"></i>
                    Favoritos
                    @if($totalFavoritos > 0)
                        <span class="inline-flex items-center justify-center min-w-[18px] h-4 px-1 rounded-full {{ $filtroEstado === 'favoritos' ? 'bg-white text-amber-600' : 'bg-amber-500 text-white' }} text-[10px] font-bold">
                            {{ $totalFavoritos }}
                        </span>
                    @endif
                </button>
            </div>

            {{-- 📡 Filtros por CANAL --}}
            <div class="mt-2">
                <div class="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-1 px-0.5">Canal</div>
                <div class="flex items-center gap-1.5">
                    {{-- Botón "Todos" más prominente a la izquierda --}}
                    <button wire:click="$set('filtroCanal', 'todos')"
                            class="inline-flex items-center justify-center gap-1 rounded-full px-3 py-1.5 text-[11px] font-semibold transition flex-shrink-0
                                  {{ $filtroCanal === 'todos' ? 'bg-slate-800 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <i class="fa-solid fa-layer-group text-[10px]"></i> Todos
                    </button>

                    {{-- Iconos compactos para cada canal con tooltip --}}
                    @foreach([
                        'whatsapp'  => ['WhatsApp',          'fa-brands fa-whatsapp',          'bg-emerald-500', 'ring-emerald-300'],
                        'instagram' => ['Instagram',         'fa-brands fa-instagram',         'bg-gradient-to-br from-pink-500 via-fuchsia-500 to-amber-400', 'ring-pink-300'],
                        'messenger' => ['Messenger',         'fa-brands fa-facebook-messenger','bg-gradient-to-br from-blue-500 to-sky-500', 'ring-blue-300'],
                        'widget'    => ['Widget web',        'fa-solid fa-globe',              'bg-sky-500', 'ring-sky-300'],
                    ] as $key => [$label, $icon, $color, $ring])
                        <button wire:click="$set('filtroCanal', '{{ $key }}')"
                                title="{{ $label }}"
                                class="group relative flex-1 inline-flex items-center justify-center rounded-full h-8 text-sm transition
                                      {{ $filtroCanal === $key ? $color . ' text-white shadow ring-2 ' . $ring : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                            <i class="{{ $icon }}"></i>
                            {{-- Tooltip al hover --}}
                            <span class="pointer-events-none absolute -bottom-7 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-slate-800 px-1.5 py-0.5 text-[10px] text-white opacity-0 group-hover:opacity-100 transition z-10">
                                {{ $label }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Lista de conversaciones --}}
        <div class="flex-1 overflow-y-auto" id="lista-conversaciones">
            @forelse($conversaciones as $c)
                @php
                    $iniciales = collect(explode(' ', trim($c->cliente?->nombre ?? 'C')))
                        ->filter()->take(2)
                        ->map(fn($p) => mb_substr($p, 0, 1))
                        ->implode('');
                    $isActiva = $conversacionActiva && $conversacionActiva->id === $c->id;
                @endphp

                @php
                    $tieneNoLeidos = ((int) ($c->no_leidos ?? 0) > 0) || ($c->marcada_no_leida ?? false);
                    $estaFijada    = !empty($c->fijada_at);
                @endphp
                <div class="group relative border-b border-slate-100 hover:bg-amber-50/40 transition
                            {{ $isActiva ? 'bg-amber-50' : ($tieneNoLeidos ? 'bg-emerald-50/40' : '') }}
                            {{ $estaFijada ? 'border-l-4 border-l-amber-400' : '' }}"
                     x-data="{ menuAbierto: false }">

                <button wire:click="seleccionar({{ $c->id }})"
                        class="w-full text-left flex items-center gap-3 px-4 py-3 pr-10">

                    <div class="relative flex-shrink-0">
                        @php
                            // 📸 Avatar unificado: foto_url > profile_pic_url > ui-avatars con iniciales
                            $avatarUrl = $c->cliente
                                ? $c->cliente->avatar_url
                                : 'https://ui-avatars.com/api/?name=' . urlencode($iniciales ?: 'C') . '&background=10b981&color=fff&size=128&bold=true';
                        @endphp
                        <img src="{{ $avatarUrl }}"
                             class="h-12 w-12 rounded-full object-cover bg-slate-100"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                             alt="avatar"
                             loading="lazy">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-brand to-brand-secondary text-white font-bold items-center justify-center" style="display:none;">
                            {{ $iniciales ?: 'C' }}
                        </div>
                        {{-- 📡 Badge del CANAL en esquina superior izquierda --}}
                        @if($c->canal === 'instagram')
                            <span class="absolute -top-1 -left-1 flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-pink-500 via-fuchsia-500 to-amber-400 text-white text-[10px] border-2 border-white shadow" title="Instagram DM">
                                <i class="fa-brands fa-instagram text-[9px]"></i>
                            </span>
                        @elseif($c->canal === 'messenger')
                            <span class="absolute -top-1 -left-1 flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-sky-500 text-white text-[10px] border-2 border-white shadow" title="Facebook Messenger">
                                <i class="fa-brands fa-facebook-messenger text-[9px]"></i>
                            </span>
                        @elseif($c->canal === 'widget')
                            <span class="absolute -top-1 -left-1 flex h-5 w-5 items-center justify-center rounded-full bg-sky-500 text-white text-[10px] border-2 border-white" title="Widget web">
                                <i class="fa-solid fa-globe text-[9px]"></i>
                            </span>
                        @else
                            <span class="absolute -top-1 -left-1 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-white text-[10px] border-2 border-white" title="WhatsApp">
                                <i class="fa-brands fa-whatsapp text-[9px]"></i>
                            </span>
                        @endif

                        @if($c->canal === 'widget')
                            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-sky-500 text-white text-[10px] border-2 border-white" title="Chat desde web (widget)">
                                <i class="fa-solid fa-globe"></i>
                            </span>
                        @elseif($c->es_interna ?? false)
                            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-violet-500 text-white text-[10px] border-2 border-white" title="Usuario interno (equipo)">
                                <i class="fa-solid fa-user-shield"></i>
                            </span>
                        @elseif($c->atendida_por_humano)
                            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-blue-500 text-white text-[10px] border-2 border-white" title="Atendida por humano">
                                <i class="fa-solid fa-user"></i>
                            </span>
                        @else
                            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-white text-[10px] border-2 border-white" title="Bot atiende">
                                <i class="fa-solid fa-robot"></i>
                            </span>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate flex items-center gap-1.5 {{ $tieneNoLeidos ? 'font-extrabold text-slate-900' : 'font-semibold text-slate-800' }}">
                                @if($estaFijada)
                                    <i class="fa-solid fa-thumbtack text-amber-500 text-[11px] shrink-0" title="Conversación fijada"></i>
                                @endif
                                <span class="truncate">{{ $c->cliente?->nombre ?? 'Cliente' }}</span>
                            </span>
                            <span class="text-[10px] flex-shrink-0 {{ $tieneNoLeidos ? 'text-emerald-600 font-bold' : 'text-slate-400' }}">
                                {{ $c->ultimo_mensaje_at?->diffForHumans(null, true) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs truncate font-mono {{ $tieneNoLeidos ? 'text-slate-700' : 'text-slate-500' }}">{{ $c->telefono_normalizado }}</div>
                            @if($tieneNoLeidos)
                                @if(($c->no_leidos ?? 0) > 0)
                                    {{-- Hay mensajes nuevos reales del cliente: badge con número --}}
                                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-emerald-500 text-white text-[11px] font-extrabold flex-shrink-0 shadow-sm">
                                        {{ $c->no_leidos > 99 ? '99+' : $c->no_leidos }}
                                    </span>
                                @else
                                    {{-- Sólo marcada manualmente como no leída: puntito verde --}}
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 flex-shrink-0 shadow-sm" title="Marcada como no leída"></span>
                                @endif
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2 mt-0.5">
                            <div class="text-[10px] text-slate-400">{{ $c->total_mensajes }} mensajes</div>
                            @if($c->departamento)
                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-700 border border-violet-200" title="Derivada a {{ $c->departamento->nombre }}">
                                    {!! $c->departamento->icono_emoji ?: '<i class="fa-solid fa-building"></i>' !!} {{ $c->departamento->nombre }}
                                </span>
                            @endif
                        </div>
                    </div>
                </button>

                {{-- Kebab menu (acciones por chat) --}}
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition"
                     :class="{ 'opacity-100': menuAbierto }">
                    <button type="button" @click.stop="menuAbierto = !menuAbierto"
                            class="flex items-center justify-center w-7 h-7 rounded-full bg-white shadow-sm border border-slate-200 hover:bg-slate-100 text-slate-600"
                            title="Más opciones">
                        <i class="fa-solid fa-ellipsis-vertical text-xs"></i>
                    </button>

                    <div x-show="menuAbierto" x-cloak
                         @click.outside="menuAbierto = false"
                         @keydown.escape.window="menuAbierto = false"
                         x-transition.opacity
                         class="absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-1 z-30">
                        <button type="button"
                                wire:click="toggleFijar({{ $c->id }})"
                                @click="menuAbierto = false"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-amber-50 flex items-center gap-2.5">
                            <i class="fa-solid fa-thumbtack {{ $estaFijada ? 'text-amber-500' : 'text-slate-400' }} w-4"></i>
                            <span class="text-slate-700">{{ $estaFijada ? 'Desfijar conversación' : 'Fijar arriba' }}</span>
                        </button>
                        <button type="button"
                                wire:click="toggleMarcarNoLeida({{ $c->id }})"
                                @click="menuAbierto = false"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-emerald-50 flex items-center gap-2.5">
                            <i class="fa-solid {{ ($c->marcada_no_leida ?? false) ? 'fa-envelope-open text-slate-400' : 'fa-envelope text-emerald-600' }} w-4"></i>
                            <span class="text-slate-700">{{ ($c->marcada_no_leida ?? false) ? 'Marcar como leída' : 'Marcar como no leída' }}</span>
                        </button>
                    </div>
                </div>
                </div>
            @empty
                <div class="p-8 text-center text-slate-400">
                    <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm">Sin conversaciones</p>
                </div>
            @endforelse
        </div>
    </aside>

    {{-- ╔═══ COLUMNA DERECHA: chat seleccionado ═══╗ --}}
    <section x-data="chatComposer()"
             x-init="init()"
             @dragenter.prevent="onDragEnter($event)"
             @dragover.prevent="onDragOver($event)"
             @dragleave.prevent="onDragLeave($event)"
             @drop.prevent="onDrop($event)"
             class="relative flex-1 flex-col min-w-0 bg-[#efeae2]
                    {{ $conversacionActivaId ? 'flex' : 'hidden lg:flex' }}">

        {{-- 🎯 Overlay drag-and-drop a TODA la conversación --}}
        <div x-show="dragging && @js((bool) $conversacionActivaId)" x-cloak
             class="absolute inset-0 z-40 flex items-center justify-center bg-emerald-50/95 backdrop-blur-sm pointer-events-none">
            <div class="text-center rounded-2xl border-2 border-dashed border-emerald-400 bg-white/80 px-10 py-8 shadow-lg">
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg mb-3">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl"></i>
                </div>
                <p class="text-base font-semibold text-emerald-700">Suelta la imagen aquí</p>
                <p class="text-xs text-emerald-600/70 mt-1">JPG, PNG &middot; máx 15 MB</p>
            </div>
        </div>

        @if($conversacionActiva)
            @php
                $iniAct = collect(explode(' ', trim($conversacionActiva->cliente?->nombre ?? 'C')))
                    ->filter()->take(2)
                    ->map(fn($p) => mb_substr($p, 0, 1))
                    ->implode('');
            @endphp

            {{-- Header del chat — sticky para que quede fijo al hacer scroll --}}
            <div class="sticky top-0 z-20 bg-white border-b border-slate-200 px-3 py-3 flex items-center justify-between gap-2 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    {{-- ⬅️ Volver (solo móvil): cierra el chat para regresar a la lista --}}
                    <button type="button"
                            wire:click="$set('conversacionActivaId', null)"
                            class="lg:hidden flex h-9 w-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 flex-shrink-0"
                            title="Volver a conversaciones">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>

                    @php
                        $avatarHeader = $conversacionActiva->cliente
                            ? $conversacionActiva->cliente->avatar_url
                            : 'https://ui-avatars.com/api/?name=' . urlencode($iniAct ?: 'C') . '&background=10b981&color=fff&size=128&bold=true';
                    @endphp
                    <div class="relative flex-shrink-0">
                        <img src="{{ $avatarHeader }}"
                             class="h-10 w-10 rounded-full object-cover bg-slate-100"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                             alt="avatar">
                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-brand to-brand-secondary text-white font-bold text-sm items-center justify-center" style="display:none;">
                            {{ $iniAct ?: 'C' }}
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800 truncate flex items-center gap-2">
                            <span>{{ $conversacionActiva->cliente?->nombre ?? 'Cliente' }}</span>
                            @if($conversacionActiva->departamento)
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 border border-violet-200"
                                      title="Derivada al departamento {{ $conversacionActiva->departamento->nombre }}">
                                    {!! $conversacionActiva->departamento->icono_emoji ?: '<i class="fa-solid fa-building"></i>' !!} {{ $conversacionActiva->departamento->nombre }}
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 font-mono">
                            {{ $conversacionActiva->telefono_normalizado }}
                            @if($conversacionActiva->atendida_por_humano)
                                <span class="ml-2 inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                    <i class="fa-solid fa-user"></i> ATENDIDA POR TI
                                </span>
                            @else
                                <span class="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                    <i class="fa-solid fa-robot"></i> BOT ACTIVO
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 flex-shrink-0">
                    {{-- 📞 Llamar al cliente (lite: abre wa.me en pestaña nueva, o tel: para celular) --}}
                    @php
                        $telLlamar = preg_replace('/[^0-9]/', '', $conversacionActiva->telefono_normalizado ?? '');
                    @endphp
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                                title="Llamar al cliente"
                                class="rounded-lg bg-sky-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-sky-600 transition inline-flex items-center gap-1">
                            <i class="fa-solid fa-phone"></i>
                            <span class="hidden md:inline">Llamar</span>
                        </button>
                        <div x-show="open" x-transition x-cloak
                             class="absolute right-0 mt-1 w-60 rounded-lg border border-slate-200 bg-white shadow-xl z-50 overflow-hidden">
                            <a href="https://wa.me/{{ $telLlamar }}" target="_blank" rel="noopener"
                               class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-emerald-50 border-b border-slate-100">
                                <i class="fa-brands fa-whatsapp text-emerald-500 text-base"></i>
                                <div class="flex-1">
                                    <div class="font-semibold text-slate-800">Llamar por WhatsApp</div>
                                    <div class="text-[10px] text-slate-500">Abre WhatsApp Web en pestaña nueva</div>
                                </div>
                            </a>
                            <a href="tel:+{{ $telLlamar }}"
                               class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-sky-50 border-b border-slate-100">
                                <i class="fa-solid fa-phone text-sky-500 text-base"></i>
                                <div class="flex-1">
                                    <div class="font-semibold text-slate-800">Llamada normal</div>
                                    <div class="text-[10px] text-slate-500">Marca al celular del cliente</div>
                                </div>
                            </a>
                            <button type="button"
                                    @click="navigator.clipboard.writeText('+{{ $telLlamar }}'); $dispatch('notify', { type:'success', message:'Número copiado: +{{ $telLlamar }}' }); open = false"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-xs hover:bg-slate-50">
                                <i class="fa-regular fa-copy text-slate-500 text-base"></i>
                                <div class="flex-1 text-left">
                                    <div class="font-semibold text-slate-800">Copiar número</div>
                                    <div class="text-[10px] text-slate-500 font-mono">+{{ $telLlamar }}</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    {{-- 🛒 Crear pedido manual (precarga datos del estado) --}}
                    <a href="{{ route('pedidos.crear-manual', ['conv' => $conversacionActiva->id]) }}"
                       title="Crear pedido manualmente con datos del chat ya pre-cargados"
                       class="rounded-lg bg-emerald-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-emerald-600 transition inline-flex items-center gap-1">
                        <i class="fa-solid fa-cart-plus"></i>
                        <span class="hidden md:inline">Crear pedido</span>
                    </a>

                    {{-- 📋 Modal de estado del pedido (datos estructurados) --}}
                    <button type="button"
                            wire:click="abrirPedidoEstadoModal"
                            title="Ver qué datos del pedido tiene el bot recopilados"
                            class="rounded-lg bg-violet-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-violet-600 transition inline-flex items-center gap-1">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <span class="hidden md:inline">Estado del pedido</span>
                    </button>

                    @if($conversacionActiva->atendida_por_humano)
                        <button wire:click="devolverAlBot"
                                wire:confirm="¿Devolver al bot? La conversación volverá al área general y otros agentes podrán verla."
                                title="El bot retomará automáticamente la conversación. Si estaba derivada a un departamento, también se libera."
                                class="rounded-lg bg-emerald-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-emerald-600 transition inline-flex items-center gap-1">
                            <i class="fa-solid fa-robot"></i>
                            <span class="hidden md:inline">Devolver al bot</span>
                        </button>
                    @else
                        <button wire:click="tomarControl"
                                title="Silencia al bot — solo TÚ respondes a este cliente"
                                class="rounded-lg bg-blue-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-blue-600 transition inline-flex items-center gap-1">
                            <i class="fa-solid fa-hand"></i>
                            <span class="hidden md:inline">Silenciar bot</span>
                        </button>
                    @endif

                </div>
            </div>

            {{-- Área de mensajes --}}
            <div class="flex-1 overflow-y-auto overflow-x-hidden px-3 md:px-4 py-4 space-y-2 w-full max-w-full" id="chat-messages"
                 style="word-break: break-word; overflow-wrap: anywhere;">
                @foreach($conversacionActiva->mensajes as $m)
                    @php
                        $mediaUrl    = $m->meta['media_url'] ?? null;
                        $esAudio     = ($m->tipo ?? null) === 'audio' && !empty($mediaUrl);
                        $esImagen    = ($m->tipo ?? null) === 'image' && !empty($mediaUrl);
                        $esVideo     = ($m->tipo ?? null) === 'video' && !empty($mediaUrl);
                        $esDocumento = ($m->tipo ?? null) === 'document' && !empty($mediaUrl);
                        $caption     = $m->meta['caption'] ?? null;
                        $docNombre   = $m->meta['filename'] ?? null;
                        $docExt      = strtolower($m->meta['extension'] ?? '');
                        // Icono FontAwesome por extensión del documento
                        $docIcono    = match (true) {
                            $docExt === 'pdf'                                    => 'fa-file-pdf text-rose-600',
                            in_array($docExt, ['doc','docx','odt'], true)        => 'fa-file-word text-blue-600',
                            in_array($docExt, ['xls','xlsx','ods','csv'], true)  => 'fa-file-excel text-emerald-600',
                            in_array($docExt, ['ppt','pptx','odp'], true)        => 'fa-file-powerpoint text-orange-600',
                            in_array($docExt, ['zip','rar','7z'], true)          => 'fa-file-zipper text-violet-600',
                            $docExt === 'txt'                                    => 'fa-file-lines text-slate-600',
                            default                                              => 'fa-file text-slate-600',
                        };
                    @endphp
                    @if($m->rol === 'user')
                        @php
                            $rescatadoPor = $m->meta['rescatado_por'] ?? null;
                            $motivoRescate = $m->meta['motivo'] ?? null;
                            $motivoLabel = match ($motivoRescate) {
                                'bot_estancado' => 'Mensaje reprocesado: el bot no respondió a tiempo',
                                'bot_pasmado'   => 'Mensaje reprocesado: el bot prometió revisar y no continuó',
                                default         => 'Mensaje reprocesado por el watchdog',
                            };
                        @endphp
                        <div class="flex justify-start group relative" x-data="{ pickerAbierto: false, hovered: false }"
                             @mouseenter="hovered = true" @mouseleave="hovered = false">
                            <div class="max-w-[85%] md:max-w-[70%] rounded-2xl rounded-tl-sm {{ $rescatadoPor ? 'bg-amber-50 border border-amber-200' : 'bg-white' }} px-3 py-2 shadow-sm">
                                {{-- 💬 Si el cliente responde a un msg nuestro, mostrar la cita --}}
                                @if($m->respondiendo_a_mensaje_id)
                                    @php
                                        $msgCitado = \App\Models\MensajeWhatsapp::find($m->respondiendo_a_mensaje_id);
                                        $citaPreview = '';
                                        if ($msgCitado) {
                                            if ($msgCitado->tipo === 'image')       $citaPreview = '🖼️ Imagen';
                                            elseif ($msgCitado->tipo === 'video')    $citaPreview = '🎬 Video';
                                            elseif ($msgCitado->tipo === 'audio')    $citaPreview = '🎤 Audio';
                                            elseif ($msgCitado->tipo === 'document') $citaPreview = '📄 ' . ($msgCitado->meta['filename'] ?? 'Documento');
                                            else                                      $citaPreview = mb_substr((string) $msgCitado->contenido, 0, 120);
                                        }
                                        $citaAutor = $msgCitado && $msgCitado->rol === 'user' ? 'Cliente' : 'Tú';
                                    @endphp
                                    @if($msgCitado)
                                        <div class="mb-1.5 border-l-4 border-emerald-400 bg-emerald-50/60 rounded-r-md pl-2 py-1">
                                            <div class="text-[10px] font-bold text-emerald-700">{{ $citaAutor }}</div>
                                            <div class="text-[11px] text-slate-700 truncate">{{ $citaPreview }}</div>
                                        </div>
                                    @endif
                                @endif
                                @if($rescatadoPor)
                                    <div class="flex items-center gap-1 mb-1 text-[10px] font-semibold text-amber-700"
                                         title="{{ $motivoLabel }}">
                                        <i class="fa-solid fa-rotate-right"></i>
                                        <span>Recuperado por watchdog</span>
                                    </div>
                                @endif
                                @if($esAudio)
                                    <audio src="{{ $mediaUrl }}" controls class="w-64 max-w-full"></audio>
                                @elseif($esImagen)
                                    <a href="{{ $mediaUrl }}" target="_blank">
                                        <img src="{{ $mediaUrl }}" class="rounded-lg max-w-full max-h-64 object-contain" alt="imagen">
                                    </a>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @elseif($esVideo)
                                    <video src="{{ $mediaUrl }}" controls preload="metadata"
                                           class="rounded-lg max-w-full max-h-80 bg-black"
                                           style="max-width: 320px;"></video>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @elseif($esDocumento)
                                    <button type="button"
                                            @click="$dispatch('open-doc-preview', { url: '{{ $mediaUrl }}', filename: @js($docNombre ?: 'Documento'), ext: '{{ $docExt }}' })"
                                            class="flex items-center gap-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 px-3 py-2.5 transition w-72 max-w-full text-left">
                                        <i class="fa-solid {{ $docIcono }} text-2xl shrink-0"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-semibold text-slate-800 truncate">{{ $docNombre ?: 'Documento' }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">
                                                {{ $docExt ?: 'archivo' }} · <i class="fa-solid fa-eye"></i> Ver
                                            </div>
                                        </div>
                                    </button>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @else
                                    <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                @endif
                                <p class="text-[10px] text-slate-400 mt-1 text-right">
                                    {{ $m->created_at->format('H:i') }}
                                </p>
                                @if($m->reaccion_operador)
                                    {{-- Badge de reacción del operador al mensaje del cliente --}}
                                    <span class="absolute -bottom-3 left-3 bg-white border border-slate-200 rounded-full px-1.5 py-0.5 text-sm shadow-sm" title="Reaccionaste {{ $m->reaccion_operador }}">
                                        {{ $m->reaccion_operador }}
                                    </span>
                                @endif
                            </div>

                            @php
                                // Reacción real a Meta solo con wamid. Sin wamid → reacción local visible solo para el equipo.
                                $reaccionMeta = $m->mensaje_externo_id && str_starts_with($m->mensaje_externo_id, 'wamid.');
                            @endphp
                            {{-- Botón "responder a este mensaje" — color según modo --}}
                            <button type="button" wire:click="iniciarRespuesta({{ $m->id }})"
                                    x-show="hovered || pickerAbierto"
                                    x-transition.opacity.duration.150ms
                                    class="self-center ml-1 flex items-center justify-center w-7 h-7 rounded-full shadow border transition
                                          {{ $reaccionMeta
                                              ? 'bg-blue-50 border-blue-300 hover:bg-blue-100 text-blue-700'
                                              : 'bg-amber-50 border-amber-300 hover:bg-amber-100 text-amber-700' }}"
                                    title="{{ $reaccionMeta ? '↩️ Responder (cliente verá la cita)' : '🔖 Citar (solo visible para el equipo)' }}">
                                <i class="fa-solid fa-reply text-xs"></i>
                            </button>

                            {{-- Botón "reaccionar" (visible al hover) — color cambia según modo --}}
                            <button type="button" @click.stop="pickerAbierto = !pickerAbierto"
                                    x-show="hovered || pickerAbierto"
                                    x-transition.opacity.duration.150ms
                                    class="self-center ml-1 flex items-center justify-center w-7 h-7 rounded-full shadow border transition
                                          {{ $reaccionMeta
                                              ? 'bg-emerald-50 border-emerald-300 hover:bg-emerald-100 text-emerald-700'
                                              : 'bg-amber-50 border-amber-300 hover:bg-amber-100 text-amber-700' }}"
                                    title="{{ $reaccionMeta ? '✅ Reaccionar (cliente lo verá)' : '🔖 Solo marca interna (cliente NO la ve)' }}">
                                <i class="fa-regular fa-face-smile text-xs"></i>
                            </button>

                            {{-- Emoji picker --}}
                            <div x-show="pickerAbierto" x-cloak
                                 @click.outside="pickerAbierto = false"
                                 @keydown.escape.window="pickerAbierto = false"
                                 x-transition.opacity
                                 class="absolute left-12 -top-2 z-20 bg-white border border-slate-200 rounded-full shadow-xl px-2 py-1 flex items-center gap-1">
                                @foreach(['👍','❤️','😂','😮','😢','🙏','🔥','✅'] as $emoji)
                                    <button type="button"
                                            wire:click="reaccionarMensaje({{ $m->id }}, '{{ $emoji }}')"
                                            @click="pickerAbierto = false"
                                            class="hover:bg-slate-100 rounded-full w-9 h-9 flex items-center justify-center text-xl transition">
                                        {{ $emoji }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @elseif($m->rol === 'assistant')
                        @php $esHumano = ($m->meta['enviado_por_humano'] ?? false); @endphp
                        <div class="flex justify-end relative">
                            <div class="max-w-[85%] md:max-w-[70%] rounded-2xl rounded-tr-sm px-3 py-2 shadow-sm relative bg-[#dcf8c6]" style="background-color:#dcf8c6;">
                                @if($m->reaccion_cliente)
                                    {{-- Badge: el cliente reaccionó a nuestro mensaje --}}
                                    <span class="absolute -bottom-3 right-3 bg-white border border-slate-200 rounded-full px-1.5 py-0.5 text-sm shadow-sm" title="El cliente reaccionó {{ $m->reaccion_cliente }}">
                                        {{ $m->reaccion_cliente }}
                                    </span>
                                @endif
                                @if($esHumano)
                                    <div class="text-[10px] uppercase font-bold text-blue-700 mb-0.5">
                                        <i class="fa-solid fa-user-tie"></i> Operador
                                    </div>
                                @endif

                                {{-- 💬 Si este mensaje es respuesta a otro, mostrar la cita arriba --}}
                                @if($m->respondiendo_a_mensaje_id)
                                    @php
                                        $msgCitado = \App\Models\MensajeWhatsapp::find($m->respondiendo_a_mensaje_id);
                                        $citaPreview = '';
                                        if ($msgCitado) {
                                            if ($msgCitado->tipo === 'image')       $citaPreview = '🖼️ Imagen';
                                            elseif ($msgCitado->tipo === 'video')    $citaPreview = '🎬 Video';
                                            elseif ($msgCitado->tipo === 'audio')    $citaPreview = '🎤 Audio';
                                            elseif ($msgCitado->tipo === 'document') $citaPreview = '📄 ' . ($msgCitado->meta['filename'] ?? 'Documento');
                                            else                                      $citaPreview = mb_substr((string) $msgCitado->contenido, 0, 120);
                                        }
                                        $citaAutor = $msgCitado && $msgCitado->rol === 'user' ? 'Cliente' : 'Tú';
                                    @endphp
                                    @if($msgCitado)
                                        <div class="mb-1.5 border-l-4 border-blue-400 bg-white/60 rounded-r-md pl-2 py-1">
                                            <div class="text-[10px] font-bold text-blue-700">{{ $citaAutor }}</div>
                                            <div class="text-[11px] text-slate-700 truncate">{{ $citaPreview }}</div>
                                        </div>
                                    @endif
                                @endif
                                @if($esAudio)
                                    <audio src="{{ $mediaUrl }}" controls class="w-64 max-w-full"></audio>
                                @elseif($esImagen)
                                    <a href="{{ $mediaUrl }}" target="_blank">
                                        <img src="{{ $mediaUrl }}" class="rounded-lg max-w-full max-h-64 object-contain" alt="imagen">
                                    </a>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @elseif($esVideo)
                                    <video src="{{ $mediaUrl }}" controls preload="metadata"
                                           class="rounded-lg max-w-full max-h-80 bg-black"
                                           style="max-width: 320px;"></video>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @elseif($esDocumento)
                                    <button type="button"
                                            @click="$dispatch('open-doc-preview', { url: '{{ $mediaUrl }}', filename: @js($docNombre ?: 'Documento'), ext: '{{ $docExt }}' })"
                                            class="flex items-center gap-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 px-3 py-2.5 transition w-72 max-w-full text-left">
                                        <i class="fa-solid {{ $docIcono }} text-2xl shrink-0"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-semibold text-slate-800 truncate">{{ $docNombre ?: 'Documento' }}</div>
                                            <div class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">
                                                {{ $docExt ?: 'archivo' }} · <i class="fa-solid fa-eye"></i> Ver
                                            </div>
                                        </div>
                                    </button>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @else
                                    <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                @endif
                                <p class="text-[10px] text-slate-500 mt-1 text-right flex items-center justify-end gap-1">
                                    <span>{!! $esHumano ? '<i class="fa-solid fa-user"></i>' : '<i class="fa-solid fa-robot"></i>' !!} {{ $m->created_at->format('H:i') }}</span>
                                    @php $ack = (int) ($m->ack ?? 0); @endphp
                                    {{-- Solo mostramos ticks/reloj si podemos rastrear el estado (mensaje del operador).
                                         Los mensajes del bot se envían vía API y asumimos entregados correctamente. --}}
                                    @if($esHumano)
                                        @if($ack >= 3)
                                            <i class="fa-solid fa-check-double text-blue-500" title="Leído"></i>
                                        @elseif($ack === 2)
                                            <i class="fa-solid fa-check-double text-slate-400" title="Entregado"></i>
                                        @elseif($ack === 1)
                                            <i class="fa-solid fa-check text-slate-400" title="Enviado"></i>
                                        @else
                                            <i class="fa-regular fa-clock text-slate-400" title="Pendiente"></i>
                                        @endif
                                    @else
                                        {{-- Bot: asumimos enviado si la API respondió 200. --}}
                                        <i class="fa-solid fa-check text-slate-400" title="Enviado"></i>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- 👁️ Modal previsualizador de documentos (PDF, Word, Excel, etc.) --}}
            {{-- Se teletransporta al <body> para escapar de cualquier overflow:hidden / posicionamiento del chat --}}
            <div x-data="{
                    open: false,
                    url: '',
                    filename: '',
                    ext: '',
                    useNativePdf: false,
                    get viewerSrc() {
                        if (!this.url) return '';
                        if (this.ext === 'txt') return this.url;
                        if (this.ext === 'pdf') {
                            return this.useNativePdf
                                ? this.url + '#toolbar=1&navpanes=0&view=FitH'
                                : 'https://docs.google.com/viewer?embedded=true&url=' + encodeURIComponent(this.url);
                        }
                        if (['doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','csv'].includes(this.ext)) {
                            return 'https://docs.google.com/viewer?embedded=true&url=' + encodeURIComponent(this.url);
                        }
                        return this.url;
                    }
                 }"
                 @open-doc-preview.window="open = true; useNativePdf = false; url = $event.detail.url; filename = $event.detail.filename; ext = ($event.detail.ext || '').toLowerCase()"
                 @keydown.escape.window="open = false">

            <template x-teleport="body">
            <div x-show="open"
                 x-cloak
                 style="position: fixed; inset: 0; z-index: 9999;"
                 class="flex items-center justify-center bg-black/70 backdrop-blur-sm p-2 sm:p-4"
                 @click.self="open = false">

                <div class="bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden"
                     style="width: 96vw; height: 94vh; max-width: 1400px;"
                     x-transition>
                    {{-- Header --}}
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-200 bg-slate-50">
                        <i class="fa-solid fa-file-pdf text-rose-600 text-lg"
                           :class="{
                               'fa-file-pdf text-rose-600': ext === 'pdf',
                               'fa-file-word text-blue-600': ['doc','docx','odt'].includes(ext),
                               'fa-file-excel text-emerald-600': ['xls','xlsx','ods','csv'].includes(ext),
                               'fa-file-powerpoint text-orange-600': ['ppt','pptx','odp'].includes(ext),
                               'fa-file-lines text-slate-600': ext === 'txt',
                               'fa-file text-slate-600': !['pdf','doc','docx','odt','xls','xlsx','ods','csv','ppt','pptx','odp','txt'].includes(ext)
                           }"></i>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-slate-800 truncate" x-text="filename"></div>
                            <div class="text-[10px] text-slate-500 uppercase font-bold tracking-wider" x-text="ext || 'archivo'"></div>
                        </div>
                        <template x-if="ext === 'pdf'">
                            <button type="button" @click="useNativePdf = !useNativePdf"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold px-3 py-2 transition"
                                    :title="useNativePdf ? 'Usar visor de Google' : 'Usar visor nativo del navegador'">
                                <i class="fa-solid fa-arrows-rotate"></i>
                                <span x-text="useNativePdf ? 'Nativo' : 'Google'"></span>
                            </button>
                        </template>
                        <a :href="url" target="_blank" download
                           class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3 py-2 transition">
                            <i class="fa-solid fa-download"></i> Descargar
                        </a>
                        <a :href="url" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold px-3 py-2 transition"
                           title="Abrir en pestaña nueva">
                            <i class="fa-solid fa-up-right-from-square"></i>
                        </a>
                        <button type="button" @click="open = false"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-200 hover:bg-rose-100 hover:text-rose-600 text-slate-700 transition"
                                title="Cerrar (Esc)">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    {{-- Visor --}}
                    <div class="flex-1 bg-slate-100 overflow-hidden relative" style="min-height: 0;">
                        <template x-if="open && viewerSrc">
                            <iframe :src="viewerSrc"
                                    style="width: 100%; height: 100%; border: 0; display: block;"
                                    referrerpolicy="no-referrer"
                                    allow="fullscreen"></iframe>
                        </template>
                    </div>
                </div>
            </div>
            </template>
            </div>

            {{-- Imagen + Input (todo en un solo x-data para compartir estado) --}}
            {{-- 💡 Copiloto (modo shadow): sugerencia del bot, NO se envía al cliente --}}
            @if($conversacionActivaId)
                <livewire:chat.bot-copiloto :conversacion-id="$conversacionActivaId" wire:key="copiloto-{{ $conversacionActivaId }}" />
            @endif

            <div>
                {{-- Preview de imagen seleccionada --}}
                <div x-show="imgDataUrl" x-cloak class="bg-slate-50 border-t border-slate-200 px-4 py-3">
                    <div class="flex items-start gap-3">
                        <img :src="imgDataUrl" class="h-20 w-20 rounded-lg object-cover border border-slate-200">
                        <div class="flex-1">
                            <input type="text" x-model="imgCaption" placeholder="Caption opcional..."
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100">
                            <div class="flex items-center gap-2 mt-2">
                                <button @click="sendImage()"
                                        :disabled="sendingImg"
                                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-4 py-2 text-sm transition disabled:opacity-50">
                                    <i class="fa-solid" :class="sendingImg ? 'fa-circle-notch fa-spin' : 'fa-paper-plane'"></i>
                                    <span x-text="sendingImg ? 'Enviando...' : 'Enviar imagen'"></span>
                                </button>
                                <button @click="discardImage()"
                                        class="rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold px-3 py-2 text-sm transition">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <p x-show="imgError" x-text="imgError" class="text-xs text-rose-600 mt-1"></p>
                        </div>
                    </div>
                </div>

                {{-- Input para responder --}}
                <div class="bg-white border-t border-slate-200"
                     x-data="audioRecorder()"
                     x-init="init()">

                @if($tenantUsaMeta)
                    {{-- 🟢 Indicador de ventana 24h Meta + botón plantillas inline --}}
                    @if($ventana24hAbierta)
                        <div class="relative px-4 py-1.5 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between gap-2 text-[11px] text-emerald-700"
                             x-data="{ open: false }">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i class="fa-solid fa-circle text-emerald-500 text-[6px]"></i>
                                <span class="truncate"><strong>Ventana 24h abierta</strong> — texto libre. Restan ~{{ $ventana24hMinutosRestantes }} min.</span>
                            </div>

                            @if($plantillasMetaAprobadas->isNotEmpty())
                                <button type="button" @click="open = !open"
                                        :class="open ? 'bg-emerald-600 text-white border-emerald-700' : 'bg-white text-emerald-700 border-emerald-300'"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-bold transition flex-shrink-0">
                                    <i class="fa-brands fa-meta text-[9px]"></i>
                                    Plantilla
                                    <span class="inline-flex items-center justify-center min-w-[15px] h-[15px] px-1 rounded-full bg-emerald-200/80 text-emerald-900 text-[9px] font-bold">{{ $plantillasMetaAprobadas->count() }}</span>
                                </button>

                                {{-- 🪟 Popover flotante (absoluto, NO empuja contenido) --}}
                                <div x-show="open" x-cloak
                                     @click.away="open = false"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     class="absolute right-2 bottom-full mb-1 z-50 w-[340px] max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-2xl border border-emerald-200 overflow-hidden">
                                    <div class="px-3 py-2 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-emerald-800"><i class="fa-brands fa-meta"></i> Plantillas aprobadas</span>
                                        <button type="button" @click="open = false" class="text-emerald-700 hover:text-emerald-900">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    </div>
                                    <div class="p-3 space-y-2 max-h-[60vh] overflow-y-auto">
                                        <select wire:model.live="plantillaChatId" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs bg-white">
                                            <option value="">— Selecciona plantilla —</option>
                                            @foreach($plantillasMetaAprobadas as $tpl)
                                                <option value="{{ $tpl->id }}">{{ $tpl->nombre }} ({{ $tpl->num_variables }} vars)</option>
                                            @endforeach
                                        </select>

                                        @if($plantillaChatSeleccionada)
                                            @php
                                                // 🎯 Etiquetas amigables por variable según nombre de la plantilla
                                                $nombreTpl = strtolower($plantillaChatSeleccionada->nombre ?? '');
                                                $etiquetasVars = match(true) {
                                                    str_starts_with($nombreTpl, 'bienvenida')        => [1 => 'Nombre cliente', 2 => 'Nombre negocio'],
                                                    str_starts_with($nombreTpl, 'pedido_confirmado') => [1 => 'Nombre cliente', 2 => '# Pedido', 3 => 'Total'],
                                                    str_starts_with($nombreTpl, 'pedido_en_proceso')=> [1 => 'Nombre cliente', 2 => '# Pedido'],
                                                    str_starts_with($nombreTpl, 'pedido_en_camino') => [1 => 'Nombre cliente', 2 => '# Pedido', 3 => 'Domiciliario', 4 => 'Tiempo'],
                                                    str_starts_with($nombreTpl, 'pedido_entregado')=> [1 => 'Nombre cliente', 2 => '# Pedido'],
                                                    str_starts_with($nombreTpl, 'pedido_cancelado')=> [1 => 'Nombre cliente', 2 => '# Pedido', 3 => 'Motivo'],
                                                    str_starts_with($nombreTpl, 'encuesta')        => [1 => 'Nombre cliente', 2 => '# Pedido'],
                                                    str_starts_with($nombreTpl, 'felicitacion')    => [1 => 'Nombre cliente', 2 => 'Descuento %', 3 => 'Vence'],
                                                    str_starts_with($nombreTpl, 'recordatorio_pago')=> [1 => 'Nombre cliente', 2 => '# Pedido', 3 => 'Monto'],
                                                    str_starts_with($nombreTpl, 'promocion')       => [1 => 'Nombre cliente', 2 => 'Oferta', 3 => 'Link'],
                                                    default => [],
                                                };

                                                // Vista previa con valores sustituidos LIVE
                                                $previewLive = $plantillaChatSeleccionada->body_preview ?: '';
                                                for ($i = 1; $i <= $plantillaChatSeleccionada->num_variables; $i++) {
                                                    $valor = trim($plantillaChatVars[$i] ?? '');
                                                    $previewLive = preg_replace('/\{\{\s*' . $i . '\s*\}\}/', $valor !== '' ? '<mark class="bg-emerald-100 text-emerald-800 px-1 rounded">' . e($valor) . '</mark>' : '<mark class="bg-rose-100 text-rose-700 px-1 rounded">[falta ' . ($etiquetasVars[$i] ?? 'valor ' . $i) . ']</mark>', $previewLive);
                                                }
                                            @endphp

                                            {{-- 📱 Vista previa con sustitución LIVE de valores --}}
                                            <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2.5">
                                                <p class="font-bold text-emerald-700 mb-1.5 text-[9px] uppercase tracking-wider"><i class="fa-solid fa-eye text-[8px]"></i> Cómo lo verá el cliente</p>
                                                <div class="text-[12px] leading-relaxed text-slate-800 whitespace-pre-wrap">{!! nl2br($previewLive) !!}</div>
                                            </div>

                                            @if(($plantillaChatSeleccionada->num_variables ?? 0) > 0)
                                                <div class="space-y-1.5">
                                                    @for($i = 1; $i <= $plantillaChatSeleccionada->num_variables; $i++)
                                                        @php $hint = $etiquetasVars[$i] ?? 'Valor ' . $i; @endphp
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-slate-600 mb-0.5">
                                                                <span class="inline-flex items-center justify-center min-w-[18px] h-[14px] rounded bg-emerald-100 text-emerald-700 text-[9px] font-bold mr-1">{{ $i }}</span>
                                                                {{ $hint }}
                                                            </label>
                                                            <input type="text" wire:model.live.debounce.300ms="plantillaChatVars.{{ $i }}"
                                                                   placeholder="{{ $hint }}"
                                                                   class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200">
                                                        </div>
                                                    @endfor
                                                </div>
                                            @endif

                                            <button type="button" wire:click="enviarPlantilla" @click="open = false"
                                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3 py-2 transition">
                                                <i class="fa-solid fa-paper-plane text-[10px]"></i>
                                                Enviar plantilla
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="px-4 py-2 bg-amber-50 border-b border-amber-200">
                            <div class="flex items-center gap-2 text-[11px] text-amber-800 mb-2">
                                <i class="fa-solid fa-lock text-amber-600"></i>
                                <span><strong>Ventana 24h cerrada.</strong> Meta solo permite enviar plantillas aprobadas para reabrir la conversación.</span>
                            </div>
                            <select wire:model.live="plantillaChatId" class="w-full rounded-lg border border-amber-300 px-2 py-1.5 text-xs bg-white">
                                <option value="">— Selecciona plantilla para enviar —</option>
                                @foreach($plantillasMetaAprobadas as $tpl)
                                    <option value="{{ $tpl->id }}">{{ $tpl->nombre }} ({{ $tpl->categoria }}, {{ $tpl->num_variables }} vars)</option>
                                @endforeach
                            </select>

                            @if($plantillaChatSeleccionada)
                                <div class="mt-2 rounded-lg bg-white border border-amber-200 px-2.5 py-1.5 text-[11px] text-slate-600">
                                    <p class="font-semibold text-amber-800 mb-1">Vista previa:</p>
                                    <pre class="whitespace-pre-wrap">{{ $plantillaChatSeleccionada->body_preview ?: '(sin body)' }}</pre>
                                </div>

                                @if(($plantillaChatSeleccionada->num_variables ?? 0) > 0)
                                    <div class="mt-2 space-y-1">
                                        @for($i = 1; $i <= $plantillaChatSeleccionada->num_variables; $i++)
                                            @php $ph = '{{' . $i . '}}'; @endphp
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex w-12 h-7 items-center justify-center rounded bg-amber-100 text-amber-700 text-[10px] font-bold">{{ $ph }}</span>
                                                <input type="text"
                                                       wire:model="plantillaChatVars.{{ $i }}"
                                                       placeholder="Valor"
                                                       class="flex-1 rounded border border-slate-200 px-2 py-1 text-xs">
                                            </div>
                                        @endfor
                                    </div>
                                @endif

                                <button type="button" wire:click="enviarPlantilla"
                                        wire:loading.attr="disabled"
                                        wire:target="enviarPlantilla"
                                        class="mt-2 w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3 py-2 transition disabled:opacity-50">
                                    <i class="fa-solid fa-paper-plane text-[10px]" wire:loading.class="fa-spin fa-circle-notch" wire:target="enviarPlantilla"></i>
                                    Enviar plantilla
                                </button>
                            @endif
                        </div>
                    @endif
                @endif

                {{-- El antiguo panel "Usar plantilla Meta" se reemplazó por un popover inline al lado del badge "Ventana 24h abierta" (ver arriba) para no generar scroll en el composer. --}}

                {{-- 💬 Preview "Respondiendo a..." — encima del composer (no flotante) --}}
                @if($respondiendoAMensajeId)
                    @php
                        $esMetaReply = $respondiendoEsMeta === true;
                        $colorAcento = $esMetaReply ? '#3b82f6' : '#f59e0b';
                        $colorLabel  = $esMetaReply ? 'text-blue-700' : 'text-amber-700';
                        $colorBadgeBg = $esMetaReply ? 'bg-blue-100' : 'bg-amber-100';
                        $colorBadgeText = $esMetaReply ? 'text-blue-700' : 'text-amber-700';
                    @endphp
                    <div class="px-3 pt-2">
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex">
                            <div class="w-1 shrink-0" style="background:{{ $colorAcento }};"></div>
                            <div class="flex-1 min-w-0 px-3 py-2 flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold {{ $colorLabel }}">{{ $respondiendoAAutor }}</span>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider {{ $colorBadgeBg }} {{ $colorBadgeText }}">
                                            @if($esMetaReply)
                                                <i class="fa-solid fa-check text-[8px]"></i> Cliente lo verá
                                            @else
                                                <i class="fa-solid fa-bookmark text-[8px]"></i> Solo equipo
                                            @endif
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-600 truncate mt-0.5">{{ $respondiendoAPreview }}</div>
                                </div>
                                <button type="button" wire:click="cancelarRespuesta"
                                        class="shrink-0 flex items-center justify-center w-7 h-7 rounded-full text-slate-400 hover:bg-slate-100 hover:text-rose-600 transition"
                                        title="Cancelar respuesta">
                                    <i class="fa-solid fa-xmark text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <form wire:submit.prevent="enviar"
                      class="px-3 py-3 flex items-center gap-2"
                      @if($tenantUsaMeta && !$ventana24hAbierta) style="opacity:0.4; pointer-events:none;" @endif
                      x-data="slashMenu({{ $respuestasRapidas->toJson() }})">
                    <div class="flex-1 relative" x-show="!recording && !preview">
                        {{-- 💡 Popup respuestas rápidas (estilo Slack /comando) --}}
                        <div x-show="open && filtradas.length > 0"
                             x-cloak
                             @click.outside="open = false"
                             style="bottom: 100%; margin-bottom: 4px;"
                             class="absolute left-0 w-full max-w-md max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl z-50">
                            <div class="sticky top-0 px-2.5 py-1 border-b border-slate-100 bg-slate-50 text-[9px] font-bold uppercase tracking-wide text-slate-500">
                                <i class="fa-solid fa-bolt"></i> Respuestas <span class="font-normal normal-case text-slate-400 text-[9px]">(↑↓ Enter Esc)</span>
                            </div>
                            <template x-for="(r, i) in filtradas" :key="r.id">
                                <button type="button"
                                        @click="seleccionar(r)"
                                        @mouseenter="indice = i"
                                        :class="indice === i ? 'bg-amber-50 border-l-2 border-amber-500' : 'border-l-2 border-transparent'"
                                        class="w-full text-left px-2.5 py-1.5 hover:bg-amber-50 transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <i class="fa-solid fa-bolt text-amber-500 text-[9px]"></i>
                                        <span class="text-[11px] font-bold text-slate-800" x-text="r.atajo || '(sin atajo)'"></span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 truncate" x-text="r.texto"></p>
                                </button>
                            </template>
                        </div>


                        <textarea wire:model="nuevoMensaje"
                                  x-ref="ta"
                                  id="chat-composer-textarea"
                                  placeholder="Escribe / para respuestas rápidas..."
                                  rows="1"
                                  @input="onInput($event)"
                                  @keydown.escape="open = false"
                                  @keydown.arrow-down.prevent="if (open) indice = Math.min(indice + 1, filtradas.length - 1)"
                                  @keydown.arrow-up.prevent="if (open) indice = Math.max(indice - 1, 0)"
                                  @keydown.enter="onEnter($event)"
                                  class="w-full resize-none rounded-2xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100"></textarea>
                    </div>

                    {{-- Indicador de grabación --}}
                    <div x-show="recording" x-cloak
                         class="flex-1 flex items-center gap-2 rounded-2xl bg-rose-50 border border-rose-200 px-4 py-2.5">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                        </span>
                        <span class="text-sm font-semibold text-rose-600">Grabando...</span>
                        <span class="text-xs text-rose-500 ml-auto font-mono" x-text="formatTime(elapsed)"></span>
                    </div>

                    {{-- Preview antes de enviar --}}
                    <div x-show="preview" x-cloak
                         class="flex-1 flex items-center gap-2 rounded-2xl bg-slate-50 border border-slate-200 px-3 py-2">
                        <audio :src="preview" controls class="flex-1 h-9"></audio>
                        <button type="button" @click="descartar()"
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 hover:bg-slate-300 text-slate-600">
                            <i class="fa-solid fa-trash text-xs"></i>
                        </button>
                    </div>

                    {{-- Botón adjuntar imagen (también se puede pegar con Ctrl+V) --}}
                    <label x-show="!recording && !preview"
                           title="Adjuntar imagen (o pegar con Ctrl+V)"
                           class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer">
                        <i class="fa-solid fa-image"></i>
                        <input type="file" accept="image/*" class="hidden"
                               @change="pickImage($event)">
                    </label>

                    {{-- Botón micrófono / detener / enviar audio --}}
                    <template x-if="!recording && !preview">
                        <button type="button" @click="start()"
                                title="Grabar nota de voz"
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                            <i class="fa-solid fa-microphone"></i>
                        </button>
                    </template>
                    <template x-if="recording">
                        <button type="button" @click="stop()"
                                title="Detener grabación"
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-rose-500 hover:bg-rose-600 text-white shadow transition">
                            <i class="fa-solid fa-stop"></i>
                        </button>
                    </template>
                    <template x-if="preview">
                        <button type="button" @click="send()"
                                :disabled="sending"
                                title="Enviar nota de voz"
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-500 hover:bg-emerald-600 text-white shadow transition disabled:opacity-50">
                            <i class="fa-solid" :class="sending ? 'fa-circle-notch fa-spin' : 'fa-paper-plane'"></i>
                        </button>
                    </template>

                    {{-- Botón enviar texto --}}
                    <button type="submit"
                            x-show="!recording && !preview"
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-brand text-white shadow hover:bg-brand-dark transition">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>

                {{-- Hint sobre el modo --}}
                <div class="px-4 pb-2 text-[10px] text-slate-500 flex items-center gap-2">
                    @if($conversacionActiva->atendida_por_humano)
                        <i class="fa-solid fa-circle text-blue-500 text-[6px]"></i>
                        <span><strong>Modo manual:</strong> el bot está silenciado — solo tú respondes a este cliente.</span>
                    @else
                        <i class="fa-solid fa-circle text-emerald-500 text-[6px]"></i>
                        <span><strong>Modo mixto:</strong> tu mensaje se envía y el bot SIGUE respondiendo automáticamente. Click en "Silenciar bot" si quieres tomar control total.</span>
                    @endif
                </div>
                </div>
            </div>

        @else
            {{-- Empty state --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center text-slate-400 max-w-md px-6">
                    <i class="fa-solid fa-comment-dots text-7xl mb-4 text-slate-300"></i>
                    <h3 class="text-2xl font-bold text-slate-700 mb-2">Selecciona una conversación</h3>
                    <p class="text-sm">Elige un chat de la izquierda para empezar a atender al cliente.
                    Los mensajes nuevos aparecerán en tiempo real <i class="fa-solid fa-fire"></i></p>
                </div>
            </div>
        @endif
    </section>

    {{-- Auto-scroll INTELIGENTE: solo si el usuario YA está cerca del fondo.
         Si scrolleó hacia arriba para leer mensajes viejos, NO lo devolvemos. --}}
    <script>
        (function () {
            const UMBRAL = 120; // px de tolerancia desde el fondo

            function estaAlFondo() {
                const el = document.getElementById('chat-messages');
                if (!el) return true;
                return (el.scrollHeight - el.scrollTop - el.clientHeight) < UMBRAL;
            }

            function scrollToBottom(force = false) {
                const el = document.getElementById('chat-messages');
                if (!el) return;
                if (force || estaAlFondo()) {
                    el.scrollTop = el.scrollHeight;
                }
            }

            // Al cambiar de chat: SIEMPRE forzar (recién entró)
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('chat-cambiado', () => {
                    setTimeout(() => scrollToBottom(true), 100);
                    // 🎯 Auto-focus al campo de escribir cuando se abre una conversación.
                    // Reintenta varias veces porque Livewire puede tardar en montar el DOM.
                    const tryFocus = (intentos = 0) => {
                        const ta = document.getElementById('chat-composer-textarea');
                        if (ta) {
                            ta.focus();
                            ta.setSelectionRange(ta.value.length, ta.value.length);
                        } else if (intentos < 10) {
                            setTimeout(() => tryFocus(intentos + 1), 80);
                        }
                    };
                    setTimeout(() => tryFocus(), 200);
                });
                Livewire.on('mensaje-enviado', () => setTimeout(() => scrollToBottom(true), 100));

                if (window.Livewire?.hook) {
                    Livewire.hook('morph.updated', () => setTimeout(scrollToBottom, 50));
                }
            });

            // ⎋ ESC global: cierra la conversación activa de UNA con un solo ESC (estilo WhatsApp Web).
            // No importa si el foco está en el composer, buscador, o lista — siempre cierra.
            // Excepciones: si hay un modal abierto (data-modal-open) o si estás en otro input
            // que no sea el composer ni el buscador del chat, no interceptamos.
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;

                const target = e.target;
                const tag = (target?.tagName || '').toUpperCase();
                const enCampo = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || target?.isContentEditable;
                const idCampo = target?.id || '';

                // Permitimos cerrar desde: composer, buscador del chat, body o cualquier no-input.
                const idsPermitidos = ['chat-composer-textarea', 'chat-search-input'];
                if (enCampo && !idsPermitidos.includes(idCampo)) return;

                const hayChatAbierto = !!document.getElementById('chat-composer-textarea');
                if (!hayChatAbierto) return;

                e.preventDefault();
                e.stopPropagation();

                // Si está en el composer, blur primero para que Livewire no quede esperando.
                if (idCampo === 'chat-composer-textarea') target.blur();

                try { window.Livewire?.dispatch?.('cerrar-conversacion-activa'); } catch {}
                setTimeout(() => {
                    const s = document.getElementById('chat-search-input');
                    if (s) s.focus();
                }, 200);
            }, true); // capture: true → corre ANTES que listeners de Alpine en el composer

            scrollToBottom(true);
        })();
    </script>

    {{-- Composer (imagen) — selecciona archivo, lo muestra en preview, envía base64 al Livewire --}}
    <script>
        function chatComposer() {
            return {
                imgDataUrl: null,
                imgCaption: '',
                sendingImg: false,
                imgError: '',
                dragging: false,
                dragCounter: 0,
                init() {
                    // 🛟 Safety nets: si el drag sale de la ventana sin drop,
                    // apaga el overlay para que no se quede pegado.
                    const self = this;
                    window.addEventListener('dragend',  () => self._resetDrag());
                    window.addEventListener('mouseout', (e) => {
                        if (!e.relatedTarget && !e.toElement) self._resetDrag();
                    });
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape' && self.dragging) self._resetDrag();
                    });

                    // 📋 Listener global de paste: pegar screenshots con Ctrl+V
                    // mientras el foco esté en algún input/textarea del composer.
                    document.addEventListener('paste', (e) => {
                        // Solo si hay conversación activa y NO hay otra imagen ya en preview
                        if (this.imgDataUrl) return;
                        const items = e.clipboardData?.items;
                        if (!items) return;
                        for (const item of items) {
                            if (item.kind === 'file' && item.type.startsWith('image/')) {
                                const blob = item.getAsFile();
                                if (!blob) continue;
                                if (blob.size > 15 * 1024 * 1024) {
                                    this.imgError = 'Imagen pegada demasiado grande (máx 15 MB).';
                                    return;
                                }
                                e.preventDefault();
                                const reader = new FileReader();
                                reader.onload = () => {
                                    this.imgDataUrl = reader.result;
                                    this.imgError = '';
                                };
                                reader.onerror = () => { this.imgError = 'No se pudo leer la imagen pegada.'; };
                                reader.readAsDataURL(blob);
                                return;
                            }
                        }
                    });
                },
                pickImage(e) {
                    const file = e.target.files && e.target.files[0];
                    if (!file) return;
                    this.imgError = '';
                    if (!file.type.startsWith('image/')) {
                        this.imgError = 'El archivo no es una imagen.';
                        e.target.value = '';
                        return;
                    }
                    if (file.size > 15 * 1024 * 1024) {
                        this.imgError = 'Imagen demasiado grande (máx 15 MB).';
                        e.target.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => { this.imgDataUrl = reader.result; };
                    reader.onerror = () => { this.imgError = 'No se pudo leer la imagen.'; };
                    reader.readAsDataURL(file);
                    e.target.value = '';
                },
                async sendImage() {
                    if (!this.imgDataUrl || this.sendingImg) return;
                    this.sendingImg = true;
                    this.imgError = '';
                    try {
                        await this.$wire.enviarImagen(this.imgDataUrl, this.imgCaption);
                        this.discardImage();
                    } catch (err) {
                        this.imgError = 'Error al enviar: ' + (err.message || err);
                    } finally {
                        this.sendingImg = false;
                    }
                },
                discardImage() {
                    this.imgDataUrl = null;
                    this.imgCaption = '';
                    this.imgError = '';
                },

                // ─── Drag and drop ──────────────────────────────────────
                _hasFiles(ev) {
                    return ev.dataTransfer && Array.from(ev.dataTransfer.types || []).includes('Files');
                },
                _resetDrag() {
                    this.dragging = false;
                    this.dragCounter = 0;
                },
                onDragEnter(ev) {
                    if (!this._hasFiles(ev) || this.imgDataUrl) return;
                    this.dragCounter++;
                    this.dragging = true;
                },
                onDragOver(ev) {
                    if (!this._hasFiles(ev) || this.imgDataUrl) return;
                    if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'copy';
                    this.dragging = true;
                },
                onDragLeave(ev) {
                    this.dragCounter = Math.max(0, this.dragCounter - 1);
                    if (this.dragCounter === 0) this.dragging = false;
                },
                onDrop(ev) {
                    // Resetear SIEMPRE el estado antes de procesar.
                    this._resetDrag();
                    if (!ev.dataTransfer) return;
                    if (this.imgDataUrl) {
                        this.imgError = 'Ya tienes una imagen en preview. Envíala o descártala primero.';
                        return;
                    }
                    const file = ev.dataTransfer.files && ev.dataTransfer.files[0];
                    if (!file) return;
                    this.imgError = '';
                    if (!file.type.startsWith('image/')) {
                        this.imgError = 'Solo se aceptan imágenes.';
                        return;
                    }
                    if (file.size > 15 * 1024 * 1024) {
                        this.imgError = 'Imagen demasiado grande (máx 15 MB).';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => { this.imgDataUrl = reader.result; };
                    reader.onerror = () => { this.imgError = 'No se pudo leer la imagen.'; };
                    reader.readAsDataURL(file);
                },
            };
        }
    </script>

    {{-- Slash menu para respuestas rápidas (estilo Slack) --}}
    <script>
        function slashMenu(respuestas) {
            return {
                respuestas: respuestas || [],
                open: false,
                filtro: '',
                indice: 0,

                get filtradas() {
                    if (!this.filtro) return this.respuestas.slice(0, 8);
                    const q = this.filtro.toLowerCase();
                    return this.respuestas.filter(r =>
                        (r.atajo || '').toLowerCase().includes(q) ||
                        (r.texto || '').toLowerCase().includes(q)
                    ).slice(0, 8);
                },

                onInput(e) {
                    const v = (e.target.value || '');
                    // Solo se activa si el texto EMPIEZA con /
                    if (v.startsWith('/')) {
                        this.filtro = v.slice(1).trim();
                        this.open = true;
                        this.indice = 0;
                    } else {
                        this.open = false;
                    }
                },

                onEnter(e) {
                    if (this.open && this.filtradas.length > 0) {
                        e.preventDefault();
                        this.seleccionar(this.filtradas[this.indice]);
                        return;
                    }
                    // Enter normal → enviar
                    e.preventDefault();
                    this.$wire.enviar();
                },

                seleccionar(r) {
                    this.$wire.set('nuevoMensaje', r.texto);
                    this.open = false;
                    this.$nextTick(() => this.$refs.ta?.focus());
                },
            };
        }
    </script>

    {{-- Grabador de audio (Alpine component) — Chrome, Firefox, Edge, Safari iOS/Mac --}}
    <script>
        function audioRecorder() {
            return {
                recording: false,
                preview: null,
                sending: false,
                elapsed: 0,
                _mediaRecorder: null,
                _chunks: [],
                _stream: null,
                _timer: null,
                _blob: null,

                init() {},

                _pickMimeType() {
                    // Orden de preferencia: opus > webm > mp4 (iOS) > aac > wav > default.
                    const candidates = [
                        'audio/webm;codecs=opus',
                        'audio/ogg;codecs=opus',
                        'audio/webm',
                        'audio/mp4;codecs=mp4a.40.2',   // AAC en container mp4 (iOS Safari)
                        'audio/mp4',
                        'audio/aac',
                        'audio/wav',
                    ];
                    const isSupported = (window.MediaRecorder && typeof MediaRecorder.isTypeSupported === 'function')
                        ? (t) => MediaRecorder.isTypeSupported(t)
                        : () => false;
                    return candidates.find(isSupported) || '';
                },

                async start() {
                    // Compatibilidad: getUserMedia moderno + fallbacks
                    const getUM = (navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
                        ? (c) => navigator.mediaDevices.getUserMedia(c)
                        : null;

                    if (!getUM) {
                        alert('Tu navegador no permite acceder al micrófono. Usa Chrome, Firefox, Edge o Safari (iOS 14.3+).');
                        return;
                    }
                    if (!window.MediaRecorder) {
                        alert('Tu navegador no soporta grabación de audio (MediaRecorder). En iPhone necesitas iOS 14.3 o superior.');
                        return;
                    }
                    // HTTPS requerido en producción para getUserMedia (excepto localhost)
                    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                        alert('La grabación de audio requiere HTTPS. Estás en ' + location.protocol);
                        return;
                    }

                    try {
                        this._stream = await getUM({
                            audio: {
                                echoCancellation: true,
                                noiseSuppression: true,
                                autoGainControl: true,
                            }
                        });
                    } catch (e) {
                        const msg = e && e.name === 'NotAllowedError'
                            ? 'Permiso de micrófono denegado. Habilítalo en la configuración del navegador.'
                            : ('No se pudo acceder al micrófono: ' + (e.message || e.name || e));
                        alert(msg);
                        return;
                    }

                    this._chunks = [];
                    const mimeType = this._pickMimeType();
                    try {
                        this._mediaRecorder = new MediaRecorder(this._stream, mimeType ? { mimeType } : undefined);
                    } catch (e) {
                        // Último recurso: sin mimeType forzado
                        this._mediaRecorder = new MediaRecorder(this._stream);
                    }

                    this._mediaRecorder.ondataavailable = (e) => {
                        if (e.data && e.data.size > 0) this._chunks.push(e.data);
                    };
                    this._mediaRecorder.onstop = () => {
                        const type = (this._mediaRecorder && this._mediaRecorder.mimeType) || mimeType || 'audio/webm';
                        this._blob = new Blob(this._chunks, { type });
                        this.preview = URL.createObjectURL(this._blob);
                        this._stopStream();
                    };
                    this._mediaRecorder.onerror = (ev) => {
                        console.error('MediaRecorder error', ev);
                        alert('Error grabando: ' + (ev.error && ev.error.message || 'desconocido'));
                        this._stopStream();
                        this.recording = false;
                    };

                    // Pide chunks cada 1s — iOS Safari requiere timeslice para algunos formatos
                    try { this._mediaRecorder.start(1000); } catch (e) { this._mediaRecorder.start(); }

                    this.recording = true;
                    this.elapsed = 0;
                    this._timer = setInterval(() => {
                        this.elapsed++;
                        // Límite de seguridad: 3 minutos
                        if (this.elapsed >= 180) this.stop();
                    }, 1000);
                },

                stop() {
                    if (this._timer) { clearInterval(this._timer); this._timer = null; }
                    try {
                        if (this._mediaRecorder && this._mediaRecorder.state !== 'inactive') {
                            this._mediaRecorder.stop();
                        }
                    } catch (_) {}
                    this.recording = false;
                },

                descartar() {
                    if (this.preview) { try { URL.revokeObjectURL(this.preview); } catch(_) {} }
                    this.preview = null;
                    this._blob = null;
                    this._chunks = [];
                },

                async send() {
                    if (!this._blob || this.sending) return;
                    this.sending = true;
                    try {
                        const dataUrl = await this._blobToDataUrl(this._blob);
                        await this.$wire.enviarAudio(dataUrl);
                        this.descartar();
                    } catch (e) {
                        console.error('Error enviando audio', e);
                        alert('No se pudo enviar el audio: ' + (e.message || e));
                    } finally {
                        this.sending = false;
                    }
                },

                _blobToDataUrl(blob) {
                    return new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(reader.result);
                        reader.onerror = () => reject(reader.error || new Error('No se pudo leer el audio'));
                        reader.readAsDataURL(blob);
                    });
                },

                _stopStream() {
                    if (this._stream) {
                        try { this._stream.getTracks().forEach(t => t.stop()); } catch(_) {}
                        this._stream = null;
                    }
                },

                formatTime(s) {
                    const m = Math.floor(s / 60);
                    const sec = s % 60;
                    return `${m}:${sec.toString().padStart(2, '0')}`;
                },
            };
        }
    </script>

    {{-- Real-time: escucha eventos de Reverb y refresca la conversación --}}
    @if($conversacionActiva)
        <script>
            (function () {
                if (!window.Echo) return;

                const convId = {{ $conversacionActiva->id }};
                const channelName = `chat.${convId}`;

                // Limpiar listeners previos para no duplicar
                if (window.__chatChannel) {
                    try { window.Echo.leave(window.__chatChannel); } catch(_) {}
                }
                window.__chatChannel = channelName;

                window.Echo.channel(channelName)
                    .listen('.mensaje.nuevo', (e) => {
                        console.log('💬 Mensaje nuevo en chat:', e);

                        // Sonido si es del cliente — beep generado con Web Audio API
                        if (e.rol === 'user') {
                            try {
                                const Ctx = window.AudioContext || window.webkitAudioContext;
                                const ctx = new Ctx();
                                const now = ctx.currentTime;
                                [{f:880,s:0,d:.12},{f:660,s:.15,d:.18}].forEach(t => {
                                    const o = ctx.createOscillator(), g = ctx.createGain();
                                    o.type = 'sine'; o.frequency.value = t.f;
                                    g.gain.setValueAtTime(0, now+t.s);
                                    g.gain.linearRampToValueAtTime(.3, now+t.s+.02);
                                    g.gain.linearRampToValueAtTime(0, now+t.s+t.d);
                                    o.connect(g).connect(ctx.destination);
                                    o.start(now+t.s); o.stop(now+t.s+t.d+.05);
                                });
                            } catch(_) {}
                        }

                        // Refrescar el componente Livewire
                        if (window.Livewire) {
                            Livewire.dispatch('refrescar-chat');
                        }
                    });

                // También escuchar el canal global para refrescar la lista lateral
                if (!window.__chatGlobalListenerSet) {
                    window.__chatGlobalListenerSet = true;
                    window.Echo.channel('chat')
                        .listen('.mensaje.nuevo', (e) => {
                            if (window.Livewire) {
                                Livewire.dispatch('refrescar-chat');
                            }
                        });
                }
            })();
        </script>
    @endif

    {{-- Modal: iniciar nueva conversación con un número --}}
    @if($nuevoChatModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarNuevoChat">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Nueva conversación</h3>
                            <p class="text-xs text-slate-500">Envía el primer mensaje a un número</p>
                        </div>
                    </div>
                    <button wire:click="cerrarNuevoChat" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Teléfono (con código país) *</label>
                        <input type="text" wire:model="nuevoChatTel" placeholder="573001234567"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-amber-100">
                        <p class="text-[10px] text-slate-400 mt-1">Solo dígitos. Ej: 573001234567</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre (opcional)</label>
                        <input type="text" wire:model="nuevoChatNombre" placeholder="Nombre del cliente"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Primer mensaje *</label>
                        <textarea wire:model="nuevoChatMensaje" rows="3" placeholder="Hola, ¿cómo estás?"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100"></textarea>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarNuevoChat"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button wire:click="crearNuevoChat"
                            wire:loading.attr="disabled"
                            wire:target="crearNuevoChat"
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-4 py-2 text-sm font-bold text-white shadow-lg disabled:opacity-50">
                        <span wire:loading.remove wire:target="crearNuevoChat"><i class="fa-solid fa-paper-plane"></i> Enviar</span>
                        <span wire:loading wire:target="crearNuevoChat"><i class="fa-solid fa-circle-notch fa-spin"></i> Enviando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ╔═══ MODAL: Publicar estado en WhatsApp ═══╗ --}}
    @if($estadoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             x-data="{
                preview: null,
                mime: '',
                filename: '',
                dataUrl: '',
                publicando: false,
                handleFile(ev) {
                    const f = ev.target.files[0];
                    if (!f) return;
                    if (f.size > 16 * 1024 * 1024) {
                        alert('El archivo supera 16MB');
                        ev.target.value = '';
                        return;
                    }
                    this.mime = f.type;
                    this.filename = f.name;
                    const reader = new FileReader();
                    reader.onload = e => {
                        this.dataUrl = e.target.result;
                        this.preview = e.target.result;
                    };
                    reader.readAsDataURL(f);
                },
                async publicar() {
                    if (!this.dataUrl) { alert('Selecciona una imagen o video'); return; }
                    this.publicando = true;
                    await @this.call('publicarEstado', this.dataUrl, @this.estadoCaption);
                    this.publicando = false;
                }
             }">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-brand to-brand-secondary text-white">
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp"></i> Estados de WhatsApp
                    </h3>
                    <button wire:click="cerrarEstadoModal" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                </div>

                {{-- Tabs --}}
                <div class="flex border-b border-slate-200 bg-slate-50">
                    <button wire:click="cambiarTabEstado('publicar')"
                            class="flex-1 px-4 py-2.5 text-xs font-semibold transition
                                @if($estadoTab==='publicar') text-brand-secondary border-b-2 border-brand bg-white @else text-slate-500 hover:text-slate-700 @endif">
                        <i class="fa-solid fa-circle-plus mr-1"></i> Publicar nuevo
                    </button>
                    <button wire:click="cambiarTabEstado('listar')"
                            class="flex-1 px-4 py-2.5 text-xs font-semibold transition
                                @if($estadoTab==='listar') text-brand-secondary border-b-2 border-brand bg-white @else text-slate-500 hover:text-slate-700 @endif">
                        <i class="fa-solid fa-list mr-1"></i> Mis estados
                    </button>
                </div>

                @if($estadoTab === 'publicar')
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Imagen o video</label>
                        <input type="file"
                               accept="image/jpeg,image/png,image/webp,video/mp4"
                               @change="handleFile($event)"
                               class="block w-full text-sm text-slate-700 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-brand-secondary hover:file:bg-orange-100 cursor-pointer">
                        <p class="text-[10px] text-slate-400 mt-1">JPG, PNG, WEBP o MP4. Máx 16MB.</p>

                        <div x-show="preview" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-2 flex items-center justify-center">
                            <template x-if="mime.startsWith('image/')">
                                <img :src="preview" alt="preview" class="max-h-48 rounded-lg">
                            </template>
                            <template x-if="mime.startsWith('video/')">
                                <video :src="preview" controls class="max-h-48 rounded-lg"></video>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Texto del estado (opcional)</label>
                        <textarea wire:model="estadoCaption" rows="3" placeholder="Escribe algo para acompañar tu estado…"
                                  maxlength="700"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-amber-100"></textarea>
                    </div>

                    <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] text-amber-800">
                        <i class="fa-solid fa-circle-info"></i>
                        El estado quedará visible 24h en el WhatsApp del número conectado.
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarEstadoModal"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button @click="publicar()"
                            :disabled="publicando || !dataUrl"
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-4 py-2 text-sm font-bold text-white shadow-lg disabled:opacity-50">
                        <span x-show="!publicando"><i class="fa-solid fa-paper-plane"></i> Publicar</span>
                        <span x-show="publicando" x-cloak><i class="fa-solid fa-circle-notch fa-spin"></i> Publicando…</span>
                    </button>
                </div>
                @else
                {{-- Tab: Mis estados publicados --}}
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-slate-500">Estados activos (vigencia 24h).</p>
                        <button wire:click="cargarEstadosPublicados"
                                wire:loading.attr="disabled"
                                wire:target="cargarEstadosPublicados"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">
                            <i class="fa-solid fa-rotate-right" wire:loading.class="fa-spin" wire:target="cargarEstadosPublicados"></i> Refrescar
                        </button>
                    </div>

                    @if($cargandoEstados)
                        <div class="text-center py-8 text-slate-400 text-sm">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Cargando…
                        </div>
                    @elseif($estadosError)
                        <div class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700">
                            <i class="fa-solid fa-triangle-exclamation"></i> {{ $estadosError }}
                        </div>
                    @elseif(empty($estadosPublicados))
                        <div class="text-center py-8 text-slate-400 text-sm">
                            <i class="fa-regular fa-circle-pause text-2xl mb-2"></i>
                            <p>Aún no has publicado estados.</p>
                            <p class="text-[11px] mt-1">Usa la pestaña "Publicar nuevo" arriba.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-2 max-h-[55vh] overflow-y-auto pr-1">
                            @foreach($estadosPublicados as $est)
                                <div class="rounded-xl overflow-hidden border border-slate-200 bg-slate-50">
                                    <div class="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
                                        @if($est['es_video'])
                                            <video src="{{ $est['media_url'] }}" controls class="w-full h-full object-cover"></video>
                                        @else
                                            <img src="{{ $est['media_url'] }}" alt="estado" loading="lazy" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <div class="px-2.5 py-2 space-y-1">
                                        @if($est['caption'])
                                            <p class="text-[11px] text-slate-700 line-clamp-2">{{ $est['caption'] }}</p>
                                        @endif
                                        <p class="text-[10px] text-slate-400">
                                            <i class="fa-regular fa-clock"></i>
                                            @if($est['created_at'])
                                                {{ \Carbon\Carbon::parse($est['created_at'])->diffForHumans() }}
                                            @else
                                                —
                                            @endif
                                        </p>
                                        @if($est['phone'])
                                            <p class="text-[10px] text-slate-400 truncate">
                                                <i class="fa-brands fa-whatsapp text-emerald-500"></i> {{ $est['wa_name'] ?: $est['phone'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarEstadoModal"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cerrar
                    </button>
                </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ╔═══════════════════════════════════════════════════════════════╗ --}}
    {{-- ║ 📋 MODAL: Estado estructurado del pedido                      ║ --}}
    {{-- ╚═══════════════════════════════════════════════════════════════╝ --}}
    @if($pedidoEstadoModal && $conversacionActiva)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             wire:click.self="cerrarPedidoEstadoModal"
             wire:poll.5s>
            <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
                {{-- Header con tabs --}}
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-gradient-to-r from-violet-50 to-white">
                    <div class="flex items-center justify-between px-6 pt-4">
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-800">
                                <i class="fa-solid fa-clipboard-list text-violet-600"></i>
                                Estado del pedido
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ $conversacionActiva->telefono_normalizado }}
                                @if($conversacionActiva->cliente?->nombre)
                                    · {{ $conversacionActiva->cliente->nombre }}
                                @endif
                            </p>
                        </div>
                        <button wire:click="cerrarPedidoEstadoModal"
                                class="rounded-full w-9 h-9 inline-flex items-center justify-center text-slate-500 hover:bg-slate-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    {{-- Tabs --}}
                    <div class="flex gap-1 mt-3 px-6">
                        <button wire:click="cambiarTab('estado')"
                                class="rounded-t-lg px-4 py-2 text-sm font-semibold transition border-b-2
                                    {{ $pedidoEstadoTab === 'estado'
                                        ? 'border-violet-500 text-violet-700 bg-white'
                                        : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                            <i class="fa-solid fa-clipboard-list mr-1"></i> Datos del pedido
                        </button>
                        <button wire:click="cambiarTab('prompt')"
                                class="rounded-t-lg px-4 py-2 text-sm font-semibold transition border-b-2
                                    {{ $pedidoEstadoTab === 'prompt'
                                        ? 'border-violet-500 text-violet-700 bg-white'
                                        : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                            <i class="fa-solid fa-brain mr-1"></i> Prompt LLM
                            <span class="ml-1 text-[9px] rounded-full bg-violet-100 text-violet-700 px-1.5">
                                debug
                            </span>
                        </button>
                    </div>
                </div>

                {{-- ═══ TAB: PROMPT LLM ═══ --}}
                @if($pedidoEstadoTab === 'prompt')
                    @if(!empty($promptInspeccion) && empty($promptInspeccion['error']))
                        <div class="px-6 py-4 space-y-3">
                            {{-- Stats --}}
                            <div class="grid grid-cols-4 gap-2">
                                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                    <div class="text-[9px] uppercase font-bold text-slate-500 tracking-wider">Modelo</div>
                                    <div class="text-sm font-bold text-slate-800 mt-0.5">{{ $promptInspeccion['meta']['modelo'] }}</div>
                                </div>
                                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                    <div class="text-[9px] uppercase font-bold text-slate-500 tracking-wider">Caracteres</div>
                                    <div class="text-sm font-bold text-slate-800 mt-0.5">{{ number_format($promptInspeccion['stats']['caracteres']) }}</div>
                                </div>
                                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                    <div class="text-[9px] uppercase font-bold text-slate-500 tracking-wider">Tokens aprox</div>
                                    <div class="text-sm font-bold text-violet-700 mt-0.5">~{{ number_format($promptInspeccion['stats']['tokens_aprox']) }}</div>
                                </div>
                                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                    <div class="text-[9px] uppercase font-bold text-slate-500 tracking-wider">Mensajes hist.</div>
                                    <div class="text-sm font-bold text-slate-800 mt-0.5">{{ $promptInspeccion['stats']['mensajes'] }}</div>
                                </div>
                            </div>

                            {{-- Bloques colapsables --}}
                            <div class="space-y-2" x-data="{ abierto: 0 }">
                                @foreach($promptInspeccion['bloques'] as $idx => $bloque)
                                    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                                        <button type="button"
                                                @click="abierto = (abierto === {{ $idx }} ? null : {{ $idx }})"
                                                class="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition text-left">
                                            <div>
                                                <div class="text-sm font-bold text-slate-800">{{ $bloque['titulo'] }}</div>
                                                @if(!empty($bloque['subtitulo']))
                                                    <div class="text-[10px] text-slate-500 font-mono">{{ $bloque['subtitulo'] }}</div>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] text-slate-400">
                                                    {{ number_format(mb_strlen($bloque['contenido'])) }} chars
                                                </span>
                                                <i class="fa-solid fa-chevron-down text-slate-400 transition"
                                                   :class="abierto === {{ $idx }} ? 'rotate-180' : ''"></i>
                                            </div>
                                        </button>
                                        <div x-show="abierto === {{ $idx }}" x-collapse class="border-t border-slate-100">
                                            <pre class="text-[11px] leading-relaxed text-slate-700 bg-slate-50 p-4 overflow-x-auto whitespace-pre-wrap font-mono max-h-96 overflow-y-auto">{{ $bloque['contenido'] }}</pre>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- 🛠️ TOOLS DISPONIBLES --}}
                                @if(!empty($promptInspeccion['tools']))
                                    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                                        <button type="button"
                                                @click="abierto = (abierto === 'tools' ? null : 'tools')"
                                                class="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition text-left">
                                            <div>
                                                <div class="text-sm font-bold text-slate-800">
                                                    <i class="fa-solid fa-screwdriver-wrench"></i> Tools disponibles ({{ $promptInspeccion['stats']['tools_total'] ?? count($promptInspeccion['tools']) }})
                                                    <span class="text-[10px] font-normal text-slate-500 ml-1">
                                                        — {{ $promptInspeccion['stats']['tools_paso'] ?? 0 }} habilitadas en este paso
                                                    </span>
                                                </div>
                                                <div class="text-[10px] text-slate-500 font-mono">
                                                    Funciones que el LLM puede invocar (function calling de OpenAI)
                                                </div>
                                            </div>
                                            <i class="fa-solid fa-chevron-down text-slate-400 transition"
                                               :class="abierto === 'tools' ? 'rotate-180' : ''"></i>
                                        </button>
                                        <div x-show="abierto === 'tools'" x-collapse class="border-t border-slate-100 p-3 space-y-2 max-h-[500px] overflow-y-auto"
                                             x-data="{ tipoFiltro: 'todas', toolAbierta: null }">
                                            {{-- Filtros --}}
                                            <div class="flex gap-1 mb-2 sticky top-0 bg-white pb-2 border-b border-slate-100 z-10">
                                                <button @click="tipoFiltro = 'todas'"
                                                        :class="tipoFiltro === 'todas' ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-500'"
                                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold transition">
                                                    Todas ({{ count($promptInspeccion['tools']) }})
                                                </button>
                                                <button @click="tipoFiltro = 'habilitadas'"
                                                        :class="tipoFiltro === 'habilitadas' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold transition">
                                                    <i class="fa-solid fa-check"></i> Habilitadas ({{ $promptInspeccion['stats']['tools_paso'] ?? 0 }})
                                                </button>
                                                <button @click="tipoFiltro = 'bloqueadas'"
                                                        :class="tipoFiltro === 'bloqueadas' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500'"
                                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold transition">
                                                    <i class="fa-solid fa-xmark"></i> Bloqueadas ({{ count($promptInspeccion['tools']) - ($promptInspeccion['stats']['tools_paso'] ?? 0) }})
                                                </button>
                                            </div>

                                            @foreach($promptInspeccion['tools'] as $idx => $tool)
                                                <div x-show="tipoFiltro === 'todas' || (tipoFiltro === 'habilitadas' && {{ $tool['permitida_paso'] ? 'true' : 'false' }}) || (tipoFiltro === 'bloqueadas' && {{ $tool['permitida_paso'] ? 'false' : 'true' }})"
                                                     class="rounded-lg border {{ $tool['permitida_paso'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200 bg-slate-50/50 opacity-70' }}">
                                                    <button type="button"
                                                            @click="toolAbierta = (toolAbierta === {{ $idx }} ? null : {{ $idx }})"
                                                            class="w-full flex items-center justify-between px-3 py-2 hover:bg-slate-50/50 text-left">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <span class="text-xs">
                                                                @if($tool['permitida_paso'])
                                                                    <span class="text-emerald-600">●</span>
                                                                @else
                                                                    <span class="text-slate-400">○</span>
                                                                @endif
                                                            </span>
                                                            <code class="text-[11px] font-mono font-bold text-slate-800">{{ $tool['nombre'] }}</code>
                                                            @if($tool['permitida_paso'])
                                                                <span class="rounded-full bg-emerald-100 text-emerald-700 px-1.5 py-0.5 text-[8px] font-bold uppercase">activa</span>
                                                            @else
                                                                <span class="rounded-full bg-slate-200 text-slate-500 px-1.5 py-0.5 text-[8px] font-bold uppercase">paso oculta</span>
                                                            @endif
                                                        </div>
                                                        <i class="fa-solid fa-chevron-down text-slate-400 text-[10px] transition flex-shrink-0"
                                                           :class="toolAbierta === {{ $idx }} ? 'rotate-180' : ''"></i>
                                                    </button>
                                                    <div x-show="toolAbierta === {{ $idx }}" x-collapse class="border-t border-slate-100 p-2.5 space-y-2 bg-white">
                                                        <div>
                                                            <div class="text-[9px] uppercase font-bold text-slate-500 tracking-wider mb-1">Descripción</div>
                                                            <p class="text-[11px] text-slate-700 leading-relaxed">
                                                                {{ \Illuminate\Support\Str::limit($tool['descripcion'], 400) }}
                                                            </p>
                                                        </div>
                                                        @php
                                                            // properties puede venir como array o stdClass (objeto vacío)
                                                            $paramsRaw = $tool['parametros']['properties'] ?? [];
                                                            $params = is_array($paramsRaw) ? $paramsRaw : (array) $paramsRaw;
                                                            $required = $tool['parametros']['required'] ?? [];
                                                            if (!is_array($required)) $required = (array) $required;
                                                        @endphp
                                                        @if(!empty($params))
                                                            <div>
                                                                <div class="text-[9px] uppercase font-bold text-slate-500 tracking-wider mb-1">
                                                                    Parámetros ({{ count($params) }})
                                                                </div>
                                                                <div class="space-y-1">
                                                                    @foreach($params as $name => $def)
                                                                        <div class="text-[10px] font-mono">
                                                                            <span class="text-violet-700 font-bold">{{ $name }}</span><span class="text-slate-500">: {{ is_array($def) ? ($def['type'] ?? '?') : '?' }}</span>
                                                                            @if(in_array($name, $required, true))<span class="text-rose-500 ml-1">*</span>@endif
                                                                            @if(is_array($def) && !empty($def['description']))
                                                                                <div class="ml-3 text-slate-500 text-[10px] leading-tight font-sans italic">{{ \Illuminate\Support\Str::limit($def['description'], 200) }}</div>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Historial --}}
                                <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                                    <button type="button"
                                            @click="abierto = (abierto === 'hist' ? null : 'hist')"
                                            class="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition text-left">
                                        <div>
                                            <div class="text-sm font-bold text-slate-800">
                                                Historial — últimos {{ count($promptInspeccion['historial']) }} mensajes
                                            </div>
                                            <div class="text-[10px] text-slate-500 font-mono">
                                                ConversacionWhatsapp::historialParaIA() — solo del día actual
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-down text-slate-400 transition"
                                           :class="abierto === 'hist' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="abierto === 'hist'" x-collapse class="border-t border-slate-100 p-3 space-y-2 max-h-96 overflow-y-auto">
                                        @foreach($promptInspeccion['historial'] as $i => $m)
                                            <div class="rounded-lg p-2 text-[11px] {{ $m['role'] === 'user' ? 'bg-blue-50' : 'bg-emerald-50' }}">
                                                <div class="text-[9px] font-bold uppercase {{ $m['role'] === 'user' ? 'text-blue-700' : 'text-emerald-700' }}">
                                                    [{{ $i }}] {{ $m['role'] }}
                                                </div>
                                                <div class="text-slate-700 mt-0.5 font-mono whitespace-pre-wrap">{{ $m['content'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <p class="text-[10px] text-slate-400 text-center pt-1">
                                <i class="fa-solid fa-gear"></i> Esta es exactamente la información que recibe OpenAI antes de generar la siguiente respuesta.
                            </p>
                        </div>
                    @elseif(!empty($promptInspeccion['error']))
                        <div class="px-6 py-12 text-center">
                            <i class="fa-solid fa-circle-exclamation text-rose-500 text-3xl mb-2"></i>
                            <p class="text-sm text-rose-600">Error al cargar prompt: {{ $promptInspeccion['error'] }}</p>
                        </div>
                    @else
                        <div class="px-6 py-12 text-center text-sm text-slate-500">
                            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Cargando prompt...
                        </div>
                    @endif
                @else
                {{-- ═══ TAB: ESTADO (original) ═══ --}}
                @if($pedidoEstado)
                    @php
                        $pasoColores = [
                            'inicio'         => 'bg-slate-100 text-slate-700',
                            'producto'       => 'bg-blue-100 text-blue-700',
                            'entrega'        => 'bg-cyan-100 text-cyan-700',
                            'identificacion' => 'bg-amber-100 text-amber-700',
                            'confirmacion'   => 'bg-violet-100 text-violet-700',
                            'confirmado'     => 'bg-emerald-100 text-emerald-700',
                            'abandonado'     => 'bg-rose-100 text-rose-700',
                        ];
                        $pasoColor = $pasoColores[$pedidoEstado->paso_actual] ?? 'bg-slate-100 text-slate-700';
                        $faltantes = $pedidoEstado->camposFaltantes();
                        $completo  = $pedidoEstado->estaCompleto();
                    @endphp

                    <div class="px-6 py-5 space-y-5">
                        {{-- Paso actual + estado completo --}}
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Paso actual</p>
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-bold {{ $pasoColor }} mt-1">
                                    {{ ucfirst($pedidoEstado->paso_actual) }}
                                </span>
                            </div>
                            <div class="text-right">
                                @if($completo)
                                    <span class="inline-flex items-center gap-1 text-emerald-600 font-bold text-sm">
                                        <i class="fa-solid fa-circle-check"></i> Datos completos
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-amber-600 font-bold text-sm">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        Falta {{ count($faltantes) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if(!empty($faltantes))
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                                <strong>Pendientes:</strong> {{ implode(', ', $faltantes) }}
                            </div>
                        @endif

                        @if($pedidoEstado->pedido_id)
                            <div class="rounded-xl bg-emerald-50 border border-emerald-300 p-3 text-sm text-emerald-800">
                                <i class="fa-solid fa-check-double mr-1"></i>
                                <strong>Pedido #{{ $pedidoEstado->pedido_id }}</strong> creado el
                                {{ $pedidoEstado->confirmado_at?->format('d/m/Y H:i') }}
                            </div>
                        @endif

                        <div class="grid gap-4 md:grid-cols-2">
                            {{-- 🛒 Productos --}}
                            <div class="rounded-xl border border-slate-200 p-4">
                                <h4 class="text-sm font-bold text-slate-800 mb-2">
                                    <i class="fa-solid fa-cart-shopping text-blue-600 mr-1"></i> Productos
                                </h4>
                                @if(!empty($pedidoEstado->productos))
                                    <ul class="space-y-1 text-sm">
                                        @foreach($pedidoEstado->productos as $p)
                                            <li class="flex items-center justify-between border-b border-slate-100 pb-1">
                                                <span class="font-medium text-slate-700">{{ $p['name'] ?? '?' }}</span>
                                                <span class="text-slate-500 text-xs">{{ $p['quantity'] ?? 1 }} {{ $p['unit'] ?? '' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-xs text-slate-400 italic">— Sin productos —</p>
                                @endif
                            </div>

                            {{-- 🚚 Entrega --}}
                            <div class="rounded-xl border border-slate-200 p-4">
                                <h4 class="text-sm font-bold text-slate-800 mb-2">
                                    <i class="fa-solid fa-truck text-cyan-600 mr-1"></i> Entrega
                                </h4>
                                @if($pedidoEstado->metodo_entrega)
                                    @php
                                        $metodoLabel = $pedidoEstado->metodo_entrega === 'domicilio'
                                            ? '🚚 Despacho'
                                            : '🏪 Cliente recoge';
                                    @endphp
                                    <p class="text-xs">
                                        <strong>Método:</strong>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold">{{ $metodoLabel }}</span>
                                    </p>
                                    @if($pedidoEstado->metodo_entrega === 'domicilio')
                                        <p class="text-xs mt-1"><strong>Dirección:</strong> {{ $pedidoEstado->direccion ?: '—' }}</p>
                                        @if($pedidoEstado->barrio)<p class="text-xs"><strong>Barrio:</strong> {{ $pedidoEstado->barrio }}</p>@endif
                                        <p class="text-xs mt-1">
                                            <strong>Cobertura:</strong>
                                            @if($pedidoEstado->cobertura_validada)
                                                <span class="text-emerald-600"><i class="fa-solid fa-circle-check"></i></span>
                                            @else
                                                <span class="text-rose-600"><i class="fa-solid fa-circle-xmark"></i></span>
                                            @endif
                                            @if($pedidoEstado->distancia_km) · {{ $pedidoEstado->distancia_km }} km @endif
                                        </p>
                                    @elseif($pedidoEstado->metodo_entrega === 'recoger')
                                        <p class="text-xs mt-1">
                                            <strong>Sede:</strong>
                                            {{ $pedidoEstado->sede?->nombre ?: ($pedidoEstado->sede_id ? "ID #{$pedidoEstado->sede_id}" : '—') }}
                                        </p>
                                    @endif
                                @else
                                    <p class="text-xs text-slate-400 italic">— Sin método —</p>
                                @endif
                            </div>

                            {{-- 👤 Identificación --}}
                            <div class="rounded-xl border border-slate-200 p-4">
                                <h4 class="text-sm font-bold text-slate-800 mb-2">
                                    <i class="fa-solid fa-id-card text-amber-600 mr-1"></i> Identificación
                                </h4>
                                <p class="text-xs"><strong>Cédula:</strong> {{ $pedidoEstado->cedula ?: '—' }}</p>
                                <p class="text-xs"><strong>Nombre:</strong> {{ $pedidoEstado->nombre_cliente ?: '—' }}</p>
                                <p class="text-xs"><strong>Teléfono:</strong> {{ $pedidoEstado->telefono ?: '—' }}</p>
                                <p class="text-xs mt-1">
                                    <strong>En ERP:</strong>
                                    @if($pedidoEstado->cliente_existe_erp)
                                        <span class="text-emerald-600 font-semibold"><i class="fa-solid fa-circle-check"></i> Sí</span>
                                    @else
                                        <span class="text-slate-500">No verificado</span>
                                    @endif
                                </p>
                            </div>

                            {{-- 💳 Pago --}}
                            <div class="rounded-xl border border-slate-200 p-4">
                                <h4 class="text-sm font-bold text-slate-800 mb-2">
                                    <i class="fa-solid fa-credit-card text-violet-600 mr-1"></i> Pago / extras
                                </h4>
                                <p class="text-xs"><strong>Método:</strong> {{ $pedidoEstado->metodo_pago ?: '—' }}</p>
                                @if($pedidoEstado->cupon_code)<p class="text-xs"><strong>Cupón:</strong> {{ $pedidoEstado->cupon_code }}</p>@endif
                                @if($pedidoEstado->notas)
                                    <p class="text-xs mt-1"><strong>Notas:</strong></p>
                                    <p class="text-[11px] text-slate-600 italic">{{ \Illuminate\Support\Str::limit($pedidoEstado->notas, 120) }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Validaciones --}}
                        @if(!empty($pedidoEstado->validaciones))
                            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                <p class="text-[10px] font-semibold text-slate-600 uppercase tracking-wide mb-2">
                                    <i class="fa-solid fa-clipboard-check mr-1"></i> Validaciones registradas
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($pedidoEstado->validaciones as $clave => $valor)
                                        <span class="rounded-full bg-white border border-slate-200 px-2.5 py-0.5 text-[10px]">
                                            {{ $clave }}:
                                            @if($valor)<span class="text-emerald-600 font-bold"><i class="fa-solid fa-check"></i></span>
                                            @else<span class="text-rose-600 font-bold"><i class="fa-solid fa-xmark"></i></span>@endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <p class="text-[10px] text-slate-400 text-center pt-1">
                            Última actualización: {{ $pedidoEstado->updated_at?->format('d/m/Y H:i:s') }}
                        </p>
                    </div>
                @else
                    <div class="px-6 py-12 text-center text-sm text-slate-500">
                        Cargando estado del pedido...
                    </div>
                @endif
                @endif {{-- /tab estado vs prompt --}}

                {{-- Footer --}}
                <div class="sticky bottom-0 flex items-center justify-between border-t border-slate-200 bg-white px-6 py-4">
                    <button wire:click="resetearEstadoPedido"
                            wire:confirm="¿Resetear todo el estado del pedido? El bot empezará limpio en su próxima respuesta."
                            class="rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 px-4 py-2 text-xs font-semibold text-rose-700 transition inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-eraser"></i> Reset estado
                    </button>
                    <button wire:click="cerrarPedidoEstadoModal"
                            class="rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
