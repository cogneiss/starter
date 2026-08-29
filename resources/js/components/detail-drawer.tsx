import { router, usePage } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { RecordPeek } from '@/types';

/**
 * A record read without leaving the list.
 *
 * The drawer is opened by the address bar — `?peek=<id>` — so a row can be
 * linked to, shared and reopened, and it renders the `peek` page prop and
 * nothing else. It never asks for a record itself: whatever scope the
 * controller looked the record up in is the only way one reaches this screen,
 * which is why an id from another organization is a 404 rather than a drawer
 * with someone else's details in it.
 */
export function DetailDrawer() {
    const peek = usePage().props.peek as RecordPeek | null;

    /**
     * Closing drops the parameter and hands the keyboard back to the row that
     * opened the drawer, so a person reading down a list does not lose their
     * place on Escape.
     */
    function close() {
        const opener = `[data-test="peek-${peek?.id}"]`;
        const url = new URL(window.location.href);
        url.searchParams.delete('peek');

        router.visit(url.pathname + url.search, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                window.setTimeout(() => {
                    const trigger = document.querySelector(opener);

                    if (trigger instanceof HTMLElement) {
                        trigger.focus();
                    }
                }, 0);
            },
        });
    }

    return (
        <Dialog
            open={peek !== null}
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent data-test="detail-drawer">
                <DialogTitle>{peek?.title}</DialogTitle>
                <DialogDescription>
                    Everything this record carries.
                </DialogDescription>

                <dl className="grid gap-2">
                    {Object.entries(peek?.fields ?? {}).map(
                        ([label, value]) => (
                            <div
                                key={label}
                                className="flex items-baseline justify-between gap-4"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd
                                    data-test={`peek-field-${label.toLowerCase()}`}
                                >
                                    {value}
                                </dd>
                            </div>
                        ),
                    )}
                </dl>
            </DialogContent>
        </Dialog>
    );
}
