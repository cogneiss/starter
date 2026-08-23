<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\SeedOrganizationRoles;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'current_organization_id' => null,
            'is_active' => true,
            'is_super_admin' => false,
            'password' => 'password',
            'remember_token' => Str::random(10),
            'two_factor_secret' => Str::random(10),
            'two_factor_recovery_codes' => Str::random(10),
            'two_factor_confirmed_at' => now(),
        ];
    }

    /**
     * Create the user as an active member of the given organization, and make
     * it their current organization.
     */
    public function forOrganization(Organization $organization, string $role = 'Owner'): self
    {
        return $this->afterCreating(function (User $user) use ($organization, $role): void {
            OrganizationMembership::factory()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ]);

            $user->forceFill(['current_organization_id' => $organization->id])->save();

            $roles = resolve(SeedOrganizationRoles::class)->handle($organization);

            resolve(OrganizationContext::class)->runAs(
                $organization,
                fn (): User => $user->assignRole($roles[$role]),
            );
        });
    }

    public function superAdmin(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_super_admin' => true,
        ]);
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function withoutTwoFactor(): self
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
}
