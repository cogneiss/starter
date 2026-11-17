import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type BulkBarProps = {
    count: number;
    total: number;
    everyMatch: boolean;
    onEveryMatch: (every: boolean) => void;
    action: string;
    onAction: (value: string) => void;
    actions: Array<{ value: string; label: string }>;
    onApply: () => void;
};

export function DataTableBulkBar({
    count,
    total,
    everyMatch,
    onEveryMatch,
    action,
    onAction,
    actions,
    onApply,
}: BulkBarProps) {
    return (
        <div
            data-test="bulk-bar"
            className="flex flex-wrap items-center gap-3 rounded-md border p-3 text-sm"
        >
            <p data-test="bulk-count">{count} selected on this page</p>

            <label className="flex items-center gap-2">
                <Checkbox
                    data-test="bulk-every-match"
                    checked={everyMatch}
                    onCheckedChange={(checked) =>
                        onEveryMatch(checked === true)
                    }
                />
                Apply to all {total} matching
            </label>

            <select
                data-test="bulk-action"
                aria-label="Bulk action"
                value={action}
                onChange={(event) => onAction(event.target.value)}
                className="h-9 rounded-md border border-input bg-background px-2"
            >
                {actions.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>

            <Button size="sm" data-test="bulk-apply" onClick={onApply}>
                Apply
            </Button>
        </div>
    );
}
