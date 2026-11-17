import { usePage } from '@inertiajs/react';
import { configureEcho, echo, echoIsConfigured } from '@laravel/echo-react';
import type Echo from 'laravel-echo';
import { useEffect } from 'react';

/**
 * Whether this deployment has a broadcaster, configuring the shared Echo
 * instance the first time it is asked.
 *
 * A starter kit runs without Reverb far more often than with it, so the absence
 * of a key is a supported state and not a failure: the connection is never
 * opened, the hook does nothing, and the inbox keeps working off page props.
 * The package's own hooks throw until configured, which is why this hook drives
 * the channel itself behind this guard instead of calling them.
 */
function realtime(): boolean {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return false;
    }

    if (!echoIsConfigured()) {
        configureEcho({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    return true;
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
        if (!realtime() || !props.organization) {
            return;
        }

        const connection: Echo<'reverb'> = echo<'reverb'>();

        const name = `organization.${props.organization.id}`;

        connection
            .private(name)
            .listen('.notification.created', onActivity)
            .error(() => {
                // Authorization was refused. Staying subscribed would retry the
                // handshake for as long as the tab is open, so the channel goes.
                connection.leave(name);
            });

        return () => {
            connection.leave(name);
        };
    }, [props.organization, onActivity]);
}
