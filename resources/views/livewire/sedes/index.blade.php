<div class="p-4 lg:p-8 space-y-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-shop text-brand"></i> Sedes
            </h2>
            <p class="text-sm text-slate-500">Configura tus puntos de atención: dirección, coordenadas y horarios.</p>
        </div>
        <button wire:click="abrirModalCrear"
                class="rounded-2xl bg-brand hover:bg-brand-dark text-white font-semibold px-5 py-2.5 transition shadow">
            <i class="fa-solid fa-plus mr-2"></i> Nueva sede
        </button>
    </div>

    {{-- Lista de sedes --}}
    @if($sedes->isEmpty())
        <div class="rounded-2xl bg-white border border-slate-200 p-12 text-center text-slate-400">
            <i class="fa-solid fa-shop text-5xl text-slate-300 mb-3"></i>
            <p class="text-lg font-semibold text-slate-600">Sin sedes configuradas</p>
            <p class="text-sm">Crea tu primera sede con el botón de arriba.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($sedes as $sede)
                @php
                    $abierta = $sede->estaAbierta();
                    $hoyTxt  = $sede->horarioHoyTexto();
                @endphp
                <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-slate-800 truncate">{{ $sede->nombre }}</h3>
                                @if(!$sede->activa)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">INACTIVA</span>
                                @endif
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $abierta ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                    <i class="fa-solid fa-circle text-[8px]"></i>
                                    {{ $abierta ? 'ABIERTA AHORA' : 'CERRADA AHORA' }}
                                </span>
                            </div>
                            @if($sede->direccion)
                                <p class="text-xs text-slate-500 mt-1"><i class="fa-solid fa-location-dot mr-1"></i> {{ $sede->direccion }}</p>
                            @endif
                            @if($sede->latitud && $sede->longitud)
                                <p class="text-[10px] text-slate-400 mt-0.5 font-mono">{{ number_format($sede->latitud, 5) }}, {{ number_format($sede->longitud, 5) }}</p>
                            @else
                                <p class="text-[10px] text-amber-600 mt-0.5"><i class="fa-solid fa-triangle-exclamation"></i> Sin coordenadas</p>
                            @endif
                        </div>
                    </div>

                    @if($sede->whatsapp_connection_id || $sede->whatsapp_telefono)
                        <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-2 text-xs">
                            <div class="flex items-center gap-2 text-emerald-700 font-semibold">
                                <i class="fa-brands fa-whatsapp"></i>
                                {{ $sede->whatsapp_telefono ?: 'Conexión #' . $sede->whatsapp_connection_id }}
                            </div>
                            @if($sede->whatsapp_connection_id)
                                <div class="text-[10px] text-emerald-600 mt-0.5">Conexión asignada: #{{ $sede->whatsapp_connection_id }}</div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-xl bg-slate-50 border border-dashed border-slate-200 px-3 py-2 text-xs text-slate-500">
                            <i class="fa-brands fa-whatsapp opacity-50 mr-1"></i>
                            Sin WhatsApp asignado <span class="text-slate-400">(usa el del tenant por defecto)</span>
                        </div>
                    @endif

                    <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        <div class="font-semibold text-slate-800 mb-1">{{ $hoyTxt }}</div>
                        <details class="text-[11px]">
                            <summary class="cursor-pointer text-brand-secondary hover:underline">Ver horarios completos</summary>
                            <div class="mt-2 space-y-0.5">
                                @foreach($sede->horariosCompletos() as $key => $h)
                                    <div class="flex justify-between">
                                        <span>{{ $h['label'] }}</span>
                                        <span class="font-mono {{ $h['abierto'] ? 'text-slate-700' : 'text-rose-500' }}">
                                            {{ $h['abierto'] ? $h['abre'] . ' - ' . $h['cierra'] : 'Cerrado' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button wire:click="abrirModalEditar({{ $sede->id }})"
                                class="flex-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 transition">
                            <i class="fa-solid fa-pen-to-square"></i> Editar
                        </button>
                        <button wire:click="toggleActiva({{ $sede->id }})"
                                class="text-xs font-semibold rounded-lg {{ $sede->activa ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} px-3 py-2 transition">
                            <i class="fa-solid {{ $sede->activa ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                        </button>
                        <button wire:click="eliminar({{ $sede->id }})"
                                wire:confirm="¿Eliminar esta sede definitivamente?"
                                class="text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-2 transition">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal CRUD --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 overflow-y-auto"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8" @click.stop>
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">
                            <i class="fa-solid {{ $editandoId ? 'fa-pen-to-square' : 'fa-plus' }}"></i>
                            {{ $editandoId ? 'Editar sede' : 'Nueva sede' }}
                        </h3>
                        <p class="text-xs text-slate-500">Datos de la sede + horarios de atención</p>
                    </div>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">

                    {{-- Datos básicos --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre *</label>
                            <input type="text" wire:model="nombre" placeholder="Sede Bello Centro"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            @error('nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center gap-2 mt-7">
                            <input type="checkbox" wire:model="activa" id="activa"
                                   class="rounded border-slate-300 text-brand">
                            <label for="activa" class="text-sm text-slate-700">Sede activa</label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Dirección</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="direccion" placeholder="Calle 50 #55-20, Bello"
                                   class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                            <button type="button" wire:click="geocodificarDireccion"
                                    wire:loading.attr="disabled"
                                    wire:target="geocodificarDireccion"
                                    class="rounded-xl bg-emerald-500 hover:bg-emerald-600 disabled:opacity-60 text-white text-xs font-bold px-4">
                                <span wire:loading.remove wire:target="geocodificarDireccion">
                                    <i class="fa-solid fa-map-location-dot"></i> Obtener coords
                                </span>
                                <span wire:loading wire:target="geocodificarDireccion">
                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Latitud</label>
                            <input type="number" step="any" wire:model="latitud"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Longitud</label>
                            <input type="number" step="any" wire:model="longitud"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono focus:border-brand focus:ring-brand">
                        </div>
                    </div>

                    {{-- HORARIOS --}}
                    <div class="rounded-xl border-2 border-slate-200 overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                            <h4 class="font-bold text-slate-800 text-sm">
                                <i class="fa-solid fa-clock text-brand"></i> Horarios de atención
                            </h4>
                            <p class="text-xs text-slate-500">Configura el horario de cada día. Lo verá el bot al responder al cliente.</p>
                        </div>

                        <div class="divide-y divide-slate-100">
                            @foreach(\App\Models\Sede::DIAS_SEMANA as $diaKey => $diaLabel)
                                <div class="px-4 py-3 grid grid-cols-12 gap-3 items-center">
                                    <div class="col-span-3">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" wire:model.live="horarios.{{ $diaKey }}.abierto"
                                                   class="rounded border-slate-300 text-emerald-500 focus:ring-emerald-400">
                                            <span class="text-sm font-semibold text-slate-700">{{ $diaLabel }}</span>
                                        </label>
                                    </div>

                                    @if(!empty($horarios[$diaKey]['abierto']))
                                        <div class="col-span-4">
                                            <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-0.5">Abre</label>
                                            <input type="time" wire:model="horarios.{{ $diaKey }}.abre"
                                                   class="w-full rounded-lg border-slate-200 text-sm">
                                        </div>
                                        <div class="col-span-4">
                                            <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-0.5">Cierra</label>
                                            <input type="time" wire:model="horarios.{{ $diaKey }}.cierra"
                                                   class="w-full rounded-lg border-slate-200 text-sm">
                                        </div>
                                        <div class="col-span-1 text-right">
                                            <span class="text-emerald-600">✓</span>
                                        </div>
                                    @else
                                        <div class="col-span-9 text-sm text-rose-500 font-semibold">
                                            <i class="fa-solid fa-circle text-[8px]"></i> Cerrado este día
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Mensaje cuando esté cerrado <span class="text-xs text-slate-400 font-normal">(opcional)</span>
                        </label>
                        <textarea wire:model="mensaje_cerrado" rows="2"
                                  placeholder="Ej: Estamos cerrados ahora. Te atendemos mañana desde las 8am 🙌"
                                  class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand"></textarea>
                    </div>

                    {{-- ═════════ WhatsApp de la sede ═════════ --}}
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="fa-brands fa-whatsapp text-emerald-600"></i>
                            <span class="text-sm font-semibold text-slate-800">WhatsApp de esta sede</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">
                            Selecciona qué número de WhatsApp atiende los pedidos de esta sede.
                            Los mensajes que entren a ese número se asignarán automáticamente aquí,
                            y las notificaciones (preparación, en camino, encuesta) se enviarán desde él.
                        </p>

                        @if(empty($conexiones))
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                No hay conexiones de WhatsApp disponibles. Configúralas primero en el módulo
                                de Configuración de WhatsApp del tenant.
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Conexión WhatsApp</label>
                                    <select wire:model="whatsapp_connection_id"
                                            wire:change="$set('whatsapp_id', $event.target.value)"
                                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand bg-white">
                                        <option value="">— Sin asignar —</option>
                                        @foreach($conexiones as $c)
                                            <option value="{{ $c['id'] }}">
                                                #{{ $c['id'] }} · {{ $c['name'] }}
                                                @if($c['number']) ({{ $c['number'] }}) @endif
                                                · {{ $c['status'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">
                                        Teléfono visible <span class="text-slate-400 font-normal">(opcional)</span>
                                    </label>
                                    <input type="text" wire:model="whatsapp_telefono"
                                           placeholder="+57 305 399 9848"
                                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-brand">
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal"
                                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-brand hover:bg-brand-dark px-6 py-2.5 text-sm font-bold text-white shadow">
                            <i class="fa-solid fa-floppy-disk mr-1"></i>
                            {{ $editandoId ? 'Actualizar' : 'Crear sede' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
