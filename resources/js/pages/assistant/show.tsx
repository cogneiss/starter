import { Head, Link } from '@inertiajs/react';
import { AssistantChat } from '@/components/assistant-chat';
import AppLayout from '@/layouts/app-layout';
import { useTranslate } from '@/lib/i18n';
import { index } from '@/routes/assistant';
import type { AssistantMessage } from '@/types/assistant';

type Props = {
    conversation: { id: string; title: string } | null;
    messages: AssistantMessage[];
};

export default function AssistantShow({ conversation, messages }: Props) {
    const t = useTranslate();

    return (
        <>
            <Head title={conversation?.title ?? t('assistant.new')} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="truncate text-lg font-semibold">
                        {conversation?.title ?? t('assistant.new')}
                    </h1>
                    <Link
                        href={index()}
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        {t('assistant.back')}
                    </Link>
                </div>

                <AssistantChat
                    key={conversation?.id ?? 'new'}
                    conversationId={conversation?.id ?? null}
                    initialMessages={messages}
                />
            </div>
        </>
    );
}

AssistantShow.layout = [
    AppLayout,
    { breadcrumbs: [{ title: 'Assistant', href: index() }] },
];
