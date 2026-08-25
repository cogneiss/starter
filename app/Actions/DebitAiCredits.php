<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Charge an organization for AI usage. App\Ai\Middleware\RecordAudit calls it
 * once per invocation with the audit row as the reference and no actor — the
 * charge is the system's, not a person's. A caller that does pass an actor has
 * that actor's permission checked here rather than at the call site.
 */
final readonly class DebitAiCredits
{
    public function __construct(private OrganizationContext $context) {}

    public function handle(Organization $organization, int $micros, string $reason, ?Model $reference = null, ?User $actor = null): AiCreditLedgerEntry
    {
        throw_if($micros <= 0, InvalidArgumentException::class, 'A charge has to be a positive number of micros.');

        if ($actor instanceof User) {
            $this->context->runAs($organization, fn (): bool => Gate::forUser($actor)->authorize('create', AiCreditLedgerEntry::class)->allowed());
        }

        return AiCreditLedgerEntry::post($organization, -$micros, $reason, $reference);
    }
}
