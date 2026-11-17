import { usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    tableFeatures,
    useTable,
    type ColumnDef,
    type RowData,
} from '@tanstack/react-table';
import { useEffect, useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { DataTableBody } from '@/components/data-table-body';
import { DataTableBulkBar } from '@/components/data-table-bulk-bar';
import { useCsvExport } from '@/components/data-table-export';
import { DataTableFilters } from '@/components/data-table-filters';
import { DataTableHead } from '@/components/data-table-head';
import { DataTablePagination } from '@/components/data-table-pagination';
import {
    PREFERENCES_VERSION,
    readPreferences,
    type Preferences,
} from '@/components/data-table-preferences';
import { MINIMUM_WIDTH_PX } from '@/components/data-table-resizer';
import { DataTableToolbar } from '@/components/data-table-toolbar';
import { useListVisits } from '@/components/data-table-visits';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAuthUser } from '@/hooks/use-auth-user';
import type { ResourceQuery } from '@/types/generated';
import type { TypedResourceList } from '@/types/ui';

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

/** One entry in the bulk menu. Destructive ones are asked about first. */
export type BulkAction = {
    value: string;
    label: string;
    destructive: boolean;
};

type BulkConfiguration = {
    actions: BulkAction[];
    /**
     * @param ids The rows ticked on the current page.
     * @param allMatching Whether the person opted in to every record the filters match.
     */
    submit: (action: string, ids: string[], allMatching: boolean) => void;
};

type DataTableProps<TRow extends RowData> = {
    /** The page of rows and the query that produced it, straight from the server. */
    list: TypedResourceList<TRow>;
    columns: DataTableColumns<TRow>;
    /** Props to reload; everything else on the page is left alone. */
    only: string[];
    /** Accessible name for the table and its search box. */
    label: string;
    rowId: (row: TRow) => string;
    /** The screen the empty state takes its copy from. */
    emptyKey: string;
    /** Whether to offer the current query as a CSV download. */
    exportable?: boolean;
    /** The actions a selection can be put through, and where to send them. */
    bulk?: BulkConfiguration;
    /**
     * The resource key views are saved against. Given, the table offers to keep
     * the current query by name; omitted, it does not.
     */
    saveable?: string;
};

/**
 * A list screen: search, sort and pagination, all decided by the server.
 *
 * The component holds no copy of the list state. Every control writes to the
 * URL and reloads the list prop, so the rendered table, the address bar and a
 * shared link always agree, and the back button walks the searches a person
 * actually ran.
 *
 * Two things deliberately do not live in the URL. Column visibility and column
 * widths are how one person likes to look at a screen, not what the screen is
 * showing — putting them in the address bar would send them to whoever the link
 * is shared with. They go to that person's browser instead, and the URL stays
 * the single account of the data.
 */
