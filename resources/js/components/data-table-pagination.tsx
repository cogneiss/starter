import { Button } from '@/components/ui/button';

type PaginationProps = {
    total: number;
    page: number;
    pages: number;
    onPage: (page: number) => void;
};

export function DataTablePagination({
    total,
    page,
    pages,
    onPage,
}: PaginationProps) {
    return (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <p data-test="table-total">
                {total} {total === 1 ? 'result' : 'results'}
            </p>

            <div className="flex items-center gap-2">
                <p data-test="table-page">
                    Page {page} of {pages}
                </p>
                <Button
                    size="sm"
                    variant="outline"
                    data-test="table-previous"
                    disabled={page <= 1}
                    onClick={() => onPage(page - 1)}
                >
                    Previous
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    data-test="table-next"
                    disabled={page >= pages}
                    onClick={() => onPage(page + 1)}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
