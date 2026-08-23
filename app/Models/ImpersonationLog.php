<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ImpersonationLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An impersonation session, from start to stop. Deliberately not organization
 * scoped: platform staff impersonate across organizations, and the audit trail
 * has to be readable without one bound.
 *
 * @property-read string $id
 * @property-read string $impersonator_user_id
 * @property-read string $impersonated_user_id
 * @property-read string|null $organization_id
 * @property-read CarbonInterface $started_at
 * @property-read CarbonInterface|null $ended_at
 * @property-read string|null $ip_address
 * @property-read string|null $user_agent
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $impersonator
 * @property-read User $impersonated
 */
final class ImpersonationLog extends Model
{
    /** @use HasFactory<ImpersonationLogFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'impersonator_user_id' => 'string',
            'impersonated_user_id' => 'string',
            'organization_id' => 'string',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
