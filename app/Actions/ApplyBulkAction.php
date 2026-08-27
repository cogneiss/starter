<?php

declare(strict_types=1);

namespace App\Actions;

use App\Resources\ResourceContract;
use App\Support\ResourceQuery;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * One action, applied to a selection, one record at a time.
 *
 * Two things a bulk path gets wrong. The first is authorisation: it is tempting
 * to check the ability once and then loop, which grants the whole selection on
 * the strength of the easiest record in it. Every record here goes through the
 * same gate the single-record path uses, and a record the person may not touch
 * is reported back by name rather than silently skipped or turned into a 500
 * that rolls back the work already done.
 *
 * The second is reach. A selection is what the person can see — the current
 * page — unless they say otherwise. Acting on everything the filters match is a
 * separate, explicit request, so a tick box that reads "select all" cannot
 * quietly mean the whole table.
 */
final readonly class ApplyBulkAction
{
    private const int CHUNK = 500;

    /**
     * @param  list<string>  $ids  The rows the person ticked.
     * @param  bool  $all  Whether they opted in to every record the filters match.
     * @param  Closure(Model): void  $act
     * @return list<array{id: string, label: string, status: string}>
     */
    public function handle(
        ResourceContract $resource,
        ResourceQuery $query,
        array $ids,
        bool $all,
        string $ability,
        Closure $act,
        ?Authenticatable $user,
    ): array {
        $scoped = $query->applyTo($resource->scopedQuery(), $resource);

        $records = $all
            // Chunked by key: "everything the filters match" is not a number the
            // request gets to choose, so it must not be a number held in memory.
            ? $scoped->lazyById(self::CHUNK)
            // Clamped on the query, not after it: a selection of ids from beyond
            // the page never reaches the action to be filtered out later.
            : $scoped->whereKey($ids)->forPage($query->page, $query->per)->get();

        $results = [];

        foreach ($records as $record) {
            $key = $record->getKey();
            assert(is_string($key) || is_int($key));

            $results[] = [
                'id' => (string) $key,
                'label' => $resource->recordLabel($record),
                'status' => $this->apply($record, $ability, $act, $user),
            ];
        }

        return $results;
    }

    /**
     * One record's outcome, in the vocabulary the screen reports back.
     *
     * A rule the action itself enforces — the last owner, say — refuses that one
     * record and nothing else. Letting it escape would abandon the selection
     * half-done with no account of which half.
     *
     * @param  Closure(Model): void  $act
     */
    private function apply(Model $record, string $ability, Closure $act, ?Authenticatable $user): string
    {
        if (! Gate::forUser($user)->allows($ability, $record)) {
            return 'unauthorized';
        }

        try {
            $act($record);
        } catch (ValidationException) {
            return 'refused';
        }

        return 'applied';
    }
}
