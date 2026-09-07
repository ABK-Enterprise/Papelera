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
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    const broadcaster = import.meta.env.VITE_BROADCASTER || (pusherKey ? 'pusher' : (reverbKey ? 'reverb' : 'null'));

    const csrfToken = typeof document !== 'undefined'
        ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '')
        : '';

    if (broadcaster === 'pusher') {
        if (!pusherKey) {
            if (import.meta.env.DEV) {
                console.warn('[Echo] VITE_PUSHER_APP_KEY no configurada. WebSockets en modo fallback silencioso.');
            }
            return createNullEcho();
        }

        try {
            return new Echo({
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'sa1',
                forceTLS: true,
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                },
            });
        } catch (err) {
            console.warn('[Echo] Error al instanciar Laravel Echo con Pusher:', err);
            return createNullEcho();
        }
    }

    if (broadcaster === 'reverb') {
        const host = import.meta.env.VITE_REVERB_HOST;
        if (!reverbKey || !host) {
            if (import.meta.env.DEV) {
                console.warn('[Echo] VITE_REVERB_APP_KEY o VITE_REVERB_HOST no configurados. WebSockets en modo fallback silencioso.');
            }
            return createNullEcho();
        }

        try {
            return new Echo({
                broadcaster: 'reverb',
                key: reverbKey,
                wsHost: host,
                wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
                wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                },
            });
        } catch (err) {
            console.warn('[Echo] Error al instanciar Laravel Echo con Reverb:', err);
            return createNullEcho();
        }
    }

    if (import.meta.env.DEV) {
        console.warn('[Echo] Sin configuración de WebSockets. Modo fallback silencioso.');
    }
    return createNullEcho();
}

export const echo = initEcho();

window.Echo = echo;

export default echo;
