<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Support\OrganizationContext;

/**
 * The delivery log is read-only for viewers; replaying a delivery re-sends
 * data to an external receiver, so it needs the manage permission.
 */
final readonly class WebhookDeliveryPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('webhooks.view');
    }

    public function replay(User $user, WebhookDelivery $delivery): bool
    {
        return $this->context->id() === $delivery->organization_id && $user->can('webhooks.manage');
    }
}
