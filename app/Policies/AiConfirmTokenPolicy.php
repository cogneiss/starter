<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiConfirmToken;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * A confirmation is addressed to one person. Reading one is gated on the bound
 * organization, on being that person, and on the AI permission.
 *
 * Running one is not gated here: App\Actions\ConsumeConfirmToken re-checks the
 * proposed action's own ability, which is a stronger check than any verb on
 * this row would be.
 */
final readonly class AiConfirmTokenPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('ai.view');
    }

    public function view(User $user, AiConfirmToken $confirmation): bool
    {
        return $this->context->id() === $confirmation->organization_id
            && $confirmation->user_id === $user->id
            && $user->can('ai.view');
    }
}
