import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// 🌐 Config de Reverb leída del HTML (server-side via Blade meta tags)
// con fallback a vars de Vite y por último a defaults sensatos.
// Esto evita hardcodear localhost:8080 en el bundle compilado.
const meta = (name, fallback = null) => {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el?.getAttribute('content') ?? fallback;
};

const reverbKey    = meta('reverb-key',    import.meta.env.VITE_REVERB_APP_KEY);
const reverbHost   = meta('reverb-host',   import.meta.env.VITE_REVERB_HOST ?? window.location.hostname);
const reverbPort   = meta('reverb-port',   import.meta.env.VITE_REVERB_PORT ?? 443);
const reverbScheme = meta('reverb-scheme', import.meta.env.VITE_REVERB_SCHEME ?? 'https');

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: parseInt(reverbPort, 10),
        wssPort: parseInt(reverbPort, 10),
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
