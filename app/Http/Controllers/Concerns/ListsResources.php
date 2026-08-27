<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Data\ResourceListData;
use App\Resources\ResourceRegistry;
use App\Support\ResourceQuery;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * Search, sort and pagination for a listed resource, once.
 *
 * Every list starts from the resource's own `scopedQuery()` — the organization
 * is a where clause on the query the database runs, not a check on the rows it
 * returns. A record belonging to another organization is therefore not merely
 * hidden from the list: it is not in the result set, and `findListed()` cannot
 * reach it either, so a foreign id is a 404 before any policy is consulted.
 */
trait ListsResources
{
    /**
     * @param  Closure(Model): Data  $row  Turns one record into its Data object.
     * @param  Closure(Builder<covariant Model>): mixed|null  $shape  Eager loads and any list-specific narrowing.
     */
    protected function listResource(string $key, Request $request, Closure $row, ?Closure $shape = null): ResourceListData
    {
        $resource = resolve(ResourceRegistry::class)->get($key);

        $query = $resource->scopedQuery();

        $query->with(ResourceQuery::relationsIn($resource->searchable()));

        if ($shape instanceof Closure) {
            $shape($query);
        }

        $listQuery = ResourceQuery::fromRequest($request, $resource);

        // Counted before the filters narrow the query, so a facet can say what
        // an option would leave rather than what the current one already left.
        $facets = $listQuery->facets(clone $query, $resource);

        $page = $listQuery->applyTo($query, $resource)->paginate(
            perPage: $listQuery->per,
            page: $listQuery->page,
        );

        return new ResourceListData(
            rows: array_values(array_map($row, $page->items())),
            total: $page->total(),
            pages: $page->lastPage(),
            query: $listQuery,
            filters: $facets,
        );
    }

    /**
     * The one record behind a listed row, or a 404. The lookup runs inside the
     * same scope the list did, so an id from another organization does not exist
     * as far as this request is concerned — which is the answer a person outside
     * the organization should get, rather than a 403 confirming it is real.
     */
    protected function findListed(string $key, string $id): Model
    {
        return resolve(ResourceRegistry::class)->get($key)->scopedQuery()->findOrFail($id);
    }
}
