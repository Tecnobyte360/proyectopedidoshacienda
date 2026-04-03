import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

const isHttps = (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: isHttps ? 80 : Number(import.meta.env.VITE_REVERB_PORT || 80),
    wssPort: isHttps ? 443 : Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
});