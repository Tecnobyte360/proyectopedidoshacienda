@props([
    'tenants'  => null,      // colección {id, nombre}; si es null, se carga de Tenant::all()
    'selected' => null,      // tenant_id seleccionado (null = no filtrar)
    'model'    => null,      // si lo pasas → modo filtro Livewire (wire:model)
    'mode'     => 'auto',    // auto | filter | as_tenant
])

@php
    $u = auth()->user();
    $esSuperAdmin = $u?->hasRole('super-admin') ?? false;

    if ($tenants === null) {
        $tenants = \App\Models\Tenant::query()
            ->withoutGlobalScopes()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    // Modo: si pasaron $model → filter (Livewire), si no → as_tenant (query string)
    $modoReal = $mode === 'auto' ? ($model ? 'filter' : 'as_tenant') : $mode;
    $seleccionadoActual = $modoReal === 'as_tenant'
        ? (request()->query('as_tenant') ?: '')
        : ($selected ?: '');
@endphp

@if($esSuperAdmin && count($tenants) > 0)
    <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2"
         x-data="{
             cambiar(value) {
                 // Construimos la URL desde window.location PERO ignoramos si el path es /livewire/update
                 // (puede pasar si este componente se re-renderiza durante un Livewire poll).
                 const loc = window.location;
                 const path = loc.pathname.includes('/livewire/') ? document.documentURI.split('?')[0] : loc.pathname;
                 const u = new URL(path, loc.origin);
                 // Preservar otros query params actuales
                 const currentSearch = new URLSearchParams(loc.search);
                 currentSearch.delete('as_tenant');
                 currentSearch.forEach((v, k) => u.searchParams.set(k, v));
                 if (value) u.searchParams.set('as_tenant', value);
                 window.location.assign(u.toString());
             }
         }">
        <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 whitespace-nowrap">
            <i class="fa-solid fa-eye text-[10px] text-slate-400"></i>
            Ver datos de
        </span>

        @if($modoReal === 'filter')
            <select wire:model.live="{{ $model }}"
                    class="border-0 bg-transparent text-sm font-semibold text-slate-800 focus:ring-0 pr-7 pl-1 cursor-pointer">
                <option value="">Todos los tenants</option>
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                @endforeach
            </select>
        @else
            <select x-on:change="cambiar($event.target.value)"
                    class="border-0 bg-transparent text-sm font-semibold text-slate-800 focus:ring-0 pr-7 pl-1 cursor-pointer">
                <option value="" @if(!$seleccionadoActual) selected @endif>— Elegí un tenant —</option>
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}" @if($seleccionadoActual == $t->id) selected @endif>{{ $t->nombre }}</option>
                @endforeach
            </select>
        @endif
    </div>
@endif
