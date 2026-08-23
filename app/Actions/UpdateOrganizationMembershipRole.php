<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Support\OrganizationContext;
use Illuminate\Validation\ValidationException;

final readonly class UpdateOrganizationMembershipRole
{
    public function __construct(
        private AssertNotLastActiveOwner $owners,
        private OrganizationContext $context,
    ) {}

    /**
     * Replace the member's role. Demoting the last owner is refused, so an
     * organization can never end up with nobody who can administer it.
     */
    public function handle(OrganizationMembership $membership, string $role): OrganizationMembership
    {
        $target = Role::query()
            ->where('organization_id', $membership->organization_id)
            ->where('name', $role)
            ->first();

        if (! $target instanceof Role) {
            throw ValidationException::withMessages([
                'role' => __('That role does not exist in this organization.'),
            ]);
        }

        if (! $target->protected) {
            $this->owners->handle($membership);
        }

        $this->context->runAs(
            $membership->organization,
            fn () => $membership->user->syncRoles([$target]),
        );

        return $membership;
    }
}
