<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Every first-party agent runs on behalf of one user inside one organization.
 *
 * Membership is decided by the same method the rest of the application uses, so
 * a deactivated or removed member loses their agents at the same moment they
 * lose everything else. Tools read `$this->organization` rather than reaching
 * for the ambient context, which is what keeps a queued agent honest.
 */
trait OrganizationScopedAgent
{
    public function __construct(
        public readonly User $user,
        public readonly Organization $organization,
    ) {
        throw_unless($user->belongsToOrganization($organization), AuthorizationException::class, 'The user is not a member of that organization.');
    }
}
