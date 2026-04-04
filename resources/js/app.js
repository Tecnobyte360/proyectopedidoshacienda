import './bootstrap';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const isLocal =
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: 'app-key',
    wsHost: isLocal ? '127.0.0.1' : 'pedidosonline.tecnobyte360.com',
    wsPort: isLocal ? 8080 : 443,
    wssPort: isLocal ? 8080 : 443,
    forceTLS: !isLocal,
    enabledTransports: isLocal ? ['ws'] : ['ws', 'wss'],
    disableStats: true,
});

document.addEventListener('DOMContentLoaded', () => {
    initChatDemo();
    initPedidosRealtime();
});

function initChatDemo() {
    const form = document.getElementById('message-form');
    const input = document.getElementById('message-input');
    const box = document.getElementById('messages');

    if (!form || !input || !box) {
        return;
    }

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const text = input.value.trim();
        if (!text) return;

        try {
            const response = await fetch('/send-message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ message: text }),
            });

            if (!response.ok) {
                console.error(
                    'Error en respuesta:',
                    response.status,
                    await response.text()
                );
            }
        } catch (error) {
            console.error('Error enviando mensaje:', error);
        }

        input.value = '';
    });

    if (!window.Echo) {
        console.warn('Echo no está disponible para chat demo');
        return;
    }

    window.Echo.channel('demo-channel').listen('MessageSent', (e) => {
        if (box.innerHTML.includes('Esperando mensajes')) {
            box.innerHTML = '';
        }

        const wrapper = document.createElement('div');
        const isAi = (e.message || '').startsWith('Asistente IA:');

        wrapper.className = isAi
            ? 'flex justify-start mb-2'
            : 'flex justify-end mb-2';

        wrapper.innerHTML = `
            <div class="max-w-xs px-4 py-2 rounded-2xl shadow ${
                isAi
                    ? 'bg-violet-100 text-violet-900'
                    : 'bg-emerald-100 text-emerald-900'
            }">
                <span class="block text-xs font-semibold mb-1">
                    ${isAi ? '🤖 IA' : '🛒 Cliente'}
                </span>
                <span class="text-sm">
                    ${(e.message || '')
                        .replace(/^Asistente IA:\s*/, '')
                        .replace(/^Cliente:\s*/, '')}
                </span>
            </div>
        `;

        box.appendChild(wrapper);
        box.scrollTop = box.scrollHeight;
    });
}

