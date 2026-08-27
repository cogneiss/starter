import { router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    tableFeatures,
    useTable,
    type ColumnDef,
    type RowData,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataTableFilters } from '@/components/data-table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ResourceList, ResourceQuery } from '@/types/generated';

/**
 * How long the table waits after the last keystroke before asking the server.
 * The same pause the command palette uses, for the same reason: typing a word
 * should be one request, not one per letter.
 */
const SEARCH_DEBOUNCE_MS = 300;

/**
 * The shortest time the body stays marked pending. A local request can come
 * back in a few milliseconds, and a busy state that flickers reads as a glitch
 * rather than as progress. Long enough to register as feedback, short enough
 * that it is never the thing keeping a fast list waiting.
 */
const PENDING_FLOOR_MS = 600;

/**
 * A column may name the server-side column it sorts by. Nothing else is
 * sortable: the header offers exactly the orders `sortable()` allows, so a
 * click can never ask for a column the resource does not expose.
 */
type SortMeta = { sort?: string };

const features = tableFeatures({ columnMeta: {} as SortMeta });

type DataTableColumns<TRow extends RowData> = Array<
    // eslint-disable-next-line typescript/no-explicit-any -- the value type varies per column; this is the library's own signature.
    ColumnDef<typeof features, TRow, any>
>;

/**
 * The column helper for a row type, pre-bound to the table's feature set so a
 * page defines columns without repeating the generics.
 */
export function dataTableColumns<TRow extends RowData>() {
    return createColumnHelper<typeof features, TRow>();
}

type DataTableProps<TRow extends RowData> = {
    /** The page of rows and the query that produced it, straight from the server. */
    list: ResourceList;
    columns: DataTableColumns<TRow>;
    /** Props to reload; everything else on the page is left alone. */
    only: string[];
    /** Accessible name for the table and its search box. */
    label: string;
    rowId: (row: TRow) => string;
    /** What to show when the query matched nothing. */
    empty?: string;
};

/**
 * A list screen: search, sort and pagination, all decided by the server.
 *
 * The component holds no copy of the list state. Every control writes to the
 * URL and reloads the list prop, so the rendered table, the address bar and a
 * shared link always agree, and the back button walks the searches a person
 * actually ran.
 */
