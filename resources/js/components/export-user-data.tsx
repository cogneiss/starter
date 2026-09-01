import { Form } from '@inertiajs/react';
import GdprController from '@/actions/App/Http/Controllers/GdprController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

export default function ExportUserData() {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Export your data"
                description="Download a copy of the personal data we hold about you"
            />

            <Form
                {...GdprController.store.form()}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="secondary"
                        disabled={processing}
                        data-test="export-user-data-button"
                    >
                        Request export
                    </Button>
                )}
            </Form>
        </div>
    );
}
