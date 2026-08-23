import { Badge } from '@/components/ui/badge';
import { EmptyValue } from '@/components/value/empty-value';

export function BooleanPill({
    value,
    yes = 'Yes',
    no = 'No',
}: {
    value?: boolean | null;
    yes?: string;
    no?: string;
}) {
    if (value === null || value === undefined) {
        return <EmptyValue />;
    }

    return (
        <Badge
            variant={value ? 'default' : 'outline'}
            data-test="boolean-pill"
            data-value={value ? 'true' : 'false'}
        >
            {value ? yes : no}
        </Badge>
    );
}
