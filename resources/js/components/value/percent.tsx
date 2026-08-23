import { EmptyValue } from '@/components/value/empty-value';

/**
 * `value` is a ratio: 0.125 renders as 12.5%.
 */
export function Percent({
    value,
    precision = 1,
    signed = false,
    locale,
}: {
    value?: number | null;
    precision?: number;
    signed?: boolean;
    locale?: string;
}) {
    if (value === null || value === undefined) {
        return <EmptyValue />;
    }

    return (
        <span className="text-right tabular-nums" data-test="percent">
            {new Intl.NumberFormat(locale, {
                style: 'percent',
                minimumFractionDigits: precision,
                maximumFractionDigits: precision,
                signDisplay: signed ? 'exceptZero' : 'auto',
            }).format(value)}
        </span>
    );
}
