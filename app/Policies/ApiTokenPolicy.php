<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiToken;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Tokens belong to the organization, not to the person who minted them: an
 * admin who leaves does not take the integration down with them, and any
 * manager may revoke a token a colleague created.
 */
final readonly class ApiTokenPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('api.tokens.view');
    }

    public function create(User $user): bool
    {
        return $this->context->id() !== null && $user->can('api.tokens.manage');
    }

    public function delete(User $user, ApiToken $token): bool
    {
        return $this->context->id() === $token->organization_id && $user->can('api.tokens.manage');
    }
}
