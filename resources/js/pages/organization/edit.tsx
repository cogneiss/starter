import { Form, Head, usePage } from '@inertiajs/react';
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

                    <Form
                        {...OrganizationController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={organization?.name}
                                        name="name"
                                        required
                                        placeholder="Organization name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="slug">Slug</Label>

                                    <Input
                                        id="slug"
                                        className="mt-1 block w-full"
                                        defaultValue={organization?.slug}
                                        name="slug"
                                        required
                                        placeholder="acme-inc"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.slug}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center space-x-3">
                                        <input
                                            type="hidden"
                                            name="require_two_factor"
                                            value="0"
                                        />

                                        <Checkbox
                                            id="require_two_factor"
                                            name="require_two_factor"
                                            value="1"
                                            defaultChecked={
                                                organization?.require_two_factor
                                            }
                                            data-test="require-two-factor-checkbox"
                                        />

                                        <Label htmlFor="require_two_factor">
                                            Require two-factor authentication
                                        </Label>
                                    </div>

                                    <p className="text-sm text-muted-foreground">
                                        Members without two-factor
                                        authentication are sent to set it up
                                        before they can use the app.
                                    </p>

                                    <InputError
                                        className="mt-2"
                                        message={errors.require_two_factor}
                                    />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="update-organization-button"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
