<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\OrganizationContext;

/**
 * Endpoints belong to the organization, not to the person who registered
 * them: anyone with the manage permission may edit or delete a colleague's
 * endpoint.
 */
final readonly class WebhookEndpointPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('webhooks.view');
    }

    public function create(User $user): bool
    {
        return $this->context->id() !== null && $user->can('webhooks.manage');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->context->id() === $endpoint->organization_id && $user->can('webhooks.manage');
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->context->id() === $endpoint->organization_id && $user->can('webhooks.manage');
    }
}
