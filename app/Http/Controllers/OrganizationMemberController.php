<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReactivateOrganizationMembership;
use App\Actions\RemoveOrganizationMembership;
use App\Actions\SuspendOrganizationMembership;
use App\Actions\UpdateOrganizationMembershipRole;
use App\Data\OrganizationMemberData;
use App\Data\RecordPeekData;
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
use Illuminate\Validation\ValidationException;
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
            'peek' => $this->peek($request),
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
            try {
                $updateRole->handle($membership, $request->string('role')->value());
            } catch (ValidationException $exception) {
                // An inline edit puts the row back to what the server last said
                // the moment the patch is refused, so the reason has to arrive
                // the way every other outcome does — as a flash toast — or the
                // value silently snaps back with nothing said.
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }
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
     * The record a `?peek=` in the address bar asks for, or nothing.
     *
     * It is found by the same scoped lookup the rest of this controller uses, so
     * a link carrying an id from another organization is a 404 — the drawer has
     * no other way to be handed a record.
     */
    private function peek(Request $request): ?RecordPeekData
    {
        if (! $request->filled('peek')) {
            return null;
        }

        $member = $this->memberRow($this->membership($request->string('peek')->value()));

        return new RecordPeekData(
            id: $member->id,
            title: $member->name,
            fields: [
                'Email' => $member->email,
                'Status' => $member->status->value,
                'Role' => $member->role ?? 'None',
            ],
        );
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
