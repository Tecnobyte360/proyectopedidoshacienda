<div class="p-4 md:p-6 w-full max-w-[1100px] mx-auto space-y-6">

    {{-- Header --}}
    <header class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-[26px] leading-tight font-semibold text-slate-900 tracking-tight">
                Informes automáticos del negocio
            </h1>
            <p class="text-[13px] text-slate-500 mt-1">
                Envío programado de métricas clave al administrador del tenant: horas pico, tiempo de respuesta,
                clientes activos y alertas. Configurás por tenant.
            </p>
        </div>
        <x-tenant-view-selector />
    </header>

    @if(!$tenantActual)
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
            Elegí un tenant arriba para configurar sus informes.
        </div>
    @else

    {{-- Tarjeta activo --}}
    <div class="rounded-xl bg-white ring-1 ring-slate-200 p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-slate-100 text-slate-600 inline-flex items-center justify-center">
                    <i class="fa-solid fa-envelope-circle-check"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">{{ $tenantActual->nombre }}</h3>
                    <p class="text-[12px] text-slate-500">Estado del envío automático para este tenant</p>
                </div>
            </div>
            <label class="inline-flex items-center cursor-pointer gap-3">
                <span class="text-[13px] font-medium text-slate-700">{{ $activo ? 'Activo' : 'Inactivo' }}</span>
                <input type="checkbox" wire:model.live="activo" class="sr-only peer">
                <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer
                            peer-checked:after:translate-x-full peer-checked:after:border-white
                            after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white
                            after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5
                            after:transition-all peer-checked:bg-emerald-500"></div>
            </label>
        </div>
    </div>

    {{-- Frecuencia & horario --}}
    <div class="rounded-xl bg-white ring-1 ring-slate-200 p-5 space-y-4">
        <h3 class="text-sm font-semibold text-slate-900">📅 Cuándo enviarlo</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Frecuencia</label>
                <select wire:model.live="frecuencia" class="w-full rounded-md border-slate-200 text-sm focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                    <option value="diario">Diario</option>
                    <option value="semanal">Semanal</option>
                    <option value="mensual">Mensual</option>
                </select>
            </div>

            @if($frecuencia === 'semanal')
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Día de la semana</label>
                    <select wire:model.live="diaSemana" class="w-full rounded-md border-slate-200 text-sm">
                        <option value="1">Lunes</option>
                        <option value="2">Martes</option>
                        <option value="3">Miércoles</option>
                        <option value="4">Jueves</option>
                        <option value="5">Viernes</option>
                        <option value="6">Sábado</option>
                        <option value="7">Domingo</option>
                    </select>
                </div>
            @elseif($frecuencia === 'mensual')
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Día del mes</label>
                    <input type="number" min="1" max="28" wire:model.live="diaMes"
                           class="w-full rounded-md border-slate-200 text-sm">
                </div>
            @else
                <div></div>
            @endif

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Hora de envío</label>
                <input type="time" wire:model.live="horaEnvio"
                       class="w-full rounded-md border-slate-200 text-sm">
            </div>
        </div>
    </div>

    {{-- Destinatarios --}}
    <div class="rounded-xl bg-white ring-1 ring-slate-200 p-5 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">📧 A quién enviar</h3>
                <p class="text-[12px] text-slate-500 mt-0.5">El informe llega como email a estos destinatarios.</p>
            </div>
        </div>

        <div>
            <div class="flex gap-2">
                <input type="email" wire:model="nuevoEmail"
                       wire:keydown.enter.prevent="agregarEmail"
                       placeholder="correo@empresa.com"
                       class="flex-1 rounded-md border-slate-200 text-sm focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                <button type="button" wire:click="agregarEmail"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                    <i class="fa-solid fa-plus text-[11px]"></i> Agregar
                </button>
            </div>

            @if(empty($emails))
                <div class="mt-3 text-center py-6 border border-dashed border-slate-200 rounded-lg">
                    <i class="fa-regular fa-envelope text-slate-300 text-xl"></i>
                    <p class="text-[13px] text-slate-400 mt-2">Sin destinatarios todavía</p>
                </div>
            @else
                <ul class="mt-3 space-y-1.5">
                    @foreach($emails as $i => $email)
                        <li class="flex items-center justify-between bg-slate-50 ring-1 ring-slate-200 rounded-md px-3 py-2">
                            <span class="text-[13px] text-slate-700 font-mono">{{ $email }}</span>
                            <button type="button" wire:click="quitarEmail({{ $i }})"
                                    class="text-rose-500 hover:text-rose-700">
                                <i class="fa-solid fa-trash text-[11px]"></i>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Métricas a incluir --}}
    <div class="rounded-xl bg-white ring-1 ring-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">📊 Qué métricas incluir</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            @php
                $items = [
                    ['incVolumen',         'Volumen', 'Conversaciones nuevas, mensajes totales'],
                    ['incHorasPico',       'Horas pico', 'Cuándo escriben más los clientes'],
                    ['incTiempoRespuesta', 'Tiempo de respuesta', 'Promedio y peor caso del operador'],
                    ['incTopClientes',     'Top clientes', 'Los 5 más activos del período'],
                    ['incSinResponder',    'Sin responder', 'Alertas de conversaciones abandonadas >2h'],
                    ['incReacciones',      'Reacciones', 'Emojis con los que respondieron los clientes'],
                    ['incClientesMolestos','😠 Clientes molestos (IA)', 'La IA detecta quejas, enojo e insatisfacción y recomienda cómo recuperarlos'],
                    ['incPalabrasTop',     'Palabras top', 'Lo más mencionado por los clientes'],
                ];
            @endphp
            @foreach($items as [$key, $label, $hint])
                <label class="flex items-start gap-3 px-3 py-2.5 rounded-md hover:bg-slate-50 cursor-pointer">
                    <input type="checkbox" wire:model.live="{{ $key }}"
                           class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                    <span>
                        <span class="block text-[13px] font-medium text-slate-800">{{ $label }}</span>
                        <span class="block text-[11px] text-slate-500">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Acciones --}}
    <div class="flex items-center justify-end gap-2 flex-wrap">
        <button wire:click="enviarPrueba"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white ring-1 ring-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            <i class="fa-solid fa-paper-plane"></i> Enviar prueba ahora
        </button>
        <button wire:click="guardar"
                class="inline-flex items-center gap-2 px-5 py-2 rounded-md bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
            <i class="fa-solid fa-floppy-disk"></i> Guardar configuración
        </button>
    </div>

    @endif
</div>
