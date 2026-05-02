<div class="min-h-screen bg-slate-50">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- El banner de impersonación ahora vive en el topbar (visible en TODAS las páginas) --}}

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
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
                    <button wire:click="nuevoTenant"
                            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark text-white font-bold px-5 py-3 transition shadow-lg">
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
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20">
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
                                       class="text-xs text-brand-secondary hover:underline font-medium inline-flex items-center gap-1">
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

                                        {{-- 🗑️ Eliminar definitivamente --}}
                                        <button wire:click="abrirModalEliminar({{ $t->id }})"
                                                title="ELIMINAR DEFINITIVAMENTE — borra DNS, Nginx, SSL y todos los datos"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
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

        <div>{{ $tenants->links() }}</div>
    </div>

    {{-- MODAL (siempre montado, se muestra/oculta con Alpine según $modalAbierto) --}}
    <div x-data x-show="$wire.modalAbierto" x-cloak wire:ignore.self
         class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
         style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);"
         @click.self="$wire.cerrarModal()"
         @keydown.escape.window="$wire.modalAbierto && $wire.cerrarModal()">
        <div @click.stop class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl my-8 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-brand-soft/40 via-white to-white border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow">
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
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                            @error('nombre') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                Slug (subdominio) <span class="text-xs text-slate-400 font-normal">— se autogenera si vacío</span>
                            </label>
                            <input type="text" wire:model="slug" placeholder="la-hacienda"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                            @error('slug') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Plan *</label>
                            <select wire:model="plan" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                                <option value="basico">Básico</option>
                                <option value="pro">Pro</option>
                                <option value="empresa">Empresa</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Contacto nombre</label>
                            <input type="text" wire:model="contacto_nombre" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Contacto email</label>
                            <input type="email" wire:model="contacto_email" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Contacto teléfono</label>
                            <input type="text" wire:model="contacto_telefono" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>

                        {{-- Datos del negocio (para que el bot sea dinámico) --}}
                        <div class="md:col-span-2 mt-2 rounded-2xl bg-purple-50 border border-purple-200 p-4">
                            <h4 class="text-sm font-bold text-purple-800 mb-1">
                                <i class="fa-solid fa-store mr-1"></i> Datos del negocio (para el bot)
                            </h4>
                            <p class="text-[11px] text-purple-600 mb-3">
                                Estos datos se inyectan automáticamente en el prompt del bot. Sin tocar el prompt, cada tenant queda personalizado.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Ciudad</label>
                                    <input type="text" wire:model="ciudad"
                                           placeholder="Ej: Bello, Antioquia"
                                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Tipo de negocio</label>
                                    <select wire:model="tipo_negocio"
                                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white">
                                        <option value="">— Selecciona —</option>
                                        @foreach (\App\Models\Tenant::TIPOS_NEGOCIO as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Slogan / frase</label>
                                    <input type="text" wire:model="slogan"
                                           placeholder='Ej: "La carne más fresca de Antioquia"'
                                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Descripción corta del negocio</label>
                                    <textarea wire:model="descripcion_negocio" rows="3"
                                              placeholder="2-3 líneas describiendo qué hace el negocio. El bot las usará al saludar y presentar la empresa."
                                              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white"></textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Google Maps: API Key + centro del mapa --}}
                        <div class="md:col-span-2 mt-2 rounded-2xl bg-blue-50 border border-blue-200 p-4">
                            <h4 class="text-sm font-bold text-blue-800 mb-1">
                                <i class="fa-solid fa-map-location-dot mr-1"></i> Google Maps
                            </h4>
                            <p class="text-[11px] text-blue-600 mb-3">
                                Ingresa tu API Key de Google Maps para activar el editor visual de zonas de cobertura.
                                <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank" class="underline font-semibold">¿Cómo obtengo una?</a>
                            </p>

                            <div class="space-y-3">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="google_maps_activo" class="mt-1 rounded border-slate-300 text-blue-600 h-5 w-5">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800">Activar Google Maps para este tenant</div>
                                        <div class="text-[11px] text-slate-500">Si está OFF, no se carga el script del mapa.</div>
                                    </div>
                                </label>

                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">API Key</label>
                                    <div class="flex gap-2">
                                        <input type="password" wire:model="google_maps_api_key"
                                               placeholder="AIzaSy..."
                                               class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                                        <button type="button" wire:click="probarGoogleMapsApiKey"
                                                wire:loading.attr="disabled" wire:target="probarGoogleMapsApiKey"
                                                class="rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-bold text-white shadow disabled:opacity-50">
                                            <span wire:loading.remove wire:target="probarGoogleMapsApiKey">
                                                <i class="fa-solid fa-flask mr-1"></i> Probar
                                            </span>
                                            <span wire:loading wire:target="probarGoogleMapsApiKey">
                                                <i class="fa-solid fa-spinner fa-spin"></i>
                                            </span>
                                        </button>
                                    </div>
                                    @if ($googleMapsTestResult !== null)
                                        <div class="mt-2 rounded-xl p-3 text-xs {{ $googleMapsTestResult['ok'] ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-700' }}">
                                            {{ $googleMapsTestResult['mensaje'] }}
                                            @if (!empty($googleMapsTestResult['lat']))
                                                <div class="mt-1 font-mono text-[10px]">
                                                    Centro detectado: {{ $googleMapsTestResult['lat'] }}, {{ $googleMapsTestResult['lng'] }}
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Centro Latitud</label>
                                        <input type="number" step="any" wire:model="google_maps_centro_lat"
                                               placeholder="6.3414"
                                               class="w-full rounded-xl border border-slate-200 px-2 py-1.5 text-xs font-mono bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Centro Longitud</label>
                                        <input type="number" step="any" wire:model="google_maps_centro_lng"
                                               placeholder="-75.5538"
                                               class="w-full rounded-xl border border-slate-200 px-2 py-1.5 text-xs font-mono bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-700 mb-1">Zoom inicial</label>
                                        <input type="number" min="1" max="20" wire:model="google_maps_zoom"
                                               class="w-full rounded-xl border border-slate-200 px-2 py-1.5 text-xs bg-white">
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-500">
                                    💡 Tip: si dejas centro/lng vacíos, el botón "Probar" los rellena automáticamente con la ciudad del tenant.
                                </p>
                            </div>
                        </div>

                        {{-- Colores del tenant: se aplican a sidebar, login, gradientes, badges, etc. --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                <i class="fa-solid fa-palette text-slate-400 mr-1"></i>
                                Colores del tenant
                            </label>
                            <p class="text-[11px] text-slate-500 mb-3">Estos colores se aplican al sidebar, login, gradientes y elementos de marca de toda la plataforma.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                {{-- Primario --}}
                                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50">
                                    <input type="color" wire:model.live="color_primario"
                                           class="h-12 w-12 rounded-lg border border-slate-200 cursor-pointer"
                                           style="padding: 2px;">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">Primario</div>
                                        <input type="text" wire:model.live="color_primario"
                                               placeholder="#d68643"
                                               maxlength="7"
                                               class="w-full mt-0.5 rounded-lg border border-slate-200 px-2 py-1 text-xs font-mono uppercase focus:border-brand focus:ring-1 focus:ring-brand/30">
                                    </div>
                                </div>

                                {{-- Secundario --}}
                                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50">
                                    <input type="color" wire:model.live="color_secundario"
                                           class="h-12 w-12 rounded-lg border border-slate-200 cursor-pointer"
                                           style="padding: 2px;">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">Secundario</div>
                                        <input type="text" wire:model.live="color_secundario"
                                               placeholder="#a85f24"
                                               maxlength="7"
                                               class="w-full mt-0.5 rounded-lg border border-slate-200 px-2 py-1 text-xs font-mono uppercase focus:border-brand focus:ring-1 focus:ring-brand/30">
                                    </div>
                                </div>
                            </div>

                            {{-- Preview en vivo --}}
                            <div class="mt-3 rounded-xl border border-slate-200 overflow-hidden">
                                <div class="text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 bg-slate-100 text-slate-500">
                                    Vista previa
                                </div>
                                <div class="p-4 flex items-center gap-3"
                                     style="background: linear-gradient(135deg,
                                        color-mix(in srgb, {{ $color_primario ?? '#d68643' }} 15%, white),
                                        #ffffff 60%,
                                        color-mix(in srgb, {{ $color_secundario ?? '#a85f24' }} 12%, white));">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-white shadow-md flex-shrink-0"
                                         style="background: linear-gradient(135deg, {{ $color_primario ?? '#d68643' }}, {{ $color_secundario ?? '#a85f24' }});">
                                        <i class="fa-solid fa-utensils"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-bold" style="color: {{ $color_primario ?? '#d68643' }};">{{ $nombre ?: 'Nombre tenant' }}</div>
                                        <div class="text-[11px] text-slate-500">Así se ve la cabecera con tus colores</div>
                                    </div>
                                    <button type="button"
                                            class="rounded-lg px-3 py-1.5 text-xs font-bold text-white shadow"
                                            style="background: linear-gradient(135deg, {{ $color_primario ?? '#d68643' }}, {{ $color_secundario ?? '#a85f24' }});">
                                        Botón
                                    </button>
                                </div>
                            </div>

                            {{-- Presets agrupados por familia de color --}}
                            @php
                                $presetsAgrupados = [
                                    'Cálidos / Tierra' => [
                                        ['Naranja Hacienda',   '#d68643', '#a85f24'],
                                        ['Marrón Café',        '#a05a2c', '#7d4421'],
                                        ['Cobre',              '#c2693e', '#8e4a26'],
                                        ['Terracota',          '#c1502a', '#92381b'],
                                        ['Mostaza',            '#ca8a04', '#854d0e'],
                                        ['Caramelo',           '#b45309', '#78350f'],
                                        ['Bronce',             '#92400e', '#451a03'],
                                        ['Tabaco',             '#78350f', '#451a03'],
                                    ],
                                    'Rojos / Rosas' => [
                                        ['Rojo cereza',        '#dc2626', '#991b1b'],
                                        ['Rojo vino',          '#9f1239', '#4c0519'],
                                        ['Coral',              '#f43f5e', '#be123c'],
                                        ['Rosa fucsia',        '#ec4899', '#9d174d'],
                                        ['Rosa pastel',        '#f472b6', '#be185d'],
                                        ['Magenta',            '#c026d3', '#86198f'],
                                        ['Salmón',             '#f87171', '#b91c1c'],
                                        ['Frambuesa',          '#e11d48', '#881337'],
                                    ],
                                    'Naranjas / Amarillos' => [
                                        ['Naranja vibrante',   '#f97316', '#c2410c'],
                                        ['Mandarina',          '#fb923c', '#c2410c'],
                                        ['Amarillo sol',       '#f59e0b', '#b45309'],
                                        ['Dorado',             '#eab308', '#854d0e'],
                                        ['Limón',              '#fbbf24', '#a16207'],
                                        ['Melocotón',          '#fb923c', '#9a3412'],
                                        ['Albaricoque',        '#fdba74', '#c2410c'],
                                        ['Mango',              '#f59e0b', '#92400e'],
                                    ],
                                    'Verdes' => [
                                        ['Verde esmeralda',    '#10b981', '#047857'],
                                        ['Verde menta',        '#34d399', '#059669'],
                                        ['Verde lima',         '#84cc16', '#3f6212'],
                                        ['Verde bosque',       '#15803d', '#14532d'],
                                        ['Verde oliva',        '#65a30d', '#365314'],
                                        ['Verde mar',          '#0d9488', '#115e59'],
                                        ['Aguacate',           '#3f6212', '#1a2e05'],
                                        ['Pino',               '#166534', '#052e16'],
                                    ],
                                    'Azules' => [
                                        ['Azul océano',        '#2563eb', '#1e40af'],
                                        ['Azul real',          '#1d4ed8', '#1e3a8a'],
                                        ['Azul cielo',         '#0ea5e9', '#0369a1'],
                                        ['Cian',               '#06b6d4', '#0e7490'],
                                        ['Turquesa',           '#14b8a6', '#0f766e'],
                                        ['Azul medianoche',    '#1e3a8a', '#0c1e4d'],
                                        ['Azul celeste',       '#38bdf8', '#075985'],
                                        ['Marino',             '#1e40af', '#172554'],
                                    ],
                                    'Violetas / Púrpuras' => [
                                        ['Violeta',            '#8b5cf6', '#6d28d9'],
                                        ['Lavanda',            '#a78bfa', '#7c3aed'],
                                        ['Púrpura',            '#a855f7', '#7e22ce'],
                                        ['Índigo',             '#6366f1', '#4338ca'],
                                        ['Berenjena',          '#7e22ce', '#3b0764'],
                                        ['Lila',               '#c084fc', '#9333ea'],
                                        ['Uva',                '#7c3aed', '#3b0764'],
                                        ['Púrpura real',       '#6b21a8', '#3b0764'],
                                    ],
                                    'Neutros / Sobrios' => [
                                        ['Slate elegante',     '#475569', '#1e293b'],
                                        ['Grafito',            '#374151', '#111827'],
                                        ['Carbón',             '#1f2937', '#030712'],
                                        ['Plata',              '#94a3b8', '#475569'],
                                        ['Niebla',             '#64748b', '#334155'],
                                        ['Pizarra',            '#52525b', '#18181b'],
                                        ['Topo',               '#78716c', '#292524'],
                                        ['Antracita',          '#27272a', '#09090b'],
                                    ],
                                ];
                            @endphp

                            <div class="mt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        <i class="fa-solid fa-swatchbook"></i> Presets de colores
                                    </div>
                                    <span class="text-[10px] text-slate-400">{{ collect($presetsAgrupados)->flatten(1)->count() }} combinaciones</span>
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-slate-50 max-h-72 overflow-y-auto">
                                    @foreach($presetsAgrupados as $grupo => $items)
                                        <div class="border-b last:border-b-0 border-slate-200 p-3">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">{{ $grupo }}</div>
                                            <div class="grid grid-cols-8 gap-1.5">
                                                @foreach($items as [$nombrePreset, $p, $s])
                                                    <button type="button"
                                                            @click="$wire.set('color_primario', '{{ $p }}'); $wire.set('color_secundario', '{{ $s }}');"
                                                            title="{{ $nombrePreset }} ({{ $p }} → {{ $s }})"
                                                            class="group relative h-9 rounded-md border-2 border-white shadow-sm ring-1 ring-slate-200 hover:ring-2 hover:ring-slate-500 hover:scale-110 transition-all"
                                                            style="background: linear-gradient(135deg, {{ $p }}, {{ $s }});">
                                                        @if($color_primario === $p && $color_secundario === $s)
                                                            <i class="fa-solid fa-check absolute inset-0 m-auto text-white text-xs drop-shadow"></i>
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1.5">
                                    <i class="fa-solid fa-circle-info"></i>
                                    Click en cualquier swatch lo aplica a los colores arriba. También puedes usar los pickers o pegar un hex personalizado.
                                </p>
                            </div>
                        </div>

                        {{-- Logo del tenant --}}
                        <div class="md:col-span-2" x-data="{
                            dataUrl: @entangle('logo_data_url').live,
                            nombre:  @entangle('logo_nombre').live,
                            error:   '',
                            pick(e) {
                                const file = e.target.files && e.target.files[0];
                                if (!file) return;
                                this.error = '';
                                if (!file.type.startsWith('image/')) { this.error = 'No es una imagen válida.'; return; }
                                if (file.size > 2 * 1024 * 1024) { this.error = 'Imagen muy grande (máx 2MB).'; return; }
                                const reader = new FileReader();
                                reader.onload = () => { this.dataUrl = reader.result; this.nombre = file.name; };
                                reader.onerror = () => { this.error = 'No se pudo leer el archivo.'; };
                                reader.readAsDataURL(file);
                            },
                            quitar() { this.dataUrl = null; this.nombre = null; this.error = ''; },
                        }">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                <i class="fa-solid fa-image text-brand"></i> Logo del tenant
                                <span class="text-xs text-slate-400 font-normal">(PNG/JPG/SVG/WebP, máx 2MB)</span>
                            </label>

                            <div class="flex items-center gap-4">
                                {{-- Vista previa --}}
                                <div class="h-20 w-20 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                                    <template x-if="dataUrl">
                                        <img :src="dataUrl" class="h-full w-full object-contain" alt="preview">
                                    </template>
                                    <template x-if="!dataUrl && '{{ $logo_url_actual }}'">
                                        <img src="{{ $logo_url_actual }}" class="h-full w-full object-contain" alt="logo actual">
                                    </template>
                                    <template x-if="!dataUrl && !'{{ $logo_url_actual }}'">
                                        <i class="fa-solid fa-image text-2xl text-slate-300"></i>
                                    </template>
                                </div>

                                <div class="flex-1">
                                    <input type="file"
                                           @change="pick($event)"
                                           accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif"
                                           class="block w-full text-sm text-slate-700
                                                  file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                                                  file:text-sm file:font-semibold
                                                  file:bg-brand-soft file:text-brand-secondary hover:file:bg-brand-soft-2 cursor-pointer">

                                    <div x-show="nombre" x-cloak class="flex items-center gap-2 mt-2 text-xs">
                                        <span class="text-emerald-600 font-semibold">
                                            <i class="fa-solid fa-check-circle"></i>
                                            <span x-text="nombre"></span>
                                        </span>
                                        <button type="button" @click="quitar()" class="text-rose-500 hover:text-rose-700">
                                            <i class="fa-solid fa-xmark"></i> Quitar
                                        </button>
                                    </div>

                                    <div x-show="error" x-cloak x-text="error" class="text-xs text-rose-600 mt-1"></div>

                                    @if($logo_url_actual)
                                        <div class="text-xs text-slate-500 mt-1" x-show="!dataUrl">
                                            Logo actual: <a href="{{ $logo_url_actual }}" target="_blank" class="text-brand-secondary underline">ver</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Favicon del tenant --}}
                        <div class="md:col-span-2" x-data="{
                            dataUrl: @entangle('favicon_data_url').live,
                            nombre:  @entangle('favicon_nombre').live,
                            error:   '',
                            pick(e) {
                                const file = e.target.files && e.target.files[0];
                                if (!file) return;
                                this.error = '';
                                if (!file.type.startsWith('image/') && !file.name.toLowerCase().endsWith('.ico')) {
                                    this.error = 'No es una imagen válida.'; return;
                                }
                                if (file.size > 1 * 1024 * 1024) { this.error = 'Favicon muy grande (máx 1MB).'; return; }
                                const reader = new FileReader();
                                reader.onload = () => { this.dataUrl = reader.result; this.nombre = file.name; };
                                reader.onerror = () => { this.error = 'No se pudo leer el archivo.'; };
                                reader.readAsDataURL(file);
                            },
                            quitar() { this.dataUrl = null; this.nombre = null; this.error = ''; },
                        }">
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">
                                <i class="fa-solid fa-globe text-brand"></i> Favicon del tenant
                                <span class="text-xs text-slate-400 font-normal">(ICO/PNG/SVG, ideal 32×32 o 64×64, máx 1MB)</span>
                            </label>

                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                                    <template x-if="dataUrl">
                                        <img :src="dataUrl" class="h-full w-full object-contain" alt="preview">
                                    </template>
                                    <template x-if="!dataUrl && '{{ $favicon_url_actual }}'">
                                        <img src="{{ $favicon_url_actual }}" class="h-full w-full object-contain" alt="favicon actual">
                                    </template>
                                    <template x-if="!dataUrl && !'{{ $favicon_url_actual }}'">
                                        <i class="fa-solid fa-globe text-lg text-slate-300"></i>
                                    </template>
                                </div>

                                <div class="flex-1">
                                    <input type="file"
                                           @change="pick($event)"
                                           accept="image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon,.ico"
                                           class="block w-full text-sm text-slate-700
                                                  file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                                                  file:text-sm file:font-semibold
                                                  file:bg-brand-soft file:text-brand-secondary hover:file:bg-brand-soft-2 cursor-pointer">

                                    <div x-show="nombre" x-cloak class="flex items-center gap-2 mt-2 text-xs">
                                        <span class="text-emerald-600 font-semibold">
                                            <i class="fa-solid fa-check-circle"></i>
                                            <span x-text="nombre"></span>
                                        </span>
                                        <button type="button" @click="quitar()" class="text-rose-500 hover:text-rose-700">
                                            <i class="fa-solid fa-xmark"></i> Quitar
                                        </button>
                                    </div>

                                    <div x-show="error" x-cloak x-text="error" class="text-xs text-rose-600 mt-1"></div>

                                    <p class="text-[11px] text-slate-400 mt-1.5">
                                        <i class="fa-solid fa-circle-info"></i>
                                        El favicon aparece en la pestaña del navegador. Si no subes uno, se usa el de la plataforma.
                                    </p>

                                    @if($favicon_url_actual)
                                        <div class="text-xs text-slate-500 mt-1" x-show="!dataUrl">
                                            Favicon actual: <a href="{{ $favicon_url_actual }}" target="_blank" class="text-brand-secondary underline">ver</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Trial vence</label>
                            <input type="date" wire:model="trial_ends_at" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Subscripción vence</label>
                            <input type="date" wire:model="subscription_ends_at" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20">
                        </div>
                    </div>

                    {{-- 🤖 OpenAI API Key por tenant --}}
                    <div class="rounded-xl border-2 border-violet-200 bg-violet-50/40 p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-robot text-violet-600 text-xl"></i>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm">OpenAI API Key propia (opcional)</h4>
                                <p class="text-xs text-slate-500">
                                    Si este tenant tiene su propia cuenta de OpenAI, pega aquí su key.
                                    Si queda vacío, usa la global (<code class="bg-white px-1 rounded">OPENAI_API_KEY</code> del .env de la plataforma).
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                sk-... <span class="text-slate-400 font-normal">(https://platform.openai.com/api-keys)</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="password"
                                       wire:model="openai_api_key"
                                       placeholder="sk-proj-... (déjalo vacío para usar la key global)"
                                       class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                       autocomplete="off">
                                <button type="button"
                                        wire:click="probarOpenaiKey"
                                        wire:loading.attr="disabled"
                                        wire:target="probarOpenaiKey"
                                        class="inline-flex items-center gap-2 rounded-xl bg-violet-500 hover:bg-violet-600 text-white font-semibold px-4 py-2 text-sm transition disabled:opacity-50">
                                    <span wire:loading.remove wire:target="probarOpenaiKey">
                                        <i class="fa-solid fa-vial"></i> Probar
                                    </span>
                                    <span wire:loading wire:target="probarOpenaiKey">
                                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                                    </span>
                                </button>
                            </div>
                            @error('openai_api_key')
                                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-[11px] text-slate-500 mt-2 flex items-center gap-1">
                                <i class="fa-solid fa-shield-halved text-slate-400"></i>
                                La key se guarda cifrada en BD y solo se usa para llamar a OpenAI en nombre de este tenant.
                                El costo de tokens se factura a la cuenta OpenAI dueña de la key.
                            </p>
                        </div>
                    </div>

                    {{-- 📱 WhatsApp del tenant: cada tenant tiene su PROPIA cuenta
                         TecnoByteApp + sus connection_ids. El sistema usa estas
                         credenciales para autenticarse y enviar/recibir mensajes
                         en nombre de este tenant. --}}
                    <div class="rounded-xl border-2 border-emerald-200 bg-emerald-50/40 p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-brands fa-whatsapp text-emerald-600 text-xl"></i>
                            <div class="flex-1">
                                <h4 class="font-bold text-slate-800 text-sm">WhatsApp (TecnoByteApp)</h4>
                                <p class="text-xs text-slate-500">
                                    Cuenta propia de este tenant en TecnoByteApp + IDs de las conexiones
                                    que tiene asignadas.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Email TecnoByteApp <span class="text-rose-500">*</span>
                                </label>
                                <input type="email" wire:model="whatsapp_email" placeholder="cliente@email.com"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Password TecnoByteApp <span class="text-rose-500">*</span>
                                </label>
                                <input type="password" wire:model="whatsapp_password" placeholder="••••••••"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                API URL <span class="text-slate-400 font-normal">(default: TecnoByteApp)</span>
                            </label>
                            <input type="text" wire:model="whatsapp_api_base_url" placeholder="https://wa-api.tecnobyteapp.com:1422"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                        </div>

                        <div x-data="{
                                ids: @entangle('whatsapp_connection_ids'),
                                toggle(id) {
                                    let arr = (this.ids || '').split(',').map(s => s.trim()).filter(Boolean);
                                    const idx = arr.indexOf(String(id));
                                    if (idx === -1) arr.push(String(id));
                                    else arr.splice(idx, 1);
                                    this.ids = arr.join(', ');
                                },
                                isChecked(id) {
                                    return (this.ids || '').split(',').map(s => s.trim()).includes(String(id));
                                },
                            }">
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-semibold text-slate-700">
                                    Conexiones WhatsApp <span class="text-rose-500">*</span>
                                </label>
                                <button type="button"
                                        wire:click="cargarConexionesTecnobyte"
                                        wire:loading.attr="disabled"
                                        wire:target="cargarConexionesTecnobyte"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-2.5 py-1 text-[11px] font-semibold transition disabled:opacity-50">
                                    <i class="fa-solid fa-rotate-right" wire:loading.class="fa-spin" wire:target="cargarConexionesTecnobyte"></i>
                                    <span wire:loading.remove wire:target="cargarConexionesTecnobyte">Cargar conexiones</span>
                                    <span wire:loading wire:target="cargarConexionesTecnobyte">Cargando…</span>
                                </button>
                            </div>

                            @if($error_conexiones)
                                <div class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700 mb-2">
                                    <i class="fa-solid fa-triangle-exclamation"></i> {{ $error_conexiones }}
                                </div>
                            @endif

                            @if(!empty($whatsapp_conexiones_disponibles))
                                <div class="rounded-xl border border-slate-200 bg-white max-h-56 overflow-y-auto">
                                    @foreach($whatsapp_conexiones_disponibles as $c)
                                        @php
                                            $bgEstado = match($c['status']) {
                                                'CONNECTED' => 'bg-emerald-100 text-emerald-700',
                                                'PAIRING','QRCODE','OPENING' => 'bg-amber-100 text-amber-700',
                                                'TIMEOUT','DISCONNECTED','NOT_CONNECTED' => 'bg-rose-100 text-rose-700',
                                                default => 'bg-slate-100 text-slate-600',
                                            };
                                        @endphp
                                        <label class="flex items-center gap-3 px-3 py-2.5 border-b last:border-b-0 border-slate-100 hover:bg-slate-50 cursor-pointer">
                                            <input type="checkbox"
                                                   :checked="isChecked({{ $c['id'] }})"
                                                   @change="toggle({{ $c['id'] }})"
                                                   class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono text-xs text-slate-500">#{{ $c['id'] }}</span>
                                                    <span class="font-semibold text-sm text-slate-800 truncate">{{ $c['name'] ?: 'Sin nombre' }}</span>
                                                    @if($c['isDefault'])
                                                        <span class="text-[10px] text-amber-600 font-bold">★</span>
                                                    @endif
                                                </div>
                                                <div class="text-[11px] text-slate-500">{{ $c['phoneNumber'] ?: '(sin número)' }}</div>
                                            </div>
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $bgEstado }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                                {{ $c['status'] }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1.5">
                                    <i class="fa-solid fa-circle-info"></i>
                                    Marca las conexiones que pertenecen a este tenant.
                                    Seleccionados: <strong x-text="(ids || '').split(',').filter(Boolean).length || 0"></strong>
                                </p>
                            @else
                                <input type="text" wire:model="whatsapp_connection_ids" placeholder="19, 28"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                                <p class="text-[11px] text-slate-500 mt-1">
                                    <i class="fa-solid fa-circle-info"></i>
                                    Llena email + password arriba y click en "Cargar conexiones" para verlas como checkboxes.
                                    O escribe los IDs separados por coma manualmente.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- 💳 Wompi por tenant (pagos en línea) --}}
                    <div class="rounded-xl border border-violet-200 bg-violet-50/40 p-4 space-y-3">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fa-solid fa-credit-card text-violet-600 text-xl"></i>
                            <div class="flex-1">
                                <h4 class="font-bold text-slate-800 text-sm">Pasarela de pagos · Wompi</h4>
                                <p class="text-[11px] text-slate-500">
                                    Cada tenant cobra a su propia cuenta de Wompi. Las llaves se guardan cifradas.
                                    <a href="https://docs.wompi.co/docs/colombia/inicio-rapido/" target="_blank"
                                       class="text-violet-700 underline">Cómo obtenerlas en Wompi</a>.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Modo</label>
                                <select wire:model="wompi_modo"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:ring-2 focus:ring-violet-100 bg-white">
                                    <option value="sandbox">Sandbox (pruebas)</option>
                                    <option value="produccion">Producción (real)</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                @if($wompi_public_key || $wompi_integrity_secret)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[11px] font-bold">
                                        <i class="fa-solid fa-circle-check"></i> Wompi configurado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-200 text-slate-600 px-3 py-1 text-[11px] font-bold">
                                        <i class="fa-solid fa-circle"></i> Sin configurar
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Llave pública <span class="text-slate-400 font-normal">(public_key)</span>
                                </label>
                                <input type="text" wire:model="wompi_public_key"
                                       placeholder="pub_test_... / pub_prod_..."
                                       autocomplete="off" spellcheck="false"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-violet-400 focus:ring-2 focus:ring-violet-100">
                                @error('wompi_public_key') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Llave privada <span class="text-slate-400 font-normal">(private_key)</span>
                                </label>
                                <input type="password" wire:model="wompi_private_key"
                                       placeholder="prv_test_... / prv_prod_..."
                                       autocomplete="off" spellcheck="false"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-violet-400 focus:ring-2 focus:ring-violet-100">
                                @error('wompi_private_key') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Secreto de eventos <span class="text-slate-400 font-normal">(events_secret)</span>
                                </label>
                                <input type="password" wire:model="wompi_events_secret"
                                       placeholder="prod_events_... / test_events_..."
                                       autocomplete="off" spellcheck="false"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-violet-400 focus:ring-2 focus:ring-violet-100">
                                <p class="text-[10px] text-slate-500 mt-1">Se usa para validar la firma de los webhooks de Wompi.</p>
                                @error('wompi_events_secret') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Secreto de integridad <span class="text-slate-400 font-normal">(integrity_secret)</span>
                                </label>
                                <input type="password" wire:model="wompi_integrity_secret"
                                       placeholder="prod_integrity_... / test_integrity_..."
                                       autocomplete="off" spellcheck="false"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:border-violet-400 focus:ring-2 focus:ring-violet-100">
                                <p class="text-[10px] text-slate-500 mt-1">Para firmar transacciones generadas por la plataforma.</p>
                                @error('wompi_integrity_secret') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- 🌐 URL del webhook por tenant --}}
                        @if($slug)
                            @php
                                $webhookUrl = url('/api/wompi/webhook/' . $slug);
                            @endphp
                            <div class="rounded-xl bg-white border-2 border-violet-300 p-3"
                                 x-data="{ copiado: false }">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fa-solid fa-link text-violet-600"></i>
                                    <span class="text-xs font-bold text-slate-800">URL de eventos para Wompi</span>
                                </div>
                                <p class="text-[11px] text-slate-600 mb-2">
                                    Pega esta URL en tu panel de Wompi en
                                    <a href="https://comercios.wompi.co" target="_blank" class="text-violet-700 underline font-semibold">comercios.wompi.co</a>
                                    → <strong>Configuración</strong> → <strong>Eventos</strong> → <strong>URL del webhook</strong>.
                                    Es única para este tenant y se genera automáticamente.
                                </p>
                                <div class="flex items-center gap-2">
                                    <input type="text" readonly value="{{ $webhookUrl }}"
                                           id="wompi-webhook-url-{{ $slug }}"
                                           class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-mono text-slate-700"
                                           onclick="this.select()">
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ $webhookUrl }}'); copiado = true; setTimeout(() => copiado = false, 1500)"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold px-3 py-2 transition flex-shrink-0">
                                        <template x-if="!copiado"><span><i class="fa-solid fa-copy"></i> Copiar</span></template>
                                        <template x-if="copiado"><span><i class="fa-solid fa-check"></i> ¡Copiado!</span></template>
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] text-amber-800">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Guarda primero el tenant para ver la URL del webhook (depende del slug).
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas internas</label>
                        <textarea wire:model="notas_internas" rows="2" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20"></textarea>
                    </div>

                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 border border-slate-200 p-3 cursor-pointer">
                        <input type="checkbox" wire:model="activo" class="mt-0.5 rounded border-slate-300 text-brand">
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
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-brand to-brand-secondary hover:from-brand-dark hover:to-brand-dark px-6 py-2.5 text-sm font-bold text-white shadow-lg">
                            <i class="fa-solid fa-floppy-disk"></i>
                            {{ $editandoId ? 'Actualizar tenant' : 'Crear tenant' }}
                        </button>
                    </div>
                </form>
            </div>
    </div>

    {{-- 🗑️ MODAL: ELIMINACIÓN DEFINITIVA con tipeo del nombre --}}
    @if($eliminarModalAbierto)
        <div wire:key="modal-tenant-eliminar"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(15,23,42,0.65); backdrop-filter: blur(6px);">
            <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden">

                <div class="px-6 py-4 border-b-2 border-rose-200 bg-gradient-to-r from-rose-50 to-rose-100 flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl bg-rose-500 text-white flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-rose-900">Eliminar tenant DEFINITIVAMENTE</h3>
                        <p class="text-xs text-rose-700">Esta acción NO se puede deshacer.</p>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    {{-- Lo que se va a eliminar --}}
                    <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-4">
                        <div class="text-xs font-bold uppercase text-rose-700 mb-2">Se eliminará TODO esto:</div>
                        <ul class="text-sm text-slate-700 space-y-1.5">
                            <li><i class="fa-solid fa-circle-xmark text-rose-500 w-5"></i> Tenant <strong>{{ $eliminarTenantNombre }}</strong></li>
                            <li><i class="fa-solid fa-circle-xmark text-rose-500 w-5"></i> Subdominio <code class="bg-white px-1.5 rounded text-xs">{{ $eliminarTenantSlug }}.tecnobyte360.com</code></li>
                            <li><i class="fa-solid fa-circle-xmark text-rose-500 w-5"></i> Registro DNS en Hostinger</li>
                            <li><i class="fa-solid fa-circle-xmark text-rose-500 w-5"></i> Configuración Nginx + certificado SSL</li>
                            <li><i class="fa-solid fa-circle-xmark text-rose-500 w-5"></i> TODOS los datos: pedidos, clientes, productos, usuarios, sedes, pagos, suscripciones</li>
                        </ul>
                    </div>

                    @if(!$eliminarCorriendo && !$eliminarLog)
                        {{-- Doble confirmación: tipeo del nombre --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Para confirmar, escribe el nombre exacto del tenant:
                            </label>
                            <div class="text-base font-bold text-rose-700 mb-2 select-all bg-rose-50 px-3 py-2 rounded-lg font-mono">{{ $eliminarTenantNombre }}</div>
                            <input type="text"
                                   wire:model.live="eliminarConfirmacion"
                                   placeholder="Tipea aquí el nombre exacto"
                                   class="w-full rounded-xl border-2 border-slate-200 focus:border-rose-500 focus:ring-2 focus:ring-rose-200 px-4 py-3 text-sm font-mono"
                                   autocomplete="off">

                            @if($eliminarConfirmacion && trim($eliminarConfirmacion) !== trim($eliminarTenantNombre))
                                <p class="text-xs text-rose-600 mt-1">
                                    <i class="fa-solid fa-xmark"></i> El texto no coincide.
                                </p>
                            @elseif($eliminarConfirmacion === $eliminarTenantNombre)
                                <p class="text-xs text-emerald-600 mt-1">
                                    <i class="fa-solid fa-check"></i> Confirmación correcta. Puedes eliminar.
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- Spinner / log --}}
                    @if($eliminarCorriendo)
                        <div class="flex items-center gap-2 text-rose-600 text-sm font-semibold bg-rose-50 px-3 py-2 rounded-lg">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Eliminando... no cierres esta ventana.
                        </div>
                    @endif

                    @if($eliminarLog)
                        <pre class="bg-slate-900 text-rose-200 text-xs p-4 rounded-lg max-h-72 overflow-auto whitespace-pre-wrap">{{ $eliminarLog }}</pre>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-between gap-2">
                    <button wire:click="cerrarModalEliminar"
                            class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold">
                        {{ $eliminarLog ? 'Cerrar' : 'Cancelar' }}
                    </button>

                    @if(!$eliminarLog)
                        @php
                            $puedeEliminar = trim((string) $eliminarConfirmacion) === trim((string) $eliminarTenantNombre)
                                             && !$eliminarCorriendo;
                        @endphp
                        <button wire:click="confirmarEliminacion"
                                wire:loading.attr="disabled"
                                wire:target="confirmarEliminacion"
                                {{ $puedeEliminar ? '' : 'disabled' }}
                                class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-rose-500 hover:bg-rose-600 text-white text-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-trash-can"></i>
                            ELIMINAR DEFINITIVAMENTE
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: log del setup de subdominio (con polling reactivo) --}}
    @if($subdomModalAbierto)
        <div wire:key="modal-tenant-subdom"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
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
