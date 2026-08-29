import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { emptyStateCopy } from '@/lib/empty-state-copy';

type EmptyStateProps = {
    /** The screen this emptiness belongs to, as `empty-state-copy` keys it. */
    resource: string;
};

/**
 * The one way this application says "there is nothing here".
 *
 * Every empty list, panel and result set renders this, so the wording, spacing
 * and the offer of something to do next are decided once rather than invented
 * per screen.
 */
export function EmptyState({ resource }: EmptyStateProps) {
    const { title, body, action } = emptyStateCopy(resource);

    return (
        <div
            className="flex flex-col items-center gap-2 px-4 py-10 text-center"
            data-test="empty-state"
        >
            <p className="text-sm font-medium">{title}</p>
            <p className="max-w-prose text-sm text-muted-foreground">{body}</p>
            {action && (
                <Button
                    size="sm"
                    variant="outline"
                    render={<Link href={action.href} />}
                >
                    {action.label}
                </Button>
            )}
        </div>
    );
}
