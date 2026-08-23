import { EmptyValue } from '@/components/value/empty-value';

/**
 * Locale-aware date, with the machine-readable value on the `<time>` element so
 * it stays parseable whatever the locale renders.
 */
export function DateValue({
    value,
    withTime = false,
    locale,
}: {
    value?: string | Date | null;
    withTime?: boolean;
    locale?: string;
}) {
    if (value === null || value === undefined) {
        return <EmptyValue />;
    }

    const date = value instanceof Date ? value : new Date(value);

    const formatted = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        ...(withTime ? { timeStyle: 'short' } : {}),
    }).format(date);

    return (
        <time dateTime={date.toISOString()} data-test="date-value">
            {formatted}
        </time>
    );
}
