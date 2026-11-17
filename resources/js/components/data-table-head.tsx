import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { ColumnResizer } from '@/components/data-table-resizer';
import { TableHead } from '@/components/ui/table';

type HeadProps = {
    id: string;
    width: number | undefined;
    sort: string | undefined;
    active: boolean;
    ascending: boolean;
    placeholder: boolean;
    onSort: (dir: 'asc' | 'desc') => void;
    onResize: (id: string, to: number) => void;
    children: ReactNode;
};

export function DataTableHead({
    id,
    width,
    sort,
    active,
    ascending,
    placeholder,
    onSort,
    onResize,
    children,
}: HeadProps) {
    return (
        <TableHead
            style={{ width }}
            className="relative"
            aria-sort={
                active ? (ascending ? 'ascending' : 'descending') : 'none'
            }
        >
            {placeholder ? null : sort === undefined ? (
                children
            ) : (
                <button
                    type="button"
                    data-test={`sort-${sort}`}
                    className="flex items-center gap-1 hover:text-foreground"
                    onClick={() => onSort(active && ascending ? 'desc' : 'asc')}
                >
                    {children}
                    {active ? (
                        ascending ? (
                            <ArrowUp className="size-3.5" />
                        ) : (
                            <ArrowDown className="size-3.5" />
                        )
                    ) : (
                        <ChevronsUpDown className="size-3.5 opacity-50" />
                    )}
                </button>
            )}

            <ColumnResizer id={id} width={width ?? null} onResize={onResize} />
        </TableHead>
    );
}
