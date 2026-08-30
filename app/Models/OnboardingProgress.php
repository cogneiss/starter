<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\OnboardingProgressFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one person decided about onboarding inside one organization.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property CarbonInterface|null $skipped_at
 * @property CarbonInterface|null $completed_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 */
#[Table(name: 'onboarding_progress')]
final class OnboardingProgress extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<OnboardingProgressFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Every read and every write starts here.
     *
     * The organization arrives from the global scope and the person from the
     * predicate, so somebody else's row is absent from the result rather than
     * fetched and then refused.
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
            'skipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
