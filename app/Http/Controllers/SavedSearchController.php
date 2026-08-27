<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SavedSearchRequest;
use App\Models\SavedSearch;
use App\Resources\ResourceRegistry;
use App\Support\ResourceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * A person's kept views of a list.
 *
 * A saved search belongs to one person inside one organization, and every route
 * here starts from {@see SavedSearch::ownedBy()} — the pair is a where clause on
 * the query, not a check on the row that came back. Someone else's saved search
 * is therefore not found rather than forbidden, on reads and writes alike: a 403
 * would confirm the id is real, and a mutating route that trusted a read-side
 * scope would be open.
 */
final readonly class SavedSearchController
{
    public function store(SavedSearchRequest $request): RedirectResponse
    {
        $resource = resolve(ResourceRegistry::class)->get($request->string('resource')->value());

        $search = new SavedSearch([
            'name' => $request->string('name')->value(),
            // Stored as the resource understands it today, and read back through
            // the same normaliser tomorrow.
            'query' => ResourceQuery::fromParameters($request->savedQuery(), $resource)
                ->toQueryParameters($resource),
            'resource' => $resource->key(),
        ]);

        $search->user()->associate($request->user());
        $search->save();

        if ($request->boolean('is_default')) {
            $this->makeDefault($search);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View saved.')]);

        return back();
    }

    /**
     * Opens the list as the saved search describes it — in the address bar, so
     * the view can be shared, reloaded and stepped back out of like any other.
     */
    public function show(Request $request, string $search): RedirectResponse
    {
        $search = $this->owned($request, $search);

        $resource = resolve(ResourceRegistry::class)->get($search->resource);

        $model = $resource->model();

        $parameters = ResourceQuery::fromParameters($search->query, $resource)
            ->toQueryParameters($resource);

        return redirect()->to($resource->url(new $model).'?'.http_build_query($parameters));
    }

    public function update(SavedSearchRequest $request, string $search): RedirectResponse
    {
        $search = $this->owned($request, $search);

        if ($request->has('name')) {
            $search->name = $request->string('name')->value();
            $search->save();
        }

        if ($request->boolean('is_default')) {
            $this->makeDefault($search);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View updated.')]);

        return back();
    }

    public function destroy(Request $request, string $search): RedirectResponse
    {
        $this->owned($request, $search)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('View deleted.')]);

        return back();
    }

    /**
     * The saved search this request may act on, or a 404. The id is a plain
     * string rather than a bound model on purpose: implicit binding would apply
     * the organization scope and stop there, leaving one colleague's views
     * reachable by another's.
     */
    private function owned(Request $request, string $search): SavedSearch
    {
        $search = SavedSearch::ownedBy($request->user())->findOrFail($search);

        Gate::authorize('manage', $search);

        return $search;
    }

    /**
     * One default per list, so a first visit has one answer. Both statements run
     * together or neither does.
     */
    private function makeDefault(SavedSearch $search): void
    {
        DB::transaction(function () use ($search): void {
            SavedSearch::ownedBy($search->user)
                ->where('resource', $search->resource)
                ->whereKeyNot($search->getKey())
                ->update(['is_default' => false]);

            $search->is_default = true;
            $search->save();
        });
    }
}
