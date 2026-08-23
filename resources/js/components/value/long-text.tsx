import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { EmptyValue } from '@/components/value/empty-value';

export function LongText({
    text,
    lines = 3,
}: {
    text?: string | null;
    lines?: number;
}) {
    const [expanded, setExpanded] = useState(false);

    if (text === null || text === undefined || text === '') {
        return <EmptyValue />;
    }

    return (
        <div className="flex flex-col items-start gap-1" data-test="long-text">
            <p
                className={expanded ? undefined : 'overflow-hidden'}
                style={
                    expanded
                        ? undefined
                        : {
                              display: '-webkit-box',
                              WebkitBoxOrient: 'vertical',
                              WebkitLineClamp: lines,
                          }
                }
            >
                {text}
            </p>

            <Button
                type="button"
                variant="link"
                size="sm"
                className="px-0"
                aria-expanded={expanded}
                onClick={() => setExpanded(!expanded)}
                data-test="long-text-toggle"
            >
                {expanded ? 'Show less' : 'Show more'}
            </Button>
        </div>
    );
}
