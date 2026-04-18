import "./bootstrap";

import Echo from "laravel-echo";
import Pusher from "pusher-js";
import "./seguimiento-pedido";
window.Pusher = Pusher;

// ─────────────────────────────────────────────────────────────────────────────
// CONFIG REVERB — auto-detección
//
// 1. En producción HTTPS (cualquier dominio que NO sea localhost):
//    usa el mismo host por wss en puerto 443. Requiere nginx proxy de /app y /apps.
//
// 2. En desarrollo local con `php artisan reverb:start` (puerto 8080):
//    usa ws://127.0.0.1:8080.
//
// 3. Para casos custom (puerto distinto, etc), define en index.html:
//    <meta name="reverb-host" content="...">
//    <meta name="reverb-port" content="...">
//    <meta name="reverb-scheme" content="http|https">
// ─────────────────────────────────────────────────────────────────────────────

// Lee meta tag. Retorna undefined si está ausente O si está vacío, para que
// el fallback con `??` siempre aplique en vez de quedarse con "".
const meta = (name) => {
    const v = document.querySelector(`meta[name="reverb-${name}"]`)?.content;
    return v && v.trim() !== "" ? v.trim() : undefined;
};

const host = window.location.hostname;
const isLocal = host === "localhost" || host === "127.0.0.1";

const reverbHost = meta("host") ?? (isLocal ? "127.0.0.1" : host);
const reverbPort = parseInt(
    meta("port") ?? (isLocal ? 8080 : 443),
    10,
);
const reverbScheme =
    meta("scheme") ?? (isLocal ? "http" : "https");
const useTLS = reverbScheme === "https";

console.log("🔌 Reverb config:", { host: reverbHost, port: reverbPort, tls: useTLS });

window.Echo = new Echo({
    broadcaster: "reverb",
    key: "app-key",
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: useTLS,
    enabledTransports: useTLS ? ["wss"] : ["ws"],
    disableStats: true,
});

document.addEventListener("DOMContentLoaded", () => {
    initChatDemo();
    initPedidosRealtime();
});

