<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                        <i class="fa-solid fa-bullhorn text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Campañas WhatsApp</h2>
                        <p class="text-sm text-slate-500">Envío masivo pausado para evitar baneos. Configura intervalos, lotes y ventana horaria.</p>
                    </div>
                </div>
                <button wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
                    <i class="fa-solid fa-plus"></i> Nueva campaña
                </button>
            </div>
        </div>

        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-800">
            <p class="font-bold mb-1"><i class="fa-solid fa-shield-halved"></i> Anti-baneo activado</p>
            <p>El envío usa intervalos aleatorios entre cada mensaje y descansos por lote para evitar que WhatsApp banee tu número (ya que estamos usando whatsapp-web.js, no la API oficial de Meta). Asegúrate que el cron <code class="bg-white px-1 rounded">campanas:procesar</code> corra cada minuto.</p>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Audiencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Progreso</th>
                        <th class="px-4 py-3">Programada</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($campanas as $c)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-800">{{ $c->nombre }}</p>
                                <p class="text-[11px] text-slate-500 truncate max-w-[260px]">{{ mb_strimwidth($c->mensaje, 0, 80, '…') }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono">{{ $c->audiencia_tipo }}</span>
                                <p class="text-slate-500 mt-0.5">{{ $c->total_destinatarios }} destinatarios</p>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $colorEstado = [
                                        'borrador'   => 'bg-slate-100 text-slate-700',
                                        'programada' => 'bg-blue-100 text-blue-700',
                                        'corriendo'  => 'bg-emerald-100 text-emerald-700',
                                        'pausada'    => 'bg-amber-100 text-amber-700',
                                        'completada' => 'bg-violet-100 text-violet-700',
                                        'cancelada'  => 'bg-rose-100 text-rose-700',
                                    ][$c->estado] ?? 'bg-slate-100';
                                @endphp
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $colorEstado }}">
                                    {{ ucfirst($c->estado) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="flex flex-col gap-0.5">
                                    <span><i class="fa-solid fa-check text-emerald-500"></i> {{ $c->total_enviados }}</span>
                                    <span><i class="fa-solid fa-xmark text-rose-500"></i> {{ $c->total_fallidos }}</span>
                                    <span><i class="fa-regular fa-clock text-slate-400"></i> {{ $c->total_pendientes }}</span>
                                </div>
                                @if($c->total_destinatarios > 0)
                                    @php $pct = (int) round((($c->total_enviados + $c->total_fallidos) / $c->total_destinatarios) * 100); @endphp
                                    <div class="w-full h-1.5 bg-slate-100 rounded-full mt-1">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $c->programada_para?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if($c->estado === 'borrador' || $c->estado === 'programada' || $c->estado === 'pausada')
                                        <button wire:click="iniciar({{ $c->id }})" title="Iniciar / reanudar"
                                                class="h-8 w-8 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 transition">
                                            <i class="fa-solid fa-play text-xs"></i>
                                        </button>
                                    @endif
                                    @if($c->estado === 'corriendo')
                                        <button wire:click="pausar({{ $c->id }})" title="Pausar"
                                                class="h-8 w-8 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-700 transition">
                                            <i class="fa-solid fa-pause text-xs"></i>
                                        </button>
                                    @endif
                                    <button wire:click="generarAudiencia({{ $c->id }})" title="Generar/regenerar audiencia"
                                            class="h-8 w-8 rounded-lg bg-violet-100 hover:bg-violet-200 text-violet-700 transition">
                                        <i class="fa-solid fa-users text-xs"></i>
                                    </button>
                                    <button wire:click="abrirEditar({{ $c->id }})" title="Editar"
                                            class="h-8 w-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    @if(in_array($c->estado, ['programada','corriendo','pausada']))
                                        <button wire:click="cancelar({{ $c->id }})" wire:confirm="¿Cancelar campaña?"
                                                class="h-8 w-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition" title="Cancelar">
                                            <i class="fa-solid fa-ban text-xs"></i>
                                        </button>
                                    @endif
                                    <button wire:click="eliminar({{ $c->id }})" wire:confirm="¿Eliminar campaña?"
                                            class="h-8 w-8 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition" title="Eliminar">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">
                            <i class="fa-solid fa-bullhorn text-3xl text-slate-300 mb-2 block"></i>
                            Sin campañas. Crea la primera para enviar mensajes masivos a tus clientes.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($campanas->hasPages())
                <div class="p-4 border-t border-slate-100">{{ $campanas->links() }}</div>
            @endif
        </div>
    </div>

    @if($modal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-brand-soft/40 via-white to-white">
                    <h3 class="font-bold text-slate-800">{{ $editandoId ? 'Editar' : 'Nueva' }} campaña</h3>
                    <button wire:click="cerrarModal" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                        <input type="text" wire:model="nombre" placeholder="Promoción semana santa"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mensaje *</label>
                        <textarea wire:model="mensaje" rows="4" placeholder="¡Hola {primer_nombre}! Tenemos un 20% de descuento en chuletas hasta el domingo. ¿Te animas? 🥩"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono"></textarea>
                        <p class="text-[10px] text-slate-500 mt-1">
                            Variables: <code>{nombre}</code>, <code>{primer_nombre}</code>, <code>{telefono}</code>
                        </p>
                    </div>

                    <div class="rounded-xl border-2 border-slate-200 p-4 space-y-3">
                        <h4 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-users text-brand"></i> Audiencia</h4>
                        <select wire:model.live="audienciaTipo" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="todos">Todos los clientes activos</option>
                            <option value="zona">Por zona de cobertura</option>
                            <option value="sede">Por sede (clientes con pedidos en esa sede)</option>
                            <option value="con_pedidos">Con N o más pedidos</option>
                            <option value="sin_pedidos">Que NO han pedido aún</option>
                            <option value="manual">Lista manual de teléfonos</option>
                        </select>

                        @if($audienciaTipo === 'zona')
                            <select wire:model="zonaId" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">— Selecciona zona —</option>
                                @foreach($zonas as $z)
                                    <option value="{{ $z->id }}">{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                        @elseif($audienciaTipo === 'sede')
                            <select wire:model="sedeId" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">— Selecciona sede —</option>
                                @foreach($sedes as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        @elseif($audienciaTipo === 'con_pedidos')
                            <input type="number" wire:model="minPedidos" min="1" placeholder="Mínimo de pedidos"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        @elseif($audienciaTipo === 'manual')
                            <textarea wire:model="telefonosManual" rows="4"
                                      placeholder="Pega los teléfonos uno por línea o separados por coma:&#10;573001234567&#10;573009876543"
                                      class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono"></textarea>
                        @endif
                    </div>

                    <div class="rounded-xl border-2 border-amber-200 bg-amber-50/30 p-4 space-y-3">
                        <h4 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-shield-halved text-amber-600"></i> Anti-baneo (throttle)</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Intervalo MIN entre mensajes (seg)</label>
                                <input type="number" wire:model="intervaloMinSeg" min="1" max="300"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Intervalo MAX entre mensajes (seg)</label>
                                <input type="number" wire:model="intervaloMaxSeg" min="1" max="600"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tamaño de lote</label>
                                <input type="number" wire:model="loteTamano" min="1" max="500"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Descanso entre lotes (min)</label>
                                <input type="number" wire:model="descansoLoteMin" min="0" max="1440"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Ventana DESDE</label>
                                <input type="time" wire:model="ventanaDesde"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Ventana HASTA</label>
                                <input type="time" wire:model="ventanaHasta"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Programar para (opcional)</label>
                        <input type="datetime-local" wire:model="programadaPara"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <p class="text-[10px] text-slate-500 mt-1">Si vacío, queda como borrador. Cuando le des "Iniciar" arrancará.</p>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50">
                    <button wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                    <button wire:click="guardar" class="rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-5 py-2 text-sm font-bold text-white shadow-lg">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
