<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-slate-200 bg-gradient-to-r from-emerald-50 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg">
                    <i class="fa-solid fa-plug-circle-bolt text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800">Asistente IA + SAP</h2>
                    <p class="text-sm text-slate-500">Configura la conexión al Service Layer de cada cliente y activa sus planes y agentes.</p>
                </div>
            </div>
        </div>

        @if (session('sap_ok'))
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i> {{ session('sap_ok') }}
            </div>
        @endif

        <div class="grid lg:grid-cols-[300px_1fr] gap-6 items-start">

            {{-- CLIENTES --}}
            <aside class="rounded-2xl bg-white border border-slate-200 shadow-sm p-3">
                <div class="flex items-center gap-2 px-2 py-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                    <i class="fa-solid fa-building"></i> Clientes
                </div>
                <div class="flex flex-col gap-1 mt-1 max-h-[65vh] overflow-y-auto">
                    @foreach ($tenants as $t)
                        <button type="button" wire:click="seleccionarTenant({{ $t->id }})"
                            class="flex items-center gap-3 text-left px-3 py-2.5 rounded-xl text-sm font-semibold transition
                            {{ $tenantId === $t->id
                                ? 'bg-gradient-to-r from-emerald-500 to-emerald-700 text-white shadow'
                                : 'text-slate-700 hover:bg-slate-100' }}">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs
                                {{ $tenantId === $t->id ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">
                                {{ mb_strtoupper(mb_substr($t->nombre, 0, 1)) }}
                            </span>
                            <span class="truncate">{{ $t->nombre }}</span>
                        </button>
                    @endforeach
                </div>
            </aside>

            {{-- CONFIGURACIÓN --}}
            <section class="space-y-6">
                @if (!$tenantId)
                    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-12 text-center">
                        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <i class="fa-solid fa-hand-pointer text-xl"></i>
                        </div>
                        <p class="text-slate-500 font-medium">Selecciona un cliente para configurar su asistente SAP.</p>
                    </div>
                @else
                    <form autocomplete="off" wire:submit.prevent="guardar" class="space-y-6">
                        {{-- Conexión Service Layer --}}
                        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                    <i class="fa-solid fa-database"></i>
                                </span>
                                <h3 class="font-bold text-slate-800">Conexión SAP · Service Layer</h3>
                                <label class="ml-auto inline-flex items-center gap-2 text-sm font-semibold text-slate-600 cursor-pointer">
                                    <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    Activo
                                </label>
                            </div>

                            <div class="p-5 grid sm:grid-cols-2 gap-4">
                                <label class="text-sm">
                                    <span class="block font-semibold text-slate-600 mb-1">Modo de conexión</span>
                                    <select wire:model.live="sl_mode" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        <option value="direct">Directo (KIVOX → SAP)</option>
                                        <option value="bridge">Puente / agente en la red del cliente</option>
                                    </select>
                                </label>
                                <label class="text-sm">
                                    <span class="block font-semibold text-slate-600 mb-1">Timeout (segundos)</span>
                                    <input type="number" wire:model="sl_timeout" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </label>

                                @if ($sl_mode === 'direct')
                                    <label class="text-sm sm:col-span-2">
                                        <span class="block font-semibold text-slate-600 mb-1">URL del Service Layer</span>
                                        <input type="text" wire:model="sl_url" autocomplete="off" placeholder="https://host:50000"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                    <label class="text-sm">
                                        <span class="block font-semibold text-slate-600 mb-1">Company DB</span>
                                        <input type="text" wire:model="sl_company" autocomplete="off"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                    <label class="text-sm">
                                        <span class="block font-semibold text-slate-600 mb-1">Usuario SAP</span>
                                        <input type="text" wire:model="sl_username" autocomplete="off" name="sap_user_{{ $tenantId }}"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                    <label class="text-sm sm:col-span-2">
                                        <span class="block font-semibold text-slate-600 mb-1">
                                            Contraseña
                                            @if($tienePassword)<span class="text-emerald-600 font-normal">· guardada (deja vacío para conservar)</span>@endif
                                        </span>
                                        <input type="password" wire:model="sl_password" autocomplete="new-password" name="sap_pass_{{ $tenantId }}" placeholder="••••••••"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                @else
                                    <label class="text-sm sm:col-span-2">
                                        <span class="block font-semibold text-slate-600 mb-1">URL del puente / agente</span>
                                        <input type="text" wire:model="bridge_url" autocomplete="off" placeholder="https://agente-cliente:puerto"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                    <label class="text-sm sm:col-span-2">
                                        <span class="block font-semibold text-slate-600 mb-1">Token del puente <span class="text-slate-400 font-normal">(deja vacío para conservar)</span></span>
                                        <input type="password" wire:model="bridge_token" autocomplete="new-password" placeholder="••••••••"
                                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                @endif
                            </div>

                            @if ($pingResultado)
                                <div class="mx-5 mb-3 rounded-xl px-4 py-2.5 text-sm font-semibold
                                    {{ str_contains($pingResultado, '✅') ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $pingResultado }}
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-2 px-5 pb-5">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-700 hover:from-emerald-600 hover:to-emerald-800 text-white font-bold px-5 py-2.5 shadow-lg transition">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                                </button>
                                <button type="button" wire:click="probarConexion" wire:loading.attr="disabled" wire:target="probarConexion"
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 text-slate-700 font-bold px-5 py-2.5 hover:bg-slate-50 transition disabled:opacity-50">
                                    <i class="fa-solid fa-plug"></i>
                                    <span wire:loading.remove wire:target="probarConexion">Probar conexión</span>
                                    <span wire:loading wire:target="probarConexion">Probando…</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- Planes y agentes --}}
                    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
                        <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <i class="fa-solid fa-layer-group"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-slate-800">Planes y agentes</h3>
                                <p class="text-xs text-slate-500">Activa un plan completo o cada agente individual. Recuerda <b>Guardar</b>.</p>
                            </div>
                        </div>

                        <div class="p-5 space-y-4">
                            @foreach ($planes as $planKey => $plan)
                                <div class="rounded-2xl border border-slate-200 overflow-hidden">
                                    <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 border-b border-slate-100">
                                        <i class="{{ $plan['icono'] ?? 'fa-solid fa-box' }} text-emerald-600"></i>
                                        <b class="text-slate-800">{{ $plan['nombre'] ?? $planKey }}</b>
                                        <div class="ml-auto flex gap-2">
                                            <button type="button" wire:click="activarPlan('{{ $planKey }}')"
                                                class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-1.5 hover:bg-emerald-100 transition">
                                                <i class="fa-solid fa-circle-check"></i> Activar todo
                                            </button>
                                            <button type="button" wire:click="desactivarPlan('{{ $planKey }}')"
                                                class="text-xs font-bold text-slate-500 bg-white border border-slate-200 rounded-lg px-3 py-1.5 hover:bg-slate-50 transition">
                                                Quitar todo
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-3 p-4">
                                        @foreach ($plan['agentes'] ?? [] as $agKey)
                                            @php $ag = $agentesCatalogo[$agKey] ?? null; $on = in_array($agKey, $agentes); @endphp
                                            @if ($ag)
                                                <button type="button" wire:click="toggleAgente('{{ $agKey }}')"
                                                    class="text-left flex items-start gap-3 p-4 rounded-xl border-2 transition
                                                    {{ $on ? 'border-emerald-400 bg-emerald-50/60' : 'border-slate-200 hover:border-slate-300 bg-white' }}">
                                                    <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-md border-2 flex-none
                                                        {{ $on ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-slate-300 text-transparent' }}">
                                                        <i class="fa-solid fa-check text-[10px]"></i>
                                                    </span>
                                                    <span class="min-w-0">
                                                        <b class="block text-sm text-slate-800">
                                                            <span class="text-emerald-600">{{ $ag['codigo'] ?? '' }}</span> {{ $ag['nombre'] }}
                                                        </b>
                                                        <span class="block text-xs text-slate-500 mt-1 leading-relaxed">{{ $ag['descripcion'] }}</span>
                                                    </span>
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
