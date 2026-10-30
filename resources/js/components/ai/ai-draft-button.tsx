import { usePage } from '@inertiajs/react';
import { SparklesIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { postJson } from '@/lib/ai-blocks';
import { useTranslate } from '@/lib/i18n';
import { store } from '@/routes/ai-draft';

type Status = 'idle' | 'working' | 'done' | 'failed';

/**
 * A "Draft with AI" button for any text field. It posts the field's key and its
 * current text, and hands the drafted text to `onDraft`; saving still goes
 * through the form's own submit and validation. Renders nothing when the
 * organization has the feature off.
 */
export function AiDraftButton({
    field,
    value,
    onDraft,
}: {
    field: string;
    value: string;
    onDraft: (text: string) => void;
}) {
    const enabled = usePage().props.aiAssist;
    const t = useTranslate();
    const [status, setStatus] = useState<Status>('idle');

    if (!enabled) {
        return null;
    }

    const draft = () => {
        setStatus('working');

        postJson<{ text: string }>(store().url, { field, text: value })
            .then(({ text }) => {
                onDraft(text);
                setStatus('done');
            })
            .catch(() => setStatus('failed'));
    };

    return (
        <div className="flex items-center gap-2">
            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={status === 'working'}
                onClick={draft}
                data-test="ai-draft-button"
            >
                {status === 'working' ? (
                    <Spinner aria-hidden="true" role="presentation" />
                ) : (
                    <SparklesIcon aria-hidden="true" />
                )}
                {t(
                    status === 'working'
                        ? 'ai_draft.working'
                        : 'ai_draft.button',
                )}
            </Button>

            <p
                role="status"
                aria-live="polite"
                className={
                    status === 'failed'
                        ? 'text-sm text-destructive'
                        : 'text-sm text-muted-foreground'
                }
            >
                {status === 'done' && t('ai_draft.done')}
                {status === 'failed' && t('ai_draft.failed')}
            </p>
        </div>
    );
}