export function DataTable<TRow extends RowData>({
    list,
    columns,
    only,
    label,
    rowId,
    empty = 'Nothing matches that search.',
}: DataTableProps<TRow>) {
    const path = usePage().url.split('?')[0];
    const [term, setTerm] = useState(list.query.q);
    const [pending, setPending] = useState(false);
    const [failed, setFailed] = useState(false);
    const debounce = useRef<number | undefined>(undefined);
    const startedAt = useRef(0);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    function visit(changes: Partial<ResourceQuery>) {
        const query: ResourceQuery = { ...list.query, page: 1, ...changes };

        router.get(path, parameters(query), {
            only,
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                startedAt.current = performance.now();
                setFailed(false);
                setPending(true);
            },
            onError: () => setFailed(true),
            onFinish: () => {
                // Hold the busy state to its floor before clearing it.
                window.setTimeout(
                    () => setPending(false),
                    Math.max(
                        0,
                        PENDING_FLOOR_MS -
                            (performance.now() - startedAt.current),
                    ),
                );
            },
        });
    }

    function search(value: string) {
        setTerm(value);
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(
            () => visit({ q: value }),
            SEARCH_DEBOUNCE_MS,
        );
    }

    const rows = list.rows as TRow[];

    const table = useTable({
        features,
        columns,
        data: rows,
        getRowId: (row: TRow) => rowId(row),
    });

    return (
        <div className="flex flex-col gap-4" data-test="data-table">
            <Input
                type="search"
                aria-label={`Search ${label}`}
                data-test="table-search"
                placeholder="Search…"
                value={term}
                onChange={(event) => search(event.target.value)}
                className="max-w-xs"
            />

            <DataTableFilters
                filters={list.filters}
                onChange={(key, value) => {
                    const filters = { ...list.query.filters };

                    if (value === null) {
                        delete filters[key];
                    } else {
                        filters[key] = value;
                    }

                    visit({ filters });
                }}
                onClear={() => visit({ filters: {} })}
            />

            <Table aria-label={label}>
                <TableHeader>
                    {table.getHeaderGroups().map((group) => (
                        <TableRow key={group.id}>
                            {group.headers.map((header) => {
                                const sort = header.column.columnDef.meta?.sort;
                                const active =
                                    sort !== undefined &&
                                    sort === list.query.sort;
                                const ascending = list.query.dir === 'asc';

                                return (
                                    <TableHead
                                        key={header.id}
                                        aria-sort={
                                            active
                                                ? ascending
                                                    ? 'ascending'
                                                    : 'descending'
                                                : 'none'
                                        }
                                    >
                                        {header.isPlaceholder ? null : sort ===
                                          undefined ? (
                                            <table.FlexRender header={header} />
                                        ) : (
                                            <button
                                                type="button"
                                                data-test={`sort-${sort}`}
                                                className="flex items-center gap-1 hover:text-foreground"
                                                onClick={() =>
                                                    visit({
                                                        sort,
                                                        dir:
                                                            active && ascending
                                                                ? 'desc'
                                                                : 'asc',
                                                    })
                                                }
                                            >
                                                <table.FlexRender
                                                    header={header}
                                                />
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
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableHeader>

                <TableBody
                    data-test="table-body"
                    data-pending={pending}
                    aria-busy={pending}
                    className="transition-opacity data-[pending=true]:opacity-50"
                >
                    {failed && (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                data-test="table-error"
                                className="text-muted-foreground"
                            >
                                That list did not load.{' '}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    data-test="table-retry"
                                    onClick={() => visit({})}
                                >
                                    Try again
                                </Button>
                            </TableCell>
                        </TableRow>
                    )}

                    {!failed && rows.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                data-test="table-empty"
                                className="text-muted-foreground"
                            >
                                {empty}
                            </TableCell>
                        </TableRow>
                    )}

                    {!failed &&
                        table.getRowModel().rows.map((row) => (
                            <TableRow key={row.id} data-test={`row-${row.id}`}>
                                {row.getAllCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        <table.FlexRender cell={cell} />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                </TableBody>
            </Table>

            <div className="flex items-center justify-between text-sm text-muted-foreground">
                <p data-test="table-total">
                    {list.total} {list.total === 1 ? 'result' : 'results'}
                </p>

                <div className="flex items-center gap-2">
                    <p data-test="table-page">
                        Page {list.query.page} of {list.pages}
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        data-test="table-previous"
                        disabled={list.query.page <= 1}
                        onClick={() => visit({ page: list.query.page - 1 })}
                    >
                        Previous
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        data-test="table-next"
                        disabled={list.query.page >= list.pages}
                        onClick={() => visit({ page: list.query.page + 1 })}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}

/**
 * The query as URL parameters, with anything the server would have defaulted to
 * left out. A first visit has a clean address bar; every deviation from the
 * default is visible in it.
 */
function parameters(query: ResourceQuery): QueryParameters {
    const parameters: QueryParameters = {};

    if (query.q !== '') {
        parameters.q = query.q;
    }

    if (query.sort !== null) {
        parameters.sort = query.sort;
        parameters.dir = query.dir;
    }

    if (query.page > 1) {
        parameters.page = query.page;
    }

    const filters: Record<string, string | string[] | Record<string, string>> =
        {};

    for (const [key, value] of Object.entries(query.filters)) {
        const serialized = serialize(value);

        if (serialized !== null) {
            filters[key] = serialized;
        }
    }

    if (Object.keys(filters).length > 0) {
        parameters.f = filters;
    }

    return parameters;
}

type QueryParameters = Record<
    string,
    string | number | Record<string, string | string[] | Record<string, string>>
>;

/**
 * One filter value as the server writes it back out: booleans as `1`/`0`, bounds
 * as strings with the empty ones left out. The two sides serialize the same way
 * on purpose — a URL the table builds must parse back into the query it drew.
 */
function serialize(
    value: ResourceQuery['filters'][string],
): string | string[] | Record<string, string> | null {
    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? null : value;
    }

    const bounds: Record<string, string> = {};

    for (const [bound, edge] of Object.entries(value)) {
        if (edge !== '') {
            bounds[bound] = String(edge);
        }
    }

    return Object.keys(bounds).length === 0 ? null : bounds;
}
