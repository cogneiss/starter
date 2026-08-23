import { Badge } from '@/components/ui/badge';
import { EmptyValue } from '@/components/value/empty-value';

type Variant = 'default' | 'secondary' | 'destructive' | 'outline';

const VARIANTS: Record<string, Variant> = {
    active: 'default',
    accepted: 'default',
    verified: 'default',
    pending: 'secondary',
    invited: 'secondary',
    suspended: 'destructive',
    revoked: 'destructive',
    expired: 'destructive',
    failed: 'destructive',
};

/**
 * A status string rendered as a badge. Unknown statuses get the neutral
 * outline rather than a wrong colour.
 */
export function StatusBadge({
    status,
    variants = {},
}: {
    status?: string | null;
    variants?: Record<string, Variant>;
}) {
    if (status === null || status === undefined || status === '') {
        return <EmptyValue />;
    }

    const key = status.toLowerCase();
    const variant = variants[key] ?? VARIANTS[key] ?? 'outline';

    return (
        <Badge variant={variant} data-test="status-badge" data-status={key}>
            {status}
        </Badge>
    );
}
