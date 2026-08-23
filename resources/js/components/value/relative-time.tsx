import { EmptyValue } from '@/components/value/empty-value';

const DIVISIONS: { amount: number; unit: Intl.RelativeTimeFormatUnit }[] = [
    { amount: 60, unit: 'second' },
    { amount: 60, unit: 'minute' },
    { amount: 24, unit: 'hour' },
    { amount: 7, unit: 'day' },
    { amount: 4.34524, unit: 'week' },
    { amount: 12, unit: 'month' },
    { amount: Number.POSITIVE_INFINITY, unit: 'year' },
];

function relative(from: Date, to: Date, locale?: string): string {
    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    let duration = (from.getTime() - to.getTime()) / 1000;

    for (const division of DIVISIONS) {
        if (Math.abs(duration) < division.amount) {
            return formatter.format(Math.round(duration), division.unit);
        }

        duration /= division.amount;
    }

    return formatter.format(Math.round(duration), 'year');
}

/**
 * "3 days ago", with the absolute timestamp in the title for anyone who needs
 * the real one.
 */
export function RelativeTime({
    value,
    now,
    locale,
}: {
    value?: string | Date | null;
    now?: Date;
    locale?: string;
}) {
    if (value === null || value === undefined) {
        return <EmptyValue />;
    }

    const date = value instanceof Date ? value : new Date(value);
    const reference = now ?? new Date();

    return (
        <time
            dateTime={date.toISOString()}
            title={date.toLocaleString(locale)}
            data-test="relative-time"
        >
            {relative(date, reference, locale)}
        </time>
    );
}