function initPedidosRealtime() {
    const ordersList = document.getElementById('orders-list');
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const orderSound = document.getElementById('new-order-sound');

    if (!ordersList || !toast || !toastMessage) {
        console.warn('No se encontraron elementos de pedidos en la vista');
        return;
    }

    if (!window.Echo) {
        console.warn('Echo no está disponible para pedidos');
        return;
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('es-CO').format(Number(value || 0));
    }

    function formatHour(dateString) {
        return new Date(dateString).toLocaleTimeString('es-CO', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    }

    function getStatusLabel(estado) {
        return {
            nuevo: 'Nuevo',
            en_proceso: 'En proceso',
            despachado: 'Despachado',
            entregado: 'Entregado',
            cancelado: 'Cancelado',
        }[estado] || estado;
    }

    function getBadgeClass(estado) {
        return {
            nuevo: 'bg-blue-100 text-blue-700 ring-blue-200',
            en_proceso: 'bg-amber-100 text-amber-700 ring-amber-200',
            despachado: 'bg-violet-100 text-violet-700 ring-violet-200',
            entregado: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            cancelado: 'bg-rose-100 text-rose-700 ring-rose-200',
        }[estado] || 'bg-slate-100 text-slate-700 ring-slate-200';
    }

    function getDotClass(estado) {
        return {
            nuevo: 'bg-blue-500',
            en_proceso: 'bg-amber-500',
            despachado: 'bg-violet-500',
            entregado: 'bg-emerald-500',
            cancelado: 'bg-rose-500',
        }[estado] || 'bg-slate-400';
    }

    function getIconEstado(estado) {
        return {
            nuevo: 'fa-bell',
            en_proceso: 'fa-gears',
            despachado: 'fa-motorcycle',
            entregado: 'fa-circle-check',
            cancelado: 'fa-ban',
        }[estado] || 'fa-circle';
    }

    function showToast(message) {
        toastMessage.textContent = message;
        toast.classList.remove('hidden');
        toast.classList.add('block');

        setTimeout(() => {
            toast.classList.add('hidden');
            toast.classList.remove('block');
        }, 4500);
    }

    function playNewOrderSound() {
        if (!orderSound) return;

        try {
            orderSound.currentTime = 0;
            const playPromise = orderSound.play();

            if (playPromise !== undefined) {
                playPromise.catch((error) => {
                    console.warn('El navegador bloqueó el audio automático:', error);
                });
            }
        } catch (error) {
            console.warn('No fue posible reproducir el sonido:', error);
        }
    }

    function highlightRow(row) {
        if (!row) return;

        row.classList.remove('new-order-row', 'new-order-ring');
        void row.offsetWidth;
        row.classList.add('new-order-row', 'new-order-ring');

        setTimeout(() => {
            row.classList.remove('new-order-row', 'new-order-ring');
        }, 5000);
    }

    function buildRow(pedido) {
        const total = pedido.total_raw ?? pedido.total ?? 0;
        const zona = pedido.zona
            ? pedido.zona.charAt(0).toUpperCase() + pedido.zona.slice(1)
            : 'Sin zona';

        const tr = document.createElement('tr');
        tr.dataset.id = pedido.id;
        tr.className = `group transition hover:bg-gradient-to-r hover:from-orange-50/40 hover:to-white ${pedido.estado === 'cancelado' ? 'opacity-70' : ''}`;

        tr.innerHTML = `
            <td class="px-6 py-5 align-middle">
                <div class="inline-flex items-center gap-2 rounded-2xl bg-orange-50 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.2em] text-orange-600">
                    <i class="fa-solid fa-hashtag text-[10px]"></i>
                    PED-${String(pedido.id).padStart(3, '0')}
                </div>
            </td>

            <td class="px-6 py-5 align-middle">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <div class="font-black text-slate-900">${pedido.cliente_nombre ?? 'Cliente'}</div>
                        <div class="text-sm text-slate-500">Ahora mismo</div>
                    </div>
                </div>
            </td>

            <td class="px-6 py-5 align-middle">
                <span class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                    <i class="fa-solid fa-location-dot text-orange-500"></i>
                    ${zona}
                </span>
            </td>

            <td class="px-6 py-5 align-middle">
                <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                    <i class="fa-solid fa-phone text-orange-500"></i>
                    ${pedido.telefono ?? 'Sin teléfono'}
                </span>
            </td>

            <td class="px-6 py-5 align-middle">
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-xs font-extrabold uppercase tracking-[0.18em] ring-1 ${getBadgeClass(pedido.estado)}">
                    <span class="h-2.5 w-2.5 rounded-full ${getDotClass(pedido.estado)}"></span>
                    <i class="fa-solid ${getIconEstado(pedido.estado)}"></i>
                    ${getStatusLabel(pedido.estado)}
                </span>
            </td>

            <td class="px-6 py-5 align-middle">
                <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                    <i class="fa-regular fa-clock text-orange-500"></i>
                    ${pedido.created_at ? formatHour(pedido.created_at) : 'Ahora'}
                </span>
            </td>

            <td class="px-6 py-5 align-middle">
                <div class="inline-flex rounded-2xl bg-gradient-to-r from-slate-900 to-slate-700 px-4 py-2 text-lg font-black text-white shadow-md">
                    $${formatMoney(total)}
                </div>
            </td>

            <td class="px-6 py-5 text-center align-middle">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-orange-200 bg-orange-50 text-orange-500 transition hover:scale-105 hover:bg-orange-100"
                >
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </td>
        `;

        return tr;
    }

    function addOrderRow(pedido) {
        const emptyState = ordersList.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        const existingRow = ordersList.querySelector(`tr[data-id="${pedido.id}"]`);
        if (existingRow) {
            existingRow.remove();
        }

        const row = buildRow(pedido);
        ordersList.prepend(row);
        highlightRow(row);
    }

    function updateOrderRow(pedido) {
        const existingRow = ordersList.querySelector(`tr[data-id="${pedido.id}"]`);

        if (!existingRow) {
            addOrderRow(pedido);
            return;
        }

        const newRow = buildRow(pedido);
        existingRow.replaceWith(newRow);

        if (pedido.estado === 'nuevo') {
            highlightRow(newRow);
        }
    }

    window.Echo.channel('pedidos')
        .listen('.pedido.confirmado', (event) => {
            console.log('pedido.confirmado', event);
            addOrderRow(event);
            playNewOrderSound();
            showToast(`Nuevo pedido de ${event.cliente_nombre ?? 'cliente'}.`);
        })
        .listen('.pedido.actualizado', (event) => {
            console.log('pedido.actualizado', event);
            updateOrderRow(event);

            if (event.accion === 'cancelado' || event.estado === 'cancelado') {
                showToast(`Pedido #${event.id} cancelado correctamente.`);
            } else {
                showToast(`Pedido #${event.id} actualizado.`);
            }
        });
}