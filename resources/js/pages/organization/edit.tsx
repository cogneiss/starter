import { Head, usePage } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react-inertia';
import type { FormEvent } from 'react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/organization';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Organization settings',
        href: edit(),
    },
];

export default function Edit() {
    const { organization } = usePage().props;

    // The slug is the one field where a live check earns its keep: uniqueness
    // is a database question, so the answer has to come from the server.
    const form = useForm('patch', OrganizationController.update().url, {
        name: organization?.name ?? '',
        slug: organization?.slug ?? '',
        require_two_factor: organization?.require_two_factor ?? false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.submit({ preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization settings" />

            <h1 className="sr-only">Organization settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Organization"
                        description="Update your organization name and slug"
                    />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>

                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                onBlur={() => form.validate('name')}
                                name="name"
                                required
                                placeholder="Organization name"
                            />

                            <InputError
                                className="mt-2"
                                message={form.errors.name}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">Slug</Label>

                            <Input
                                id="slug"
                                className="mt-1 block w-full"
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
                                onBlur={() => form.validate('slug')}
                                name="slug"
                                required
                                placeholder="acme-inc"
                            />

                            <InputError
                                className="mt-2"
                                message={form.errors.slug}
                            />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="require_two_factor"
                                    name="require_two_factor"
                                    checked={form.data.require_two_factor}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'require_two_factor',
                                            checked === true,
                                        )
                                    }
                                    data-test="require-two-factor-checkbox"
                                />

                                <Label htmlFor="require_two_factor">
                                    Require two-factor authentication
                                </Label>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                Members without two-factor authentication are
                                sent to set it up before they can use the app.
                            </p>

                            <InputError
                                className="mt-2"
                                message={form.errors.require_two_factor}
                            />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="update-organization-button"
                            >
                                Save
                            </Button>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
