import { EmptyValue } from '@/components/value/empty-value';

export function Money({
    amount,
    currency = 'USD',
    locale,
}: {
    amount?: number | null;
    currency?: string;
    locale?: string;
}) {
    if (amount === null || amount === undefined) {
        return <EmptyValue />;
    }

    return (
        <span
            className="text-right tabular-nums"
            data-test="money"
            data-currency={currency}
        >
            {new Intl.NumberFormat(locale, {
                style: 'currency',
                currency,
            }).format(amount)}
        </span>
    );
}
