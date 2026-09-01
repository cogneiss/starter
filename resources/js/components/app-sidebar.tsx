import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, ShieldCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslate } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const t = useTranslate();
    const { auth } = usePage().props;

    const mainNavItems: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(auth.user?.is_super_admin
            ? [
                  {
                      title: t('nav.admin'),
                      href: adminIndex(),
                      icon: ShieldCheck,
                  },
              ]
            : []),
    ];

    const footerNavItems: NavItem[] = [
        {
            title: t('nav.repository'),
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: t('nav.documentation'),
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            render={<Link href={dashboard()} prefetch />}
                        >
                            <AppLogo />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <OrganizationSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
