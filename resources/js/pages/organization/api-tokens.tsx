import { Head, router } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react-inertia';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import OrganizationApiTokenController from '@/actions/App/Http/Controllers/OrganizationApiTokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { destroy, edit } from '@/routes/api-token';
import type { ApiToken, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API tokens',
        href: edit(),
    },
];

export default function ApiTokens({
    tokens,
    abilities,
}: {
    tokens: ApiToken[];
    abilities: string[];
}) {
    // The plaintext arrives once, in the flash of the request that created the
    // token. It lives in component state until the next navigation and is
    // never part of the page props.
    const [plainTextToken, setPlainTextToken] = useState<string | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const token = event.detail.flash.plainTextToken;

            if (typeof token === 'string') {
                setPlainTextToken(token);
            }
        });
    }, []);

    const form = useForm('post', OrganizationApiTokenController.store().url, {
        name: '',
        abilities: [] as string[],
        expires_at: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.submit({
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const toggleAbility = (ability: string, checked: boolean) => {
        form.setData(
            'abilities',
            checked
                ? [...form.data.abilities, ability]
                : form.data.abilities.filter((item) => item !== ability),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API tokens" />

            <h1 className="sr-only">API tokens</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="API tokens"
                        description="Tokens for reading this organization's data over the API"
                    />

                    {plainTextToken && (
                        <div
                            className="space-y-2 rounded-md border p-4"
                            data-test="plain-text-token"
                        >
                            <p className="text-sm font-medium">
                                Copy your new token now. It will not be shown
                                again.
                            </p>

                            <code className="block text-sm break-all">
                                {plainTextToken}
                            </code>
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>

                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                onBlur={() => form.validate('name')}
                                name="name"
                                required
                                placeholder="Reporting integration"
                            />

                            <InputError
                                className="mt-2"
                                message={form.errors.name}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label>Abilities</Label>

                            {abilities.map((ability) => (
                                <div
                                    key={ability}
                                    className="flex items-center space-x-3"
                                >
                                    <Checkbox
                                        id={`ability-${ability}`}
                                        checked={form.data.abilities.includes(
                                            ability,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleAbility(
                                                ability,
                                                checked === true,
                                            )
                                        }
                                    />

                                    <Label htmlFor={`ability-${ability}`}>
                                        {ability}
                                    </Label>
                                </div>
                            ))}

                            <InputError
                                className="mt-2"
                                message={form.errors.abilities}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="expires_at">
                                Expires at (optional)
                            </Label>

                            <Input
                                id="expires_at"
                                type="date"
                                className="mt-1 block w-full"
                                value={form.data.expires_at}
                                onChange={(event) =>
                                    form.setData(
                                        'expires_at',
                                        event.target.value,
                                    )
                                }
                                onBlur={() => form.validate('expires_at')}
                                name="expires_at"
                            />

                            <InputError
                                className="mt-2"
                                message={form.errors.expires_at}
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-test="create-token-button"
                        >
                            Create token
                        </Button>
                    </form>

                    <div className="space-y-3">
                        {tokens.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No active tokens.
                            </p>
                        )}

                        {tokens.map((token) => (
                            <div
                                key={token.id}
                                className="flex items-center justify-between rounded-md border p-4"
                            >
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        {token.name}
                                    </p>

                                    <p className="text-sm text-muted-foreground">
                                        {token.abilities.join(', ')}
                                    </p>

                                    <p className="text-sm text-muted-foreground">
                                        {token.lastUsedAt
                                            ? `Last used ${new Date(token.lastUsedAt).toLocaleDateString()}`
                                            : 'Never used'}
                                        {token.expiresAt &&
                                            ` · Expires ${new Date(token.expiresAt).toLocaleDateString()}`}
                                    </p>
                                </div>

                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            destroy({ token: token.id }).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                    data-test={`revoke-token-${token.id}`}
                                >
                                    Revoke
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