export function DataTable<TRow extends RowData>({
    list,
    columns,
    only,
    label,
    rowId,
    emptyKey,
    exportable = false,
    bulk,
    saveable,
}: DataTableProps<TRow>) {
    const page = usePage();
    const user = useAuthUser();
    const path = page.url.split('?')[0] ?? page.url;

    const { term, search, visit, pending, failed, setFailed } = useListVisits(
        list,
        path,
        only,
    );

    const preferencesKey = `table:${PREFERENCES_VERSION}:${path}:${user.id}`;
    const [preferences, setPreferences] = useState<Preferences>(() =>
        readPreferences(preferencesKey),
    );

    const confirm = useConfirm();

    const [selection, setSelection] = useState<string[]>([]);
    const [everyMatch, setEveryMatch] = useState(false);
    const [action, setAction] = useState(bulk?.actions[0]?.value ?? '');
    const { exported, exportCsv } = useCsvExport(path, label, setFailed);

    useEffect(() => {
        window.localStorage.setItem(
            preferencesKey,
            JSON.stringify(preferences),
        );
    }, [preferencesKey, preferences]);

    /**
     * Sorting and paging rearrange the same records, so a selection made before
     * one still means what it meant. Filtering does not: the rows a person
     * ticked may no longer be in front of them, and acting on a selection they
     * can no longer see is how the wrong records get changed.
     */
    function filter(filters: ResourceQuery['filters']) {
        clearSelection();
        visit({ filters });
    }

    function clearSelection() {
        setSelection([]);
        setEveryMatch(false);
    }

    const rows = list.rows;

    const table = useTable({
        features,
        columns,
        data: rows,
        getRowId: (row: TRow) => rowId(row),
    });

    const visible = (id: string) => !preferences.hidden.includes(id);
    const visibleColumns = table
        .getAllColumns()
        .filter((column) => visible(column.id));
    const span = visibleColumns.length + (bulk ? 1 : 0);

    function toggleColumn(id: string) {
        setPreferences((current) => ({
            ...current,
            hidden: current.hidden.includes(id)
                ? current.hidden.filter((hidden) => hidden !== id)
                : [...current.hidden, id],
        }));
    }

    function resize(id: string, to: number) {
        setPreferences((current) => ({
            ...current,
            widths: {
                ...current.widths,
                [id]: Math.max(MINIMUM_WIDTH_PX, Math.round(to)),
            },
        }));
    }

    async function apply(chosen: BulkAction) {
        if (
            chosen.destructive &&
            !(await confirm({
                title: `${chosen.label} ${selection.length} record(s)?`,
                description: 'This cannot be undone. Nothing has happened yet.',
                confirmLabel: chosen.label,
                intent: 'destructive',
            }))
        ) {
            return;
        }

        bulk?.submit(chosen.value, selection, everyMatch);
        clearSelection();
    }

    return (
        <div className="flex flex-col gap-4" data-test="data-table">
            <DataTableToolbar
                label={label}
                term={term}
                onSearch={search}
                columns={table.getAllColumns().map((column) => ({
                    id: column.id,
                    label: String(column.columnDef.header ?? column.id),
                }))}
                isVisible={visible}
                onToggleColumn={toggleColumn}
                saveable={saveable}
                searches={list.searches}
                query={list.query}
                exportable={exportable}
                onExport={() => void exportCsv()}
                exported={exported}
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

                    filter(filters);
                }}
                onClear={() => filter({})}
            />

            {bulk && selection.length > 0 && (
                <DataTableBulkBar
                    count={selection.length}
                    total={list.total}
                    everyMatch={everyMatch}
                    onEveryMatch={setEveryMatch}
                    action={action}
                    onAction={setAction}
                    actions={bulk.actions}
                    onApply={() => {
                        const chosen = bulk.actions.find(
                            (option) => option.value === action,
                        );

                        if (chosen) {
                            void apply(chosen);
                        }
                    }}
                />
            )}

            <Table aria-label={label}>
                <TableHeader>
                    {table.getHeaderGroups().map((group) => (
                        <TableRow key={group.id}>
                            {bulk && (
                                <TableHead className="w-10">
                                    <Checkbox
                                        data-test="select-page"
                                        aria-label="Select every row on this page"
                                        checked={
                                            rows.length > 0 &&
                                            selection.length === rows.length
                                        }
                                        onCheckedChange={(checked) =>
                                            checked === true
                                                ? setSelection(rows.map(rowId))
                                                : clearSelection()
                                        }
                                    />
                                </TableHead>
                            )}

                            {group.headers
                                .filter((header) => visible(header.column.id))
                                .map((header) => {
                                    const id = header.column.id;
                                    const sort =
                                        header.column.columnDef.meta?.sort;

                                    return (
                                        <DataTableHead
                                            key={header.id}
                                            id={id}
                                            width={preferences.widths[id]}
                                            sort={sort}
                                            active={
                                                sort !== undefined &&
                                                sort === list.query.sort
                                            }
                                            ascending={list.query.dir === 'asc'}
                                            placeholder={header.isPlaceholder}
                                            onSort={(dir) =>
                                                visit({ sort, dir })
                                            }
                                            onResize={resize}
                                        >
                                            <table.FlexRender header={header} />
                                        </DataTableHead>
                                    );
                                })}
                        </TableRow>
                    ))}
                </TableHeader>

                <DataTableBody
                    pending={pending}
                    failed={failed}
                    empty={rows.length === 0}
                    span={span}
                    emptyKey={emptyKey}
                    onRetry={() => visit({})}
                >
                    {!failed &&
                        table.getRowModel().rows.map((row) => (
                            <TableRow key={row.id} data-test={`row-${row.id}`}>
                                {bulk && (
                                    <TableCell>
                                        <Checkbox
                                            data-test={`select-${row.id}`}
                                            aria-label={`Select row ${row.id}`}
                                            checked={selection.includes(row.id)}
                                            onCheckedChange={(checked) =>
                                                setSelection((current) =>
                                                    checked === true
                                                        ? [...current, row.id]
                                                        : current.filter(
                                                              (id) =>
                                                                  id !== row.id,
                                                          ),
                                                )
                                            }
                                        />
                                    </TableCell>
                                )}

                                {row
                                    .getAllCells()
                                    .filter((cell) => visible(cell.column.id))
                                    .map((cell) => (
                                        <TableCell key={cell.id}>
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                            </TableRow>
                        ))}
                </DataTableBody>
            </Table>

            <DataTablePagination
                total={list.total}
                page={list.query.page}
                pages={list.pages}
                onPage={(page) => visit({ page })}
            />
        </div>
    );
}
