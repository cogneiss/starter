import { router } from '@inertiajs/react';
import { SendHorizontal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AiBlocks } from '@/components/ai/blocks/AiBlocks';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { postJson } from '@/lib/ai-blocks';
import { useTranslate } from '@/lib/i18n';
import { transitionStyle } from '@/lib/motion';
import { show, store } from '@/routes/assistant';
import type { AssistantMessage, AssistantReply } from '@/types/assistant';

type Props = {
    conversationId: string | null;
    initialMessages: AssistantMessage[];
};

type Failure = 'quota' | 'failed' | null;

/**
 * `postJson` reports a failed request as an error naming its status, so a
 * refused quota is told apart from any other failure by that status.
 */
function failureFor(error: unknown): Failure {
    return error instanceof Error && error.message.includes('status 429')
        ? 'quota'
        : 'failed';
}

export function AssistantChat({ conversationId, initialMessages }: Props) {
    const t = useTranslate();
    const [messages, setMessages] = useState(initialMessages);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [failure, setFailure] = useState<Failure>(null);
    const bottom = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bottom.current?.scrollIntoView({ block: 'end' });
    }, [messages, sending]);

    async function submit(event: FormEvent) {
        event.preventDefault();

        const message = draft.trim();

        if (message === '' || sending) {
            return;
        }

        setSending(true);
        setFailure(null);
        setMessages((current) => [
            ...current,
            {
                id: `local-${current.length}`,
                role: 'user',
                text: message,
                blocks: [],
            },
        ]);
        setDraft('');

        try {
            const reply = await postJson<AssistantReply>(store().url, {
                message,
                conversation_id: conversationId,
            });

            if (conversationId === null) {
                router.visit(show(reply.conversation_id).url);

                return;
            }

            setMessages((current) => [
                ...current,
                {
                    id: `reply-${current.length}`,
                    role: 'assistant',
                    text: reply.text,
                    blocks: reply.blocks,
                },
            ]);
        } catch (error) {
            setFailure(failureFor(error));
            setDraft(message);
        } finally {
            setSending(false);
        }
    }

    return (
        <div className="flex h-full flex-col gap-4" data-test="assistant-chat">
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto">
                {messages.length === 0 && !sending && (
                    <p
                        className="py-10 text-center text-sm text-muted-foreground"
                        data-test="assistant-intro"
                    >
                        {t('assistant.intro')}
                    </p>
                )}

                {messages.map((message) => (
                    <div
                        key={message.id}
                        className={
                            message.role === 'user'
                                ? 'ml-auto max-w-[85%] rounded-xl bg-primary px-4 py-2 text-sm text-primary-foreground'
                                : 'max-w-[85%] rounded-xl border bg-card px-4 py-2 text-sm'
                        }
                        style={transitionStyle('rise')}
                        data-test={`assistant-message-${message.role}`}
                    >
                        <p className="mb-1 text-xs opacity-70">
                            {message.role === 'user'
                                ? t('assistant.you')
                                : t('assistant.assistant')}
                        </p>
                        <p className="whitespace-pre-wrap">{message.text}</p>
                        {message.blocks.length > 0 && (
                            <div className="mt-3">
                                <AiBlocks blocks={message.blocks} />
                            </div>
                        )}
                    </div>
                ))}

                {sending && (
                    <div
                        className="flex items-center gap-2 text-sm text-muted-foreground"
                        role="status"
                        data-test="assistant-sending"
                    >
                        <Spinner />
                        {t('assistant.sending')}
                    </div>
                )}

                {failure !== null && (
                    <p
                        className="rounded-md border border-destructive/40 px-3 py-2 text-sm text-destructive"
                        role="alert"
                        data-test={`assistant-${failure}`}
                    >
                        {t(`assistant.${failure}`)}
                    </p>
                )}

                <div ref={bottom} />
            </div>

            <form
                onSubmit={(event) => void submit(event)}
                className="flex items-end gap-2"
            >
                <textarea
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && !event.shiftKey) {
                            event.preventDefault();
                            event.currentTarget.form?.requestSubmit();
                        }
                    }}
                    rows={2}
                    maxLength={4000}
                    placeholder={t('assistant.placeholder')}
                    aria-label={t('assistant.placeholder')}
                    className="min-h-12 flex-1 resize-none rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    data-test="assistant-input"
                />
                <Button type="submit" disabled={sending || draft.trim() === ''}>
                    <SendHorizontal />
                    {t('assistant.send')}
                </Button>
            </form>
        </div>
    );
}
