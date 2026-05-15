<div class="space-y-4">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Estados de WhatsApp</h2>
            <p class="text-sm text-slate-500">Publica fotos, videos o textos como estado (Stories) en WhatsApp</p>
        </div>
        <button wire:click="abrirModal"
                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 px-4 py-2.5 text-sm font-bold text-white shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all">
            <i class="fa-solid fa-plus"></i>
            Crear Estado
        </button>
    </div>

    {{-- Contenido principal --}}
    @if($cargando)
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-12 text-center">
            <i class="fa-solid fa-spinner fa-spin text-3xl text-slate-400 mb-3"></i>
            <p class="text-sm text-slate-500">Cargando estados...</p>
        </div>
    @elseif(empty($estados))
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-4">
                <i class="fa-solid fa-circle-info text-2xl"></i>
            </div>
            <h3 class="text-base font-semibold text-slate-700">Sin estados activos</h3>
            <p class="mt-1 text-sm text-slate-500">Crea un nuevo estado para publicarlo en WhatsApp</p>
            <button wire:click="abrirModal"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-600 transition">
                <i class="fa-solid fa-plus"></i>
                Crear primer estado
            </button>
        </div>
    @else
        {{-- Cards de estados --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($estados as $estado)
                @php
                    $expirado   = isset($estado['expiresAt']) && \Carbon\Carbon::parse($estado['expiresAt'])->isPast();
                    $programado = ($estado['status'] ?? '') === 'pending';
                    $mediaUrl   = !empty($estado['mediaUrl'])
                        ? rtrim(config('services.whatsapp.api_base_url', 'https://wa-api.tecnobyteapp.com:1422'), '/') . '/public/' . $estado['mediaUrl']
                        : null;
                    $esImagen   = $mediaUrl && str_starts_with($estado['mediaType'] ?? '', 'image/');
                    $esVideo    = $mediaUrl && str_starts_with($estado['mediaType'] ?? '', 'video/');
                    $esAudio    = $mediaUrl && str_starts_with($estado['mediaType'] ?? '', 'audio/');
                @endphp

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col
                            {{ $expirado ? 'opacity-60' : '' }}">

                    {{-- Preview de media --}}
                    @if($esImagen)
                        <div class="relative h-48 bg-slate-100">
                            <img src="{{ $mediaUrl }}" alt="Estado" class="w-full h-full object-cover"
                                 onerror="this.parentElement.innerHTML='<div class=\'flex items-center justify-center h-full text-slate-400\'><i class=\'fa-solid fa-image text-3xl\'></i></div>'">
                            @if(!empty($estado['body']))
                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-3">
                                    <p class="text-white text-sm line-clamp-2">{{ $estado['body'] }}</p>
                                </div>
                            @endif
                        </div>
                    @elseif($esVideo)
                        <div class="relative h-48 bg-slate-900 flex items-center justify-center">
                            <i class="fa-solid fa-video text-4xl text-white/60"></i>
                            @if(!empty($estado['body']))
                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-3">
                                    <p class="text-white text-sm line-clamp-2">{{ $estado['body'] }}</p>
                                </div>
                            @endif
                        </div>
                    @elseif($esAudio)
                        <div class="h-32 bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center">
                            <i class="fa-solid fa-headphones text-4xl text-white/80"></i>
                        </div>
                    @elseif(!empty($estado['body']))
                        <div class="h-48 bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center p-6">
                            <p class="text-white text-center text-lg font-medium leading-relaxed line-clamp-5">{{ $estado['body'] }}</p>
                        </div>
                    @endif

                    {{-- Info --}}
                    <div class="p-4 flex-1 flex flex-col gap-3">
                        {{-- Conexion --}}
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <i class="fa-brands fa-whatsapp text-emerald-500"></i>
                            <span class="font-medium">{{ $estado['whatsapp']['name'] ?? 'Conexion' }}</span>
                            @if(!empty($estado['whatsapp']['phoneNumber']))
                                <span class="text-slate-400">{{ $estado['whatsapp']['phoneNumber'] }}</span>
                            @endif
                        </div>

                        {{-- Estado badge + fechas --}}
                        <div class="flex items-center gap-2">
                            @if($programado)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 border border-amber-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                                    <i class="fa-solid fa-clock text-[9px]"></i> Programado
                                </span>
                            @elseif($expirado)
                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 border border-rose-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-rose-700">
                                    <i class="fa-solid fa-clock-rotate-left text-[9px]"></i> Expirado
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 border border-emerald-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700">
                                    <i class="fa-solid fa-circle text-[6px]"></i> Activo
                                </span>
                            @endif
                        </div>

                        <div class="text-[11px] text-slate-400 space-y-0.5 mt-auto">
                            @if(!empty($estado['createdAt']))
                                <div><i class="fa-regular fa-calendar text-[9px] mr-1"></i> Creado: {{ \Carbon\Carbon::parse($estado['createdAt'])->format('d/m/Y h:i a') }}</div>
                            @endif
                            @if($programado && !empty($estado['scheduledFor']))
                                <div><i class="fa-solid fa-clock text-[9px] mr-1"></i> Programado: {{ \Carbon\Carbon::parse($estado['scheduledFor'])->format('d/m/Y h:i a') }}</div>
                            @elseif(!empty($estado['expiresAt']))
                                <div><i class="fa-solid fa-hourglass-end text-[9px] mr-1"></i> Expira: {{ \Carbon\Carbon::parse($estado['expiresAt'])->format('d/m/Y h:i a') }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="border-t border-slate-100 px-4 py-3">
                        <button wire:click="confirmarEliminar({{ $estado['id'] }})"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition">
                            <i class="fa-solid fa-trash-can text-[10px]"></i>
                            Eliminar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Boton refrescar --}}
        <div class="text-center">
            <button wire:click="cargarDatos" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                <i class="fa-solid fa-arrows-rotate" wire:loading.class="fa-spin" wire:target="cargarDatos"></i>
                Actualizar
            </button>
        </div>
    @endif

    {{-- ═══ MODAL CREAR ESTADO ═══ --}}
    @if($modal)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             @click="$wire.cerrarModal()">

            <div class="w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white shadow-2xl max-h-[90vh] flex flex-col" @click.stop>
                {{-- Header --}}
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 shrink-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-slate-800">Crear nuevo estado</h3>
                        <p class="text-xs text-slate-500">Se publicara como Story en WhatsApp</p>
                    </div>
                    <button wire:click="cerrarModal"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                    {{-- Conexion --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            <i class="fa-brands fa-whatsapp text-emerald-500 mr-1"></i>
                            Conexion de WhatsApp
                        </label>
                        @if(empty($conexiones))
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                No hay conexiones de WhatsApp activas. Verifica que al menos una este conectada.
                            </div>
                        @else
                            <select wire:model="whatsappId"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-emerald-400 focus:bg-white focus:ring-2 focus:ring-emerald-100">
                                <option value="">-- Seleccionar conexion --</option>
                                @foreach($conexiones as $conn)
                                    <option value="{{ $conn['id'] }}">
                                        {{ $conn['name'] }}
                                        @if(!empty($conn['phoneNumber']))
                                            ({{ $conn['phoneNumber'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Mensaje --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            <i class="fa-solid fa-font mr-1 text-slate-400"></i>
                            Texto del estado
                        </label>
                        <textarea wire:model="body" rows="4"
                                  placeholder="Escribe el texto de tu estado..."
                                  class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-emerald-400 focus:bg-white focus:ring-2 focus:ring-emerald-100 resize-none"></textarea>
                        <p class="text-[10px] text-slate-400 mt-1">Opcional si adjuntas una imagen o video</p>
                    </div>

                    {{-- Archivo --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            <i class="fa-solid fa-paperclip mr-1 text-slate-400"></i>
                            Imagen, video o audio
                        </label>
                        <div x-data="{ fileName: null, preview: null }" class="space-y-2">
                            <label class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/30 transition">
                                <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                                <span x-show="!fileName">Haz clic para seleccionar archivo</span>
                                <span x-show="fileName" x-text="fileName" class="font-medium text-slate-700 truncate"></span>
                                <input type="file" wire:model="media" accept="image/*,video/*,audio/*" class="hidden"
                                       @change="fileName = $event.target.files[0]?.name;
                                                if ($event.target.files[0]?.type.startsWith('image/')) {
                                                    const reader = new FileReader();
                                                    reader.onload = (e) => preview = e.target.result;
                                                    reader.readAsDataURL($event.target.files[0]);
                                                } else { preview = null; }">
                            </label>

                            {{-- Preview --}}
                            <template x-if="preview">
                                <div class="relative rounded-xl overflow-hidden border border-slate-200">
                                    <img :src="preview" class="w-full max-h-48 object-cover">
                                    <button type="button" @click="fileName = null; preview = null; $wire.set('media', null)"
                                            class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70 transition">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                </div>
                            </template>
                        </div>

                        {{-- Livewire upload progress --}}
                        <div wire:loading wire:target="media" class="mt-2">
                            <div class="flex items-center gap-2 text-xs text-emerald-600">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                Subiendo archivo...
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Max 16MB. Formatos: JPG, PNG, MP4, MP3, OGG</p>
                    </div>

                    {{-- Programar --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            <i class="fa-solid fa-clock mr-1 text-slate-400"></i>
                            Programar para (opcional)
                        </label>
                        <input type="datetime-local" wire:model="scheduledFor"
                               min="{{ now()->format('Y-m-d\TH:i') }}"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:border-emerald-400 focus:bg-white focus:ring-2 focus:ring-emerald-100">
                        <p class="text-[10px] text-slate-400 mt-1">Dejalo vacio para publicar inmediatamente</p>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex flex-col-reverse sm:flex-row gap-2 border-t border-slate-100 px-5 py-4 shrink-0">
                    <button wire:click="cerrarModal"
                            class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button wire:click="crearEstado"
                            wire:loading.attr="disabled" wire:target="crearEstado"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 px-4 py-2.5 text-sm font-bold text-white hover:from-emerald-600 hover:to-teal-700 disabled:opacity-60 transition shadow-md">
                        <i class="fa-solid fa-paper-plane" wire:loading.class="hidden" wire:target="crearEstado"></i>
                        <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="crearEstado"></i>
                        {{ $scheduledFor ? 'Programar' : 'Publicar ahora' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ MODAL CONFIRMAR ELIMINAR ═══ --}}
    @if($modalEliminar)
        <div class="fixed inset-0 z-50 flex items-center justify-center sm:p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">
            <div class="w-full sm:max-w-sm rounded-2xl border border-slate-200 bg-white shadow-2xl" @click.stop>
                <div class="p-6 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-500 mb-4">
                        <i class="fa-solid fa-trash-can text-xl"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 mb-1">Eliminar estado</h3>
                    <p class="text-sm text-slate-500">Este estado se eliminara de WhatsApp. Esta accion no se puede deshacer.</p>
                </div>
                <div class="flex gap-2 border-t border-slate-100 px-5 py-4">
                    <button wire:click="cancelarEliminar"
                            class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button wire:click="eliminarEstado"
                            wire:loading.attr="disabled" wire:target="eliminarEstado"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-rose-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-600 disabled:opacity-60 transition">
                        <i class="fa-solid fa-trash-can" wire:loading.class="hidden" wire:target="eliminarEstado"></i>
                        <i class="fa-solid fa-spinner fa-spin hidden" wire:loading.class.remove="hidden" wire:target="eliminarEstado"></i>
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
