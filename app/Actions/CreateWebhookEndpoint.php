<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

final readonly class CreateWebhookEndpoint
{
    /**
     * The organization is deliberately not a parameter: the endpoint's
     * organization_id comes from the bound OrganizationContext via the
     * BelongsToOrganization creating hook, so a request cannot name one.
     *
     * The generated secret is returned alongside the endpoint so the
     * controller can flash it once. It is stored encrypted and no endpoint
     * ever returns it again.
     *
     * @param  list<string>  $events
     * @return array{endpoint: WebhookEndpoint, secret: string}
     */
    public function handle(User $user, string $url, ?string $description, array $events): array
    {
        $secret = 'whsec_'.Str::random(40);

        $endpoint = WebhookEndpoint::query()->create([
            'url' => $url,
            'description' => $description,
            'events' => $events,
            'secret' => $secret,
            'active' => true,
            'created_by' => $user->id,
        ]);

        return ['endpoint' => $endpoint, 'secret' => $secret];
    }
}
