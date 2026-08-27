<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApplyBulkAction;
use App\Actions\ReactivateOrganizationMembership;
use App\Actions\RemoveOrganizationMembership;
use App\Actions\SuspendOrganizationMembership;
use App\Actions\UpdateOrganizationMembershipRole;
use App\Data\OrganizationMemberData;
use App\Enums\BulkMembershipAction;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Concerns\ListsResources;
use App\Http\Requests\BulkOrganizationMembershipRequest;
use App\Http\Requests\UpdateOrganizationMembershipRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use App\Support\ResourceQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class OrganizationMemberController
{
    use ListsResources;

    public function edit(Request $request, OrganizationContext $context): Response|StreamedResponse
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('members.view');

        if ($this->exportsCsv($request)) {
            return $this->exportResource('organization-members', $request);
        }

        return Inertia::render('organization-member/edit', [
            'members' => $this->listResource(
                'organization-members',
                $request,
                $this->memberRow(...),
            ),
            'roles' => $this->roles($organization),
        ]);
    }

    public function update(
        UpdateOrganizationMembershipRequest $request,
        string $membership,
        UpdateOrganizationMembershipRole $updateRole,
        SuspendOrganizationMembership $suspend,
        ReactivateOrganizationMembership $reactivate,
    ): RedirectResponse {
        $membership = $this->membership($membership);

        Gate::authorize('update', $membership);

        if ($request->has('role')) {
            $updateRole->handle($membership, $request->string('role')->value());
        }

        if ($request->has('status')) {
            $request->enum('status', MembershipStatus::class) === MembershipStatus::Suspended
                ? $suspend->handle($membership)
                : $reactivate->handle($membership);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Member updated.'),
        ]);

        return to_route('organization-member.edit');
    }

    public function destroy(string $membership, RemoveOrganizationMembership $action): RedirectResponse
    {
        $membership = $this->membership($membership);

        Gate::authorize('delete', $membership);

        $action->handle($membership);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Member removed.'),
        ]);

        return to_route('organization-member.edit');
    }

    /**
     * One action over a selection of members.
     *
     * The gate here only says this person may work with members at all; whether
     * they may touch each record is decided per record, inside the action, by
     * the same policy `update()` and `destroy()` consult. The result comes back
     * row by row so the screen can say which member was left alone and why.
     */
    public function bulk(
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

        $results = $bulk->handle(
            $resource,
            ResourceQuery::fromRequest($request, $resource),
            $request->ids(),
            $request->boolean('all'),
            $action->ability(),
            function (Model $record) use ($action, $suspend, $reactivate, $remove): void {
                assert($record instanceof OrganizationMembership);

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
            'message' => $this->bulkMessage($results),
        ]);

        return to_route('organization-member.edit');
    }

    /**
     * @param  list<array{id: string, label: string, status: string}>  $results
     */
    private function bulkMessage(array $results): string
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

    /**
     * The membership behind a row, found inside the organization scope so an id
     * from elsewhere is a 404 rather than a policy decision about a real record.
     */
    private function membership(string $id): OrganizationMembership
    {
        $membership = $this->findListed('organization-members', $id);
        assert($membership instanceof OrganizationMembership);

        return $membership;
    }

    private function memberRow(Model $record): OrganizationMemberData
    {
        assert($record instanceof OrganizationMembership);

        return OrganizationMemberData::fromModel($record);
    }

    /**
     * @return array<int, string>
     */
    private function roles(Organization $organization): array
    {
        return Role::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): string => $role->name)
            ->all();
    }
}
