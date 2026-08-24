import { ConfirmBlock } from '@/components/ai/blocks/ConfirmBlock';
import { FormBlock } from '@/components/ai/blocks/FormBlock';
import { ListBlock } from '@/components/ai/blocks/ListBlock';
import { MarkdownBlock } from '@/components/ai/blocks/MarkdownBlock';
import { MetricBlock } from '@/components/ai/blocks/MetricBlock';
import { TableBlock } from '@/components/ai/blocks/TableBlock';
import { TextBlock } from '@/components/ai/blocks/TextBlock';
import type { AiBlock } from '@/types/ai-blocks';

/**
 * One component per member of the union, chosen by an exhaustive `switch`.
 *
 * The default arm assigns to `never`, so adding a member without adding an arm
 * here is a type error rather than a blank space on the page. At runtime the
 * same arm renders nothing, which is what a payload the server let through but
 * this build does not know gets.
 */
function Block({ block }: { block: AiBlock }) {
    switch (block.type) {
        case 'text':
            return <TextBlock block={block} />;
        case 'markdown':
            return <MarkdownBlock block={block} />;
        case 'table':
            return <TableBlock block={block} />;
        case 'list':
            return <ListBlock block={block} />;
        case 'metric':
            return <MetricBlock block={block} />;
        case 'form':
            return <FormBlock block={block} />;
        case 'confirm':
            return <ConfirmBlock block={block} />;
        default: {
            const unknown: never = block;

            void unknown;

            return null;
        }
    }
}

export function AiBlocks({ blocks }: { blocks: AiBlock[] }) {
    return (
        <div className="flex flex-col gap-4" data-test="ai-blocks">
            {blocks.map((block, index) => (
                <Block key={index} block={block} />
            ))}
        </div>
    );
}
