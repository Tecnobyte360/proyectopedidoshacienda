<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white shadow-lg">
                        <i class="fa-solid fa-comments text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Widgets de Chat Web</h2>
                        <p class="text-sm text-slate-500">Conecta tu página web con la IA. Copia el script y pégalo en tu sitio.</p>
                    </div>
                </div>
                <button wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nuevo widget
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($widgets as $w)
                <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm {{ !$w->activo ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl text-white text-xl"
                                 style="background: linear-gradient(135deg, {{ $w->color_primario }}, {{ $w->color_secundario }});">
                                💬
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800">{{ $w->nombre }}</h3>
                                <p class="text-xs text-slate-500">{{ $w->total_conversaciones }} conversaciones · {{ $w->total_mensajes }} mensajes</p>
                            </div>
                        </div>
                        @if($w->activo)
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Activo</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Inactivo</span>
                        @endif
                    </div>

                    <div class="bg-slate-900 rounded-lg p-3 mb-3 relative" x-data="{ copied: false }">
                        <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">Script para pegar en tu web</p>
                        <code class="text-[10px] text-emerald-300 font-mono break-all block"
                              x-ref="snippet">&lt;script src="{{ url('/widget.js?token=' . $w->token) }}" async&gt;&lt;/script&gt;</code>
                        <button @click="navigator.clipboard.writeText($refs.snippet.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                                class="absolute top-2 right-2 text-xs px-2 py-1 rounded bg-slate-700 hover:bg-slate-600 text-white">
                            <span x-show="!copied"><i class="fa-solid fa-copy"></i> Copiar</span>
                            <span x-show="copied" x-cloak class="text-emerald-300"><i class="fa-solid fa-check"></i> Copiado</span>
                        </button>
                    </div>

                    <div class="flex items-center justify-between gap-2 text-xs text-slate-500 mb-3">
                        <span><i class="fa-solid fa-globe"></i> {{ $w->dominios_permitidos ?: 'Cualquier dominio' }}</span>
                        <span><i class="fa-solid fa-{{ $w->posicion === 'bottom-left' ? 'left' : 'right' }}-long"></i> {{ $w->posicion }}</span>
                    </div>

                    <div class="flex items-center justify-end gap-1 pt-2 border-t border-slate-100">
                        <a href="{{ url('/widget-preview?token=' . $w->token) }}" target="_blank" title="Preview"
                           class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-violet-100 hover:bg-violet-200 text-violet-700 transition">
                            <i class="fa-solid fa-eye text-xs"></i>
                        </a>
                        <button wire:click="abrirEditar({{ $w->id }})" class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>
                        <button wire:click="toggleActivo({{ $w->id }})" class="h-8 w-8 rounded-lg {{ $w->activo ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} transition">
                            <i class="fa-solid {{ $w->activo ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                        </button>
                        <button wire:click="eliminar({{ $w->id }})" wire:confirm="¿Eliminar este widget?"
                                class="h-8 w-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl bg-white border border-slate-200 p-12 text-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                        <i class="fa-solid fa-comments text-2xl"></i>
                    </div>
                    <p class="text-base font-semibold text-slate-700">Sin widgets</p>
                    <p class="text-sm text-slate-500">Crea uno para embeber el chat en tu sitio web.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white">
                    <h3 class="font-bold text-slate-800">{{ $editandoId ? 'Editar' : 'Nuevo' }} widget</h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[75vh] overflow-y-auto">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre del widget *</label>
                        <input type="text" wire:model="nombre" placeholder="Widget tienda online"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        @error('nombre') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Título visible *</label>
                        <input type="text" wire:model="titulo"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Subtítulo</label>
                        <input type="text" wire:model="subtitulo" placeholder="Respuesta inmediata"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Saludo inicial (se muestra al abrir)</label>
                        <textarea wire:model="saludoInicial" rows="2"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Placeholder del input</label>
                        <input type="text" wire:model="placeholder"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Posición</label>
                        <select wire:model="posicion" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="bottom-right">Abajo derecha</option>
                            <option value="bottom-left">Abajo izquierda</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Color primario</label>
                        <input type="color" wire:model="colorPrimario"
                               class="w-full h-10 rounded-xl border border-slate-200 cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Color secundario</label>
                        <input type="color" wire:model="colorSecundario"
                               class="w-full h-10 rounded-xl border border-slate-200 cursor-pointer">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">URL del avatar (opcional)</label>
                        <input type="text" wire:model="avatarUrl" placeholder="https://tuempresa.com/logo.png"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Dominios permitidos <span class="text-slate-400">(separados por coma — vacío = cualquiera)</span>
                        </label>
                        <input type="text" wire:model="dominiosPermitidos"
                               placeholder="tiendaonline.com, landing.miempresa.co"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono">
                    </div>

                    <div class="md:col-span-2 grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="activo" class="rounded text-[#d68643]">
                            <span class="font-semibold text-slate-700">Activo</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="sonidoNotificacion" class="rounded text-[#d68643]">
                            <span class="font-semibold text-slate-700">Sonido al recibir</span>
                        </label>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                    <button wire:click="guardar" class="rounded-xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] px-5 py-2 text-sm font-bold text-white shadow-lg">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
