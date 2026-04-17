import "./bootstrap";

import Echo from "laravel-echo";
import Pusher from "pusher-js";
import "./seguimiento-pedido";
window.Pusher = Pusher;

const isLocal =
    window.location.hostname === "localhost" ||
    window.location.hostname === "127.0.0.1";

window.Echo = new Echo({
    broadcaster: "reverb",
    key: "app-key",
    wsHost: isLocal ? "127.0.0.1" : "pedidosonline.tecnobyte360.com",
    wsPort: isLocal ? 8080 : 443,
    wssPort: isLocal ? 8080 : 443,
    forceTLS: !isLocal,
    enabledTransports: isLocal ? ["ws"] : ["ws", "wss"],
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

    window.Echo.channel("pedidos")
        .listen(".pedido.confirmado", (event) => {
            console.log("📦 pedido.confirmado", event);
            playNewOrderSound();
            toast(
                `🔥 Nuevo pedido de ${event.cliente_nombre ?? "cliente"}`,
                "success",
            );
        })
        .listen(".pedido.actualizado", (event) => {
            console.log("🔄 pedido.actualizado", event);
            const estado = LABEL[event.estado] ?? event.estado ?? "actualizado";
            toast(`Pedido #${event.id}: ${estado}`, "info");
        });

    console.log("✅ Pedidos realtime listo (canal: pedidos)");
}
