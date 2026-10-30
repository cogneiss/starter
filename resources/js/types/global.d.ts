import type { Auth } from '@/types/auth';
import type {
    AnnouncementBanner,
    AppNotification,
    Impersonator,
    Organization,
} from '@/types/generated';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            plainTextToken?: string;
            webhookSecret?: string;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            organization: Organization | null;
            organizations: Organization[];
            impersonating: Impersonator | null;
            announcements: AnnouncementBanner[];
            locale: string;
            supportedLocales: string[];
            translations: Record<string, string>;
            sidebarOpen: boolean;
            unreadNotifications: number;
            doNotTrack: boolean;
            recentNotifications: AppNotification[];
            [key: string]: unknown;
        };
    }
}
