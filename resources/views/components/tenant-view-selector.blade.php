@props([
    'tenants'  => [],            // colección {id, nombre}
    'selected' => null,          // tenant_id seleccionado (null = todos)
    'model'    => 'tenantViewId',// propiedad Livewire a bindear
])

@php
    $esSuperAdmin = auth()->user()?->hasRole('super-admin') ?? false;
@endphp

@if($esSuperAdmin && count($tenants) > 0)
    <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
        <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-500">
            <i class="fa-solid fa-building text-[10px] text-slate-400"></i>
            Tenant
        </span>
        <select wire:model.live="{{ $model }}"
                class="border-0 bg-transparent text-sm font-semibold text-slate-800 focus:ring-0 pr-7 pl-1 cursor-pointer">
            <option value="">Todos los tenants</option>
            @foreach($tenants as $t)
                <option value="{{ $t->id }}">{{ $t->nombre }}</option>
            @endforeach
        </select>
    </div>
@endif
