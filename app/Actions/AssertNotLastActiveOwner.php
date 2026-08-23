<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Support\OrganizationContext;
use Illuminate\Validation\ValidationException;

final readonly class AssertNotLastActiveOwner
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * Refuse a change that would leave the organization with nobody holding the
     * protected owner role. Removal, suspension and demotion all route here.
     */
    public function handle(OrganizationMembership $membership): void
    {
        if (! $this->isOwner($membership) || ! $membership->isActive()) {
            return;
        }

        if ($this->activeOwnerCount($membership) > 1) {
            return;
        }

        throw ValidationException::withMessages([
            'membership' => __('An organization must keep at least one active owner.'),
        ]);
    }

    private function isOwner(OrganizationMembership $membership): bool
    {
        return $this->context->runAs(
            $membership->organization,
            fn (): bool => $membership->user->hasRole($this->ownerRole($membership)),
        );
    }

    private function activeOwnerCount(OrganizationMembership $membership): int
    {
        $owners = $this->context->runAs(
            $membership->organization,
            fn (): array => $this->ownerRole($membership)->users()->pluck('users.id')->all(),
        );

        return OrganizationMembership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('status', MembershipStatus::Active)
            ->whereIn('user_id', $owners)
            ->count();
    }

    private function ownerRole(OrganizationMembership $membership): Role
    {
        return Role::query()
            ->where('organization_id', $membership->organization_id)
            ->where('protected', true)
            ->firstOrFail();
    }
}
