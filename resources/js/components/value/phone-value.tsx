import { EmptyValue } from '@/components/value/empty-value';

export function PhoneValue({ phone }: { phone?: string | null }) {
    if (phone === null || phone === undefined || phone === '') {
        return <EmptyValue />;
    }

    // `tel:` wants digits, a leading plus and nothing else.
    const href = `tel:${phone.replace(/[^\d+]/g, '')}`;

    return (
        <a
            href={href}
            title={phone}
            className="block max-w-full truncate whitespace-nowrap underline-offset-4 hover:underline"
            data-test="phone-value"
        >
            {phone}
        </a>
    );
}
