import { Form, Head, usePage } from '@inertiajs/react';
// Components
import FormFrictionFields, {
    type Friction,
} from '@/components/form-friction-fields';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/magic-link';

export default function MagicLink({
    status,
    friction,
}: {
    status?: string;
    friction: Friction;
}) {
    const { token } = usePage().props.errors;

    return (
        <AuthLayout
            title="Email login link"
            description="Enter your email to receive a link that logs you in"
        >
            <Head title="Email login link" />

            {token && (
                <div className="mb-4 text-center text-sm font-medium text-red-600">
                    {token}
                </div>
            )}

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...store.form()}>
                    {({ processing, errors }) => (
                        <>
                            <FormFrictionFields friction={friction} />
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    placeholder="email@example.com"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-login-link-button"
                                >
                                    {processing && <Spinner />}
                                    Email login link
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Or, return to</span>
                    <TextLink href={login()}>log in</TextLink>
                </div>
            </div>
        </AuthLayout>
    );
}
