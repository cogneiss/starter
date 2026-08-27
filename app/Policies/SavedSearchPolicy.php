<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * A saved search is one person's own note about a list, so ownership is the
 * whole of the question — there is no shared permission to hold, and no role
 * that grants a colleague's views.
 *
 * The real gate is the pair of where clauses in {@see SavedSearch::ownedBy()},
 * which is why a foreign id is a 404 here rather than a 403. This policy is the
 * second lock on the same door: it repeats the pair against the record that came
 * back, so a route that ever forgets to start from `ownedBy()` still refuses.
 */
final readonly class SavedSearchPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function manage(User $user, SavedSearch $search): bool
    {
        return $this->context->id() === $search->organization_id
            && $user->id === $search->user_id;
    }
}
