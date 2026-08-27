import { router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    tableFeatures,
    useTable,
    type ColumnDef,
    type RowData,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown, Columns3 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataTableFilters } from '@/components/data-table-filters';
import {
    DataTableViews,
    type QueryParameters,
} from '@/components/data-table-views';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
 * The version in the preferences key. Column preferences are stored per person
 * per screen, and the shape they are stored in will change. Reading an old
 * shape back into a new table is how a saved preference turns into a blank
 * screen, so the version goes in the key: a shape change orphans the old entry
 * instead of misreading it.
 */
const PREFERENCES_VERSION = 'v1';

/** How far one arrow press moves a column edge, and how narrow it may get. */
const RESIZE_STEP_PX = 16;
const MINIMUM_WIDTH_PX = 80;

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
     * @param all Whether the person opted in to every record the filters match.
     */
    submit: (action: string, ids: string[], all: boolean) => void;
};

/** What a person has arranged for themselves on one list screen. */
type Preferences = {
    hidden: string[];
    widths: Record<string, number>;
};

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
    empty = 'Nothing matches that search.',
    exportable = false,
    bulk,
    saveable,
}: DataTableProps<TRow>) {
    const page = usePage();
    const path = page.url.split('?')[0];
    const [term, setTerm] = useState(list.query.q);
    const [pending, setPending] = useState(false);
    const [failed, setFailed] = useState(false);
    const debounce = useRef<number | undefined>(undefined);
    const startedAt = useRef(0);

    const preferencesKey = `table:${PREFERENCES_VERSION}:${path}:${page.props.auth.user.id}`;
    const [preferences, setPreferences] = useState<Preferences>(() =>
        readPreferences(preferencesKey),
    );

    const [selection, setSelection] = useState<string[]>([]);
    const [everyMatch, setEveryMatch] = useState(false);
    const [action, setAction] = useState(bulk?.actions[0]?.value ?? '');
    const [confirming, setConfirming] = useState<BulkAction | null>(null);
    const [exported, setExported] = useState(false);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    useEffect(() => {
        window.localStorage.setItem(
            preferencesKey,
            JSON.stringify(preferences),
        );
    }, [preferencesKey, preferences]);

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

    const rows = list.rows as TRow[];

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

    function apply(chosen: BulkAction) {
        if (chosen.destructive) {
            setConfirming(chosen);

            return;
        }

        run(chosen);
    }

    function run(chosen: BulkAction) {
        setConfirming(null);
        bulk?.submit(chosen.value, selection, everyMatch);
        clearSelection();
    }

    /**
     * The export asks the list endpoint it is already looking at for the same
     * query in another representation, so there is no second endpoint to keep
     * in step and no chance of exporting a different set of rows than the one
     * on screen. `Accept` is what makes it CSV; without that header the server
     * answers with the page and the download never appears.
     */
    async function exportCsv() {
        setExported(false);

        const response = await fetch(path + window.location.search, {
            headers: { Accept: 'text/csv' },
        });

        if (!response.headers.get('content-type')?.startsWith('text/csv')) {
            setFailed(true);

            return;
        }

        const url = URL.createObjectURL(await response.blob());
        const anchor = document.createElement('a');

        anchor.href = url;
        anchor.download = `${label.toLowerCase()}.csv`;
        anchor.click();

        // Released on the next tick: revoking it in the same one cancels the
        // download the click just started.
        window.setTimeout(() => URL.revokeObjectURL(url), 0);

        setExported(true);
    }

    return (
        <div className="flex flex-col gap-4" data-test="data-table">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    type="search"
                    aria-label={`Search ${label}`}
                    data-test="table-search"
                    placeholder="Search…"
                    value={term}
                    onChange={(event) => search(event.target.value)}
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
                        {table.getAllColumns().map((column) => (
                            <DropdownMenuCheckboxItem
                                key={column.id}
                                data-test={`column-${column.id}`}
                                checked={visible(column.id)}
                                onCheckedChange={() => toggleColumn(column.id)}
                            >
                                {String(column.columnDef.header ?? column.id)}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {saveable !== undefined && (
                    <DataTableViews
                        searches={list.searches}
                        resource={saveable}
                        current={parameters(list.query)}
                    />
                )}

                {exportable && (
                    <Button
                        size="sm"
                        variant="outline"
                        data-test="table-export"
                        onClick={() => void exportCsv()}
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
                <div
                    data-test="bulk-bar"
                    className="flex flex-wrap items-center gap-3 rounded-md border p-3 text-sm"
                >
                    <p data-test="bulk-count">
                        {selection.length} selected on this page
                    </p>

                    <label className="flex items-center gap-2">
                        <Checkbox
                            data-test="bulk-every-match"
                            checked={everyMatch}
                            onCheckedChange={(checked) =>
                                setEveryMatch(checked === true)
                            }
                        />
                        Apply to all {list.total} matching
                    </label>

                    <select
                        data-test="bulk-action"
                        aria-label="Bulk action"
                        value={action}
                        onChange={(event) => setAction(event.target.value)}
                        className="h-9 rounded-md border border-input bg-background px-2"
                    >
                        {bulk.actions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>

                    <Button
                        size="sm"
                        data-test="bulk-apply"
                        onClick={() => {
                            const chosen = bulk.actions.find(
                                (option) => option.value === action,
                            );

                            if (chosen) {
                                apply(chosen);
                            }
                        }}
                    >
                        Apply
                    </Button>
                </div>
            )}

            <Dialog
                open={confirming !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirming(null);
                    }
                }}
            >
                <DialogContent data-test="bulk-confirm">
                    <DialogTitle>
                        {confirming?.label} {selection.length} record(s)?
                    </DialogTitle>
                    <DialogDescription>
                        This cannot be undone. Nothing has happened yet.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose
                            render={
                                <Button
                                    variant="secondary"
                                    data-test="bulk-cancel"
                                />
                            }
                        >
                            Cancel
                        </DialogClose>
                        <Button
                            variant="destructive"
                            data-test="bulk-proceed"
                            onClick={() => confirming && run(confirming)}
                        >
                            {confirming?.label}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
                                    const active =
                                        sort !== undefined &&
                                        sort === list.query.sort;
                                    const ascending = list.query.dir === 'asc';

                                    return (
                                        <TableHead
                                            key={header.id}
                                            style={{
                                                width: preferences.widths[id],
                                            }}
                                            className="relative"
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
                                                <table.FlexRender
                                                    header={header}
                                                />
                                            ) : (
                                                <button
                                                    type="button"
                                                    data-test={`sort-${sort}`}
                                                    className="flex items-center gap-1 hover:text-foreground"
                                                    onClick={() =>
                                                        visit({
                                                            sort,
                                                            dir:
                                                                active &&
                                                                ascending
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

                                            <ColumnResizer
                                                id={id}
                                                width={
                                                    preferences.widths[id] ??
                                                    null
                                                }
                                                onResize={resize}
                                            />
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
                                colSpan={span}
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
                                colSpan={span}
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
 * The handle on a column's trailing edge.
 *
 * It is reachable by keyboard as well as by pointer, because a column that can
 * only be widened by dragging is a column someone who does not use a mouse
 * cannot read. The arrow keys move the same edge the drag does.
 */
function ColumnResizer({
    id,
    width,
    onResize,
}: {
    id: string;
    width: number | null;
    onResize: (id: string, to: number) => void;
}) {
    return (
        <span
            role="separator"
            aria-label={`Resize column ${id}`}
            aria-orientation="vertical"
            aria-valuenow={width ?? MINIMUM_WIDTH_PX}
            aria-valuemin={MINIMUM_WIDTH_PX}
            tabIndex={0}
            data-test={`resize-${id}`}
            className="absolute inset-y-0 right-0 w-2 cursor-col-resize touch-none select-none hover:bg-border focus-visible:bg-ring"
            onKeyDown={(event) => {
                const step =
                    event.key === 'ArrowLeft'
                        ? -RESIZE_STEP_PX
                        : event.key === 'ArrowRight'
                          ? RESIZE_STEP_PX
                          : 0;

                if (step === 0) {
                    return;
                }

                event.preventDefault();

                onResize(id, (width ?? measure(event.currentTarget)) + step);
            }}
            onPointerDown={(event) => {
                const element = event.currentTarget;
                const from = width ?? measure(element);
                const startedAt = event.clientX;

                element.setPointerCapture(event.pointerId);

                const move = (moved: PointerEvent) =>
                    onResize(id, from + moved.clientX - startedAt);

                const stop = () => {
                    element.removeEventListener('pointermove', move);
                    element.removeEventListener('pointerup', stop);
                };

                element.addEventListener('pointermove', move);
                element.addEventListener('pointerup', stop);
            }}
        />
    );
}

/** The rendered width of the column a handle sits in. */
function measure(handle: HTMLElement): number {
    return (
        handle.parentElement?.getBoundingClientRect().width ?? MINIMUM_WIDTH_PX
    );
}

/**
 * What this person last arranged on this screen, or nothing at all. Anything
 * unreadable is nothing at all: a corrupted entry is not worth a broken table.
 */
function readPreferences(key: string): Preferences {
    const empty: Preferences = { hidden: [], widths: {} };

    try {
        const stored = window.localStorage.getItem(key);

        if (stored === null) {
            return empty;
        }

        const parsed = JSON.parse(stored) as Partial<Preferences>;

        return {
            hidden: Array.isArray(parsed.hidden) ? parsed.hidden : [],
            widths:
                typeof parsed.widths === 'object' && parsed.widths !== null
                    ? parsed.widths
                    : {},
        };
    } catch {
        return empty;
    }
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
