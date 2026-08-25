<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AiMemoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One fact the assistant was told to remember about one person.
 *
 * Deliberately not organization-scoped by a global scope. The predicate that
 * keeps memory private is written out in App\Ai\Memory\AssistantMemory, on both
 * columns, where it is visible at the point of the query and where removing it
 * fails a test — a silent global scope would hide the control and would still
 * hand a person their own facts from another organization.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property-read string $key
 * @property-read string $value
 * @property-read string $source
 * @property-read ?CarbonInterface $expires_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class AiMemory extends Model
{
    /** @use HasFactory<AiMemoryFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
