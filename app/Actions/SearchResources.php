<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\SearchGroupData;
use App\Data\SearchResultData;
use App\Models\User;
use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Searches every registered resource at once for the command palette.
 *
 * Two gates decide what comes back. The organization gate is the resource's own
 * scoped query, so another organization's rows are excluded by a where clause
 * and never loaded. The permission gate is the resource's policy: a member who
 * may not view a resource gets no group for it, rather than a 403 that would
 * take down the whole search because of one resource they cannot see.
 */
final readonly class SearchResources
{
    /**
     * Hits per resource. A palette is a shortcut, not a list screen — past a
     * handful of rows per group the keyboard is slower than the list itself.
     */
    private const int PER_GROUP = 5;

    public function __construct(
        private ResourceRegistry $registry,
        private Gate $gate,
    ) {}

    /**
     * @return list<SearchGroupData>
     */
    public function handle(User $user, string $term): array
    {
        $term = mb_trim($term);

        if ($term === '') {
            return [];
        }

        $groups = [];

        foreach ($this->registry->all() as $resource) {
            if (! $this->allowed($user, $resource)) {
                continue;
            }

            $results = $this->results($resource, $term);

            if ($results === []) {
                continue;
            }

            $groups[] = new SearchGroupData(
                key: $resource->key(),
                label: $resource->label(),
                results: $results,
            );
        }

        return $groups;
    }

    /**
     * A resource without a policy is one no member can be forbidden from seeing;
     * the scoped query is the whole of its authorization.
     */
    private function allowed(User $user, ResourceContract $resource): bool
    {
        if ($resource->policy() === null) {
            return true;
        }

        return $this->gate->forUser($user)->allows('viewAny', $resource->model());
    }

    /**
     * @return list<SearchResultData>
     */
    private function results(ResourceContract $resource, string $term): array
    {
        $records = $this->matching($resource, $term)->limit(self::PER_GROUP)->get();

        $results = [];

        foreach ($records as $record) {
            $results[] = new SearchResultData(
                label: $resource->recordLabel($record),
                description: $resource->recordDescription($record),
                url: $resource->url($record),
            );
        }

        return $results;
    }

    /**
     * @return Builder<covariant Model>
     */
    private function matching(ResourceContract $resource, string $term): Builder
    {
        $columns = $resource->searchable();

        // A term is user input, so its wildcards are escaped: searching for
        // "100%" must not turn into a match-everything pattern.
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query = $resource->scopedQuery();

        $relations = [];

        foreach ($columns as $column) {
            if (str_contains($column, '.')) {
                $relations[] = explode('.', $column, 2)[0];
            }
        }

        if ($relations !== []) {
            $query->with(array_unique($relations));
        }

        return $query->where(function (Builder $inner) use ($columns, $like): void {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);

                    $inner->orWhereHas(
                        $relation,
                        fn (Builder $related): Builder => $related->where($field, 'like', $like),
                    );

                    continue;
                }

                $inner->orWhere($column, 'like', $like);
            }
        });
    }
}
