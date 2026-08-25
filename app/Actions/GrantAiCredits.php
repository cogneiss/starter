<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Add AI credit to an organization. Pass the person doing it as `$actor` and the
 * action checks their permission itself; the seed and console paths pass null,
 * which is the system granting credit to nobody's account but the organization's.
 */
final readonly class GrantAiCredits
{
    public function __construct(private OrganizationContext $context) {}

    public function handle(Organization $organization, int $micros, string $reason, ?User $actor = null): AiCreditLedgerEntry
    {
        throw_if($micros <= 0, InvalidArgumentException::class, 'A grant has to be a positive number of micros.');

        if ($actor instanceof User) {
            $this->context->runAs($organization, fn (): bool => Gate::forUser($actor)->authorize('create', AiCreditLedgerEntry::class)->allowed());
        }

        return AiCreditLedgerEntry::post($organization, $micros, $reason);
    }
}
