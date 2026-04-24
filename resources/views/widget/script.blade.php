(function () {
    'use strict';
    if (window.__twChatWidgetMounted) return;
    window.__twChatWidgetMounted = true;

    const CFG = {!! $config !!};

    // ── Utilidades
    const uuid = () => 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0;
        return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
    const storageKey = 'tw_widget_session_' + CFG.token;
    let sessionId = localStorage.getItem(storageKey);
    if (!sessionId) { sessionId = uuid(); localStorage.setItem(storageKey, sessionId); }

    // ── Estilos inyectados
    const css = `
        .twcw-container * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .twcw-container { position: fixed; z-index: 2147483647; ${CFG.pos === 'bottom-left' ? 'left: 20px;' : 'right: 20px;'} bottom: 20px; }
        .twcw-btn { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, ${CFG.color1}, ${CFG.color2}); color: #fff; cursor: pointer; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.25); display: flex; align-items: center; justify-content: center; font-size: 28px; transition: transform 0.2s; }
        .twcw-btn:hover { transform: scale(1.08); }
        .twcw-panel { position: fixed; ${CFG.pos === 'bottom-left' ? 'left: 20px;' : 'right: 20px;'} bottom: 95px; width: 360px; max-width: calc(100vw - 40px); height: 520px; max-height: calc(100vh - 120px); background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); display: none; flex-direction: column; overflow: hidden; }
        .twcw-panel.open { display: flex; animation: twcw-slide 0.25s ease-out; }
        @keyframes twcw-slide { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .twcw-header { background: linear-gradient(135deg, ${CFG.color1}, ${CFG.color2}); color: #fff; padding: 16px; display: flex; align-items: center; gap: 12px; }
        .twcw-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: rgba(255,255,255,0.2); }
        .twcw-header .twcw-title { font-weight: 700; font-size: 15px; margin: 0; }
        .twcw-header .twcw-sub { font-size: 11px; opacity: 0.85; }
        .twcw-close { margin-left: auto; background: transparent; border: 0; color: #fff; cursor: pointer; font-size: 20px; opacity: 0.9; }
        .twcw-messages { flex: 1; padding: 16px; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 8px; }
        .twcw-msg { max-width: 80%; padding: 10px 14px; border-radius: 18px; font-size: 14px; line-height: 1.4; white-space: pre-wrap; word-wrap: break-word; }
        .twcw-msg.user { align-self: flex-end; background: ${CFG.color1}; color: #fff; border-bottom-right-radius: 4px; }
        .twcw-msg.bot  { align-self: flex-start; background: #fff; color: #1e293b; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }
        .twcw-msg.typing { font-style: italic; color: #64748b; }
        .twcw-input { padding: 12px; background: #fff; border-top: 1px solid #e2e8f0; display: flex; gap: 8px; }
        .twcw-input input { flex: 1; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 20px; font-size: 14px; outline: none; }
        .twcw-input input:focus { border-color: ${CFG.color1}; }
        .twcw-input button { background: linear-gradient(135deg, ${CFG.color1}, ${CFG.color2}); color: #fff; border: 0; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }
        .twcw-footer { text-align: center; padding: 6px; font-size: 10px; color: #94a3b8; background: #fff; }
        .twcw-name-form { padding: 20px; background: #fff; }
        .twcw-name-form label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .twcw-name-form input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; margin-bottom: 12px; }
        .twcw-name-form button { width: 100%; background: linear-gradient(135deg, ${CFG.color1}, ${CFG.color2}); color: #fff; border: 0; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; }
    `;
    const styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    // ── HTML
    const container = document.createElement('div');
    container.className = 'twcw-container';
    container.innerHTML = `
        <button class="twcw-btn" title="Abrir chat">💬</button>
        <div class="twcw-panel" role="dialog" aria-label="Chat">
            <div class="twcw-header">
                ${CFG.avatar ? `<img src="${CFG.avatar}" alt="">` : `<div style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:18px;">🤖</div>`}
                <div>
                    <p class="twcw-title">${escapeHtml(CFG.titulo || '¿En qué te ayudamos?')}</p>
                    <p class="twcw-sub">En línea</p>
                </div>
                <button class="twcw-close" aria-label="Cerrar">×</button>
            </div>
            <div class="twcw-messages"></div>
            <div class="twcw-input">
                <input type="text" placeholder="${escapeHtml(CFG.holder || 'Escribe un mensaje...')}" maxlength="2000">
                <button aria-label="Enviar">➤</button>
            </div>
            <div class="twcw-footer">Powered by TecnoByte360</div>
        </div>
    `;
    document.body.appendChild(container);

    const btn = container.querySelector('.twcw-btn');
    const panel = container.querySelector('.twcw-panel');
    const closeBtn = container.querySelector('.twcw-close');
    const messagesEl = container.querySelector('.twcw-messages');
    const inputEl = container.querySelector('.twcw-input input');
    const sendBtn = container.querySelector('.twcw-input button');

    let sending = false;
    let greeted = false;

    btn.addEventListener('click', () => {
        panel.classList.add('open');
        if (!greeted && CFG.saludo) {
            appendMsg('bot', CFG.saludo);
            greeted = true;
        }
        setTimeout(() => inputEl.focus(), 100);
    });
    closeBtn.addEventListener('click', () => panel.classList.remove('open'));
    sendBtn.addEventListener('click', send);
    inputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });

    function send() {
        const texto = inputEl.value.trim();
        if (!texto || sending) return;
        sending = true;

        appendMsg('user', texto);
        inputEl.value = '';
        const typingEl = appendMsg('bot', 'escribiendo...');
        typingEl.classList.add('typing');

        fetch(CFG.apiBase + '/mensaje', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                session_id: sessionId,
                mensaje: texto,
                url_origen: location.href,
            }),
        })
        .then(r => r.json())
        .then(data => {
            typingEl.remove();
            if (data && data.reply) appendMsg('bot', data.reply);
            else appendMsg('bot', 'Uy, no logré responderte. Intenta de nuevo.');
        })
        .catch(() => {
            typingEl.remove();
            appendMsg('bot', 'Tuve un problema de conexión. ¿Me lo repites?');
        })
        .finally(() => { sending = false; inputEl.focus(); });
    }

    function appendMsg(rol, texto) {
        const div = document.createElement('div');
        div.className = 'twcw-msg ' + rol;
        div.textContent = texto;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return div;
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, m => ({
            '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
        }[m]));
    }
})();
