<div class="p-4 md:p-6 max-w-7xl mx-auto">

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-phone text-sky-500"></i>
                Llamadas WhatsApp (Meta Calling API)
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Historial de llamadas entrantes/salientes y permisos de los clientes.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="horas" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs">
                <option value="1">Última hora</option>
                <option value="24">Últimas 24 h</option>
                <option value="168">Últimos 7 días</option>
                <option value="720">Últimos 30 días</option>
            </select>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
        <div class="bg-white border border-slate-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-slate-500 font-bold">Total</div>
            <div class="text-2xl font-bold text-slate-800">{{ $this->kpis['total'] }}</div>
        </div>
        <div class="bg-white border border-emerald-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-emerald-600 font-bold">Conectadas</div>
            <div class="text-2xl font-bold text-emerald-700">{{ $this->kpis['conectadas'] }}</div>
        </div>
        <div class="bg-white border border-rose-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-rose-600 font-bold">Fallidas</div>
            <div class="text-2xl font-bold text-rose-700">{{ $this->kpis['fallidas'] }}</div>
        </div>
        <div class="bg-white border border-sky-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-sky-600 font-bold">Minutos</div>
            <div class="text-2xl font-bold text-sky-700">{{ $this->kpis['minutos'] }}</div>
        </div>
        <div class="bg-white border border-violet-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-violet-600 font-bold">Costo USD</div>
            <div class="text-2xl font-bold text-violet-700">${{ number_format($this->kpis['costo_usd'], 4) }}</div>
        </div>
        <div class="bg-white border border-amber-200 rounded-xl p-3">
            <div class="text-[10px] uppercase text-amber-600 font-bold">Permisos OK</div>
            <div class="text-2xl font-bold text-amber-700">{{ $this->kpis['perms_ok'] }}</div>
        </div>
    </div>

    {{-- Aviso si Calling API aún no está activo --}}
    @if($this->kpis['total'] === 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 mb-4 text-xs text-amber-800 flex items-start gap-2">
            <i class="fa-solid fa-circle-info text-amber-500 mt-0.5"></i>
            <div>
                <strong>Calling API aún no está activo en tu WABA.</strong>
                Hasta que Meta apruebe la solicitud (Tarea #50), este panel se mantendrá vacío.
                Mientras tanto, el botón "Llamar" en /chat abre wa.me como alternativa manual.
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex border-b border-slate-200 mb-3">
        <button wire:click="$set('tab', 'historial')"
                class="px-4 py-2 text-xs font-semibold {{ $tab === 'historial' ? 'border-b-2 border-sky-500 text-sky-700' : 'text-slate-500' }}">
            <i class="fa-solid fa-list mr-1"></i> Historial ({{ $this->llamadas->count() }})
        </button>
        <button wire:click="$set('tab', 'permisos')"
                class="px-4 py-2 text-xs font-semibold {{ $tab === 'permisos' ? 'border-b-2 border-sky-500 text-sky-700' : 'text-slate-500' }}">
            <i class="fa-solid fa-shield-check mr-1"></i> Permisos ({{ $this->permisos->count() }})
        </button>
    </div>

    @if($tab === 'historial')
        {{-- Filtros --}}
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <select wire:model.live="filtroDir" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs">
                <option value="">Todas (in/out)</option>
                <option value="outbound">Salientes</option>
                <option value="inbound">Entrantes</option>
            </select>
            <select wire:model.live="filtroEst" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs">
                <option value="">Todos los estados</option>
                <option value="connected">Conectada</option>
                <option value="ended">Finalizada</option>
                <option value="failed">Fallida</option>
                <option value="rejected">Rechazada</option>
                <option value="no_permission">Sin permiso</option>
            </select>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            @if($this->llamadas->isEmpty())
                <div class="p-10 text-center text-slate-400">
                    <i class="fa-solid fa-phone-slash text-4xl mb-2 text-slate-300"></i>
                    <p class="text-sm">No hay llamadas en el período seleccionado.</p>
                </div>
            @else
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold">
                        <tr>
                            <th class="px-2 py-2 text-left w-20">Dir</th>
                            <th class="px-2 py-2 text-left">Cliente</th>
                            <th class="px-2 py-2 text-left">Operador</th>
                            <th class="px-2 py-2 text-center w-24">Estado</th>
                            <th class="px-2 py-2 text-center w-20">Duración</th>
                            <th class="px-2 py-2 text-right w-24">Costo</th>
                            <th class="px-2 py-2 text-left w-32">Cuando</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($this->llamadas as $c)
                            <tr class="hover:bg-sky-50/30">
                                <td class="px-2 py-2 align-top">
                                    @if($c->direccion === 'outbound')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                            <i class="fa-solid fa-phone-arrow-up-right"></i> OUT
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700">
                                            <i class="fa-solid fa-phone-arrow-down-left"></i> IN
                                        </span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 align-top">
                                    <div class="font-semibold text-slate-800">{{ $c->cliente?->nombre ?? '—' }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">+{{ $c->telefono }}</div>
                                </td>
                                <td class="px-2 py-2 align-top text-slate-700">{{ $c->operador?->name ?? '—' }}</td>
                                <td class="px-2 py-2 text-center align-top">
                                    @php
                                        $colorMap = [
                                            'connected' => 'bg-emerald-100 text-emerald-700',
                                            'ended'     => 'bg-slate-100 text-slate-600',
                                            'ringing'   => 'bg-amber-100 text-amber-700',
                                            'requested' => 'bg-blue-100 text-blue-700',
                                            'connecting'=> 'bg-amber-100 text-amber-700',
                                            'failed'    => 'bg-rose-100 text-rose-700',
                                            'rejected'  => 'bg-rose-100 text-rose-700',
                                            'no_permission' => 'bg-orange-100 text-orange-700',
                                        ];
                                        $color = $colorMap[$c->estado] ?? 'bg-slate-100 text-slate-600';
                                    @endphp
                                    <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold {{ $color }}">
                                        {{ strtoupper($c->estado) }}
                                    </span>
                                    @if($c->error_msg)
                                        <div class="text-[9px] text-rose-500 mt-0.5" title="{{ $c->error_msg }}">
                                            {{ \Illuminate\Support\Str::limit($c->error_msg, 30) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-center align-top text-slate-700">
                                    @if($c->duracion_seg > 0)
                                        {{ floor($c->duracion_seg / 60) }}:{{ str_pad($c->duracion_seg % 60, 2, '0', STR_PAD_LEFT) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right align-top text-slate-700">
                                    @if((float) $c->costo_usd > 0)
                                        ${{ number_format($c->costo_usd, 4) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-2 py-2 align-top text-[10px] text-slate-500">
                                    {{ $c->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @else
        {{-- Tab Permisos --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-3 p-3">
            <form wire:submit.prevent="solicitarPermisoManual" class="flex items-end gap-2 flex-wrap">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                        Solicitar permiso de llamada a un número
                    </label>
                    <input type="text" wire:model="telPermiso" placeholder="573216499744"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                </div>
                <button type="submit" class="inline-flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition">
                    <i class="fa-solid fa-paper-plane text-[10px]"></i>
                    Enviar solicitud
                </button>
            </form>
            <p class="text-[10px] text-slate-500 mt-2">
                El cliente recibirá un mensaje con botón "Permitir/Bloquear". Si acepta, podrás llamarlo desde el botón 📞 del chat.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            @if($this->permisos->isEmpty())
                <div class="p-10 text-center text-slate-400">
                    <i class="fa-solid fa-shield text-4xl mb-2 text-slate-300"></i>
                    <p class="text-sm">Aún no hay permisos solicitados.</p>
                </div>
            @else
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500 font-semibold">
                        <tr>
                            <th class="px-2 py-2 text-left">Teléfono</th>
                            <th class="px-2 py-2 text-center w-28">Estado</th>
                            <th class="px-2 py-2 text-left w-40">Solicitado</th>
                            <th class="px-2 py-2 text-left w-40">Respondido</th>
                            <th class="px-2 py-2 text-left w-40">Expira</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($this->permisos as $p)
                            <tr class="hover:bg-amber-50/30">
                                <td class="px-2 py-2 font-mono text-slate-800">+{{ $p->telefono }}</td>
                                <td class="px-2 py-2 text-center">
                                    @php
                                        $clr = match($p->estado) {
                                            'accepted' => 'bg-emerald-100 text-emerald-700',
                                            'rejected' => 'bg-rose-100 text-rose-700',
                                            'expired'  => 'bg-slate-100 text-slate-500',
                                            default    => 'bg-amber-100 text-amber-700',
                                        };
                                    @endphp
                                    <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold {{ $clr }}">
                                        {{ strtoupper($p->estado) }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 text-[10px] text-slate-500">{{ $p->solicitado_at?->diffForHumans() ?? '—' }}</td>
                                <td class="px-2 py-2 text-[10px] text-slate-500">{{ $p->respondido_at?->diffForHumans() ?? '—' }}</td>
                                <td class="px-2 py-2 text-[10px] {{ $p->expira_at && $p->expira_at->isPast() ? 'text-rose-500' : 'text-slate-500' }}">
                                    {{ $p->expira_at?->format('Y-m-d H:i') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
