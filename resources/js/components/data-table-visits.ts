import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { parameters } from '@/components/data-table-query';
import type { ResourceQuery } from '@/types/generated';
import type { TypedResourceList } from '@/types/ui';

/**
 * How long the table waits after the last keystroke before asking the server.
 * The same pause the command palette uses, for the same reason: typing a word
 * should be one request, not one per letter.
 */
const SEARCH_DEBOUNCE_MS = 300;

/**
 * The shortest time the body stays marked pending. A local request can come
 * back in a few milliseconds, and a busy state that flickers reads as a glitch
 * rather than as progress. Long enough to register as feedback, short enough
 * that it is never the thing keeping a fast list waiting.
 */
const PENDING_FLOOR_MS = 600;

/**
 * The URL-driven half of a list screen: the search term, the visit that asks
 * the server for a new query, and the busy and failed states around it.
 */
export function useListVisits(
    list: Pick<TypedResourceList<unknown>, 'query'>,
    path: string,
    only: string[],
) {
    const [term, setTerm] = useState(list.query.q);
    const [pending, setPending] = useState(false);
    const [failed, setFailed] = useState(false);
    const debounce = useRef<number | undefined>(undefined);
    const startedAt = useRef(0);
    const pendingFloor = useRef<number | undefined>(undefined);

    useEffect(
        () => () => {
            window.clearTimeout(debounce.current);
            window.clearTimeout(pendingFloor.current);
        },
        [],
    );

    function visit(changes: Partial<ResourceQuery>) {
        const query: ResourceQuery = { ...list.query, page: 1, ...changes };

        router.get(path, parameters(query), {
            only,
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                // A floor left over from the previous visit would clear this one's busy state mid-request.
                window.clearTimeout(pendingFloor.current);
                startedAt.current = performance.now();
                setFailed(false);
                setPending(true);
            },
            onError: () => setFailed(true),
            onFinish: () => {
                // Hold the busy state to its floor before clearing it.
                pendingFloor.current = window.setTimeout(
                    () => setPending(false),
                    Math.max(
                        0,
                        PENDING_FLOOR_MS -
                            (performance.now() - startedAt.current),
                    ),
                );
            },
        });
    }

    function search(value: string) {
        setTerm(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(
            () => visit({ q: value }),
            SEARCH_DEBOUNCE_MS,
        );
    }

    return { term, search, visit, pending, failed, setFailed };
}
