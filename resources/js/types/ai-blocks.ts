import type {
    AiConfirmBlock,
    AiFormBlock,
    AiListBlock,
    AiMarkdownBlock,
    AiMetricBlock,
    AiTableBlock,
    AiTextBlock,
} from '@/types/generated';

/**
 * The block union, discriminated on `type`.
 *
 * The generated types all carry the full `AiBlockType` union, so each member is
 * intersected with its own literal here. That is what makes the renderer's
 * `switch` narrow, and what makes a missing arm a type error.
 */
export type AiBlock =
    | (AiTextBlock & { type: 'text' })
    | (AiMarkdownBlock & { type: 'markdown' })
    | (AiTableBlock & { type: 'table' })
    | (AiListBlock & { type: 'list' })
    | (AiMetricBlock & { type: 'metric' })
    | (AiFormBlock & { type: 'form' })
    | (AiConfirmBlock & { type: 'confirm' });
