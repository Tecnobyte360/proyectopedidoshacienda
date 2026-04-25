<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Seguimiento del pedido' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#fbe9d7]/30 via-white to-[#f5d4ad]/20">

    {{-- Brand del tenant en la cabecera (si lo conocemos) --}}
    @php
        $tenant = isset($pedido) && $pedido?->tenant_id
            ? \App\Models\Tenant::withoutGlobalScopes()->find($pedido->tenant_id)
            : null;
    @endphp

    <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-3xl mx-auto px-4 py-4 flex items-center gap-3">
            @if($tenant?->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->nombre }}" class="h-10 w-10 rounded-xl object-contain bg-white border border-slate-100">
            @else
                <div class="h-10 w-10 rounded-xl flex items-center justify-center text-white text-lg"
                     style="background: linear-gradient(135deg, {{ $tenant?->color_primario ?: '#d68643' }}, {{ $tenant?->color_secundario ?: '#a85f24' }});">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
            @endif
            <div class="flex-1 min-w-0">
                <h1 class="font-extrabold text-slate-800 truncate">{{ $tenant?->nombre ?? 'Seguimiento de pedido' }}</h1>
                <p class="text-xs text-slate-500">Estado de tu pedido en tiempo real</p>
            </div>
        </div>
    </header>

    {{-- Contenido principal --}}
    <main class="max-w-3xl mx-auto px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="max-w-3xl mx-auto px-4 py-6 text-center text-xs text-slate-400">
        © {{ date('Y') }} {{ $tenant?->nombre ?? 'TecnoByte360' }} · Powered by TecnoByte360
    </footer>

    @livewireScripts
</body>
</html>
