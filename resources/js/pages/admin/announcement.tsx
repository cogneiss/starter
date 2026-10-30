import { Head, Link, router } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react-inertia';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useTranslate } from '@/lib/i18n';
import { index, pages } from '@/routes/admin';
import { destroy, store, update } from '@/routes/admin/announcements';
import type { AnnouncementLevel, BreadcrumbItem } from '@/types';

type Editable = {
    id: string;
    title: string;
    body: string;
    level: AnnouncementLevel;
    starts_at: string;
    ends_at: string | null;
    organization_id: string | null;
};

type Props = {
    announcement: Editable | null;
    levels: AnnouncementLevel[];
    organizations: { id: string; name: string }[];
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function Announcement({
    announcement,
    levels,
    organizations,
}: Props) {
    const t = useTranslate();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: index() },
        {
            title: t('announcements.title'),
            href: pages({ page: 'announcements' }),
        },
    ];

    const initialLevel: string = announcement?.level ?? 'info';

    const form = useForm(
        announcement ? 'patch' : 'post',
        announcement ? update.url(announcement.id) : store.url(),
        {
            title: announcement?.title ?? '',
            body: announcement?.body ?? '',
            level: initialLevel,
            starts_at: announcement?.starts_at ?? '',
            ends_at: announcement?.ends_at ?? '',
            organization_id: announcement?.organization_id ?? '',
        },
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit();
    };

    const field = (name: keyof typeof form.data) => ({
        id: name,
        name,
        value: form.data[name],
        onBlur: () => form.validate(name),
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('announcements.title')} />

            <form onSubmit={submit} className="max-w-xl space-y-6 p-4">
                <Heading
                    title={t(
                        announcement
                            ? 'announcements.edit'
                            : 'announcements.new',
                    )}
                />

                <div className="grid gap-2">
                    <Label htmlFor="title">
                        {t('announcements.title_field')}
                    </Label>
                    <Input
                        {...field('title')}
                        required
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                    <InputError message={form.errors.title} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="body">
                        {t('announcements.body_field')}
                    </Label>
                    <Input
                        {...field('body')}
                        required
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                    />
                    <InputError message={form.errors.body} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="level">
                        {t('announcements.level_field')}
                    </Label>
                    <select
                        {...field('level')}
                        className={selectClass}
                        onChange={(event) =>
                            form.setData('level', event.target.value)
                        }
                    >
                        {levels.map((level) => (
                            <option key={level} value={level}>
                                {t(`announcements.level_${level}`)}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.level} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="starts_at">
                        {t('announcements.starts_field')}
                    </Label>
                    <Input
                        {...field('starts_at')}
                        type="datetime-local"
                        required
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                    />
                    <InputError message={form.errors.starts_at} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ends_at">
                        {t('announcements.ends_field')}
                    </Label>
                    <Input
                        {...field('ends_at')}
                        type="datetime-local"
                        onChange={(event) =>
                            form.setData('ends_at', event.target.value)
                        }
                    />
                    <InputError message={form.errors.ends_at} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="organization_id">
                        {t('announcements.audience_field')}
                    </Label>
                    <select
                        {...field('organization_id')}
                        className={selectClass}
                        onChange={(event) =>
                            form.setData('organization_id', event.target.value)
                        }
                    >
                        <option value="">
                            {t('announcements.audience_all')}
                        </option>
                        {organizations.map((organization) => (
                            <option
                                key={organization.id}
                                value={organization.id}
                            >
                                {organization.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.organization_id} />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {t('announcements.save')}
                    </Button>

                    {announcement && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroy.url(announcement.id))
                            }
                        >
                            {t('announcements.delete')}
                        </Button>
                    )}

                    <Button
                        variant="ghost"
                        render={
                            <Link href={pages({ page: 'announcements' })} />
                        }
                    >
                        {t('announcements.cancel')}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
