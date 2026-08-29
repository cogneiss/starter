import { Link, usePage } from '@inertiajs/react';
import NotificationBulkController from '@/actions/App/Http/Controllers/NotificationBulkController';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';

/**
 * The unread notifications of the signed-in person, in the organization they are
 * currently in.
 *
 * The rows come from a shared page prop and from nothing else: the query behind
 * that prop is the one place the tenant is applied, so the panel has no way to
 * show a row from an organization the server did not scope to.
 */
export function NotificationPanel() {
    const { recentNotifications } = usePage().props;

    if (recentNotifications.length === 0) {
        return (
            <p
                data-test="notification-panel"
                className="px-3 py-6 text-center text-sm text-muted-foreground"
            >
                You are all caught up.
            </p>
        );
    }

    return (
        <div className="flex flex-col" data-test="notification-panel">
            <ul className="divide-y divide-border">
                {recentNotifications.map((notification) => (
                    <li key={notification.id} className="px-3 py-2 text-sm">
                        {notification.url ? (
                            <a
                                href={notification.url}
                                className="font-medium hover:underline"
                            >
                                {notification.title}
                            </a>
                        ) : (
                            <span className="font-medium">
                                {notification.title}
                            </span>
                        )}

                        <div className="mt-1 flex items-center justify-between gap-2">
                            <time
                                dateTime={notification.created_at}
                                className="text-xs text-muted-foreground"
                            >
                                {new Date(
                                    notification.created_at,
                                ).toLocaleString()}
                            </time>

                            <Link
                                href={NotificationController.update({
                                    notification: notification.id,
                                })}
                                as="button"
                                preserveScroll
                                className="text-xs text-muted-foreground hover:text-foreground"
                            >
                                Mark read
                            </Link>
                        </div>
                    </li>
                ))}
            </ul>

            <Link
                href={NotificationBulkController()}
                as="button"
                preserveScroll
                className="border-t border-border px-3 py-2 text-center text-xs font-medium hover:bg-accent"
            >
                Mark all as read
            </Link>
        </div>
    );
}
