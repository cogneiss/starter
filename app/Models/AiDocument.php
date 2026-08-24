<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\AiDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One embedded chunk of organization content.
 *
 * The global scope is the whole security story of retrieval: a similarity
 * search has no natural boundary, so nearest-neighbour on an unscoped table
 * hands one organization the contents of another. Every query here is scoped,
 * and the only way out is an explicit withoutOrganizationScope() call.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $source_type
 * @property-read string $source_id
 * @property-read string $title
 * @property-read string $content
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization $organization
 */
final class AiDocument extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AiDocumentFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }
}
