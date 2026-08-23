import { Form, Head } from '@inertiajs/react';
import OrganizationInvitationAcceptanceController from '@/actions/App/Http/Controllers/OrganizationInvitationAcceptanceController';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

type Props = {
    token: string;
    email?: string | null;
    organization?: string | null;
    role?: string | null;
    pending?: boolean;
};

export default function Show({
    token,
    email = null,
    organization = null,
    role = null,
    pending = false,
}: Props) {
    if (!pending) {
        return (
            <AuthLayout
                title="Invitation unavailable"
                description="This invitation has expired or has already been used."
            >
                <Head title="Invitation unavailable" />

                <p className="text-sm text-muted-foreground">
                    Ask whoever invited you to send a new invitation.
                </p>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title={`Join ${organization}`}
            description={`You were invited as a ${role}.`}
        >
            <Head title="Accept invitation" />

            <p className="text-sm text-muted-foreground">
                This invitation was sent to {email}.
            </p>

            <Form
                {...OrganizationInvitationAcceptanceController.update.form(
                    token,
                )}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        disabled={processing}
                        data-test="accept-invitation-button"
                    >
                        Accept invitation
                    </Button>
                )}
            </Form>
        </AuthLayout>
    );
}
