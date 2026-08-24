<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

use App\Models\Organization;
use App\Models\User;

/**
 * An agent that runs on behalf of one member of one organization. The metering
 * and guardrail middleware only act on these: an anonymous agent has nobody to
 * charge and no organization to scope to, so it passes through untouched.
 *
 * Satisfied by App\Ai\Concerns\OrganizationScopedAgent.
 */
interface OrganizationScoped
{
    public User $user { get; }

    public Organization $organization { get; }
}
