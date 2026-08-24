<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCreditLedgerEntry>
 */
final class AiCreditLedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'delta_micros' => 1_000_000,
            'reason' => 'Signup grant',
            'reference_type' => null,
            'reference_id' => null,
            'balance_micros_after' => 1_000_000,
            'created_at' => now(),
        ];
    }
}
