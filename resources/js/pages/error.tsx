import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type ErrorProps = {
    status: number;
    message: string;
};

/**
 * The page behind every failed request that is not recoverable in place.
 *
 * It shows the sentence the server wrote for this failure and one way out. A
 * stack trace is never a message to a person, and neither is a bare status
 * code.
 */
export default function Error({ status, message }: ErrorProps) {
    return (
        <div
            className="flex min-h-svh flex-col items-center justify-center gap-4 p-8 text-center"
            data-test="error-page"
        >
            <Head title={`Error ${status}`} />
            <p className="font-mono text-sm text-muted-foreground">{status}</p>
            <p className="max-w-prose text-base" data-test="error-message">
                {message}
            </p>
            <Button render={<Link href={dashboard()} />} variant="outline">
                Back to the dashboard
            </Button>
        </div>
    );
}
