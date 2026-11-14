import type { AiBlock } from '@/types/ai-blocks';

export type AssistantConversationSummary = {
    id: string;
    title: string;
    updated_at: string;
};

export type AssistantMessage = {
    id: string;
    role: 'user' | 'assistant';
    text: string;
    blocks: AiBlock[];
};

export type AssistantReply = {
    conversation_id: string;
    text: string;
    blocks: AiBlock[];
};
