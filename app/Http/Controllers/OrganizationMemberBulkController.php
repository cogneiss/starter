<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApplyBulkAction;
use App\Actions\ReactivateOrganizationMembership;
use App\Actions\RemoveOrganizationMembership;
use App\Actions\SuspendOrganizationMembership;
use App\Enums\BulkMembershipAction;
use App\Http\Requests\BulkOrganizationMembershipRequest;
use App\Models\OrganizationMembership;
use App\Resources\ResourceRegistry;
use App\Support\ResourceQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * One action over a selection of members.
 *
 * The gate here only says this person may work with members at all; whether
 * they may touch each record is decided per record, inside the action, by the
 * same policy the single-record routes consult. The result comes back row by
 * row so the screen can say which member was left alone and why.
 */
final readonly class OrganizationMemberBulkController
{
    public function __invoke(
        BulkOrganizationMembershipRequest $request,
        ApplyBulkAction $bulk,
        SuspendOrganizationMembership $suspend,
        ReactivateOrganizationMembership $reactivate,
        RemoveOrganizationMembership $remove,
    ): RedirectResponse {
        Gate::authorize('members.view');

        $action = $request->enum('action', BulkMembershipAction::class);
        assert($action instanceof BulkMembershipAction);

        $resource = resolve(ResourceRegistry::class)->get('organization-members');

        $actor = $request->user()?->getAuthIdentifier();

        $results = $bulk->handle(
            $resource,
            ResourceQuery::fromRequest($request, $resource),
            $request->ids(),
            $request->boolean('all'),
            $action->ability(),
            function (Model $record) use ($action, $actor, $suspend, $reactivate, $remove): void {
                assert($record instanceof OrganizationMembership);

                // Removing someone else is administration; removing yourself is
                // leaving, and a tick box over a whole page is not how anyone
                // means to leave. Refusing the one row names it on the way back
                // rather than dropping it silently.
                if ($action === BulkMembershipAction::Remove && $record->user_id === $actor) {
                    throw ValidationException::withMessages([
                        'ids' => __('You cannot remove yourself.'),
                    ]);
                }

                match ($action) {
                    BulkMembershipAction::Suspend => $suspend->handle($record),
                    BulkMembershipAction::Reactivate => $reactivate->handle($record),
                    BulkMembershipAction::Remove => $remove->handle($record),
                };
            },
            $request->user(),
        );

        Inertia::flash('bulk', $results);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->message($results),
        ]);

        return to_route('organization-member.edit');
    }

    /**
     * @param  list<array{id: string, label: string, status: string}>  $results
     */
    private function message(array $results): string
    {
        $skipped = [];

        foreach ($results as $result) {
            if ($result['status'] !== 'applied') {
                $skipped[] = $result['label'];
            }
        }

        $message = __(':count member(s) updated.', ['count' => count($results) - count($skipped)]);

        if ($skipped !== []) {
            $message .= ' '.__('Left alone: :skipped.', ['skipped' => implode(', ', $skipped)]);
        }

        return $message;
    }
}
