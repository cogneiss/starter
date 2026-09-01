<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Actions\ExportResource;
use App\Data\ResourceListData;
use App\Data\SavedSearchData;
use App\Models\SavedSearch;
use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use App\Support\ResourceQuery;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    protected function listResource(ResourceContract|string $key, Request $request, Closure $row, ?Closure $shape = null): ResourceListData
    {
        $resource = $key instanceof ResourceContract ? $key : resolve(ResourceRegistry::class)->get($key);

        $query = $resource->scopedQuery();

        $query->with(ResourceQuery::relationsIn($resource->searchable()));

        if ($shape instanceof Closure) {
            $shape($query);
        }

        // Saved views are per-organization; the admin control plane runs with
        // no organization context, so there it simply has none.
        /** @var Collection<int, SavedSearch> $searches */
        $searches = resolve(OrganizationContext::class)->id() === null
            ? new Collection
            : SavedSearch::ownedBy($request->user())
                ->where('resource', $resource->key())
                ->orderBy('name')
                ->get();

        $listQuery = $this->listQuery($request, $resource, $searches);

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
            searches: array_values($searches->map(
                fn (SavedSearch $search): SavedSearchData => SavedSearchData::fromModel($search, $resource),
            )->all()),
        );
    }

    /**
     * Whether this request asked for the list as a spreadsheet rather than a
     * screen. The same URL with the same query string answers both, so an export
     * cannot drift away from the filters the person is looking at.
     */
    protected function exportsCsv(Request $request): bool
    {
        return in_array('text/csv', $request->getAcceptableContentTypes(), true);
    }

    /**
     * The list this request describes, streamed as CSV.
     */
    protected function exportResource(ResourceContract|string $key, Request $request): StreamedResponse
    {
        $resource = $key instanceof ResourceContract ? $key : resolve(ResourceRegistry::class)->get($key);

        return resolve(ExportResource::class)->handle(
            $resource,
            ResourceQuery::fromRequest($request, $resource),
            $request->user(),
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

    /**
     * What this request asked for, or — on a first visit — what the person said
     * they usually want.
     *
     * A default only speaks when the URL is silent. The moment the address bar
     * carries any of the list's own parameters the person has said something
     * about this list, and a stored preference must not talk over it; that is
     * also what stops a default from snapping the list back on every sort click.
     *
     * @param  Collection<int, SavedSearch>  $searches
     */
    private function listQuery(Request $request, ResourceContract $resource, Collection $searches): ResourceQuery
    {
        if (! $request->hasAny(ResourceQuery::PARAMETERS)) {
            $default = $searches->first(fn (SavedSearch $search): bool => $search->is_default);

            if ($default instanceof SavedSearch) {
                return ResourceQuery::fromParameters($default->query, $resource);
            }
        }

        return ResourceQuery::fromRequest($request, $resource);
    }
}
