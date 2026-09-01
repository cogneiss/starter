<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * One audit entry. The organization global scope makes a cross-organization
 * audit read impossible at the query level, and the creating hook stamps
 * `organization_id` from the bound OrganizationContext at write time.
 *
 * @property-read string $organization_id
 */
final class Activity extends SpatieActivity
{
    use BelongsToOrganization;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;
}
