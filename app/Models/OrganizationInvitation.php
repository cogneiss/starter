<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $email
 * @property-read string $role
 * @property-read string $token
 * @property-read string|null $invited_by_user_id
 * @property-read CarbonInterface $expires_at
 * @property-read CarbonInterface|null $accepted_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization $organization
 * @property-read User|null $invitedBy
 */
final class OrganizationInvitation extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Find the invitation a raw token belongs to. Deliberately unscoped:
     * whoever follows an invitation link has no organization bound yet.
     */
    public static function findByToken(string $token): ?self
    {
        return self::withoutOrganizationScope()
            ->where('token', hash('sha256', $token))
            ->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'organization_id' => 'string',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
