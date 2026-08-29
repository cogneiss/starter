import { Form, Head } from '@inertiajs/react';
import UserNotificationPreferenceController from '@/actions/App/Http/Controllers/UserNotificationPreferenceController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/user-notification-preference';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: edit().url,
    },
];

const labels: Record<string, string> = {
    organization_invitation_notification: 'Organization invitations',
    mail: 'Email',
    database: 'In-app',
};

function label(key: string): string {
    return labels[key] ?? key;
}

export default function NotificationPreferences({
    preferences,
}: {
    preferences: Record<string, Record<string, boolean>>;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Notifications"
                        description="Choose how you hear about what happens in your organizations"
                    />

                    <Form
                        {...UserNotificationPreferenceController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful }) => (
                            <>
                                {Object.entries(preferences).map(
                                    ([notification, channels]) => (
                                        <fieldset
                                            key={notification}
                                            className="space-y-3"
                                        >
                                            <legend className="text-sm font-medium">
                                                {label(notification)}
                                            </legend>

                                            {Object.entries(channels).map(
                                                ([channel, enabled]) => (
                                                    <div
                                                        key={channel}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name={`${notification}[${channel}]`}
                                                            value="0"
                                                        />

                                                        <Checkbox
                                                            id={`${notification}-${channel}`}
                                                            name={`${notification}[${channel}]`}
                                                            value="1"
                                                            defaultChecked={
                                                                enabled
                                                            }
                                                        />

                                                        <Label
                                                            htmlFor={`${notification}-${channel}`}
                                                        >
                                                            {label(channel)}
                                                        </Label>
                                                    </div>
                                                ),
                                            )}
                                        </fieldset>
                                    ),
                                )}

                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>Save</Button>

                                    {recentlySuccessful && (
                                        <p className="text-sm text-neutral-600">
                                            Saved
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
