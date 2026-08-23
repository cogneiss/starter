<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationInvitation;
use App\Actions\RevokeOrganizationInvitation;
use App\Http\Requests\CreateOrganizationInvitationRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationInvitationController
{
    public function create(OrganizationContext $context): Response
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('members.invite');

        return Inertia::render('organization-invitation/create', [
            'roles' => Role::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): string => $role->name)
                ->all(),
        ]);
    }

    public function store(
        CreateOrganizationInvitationRequest $request,
        OrganizationContext $context,
        #[CurrentUser] User $user,
        CreateOrganizationInvitation $action,
    ): RedirectResponse {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('members.invite');

        $action->handle(
            $organization,
            $user,
            $request->string('email')->value(),
            $request->string('role')->value(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invitation sent.'),
        ]);

        return to_route('organization-member.edit');
    }

    public function destroy(OrganizationInvitation $invitation, RevokeOrganizationInvitation $action): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $action->handle($invitation);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invitation revoked.'),
        ]);

        return to_route('organization-member.edit');
    }
}
