<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AiMemory;
use App\Models\OrganizationMembership;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;

final readonly class RemoveOrganizationMembership
{
    public function __construct(
        private AssertNotLastActiveOwner $owners,
        private OrganizationContext $context,
    ) {}

    /**
     * Drop the membership, the roles that came with it, and the pointer to the
     * organization if the user was currently in it.
     */
    public function handle(OrganizationMembership $membership): void
    {
        $this->owners->handle($membership);

        DB::transaction(function () use ($membership): void {
            $user = $membership->user;

            $this->context->runAs(
                $membership->organization,
                fn () => $user->syncRoles([]),
            );

            if ($user->current_organization_id === $membership->organization_id) {
                $user->forceFill(['current_organization_id' => null])->save();
            }

            AiMemory::query()
                ->where('organization_id', $membership->organization_id)
                ->where('user_id', $membership->user_id)
                ->delete();

            $membership->delete();
        });
    }
}
