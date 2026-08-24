import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/ai-confirm';
import type { AiBlock } from '@/types/ai-blocks';

/**
 * The only block that can change anything, and it still cannot do it alone —
 * the button carries a token id, and the server decides whether that token is
 * live, unspent and the caller's to spend.
 */
export function ConfirmBlock({
    block,
}: {
    block: Extract<AiBlock, { type: 'confirm' }>;
}) {
    return (
        <div
            className="flex flex-col gap-2 rounded-md border p-3"
            data-test="ai-confirm-block"
        >
            <p className="text-sm">{block.summary}</p>

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    size="sm"
                    onClick={() => router.post(store(block.token).url)}
                    data-test="ai-confirm-submit"
                >
                    Confirm
                </Button>

                <span className="text-xs text-muted-foreground">
                    Expires {new Date(block.expires_at).toLocaleTimeString()}
                </span>
            </div>
        </div>
    );
}
