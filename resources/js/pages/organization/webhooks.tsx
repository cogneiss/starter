import { Head, router } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react-inertia';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import OrganizationWebhookEndpointController from '@/actions/App/Http/Controllers/OrganizationWebhookEndpointController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { destroy, edit, replay, update } from '@/routes/webhook';
import type { BreadcrumbItem, WebhookDelivery, WebhookEndpoint } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Webhooks',
        href: edit(),
    },
];

export default function Webhooks({
    endpoints,
    deliveries,
    events,
}: {
    endpoints: WebhookEndpoint[];
    deliveries: WebhookDelivery[];
    events: string[];
}) {
    // The signing secret arrives once, in the flash of the request that
    // created the endpoint. It lives in component state until the next
    // navigation and is never part of the page props.
    const [webhookSecret, setWebhookSecret] = useState<string | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const secret = event.detail.flash.webhookSecret;

            if (typeof secret === 'string') {
                setWebhookSecret(secret);
            }
        });
    }, []);

    const form = useForm(
        'post',
        OrganizationWebhookEndpointController.store().url,
        {
            url: '',
            description: '',
            events: [] as string[],
        },
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.submit({
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const toggleEvent = (name: string, checked: boolean) => {
        form.setData(
            'events',
            checked
                ? [...form.data.events, name]
                : form.data.events.filter((item) => item !== name),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Webhooks" />

            <h1 className="sr-only">Webhooks</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Webhooks"
                        description="Signed notifications this organization's records send to your servers"
                    />

                    {webhookSecret && (
                        <div
                            className="space-y-2 rounded-md border p-4"
                            data-test="webhook-secret"
                        >
                            <p className="text-sm font-medium">
                                Copy your signing secret now. It will not be
                                shown again.
                            </p>

                            <code className="block text-sm break-all">
                                {webhookSecret}
                            </code>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="url">URL</Label>

                            <Input
                                id="url"
                                className="mt-1 block w-full"
                                value={form.data.url}
                                onChange={(event) =>
                                    form.setData('url', event.target.value)
                                }
                                onBlur={() => form.validate('url')}
                                name="url"
                                required
                                placeholder="https://example.com/hooks"
                            />

                            <InputError
                                className="mt-2"
                                message={form.errors.url}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                Description (optional)
                            </Label>

                            <Input
                                id="description"
                                className="mt-1 block w-full"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.validate('description')}
                                name="description"
                                placeholder="Reporting integration"
                            />

                            <InputError
                                className="mt-2"
                                message={form.errors.description}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label>Events</Label>

                            {events.map((name) => (
                                <div
                                    key={name}
                                    className="flex items-center space-x-3"
                                >
                                    <Checkbox
                                        id={`event-${name}`}
                                        checked={form.data.events.includes(
                                            name,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleEvent(name, checked === true)
                                        }
                                    />

                                    <Label htmlFor={`event-${name}`}>
                                        {name}
                                    </Label>
                                </div>
                            ))}

                            <InputError
                                className="mt-2"
                                message={form.errors.events}
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-test="create-webhook-button"
                        >
                            Create endpoint
                        </Button>
                    </form>

                    <div className="space-y-3">
                        {endpoints.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No webhook endpoints.
                            </p>
                        )}

                        {endpoints.map((endpoint) => (
                            <div
                                key={endpoint.id}
                                className="flex items-center justify-between rounded-md border p-4"
                            >
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        {endpoint.url}
                                    </p>

                                    <p className="text-sm text-muted-foreground">
                                        {endpoint.events.join(', ')}
                                    </p>

                                    <p className="text-sm text-muted-foreground">
                                        {endpoint.active
                                            ? 'Active'
                                            : 'Deactivated'}
                                        {endpoint.consecutiveFailures > 0 &&
                                            ` · ${endpoint.consecutiveFailures} consecutive failures`}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    {!endpoint.active && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    update({
                                                        endpoint: endpoint.id,
                                                    }).url,
                                                    { active: true },
                                                    { preserveScroll: true },
                                                )
                                            }
                                            data-test={`reactivate-webhook-${endpoint.id}`}
                                        >
                                            Reactivate
                                        </Button>
                                    )}

                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                destroy({
                                                    endpoint: endpoint.id,
                                                }).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                        data-test={`delete-webhook-${endpoint.id}`}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="space-y-3">
                        <Heading
                            variant="small"
                            title="Recent deliveries"
                            description="The latest attempts, newest first"
                        />

                        {deliveries.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No deliveries yet.
                            </p>
                        )}

                        {deliveries.map((delivery) => (
                            <div
                                key={delivery.id}
                                className="flex items-center justify-between rounded-md border p-4"
                            >
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        {delivery.event}
                                    </p>

                                    <p className="text-sm text-muted-foreground">
                                        {delivery.status}
                                        {delivery.statusCode !== null &&
                                            ` · HTTP ${delivery.statusCode}`}
                                        {` · attempt ${delivery.attempt}`}
                                        {delivery.durationMs !== null &&
                                            ` · ${delivery.durationMs}ms`}
                                    </p>
                                </div>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            replay({ delivery: delivery.id })
                                                .url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    data-test={`replay-delivery-${delivery.id}`}
                                >
                                    Replay
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
