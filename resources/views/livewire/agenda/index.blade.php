<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        @if(session('success'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
        @endif

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-lg">
                        <i class="fa-solid fa-calendar-check text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Agenda de Citas</h2>
                        <p class="text-sm text-slate-500">Programa y gestiona las citas. Sincronización con Google Calendar próximamente.</p>
                    </div>
                </div>
                <button wire:click="$toggle('mostrarConfig')" class="inline-flex items-center gap-2 rounded-2xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-5 py-3 transition">
                    <i class="fa-solid fa-gear"></i> Configuración
                </button>
            </div>
        </div>

        {{-- CONFIG --}}
        @if($mostrarConfig)
            <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6 space-y-5">
                <h3 class="text-lg font-bold text-slate-800">Configuración de la agenda</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Duración de la cita (min)</label>
                        <input type="number" wire:model="duracion_min" min="5" max="600" class="w-full rounded-xl border-slate-200 text-sm">
                        @error('duracion_min')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descanso entre citas (min)</label>
                        <input type="number" wire:model="buffer_min" min="0" max="240" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="activa" class="rounded border-slate-300 text-brand"> Agenda activa
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Días de atención</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['1'=>'Lun','2'=>'Mar','3'=>'Mié','4'=>'Jue','5'=>'Vie','6'=>'Sáb','7'=>'Dom'] as $n => $lbl)
                            <button type="button" wire:click="toggleDia({{ $n }})"
                                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border-2 {{ in_array((int)$n, $dias) ? 'border-brand bg-brand-soft text-brand-dark' : 'border-slate-200 text-slate-500 hover:bg-slate-50' }}">
                                {{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 max-w-md">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hora inicio</label>
                        <input type="time" wire:model="hora_inicio" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hora fin</label>
                        <input type="time" wire:model="hora_fin" class="w-full rounded-xl border-slate-200 text-sm">
                        @error('hora_fin')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                </div>

                {{-- Google Calendar --}}
                <div class="rounded-xl border {{ $cfg->google_conectado ? 'border-emerald-200 bg-emerald-50' : 'border-dashed border-slate-300 bg-slate-50' }} p-4 flex items-center justify-between gap-3">
                    <div class="text-sm text-slate-600">
                        <i class="fa-brands fa-google text-indigo-500"></i>
                        <strong>Google Calendar</strong> —
                        @if($cfg->google_conectado)
                            <span class="text-emerald-700 font-semibold">Conectado</span>
                            @if($cfg->google_cuenta_email)<span class="text-slate-500">({{ $cfg->google_cuenta_email }})</span>@endif
                        @else
                            <span class="text-slate-500">No conectado</span>
                        @endif
                    </div>
                    @if($cfg->google_conectado)
                        <button type="button" wire:click="desconectarGoogle" wire:confirm="¿Desconectar Google Calendar?"
                                class="text-xs px-3 py-2 rounded-lg bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 font-semibold">Desconectar</button>
                    @elseif($googleConfigurado)
                        <a href="{{ route('agenda.google.conectar') }}"
                           class="text-xs px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-semibold">
                            <i class="fa-brands fa-google"></i> Conectar Google Calendar
                        </a>
                    @else
                        <span class="text-[11px] text-amber-600 font-semibold text-right max-w-[220px]">Faltan las credenciales de Google en el servidor (Client ID/Secret).</span>
                    @endif
                </div>

                <div class="flex justify-end">
                    <button wire:click="guardarConfig" class="px-5 py-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary text-white font-bold text-sm hover:from-brand-dark hover:to-brand-dark">Guardar configuración</button>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            {{-- NUEVA CITA --}}
            <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm p-5 space-y-4 h-fit">
                <h3 class="text-lg font-bold text-slate-800"><i class="fa-solid fa-plus text-brand"></i> Nueva cita</h3>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Fecha</label>
                    <input type="date" wire:model.live="nc_fecha" min="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Horarios disponibles</label>
                    @if(empty($slots))
                        <p class="text-xs text-slate-400 italic py-2">No hay horarios libres ese día (revisa días de atención / citas existentes).</p>
                    @else
                        <div class="grid grid-cols-3 gap-2 max-h-48 overflow-y-auto">
                            @foreach($slots as $s)
                                @php $iso = $s->toIso8601String(); @endphp
                                <button type="button" wire:click="seleccionarSlot('{{ $iso }}')"
                                        class="px-2 py-1.5 rounded-lg text-sm font-semibold border-2 {{ $nc_slot === $iso ? 'border-brand bg-brand text-white' : 'border-slate-200 text-slate-700 hover:border-brand' }}">
                                    {{ $s->format('h:i a') }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @error('nc_slot')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                </div>

                @if($nc_slot)
                    <div class="space-y-3 pt-2 border-t border-slate-100">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Paciente</label>
                            <input wire:model="nc_nombre" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Nombre completo">
                            @error('nc_nombre')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Teléfono (opcional)</label>
                            <input wire:model="nc_telefono" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Motivo (opcional)</label>
                            <input wire:model="nc_motivo" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Primera consulta, seguimiento...">
                        </div>
                        <button wire:click="guardarCita" class="w-full rounded-xl bg-gradient-to-r from-brand to-brand-secondary text-white font-bold py-2.5 text-sm hover:from-brand-dark hover:to-brand-dark">
                            Agendar cita
                        </button>
                    </div>
                @endif
            </div>

            {{-- PRÓXIMAS CITAS --}}
            <div class="lg:col-span-3 rounded-2xl bg-white border border-slate-200 shadow-sm p-5">
                <h3 class="text-lg font-bold text-slate-800 mb-3"><i class="fa-solid fa-calendar-days text-indigo-500"></i> Próximas citas</h3>
                @if($proximas->isEmpty())
                    <p class="text-sm text-slate-400 italic py-4 text-center">No hay citas próximas.</p>
                @else
                    <div class="space-y-2">
                        @php $diaActual = null; @endphp
                        @foreach($proximas as $c)
                            @php $d = $c->inicio_at->translatedFormat('l d \d\e F'); @endphp
                            @if($d !== $diaActual)
                                <p class="text-[11px] font-bold uppercase text-slate-400 pt-3">{{ $d }}</p>
                                @php $diaActual = $d; @endphp
                            @endif
                            <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                                <div class="text-center shrink-0 w-16">
                                    <div class="text-sm font-extrabold text-slate-800">{{ $c->inicio_at->format('h:i') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $c->inicio_at->format('a') }}</div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-slate-800 truncate">{{ $c->paciente_nombre }}</p>
                                    <p class="text-xs text-slate-500 truncate">
                                        {{ $c->motivo ?: 'Sin motivo' }}
                                        @if($c->paciente_telefono) · {{ $c->paciente_telefono }} @endif
                                        @if($c->origen === 'whatsapp') <span class="text-emerald-600"><i class="fa-brands fa-whatsapp"></i></span> @endif
                                    </p>
                                </div>
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full shrink-0
                                    {{ $c->estado === 'confirmada' ? 'bg-emerald-100 text-emerald-700' : ($c->estado === 'pendiente' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $c->estado }}
                                </span>
                                <div class="flex items-center gap-1 shrink-0">
                                    <button wire:click="cambiarEstado({{ $c->id }}, 'completada')" title="Marcar completada" class="text-slate-300 hover:text-emerald-600 px-1"><i class="fa-solid fa-circle-check"></i></button>
                                    <button wire:click="cambiarEstado({{ $c->id }}, 'cancelada')" wire:confirm="¿Cancelar esta cita?" title="Cancelar" class="text-slate-300 hover:text-rose-600 px-1"><i class="fa-solid fa-circle-xmark"></i></button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
