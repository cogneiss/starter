<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\SavedSearchFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A list view someone arranged and kept.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property-read string $resource
 * @property string $name
 * @property-read array<string, mixed> $query
 * @property bool $is_default
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 */
final class SavedSearch extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Every read and every write starts here.
     *
     * Saved searches are not shared, so "mine" is two where clauses and not a
     * check on a record that has already been fetched: the organization comes
     * from the global scope, the person from the predicate below. A saved search
     * belonging to someone else — in this organization or another — is therefore
     * absent from the result set rather than found and then refused, which is
     * what makes a foreign id a 404 on every route instead of a 403 confirming
     * the id is real.
     *
     * @return Builder<self>
     */
    public static function ownedBy(?Authenticatable $user): Builder
    {
        return self::query()->where('user_id', $user?->getAuthIdentifier());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'organization_id' => 'string',
            'user_id' => 'string',
            'query' => 'array',
            'is_default' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
