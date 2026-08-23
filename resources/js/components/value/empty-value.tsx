/**
 * The one shared treatment for "there is no value here", so an empty cell looks
 * the same wherever it turns up.
 */
export function EmptyValue({ label = 'No value' }: { label?: string }) {
    return (
        <span className="text-muted-foreground" data-test="empty-value">
            <span aria-hidden="true">&mdash;</span>
            <span className="sr-only">{label}</span>
        </span>
    );
}
