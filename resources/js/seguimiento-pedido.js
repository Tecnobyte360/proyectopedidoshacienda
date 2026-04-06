import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

const isLocal =
    window.location.hostname === "localhost" ||
    window.location.hostname === "127.0.0.1";

if (!window.Echo) {
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
}

document.addEventListener("DOMContentLoaded", () => {
    initSeguimientoPedidoRealtime();
});

function initSeguimientoPedidoRealtime() {
    const root = document.getElementById("seguimiento-pedido-root");

    if (!root) return;

    const codigo = root.dataset.codigoSeguimiento;

    if (!codigo) {
        console.warn("No se encontró el código de seguimiento.");
        return;
    }

    if (!window.Echo) {
        console.warn("Echo no está disponible.");
        return;
    }

    console.log("📡 Escuchando canal:", `pedido-seguimiento.${codigo}`);

    window.Echo.channel(`pedido-seguimiento.${codigo}`)
        .listen(".pedido.actualizado", (event) => {
            console.log("🔄 Seguimiento actualizado:", event);

            if (window.Livewire) {
                window.Livewire.dispatch("seguimiento-actualizado");
            }

            mostrarFlashEstado(event.estado);
        });
}

function mostrarFlashEstado(estado) {
    const flash = document.getElementById("seguimiento-estado-flash");
    if (!flash) return;

    const textoBase = estado
        ? `Estado actualizado a: ${String(estado).replaceAll("_", " ")}`
        : "Estado actualizado";

    flash.textContent = textoBase;
    flash.classList.remove("hidden", "opacity-0", "translate-y-2");
    flash.classList.add("opacity-100", "translate-y-0");

    setTimeout(() => {
        flash.classList.remove("opacity-100", "translate-y-0");
        flash.classList.add("opacity-0", "translate-y-2");

        setTimeout(() => {
            flash.classList.add("hidden");
        }, 300);
    }, 2600);
}