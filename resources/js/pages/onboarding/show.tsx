import { Form, Head } from '@inertiajs/react';
import ActivationChecklist, {
    type Checklist,
} from '@/components/activation-checklist';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import onboarding from '@/routes/onboarding';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

/**
 * The same checklist every time, with the first unfinished step called out.
 * Leaving and coming back resumes; skipping is one button and it is permanent.
 */
export default function OnboardingShow({
    checklist,
}: {
    checklist: Checklist;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Get set up" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Welcome</h1>

                <ActivationChecklist checklist={checklist} />

                <Form {...onboarding.skip.form()}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="ghost"
                            disabled={processing}
                            className="self-start"
                        >
                            Skip for now
                        </Button>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
