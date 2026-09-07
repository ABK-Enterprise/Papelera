import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
    }
}

window.Pusher = Pusher;

function createNullEcho(): Echo<'reverb'> {
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
    } as unknown as Echo<'reverb'>;
}

function initEcho(): Echo<'reverb'> {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST;

    if (!key || !host) {
        if (import.meta.env.DEV) {
            console.warn('[Echo] VITE_REVERB_APP_KEY o VITE_REVERB_HOST no configurados. WebSockets en modo fallback silencioso.');
        }
        return createNullEcho();
    }

    try {
        return new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: host,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': typeof document !== 'undefined' ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '') : '',
                },
            },
        });
    } catch (err) {
        console.warn('[Echo] Error al instanciar Laravel Echo Reverb:', err);
        return createNullEcho();
    }
}

export const echo = initEcho();

window.Echo = echo;

export default echo;

