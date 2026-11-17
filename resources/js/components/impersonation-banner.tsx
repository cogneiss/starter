import { Form, usePage } from '@inertiajs/react';
import UserImpersonationController from '@/actions/App/Http/Controllers/UserImpersonationController';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/lib/i18n';

export function ImpersonationBanner() {
    const impersonating = usePage().props.impersonating;
    const t = useTranslate();
    const name = impersonating?.name ?? null;

    if (!impersonating) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label={t('impersonation.region')}
            className="flex w-full flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-amber-950"
        >
            <span>
                {name === null
                    ? t('impersonation.banner_unknown')
                    : t('impersonation.banner').replace(':name', () => name)}
            </span>

            <Form {...UserImpersonationController.destroy.form()}>
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    data-test="stop-impersonation-button"
                >
                    {t('impersonation.stop')}
                </Button>
            </Form>
        </div>
    );
}
