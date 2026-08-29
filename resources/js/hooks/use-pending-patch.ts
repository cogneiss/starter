import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Inline edits that answer immediately and put themselves right if they fail.
 *
 * Pending state is keyed by record, not held as one flag, because two rows can
 * be edited at once: each shows its own spinner, each keeps its own optimistic
 * value, and neither row ever displays the other's. The requests are sent
 * asynchronously for the same reason — a second edit must not cancel the first.
 *
 * A refused patch drops the optimistic value, so the row goes back to what the
 * server last said it was. The reason arrives as the flash toast the server
 * sent, which is the same way every other outcome in this application is
 * announced.
 */
export function usePendingPatch<T>(): {
    pending: Record<string, T>;
    patch: (key: string, value: T, url: string, data: RequestPayload) => void;
} {
    const [pending, setPending] = useState<Record<string, T>>({});

    function forget(key: string) {
        setPending((current) => {
            const next = { ...current };
            delete next[key];

            return next;
        });
    }

    function patch(key: string, value: T, url: string, data: RequestPayload) {
        setPending((current) => ({ ...current, [key]: value }));

        router.patch(url, data, {
            async: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => forget(key),
            onError: () => forget(key),
        });
    }

    return { pending, patch };
}
