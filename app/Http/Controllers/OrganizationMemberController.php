<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReactivateOrganizationMembership;
use App\Actions\RemoveOrganizationMembership;
use App\Actions\SuspendOrganizationMembership;
use App\Actions\UpdateOrganizationMembershipRole;
use App\Data\OrganizationInvitationData;
use App\Data\OrganizationMemberData;
use App\Enums\MembershipStatus;
use App\Http\Requests\UpdateOrganizationMembershipRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationMemberController
{
    public function edit(OrganizationContext $context): Response
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('members.view');

        return Inertia::render('organization-member/edit', [
            'members' => $this->members($organization),
            'invitations' => $this->invitations(),
            'roles' => $this->roles($organization),
        ]);
    }

    public function update(
        UpdateOrganizationMembershipRequest $request,
        OrganizationMembership $membership,
        UpdateOrganizationMembershipRole $updateRole,
        SuspendOrganizationMembership $suspend,
        ReactivateOrganizationMembership $reactivate,
    ): RedirectResponse {
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

    public function destroy(OrganizationMembership $membership, RemoveOrganizationMembership $action): RedirectResponse
    {
        Gate::authorize('delete', $membership);

        $action->handle($membership);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Member removed.'),
        ]);

        return to_route('organization-member.edit');
    }

    /**
     * @return array<int, OrganizationMemberData>
     */
    private function members(Organization $organization): array
    {
        return $organization->memberships()
            ->with('user')
            ->get()
            ->map(fn (OrganizationMembership $membership): OrganizationMemberData => OrganizationMemberData::fromModel($membership))
            ->all();
    }

    /**
     * @return array<int, OrganizationInvitationData>
     */
    private function invitations(): array
    {
        return OrganizationInvitation::query()
            ->whereNull('accepted_at')
            ->orderBy('email')
            ->get()
            ->map(fn (OrganizationInvitation $invitation): OrganizationInvitationData => OrganizationInvitationData::fromModel($invitation))
            ->all();
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
