import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<any>;
    }
}

window.Pusher = Pusher;

function createNullEcho(): Echo<any> {
    const dummyChannel = {
        listen: () => dummyChannel,
        listenForWhisper: () => dummyChannel,
        stopListening: () => dummyChannel,
        stopListeningForWhisper: () => dummyChannel,
        notification: () => dummyChannel,
        error: () => dummyChannel,
    };

    return {
        private: () => dummyChannel,
        channel: () => dummyChannel,
        join: () => dummyChannel,
        leave: () => {},
        leaveChannel: () => {},
        disconnect: () => {},
        connector: null,
    } as unknown as Echo<any>;
}

function initEcho(): Echo<any> {
    const csrfToken = typeof document !== 'undefined'
        ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '')
        : '';

    const metaBroadcaster = typeof document !== 'undefined'
        ? document.querySelector('meta[name="broadcaster"]')?.getAttribute('content')
        : null;

    const metaPusherKey = typeof document !== 'undefined'
        ? document.querySelector('meta[name="pusher-key"]')?.getAttribute('content')
        : null;

    const metaPusherCluster = typeof document !== 'undefined'
        ? document.querySelector('meta[name="pusher-cluster"]')?.getAttribute('content')
        : null;

    const metaReverbKey = typeof document !== 'undefined'
        ? document.querySelector('meta[name="reverb-key"]')?.getAttribute('content')
        : null;

    const metaReverbHost = typeof document !== 'undefined'
        ? document.querySelector('meta[name="reverb-host"]')?.getAttribute('content')
        : null;

    const metaReverbPort = typeof document !== 'undefined'
        ? document.querySelector('meta[name="reverb-port"]')?.getAttribute('content')
        : null;

    const metaReverbScheme = typeof document !== 'undefined'
        ? document.querySelector('meta[name="reverb-scheme"]')?.getAttribute('content')
        : null;

    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || metaPusherKey;
    const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || metaPusherCluster || 'sa1';
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || metaReverbKey;
    const broadcaster = import.meta.env.VITE_BROADCASTER || metaBroadcaster || (pusherKey ? 'pusher' : (reverbKey ? 'reverb' : 'null'));

    if (broadcaster === 'pusher') {
        if (!pusherKey) {
            console.warn('[Echo] Pusher App Key no configurada (ni en compilación ni en meta tag). WebSockets en modo fallback silencioso.');
            return createNullEcho();
        }

        try {
            const echoInstance = new Echo({
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: pusherCluster,
                forceTLS: true,
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                },
            });

            console.info(`[Echo] Conectando a Pusher (cluster: ${pusherCluster})`);
            return echoInstance;
        } catch (err) {
            console.warn('[Echo] Error al instanciar Laravel Echo con Pusher:', err);
            return createNullEcho();
        }
    }

    if (broadcaster === 'reverb') {
        const host = import.meta.env.VITE_REVERB_HOST || metaReverbHost;
        const port = Number(import.meta.env.VITE_REVERB_PORT || metaReverbPort || 80);
        const scheme = import.meta.env.VITE_REVERB_SCHEME || metaReverbScheme || 'https';

        if (!reverbKey || !host) {
            console.warn('[Echo] Reverb Key o Host no configurados. WebSockets en modo fallback silencioso.');
            return createNullEcho();
        }

        try {
            const echoInstance = new Echo({
                broadcaster: 'reverb',
                key: reverbKey,
                wsHost: host,
                wsPort: port,
                wssPort: Number(import.meta.env.VITE_REVERB_PORT || metaReverbPort || 443),
                forceTLS: scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                },
            });

            console.info(`[Echo] Conectando a Reverb (${host}:${port})`);
            return echoInstance;
        } catch (err) {
            console.warn('[Echo] Error al instanciar Laravel Echo con Reverb:', err);
            return createNullEcho();
        }
    }

    console.warn('[Echo] Sin configuración de WebSockets activa. Modo fallback silencioso.');
    return createNullEcho();
}

export const echo = initEcho();

window.Echo = echo;

export default echo;

