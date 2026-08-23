import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { EmptyValue } from '@/components/value/empty-value';
import { useClipboard } from '@/hooks/use-clipboard';

export function CodeValue({ value }: { value?: string | null }) {
    const [copied, copy] = useClipboard();

    if (value === null || value === undefined || value === '') {
        return <EmptyValue />;
    }

    return (
        <span className="inline-flex items-center gap-1" data-test="code-value">
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                {value}
            </code>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={copied === value ? 'Copied' : `Copy ${value}`}
                onClick={() => void copy(value)}
                data-test="copy-code-button"
            >
                {copied === value ? <Check /> : <Copy />}
            </Button>
        </span>
    );
}
