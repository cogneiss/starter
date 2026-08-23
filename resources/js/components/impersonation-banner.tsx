import { Form, usePage } from '@inertiajs/react';
import UserImpersonationController from '@/actions/App/Http/Controllers/UserImpersonationController';
import { Button } from '@/components/ui/button';

export function ImpersonationBanner() {
    const impersonating = usePage().props.impersonating;

    if (!impersonating) {
        return null;
    }

    return (
        <div className="flex w-full flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950">
            <span>
                You are impersonating this account as {impersonating.name}.
            </span>

            <Form {...UserImpersonationController.destroy.form()}>
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    data-test="stop-impersonation-button"
                >
                    Stop impersonating
                </Button>
            </Form>
        </div>
    );
}
