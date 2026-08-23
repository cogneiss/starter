import { Form, Head, Link } from '@inertiajs/react';
import OrganizationInvitationController from '@/actions/App/Http/Controllers/OrganizationInvitationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { create as invite } from '@/routes/organization-invitation';
import { edit } from '@/routes/organization-member';
import type {
    BreadcrumbItem,
    OrganizationInvitation,
    OrganizationMember,
} from '@/types';

type Props = {
    members?: OrganizationMember[];
    invitations?: OrganizationInvitation[];
    roles?: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Members',
        href: edit(),
    },
];

export default function Edit({
    members = [],
    invitations = [],
    roles = [],
}: Props) {
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

                    <Button render={<Link href={invite()} />} size="sm">
                        Invite member
                    </Button>

                    <ul className="divide-y divide-border">
                        {members.map((member) => (
                            <li
                                key={member.id}
                                className="flex flex-wrap items-center justify-between gap-4 py-4"
                                data-test={`member-${member.id}`}
                            >
                                <div>
                                    <p className="font-medium">{member.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {member.email} &middot; {member.status}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Form
                                        {...OrganizationMemberController.update.form(
                                            member.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        className="flex items-center gap-2"
                                    >
                                        {({ processing }) => (
                                            <>
                                                <select
                                                    name="role"
                                                    aria-label={`Role for ${member.email}`}
                                                    defaultValue={
                                                        member.role ?? ''
                                                    }
                                                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                                >
                                                    {roles.map((role) => (
                                                        <option
                                                            key={role}
                                                            value={role}
                                                        >
                                                            {role}
                                                        </option>
                                                    ))}
                                                </select>

                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Save role
                                                </Button>
                                            </>
                                        )}
                                    </Form>

                                    <Form
                                        {...OrganizationMemberController.update.form(
                                            member.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value={
                                                        member.status ===
                                                        'active'
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
                                        {...OrganizationMemberController.destroy.form(
                                            member.id,
                                        )}
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
                            </li>
                        ))}
                    </ul>

                    <Heading
                        variant="small"
                        title="Pending invitations"
                        description="People who have been invited but have not joined yet"
                    />

                    {invitations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No pending invitations.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.id}
                                    className="flex items-center justify-between gap-4 py-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {invitation.email}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {invitation.role} &middot; expires{' '}
                                            {invitation.expires_at}
                                        </p>
                                    </div>

                                    <Form
                                        {...OrganizationInvitationController.destroy.form(
                                            invitation.id,
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
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
