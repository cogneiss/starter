import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { DataTable, dataTableColumns } from '@/components/data-table';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index } from '@/routes/audit-log';
import type { Activity, BreadcrumbItem, ResourceList } from '@/types';

type Props = {
    entries: ResourceList;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Audit log',
        href: index(),
    },
];

const helper = dataTableColumns<Activity>();

export default function Index({ entries }: Props) {
    const columns = useMemo(
        () =>
            helper.columns([
                helper.accessor('description', {
                    header: 'Description',
                    meta: { sort: 'description' },
                }),
                helper.accessor('event', {
                    header: 'Event',
                    meta: { sort: 'event' },
                }),
                helper.accessor('subject_type', {
                    header: 'Record type',
                }),
                helper.accessor('causer', {
                    header: 'Member',
                }),
                helper.accessor('created_at', {
                    header: 'When',
                    meta: { sort: 'created_at' },
                }),
            ]),
        [],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit log" />

            <h1 className="sr-only">Audit log</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Audit log"
                        description="Who changed what in the organization, and when"
                    />

                    <DataTable<Activity>
                        list={entries}
                        columns={columns}
                        only={['entries']}
                        label="Audit log"
                        rowId={(entry) => entry.id}
                        emptyKey="audit-log"
                    />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
