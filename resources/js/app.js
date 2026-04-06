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
// ─────────────────────────────────────────────────────────────────────────────
function initPedidosRealtime() {
    const ordersList = document.getElementById("orders-list");
    const toast = document.getElementById("toast");
    const toastMsg = document.getElementById("toast-message");
    const orderSound = document.getElementById("new-order-sound");

    if (!ordersList || !toast || !toastMsg) {
        console.warn("No se encontraron elementos de pedidos en la vista");
        return;
    }

    if (!window.Echo) {
        console.warn("Echo no está disponible para pedidos");
        return;
    }

    // ── Config de estados ─────────────────────────────────────────────────────
    const ESTADOS = {
        nuevo: {
            badge: "bg-blue-50 text-blue-700 border-blue-200",
            dot: "bg-blue-500",
            icon: "fa-bell",
            label: "Nuevo",
            rowOpacity: "",
        },
        en_preparacion: {
            badge: "bg-amber-50 text-amber-700 border-amber-200",
            dot: "bg-amber-500",
            icon: "fa-gears",
            label: "En proceso",
            rowOpacity: "",
        },
        repartidor_en_camino: {
            badge: "bg-violet-50 text-violet-700 border-violet-200",
            dot: "bg-violet-500",
            icon: "fa-motorcycle",
            label: "Despachado",
            rowOpacity: "",
        },
        entregado: {
            badge: "bg-emerald-50 text-emerald-700 border-emerald-200",
            dot: "bg-emerald-500",
            icon: "fa-circle-check",
            label: "Entregado",
            rowOpacity: "",
        },
        cancelado: {
            badge: "bg-rose-50 text-rose-700 border-rose-200",
            dot: "bg-rose-500",
            icon: "fa-ban",
            label: "Cancelado",
            rowOpacity: "opacity-75",
        },
    };

    // ── Helpers ───────────────────────────────────────────────────────────────

    function formatMoney(value) {
        return new Intl.NumberFormat("es-CO").format(Number(value || 0));
    }

    function formatHour(dateString) {
        if (!dateString) return "Ahora";
        return new Date(dateString).toLocaleTimeString("es-CO", {
            hour: "2-digit",
            minute: "2-digit",
            hour12: true,
        });
    }

    function diffForHumans(dateString) {
        if (!dateString) return "Ahora mismo";
        const diff = Math.floor((Date.now() - new Date(dateString)) / 1000);
        if (diff < 60) return "Hace un momento";
        if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
        if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} h`;
        return `Hace ${Math.floor(diff / 86400)} días`;
    }

    function getIniciales(nombre) {
        return (
            (nombre ?? "CL")
                .trim()
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((p) => p.charAt(0).toUpperCase())
                .join("") || "CL"
        );
    }

    function getEstadoCfg(estado) {
        return (
            ESTADOS[estado] ?? {
                badge: "bg-slate-100 text-slate-700 border-slate-200",
                dot: "bg-slate-400",
                icon: "fa-circle",
                label: (estado ?? "").replace(/_/g, " "),
                rowOpacity: "",
            }
        );
    }

    // ── Busca el componente Livewire correcto (pedidos.index) ─────────────────
    // El problema de usar querySelector('[wire:id]') es que agarra el PRIMER
    // componente del DOM, que puede ser el layout u otro componente distinto.
    // Esta función itera TODOS los componentes y encuentra el correcto.
    function findPedidosComponent() {
        // Estrategia 1: Livewire v3 — window.Livewire.all()
        const all = window.Livewire?.all?.();
        if (Array.isArray(all)) {
            // Busca por nombre del componente (pedidos.index ó similar)
            const found = all.find((c) => {
                const name =
                    c.name ??
                    c.snapshot?.memo?.name ??
                    c.__instance?.name ??
                    "";
                return name.toLowerCase().includes("pedido");
            });
            if (found) return found;
        }

        // Estrategia 2: iterar todos los [wire:id] del DOM y probar cuál
        // tiene el método marcarEnPreparacion en su $wire proxy
        const wireEls = document.querySelectorAll("[wire\\:id]");
        for (const el of wireEls) {
            const wireId = el.getAttribute("wire:id");
            const component = window.Livewire?.find(wireId);

            if (!component) continue;

            // En Livewire v3, $wire es un proxy con los métodos del componente
            if (typeof component.$wire?.marcarEnPreparacion === "function") {
                return component;
            }

            // Alternativa: intentar con .call() y ver si lanza error de método
            // (solo como último recurso, cubierto en callLivewire)
        }

        return null;
    }

    // ── Llama a un método Livewire de forma segura ────────────────────────────
    function callLivewire(method, id) {
        const component = findPedidosComponent();

        if (!component) {
            // Debug: muestra qué componentes hay disponibles
            const all = window.Livewire?.all?.() ?? [];
            console.error(
                `[Pedidos] No se encontró el componente Livewire de pedidos.`,
                `Componentes en el DOM:`,
                all.map(
                    (c) => c.name ?? c.snapshot?.memo?.name ?? "(sin nombre)",
                ),
            );
            return;
        }

        // Livewire v3: preferir $wire (proxy directo) sobre .call()
        if (typeof component.$wire?.[method] === "function") {
            component.$wire[method](id);
        } else {
            component.call(method, id);
        }
    }

    // ── Celda de acción ───────────────────────────────────────────────────────
   function buildActionCell(pedido) {
    const estado = pedido.estado ?? "nuevo";
    const id = pedido.id;

    const btn = ({ bgColor, hoverColor, method, icon, label }) => `
        <button
            type="button"
            data-action="${method}"
            data-pedido-id="${id}"
            class="js-action-btn inline-flex items-center gap-2 rounded-xl ${bgColor} px-3 py-2 text-xs font-bold text-white transition ${hoverColor} disabled:opacity-60 disabled:cursor-not-allowed">
            <i class="fa-solid ${icon} js-btn-icon"></i>
            <i class="fa-solid fa-spinner fa-spin hidden js-btn-spinner"></i>
            ${label}
        </button>`;

    if (estado === "nuevo") {
        return btn({
            bgColor: "bg-amber-500",
            hoverColor: "hover:bg-amber-600",
            method: "marcarEnPreparacion",
            icon: "fa-utensils",
            label: "Iniciar preparación",
        });
    }

    if (estado === "en_preparacion") {
        return `
            <div class="flex flex-col items-center gap-2">
                ${
                    pedido.domiciliario
                        ? `
                    <div class="inline-flex items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">
                        <i class="fa-solid fa-user-check text-[10px]"></i>
                        ${pedido.domiciliario.nombre ?? "Domiciliario"}
                    </div>
                `
                        : ""
                }

                ${btn({
                    bgColor: "bg-violet-500",
                    hoverColor: "hover:bg-violet-600",
                    method: "abrirModalDespacho",
                    icon: "fa-motorcycle",
                    label: pedido.domiciliario ? "Reasignar y despachar" : "Asignar y despachar",
                })}
            </div>
        `;
    }

    if (estado === "repartidor_en_camino") {
        return `
            <div class="flex flex-col items-center gap-2">
                ${
                    pedido.domiciliario
                        ? `
                    <div class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">
                        <i class="fa-solid fa-motorcycle text-[10px]"></i>
                        ${pedido.domiciliario.nombre ?? "Domiciliario"}
                    </div>
                `
                        : ""
                }

                ${
                    pedido.token_entrega
                        ? `
                    <div class="inline-flex items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">
                        <i class="fa-solid fa-key text-[10px]"></i>
                        Token: <span class="tracking-widest">${pedido.token_entrega}</span>
                    </div>
                `
                        : ""
                }

                ${btn({
                    bgColor: "bg-emerald-500",
                    hoverColor: "hover:bg-emerald-600",
                    method: "abrirModalEntrega",
                    icon: "fa-circle-check",
                    label: "Confirmar entrega",
                })}
            </div>
        `;
    }

    if (estado === "entregado") {
        return `
            <div class="flex flex-col items-center gap-2">
                ${
                    pedido.domiciliario
                        ? `
                    <div class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                        <i class="fa-solid fa-user-check text-[10px]"></i>
                        ${pedido.domiciliario.nombre ?? "Domiciliario"}
                    </div>
                `
                        : ""
                }

                <span class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 border border-emerald-200">
                    <i class="fa-solid fa-circle-check"></i>
                    Entregado
                </span>
            </div>
        `;
    }

    if (estado === "cancelado") {
        return `
            <div class="flex flex-col items-center gap-2">
                ${
                    pedido.domiciliario
                        ? `
                    <div class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-600">
                        <i class="fa-solid fa-user text-[10px]"></i>
                        ${pedido.domiciliario.nombre ?? "Domiciliario"}
                    </div>
                `
                        : ""
                }

                <span class="inline-flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 border border-rose-200">
                    <i class="fa-solid fa-ban"></i>
                    Cancelado
                </span>
            </div>
        `;
    }

    const cfg = getEstadoCfg(estado);
    return `
        <span class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 border border-slate-200">
            <i class="fa-solid fa-circle-info"></i>
            ${cfg.label}
        </span>`;
}
    // ── buildRow ──────────────────────────────────────────────────────────────
   function buildRow(pedido) {
    const estado = pedido.estado ?? "nuevo";
    const cfg = getEstadoCfg(estado);
    const pedidoId = String(pedido.id).padStart(3, "0",);
    const iniciales = getIniciales(pedido.cliente_nombre);
    const zona = pedido.zona
        ? pedido.zona.charAt(0).toUpperCase() + pedido.zona.slice(1)
        : "Sin zona";
    const hora = formatHour(pedido.created_at);
    const diff = diffForHumans(pedido.created_at);
    const total = formatMoney(pedido.total_raw ?? pedido.total ?? 0);
    const action = buildActionCell(pedido);

    const domiciliarioHtml = pedido.domiciliario
        ? `
        <div class="flex flex-col">
            <span class="text-sm font-semibold text-slate-800">
                ${pedido.domiciliario.nombre ?? "Sin nombre"}
            </span>
            <span class="text-xs text-slate-500">
                ${pedido.domiciliario.telefono ?? "Sin teléfono"}
            </span>
        </div>
    `
        : `
        <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-500">
            <i class="fa-solid fa-user-clock"></i>
            Sin asignar
        </span>
    `;

    const tr = document.createElement("tr");
    tr.dataset.id = pedido.id;
    tr.className = `transition hover:bg-slate-50 ${cfg.rowOpacity}`;

    tr.innerHTML = `
    <td class="px-4 py-3.5 align-middle">
        <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-700">
            <i class="fa-solid fa-hashtag text-[9px] text-slate-400"></i>
            PED-${pedidoId}
        </div>
    </td>

    <td class="px-4 py-3.5 align-middle">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">
                ${iniciales}
            </div>
            <div class="min-w-0">
                <div class="truncate font-semibold text-slate-900">${pedido.cliente_nombre ?? "Cliente"}</div>
                <div class="text-xs text-slate-500">${diff}</div>
            </div>
        </div>
    </td>

    <td class="px-4 py-3.5 align-middle">
        <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700">
            <i class="fa-solid fa-location-dot text-indigo-500 text-[11px]"></i>
            ${zona}
        </span>
    </td>

    <td class="px-4 py-3.5 align-middle">
        <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
            <i class="fa-solid fa-phone text-slate-400 text-[11px]"></i>
            ${pedido.telefono ?? "Sin teléfono"}
        </span>
    </td>

    <td class="px-4 py-3.5 align-middle">
        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.15em] ${cfg.badge}">
            <span class="h-2 w-2 rounded-full ${cfg.dot}"></span>
            <i class="fa-solid ${cfg.icon} text-[10px]"></i>
            ${cfg.label}
        </span>
    </td>

    <td class="px-4 py-3.5 align-middle">
        <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
            <i class="fa-regular fa-clock text-slate-400 text-[11px]"></i>
            ${hora}
        </span>
    </td>

    <td class="px-4 py-3.5 align-middle">
        <span class="inline-flex rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white shadow-sm">
            $${total}
        </span>
    </td>

    <td class="px-4 py-3.5 align-middle">
        ${domiciliarioHtml}
    </td>

    <td class="px-4 py-3.5 text-center align-middle">
        ${action}
    </td>
    `;

    return tr;
}


    // ── Delegación de clicks ──────────────────────────────────────────────────
    ordersList.addEventListener("click", (e) => {
        const btn = e.target.closest(".js-action-btn");
        if (!btn || btn.disabled) return;

        const method = btn.dataset.action;
        const id = Number(btn.dataset.pedidoId);
        if (!method || !id) return;

        // Spinner ON — replica wire:loading
        const icon = btn.querySelector(".js-btn-icon");
        const spinner = btn.querySelector(".js-btn-spinner");
        btn.disabled = true;
        icon?.classList.add("hidden");
        spinner?.classList.remove("hidden");

        callLivewire(method, id);
    });

    // ── Toast ─────────────────────────────────────────────────────────────────
    function showToast(message) {
        toastMsg.textContent = message;
        toast.classList.remove("hidden");
        toast.classList.add("block");
        setTimeout(() => {
            toast.classList.add("hidden");
            toast.classList.remove("block");
        }, 4500);
    }

    // ── Sonido ────────────────────────────────────────────────────────────────
    function playNewOrderSound() {
        if (!orderSound) return;
        try {
            orderSound.currentTime = 0;
            orderSound
                .play()
                ?.catch((err) =>
                    console.warn("Audio bloqueado por el navegador:", err),
                );
        } catch (err) {
            console.warn("No fue posible reproducir el sonido:", err);
        }
    }

    // ── Highlight de fila ─────────────────────────────────────────────────────
    function highlightRow(row) {
        if (!row) return;
        row.classList.remove("new-order-row", "new-order-ring");
        void row.offsetWidth;
        row.classList.add("new-order-row", "new-order-ring");
        setTimeout(
            () => row.classList.remove("new-order-row", "new-order-ring"),
            5000,
        );
    }

    // ── Insertar fila nueva ───────────────────────────────────────────────────
    function addOrderRow(pedido) {
        ordersList.querySelector(".empty-state")?.remove();
        ordersList.querySelector(`tr[data-id="${pedido.id}"]`)?.remove();

        const row = buildRow(pedido);
        ordersList.prepend(row);
        highlightRow(row);
    }

    // ── Actualizar fila existente ─────────────────────────────────────────────
    function updateOrderRow(pedido) {
        const existing = ordersList.querySelector(`tr[data-id="${pedido.id}"]`);

        if (!existing) {
            addOrderRow(pedido);
            return;
        }

        const newRow = buildRow(pedido);
        existing.replaceWith(newRow);

        if (pedido.estado === "nuevo") highlightRow(newRow);
    }

    // ── Listeners de Reverb ───────────────────────────────────────────────────
    window.Echo.channel("pedidos")
        .listen(".pedido.confirmado", (event) => {
            console.log("📦 pedido.confirmado", event);
            addOrderRow(event);
            playNewOrderSound();
            showToast(`Nuevo pedido de ${event.cliente_nombre ?? "cliente"}.`);
        })
        .listen(".pedido.actualizado", (event) => {
            console.log("🔄 pedido.actualizado", event);
            updateOrderRow(event);

            if (event.accion === "cancelado" || event.estado === "cancelado") {
                showToast(`Pedido #${event.id} cancelado correctamente.`);
            } else {
                showToast(
                    `Pedido #${event.id} actualizado a: ${getEstadoCfg(event.estado).label}.`,
                );
            }
        });
}
