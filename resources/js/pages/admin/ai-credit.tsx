import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { useTranslate } from '@/lib/i18n';
import { index, pages } from '@/routes/admin';
import { store } from '@/routes/admin/ai-credits';
import type { BreadcrumbItem, Organization } from '@/types';

type Props = {
    search: string;
    organizations: Organization[];
    maxMicros: number;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

/** Typing a name should be one request, not one per letter. */
const SEARCH_DEBOUNCE_MS = 300;

export default function AiCredit({ search, organizations, maxMicros }: Props) {
    const t = useTranslate();
    const [term, setTerm] = useState(search);
    const debounce = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: index() },
        {
            title: t('credits.grant_title'),
            href: pages({ page: 'organizations' }),
        },
    ];

    const form = useForm('post', store.url(), {
        organization_id: '',
        micros: '',
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit();
    };

    // A new search replaces the options, so a choice made from the old ones is
    // dropped rather than left selected out of sight.
    const searchOrganizations = (value: string) => {
        setTerm(value);
        form.setData('organization_id', '');
        window.clearTimeout(debounce.current);
        debounce.current = window.setTimeout(
            () =>
                router.reload({
                    data: { search: value },
                    only: ['search', 'organizations'],
                }),
            SEARCH_DEBOUNCE_MS,
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('credits.grant_title')} />

            <h1 className="sr-only">{t('credits.grant_title')}</h1>

            <form onSubmit={submit} className="max-w-xl space-y-6 p-4">
                <Heading title={t('credits.grant_title')} />

                <div className="grid gap-2">
                    <Label htmlFor="organization_id">
                        {t('credits.organization_field')}
                    </Label>
                    <Input
                        type="search"
                        aria-label={t('credits.organization_search')}
                        placeholder={t('credits.organization_search')}
                        data-test="organization-search"
                        value={term}
                        onChange={(event) =>
                            searchOrganizations(event.target.value)
                        }
                    />
                    {organizations.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t('credits.organization_none')}
                        </p>
                    )}
                    <select
                        id="organization_id"
                        name="organization_id"
                        required
                        className={selectClass}
                        value={form.data.organization_id}
                        onChange={(event) =>
                            form.setData('organization_id', event.target.value)
                        }
                        onBlur={() => form.validate('organization_id')}
                    >
                        <option value="">
                            {t('credits.organization_choose')}
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

                <div className="grid gap-2">
                    <Label htmlFor="micros">{t('credits.amount_field')}</Label>
                    <Input
                        id="micros"
                        name="micros"
                        type="number"
                        min={1}
                        max={maxMicros}
                        required
                        value={form.data.micros}
                        onChange={(event) =>
                            form.setData('micros', event.target.value)
                        }
                        onBlur={() => form.validate('micros')}
                    />
                    <InputError message={form.errors.micros} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="reason">{t('credits.reason_field')}</Label>
                    <Input
                        id="reason"
                        name="reason"
                        required
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        onBlur={() => form.validate('reason')}
                    />
                    <InputError message={form.errors.reason} />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {t('credits.grant_submit')}
                    </Button>

                    <Button
                        variant="ghost"
                        render={
                            <Link href={pages({ page: 'organizations' })} />
                        }
                    >
                        {t('credits.cancel')}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
