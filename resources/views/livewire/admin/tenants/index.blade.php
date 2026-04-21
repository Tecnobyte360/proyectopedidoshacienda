<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- El banner de impersonación ahora vive en el topbar (visible en TODAS las páginas) --}}

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-violet-700 text-white shadow-lg">
                        <i class="fa-solid fa-building text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800">Tenants (Empresas Cliente)</h2>
                        <p class="text-sm text-slate-500">Gestiona las empresas que usan tu plataforma SaaS</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="probarHostinger"
                            class="inline-flex items-center gap-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-4 py-3 text-sm transition"
                            title="Verifica que la API Key de Hostinger funciona">
                        <i class="fa-solid fa-plug"></i> Probar Hostinger
                    </button>
                    <button wire:click="abrirModalCrear"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] text-white font-bold px-5 py-3 transition shadow-lg">
                        <i class="fa-solid fa-plus"></i> Nuevo tenant
                    </button>
                </div>
            </div>
        </div>

        {{-- KPIS --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 font-bold">Total</p>
                        <p class="text-3xl font-extrabold text-slate-800 mt-1">{{ $kpis['total'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <i class="fa-solid fa-building"></i>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white border border-emerald-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-emerald-600 font-bold">Activos</p>
                        <p class="text-3xl font-extrabold text-emerald-700 mt-1">{{ $kpis['activos'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white border border-blue-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-blue-600 font-bold">En Trial</p>
                        <p class="text-3xl font-extrabold text-blue-700 mt-1">{{ $kpis['trial'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white border border-rose-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-rose-600 font-bold">Vencidos</p>
                        <p class="text-3xl font-extrabold text-rose-700 mt-1">{{ $kpis['vencidos'] }}</p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- BÚSQUEDA --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" wire:model.live.debounce.400ms="busqueda"
                       placeholder="Buscar por nombre, slug o email…"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm focus:border-[#d68643] focus:bg-white focus:ring-2 focus:ring-[#d68643]/20">
            </div>
        </div>

        {{-- TABLA --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Suscripción</th>
                            <th class="px-4 py-3 text-center">Usuarios</th>
                            <th class="px-4 py-3 text-center">Pedidos</th>
                            <th class="px-4 py-3">Subdominio</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($tenants as $t)
                            @php
                                $planBadge = [
                                    'basico'  => 'bg-slate-100 text-slate-700',
                                    'pro'     => 'bg-blue-100 text-blue-700',
                                    'empresa' => 'bg-violet-100 text-violet-700',
                                ][$t->plan] ?? 'bg-slate-100 text-slate-700';

                                $dominio = $t->slug . '.tecnobyte360.com';
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition {{ !$t->activo ? 'opacity-60 bg-rose-50/30' : '' }}">
                                {{-- EMPRESA --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="h-10 w-10 rounded-xl flex items-center justify-center text-white font-extrabold text-sm flex-shrink-0 overflow-hidden"
                                             style="background: linear-gradient(135deg, {{ $t->color_primario }}, {{ $t->color_secundario }});">
                                            @if($t->logo_url)
                                                <img src="{{ $t->logo_url }}" class="h-full w-full object-contain" alt="logo">
                                            @else
                                                {{ strtoupper(substr($t->nombre, 0, 1)) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-800 truncate">{{ $t->nombre }}</div>
                                            <div class="text-xs text-slate-500 font-mono truncate">@/{{ $t->slug }}</div>
                                            @if($t->contacto_email)
                                                <div class="text-[11px] text-slate-400 truncate">
                                                    <i class="fa-solid fa-envelope text-[9px]"></i> {{ $t->contacto_email }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- PLAN --}}
                                <td class="px-4 py-3">
                                    <span class="text-[11px] font-bold uppercase px-2 py-1 rounded-full {{ $planBadge }}">
                                        {{ $t->plan }}
                                    </span>
                                </td>

                                {{-- ESTADO --}}
                                <td class="px-4 py-3">
                                    @if($t->activo)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                            <i class="fa-solid fa-circle text-[8px]"></i> Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-rose-100 text-rose-700">
                                            <i class="fa-solid fa-pause text-[9px]"></i> Suspendido
                                        </span>
                                    @endif
                                </td>

                                {{-- SUSCRIPCIÓN --}}
                                <td class="px-4 py-3 text-xs">
                                    @if($t->trial_ends_at && $t->trial_ends_at->isFuture())
                                        <span class="font-bold px-2 py-1 rounded-full bg-blue-100 text-blue-700">
                                            Trial · {{ $t->trial_ends_at->diffForHumans() }}
                                        </span>
                                    @elseif($t->subscription_ends_at)
                                        @if($t->subscription_ends_at->isPast())
                                            <span class="font-bold px-2 py-1 rounded-full bg-rose-100 text-rose-700">
                                                Vencido {{ $t->subscription_ends_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <div class="text-slate-700 font-medium">{{ $t->subscription_ends_at->format('d/m/Y') }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $t->subscription_ends_at->diffForHumans() }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- USUARIOS --}}
                                <td class="px-4 py-3 text-center font-bold text-slate-700">{{ $t->users_count }}</td>

                                {{-- PEDIDOS --}}
                                <td class="px-4 py-3 text-center font-bold text-slate-700">{{ $t->pedidos_count }}</td>

                                {{-- SUBDOMINIO --}}
                                <td class="px-4 py-3">
                                    <a href="https://{{ $dominio }}" target="_blank"
                                       class="text-xs text-[#a85f24] hover:underline font-medium inline-flex items-center gap-1">
                                        <i class="fa-solid fa-globe text-[10px]"></i>
                                        {{ $dominio }}
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px] opacity-60"></i>
                                    </a>
                                    <div class="mt-1">
                                        <button wire:click="configurarSubdominio({{ $t->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="configurarSubdominio({{ $t->id }})"
                                                class="text-[10px] font-bold px-2 py-1 rounded-md bg-sky-100 hover:bg-sky-200 text-sky-700 transition disabled:opacity-50">
                                            <span wire:loading.remove wire:target="configurarSubdominio({{ $t->id }})">
                                                <i class="fa-solid fa-rotate"></i> Re-configurar
                                            </span>
                                            <span wire:loading wire:target="configurarSubdominio({{ $t->id }})">
                                                <i class="fa-solid fa-circle-notch fa-spin"></i> ...
                                            </span>
                                        </button>
                                    </div>
                                </td>

                                {{-- ACCIONES --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="impersonar({{ $t->id }})"
                                                title="Ver la plataforma como esta empresa"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-violet-100 hover:bg-violet-200 text-violet-700 transition">
                                            <i class="fa-solid fa-mask text-xs"></i>
                                        </button>
                                        <button wire:click="abrirModalEditar({{ $t->id }})"
                                                title="Editar tenant"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <button @click.prevent="$dispatch('confirm-show', {
                                                    title: '{{ $t->activo ? '¿Suspender' : '¿Reactivar' }} tenant?',
                                                    message: '{{ $t->activo
                                                        ? 'Suspender ' . $t->nombre . '. Sus usuarios no podrán entrar al panel hasta que lo reactives. Los datos NO se borran.'
                                                        : 'Reactivar ' . $t->nombre . '. Sus usuarios podrán volver a entrar.' }}',
                                                    confirmText: '{{ $t->activo ? 'Sí, suspender' : 'Sí, reactivar' }}',
                                                    type: '{{ $t->activo ? 'danger' : 'success' }}',
                                                    onConfirm: () => $wire.toggleActivo({{ $t->id }}),
                                                })"
                                                title="{{ $t->activo ? 'Suspender' : 'Reactivar' }} tenant"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg {{ $t->activo ? 'bg-amber-100 hover:bg-amber-200 text-amber-700' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }} transition">
                                            <i class="fa-solid {{ $t->activo ? 'fa-pause' : 'fa-play' }} text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                                        <i class="fa-solid fa-building text-2xl"></i>
                                    </div>
                                    <p class="text-base font-semibold text-slate-700">Sin tenants</p>
                                    <p class="text-sm text-slate-500">Crea la primera empresa con el botón "Nuevo tenant" arriba.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        </div>

        <div>{{ $tenants->links() }}</div>
    </div>

    {{-- MODAL --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             wire:click.self="cerrarModal">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-[#fbe9d7]/40 via-white to-white border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white shadow">
                            <i class="fa-solid {{ $editandoId ? 'fa-pen-to-square' : 'fa-plus' }}"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-800">
                                {{ $editandoId ? 'Editar tenant' : 'Nuevo tenant' }}
                            </h3>
                            <p class="text-xs text-slate-500">Datos de la empresa cliente</p>
                        </div>
                    </div>
                    <button wire:click="cerrarModal" class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form wire:submit.prevent="guardar" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre de la empresa *</label>
                            <input type="text" wire:model="nombre" placeholder="Alimentos La Hacienda"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                            @error('nombre') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Slug (subdominio) <span class="text-xs text-slate-400 font-normal">— se autogenera si vacío</span>
                            </label>
                            <input type="text" wire:model="slug" placeholder="la-hacienda"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                            @error('slug') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Plan *</label>
                            <select wire:model="plan" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                                <option value="basico">Básico</option>
                                <option value="pro">Pro</option>
                                <option value="empresa">Empresa</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Contacto nombre</label>
                            <input type="text" wire:model="contacto_nombre" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Contacto email</label>
                            <input type="email" wire:model="contacto_email" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Contacto teléfono</label>
                            <input type="text" wire:model="contacto_telefono" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Color primario</label>
                            <input type="color" wire:model="color_primario" class="w-full h-11 rounded-xl border-slate-200">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Color secundario</label>
                            <input type="color" wire:model="color_secundario" class="w-full h-11 rounded-xl border-slate-200">
                        </div>

                        {{-- Logo del tenant --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                <i class="fa-solid fa-image text-[#d68643]"></i> Logo del tenant
                                <span class="text-xs text-slate-400 font-normal">(PNG/JPG/SVG/WebP, máx 2MB)</span>
                            </label>

                            <div class="flex items-center gap-4">
                                {{-- Vista previa --}}
                                <div class="h-20 w-20 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                                    @if($logo_archivo)
                                        <img src="{{ $logo_archivo->temporaryUrl() }}" class="h-full w-full object-contain" alt="preview">
                                    @elseif($logo_url_actual)
                                        <img src="{{ $logo_url_actual }}" class="h-full w-full object-contain" alt="logo actual">
                                    @else
                                        <i class="fa-solid fa-image text-2xl text-slate-300"></i>
                                    @endif
                                </div>

                                <div class="flex-1">
                                    <input type="file"
                                           wire:model="logo_archivo"
                                           accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                           class="block w-full text-sm text-slate-700
                                                  file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                                                  file:text-sm file:font-semibold
                                                  file:bg-[#fbe9d7] file:text-[#a85f24] hover:file:bg-[#f5d4ad] cursor-pointer">

                                    <div wire:loading wire:target="logo_archivo" class="text-xs text-sky-600 mt-1">
                                        <i class="fa-solid fa-circle-notch fa-spin"></i> Subiendo...
                                    </div>

                                    @error('logo_archivo')
                                        <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                                    @enderror

                                    @if($logo_url_actual && !$logo_archivo)
                                        <div class="text-xs text-slate-500 mt-1">
                                            Logo actual: <a href="{{ $logo_url_actual }}" target="_blank" class="text-[#a85f24] underline">ver</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Trial vence</label>
                            <input type="date" wire:model="trial_ends_at" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Subscripción vence</label>
                            <input type="date" wire:model="subscription_ends_at" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20">
                        </div>
                    </div>

                    {{-- 📱 WhatsApp del tenant --}}
                    <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50/40 p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-whatsapp text-emerald-600 text-xl"></i>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm">WhatsApp (TecnoByteApp)</h4>
                                <p class="text-xs text-slate-500">Credenciales propias del tenant. Si están vacías, usa las del .env (sandbox).</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Email TecnoByteApp</label>
                                <input type="email" wire:model="whatsapp_email" placeholder="cliente@email.com"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Password TecnoByteApp</label>
                                <input type="password" wire:model="whatsapp_password" placeholder="••••••••"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">API URL <span class="text-slate-400 font-normal">(default: TecnoByteApp)</span></label>
                            <input type="text" wire:model="whatsapp_api_base_url" placeholder="https://wa-api.tecnobyteapp.com:1422"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Connection IDs de TecnoByteApp <span class="text-rose-500">*</span>
                                <span class="text-slate-400 font-normal">(separadas por coma)</span>
                            </label>
                            <input type="text" wire:model="whatsapp_connection_ids" placeholder="15, 28, 42"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            <p class="text-[11px] text-slate-500 mt-1">
                                <i class="fa-solid fa-circle-info"></i>
                                Estos IDs identifican qué números de WhatsApp pertenecen a este tenant. Cuando llegue un webhook con un connection_id, el sistema lo dirige al tenant correcto automáticamente.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas internas</label>
                        <textarea wire:model="notas_internas" rows="2" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20"></textarea>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 border border-slate-200 p-3 cursor-pointer">
                        <input type="checkbox" wire:model="activo" class="mt-0.5 rounded border-slate-300 text-[#d68643]">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">Tenant activo</div>
                            <div class="text-xs text-slate-500">Si está inactivo, sus usuarios no pueden iniciar sesión.</div>
                        </div>
                    </label>

                    {{-- Crear admin inicial (solo en creación) --}}
                    @if(!$editandoId)
                        <div class="rounded-xl border-2 border-dashed border-violet-200 bg-violet-50/50 p-4 space-y-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="crear_admin_inicial" class="rounded border-violet-300 text-violet-500">
                                <span class="text-sm font-bold text-violet-900">
                                    <i class="fa-solid fa-user-plus"></i> Crear usuario admin inicial para este tenant
                                </span>
                            </label>

                            @if($crear_admin_inicial)
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <input type="text" wire:model="admin_nombre" placeholder="Nombre del admin"
                                           class="rounded-xl border border-violet-200 px-3 py-2 text-sm">
                                    <input type="email" wire:model="admin_email" placeholder="email@empresa.com"
                                           class="rounded-xl border border-violet-200 px-3 py-2 text-sm">
                                    <input type="password" wire:model="admin_password" placeholder="Password (min 6)"
                                           class="rounded-xl border border-violet-200 px-3 py-2 text-sm">
                                </div>
                                @error('admin_email') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                @error('admin_password') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    @endif

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="cerrarModal" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] px-6 py-2.5 text-sm font-bold text-white shadow-lg">
                            <i class="fa-solid fa-floppy-disk"></i>
                            {{ $editandoId ? 'Actualizar tenant' : 'Crear tenant' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL: log del setup de subdominio (con polling reactivo) --}}
    @if($subdomModalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
             @if($subdomCorriendo) wire:poll.2s="chequearEstadoSubdominio" @endif>
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 bg-gradient-to-r from-sky-50 to-blue-50">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">
                            <i class="fa-solid fa-rocket text-sky-600"></i>
                            Setup de subdominio
                        </h3>
                        <p class="text-xs text-slate-500">{{ $subdomTenantNombre }} — {{ $subdomDominio }}</p>
                    </div>
                    <button wire:click="cerrarSubdomModal"
                            class="h-9 w-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-5">
                    {{-- Stepper visual --}}
                    <div class="flex items-center justify-between mb-4 text-xs font-semibold">
                        <div class="flex items-center gap-2 {{ in_array($subdomEstado, ['pendiente','aplicado']) || $subdomExito ? 'text-emerald-600' : ($subdomEstado === 'error' ? 'text-rose-600' : 'text-slate-400') }}">
                            <div class="h-7 w-7 rounded-full flex items-center justify-center
                                        {{ $subdomCorriendo ? 'bg-sky-100 text-sky-600' : ($subdomExito ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600') }}">
                                @if($subdomCorriendo)
                                    <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                @elseif($subdomExito)
                                    <i class="fa-solid fa-check text-xs"></i>
                                @else
                                    <i class="fa-solid fa-xmark text-xs"></i>
                                @endif
                            </div>
                            <span>1. DNS Hostinger</span>
                        </div>

                        <div class="flex-1 h-0.5 bg-slate-200 mx-2"></div>

                        <div class="flex items-center gap-2 {{ $subdomEstado === 'aplicado' ? 'text-emerald-600' : ($subdomEstado === 'error' ? 'text-rose-600' : 'text-slate-400') }}">
                            <div class="h-7 w-7 rounded-full flex items-center justify-center
                                        {{ $subdomEstado === 'aplicado' ? 'bg-emerald-100 text-emerald-600' : ($subdomEstado === 'error' ? 'bg-rose-100 text-rose-600' : ($subdomCorriendo ? 'bg-sky-100 text-sky-600' : 'bg-slate-100 text-slate-400')) }}">
                                @if($subdomEstado === 'aplicado')
                                    <i class="fa-solid fa-check text-xs"></i>
                                @elseif($subdomEstado === 'error')
                                    <i class="fa-solid fa-xmark text-xs"></i>
                                @elseif($subdomCorriendo)
                                    <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                @else
                                    <i class="fa-solid fa-clock text-xs"></i>
                                @endif
                            </div>
                            <span>2. Nginx + SSL</span>
                        </div>

                        <div class="flex-1 h-0.5 bg-slate-200 mx-2"></div>

                        <div class="flex items-center gap-2 {{ $subdomExito ? 'text-emerald-600' : 'text-slate-400' }}">
                            <div class="h-7 w-7 rounded-full flex items-center justify-center
                                        {{ $subdomExito ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400' }}">
                                <i class="fa-solid {{ $subdomExito ? 'fa-globe' : 'fa-clock' }} text-xs"></i>
                            </div>
                            <span>3. Operativo</span>
                        </div>
                    </div>

                    @if($subdomCorriendo)
                        <div class="flex items-center gap-2 text-sky-600 text-sm font-semibold mb-3 bg-sky-50 px-3 py-2 rounded-lg">
                            <i class="fa-solid fa-circle-notch fa-spin"></i>
                            Procesando... esto suele tardar 5–30 segundos.
                        </div>
                    @elseif($subdomExito)
                        <div class="flex items-center justify-between gap-2 text-emerald-700 text-sm font-semibold mb-3 bg-emerald-50 px-3 py-2 rounded-lg">
                            <span><i class="fa-solid fa-check-circle"></i> ¡Listo! El subdominio está operativo con SSL.</span>
                            <a href="https://{{ $subdomDominio }}" target="_blank"
                               class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir sitio
                            </a>
                        </div>
                    @else
                        <div class="flex items-center gap-2 text-rose-600 text-sm font-semibold mb-3 bg-rose-50 px-3 py-2 rounded-lg">
                            <i class="fa-solid fa-triangle-exclamation"></i> Hubo errores. Revisa el log.
                        </div>
                    @endif

                    <pre class="bg-slate-900 text-emerald-300 text-xs p-4 rounded-lg max-h-96 overflow-auto whitespace-pre-wrap">{{ $subdomLog ?: 'Sin salida.' }}</pre>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50 flex justify-end">
                    <button wire:click="cerrarSubdomModal"
                            class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
