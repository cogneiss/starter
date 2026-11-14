<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

it('answers 429 once a member sends a 31st AI request inside a minute', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->set($organization);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->actingAs($member)->postJson(route('ai-block.store'))->assertUnprocessable();
    }

    $this->actingAs($member)->postJson(route('ai-block.store'))->assertTooManyRequests();
});
