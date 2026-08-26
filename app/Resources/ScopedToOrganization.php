<?php

declare(strict_types=1);

namespace App\Resources;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fail-closed scoping for resources whose model carries no organization global
 * scope. The narrowing is a where clause on the query the caller receives, so a
 * record outside the bound organization is unreachable rather than filtered out
 * afterwards.
 *
 * @phpstan-require-implements ResourceContract
 */
trait ScopedToOrganization
{
    /**
     * @template TModel of Model
     *
     * @param  Closure(Organization): Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopedToOrganization(Closure $query): Builder
    {
        $organization = resolve(OrganizationContext::class)->get();

        // No bound organization means no rows, never every row. The unsaved
        // organization keeps the closure's shape; the false predicate is what
        // decides it.
        if (! $organization instanceof Organization) {
            return $query(new Organization)->whereRaw('1 = 0');
        }

        return $query($organization);
    }
}
