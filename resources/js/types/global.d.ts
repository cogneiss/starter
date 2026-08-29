import type { Auth } from '@/types/auth';
import type { AppNotification, Impersonator, Organization } from '@/types/generated';
import type { FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            organization: Organization | null;
            organizations: Organization[];
            impersonating: Impersonator | null;
            sidebarOpen: boolean;
            unreadNotifications: number;
            recentNotifications: AppNotification[];
            [key: string]: unknown;
        };
    }
}
