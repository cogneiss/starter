import { usePage } from '@inertiajs/react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useEffect } from 'react';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

let connection: Echo<'reverb'> | null | undefined;

/**
 * The websocket connection, or null when this deployment has no broadcaster.
 *
 * A starter kit runs without Reverb far more often than with it, so the absence
 * of a key is a supported state and not a failure: the connection is never
 * opened, the hook does nothing, and the inbox keeps working off page props.
 */
function realtime(): Echo<'reverb'> | null {
    if (connection === undefined) {
        const key = import.meta.env.VITE_REVERB_APP_KEY;

        window.Pusher = Pusher;

        connection = key
            ? new Echo({
                  broadcaster: 'reverb',
                  key,
                  wsHost: import.meta.env.VITE_REVERB_HOST,
                  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
                  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
                  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
                  enabledTransports: ['ws', 'wss'],
              })
            : null;
    }

    return connection;
}

/**
 * Listen on the current organization's private channel.
 *
 * The channel name is read from the page props rather than taken as an
 * argument. A hook that accepted an organization would let any caller name one,
 * and the server's authorization callback would then be the only thing between
 * a typo and another tenant's traffic.
 */
export function useOrganizationChannel(onActivity: () => void): void {
    const props = usePage().props;

    useEffect(() => {
        const echo = realtime();

        if (!echo || !props.organization) {
            return;
        }

        const name = `organization.${props.organization.id}`;

        echo.private(`organization.${props.organization.id}`)
            .listen('.notification.created', onActivity)
            .error(() => {
                // Authorization was refused. Staying subscribed would retry the
                // handshake for as long as the tab is open, so the channel goes.
                echo.leave(name);
            });

        return () => {
            echo.leave(name);
        };
    }, [props.organization, onActivity]);
}
