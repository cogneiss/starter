import type { AiBlock } from '@/types/ai-blocks';

export function TextBlock({
    block,
}: {
    block: Extract<AiBlock, { type: 'text' }>;
}) {
    return (
        <p className="text-sm" data-test="ai-text-block">
            {block.text}
        </p>
    );
}
