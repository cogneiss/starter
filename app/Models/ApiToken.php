<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A per-organization API token. The plaintext is shown exactly once at creation;
 * only its sha256 is stored, which is what Sanctum's `findToken()` compares.
 *
 * Every request the token authenticates is pinned to `organization_id` by
 * {@see \App\Http\Middleware\EnsureTokenMatchesOrganization} — nothing in the
 * request may move it.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $tokenable_id
 * @property-read string $tokenable_type
 * @property-read string|null $created_by
 * @property-read string $name
 * @property-read string $token
 * @property-read list<string>|null $abilities
 * @property-read CarbonInterface|null $last_used_at
 * @property-read CarbonInterface|null $expires_at
 * @property-read CarbonInterface|null $revoked_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization $organization
 * @property-read User|null $creator
 */
#[Table(name: 'personal_access_tokens')]
final class ApiToken extends PersonalAccessToken
{
    use BelongsToOrganization;

    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Sanctum resolves the bearer token before any organization is bound, so
     * the lookup deliberately crosses the scope: the token itself is what
     * decides the organization, in EnsureTokenMatchesOrganization.
     */
    public static function findToken($token): ?self
    {
        if (! str_contains($token, '|')) {
            return null;
        }

        [$id, $plain] = explode('|', $token, 2);

        // The id column is a uuid; anything else would be a database error
        // rather than the 401 a garbage credential deserves.
        if (! Str::isUuid($id)) {
            return null;
        }

        $instance = self::withoutOrganizationScope()->find($id);

        if ($instance instanceof self && hash_equals($instance->token, hash('sha256', $plain))) {
            // Sanctum reads tokenable before any organization is bound, and
            // relation autoloading would eager-load it through a query that
            // carries this model's organization scope. Loading it here, through
            // the typed morph query, keeps that lookup scope-free too.
            $instance->setRelation('tokenable', $instance->tokenable()->getResults());

            return $instance;
        }

        return null;
    }

    /**
     * Not revoked and not expired — the only tokens the settings list shows.
     *
     * @return Builder<self>
     */
    public static function active(): Builder
    {
        return self::query()
            ->whereNull('revoked_at')
            ->where(fn (Builder $inner) => $inner
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'organization_id' => 'string',
            'created_by' => 'string',
            'revoked_at' => 'datetime',
        ];
    }
}
