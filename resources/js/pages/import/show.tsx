import { Head, Link, router } from '@inertiajs/react';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import ImportRetryController from '@/actions/App/Http/Controllers/ImportRetryController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show } from '@/routes/import';
import type { BreadcrumbItem } from '@/types';

type Failure = {
    line_number: number;
    data: Record<string, string>;
    errors: string[];
};

type Props = {
    batch: {
        id: string;
        import: string;
        status: string;
        imported: number;
        failed: number;
    };
    failures: Failure[];
};

export default function Show({ batch, failures }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Import', href: show({ batch: batch.id }) },
    ];

    const retry = () => {
        router.post(ImportRetryController({ batch: batch.id }).url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import" />

            <h1 className="sr-only">Import</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Import"
                        description={`${batch.imported} imported, ${batch.failed} failed (${batch.status})`}
                    />

                    {failures.length > 0 && (
                        <div className="space-y-4">
                            <ul className="space-y-2 text-sm">
                                {failures.map((failure) => (
                                    <li key={failure.line_number}>
                                        <span className="font-medium">
                                            Line {failure.line_number}
                                        </span>
                                        : {failure.errors.join(' ')}
                                    </li>
                                ))}
                            </ul>

                            <Button type="button" onClick={retry}>
                                Retry failed lines
                            </Button>
                        </div>
                    )}

                    <p className="text-sm text-muted-foreground">
                        <Link
                            className="underline underline-offset-4"
                            href={
                                ImportController.create({
                                    import: batch.import,
                                }).url
                            }
                        >
                            Import another file
                        </Link>
                    </p>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
