import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { index, pages as pageRoute } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type HealthCheck = {
    name: string;
    status: string;
    duration_ms: number;
};

type Props = {
    report: {
        status: string;
        checks: HealthCheck[];
    };
    pages: { key: string; label: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: index() }];

const statusTone: Record<string, string> = {
    ok: 'text-green-600 dark:text-green-400',
    degraded: 'text-amber-600 dark:text-amber-400',
    failed: 'text-red-600 dark:text-red-400',
};

export default function Health({ report, pages }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin — Health" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Platform health"
                    description="The same checks app:doctor and the /up endpoint run"
                />

                <nav
                    aria-label="Admin pages"
                    className="flex flex-wrap gap-2 text-sm"
                >
                    {pages.map((item) => (
                        <Link
                            key={item.key}
                            href={pageRoute({ page: item.key })}
                            className="rounded-md border px-3 py-1 text-muted-foreground hover:text-foreground"
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <p className="text-sm">
                    Overall:{' '}
                    <span
                        className={cn(
                            'font-medium',
                            statusTone[report.status] ?? '',
                        )}
                    >
                        {report.status}
                    </span>
                </p>

                <ul className="space-y-1 text-sm" aria-label="Health checks">
                    {report.checks.map((check) => (
                        <li key={check.name} className="flex flex-wrap gap-2">
                            <span className="font-medium">{check.name}</span>
                            <span
                                className={cn(
                                    statusTone[check.status] ??
                                        'text-muted-foreground',
                                )}
                            >
                                {check.status}
                            </span>
                            <span className="text-muted-foreground">
                                {check.duration_ms} ms
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </AppLayout>
    );
}
