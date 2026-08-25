import { Head } from '@inertiajs/react';
import { MetricBlock } from '@/components/ai/blocks/MetricBlock';
import { TableBlock } from '@/components/ai/blocks/TableBlock';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { aiUsage } from '@/routes/organization';
import type { AiUsage, AiUsageRow, BreadcrumbItem } from '@/types';
import type { AiBlock } from '@/types/ai-blocks';

type Props = {
    usage: AiUsage;
};

type MetricBlockData = Extract<AiBlock, { type: 'metric' }>;

type TableBlockData = Extract<AiBlock, { type: 'table' }>;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'AI usage',
        href: aiUsage(),
    },
];

const money = (micros: number) =>
    `$${(micros / 1_000_000).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const metric = (label: string, value: string): MetricBlockData => ({
    type: 'metric',
    label,
    value,
    delta: null,
    trend: null,
});

/**
 * Rows are rendered by the same table block an agent's answer uses, so a figure
 * on this page and a figure in a briefing are laid out identically.
 */
const table = (rows: AiUsageRow[]): TableBlockData => ({
    type: 'table',
    columns: ['Name', 'Runs', 'Tokens', 'Spend'],
    rows: rows.map((row) => [
        row.name,
        row.runs.toLocaleString(),
        row.tokens.toLocaleString(),
        money(row.cost_micros),
    ]),
});

export default function AiUsagePage({ usage }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI usage" />

            <h1 className="sr-only">AI usage</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="AI usage"
                        description="What this organization has asked of the AI layer in the last 30 days"
                    />

                    <div
                        className="grid grid-cols-2 gap-4 sm:grid-cols-4"
                        data-test="ai-usage-totals"
                    >
                        <MetricBlock
                            block={metric('Runs', usage.runs.toLocaleString())}
                        />
                        <MetricBlock
                            block={metric(
                                'Blocked',
                                usage.blocked.toLocaleString(),
                            )}
                        />
                        <MetricBlock
                            block={metric(
                                'Tokens',
                                usage.tokens.toLocaleString(),
                            )}
                        />
                        <MetricBlock
                            block={metric('Spend', money(usage.cost_micros))}
                        />
                    </div>

                    {usage.agents.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No agent has run here yet.
                        </p>
                    ) : (
                        <div className="space-y-6">
                            <section className="space-y-2">
                                <h2 className="text-sm font-medium">
                                    By agent
                                </h2>

                                <TableBlock block={table(usage.agents)} />
                            </section>

                            <section className="space-y-2">
                                <h2 className="text-sm font-medium">By tier</h2>

                                <TableBlock block={table(usage.tiers)} />
                            </section>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
