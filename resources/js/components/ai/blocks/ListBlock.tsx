import type { AiBlock } from '@/types/ai-blocks';

export function ListBlock({
    block,
}: {
    block: Extract<AiBlock, { type: 'list' }>;
}) {
    const items = block.items.map((item, index) => <li key={index}>{item}</li>);

    return block.ordered ? (
        <ol className="list-decimal pl-5 text-sm" data-test="ai-list-block">
            {items}
        </ol>
    ) : (
        <ul className="list-disc pl-5 text-sm" data-test="ai-list-block">
            {items}
        </ul>
    );
}
