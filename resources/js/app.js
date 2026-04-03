import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('message-form');
    const input = document.getElementById('message-input');
    const box = document.getElementById('messages');

    if (!form || !input || !box) {
        console.warn('Elementos del formulario no encontrados');
        return;
    }

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    // ================================
    // ENVÍO DE MENSAJES
    // ================================
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
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: text }),
            });

            if (!response.ok) {
                console.error('Error en respuesta:', response.status, await response.text());
            }
        } catch (error) {
            console.error('Error enviando mensaje:', error);
        }

        input.value = '';
    });

    // ================================
    // WEBSOCKET - RECEPCIÓN DE MENSAJES
    // ================================
    const initEcho = () => {
        if (!window.Echo) {
            console.warn('window.Echo no está listo, reintentando...');
            return setTimeout(initEcho, 200);
        }

        console.log('Echo listo, suscribiendo a demo-channel');

        window.Echo.channel('demo-channel')
            .listen('MessageSent', (e) => {
                console.log('Evento recibido:', e);

                // Elimina texto inicial
                if (box.innerHTML.includes('Esperando mensajes')) {
                    box.innerHTML = '';
                }

                const p = document.createElement('div');
                const isAi = (e.message || '').startsWith('Asistente IA:');

                p.className = isAi
                    ? 'flex justify-start mb-2'
                    : 'flex justify-end mb-2';

                p.innerHTML = `
                    <div class="max-w-xs px-4 py-2 rounded-2xl shadow 
                        ${isAi 
                            ? 'bg-violet-100 text-violet-900' 
                            : 'bg-emerald-100 text-emerald-900'}">
                        <span class="block text-xs font-semibold mb-1">
                            ${isAi ? '🤖 IA' : '🛒 Cliente'}
                        </span>
                        <span class="text-sm">
                            ${e.message
                                .replace(/^Asistente IA:\s*/,'')
                                .replace(/^Cliente:\s*/,'')}
                        </span>
                    </div>
                `;

                box.appendChild(p);
                box.scrollTop = box.scrollHeight;
            });
    };

    initEcho();
});
