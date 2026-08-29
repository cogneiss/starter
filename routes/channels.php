<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Who may listen to an organization's channel.
 *
 * Two gates, the same two the policies use. Membership is a query — an active
 * row in `organization_memberships` for this user and this organization — so a
 * channel name naming a tenant the caller has no row for is refused before
 * anything is loaded. The permission gate is the second: a person may hold a
 * membership and still not be allowed to read the organization, and a websocket
 * is a read.
 */
Broadcast::channel(
    'organization.{organization}',
    fn (User $user, Organization $organization): bool => $user->belongsToOrganization($organization)
        && $user->can('viewAny', Organization::class),
);
