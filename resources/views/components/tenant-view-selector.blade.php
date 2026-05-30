@props([
    'tenants'  => null,      // colección {id, nombre}; si es null, se carga de Tenant::all()
    'selected' => null,      // tenant_id seleccionado (null = todos / actual)
    'model'    => null,      // si lo pasas → modo filtro Livewire (wire:model)
    'mode'     => 'auto',    // auto | filter | impersonate
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

    // Modo: si pasaron $model → filter (Livewire), si no → impersonate (form POST)
    $modoReal = $mode === 'auto' ? ($model ? 'filter' : 'impersonate') : $mode;
    $seleccionadoActual = $modoReal === 'impersonate'
        ? (session('tenant_imitado_id') ?: '')
        : ($selected ?: '');
@endphp

@if($esSuperAdmin && count($tenants) > 0)
    <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
        <span class="text-[10px] uppercase tracking-wider font-semibold text-slate-500 whitespace-nowrap">
            <i class="fa-solid fa-building text-[10px] text-slate-400"></i>
            Tenant
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
            {{-- Modo impersonate: form POST que cambia tenant_imitado_id y recarga la URL actual --}}
            <form method="POST" action="{{ route('admin.ver-tenant') }}" class="inline-flex">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                <select name="tenant_id"
                        onchange="this.form.submit()"
                        class="border-0 bg-transparent text-sm font-semibold text-slate-800 focus:ring-0 pr-7 pl-1 cursor-pointer">
                    <option value="" @if(!$seleccionadoActual) selected @endif>— Elegí un tenant —</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}" @if($seleccionadoActual == $t->id) selected @endif>{{ $t->nombre }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>
@endif
