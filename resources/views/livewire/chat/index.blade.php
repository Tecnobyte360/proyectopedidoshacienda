<div class="h-[calc(100vh-5rem)] flex flex-col lg:flex-row bg-slate-100"
     wire:poll.2s="refrescar">

    @php $cfgBot = \App\Models\ConfiguracionBot::actual(); @endphp

    {{-- Banner de advertencia si el bot está apagado globalmente --}}
    @if(!$cfgBot->activo)
        <div class="absolute top-20 left-0 right-0 z-20 bg-rose-500 text-white px-4 py-2 text-center text-xs font-bold shadow lg:left-64">
            ⚠️ El bot está APAGADO en {{ \Illuminate\Support\Facades\Route::has('configuracion.bot') ? '' : '' }}
            <a href="{{ route('configuracion.bot') }}" class="underline">Configuración del bot</a>
            — los clientes escriben pero la IA no responde.
        </div>
    @endif

    {{-- ╔═══ COLUMNA IZQUIERDA: lista de conversaciones ═══╗ --}}
    <aside class="w-full lg:w-96 flex-shrink-0 bg-white border-r border-slate-200 flex flex-col">

        {{-- Header --}}
        <div class="p-4 border-b border-slate-200 bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <i class="fa-solid fa-comments"></i> Chat en vivo
                    </h2>
                    <p class="text-xs text-white/80">Atiende clientes en tiempo real</p>
                </div>
                <button wire:click="abrirNuevoChat"
                        title="Iniciar chat con un número nuevo"
                        class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-white/20 hover:bg-white/30 backdrop-blur px-3 py-2 text-xs font-semibold transition">
                    <i class="fa-solid fa-pen-to-square"></i> Nuevo
                </button>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="p-3 border-b border-slate-200 space-y-2">
            <input type="text" wire:model.live.debounce.400ms="busqueda"
                   placeholder="Buscar cliente o teléfono..."
                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#d68643] focus:ring-[#d68643]">

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

                @php $tieneNoLeidos = (int) ($c->no_leidos ?? 0) > 0; @endphp
                <button wire:click="seleccionar({{ $c->id }})"
                        class="w-full text-left flex items-center gap-3 px-4 py-3 border-b border-slate-100 hover:bg-amber-50/40 transition
                              {{ $isActiva ? 'bg-amber-50' : ($tieneNoLeidos ? 'bg-emerald-50/40' : '') }}">

                    <div class="relative flex-shrink-0">
                        @if($c->cliente?->profile_pic_url)
                            <img src="{{ $c->cliente->profile_pic_url }}"
                                 class="h-12 w-12 rounded-full object-cover bg-slate-100"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                 alt="avatar">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white font-bold items-center justify-center" style="display:none;">
                                {{ $iniciales ?: 'C' }}
                            </div>
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white font-bold">
                                {{ $iniciales ?: 'C' }}
                            </div>
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
                            <span class="truncate {{ $tieneNoLeidos ? 'font-extrabold text-slate-900' : 'font-semibold text-slate-800' }}">
                                {{ $c->cliente?->nombre ?? 'Cliente' }}
                            </span>
                            <span class="text-[10px] flex-shrink-0 {{ $tieneNoLeidos ? 'text-emerald-600 font-bold' : 'text-slate-400' }}">
                                {{ $c->ultimo_mensaje_at?->diffForHumans(null, true) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs truncate font-mono {{ $tieneNoLeidos ? 'text-slate-700' : 'text-slate-500' }}">{{ $c->telefono_normalizado }}</div>
                            @if($tieneNoLeidos)
                                <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-emerald-500 text-white text-[11px] font-extrabold flex-shrink-0 shadow-sm">
                                    {{ $c->no_leidos > 99 ? '99+' : $c->no_leidos }}
                                </span>
                            @endif
                        </div>
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
    <section class="flex-1 flex flex-col min-w-0 bg-[#efeae2]">

        @if($conversacionActiva)
            @php
                $iniAct = collect(explode(' ', trim($conversacionActiva->cliente?->nombre ?? 'C')))
                    ->filter()->take(2)
                    ->map(fn($p) => mb_substr($p, 0, 1))
                    ->implode('');
            @endphp

            {{-- Header del chat --}}
            <div class="relative z-10 bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3 min-w-0">
                    @if($conversacionActiva->cliente?->profile_pic_url)
                        <img src="{{ $conversacionActiva->cliente->profile_pic_url }}"
                             class="h-10 w-10 rounded-full object-cover bg-slate-100 flex-shrink-0"
                             alt="avatar">
                    @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white font-bold text-sm flex-shrink-0">
                            {{ $iniAct ?: 'C' }}
                        </div>
                    @endif
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
                                title="El bot retomará automáticamente la conversación"
                                class="rounded-xl bg-emerald-500 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-600 transition">
                            <i class="fa-solid fa-robot mr-1"></i> Devolver al bot
                        </button>
                    @else
                        <button wire:click="tomarControl"
                                title="Silencia al bot — solo TÚ respondes a este cliente"
                                class="rounded-xl bg-blue-500 px-3 py-2 text-xs font-bold text-white hover:bg-blue-600 transition">
                            <i class="fa-solid fa-hand mr-1"></i> Silenciar bot
                        </button>
                    @endif

                    @if($conversacionActiva->cliente?->whatsappUrl())
                        <a href="{{ $conversacionActiva->cliente->whatsappUrl() }}" target="_blank"
                           class="rounded-xl bg-green-500 px-3 py-2 text-xs font-bold text-white hover:bg-green-600 transition" title="Abrir en WhatsApp">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Área de mensajes --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2" id="chat-messages">
                @foreach($conversacionActiva->mensajes as $m)
                    @php
                        $mediaUrl = $m->meta['media_url'] ?? null;
                        $esAudio  = ($m->tipo ?? null) === 'audio' && !empty($mediaUrl);
                        $esImagen = ($m->tipo ?? null) === 'image' && !empty($mediaUrl);
                        $caption  = $m->meta['caption'] ?? null;
                    @endphp
                    @if($m->rol === 'user')
                        <div class="flex justify-start">
                            <div class="max-w-[70%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 shadow-sm">
                                @if($esAudio)
                                    <audio src="{{ $mediaUrl }}" controls class="w-64 max-w-full"></audio>
                                @elseif($esImagen)
                                    <a href="{{ $mediaUrl }}" target="_blank">
                                        <img src="{{ $mediaUrl }}" class="rounded-lg max-w-full max-h-64 object-contain" alt="imagen">
                                    </a>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @else
                                    <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                @endif
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
                                @if($esAudio)
                                    <audio src="{{ $mediaUrl }}" controls class="w-64 max-w-full"></audio>
                                @elseif($esImagen)
                                    <a href="{{ $mediaUrl }}" target="_blank">
                                        <img src="{{ $mediaUrl }}" class="rounded-lg max-w-full max-h-64 object-contain" alt="imagen">
                                    </a>
                                    @if($caption)
                                        <p class="text-sm text-slate-800 mt-1 whitespace-pre-wrap">{{ $caption }}</p>
                                    @endif
                                @else
                                    <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ $m->contenido }}</p>
                                @endif
                                <p class="text-[10px] text-slate-500 mt-1 text-right flex items-center justify-end gap-1">
                                    <span>{{ $esHumano ? '👤' : '🤖' }} {{ $m->created_at->format('H:i') }}</span>
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

            {{-- Imagen + Input (todo en un solo x-data para compartir estado) --}}
            <div x-data="chatComposer()"
                 x-init="init()">

                {{-- Preview de imagen seleccionada --}}
                <div x-show="imgDataUrl" x-cloak class="bg-slate-50 border-t border-slate-200 px-4 py-3">
                    <div class="flex items-start gap-3">
                        <img :src="imgDataUrl" class="h-20 w-20 rounded-lg object-cover border border-slate-200">
                        <div class="flex-1">
                            <input type="text" x-model="imgCaption" placeholder="Caption opcional..."
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-amber-100">
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
                <form wire:submit.prevent="enviar"
                      class="px-3 py-3 flex items-center gap-2">
                    <textarea wire:model="nuevoMensaje"
                              placeholder="Escribe tu respuesta..."
                              rows="1"
                              x-show="!recording && !preview"
                              @keydown.enter.prevent="$wire.enviar()"
                              class="flex-1 resize-none rounded-2xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-amber-100"></textarea>

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

                    {{-- Botón adjuntar imagen --}}
                    <label x-show="!recording && !preview"
                           title="Adjuntar imagen"
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
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-[#d68643] text-white shadow hover:bg-[#c97a36] transition">
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
                    Los mensajes nuevos aparecerán en tiempo real 🔥</p>
                </div>
            </div>
        @endif
    </section>

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

    {{-- Composer (imagen) — selecciona archivo, lo muestra en preview, envía base64 al Livewire --}}
    <script>
        function chatComposer() {
            return {
                imgDataUrl: null,
                imgCaption: '',
                sendingImg: false,
                imgError: '',
                init() {},
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
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white">
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
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-mono focus:border-[#d68643] focus:ring-2 focus:ring-amber-100">
                        <p class="text-[10px] text-slate-400 mt-1">Solo dígitos. Ej: 573001234567</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre (opcional)</label>
                        <input type="text" wire:model="nuevoChatNombre" placeholder="Nombre del cliente"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-amber-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Primer mensaje *</label>
                        <textarea wire:model="nuevoChatMensaje" rows="3" placeholder="Hola, ¿cómo estás?"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-amber-100"></textarea>
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
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] px-4 py-2 text-sm font-bold text-white shadow-lg disabled:opacity-50">
                        <span wire:loading.remove wire:target="crearNuevoChat"><i class="fa-solid fa-paper-plane"></i> Enviar</span>
                        <span wire:loading wire:target="crearNuevoChat"><i class="fa-solid fa-circle-notch fa-spin"></i> Enviando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
