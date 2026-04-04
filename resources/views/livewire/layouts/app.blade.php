<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Pedidos | Tecnobyte</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        html,
        body {
            font-family: 'Inter', sans-serif;
            background: #f7f7f9;
            margin: 0;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-[#f7f7f9] text-slate-800 antialiased">

    {{-- TOPBAR --}}
    <livewire:layouts.topbar />

    {{-- CONTENIDO --}}
  <main class="w-full pt-24">
    {{ $slot }}
</main>

    @livewireScripts
    @stack('scripts')
</body>
</html>