import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { search } from '@/routes';
import type { SearchGroup, SearchResult } from '@/types/generated';

/**
 * How long the palette waits after the last keystroke before asking the server.
 * Long enough that typing a word is one request, short enough that the results
 * feel like they arrive as you type.
 */
const DEBOUNCE_MS = 250;

type Status = 'idle' | 'loading' | 'ready' | 'error';

/**
 * A result and where it sits in the flat, keyboard-navigable list. The palette
 * draws every hit from the label, description and URL the server sent, so a new
 * resource appears here without this component learning anything about it.
 */
type Positioned = { result: SearchResult; index: number };

function useDebouncedSearch(term: string, open: boolean) {
    const [groups, setGroups] = useState<SearchGroup[]>([]);
    const [status, setStatus] = useState<Status>('idle');
    const [attempt, setAttempt] = useState(0);

    const trimmed = term.trim();

    useEffect(() => {
        if (!open || trimmed === '') {
            setGroups([]);
            setStatus('idle');

            return;
        }

        // Every keystroke marks the palette busy straight away, so the skeleton
        // covers the debounce as well as the request itself.
        setStatus('loading');

        const controller = new AbortController();

        const timer = window.setTimeout(() => {
            fetch(search.url({ query: { q: trimmed } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(
                            `Search failed with status ${response.status}.`,
                        );
                    }

                    return response.json() as Promise<{
                        groups: SearchGroup[];
                    }>;
                })
                .then((payload) => {
                    setGroups(payload.groups);
                    setStatus('ready');
                })
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setStatus('error');
                    }
                });
        }, DEBOUNCE_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [trimmed, open, attempt]);

    const retry = useCallback(() => setAttempt((previous) => previous + 1), []);

    return { groups, status, retry };
}

export function CommandPalette() {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const [selected, setSelected] = useState(0);

    const { groups, status, retry } = useDebouncedSearch(term, open);

    // The groups are headings; the keyboard walks one flat list across them.
    const positioned = useMemo(() => {
        let index = 0;

        return groups.map((group) => ({
            group,
            results: group.results.map(
                (result): Positioned => ({
                    result,
                    index: index++,
                }),
            ),
        }));
    }, [groups]);

    const flat = useMemo(
        () => positioned.flatMap((entry) => entry.results),
        [positioned],
    );

    useEffect(() => setSelected(0), [groups]);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((previous) => !previous);
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    function reset(next: boolean) {
        setOpen(next);

        if (!next) {
            setTerm('');
            setSelected(0);
        }
    }

    function onListKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
        if (flat.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setSelected((previous) => (previous + 1) % flat.length);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setSelected(
                (previous) => (previous - 1 + flat.length) % flat.length,
            );
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            reset(false);
            router.visit(flat[selected].result.url);
        }
    }

    return (
        <Dialog open={open} onOpenChange={reset}>
            <DialogContent
                data-test="command-palette"
                className="top-24 max-w-lg translate-y-0 gap-3 p-0 sm:max-w-lg"
                showCloseButton={false}
            >
                <div className="border-b p-3">
                    <DialogTitle className="sr-only">Search</DialogTitle>
                    <DialogDescription className="sr-only">
                        Type to search, move with the arrow keys, and press
                        Enter to open a result.
                    </DialogDescription>
                    <Input
                        autoFocus
                        aria-label="Search"
                        aria-controls="command-palette-results"
                        aria-activedescendant={
                            flat.length > 0
                                ? `command-palette-result-${selected}`
                                : undefined
                        }
                        data-test="palette-input"
                        placeholder="Search…"
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        onKeyDown={onListKeyDown}
                    />
                </div>

                <div className="max-h-80 overflow-y-auto p-2">
                    {status === 'idle' && (
                        <p
                            className="p-2 text-sm text-muted-foreground"
                            data-test="palette-hint"
                        >
                            Start typing to search this organization.
                        </p>
                    )}

                    {status === 'loading' && (
                        <div
                            className="flex flex-col gap-2 p-1"
                            data-test="palette-skeleton"
                        >
                            {[0, 1, 2].map((row) => (
                                <Skeleton key={row} className="h-9 w-full" />
                            ))}
                        </div>
                    )}

                    {status === 'error' && (
                        <div
                            className="flex flex-col items-start gap-2 p-2"
                            data-test="palette-error"
                        >
                            <p className="text-sm text-muted-foreground">
                                Search is not answering right now.
                            </p>
                            <Button
                                size="sm"
                                variant="outline"
                                data-test="palette-retry"
                                onClick={retry}
                            >
                                Try again
                            </Button>
                        </div>
                    )}

                    {status === 'ready' && flat.length === 0 && (
                        <p
                            className="p-2 text-sm text-muted-foreground"
                            data-test="palette-empty"
                        >
                            Nothing matches that search.
                        </p>
                    )}

                    {status === 'ready' && flat.length > 0 && (
                        <div
                            id="command-palette-results"
                            role="listbox"
                            aria-label="Search results"
                        >
                            {positioned.map((entry) => (
                                <div
                                    key={entry.group.key}
                                    role="group"
                                    aria-label={entry.group.label}
                                    className="mb-2 last:mb-0"
                                >
                                    <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                        {entry.group.label}
                                    </p>
                                    {entry.results.map(({ result, index }) => (
                                        <div
                                            key={`${index}-${result.url}`}
                                            id={`command-palette-result-${index}`}
                                            role="option"
                                            aria-selected={index === selected}
                                            data-test="palette-result"
                                            data-selected={index === selected}
                                            className="rounded-md px-2 py-1.5 data-[selected=true]:bg-accent"
                                        >
                                            <span className="block text-sm">
                                                {result.label}
                                            </span>
                                            {result.description && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {result.description}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
