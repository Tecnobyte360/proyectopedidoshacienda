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

    {{-- Store global Alpine — registrado en el <head> ANTES de cualquier script Livewire/Alpine.
         Esto garantiza que el listener de 'alpine:init' esté presente cuando Alpine arranque. --}}
    <script>
        (function () {
            const registrarStore = () => {
                if (!window.Alpine) return false;
                if (Alpine.store && Alpine.store('ui')) return true;
                if (Alpine.store) {
                    Alpine.store('ui', {
                        fullscreen: false,
                        toggle() { this.fullscreen = !this.fullscreen; },
                    });
                    return true;
                }
                return false;
            };

            // 1) Antes de que Alpine arranque
            document.addEventListener('alpine:init', registrarStore);

            // 2) Después de que Alpine ya arrancó (fallback)
            document.addEventListener('alpine:initialized', registrarStore);

            // 3) Reintento por si el evento ya pasó cuando este script corrió
            const intentar = () => { if (!registrarStore()) setTimeout(intentar, 50); };
            if (document.readyState !== 'loading') intentar();
            else document.addEventListener('DOMContentLoaded', intentar);

            // ESC sale del fullscreen
            window.addEventListener('keydown', (e) => {
                try {
                    if (e.key === 'Escape' && window.Alpine?.store('ui')?.fullscreen) {
                        window.Alpine.store('ui').fullscreen = false;
                    }
                } catch (_) {}
            });
        })();
    </script>

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
    <div x-data x-show="!($store.ui?.fullscreen)" x-transition.opacity.duration.200ms>
        <livewire:layouts.sidebar />
    </div>

    {{-- TOPBAR --}}
    <div x-data x-show="!($store.ui?.fullscreen)" x-transition.opacity.duration.200ms>
        <livewire:layouts.topbar />
    </div>

    {{-- CONTENIDO --}}
    <main x-data class="min-h-screen transition-all duration-300"
          :class="$store.ui?.fullscreen ? 'pt-0 pl-0' : 'pt-20 md:pl-64'">
        {{ $slot }}
    </main>

    {{-- BOTÓN FLOTANTE PARA SALIR DE FULLSCREEN --}}
    <button x-data
            x-show="$store.ui?.fullscreen"
            x-transition
            @click="$store.ui && ($store.ui.fullscreen = false)"
            title="Salir de pantalla completa (ESC)"
            class="fixed top-4 right-4 z-[60] flex h-11 w-11 items-center justify-center rounded-full bg-slate-900 text-white shadow-2xl hover:bg-slate-700 transition"
            style="display: none;">
        <i class="fa-solid fa-compress"></i>
    </button>

    {{-- ╔═══ MODAL DE CONFIRMACIÓN GLOBAL ═══╗
         Uso desde cualquier botón:
         <button @click.prevent="$dispatch('confirm-show', {
             message: '¿Eliminar este registro?',
             confirmText: 'Sí, eliminar',
             type: 'danger',
             onConfirm: () => $wire.eliminar(123),
         })">Eliminar</button>
    --}}
    <div x-data="{
            open: false,
            title: 'Confirmar acción',
            message: '',
            confirmText: 'Aceptar',
            cancelText: 'Cancelar',
            type: 'primary',
            callback: null,
            init() {
                window.addEventListener('confirm-show', (e) => {
                    this.title       = e.detail.title       ?? 'Confirmar acción';
                    this.message     = e.detail.message     ?? '¿Estás seguro?';
                    this.confirmText = e.detail.confirmText ?? 'Aceptar';
                    this.cancelText  = e.detail.cancelText  ?? 'Cancelar';
                    this.type        = e.detail.type        ?? 'primary';
                    this.callback    = e.detail.onConfirm   ?? null;
                    this.open = true;
                });
                window.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.open) this.cancel();
                });
            },
            confirm() {
                this.open = false;
                if (typeof this.callback === 'function') this.callback();
                this.callback = null;
            },
            cancel() { this.open = false; this.callback = null; }
         }"
         x-show="open"
         x-transition.opacity.duration.150ms
         @click.self="cancel()"
         class="fixed inset-0 z-[200] flex items-center justify-center p-4"
         style="display: none; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);">

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">

            {{-- Header con ícono según tipo --}}
            <div class="flex items-start gap-4 px-6 pt-6 pb-2">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                     :class="{
                        'bg-amber-100 text-amber-600': type === 'primary',
                        'bg-rose-100 text-rose-600':   type === 'danger',
                        'bg-emerald-100 text-emerald-600': type === 'success',
                        'bg-blue-100 text-blue-600':   type === 'info',
                     }">
                    <i class="fa-solid"
                       :class="{
                           'fa-circle-question': type === 'primary',
                           'fa-triangle-exclamation': type === 'danger',
                           'fa-circle-check': type === 'success',
                           'fa-circle-info': type === 'info',
                       }"></i>
                </div>
                <div class="flex-1 pt-1">
                    <h3 class="text-base font-bold text-slate-800" x-text="title"></h3>
                    <p class="mt-1 text-sm text-slate-600 leading-relaxed" x-text="message"></p>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex flex-col-reverse sm:flex-row gap-2 px-6 py-4 bg-slate-50 border-t border-slate-100">
                <button type="button" @click="cancel()"
                        class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    <span x-text="cancelText"></span>
                </button>
                <button type="button" @click="confirm()"
                        :class="{
                            'bg-[#d68643] hover:bg-[#c97a36]': type === 'primary',
                            'bg-rose-500 hover:bg-rose-600':    type === 'danger',
                            'bg-emerald-500 hover:bg-emerald-600': type === 'success',
                            'bg-blue-500 hover:bg-blue-600':    type === 'info',
                        }"
                        class="flex-1 rounded-xl px-4 py-2.5 text-sm font-bold text-white transition shadow">
                    <span x-text="confirmText"></span>
                </button>
            </div>
        </div>
    </div>

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
