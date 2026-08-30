import { Head } from '@inertiajs/react';
import ActivationChecklist, {
    type Checklist,
} from '@/components/activation-checklist';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Widget = { label: string; value: number };

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function Dashboard({
    widgets,
    checklist,
}: {
    widgets: Record<string, Widget>;
    checklist: Checklist;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <h1 className="sr-only">Dashboard</h1>

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {checklist.complete || checklist.dismissed ? null : (
                    <ActivationChecklist checklist={checklist} />
                )}

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {Object.entries(widgets).map(([key, widget]) => (
                        <div
                            key={key}
                            data-widget={key}
                            className="flex aspect-video flex-col justify-end gap-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        >
                            <span className="text-3xl font-semibold tabular-nums">
                                {widget.value}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {widget.label}
                            </span>
                        </div>
                    ))}
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </AppLayout>
    );
}
