import { router, usePage } from "@inertiajs/react";
import { Bell } from "lucide-react";
import { useCallback } from "react";
import { NotificationPanel } from "@/components/notification-panel";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useOrganizationChannel } from "@/hooks/use-organization-channel";

/**
 * The unread badge.
 *
 * A websocket message says only that something happened in the organization —
 * everyone on the channel gets the same nudge — so the count is re-read from the
 * server, which scopes it to this person. Nothing about another member's inbox
 * travels over the wire.
 */
export function NotificationBell() {
    const { unreadNotifications } = usePage().props;

    useOrganizationChannel(
        useCallback(() => {
            router.reload({
                only: ["unreadNotifications", "recentNotifications"],
            });
        }, []),
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label="Notifications"
                    data-test="notification-bell"
                >
                    <Bell className="size-5" />

                    {unreadNotifications > 0 && (
                        <span
                            data-test="notification-badge"
                            className="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] leading-none font-medium text-primary-foreground">
                            {unreadNotifications > 9
                                ? "9+"
                                : unreadNotifications}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80 p-0">
                <NotificationPanel />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
