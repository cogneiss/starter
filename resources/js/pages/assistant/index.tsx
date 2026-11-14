import { Deferred, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { useTranslate } from '@/lib/i18n';
import { create, index, show } from '@/routes/assistant';
import type { BreadcrumbItem } from '@/types';
import type { AssistantConversationSummary } from '@/types/assistant';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Assistant', href: index() }];

function ConversationSkeleton() {
    return (
        <div className="flex flex-col gap-2" data-test="assistant-skeleton">
            {[0, 1, 2].map((row) => (
                <Skeleton key={row} className="h-12 w-full" />
            ))}
        </div>
    );
}

function ConversationList({
    conversations,
}: {
    conversations?: AssistantConversationSummary[];
}) {
    const t = useTranslate();

    if (conversations === undefined || conversations.length === 0) {
        return (
            <div
                className="flex flex-col items-center gap-2 px-4 py-10 text-center"
                data-test="empty-state"
            >
                <p className="text-sm font-medium">
                    {t('assistant.empty_title')}
                </p>
                <p className="max-w-prose text-sm text-muted-foreground">
                    {t('assistant.empty_body')}
                </p>
            </div>
        );
    }

    return (
        <ul className="divide-y rounded-xl border" data-test="assistant-list">
            {conversations.map((conversation) => (
                <li key={conversation.id}>
                    <Link
                        href={show(conversation.id)}
                        className="flex items-center justify-between gap-4 px-4 py-3 text-sm hover:bg-muted/50"
                    >
                        <span className="truncate">{conversation.title}</span>
                        <time
                            dateTime={conversation.updated_at}
                            className="shrink-0 text-xs text-muted-foreground"
                        >
                            {new Date(
                                conversation.updated_at,
                            ).toLocaleDateString()}
                        </time>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export default function AssistantIndex({
    conversations,
}: {
    conversations?: AssistantConversationSummary[];
}) {
    const t = useTranslate();

    return (
        <>
            <Head title={t('assistant.title')} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-semibold">
                        {t('assistant.recent')}
                    </h1>
                    <Button render={<Link href={create()} />}>
                        {t('assistant.new')}
                    </Button>
                </div>

                <Deferred
                    data="conversations"
                    fallback={<ConversationSkeleton />}
                >
                    <ConversationList conversations={conversations} />
                </Deferred>
            </div>
        </>
    );
}

AssistantIndex.layout = [AppLayout, { breadcrumbs }];
