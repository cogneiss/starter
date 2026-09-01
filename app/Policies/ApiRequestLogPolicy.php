<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiRequestLog;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * The log is append-only, so there is nothing to authorize beyond reading it.
 */
final readonly class ApiRequestLogPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('api.tokens.view');
    }

    public function view(User $user, ApiRequestLog $log): bool
    {
        return $this->context->id() === $log->organization_id && $user->can('api.tokens.view');
    }
}
