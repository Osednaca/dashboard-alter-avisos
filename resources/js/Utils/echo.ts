import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let client: Echo<'reverb'> | null = null;
let initialized = false;

/**
 * Lazily create the Reverb/Echo client. Returns null when no app key is
 * configured (e.g. production without websockets) so callers can fall back to
 * polling without crashing.
 */
export function getEcho(): Echo<'reverb'> | null {
    if (initialized) {
        return client;
    }

    initialized = true;

    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (!key) {
        return null;
    }

    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    client = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: (import.meta.env.VITE_REVERB_HOST as string) ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return client;
}
