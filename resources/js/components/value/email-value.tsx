import { EmptyValue } from '@/components/value/empty-value';

export function EmailValue({ email }: { email?: string | null }) {
    if (email === null || email === undefined || email === '') {
        return <EmptyValue />;
    }

    return (
        <a
            href={`mailto:${email}`}
            title={email}
            className="block max-w-full truncate underline-offset-4 hover:underline"
            data-test="email-value"
        >
            {email}
        </a>
    );
}
