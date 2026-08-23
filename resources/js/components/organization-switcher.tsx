import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { create } from '@/routes/organization';
import { update } from '@/routes/organization-switch';
import type { Organization } from '@/types';

export function OrganizationSwitcher() {
    const { organization, organizations } = usePage().props;

    // B2C apps never see the concept: a lone personal organization stays hidden.
    if (
        organization === null ||
        (organizations.length === 1 && organizations[0].personal)
    ) {
        return null;
    }

    const switchTo = (target: Organization) => {
        if (target.id !== organization.id) {
            router.put(update(), { organization: target.id });
        }
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <SidebarMenuButton
                                className="text-sidebar-accent-foreground data-[popup-open]:bg-sidebar-accent"
                                data-test="organization-switcher"
                            />
                        }
                    >
                        <Building2 className="size-4" />
                        <span className="truncate">{organization.name}</span>
                        <ChevronsUpDown className="ml-auto size-4" />
                    </DropdownMenuTrigger>

                    <DropdownMenuContent className="min-w-56 rounded-lg">
                        {organizations.map((item) => (
                            <DropdownMenuItem
                                key={item.id}
                                onClick={() => switchTo(item)}
                            >
                                <span className="truncate">{item.name}</span>
                                {item.id === organization.id && (
                                    <Check className="ml-auto size-4" />
                                )}
                            </DropdownMenuItem>
                        ))}

                        <DropdownMenuSeparator />

                        <DropdownMenuItem
                            onClick={() => router.visit(create())}
                            data-test="create-organization-link"
                        >
                            <Plus className="size-4" />
                            New organization
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
