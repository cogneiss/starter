import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Says out loud which page a single-page navigation landed on.
 *
 * A full page load makes the browser announce the new document itself. An
 * Inertia visit does not: the document stays put and only its contents change,
 * so without this a screen reader reports nothing at all and the reader is left
 * on a page that silently became a different one.
 *
 * The title is read from the document rather than from a prop, because that is
 * what `<Head title>` has just written and it is the same sentence a full load
 * would have announced.
 */
export function RouteAnnouncer() {
    const { url } = usePage();
    const [announcement, setAnnouncement] = useState('');

    useEffect(() => {
        // `<Head>` writes the title in its own effect, which may not have run
        // yet when this one does, so the read waits for the frame after.
        const timer = window.setTimeout(
            () => setAnnouncement(document.title),
            50,
        );

        return () => window.clearTimeout(timer);
    }, [url]);

    return (
        <span
            aria-live="polite"
            aria-atomic="true"
            className="sr-only"
            data-test="route-announcer"
        >
            {announcement}
        </span>
    );
}
