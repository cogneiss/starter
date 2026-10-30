import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    type ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslate } from '@/lib/i18n';
import { transitionStyle } from '@/lib/motion';
import type { ApiUsageRow } from '@/types';

/**
 * The organization's API requests per day, drawn from a deferred prop.
 *
 * `rows` is undefined until the deferred request answers, and the skeleton
 * holds the chart's place so nothing jumps when the data arrives.
 */
export default function ActivityChart({ rows }: { rows?: ApiUsageRow[] }) {
    const t = useTranslate();
    const total = rows?.reduce((sum, row) => sum + row.requests, 0) ?? 0;
    const config = {
        requests: { label: t('dashboard.activity.requests') },
    } satisfies ChartConfig;

    return (
        <Card data-test="activity-chart">
            <CardHeader>
                <CardTitle>{t('dashboard.activity.title')}</CardTitle>
                <CardDescription>
                    {t('dashboard.activity.description')}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {rows === undefined ? (
                    <Skeleton
                        data-test="activity-chart-skeleton"
                        className="aspect-[3/1] min-h-48 w-full"
                    />
                ) : rows.length === 0 ? (
                    <p className="py-12 text-center text-sm text-muted-foreground">
                        {t('dashboard.activity.empty')}
                    </p>
                ) : (
                    <>
                        <p className="sr-only">
                            {`${total.toLocaleString()} ${t('dashboard.activity.summary')}`}
                        </p>
                        <ChartContainer
                            config={config}
                            className="aspect-[3/1] min-h-48 w-full"
                            aria-hidden="true"
                        >
                            <BarChart data={rows} accessibilityLayer={false}>
                                <CartesianGrid vertical={false} />
                                <XAxis
                                    dataKey="name"
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    minTickGap={24}
                                />
                                <YAxis
                                    allowDecimals={false}
                                    tickLine={false}
                                    axisLine={false}
                                    width={32}
                                />
                                <ChartTooltip
                                    content={<ChartTooltipContent />}
                                />
                                <Bar
                                    dataKey="requests"
                                    fill="var(--color-chart-3)"
                                    radius={4}
                                    animationDuration={Number.parseInt(
                                        String(
                                            transitionStyle('rise')
                                                .transitionDuration,
                                        ),
                                        10,
                                    )}
                                />
                            </BarChart>
                        </ChartContainer>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
