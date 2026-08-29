import type { Method, PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { csrfToken } from '@/routes';

/**
 * What a person is told when a request fails in the browser.
 *
 * The server writes the same sentences for the failures it can answer with a
 * page (see `UserFriendlyExceptionRegistrar`). These are for the failures it
 * cannot: an Inertia visit that came back as something other than a page, and
 * the plain XHR calls the palette and the streaming views make. One table, so a
 * 403 reads the same whichever half of the application noticed it.
 */
const SENTENCES: Record<number, string> = {
    401: 'You are signed out. Sign in again to pick up where you left off.',
    403: 'You do not have permission to do that.',
    404: 'We could not find that. It may have been deleted, or belong to another organization.',
    419: 'Your session expired while that page was open.',
    422: 'Some of what was sent was not valid. Check the highlighted fields and try again.',
    429: 'That was a lot of requests at once. Wait a moment and try again.',
};

/** The sentence for a status, never a bare code and never a class name. */
export function friendlyMessage(status: number): string {
    if (SENTENCES[status]) {
        return SENTENCES[status];
    }

    return status >= 500
        ? 'Something went wrong at our end. The error has been recorded.'
        : 'That request did not go through. Try again in a moment.';
}

/**
 * `fetch`, with the same sentence on failure as everything else.
 *
 * The built-in XHR client is not an Inertia visit, so none of the router events
 * fire for it. Calls made through here still say the same thing, and still
 * reject, so the caller keeps whatever local state it wants to show as well.
 */
export async function xhrFetch(
    input: string,
    init?: RequestInit,
): Promise<Response> {
    const response = await fetch(input, init);

    if (!response.ok) {
        toast.error(friendlyMessage(response.status));

        throw new Error(`Request failed with status ${response.status}.`);
    }

    return response;
}

/** Ask for a fresh session cookie, then send the visit that expired again. */
async function retryExpiredVisit(visit: PendingVisit): Promise<void> {
    await fetch(csrfToken.url(), { headers: { Accept: 'application/json' } });

    router.visit(visit.url, {
        method: visit.method as Method,
        data: visit.data,
        preserveScroll: true,
    });
}

/**
 * Teach the router to speak to people.
 *
 * Anything the server could answer with a page already arrives as one. What is
 * left here is the responses Inertia cannot navigate to — and an expired token,
 * which is the one failure the browser can undo on its own: it asks for a new
 * token and offers the request again rather than telling someone to reload and
 * retype what they had.
 */
export function installHttpErrorHandlers(): void {
    let lastVisit: PendingVisit | null = null;

    router.on('start', (event) => {
        lastVisit = event.detail.visit;
    });

    router.on('httpException', (event) => {
        event.preventDefault();

        const { status } = event.detail.response;
        const expired = lastVisit;

        if (status === 419 && expired) {
            toast.error(friendlyMessage(status), {
                action: {
                    label: 'Try again',
                    onClick: () => void retryExpiredVisit(expired),
                },
            });

            return;
        }

        toast.error(friendlyMessage(status));
    });

    router.on('networkError', (event) => {
        event.preventDefault();

        toast.error(
            'The connection dropped before that finished. Check your network and try again.',
        );
    });
}
