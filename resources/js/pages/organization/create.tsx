import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/organization';

export default function Create() {
    return (
        <AuthLayout
            title="Create an organization"
            description="Name the organization you want to work in"
        >
            <Head title="Create an organization" />

            <Form
                {...store.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                type="text"
                                required
                                autoFocus
                                name="name"
                                placeholder="Acme Inc."
                            />
                            <InputError message={errors.name} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            data-test="create-organization-button"
                        >
                            {processing && <Spinner />}
                            Create organization
                        </Button>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
