<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OnboardingProgress;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Skipping the activation checklist is one person's own decision, so ownership
 * is the whole of the question — no role grants a colleague's decision.
 *
 * The real gate is the pair of where clauses in
 * {@see OnboardingProgress::ownedBy()}, which is why a foreign row is simply
 * not there rather than refused. This policy is the second lock on the same
 * door, repeating the pair against the record that came back.
 */
final readonly class OnboardingProgressPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function manage(User $user, OnboardingProgress $progress): bool
    {
        return $this->context->id() === $progress->organization_id
            && $user->id === $progress->user_id;
    }
}
