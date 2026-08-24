import type { AiBlock } from '@/types/ai-blocks';
import type { AiMetricTrend } from '@/types/generated';

const TREND_LABELS: Record<AiMetricTrend, string> = {
    up: 'Up',
    down: 'Down',
    flat: 'Flat',
};

export function MetricBlock({
    block,
}: {
    block: Extract<AiBlock, { type: 'metric' }>;
}) {
    return (
        <div className="flex flex-col gap-1" data-test="ai-metric-block">
            <span className="text-xs text-muted-foreground">{block.label}</span>
            <span className="text-2xl font-semibold">{block.value}</span>
            {block.delta !== null && (
                <span className="text-xs" data-test="ai-metric-delta">
                    {block.trend === null
                        ? block.delta
                        : `${TREND_LABELS[block.trend]} ${block.delta}`}
                </span>
            )}
        </div>
    );
}
