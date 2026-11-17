import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { TableBody, TableCell, TableRow } from '@/components/ui/table';

type BodyProps = {
    pending: boolean;
    failed: boolean;
    empty: boolean;
    span: number;
    emptyKey: string;
    onRetry: () => void;
    children: ReactNode;
};

export function DataTableBody({
    pending,
    failed,
    empty,
    span,
    emptyKey,
    onRetry,
    children,
}: BodyProps) {
    return (
        <TableBody
            data-test="table-body"
            data-pending={pending}
            aria-busy={pending}
            className="transition-opacity data-[pending=true]:opacity-50"
        >
            {failed && (
                <TableRow>
                    <TableCell
                        colSpan={span}
                        data-test="table-error"
                        className="text-muted-foreground"
                    >
                        That list did not load.{' '}
                        <Button
                            size="sm"
                            variant="outline"
                            data-test="table-retry"
                            onClick={onRetry}
                        >
                            Try again
                        </Button>
                    </TableCell>
                </TableRow>
            )}

            {!failed && empty && (
                <TableRow>
                    <TableCell
                        colSpan={span}
                        data-test="table-empty"
                        className="text-muted-foreground"
                    >
                        <EmptyState resource={emptyKey} />
                    </TableCell>
                </TableRow>
            )}

            {children}
        </TableBody>
    );
}
