<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('narrows the organization picker on the server and drops a choice the new search replaced', function (): void {
    $other = Organization::factory()->create(['name' => 'Alpine Bakery']);
    $wanted = Organization::factory()->create(['name' => 'Zebra Logistics']);

    $this->actingAs(User::factory()->superAdmin()->forOrganization($other)->create());

    $page = visit('/admin/ai-credits/create')
        ->assertPresent('#organization_id option[value="'.$other->id.'"]')
        ->assertPresent('#organization_id option[value="'.$wanted->id.'"]');

    $page->type('[data-test="organization-search"]', 'zebra')
        ->wait(1)
        ->assertQueryStringHas('search', 'zebra')
        ->assertMissing('#organization_id option[value="'.$other->id.'"]')
        ->select('#organization_id', $wanted->id)
        ->assertValue('#organization_id', $wanted->id);

    $page->type('[data-test="organization-search"]', 'zeb')
        ->assertValue('#organization_id', '')
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors();
});
