import { Badge } from '@/components/ui/badge';
import { EmptyValue } from '@/components/value/empty-value';

/**
 * Shows the first `max` tags and collapses the rest into a "+N" chip that
 * carries the full list in its title.
 */
export function TagList({
    tags,
    max = 3,
}: {
    tags?: string[] | null;
    max?: number;
}) {
    if (tags === null || tags === undefined || tags.length === 0) {
        return <EmptyValue label="No tags" />;
    }

    const shown = tags.slice(0, max);
    const hidden = tags.slice(max);

    return (
        <span
            className="flex flex-wrap items-center gap-1"
            data-test="tag-list"
        >
            {shown.map((tag) => (
                <Badge key={tag} variant="secondary">
                    {tag}
                </Badge>
            ))}

            {hidden.length > 0 && (
                <Badge
                    variant="outline"
                    title={hidden.join(', ')}
                    data-test="tag-list-overflow"
                >
                    +{hidden.length}
                </Badge>
            )}
        </span>
    );
}
