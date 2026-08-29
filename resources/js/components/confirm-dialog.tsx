import {
    createContext,
    use,
    useCallback,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * What to ask, and how much the answer costs.
 *
 * `intent` is the kind of thing being confirmed, not a colour: the dialog
 * decides how a destructive answer looks, so no caller has to remember which
 * button variant means "this cannot be undone".
 */
export type ConfirmRequest = {
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    intent?: 'ordinary' | 'destructive';
};

type ConfirmFn = (request: ConfirmRequest) => Promise<boolean>;

const ConfirmContext = createContext<ConfirmFn | null>(null);

/**
 * The one place this application asks "are you sure?".
 *
 * Callers await an answer and get `true` only from the confirm control. Escape,
 * the cancel control and any other way of dismissing the dialog all resolve
 * `false`, so a caller can never mistake "went away" for "said yes".
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [request, setRequest] = useState<ConfirmRequest | null>(null);
    const settle = useRef<((answer: boolean) => void) | null>(null);

    const confirm = useCallback<ConfirmFn>((next) => {
        // A second question replaces the first, and the first is answered no
        // rather than left hanging.
        settle.current?.(false);

        return new Promise<boolean>((resolve) => {
            settle.current = resolve;
            setRequest(next);
        });
    }, []);

    function answer(response: boolean) {
        settle.current?.(response);
        settle.current = null;
        setRequest(null);
    }

    return (
        <ConfirmContext value={confirm}>
            {children}

            <Dialog
                open={request !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        answer(false);
                    }
                }}
            >
                <DialogContent data-test="confirm-dialog">
                    <DialogTitle>{request?.title}</DialogTitle>
                    <DialogDescription>
                        {request?.description}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            data-test="confirm-cancel"
                            onClick={() => answer(false)}
                        >
                            {request?.cancelLabel ?? 'Cancel'}
                        </Button>
                        <Button
                            variant={
                                request?.intent === 'destructive'
                                    ? 'destructive'
                                    : 'default'
                            }
                            data-test="confirm-proceed"
                            onClick={() => answer(true)}
                        >
                            {request?.confirmLabel ?? 'Continue'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ConfirmContext>
    );
}

/** Ask the question, await the answer. */
export function useConfirm(): ConfirmFn {
    const confirm = use(ConfirmContext);

    if (confirm === null) {
        throw new Error('useConfirm() needs a <ConfirmProvider> above it.');
    }

    return confirm;
}
