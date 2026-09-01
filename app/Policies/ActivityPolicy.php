<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Two gates, always: the entry belongs to the bound organization, and the user
 * holds the permission.
 */
final readonly class ActivityPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('audit.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->context->id() === $activity->organization_id && $user->can('audit.view');
    }
}
