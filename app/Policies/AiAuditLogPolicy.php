<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiAuditLog;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * The log is append-only, so there is nothing to authorize beyond reading it.
 */
final readonly class AiAuditLogPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('ai.view');
    }

    public function view(User $user, AiAuditLog $log): bool
    {
        return $this->context->id() === $log->organization_id && $user->can('ai.view');
    }
}
