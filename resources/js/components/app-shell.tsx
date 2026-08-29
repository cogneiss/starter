import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ImpersonationBanner } from '@/components/impersonation-banner';
import { RouteAnnouncer } from '@/components/route-announcer';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">
                <ImpersonationBanner />
                <RouteAnnouncer />
                {children}
            </div>
        );
    }

    return (
        <div className="flex min-h-screen w-full flex-col">
            <ImpersonationBanner />
            <RouteAnnouncer />
            <SidebarProvider defaultOpen={isOpen}>{children}</SidebarProvider>
        </div>
    );
}
