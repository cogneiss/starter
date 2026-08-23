<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AcceptOrganizationInvitation
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * Attach the user to the organization the invitation names. Accepting twice
     * is not an error: the existing membership is returned untouched.
     */
    public function handle(OrganizationInvitation $invitation, User $user): OrganizationMembership
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => __('That invitation has expired or has already been used.'),
            ]);
        }

        if (! hash_equals(Str::lower($invitation->email), Str::lower($user->email))) {
            throw ValidationException::withMessages([
                'token' => __('That invitation was sent to a different email address.'),
            ]);
        }

        return DB::transaction(function () use ($invitation, $user): OrganizationMembership {
            $organization = $invitation->organization;

            $membership = OrganizationMembership::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $user->id],
                ['status' => MembershipStatus::Active, 'joined_at' => now()],
            );

            $this->context->runAs($organization, function () use ($invitation, $user, $organization): void {
                $role = Role::query()
                    ->where('organization_id', $organization->id)
                    ->where('name', $invitation->role)
                    ->first();

                if ($role instanceof Role) {
                    $user->assignRole($role);
                }
            });

            $invitation->forceFill(['accepted_at' => now()])->save();

            if ($user->current_organization_id === null) {
                $user->forceFill(['current_organization_id' => $organization->id])->save();
            }

            return $membership;
        });
    }
}
