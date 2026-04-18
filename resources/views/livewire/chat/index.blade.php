<div class="h-[calc(100vh-5rem)] flex flex-col lg:flex-row bg-slate-100"
     wire:poll.10s="refrescar">

    {{-- ╔═══ COLUMNA IZQUIERDA: lista de conversaciones ═══╗ --}}
    <aside class="w-full lg:w-96 flex-shrink-0 bg-white border-r border-slate-200 flex flex-col">

        {{-- Header --}}
        <div class="p-4 border-b border-slate-200 bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white">
            <h2 class="text-lg font-bold flex items-center gap-2">
                <i class="fa-solid fa-comments"></i> Chat en vivo
            </h2>
            <p class="text-xs text-white/80">Atiende clientes en tiempo real</p>
        </div>

        {{-- Filtros --}}
        <div class="p-3 border-b border-slate-200 space-y-2">
            <input type="text" wire:model.live.debounce.400ms="busqueda"
                   placeholder="Buscar cliente o teléfono..."
                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#d68643] focus:ring-[#d68643]">

            <div class="flex gap-1">
                @foreach([
                    'todas'  => ['Todas', 'fa-list'],
                    'activa' => ['Activas', 'fa-circle-dot'],
                    'humano' => ['Humano', 'fa-user'],
                    'bot'    => ['Bot', 'fa-robot'],
                ] as $key => [$label, $icon])
                    <button wire:click="$set('filtroEstado', '{{ $key }}')"
                            class="flex-1 rounded-lg px-2 py-1.5 text-xs font-semibold transition
                                  {{ $filtroEstado === $key ? 'bg-[#d68643] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <i class="fa-solid {{ $icon }} mr-0.5"></i> {{ $label }}
                    </button>
                @endforeach
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

                <button wire:click="seleccionar({{ $c->id }})"
                        class="w-full text-left flex items-center gap-3 px-4 py-3 border-b border-slate-100 hover:bg-amber-50/40 transition
                              {{ $isActiva ? 'bg-amber-50' : '' }}">

                    <div class="relative flex-shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white font-bold">
                            {{ $iniciales ?: 'C' }}
                        </div>
                        @if($c->atendida_por_humano)
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
                            <span class="font-semibold text-slate-800 truncate">{{ $c->cliente?->nombre ?? 'Cliente' }}</span>
                            <span class="text-[10px] text-slate-400 flex-shrink-0">{{ $c->ultimo_mensaje_at?->diffForHumans(null, true) }}</span>
                        </div>
                        <div class="text-xs text-slate-500 truncate font-mono">{{ $c->telefono_normalizado }}</div>
                        <div class="text-[10px] text-slate-400">{{ $c->total_mensajes }} mensajes</div>
                    </div>
                </button>
            @empty
                <div class="p-8 text-center text-slate-400">
                    <i class="fa-solid fa-inbox text-3xl mb-2 block"></i>
                    <p class="text-sm">Sin conversaciones</p>
                </div>
            @endforelse
        </div>
    </aside>

    {{-- ╔═══ COLUMNA DERECHA: chat seleccionado ═══╗ --}}
    <main class="flex-1 flex flex-col bg-[#efeae2]"
          style="background-image: linear-gradient(rgba(239,234,226,0.95), rgba(239,234,226,0.95)),
                                     url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'40\' height=\'40\' viewBox=\'0 0 40 40\'><circle cx=\'2\' cy=\'2\' r=\'1\' fill=\'%23d4cab8\' opacity=\'0.3\'/></svg>');">

        @if($conversacionActiva)
            @php
                $iniAct = collect(explode(' ', trim($conversacionActiva->cliente?->nombre ?? 'C')))
                    ->filter()->take(2)
                    ->map(fn($p) => mb_substr($p, 0, 1))
                    ->implode('');
            @endphp

            {{-- Header del chat --}}
            <header class="bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white font-bold text-sm">
                        {{ $iniAct ?: 'C' }}
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800 truncate">{{ $conversacionActiva->cliente?->nombre ?? 'Cliente' }}</div>
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

                <div class="flex items-center gap-2">
                    @if($conversacionActiva->atendida_por_humano)
                        <button wire:click="devolverAlBot"
                                class="rounded-xl bg-emerald-500 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-600 transition">
                            <i class="fa-solid fa-robot mr-1"></i> Devolver al bot
                        </button>
                    @else
                        <button wire:click="tomarControl"
                                class="rounded-xl bg-blue-500 px-3 py-2 text-xs font-bold text-white hover:bg-blue-600 transition">
                            <i class="fa-solid fa-hand mr-1"></i> Tomar control
                        </button>
                    @endif

                    @if($conversacionActiva->cliente?->whatsappUrl())
                        <a href="{{ $conversacionActiva->cliente->whatsappUrl() }}" target="_blank"
                           class="rounded-xl bg-green-500 px-3 py-2 text-xs font-bold text-white hover:bg-green-600 transition" title="Abrir en WhatsApp">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                    @endif
                </div>
            </header>

            {{-- Área de mensajes --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2" id="chat-messages">
                @foreach($conversacionActiva->mensajes as $m)
                    @if($m->rol === 'user')
                        <div class="flex justify-start">
                            <div class="max-w-[70%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 shadow-sm">
                                <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                <p class="text-[10px] text-slate-400 mt-1 text-right">
                                    {{ $m->created_at->format('H:i') }}
                                </p>
                            </div>
                        </div>
                    @elseif($m->rol === 'assistant')
                        @php $esHumano = ($m->meta['enviado_por_humano'] ?? false); @endphp
                        <div class="flex justify-end">
                            <div class="max-w-[70%] rounded-2xl rounded-tr-sm px-3 py-2 shadow-sm
                                        {{ $esHumano ? 'bg-blue-100' : 'bg-[#dcf8c6]' }}">
                                @if($esHumano)
                                    <div class="text-[10px] uppercase font-bold text-blue-700 mb-0.5">
                                        <i class="fa-solid fa-user-tie"></i> Operador
                                    </div>
                                @endif
                                <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                <p class="text-[10px] text-slate-500 mt-1 text-right">
                                    {{ $esHumano ? '👤' : '🤖' }} {{ $m->created_at->format('H:i') }}
                                </p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Input para responder --}}
            <form wire:submit.prevent="enviar"
                  class="bg-white border-t border-slate-200 px-3 py-3 flex items-center gap-2">
                <textarea wire:model="nuevoMensaje"
                          placeholder="Escribe tu respuesta..."
                          rows="1"
                          @keydown.enter.prevent="$wire.enviar()"
                          class="flex-1 resize-none rounded-2xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-amber-100"></textarea>
                <button type="submit"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-[#d68643] text-white shadow hover:bg-[#c97a36] transition">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>

        @else
            {{-- Empty state --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center text-slate-400 max-w-md px-6">
                    <i class="fa-solid fa-comment-dots text-7xl mb-4 text-slate-300"></i>
                    <h3 class="text-2xl font-bold text-slate-700 mb-2">Selecciona una conversación</h3>
                    <p class="text-sm">Elige un chat de la izquierda para empezar a atender al cliente.
                    Los mensajes nuevos aparecerán en tiempo real 🔥</p>
                </div>
            </div>
        @endif
    </main>

    {{-- Auto-scroll al final del chat al abrir y al recibir mensaje --}}
    <script>
        (function () {
            function scrollToBottom() {
                const el = document.getElementById('chat-messages');
                if (el) el.scrollTop = el.scrollHeight;
            }

            // Al cambiar de chat
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('chat-cambiado', () => setTimeout(scrollToBottom, 100));
                Livewire.on('mensaje-enviado', () => setTimeout(scrollToBottom, 100));

                if (window.Livewire?.hook) {
                    Livewire.hook('morph.updated', () => setTimeout(scrollToBottom, 50));
                }
            });

            scrollToBottom();
        })();
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

                        // Sonido si es del cliente
                        if (e.rol === 'user') {
                            const audio = document.getElementById('new-order-sound');
                            if (audio) { audio.currentTime = 0; audio.play().catch(()=>{}); }
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
</div>
