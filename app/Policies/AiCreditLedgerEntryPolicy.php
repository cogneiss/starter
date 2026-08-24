<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiCreditLedgerEntry;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Reading the ledger is a usage question; writing to it is a money question, so
 * the two carry different permissions.
 */
final readonly class AiCreditLedgerEntryPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('ai.view');
    }

    public function view(User $user, AiCreditLedgerEntry $entry): bool
    {
        return $this->context->id() === $entry->organization_id && $user->can('ai.view');
    }

    public function create(User $user): bool
    {
        return $this->context->id() !== null && $user->can('ai.grant');
    }
}
