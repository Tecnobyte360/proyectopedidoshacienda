<div class="max-w-6xl mx-auto p-4 md:p-6" style="font-family:system-ui,sans-serif">

    <div class="mb-5">
        <h1 class="text-2xl font-extrabold text-slate-900">Asistente IA + SAP · Activación por cliente</h1>
        <p class="text-sm text-slate-500 mt-1">Configura la conexión al Service Layer de cada tenant y activa sus planes/agentes.</p>
    </div>

    @if (session('sap_ok'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">
            {{ session('sap_ok') }}
        </div>
    @endif

    <div class="grid md:grid-cols-[260px_1fr] gap-6">

        {{-- Selector de tenants --}}
        <aside class="bg-white rounded-2xl border border-slate-200 p-3 h-max">
            <div class="text-xs font-bold uppercase tracking-wide text-slate-400 px-2 py-1">Clientes</div>
            <div class="flex flex-col gap-1 mt-1 max-h-[70vh] overflow-y-auto">
                @foreach ($tenants as $t)
                    <button type="button" wire:click="seleccionarTenant({{ $t->id }})"
                        class="text-left px-3 py-2 rounded-lg text-sm font-semibold transition
                        {{ $tenantId === $t->id ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                        {{ $t->nombre }}
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- Panel de configuración --}}
        <section>
            @if (!$tenantId)
                <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
                    Selecciona un cliente para configurar su asistente SAP.
                </div>
            @else
                {{-- Conexión Service Layer --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-5">
                    <div class="flex items-center gap-3 mb-4">
                        <h2 class="font-bold text-slate-900">Conexión SAP (Service Layer)</h2>
                        <label class="ml-auto inline-flex items-center gap-2 text-sm font-semibold text-slate-600">
                            <input type="checkbox" wire:model="activo" class="rounded"> Activo
                        </label>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="text-sm">
                            <span class="text-slate-500">Modo</span>
                            <select wire:model="sl_mode" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="direct">Directo (KIVOX → SAP)</option>
                                <option value="bridge">Puente/agente en la red del cliente</option>
                            </select>
                        </label>
                        <label class="text-sm">
                            <span class="text-slate-500">Timeout (s)</span>
                            <input type="number" wire:model="sl_timeout" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        </label>

                        @if ($sl_mode === 'direct')
                            <label class="text-sm sm:col-span-2">
                                <span class="text-slate-500">URL Service Layer</span>
                                <input type="text" wire:model="sl_url" placeholder="https://host:50000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            </label>
                            <label class="text-sm">
                                <span class="text-slate-500">Company DB</span>
                                <input type="text" wire:model="sl_company" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            </label>
                            <label class="text-sm">
                                <span class="text-slate-500">Usuario</span>
                                <input type="text" wire:model="sl_username" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            </label>
                            <label class="text-sm sm:col-span-2">
                                <span class="text-slate-500">Contraseña @if($tienePassword)<em class="text-emerald-600 not-italic">(guardada — deja vacío para conservar)</em>@endif</span>
                                <input type="password" wire:model="sl_password" placeholder="••••••••" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            </label>
                        @else
                            <label class="text-sm sm:col-span-2">
                                <span class="text-slate-500">URL del puente/agente</span>
                                <input type="text" wire:model="bridge_url" placeholder="https://agente-cliente:puerto" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            </label>
                            <label class="text-sm sm:col-span-2">
                                <span class="text-slate-500">Token del puente (deja vacío para conservar)</span>
                                <input type="password" wire:model="bridge_token" placeholder="••••••••" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                            </label>
                        @endif
                    </div>

                    @if ($pingResultado)
                        <div class="mt-3 text-sm font-semibold {{ str_contains($pingResultado, '✅') ? 'text-emerald-700' : 'text-red-600' }}">
                            {{ $pingResultado }}
                        </div>
                    @endif

                    <div class="flex gap-2 mt-4">
                        <button wire:click="guardar" class="bg-slate-900 text-white text-sm font-bold rounded-lg px-4 py-2 hover:bg-slate-700">Guardar</button>
                        <button wire:click="probarConexion" wire:loading.attr="disabled" class="border border-slate-300 text-slate-700 text-sm font-bold rounded-lg px-4 py-2 hover:bg-slate-50">
                            <span wire:loading.remove wire:target="probarConexion">Probar conexión</span>
                            <span wire:loading wire:target="probarConexion">Probando…</span>
                        </button>
                    </div>
                </div>

                {{-- Planes y agentes --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-bold text-slate-900 mb-1">Planes y agentes</h2>
                    <p class="text-xs text-slate-500 mb-4">Activa un plan completo o cada agente individual. Recuerda <b>Guardar</b>.</p>

                    @foreach ($planes as $planKey => $plan)
                        @php
                            $agentesPlan = $plan['agentes'] ?? [];
                            $todos = count(array_intersect($agentesPlan, $agentes)) === count($agentesPlan) && count($agentesPlan);
                        @endphp
                        <div class="border border-slate-200 rounded-xl p-4 mb-3">
                            <div class="flex items-center gap-3 mb-3">
                                <i class="{{ $plan['icono'] ?? 'fa-solid fa-box' }} text-emerald-600"></i>
                                <b class="text-slate-800">{{ $plan['nombre'] ?? $planKey }}</b>
                                <div class="ml-auto flex gap-2">
                                    <button wire:click="activarPlan('{{ $planKey }}')" class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-1 hover:bg-emerald-100">Activar todo</button>
                                    <button wire:click="desactivarPlan('{{ $planKey }}')" class="text-xs font-bold text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1 hover:bg-slate-100">Quitar todo</button>
                                </div>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-2">
                                @foreach ($agentesPlan as $agKey)
                                    @php $ag = $agentesCatalogo[$agKey] ?? null; @endphp
                                    @if ($ag)
                                        <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition
                                            {{ in_array($agKey, $agentes) ? 'border-emerald-400 bg-emerald-50/50' : 'border-slate-200 hover:border-slate-300' }}">
                                            <input type="checkbox" class="mt-1 rounded"
                                                wire:click="toggleAgente('{{ $agKey }}')"
                                                @checked(in_array($agKey, $agentes))>
                                            <span>
                                                <b class="text-sm text-slate-800">{{ $ag['codigo'] ?? '' }} {{ $ag['nombre'] }}</b>
                                                <span class="block text-xs text-slate-500 mt-0.5">{{ $ag['descripcion'] }}</span>
                                            </span>
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
