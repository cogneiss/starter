import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import ImportTemplateController from '@/actions/App/Http/Controllers/ImportTemplateController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { create } from '@/routes/import';
import type { BreadcrumbItem } from '@/types';

type Props = {
    import: string;
    columns: string[];
};

export default function Create({ import: key, columns }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Import', href: create({ import: key }) },
    ];

    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(ImportController.store({ import: key }).url, {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import" />

            <h1 className="sr-only">Import</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Import a file"
                        description="Upload a CSV. Nothing is created until the file has been scanned and read."
                    />

                    <p className="text-sm text-muted-foreground">
                        Columns: {columns.join(', ')}.{' '}
                        <a
                            className="underline underline-offset-4"
                            href={ImportTemplateController({ import: key }).url}
                        >
                            Download a blank template
                        </a>
                    </p>

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="file">File</Label>

                            <Input
                                id="file"
                                type="file"
                                name="file"
                                accept=".csv,text/csv"
                                required
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />

                            <InputError message={form.errors.file} />
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            Upload
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
