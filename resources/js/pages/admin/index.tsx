import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { DataTable, dataTableColumns } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, pages as pageRoute } from '@/routes/admin';
import { create, edit } from '@/routes/admin/announcements';
import type { AdminRow, BreadcrumbItem, TypedResourceList } from '@/types';

type ColumnHeader = {
    key: string;
    label: string;
    sortable: boolean;
};

type RecentFailure = {
    id: string;
    organization_id: string;
    status: string;
    attempt: number;
    created_at: string;
};

type Props = {
    page: string;
    label: string;
    pages: { key: string; label: string }[];
    columns: ColumnHeader[];
    list: TypedResourceList<AdminRow>;
    recentFailures: RecentFailure[] | null;
};

const helper = dataTableColumns<AdminRow>();

const editLink = (id: string, title: string | undefined) => (
    <Link href={edit(id)} className="underline">
        {title}
    </Link>
);

export default function Index({
    page,
    label,
    pages,
    columns,
    list,
    recentFailures,
}: Props) {
    const t = useTranslate();
    const announcements = page === 'announcements';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: index() },
        { title: label, href: pageRoute({ page }) },
    ];

    const tableColumns = useMemo(
        () =>
            helper.columns(
                columns.map((column) =>
                    helper.accessor((row) => row.cells[column.key] ?? '', {
                        id: column.key,
                        header: column.label,
                        ...(announcements && column.key === 'title'
                            ? {
                                  cell: ({ row }) =>
                                      editLink(
                                          row.original.id,
                                          row.original.cells.title,
                                      ),
                              }
                            : {}),
                        ...(column.sortable
                            ? { meta: { sort: column.key } }
                            : {}),
                    }),
                ),
            ),
        [columns, announcements],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Admin — ${label}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title={label}
                    description="Platform-wide view across every organization"
                />

                <nav
                    aria-label="Admin pages"
                    className="flex flex-wrap gap-2 text-sm"
                >
                    {pages.map((item) => (
                        <Link
                            key={item.key}
                            href={pageRoute({ page: item.key })}
                            className={cn(
                                'rounded-md border px-3 py-1',
                                item.key === page
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                {announcements && (
                    <Button render={<Link href={create()} />}>
                        {t('announcements.new')}
                    </Button>
                )}

                <DataTable<AdminRow>
                    list={list}
                    columns={tableColumns}
                    only={['list']}
                    label={label}
                    rowId={(row) => row.id}
                    emptyKey={page}
                    exportable
                />

                {recentFailures !== null && (
                    <section className="space-y-2">
                        <Heading
                            variant="small"
                            title="Recent failed deliveries"
                            description="The latest failed or blocked webhook deliveries across every organization"
                        />

                        {recentFailures.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No failed deliveries.
                            </p>
                        ) : (
                            <ul className="space-y-1 text-sm">
                                {recentFailures.map((failure) => (
                                    <li
                                        key={failure.id}
                                        className="flex flex-wrap gap-2"
                                    >
                                        <span className="font-medium">
                                            {failure.status}
                                        </span>
                                        <span className="text-muted-foreground">
                                            attempt {failure.attempt} ·{' '}
                                            {failure.organization_id} ·{' '}
                                            {failure.created_at}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
