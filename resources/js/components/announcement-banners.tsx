import { router, usePage } from '@inertiajs/react';
import { XIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dismiss } from '@/routes/announcement';

export function AnnouncementBanners() {
    const announcements = usePage().props.announcements;
    const t = useTranslate();

    if (announcements.length === 0) {
        return null;
    }

    return (
        <section aria-label={t('announcements.region')}>
            {announcements.map((announcement) => (
                <div
                    key={announcement.id}
                    role="status"
                    data-test="announcement-banner"
                    className={cn(
                        'flex w-full items-center justify-center gap-3 border-b px-4 py-2 text-sm',
                        announcement.level === 'warning'
                            ? 'border-destructive bg-card text-destructive'
                            : 'bg-muted text-foreground',
                    )}
                >
                    <p>
                        <strong className="font-medium">
                            {announcement.title}
                        </strong>{' '}
                        {announcement.body}
                    </p>

                    <Button
                        type="button"
                        size="icon-sm"
                        variant="ghost"
                        aria-label={t('announcements.dismiss')}
                        data-test="announcement-dismiss"
                        onClick={() =>
                            router.post(
                                dismiss.url(announcement.id),
                                {},
                                {
                                    preserveScroll: true,
                                    only: ['announcements'],
                                },
                            )
                        }
                    >
                        <XIcon aria-hidden="true" />
                    </Button>
                </div>
            ))}
        </section>
    );
}
