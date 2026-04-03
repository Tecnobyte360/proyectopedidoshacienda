<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Panel de Pedidos – Tiempo Real + IA</title>

    <!-- Token CSRF -->
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-2xl rounded-2xl w-full max-w-3xl p-6 md:p-8 space-y-6 border border-slate-200">

        <!-- Encabezado -->
        <header class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800">
                    Panel de Pedidos Inteligente
                </h1>
                <p class="text-sm text-slate-500 mt-1">
                    Registra pedidos y recíbelos en tiempo real con análisis automático de IA.
                </p>
            </div>

            <div class="flex flex-col items-end gap-1">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    WebSocket activo
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-50 text-violet-700 text-xs font-semibold">
                    🤖 IA conectada
                </span>
            </div>
        </header>

        <!-- Tarjeta: Nuevo pedido -->
        <section class="bg-slate-50 border border-slate-200 rounded-xl p-4 md:p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-800">Registrar nuevo pedido</h2>
                <span class="text-xs text-slate-500">
                    La IA analizará el pedido y responderá automáticamente
                </span>
            </div>

            <form id="message-form" class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <label for="message-input" class="block text-xs font-medium text-slate-600 mb-1">
                        Detalle del pedido
                    </label>
                    <input
                        id="message-input"
                        type="text"
                        name="message"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
                        placeholder="Ej: Cliente 1023 - 2 Kg de costilla para asar"
                    />
                </div>

                <div class="flex items-end">
                    <button
                        type="submit"
                        class="w-full md:w-auto px-5 py-2.5 rounded-lg bg-violet-600 text-white text-sm font-semibold shadow-sm hover:bg-violet-700 active:bg-violet-800 transition"
                    >
                        Crear pedido
                    </button>
                </div>
            </form>
        </section>

        <!-- Tarjeta: Pedidos en tiempo real -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-800">
                    Conversación en tiempo real
                </h2>
                <span class="text-xs text-slate-500">
                    Cliente vs Asistente IA
                </span>
            </div>

            <div class="border border-slate-200 rounded-xl bg-slate-50/60">

                <div class="flex items-center justify-between px-4 py-2 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                    <span>Pedidos y respuestas</span>
                </div>

                <div class="p-3 h-72 overflow-y-auto text-sm space-y-1" id="messages">
                    <p class="text-slate-400 text-xs">
                        Esperando pedidos inteligentes...
                    </p>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
