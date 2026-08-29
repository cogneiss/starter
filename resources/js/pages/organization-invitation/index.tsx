import { Form, Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import OrganizationInvitationController from '@/actions/App/Http/Controllers/OrganizationInvitationController';
import { DataTable, dataTableColumns } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { create, index } from '@/routes/organization-invitation';
import type {
    BreadcrumbItem,
    OrganizationInvitation,
    ResourceList,
} from '@/types';

type Props = {
    invitations: ResourceList;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pending invitations',
        href: index(),
    },
];

const helper = dataTableColumns<OrganizationInvitation>();

export default function Index({ invitations }: Props) {
    const columns = useMemo(
        () =>
            helper.columns([
                helper.accessor('email', {
                    header: 'Email',
                    meta: { sort: 'email' },
                }),
                helper.accessor('role', {
                    header: 'Role',
                    meta: { sort: 'role' },
                }),
                helper.accessor('expires_at', {
                    header: 'Expires',
                    meta: { sort: 'expires_at' },
                }),
                helper.display({
                    id: 'actions',
                    header: 'Actions',
                    cell: ({ row }) => (
                        <Form
                            {...OrganizationInvitationController.destroy.form(
                                row.original.id,
                            )}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Revoke
                                </Button>
                            )}
                        </Form>
                    ),
                }),
            ]),
        [],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pending invitations" />

            <h1 className="sr-only">Pending invitations</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Pending invitations"
                        description="People who have been invited but have not joined yet"
                    />

                    <Button render={<Link href={create()} />} size="sm">
                        Invite member
                    </Button>

                    <DataTable<OrganizationInvitation>
                        list={invitations}
                        columns={columns}
                        only={['invitations']}
                        label="Pending invitations"
                        rowId={(invitation) => invitation.id}
                        emptyKey="organization-invitations"
                    />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
