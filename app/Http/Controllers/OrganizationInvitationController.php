<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationInvitation;
use App\Actions\RevokeOrganizationInvitation;
use App\Data\OrganizationInvitationData;
use App\Http\Controllers\Concerns\ListsResources;
use App\Http\Requests\CreateOrganizationInvitationRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationInvitationController
{
    use ListsResources;

    /**
     * Invitations outgrew the members screen the moment there were more of them
     * than fit under it, so they have a list of their own — the same list kit,
     * pointed at a different resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('members.view');

        return Inertia::render('organization-invitation/index', [
            'invitations' => $this->listResource(
                'organization-invitations',
                $request,
                $this->invitationRow(...),
                fn (Builder $query): Builder => $query->whereNull('accepted_at'),
            ),
        ]);
    }

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

        return to_route('organization-invitation.index');
    }

    public function destroy(string $invitation, RevokeOrganizationInvitation $action): RedirectResponse
    {
        $record = $this->findListed('organization-invitations', $invitation);
        assert($record instanceof OrganizationInvitation);

        Gate::authorize('delete', $record);

        $action->handle($record);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invitation revoked.'),
        ]);

        return to_route('organization-invitation.index');
    }

    private function invitationRow(Model $record): OrganizationInvitationData
    {
        assert($record instanceof OrganizationInvitation);

        return OrganizationInvitationData::fromModel($record);
    }
}
