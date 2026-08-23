<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('lists the users of an organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    expect($organization->users()->pluck('users.id')->all())->toBe([$user->id]);
});
