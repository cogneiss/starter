<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Events\OrganizationCreated;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateOrganization
{
    /**
     * Create an organization owned by the given user, along with their
     * membership of it, and make it their current organization.
     */
    public function handle(User $user, string $name, bool $personal = false): Organization
    {
        return DB::transaction(function () use ($user, $name, $personal): Organization {
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'personal' => $personal,
            ]);

            $organization->memberships()->create([
                'user_id' => $user->id,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            $user->forceFill(['current_organization_id' => $organization->id])->save();

            event(new OrganizationCreated($organization, $user));

            return $organization;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'organization';
        }

        $slug = $base;

        while (Organization::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
