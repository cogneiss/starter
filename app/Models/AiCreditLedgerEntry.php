<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Support\OrganizationContext;
use Carbon\CarbonInterface;
use Database\Factories\AiCreditLedgerEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A single movement of AI credit, in signed integer micros. The organization's
 * balance is the sum of its rows — never a column on organizations, which two
 * concurrent writers could disagree about.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read int $delta_micros
 * @property-read string $reason
 * @property-read string|null $reference_type
 * @property-read string|null $reference_id
 * @property-read int $balance_micros_after
 * @property-read CarbonInterface $created_at
 * @property-read Organization $organization
 */
final class AiCreditLedgerEntry extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AiCreditLedgerEntryFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'ai_credit_ledger';

    /**
     * Append one movement and carry the running balance forward, in the one
     * transaction so that two writers cannot both read the same balance.
     * Go through App\Actions\GrantAiCredits or App\Actions\DebitAiCredits.
     */
    public static function post(Organization $organization, int $deltaMicros, string $reason, ?Model $reference = null): self
    {
        return DB::transaction(fn (): self => resolve(OrganizationContext::class)->runAs(
            $organization,
            fn (): self => self::query()->create([
                'delta_micros' => $deltaMicros,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'balance_micros_after' => self::balanceMicros() + $deltaMicros,
            ]),
        ));
    }

    /**
     * The balance of the organization bound to the current context, in micros.
     * Summed rather than read off the newest row so that two rows written in
     * the same millisecond cannot make the answer depend on tie-breaking.
     */
    public static function balanceMicros(): int
    {
        return (int) self::query()->sum('delta_micros');
    }

    /**
     * Editing a ledger row rewrites history and breaks every balance after it.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('ai_credit_ledger is append-only — post a correcting entry instead.');
    }

    public function delete(): bool
    {
        throw new LogicException('ai_credit_ledger is append-only — entries are never deleted.');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'organization_id' => 'string',
            'delta_micros' => 'integer',
            'reference_id' => 'string',
            'balance_micros_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
