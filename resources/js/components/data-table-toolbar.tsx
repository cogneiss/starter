import { Columns3 } from 'lucide-react';
import type { ComponentProps } from 'react';
import { parameters } from '@/components/data-table-query';
import { DataTableViews } from '@/components/data-table-views';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { ResourceQuery } from '@/types/generated';

type ToolbarProps = {
    label: string;
    term: string;
    onSearch: (value: string) => void;
    columns: Array<{ id: string; label: string }>;
    isVisible: (id: string) => boolean;
    onToggleColumn: (id: string) => void;
    saveable: string | undefined;
    searches: ComponentProps<typeof DataTableViews>['searches'];
    query: ResourceQuery;
    exportable: boolean;
    onExport: () => void;
    exported: boolean;
};

export function DataTableToolbar({
    label,
    term,
    onSearch,
    columns,
    isVisible,
    onToggleColumn,
    saveable,
    searches,
    query,
    exportable,
    onExport,
    exported,
}: ToolbarProps) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <Input
                type="search"
                aria-label={`Search ${label}`}
                data-test="table-search"
                placeholder="Search…"
                value={term}
                onChange={(event) => onSearch(event.target.value)}
                className="max-w-xs"
            />

            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            size="sm"
                            variant="outline"
                            data-test="column-controls"
                        />
                    }
                >
                    <Columns3 className="size-4" />
                    Columns
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    {columns.map((column) => (
                        <DropdownMenuCheckboxItem
                            key={column.id}
                            data-test={`column-${column.id}`}
                            checked={isVisible(column.id)}
                            onCheckedChange={() => onToggleColumn(column.id)}
                        >
                            {column.label}
                        </DropdownMenuCheckboxItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>

            {saveable !== undefined && (
                <DataTableViews
                    searches={searches}
                    resource={saveable}
                    current={parameters(query)}
                />
            )}

            {exportable && (
                <Button
                    size="sm"
                    variant="outline"
                    data-test="table-export"
                    onClick={onExport}
                >
                    Export CSV
                </Button>
            )}

            {exported && (
                <p
                    data-test="export-ready"
                    className="text-sm text-muted-foreground"
                >
                    Export downloaded.
                </p>
            )}
        </div>
    );
}
