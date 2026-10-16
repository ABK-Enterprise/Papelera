import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<any>;
    }
}

if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
}

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
    if (typeof window === 'undefined') {
        return createNullEcho();
    }

    const csrfToken = typeof document !== 'undefined'
        ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '')
        : '';

    const metaKey = typeof document !== 'undefined'
        ? document.querySelector('meta[name="pusher-key"]')?.getAttribute('content')
        : null;

    const metaCluster = typeof document !== 'undefined'
        ? document.querySelector('meta[name="pusher-cluster"]')?.getAttribute('content')
        : null;

    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || metaKey;
    const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || metaCluster || 'sa1';

    if (!pusherKey) {
        console.warn('[Echo] Pusher App Key no encontrada. WebSockets en modo fallback.');
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

export const echo = initEcho();

if (typeof window !== 'undefined') {
    window.Echo = echo;
}

export default echo;

