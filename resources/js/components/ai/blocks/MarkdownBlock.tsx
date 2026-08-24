import type { AiBlock } from '@/types/ai-blocks';

/**
 * The HTML here was produced by the server's markdown renderer with HTML input
 * stripped, never by the model. That is the only reason setting it directly is
 * safe, so this component reads `html` and never `markdown`.
 */
export function MarkdownBlock({
    block,
}: {
    block: Extract<AiBlock, { type: 'markdown' }>;
}) {
    return (
        <div
            className="prose prose-sm dark:prose-invert max-w-none text-sm"
            data-test="ai-markdown-block"
            dangerouslySetInnerHTML={{ __html: block.html }}
        />
    );
}
