<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Pedidos | Tecnobyte</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    {{-- Leaflet + plugins (mapas para zonas) --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js" defer></script>
    <script src="https://unpkg.com/@turf/turf@7/turf.min.js" defer></script>

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

    {{-- SIDEBAR --}}
    <div x-data x-show="!$store.ui.fullscreen" x-transition.opacity.duration.200ms>
        <livewire:layouts.sidebar />
    </div>

    {{-- TOPBAR --}}
    <div x-data x-show="!$store.ui.fullscreen" x-transition.opacity.duration.200ms>
        <livewire:layouts.topbar />
    </div>

    {{-- CONTENIDO --}}
    <main x-data class="min-h-screen transition-all duration-300"
          :class="$store.ui.fullscreen ? 'pt-0 pl-0' : 'pt-20 md:pl-64'">
        {{ $slot }}
    </main>

    {{-- BOTÓN FLOTANTE PARA SALIR DE FULLSCREEN --}}
    <button x-data
            x-show="$store.ui.fullscreen"
            x-transition
            @click="$store.ui.fullscreen = false"
            title="Salir de pantalla completa (ESC)"
            class="fixed top-4 right-4 z-[60] flex h-11 w-11 items-center justify-center rounded-full bg-slate-900 text-white shadow-2xl hover:bg-slate-700 transition"
            style="display: none;">
        <i class="fa-solid fa-compress"></i>
    </button>

    {{-- TOAST NOTIFICATIONS --}}
    <div x-data="{ messages: [] }"
         x-init="
            window.addEventListener('notify', e => {
                const id = Date.now();
                messages.push({ id, ...e.detail[0] });
                setTimeout(() => messages = messages.filter(m => m.id !== id), 4000);
            });
            Livewire.on('notify', payload => {
                const id = Date.now();
                const data = Array.isArray(payload) ? payload[0] : payload;
                messages.push({ id, ...data });
                setTimeout(() => messages = messages.filter(m => m.id !== id), 4000);
            });
         "
         class="fixed top-24 right-6 z-[100] space-y-2">
        <template x-for="m in messages" :key="m.id">
            <div class="rounded-xl px-5 py-3 shadow-2xl text-sm font-medium text-white min-w-[260px]"
                 :class="{
                    'bg-green-500': m.type === 'success',
                    'bg-red-500': m.type === 'error',
                    'bg-amber-500': m.type === 'warning',
                    'bg-slate-700': m.type === 'info' || !m.type,
                 }"
                 x-text="m.message">
            </div>
        </template>
    </div>

    @livewireScripts

    {{-- Alpine ya viene incluido en Livewire 4 — NO cargar el CDN aparte (duplicaría y rompería wire:click) --}}

    <script>
        // Store global de UI (modo pantalla completa)
        document.addEventListener('alpine:init', () => {
            Alpine.store('ui', {
                fullscreen: false,
                toggle() { this.fullscreen = !this.fullscreen; },
            });
        });

        // ESC sale del modo fullscreen
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && window.Alpine?.store('ui')?.fullscreen) {
                window.Alpine.store('ui').fullscreen = false;
            }
        });

        let audioUnlocked = false;

        document.addEventListener('click', function () {
            if (!audioUnlocked) {
                const audio = document.getElementById('new-order-sound');
                if (audio) {
                    audio.play().then(() => {
                        audio.pause();
                        audio.currentTime = 0;
                        audioUnlocked = true;
                    }).catch(() => {});
                }
            }
        });

        document.addEventListener('livewire:initialized', () => {
            Livewire.on('pedidoActualizado', () => {
                const audio = document.getElementById('new-order-sound');
                if (audio && audioUnlocked) {
                    audio.currentTime = 0;
                    audio.play().catch(() => {});
                }
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
