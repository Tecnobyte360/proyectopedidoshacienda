<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Pedidos | Tecnobyte</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Config de Reverb leída por resources/js/app.js (auto-detección con override) --}}
    <meta name="reverb-host"   content="{{ env('REVERB_PUBLIC_HOST', '') }}">
    <meta name="reverb-port"   content="{{ env('REVERB_PUBLIC_PORT', '') }}">
    <meta name="reverb-scheme" content="{{ env('REVERB_PUBLIC_SCHEME', '') }}">

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
    <style>[x-cloak] { display: none !important; }</style>

    {{-- Fullscreen mode — toggle simple con body class + localStorage (sin Alpine store).
         Cualquier botón que llame a window.toggleFullscreen() activa/desactiva el modo. --}}
    <script>
        (function () {
            // Restaurar estado al cargar
            if (localStorage.getItem('fullscreen') === '1') {
                document.documentElement.classList.add('preload-fullscreen');
                document.addEventListener('DOMContentLoaded', () => {
                    document.body.classList.add('is-fullscreen');
                });
            }

            window.toggleFullscreen = function () {
                const on = !document.body.classList.contains('is-fullscreen');
                document.body.classList.toggle('is-fullscreen', on);
                localStorage.setItem('fullscreen', on ? '1' : '0');
            };

            window.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.body.classList.contains('is-fullscreen')) {
                    document.body.classList.remove('is-fullscreen');
                    localStorage.setItem('fullscreen', '0');
                }
            });
        })();
    </script>

    {{-- CSS para el modo fullscreen (oculta sidebar, topbar, ajusta padding) --}}
    <style>
        body.is-fullscreen aside.app-sidebar,
        body.is-fullscreen header.app-topbar { display: none !important; }
        body.is-fullscreen main             { padding-top: 0 !important; padding-left: 0 !important; }
        body.is-fullscreen #btn-exit-fullscreen { display: flex !important; }
    </style>

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

    {{-- SIDEBAR (Livewire — siempre presente, oculto solo por CSS según body class) --}}
    <livewire:layouts.sidebar />

    {{-- TOPBAR (Livewire — siempre presente) --}}
    <livewire:layouts.topbar />

    {{-- CONTENIDO --}}
    <main class="min-h-screen pt-20 lg:pl-64 transition-all duration-300">
        {{ $slot }}
    </main>

    {{-- BOTÓN FLOTANTE PARA SALIR DE FULLSCREEN (controlado por body class) --}}
    <button id="btn-exit-fullscreen"
            type="button"
            onclick="document.body.classList.remove('is-fullscreen'); localStorage.setItem('fullscreen','0');"
            title="Salir de pantalla completa (ESC)"
            class="hidden fixed top-4 right-4 z-[60] h-11 w-11 items-center justify-center rounded-full bg-slate-900 text-white shadow-2xl hover:bg-slate-700 transition">
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

    {{-- ✨ SWEETALERT 2 — notificaciones bonitas --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
        .swal2-popup {
            border-radius: 20px !important;
            padding: 1.75rem !important;
            font-family: 'Inter', system-ui, sans-serif !important;
        }
        .swal2-title {
            font-size: 1.15rem !important;
            font-weight: 800 !important;
            color: #1e293b !important;
        }
        .swal2-html-container {
            font-size: 0.9rem !important;
            color: #475569 !important;
        }
        .swal2-toast {
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.15) !important;
            border-radius: 16px !important;
        }
        .swal2-confirm {
            background: linear-gradient(135deg, #d68643, #a85f24) !important;
            font-weight: 700 !important;
            border-radius: 12px !important;
            padding: 10px 24px !important;
            box-shadow: 0 4px 12px rgba(214, 134, 67, 0.3) !important;
        }
        .swal2-cancel {
            background: #e2e8f0 !important;
            color: #475569 !important;
            font-weight: 600 !important;
            border-radius: 12px !important;
            padding: 10px 24px !important;
        }
        .swal2-icon.swal2-success { border-color: #10b981 !important; }
        .swal2-icon.swal2-success [class^='swal2-success-line'] { background: #10b981 !important; }
        .swal2-icon.swal2-success .swal2-success-ring { border-color: rgba(16, 185, 129, 0.3) !important; }
        .swal2-icon.swal2-error { border-color: #ef4444 !important; }
        .swal2-icon.swal2-error [class^='swal2-x-mark-line'] { background: #ef4444 !important; }
    </style>
    <script>
        (function () {
            if (typeof Swal === 'undefined') return;

            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                customClass: { popup: 'swal2-toast' },
                didOpen: (t) => {
                    t.addEventListener('mouseenter', Swal.stopTimer);
                    t.addEventListener('mouseleave', Swal.resumeTimer);
                },
            });

            window.showNotify = function (payload) {
                const data   = Array.isArray(payload) ? payload[0] : payload;
                const type   = (data && data.type)    || 'info';
                const msg    = (data && data.message) || '';
                const title  = (data && data.title)   || '';
                const iconMap = {
                    success: 'success', error: 'error',
                    warning: 'warning', info: 'info', question: 'question'
                };
                toast.fire({
                    icon: iconMap[type] || 'info',
                    title: title || msg,
                    text:  title ? msg : '',
                });
            };

            // Confirmar acción (reemplazo de wire:confirm nativo)
            window.showConfirm = function (opts) {
                return Swal.fire({
                    title: opts.title || '¿Estás seguro?',
                    html:  opts.message || '',
                    icon:  opts.icon || 'question',
                    showCancelButton: true,
                    confirmButtonText: opts.confirmText || 'Sí, confirmar',
                    cancelButtonText:  opts.cancelText  || 'Cancelar',
                    reverseButtons: true,
                });
            };

            document.addEventListener('livewire:initialized', () => {
                Livewire.on('notify', payload => window.showNotify(payload));
            });
            window.addEventListener('notify', e => window.showNotify(e.detail));
        })();
    </script>

    {{-- 🎭 Función global: salir de impersonación con reload garantizado --}}
    <script>
        window.salirDeImpersonacion = function (btn) {
            try {
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saliendo...';
                }
                var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                var url   = "/admin/dejar-impersonar";
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                }).finally(function () {
                    window.location.replace('/admin/tenants?_=' + Date.now());
                });
            } catch (e) {
                console.error('salirDeImpersonacion error', e);
                window.location.replace('/admin/tenants?_=' + Date.now());
            }
        };
    </script>

    @livewireScripts

    {{-- Alpine ya viene incluido en Livewire 3 — NO cargar el CDN aparte (duplicaría y rompería wire:click) --}}

    {{-- El sonido de "nuevo pedido" ahora se genera con Web Audio API en
         /livewire/pedidos/index.blade.php (sin dependencia de archivos mp3). --}}

    @stack('scripts')
</body>
</html>
