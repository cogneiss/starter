import type { RequestPayload } from '@inertiajs/core';
import { Form, Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import {
    DataTable,
    dataTableColumns,
    type BulkAction,
} from '@/components/data-table';
import { DetailDrawer } from '@/components/detail-drawer';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { usePendingPatch } from '@/hooks/use-pending-patch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { create as invite } from '@/routes/organization-invitation';
import { index as invitations } from '@/routes/organization-invitation';
import { bulk, edit } from '@/routes/organization-member';
import type { BreadcrumbItem, OrganizationMember, ResourceList } from '@/types';

type Props = {
    members: ResourceList;
    roles?: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Members',
        href: edit(),
    },
];

const helper = dataTableColumns<OrganizationMember>();

/**
 * Removing people is the one bulk action that cannot be walked back, so it is
 * the one marked destructive and the one the table asks about first.
 */
const bulkActions: BulkAction[] = [
    { value: 'suspend', label: 'Suspend', destructive: false },
    { value: 'reactivate', label: 'Reactivate', destructive: false },
    { value: 'remove', label: 'Remove', destructive: true },
];

export default function Edit({ members, roles = [] }: Props) {
    // One patcher for the whole table: the pending values are keyed by member,
    // so two rows edited at once keep their own spinner and their own value.
    const { pending, patch } = usePendingPatch<string>();

    const columns = useMemo(
        () =>
            helper.columns([
                helper.accessor('name', {
                    header: 'Name',
                    meta: { sort: 'user.name' },
                }),
                helper.accessor('email', {
                    header: 'Email',
                    meta: { sort: 'user.email' },
                }),
                helper.accessor('status', {
                    header: 'Status',
                    meta: { sort: 'status' },
                }),
                helper.display({
                    id: 'actions',
                    header: 'Actions',
                    cell: ({ row }) => (
                        <MemberActions
                            member={row.original}
                            roles={roles}
                            pendingRole={pending[row.original.id]}
                            patch={patch}
                        />
                    ),
                }),
            ]),
        [roles, pending, patch],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Members" />

            <h1 className="sr-only">Members</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Members"
                        description="Manage who belongs to this organization"
                    />

                    <div className="flex items-center gap-2">
                        <Button render={<Link href={invite()} />} size="sm">
                            Invite member
                        </Button>
                        <Button
                            render={<Link href={invitations()} />}
                            size="sm"
                            variant="outline"
                        >
                            Pending invitations
                        </Button>
                    </div>

                    <DataTable<OrganizationMember>
                        list={members}
                        columns={columns}
                        only={['members']}
                        label="Members"
                        rowId={(member) => member.id}
                        emptyKey="organization-members"
                        exportable
                        saveable="organization-members"
                        bulk={{
                            actions: bulkActions,
                            submit: (action, ids, all) =>
                                router.post(
                                    bulk(),
                                    { action, ids, all },
                                    { preserveScroll: true },
                                ),
                        }}
                    />
                </div>

                <DetailDrawer />
            </SettingsLayout>
        </AppLayout>
    );
}

function MemberActions({
    member,
    roles,
    pendingRole,
    patch,
}: {
    member: OrganizationMember;
    roles: string[];
    pendingRole?: string;
    patch: (
        key: string,
        value: string,
        url: string,
        data: RequestPayload,
    ) => void;
}) {
    return (
        <div
            className="flex items-center gap-2"
            data-test={`member-${member.id}`}
        >
            <Button
                render={
                    <Link
                        href={edit.url({ query: { peek: member.id } })}
                        preserveScroll
                        preserveState
                    />
                }
                size="sm"
                variant="outline"
                data-test={`peek-${member.id}`}
            >
                View
            </Button>

            <div className="flex items-center gap-2">
                <select
                    name="role"
                    data-test={`role-${member.id}`}
                    aria-label={`Role for ${member.email}`}
                    value={pendingRole ?? member.role ?? ''}
                    onChange={(event) =>
                        patch(
                            member.id,
                            event.target.value,
                            OrganizationMemberController.update.url(member.id),
                            { role: event.target.value },
                        )
                    }
                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                >
                    {roles.map((role) => (
                        <option key={role} value={role}>
                            {role}
                        </option>
                    ))}
                </select>

                {pendingRole === undefined ? null : (
                    <Spinner data-test={`patching-${member.id}`} />
                )}
            </div>

            <Form
                {...OrganizationMemberController.update.form(member.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="status"
                            value={
                                member.status === 'active'
                                    ? 'suspended'
                                    : 'active'
                            }
                        />

                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            {member.status === 'active'
                                ? 'Suspend'
                                : 'Reactivate'}
                        </Button>
                    </>
                )}
            </Form>

            <Form
                {...OrganizationMemberController.destroy.form(member.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="destructive"
                        disabled={processing}
                    >
                        Remove
                    </Button>
                )}
            </Form>
        </div>
    );
}
