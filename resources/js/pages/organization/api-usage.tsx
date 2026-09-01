import { Head } from '@inertiajs/react';
import { MetricBlock } from '@/components/ai/blocks/MetricBlock';
import { TableBlock } from '@/components/ai/blocks/TableBlock';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { apiUsage } from '@/routes/organization';
import type { ApiUsage, ApiUsageRow, BreadcrumbItem } from '@/types';
import type { AiBlock } from '@/types/ai-blocks';

type Props = {
    usage: ApiUsage;
};

type MetricBlockData = Extract<AiBlock, { type: 'metric' }>;

type TableBlockData = Extract<AiBlock, { type: 'table' }>;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API usage',
        href: apiUsage(),
    },
];

const metric = (label: string, value: string): MetricBlockData => ({
    type: 'metric',
    label,
    value,
    delta: null,
    trend: null,
});

/**
 * Rows are rendered by the same table block the AI usage page uses, so a figure
 * on this page and a figure on that one are laid out identically.
 */
const table = (nameColumn: string, rows: ApiUsageRow[]): TableBlockData => ({
    type: 'table',
    columns: [nameColumn, 'Requests'],
    rows: rows.map((row) => [row.name, row.requests.toLocaleString()]),
});

export default function ApiUsagePage({ usage }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API usage" />

            <h1 className="sr-only">API usage</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="API usage"
                        description="What this organization's tokens have asked of the read API in the last 30 days"
                    />

                    <div
                        className="grid grid-cols-2 gap-4 sm:grid-cols-4"
                        data-test="api-usage-totals"
                    >
                        <MetricBlock
                            block={metric(
                                'Requests',
                                usage.requests.toLocaleString(),
                            )}
                        />
                        <MetricBlock
                            block={metric(
                                'Throttled',
                                usage.throttled.toLocaleString(),
                            )}
                        />
                        <MetricBlock
                            block={metric(
                                `Limit (${usage.tier})`,
                                `${usage.limit.toLocaleString()}/min`,
                            )}
                        />
                        <MetricBlock
                            block={metric(
                                'Remaining',
                                usage.remaining.toLocaleString(),
                            )}
                        />
                    </div>

                    {usage.requests === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No token has called the API yet.
                        </p>
                    ) : (
                        <div className="space-y-6">
                            <section className="space-y-2">
                                <h2 className="text-sm font-medium">By day</h2>

                                <TableBlock block={table('Day', usage.daily)} />
                            </section>

                            <section className="space-y-2">
                                <h2 className="text-sm font-medium">
                                    By endpoint
                                </h2>

                                <TableBlock
                                    block={table('Endpoint', usage.endpoints)}
                                />
                            </section>

                            <section className="space-y-2">
                                <h2 className="text-sm font-medium">
                                    Top tokens
                                </h2>

                                <TableBlock
                                    block={table('Token', usage.tokens)}
                                />
                            </section>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
