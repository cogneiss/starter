import { Head } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react-inertia';
import type { FormEvent } from 'react';
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
    // "Already a member" and "already invited" are both server-side facts, so
    // the address is checked as it is typed rather than after the send.
    const form = useForm('post', OrganizationInvitationController.store().url, {
        email: '',
        role: roles[0] ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.submit({ preserveScroll: true });
    };

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

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>

                            <Input
                                id="email"
                                type="email"
                                name="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                onBlur={() => form.validate('email')}
                                required
                                placeholder="teammate@example.com"
                            />

                            <InputError message={form.errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">Role</Label>

                            <select
                                id="role"
                                name="role"
                                value={form.data.role}
                                onChange={(event) =>
                                    form.setData('role', event.target.value)
                                }
                                onBlur={() => form.validate('role')}
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            >
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {role}
                                    </option>
                                ))}
                            </select>

                            <InputError message={form.errors.role} />
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-test="send-invitation-button"
                        >
                            Send invitation
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
