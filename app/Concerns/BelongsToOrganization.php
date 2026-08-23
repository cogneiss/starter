<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\OrganizationContextMissing;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the organization bound in OrganizationContext, and fills
 * organization_id on create. Fail-closed: with no organization bound it either
 * throws (strict) or returns nothing. It never returns every organization's rows.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $model = $builder->getModel();

            $organizationId = resolve(OrganizationContext::class)->id();

            if ($organizationId === null) {
                if (config()->boolean('organizations.strict')) {
                    throw OrganizationContextMissing::for($model::class);
                }

                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($model->qualifyColumn('organization_id'), $organizationId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', resolve(OrganizationContext::class)->id());
            }
        });
    }

    /**
     * Query across every organization. Only for deliberate cross-organization
     * work (platform admin listings, impersonation lookups) — say why at the
     * call site.
     *
     * @return Builder<static>
     */
    public static function withoutOrganizationScope(): Builder
    {
        return static::query()->withoutGlobalScope('organization');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
