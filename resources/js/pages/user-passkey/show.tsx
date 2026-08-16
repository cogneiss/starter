import { Head } from '@inertiajs/react';
import ManagePasskeys from '@/components/manage-passkeys';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show } from '@/routes/passkey';
import type { BreadcrumbItem, Passkey } from '@/types';

type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Passkeys',
        href: show(),
    },
];

export default function Show({
    canManagePasskeys = false,
    passkeys = [],
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Passkeys" />
            <SettingsLayout>
                <ManagePasskeys
                    canManagePasskeys={canManagePasskeys}
                    passkeys={passkeys}
                />
            </SettingsLayout>
        </AppLayout>
    );
}
