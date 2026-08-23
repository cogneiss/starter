import { EmptyValue } from '@/components/value/empty-value';

/**
 * A bare host like `example.com` still has to become a real link, so anything
 * without a scheme gets `https://`.
 */
export function UrlValue({
    url,
    label,
}: {
    url?: string | null;
    label?: string;
}) {
    if (url === null || url === undefined || url === '') {
        return <EmptyValue />;
    }

    const href = /^https?:\/\//i.test(url) ? url : `https://${url}`;

    return (
        <a
            href={href}
            title={href}
            target="_blank"
            rel="noreferrer noopener"
            className="block max-w-full truncate underline-offset-4 hover:underline"
            data-test="url-value"
        >
            {label ?? url}
        </a>
    );
}
