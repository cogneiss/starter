import type { Auth } from '@/types/auth';
import type { Organization } from '@/types/organization';
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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
