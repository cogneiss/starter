import { Form, Head } from '@inertiajs/react';
import OrganizationInvitationController from '@/actions/App/Http/Controllers/OrganizationInvitationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { create } from '@/routes/organization-invitation';
import type { BreadcrumbItem } from '@/types';

type Props = {
    roles?: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Invite member',
        href: create(),
    },
];

export default function Create({ roles = [] }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invite member" />

            <h1 className="sr-only">Invite member</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Invite member"
                        description="Send someone an invitation to join this organization"
                    />

                    <Form
                        {...OrganizationInvitationController.store.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        placeholder="teammate@example.com"
                                    />

                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>

                                    <select
                                        id="role"
                                        name="role"
                                        className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                    >
                                        {roles.map((role) => (
                                            <option key={role} value={role}>
                                                {role}
                                            </option>
                                        ))}
                                    </select>

                                    <InputError message={errors.role} />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="send-invitation-button"
                                >
                                    Send invitation
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