// ─────────────────────────────────────────────────────────────────────────────
// CHAT DEMO
// ─────────────────────────────────────────────────────────────────────────────
function initChatDemo() {
    const form = document.getElementById("message-form");
    const input = document.getElementById("message-input");
    const box = document.getElementById("messages");

    if (!form || !input || !box) return;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        try {
            const response = await fetch("/send-message", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
                body: JSON.stringify({ message: text }),
            });

            if (!response.ok) {
                console.error(
                    "Error en respuesta:",
                    response.status,
                    await response.text(),
                );
            }
        } catch (error) {
            console.error("Error enviando mensaje:", error);
        }

        input.value = "";
    });

    if (!window.Echo) {
        console.warn("Echo no está disponible para chat demo");
        return;
    }

    window.Echo.channel("demo-channel").listen("MessageSent", (e) => {
        if (box.innerHTML.includes("Esperando mensajes")) box.innerHTML = "";

        const wrapper = document.createElement("div");
        const isAi = (e.message || "").startsWith("Asistente IA:");

        wrapper.className = isAi
            ? "flex justify-start mb-2"
            : "flex justify-end mb-2";
        wrapper.innerHTML = `
            <div class="max-w-xs px-4 py-2 rounded-2xl shadow ${isAi ? "bg-violet-100 text-violet-900" : "bg-emerald-100 text-emerald-900"}">
                <span class="block text-xs font-semibold mb-1">${isAi ? "🤖 IA" : "🛒 Cliente"}</span>
                <span class="text-sm">
                    ${(e.message || "").replace(/^Asistente IA:\s*/, "").replace(/^Cliente:\s*/, "")}
                </span>
            </div>
        `;

        box.appendChild(wrapper);
        box.scrollTop = box.scrollHeight;
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// PEDIDOS EN TIEMPO REAL
//
// Livewire 3 escucha directamente los eventos de Echo via
//   `echo:pedidos,.pedido.confirmado` y `echo:pedidos,.pedido.actualizado`
// en el componente Pedidos/Index.php — eso se encarga de re-renderizar la
// vista completa (cards + tabla) cuando llega un evento.
//
// Este bloque JS es COMPLEMENTARIO: solo da feedback inmediato — beep y toast.
// ─────────────────────────────────────────────────────────────────────────────
function initPedidosRealtime() {
    if (!window.Echo) {
        console.warn("Echo no disponible — sin tiempo real");
        return;
    }

    const orderSound = document.getElementById("new-order-sound");

    function playNewOrderSound() {
        if (!orderSound) return;
        try {
            orderSound.currentTime = 0;
            orderSound.play().catch(() => {});
        } catch (_) {}
    }

    function toast(msg, type = "success") {
        window.dispatchEvent(
            new CustomEvent("notify", {
                detail: [{ type, message: msg }],
            }),
        );
    }

    const LABEL = {
        nuevo: "Nuevo",
        en_preparacion: "En proceso",
        repartidor_en_camino: "Despachado",
        entregado: "Entregado",
        cancelado: "Cancelado",
    };

    // ── Resaltado de pedidos recién llegados ──────────────────────────────────
    // Set con los IDs recientes. Como Livewire re-renderiza de forma asíncrona
    // después de recibir el broadcast, aplicamos la clase con varios reintentos
    // (100ms, 400ms, 900ms, 1500ms, 2500ms) para atrapar el momento en que la
    // fila/card ya esté en el DOM.
    const pedidosRecientes = new Set();
    const DURACION_HIGHLIGHT_MS = 6000;
    const DELAYS_REINTENTO = [50, 150, 400, 800, 1500, 2500, 4000];

    function aplicarResaltado() {
        const nodos = document.querySelectorAll("[data-pedido-id]");
        nodos.forEach((el) => {
            const id = parseInt(el.dataset.pedidoId, 10);
            if (pedidosRecientes.has(id)) {
                if (!el.classList.contains("new-order-highlight")) {
                    el.classList.add("new-order-highlight");
                    console.log(`✨ Resaltando pedido #${id}`, el);
                }
            } else {
                el.classList.remove("new-order-highlight");
            }
        });
    }

    function marcarPedidoReciente(id) {
        if (!id) return;
        pedidosRecientes.add(id);

        // Reintentos escalonados para garantizar que la fila esté en el DOM
        DELAYS_REINTENTO.forEach((ms) => setTimeout(aplicarResaltado, ms));

        // Auto-limpiar después de la duración del highlight
        setTimeout(() => {
            pedidosRecientes.delete(id);
            aplicarResaltado();
        }, DURACION_HIGHLIGHT_MS);
    }

    // Re-aplicar después de cualquier re-render de Livewire.
    // Esto captura el caso cuando Livewire recibe el broadcast, refresca la
    // lista, y necesitamos repintar la clase en el nuevo DOM.
    function engancharLivewire() {
        if (!window.Livewire?.hook) return false;
        Livewire.hook("morph.updated", () => {
            setTimeout(aplicarResaltado, 20);
        });
        Livewire.hook("commit", ({ respond }) => {
            respond(() => setTimeout(aplicarResaltado, 20));
        });
        console.log("🔗 Hooks de Livewire registrados");
        return true;
    }

    if (!engancharLivewire()) {
        document.addEventListener("livewire:init", engancharLivewire);
    }

    window.Echo.channel("pedidos")
        .listen(".pedido.confirmado", (event) => {
            console.log("📦 pedido.confirmado", event);
            playNewOrderSound();
            toast(
                `🔥 Nuevo pedido de ${event.cliente_nombre ?? "cliente"}`,
                "success",
            );
            marcarPedidoReciente(event.id);
        })
        .listen(".pedido.actualizado", (event) => {
            console.log("🔄 pedido.actualizado", event);
            const estado = LABEL[event.estado] ?? event.estado ?? "actualizado";
            toast(`Pedido #${event.id}: ${estado}`, "info");
            marcarPedidoReciente(event.id);
        });

    console.log("✅ Pedidos realtime listo (canal: pedidos)");
}
