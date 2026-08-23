<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * An organization-scoped role. Roles are cloned from RoleTemplate when an
 * organization is created; protected roles cannot be renamed or deleted.
 *
 * @property-read int $id
 * @property-read string|null $organization_id
 * @property-read string $name
 * @property-read string $guard_name
 * @property-read bool $protected
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization|null $organization
 */
final class Role extends SpatieRole
{
    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'organization_id' => 'string',
            'name' => 'string',
            'guard_name' => 'string',
            'protected' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
