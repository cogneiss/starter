<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReactivateOrganizationMembership;
use App\Actions\RemoveOrganizationMembership;
use App\Actions\SuspendOrganizationMembership;
use App\Actions\UpdateOrganizationMembershipRole;
use App\Data\OrganizationMemberData;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Concerns\ListsResources;
use App\Http\Requests\UpdateOrganizationMembershipRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationMemberController
{
    use ListsResources;

    public function edit(Request $request, OrganizationContext $context): Response
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('members.view');

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
