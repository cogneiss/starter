<?php

declare(strict_types=1);

use App\Enums\KnownFeatures;
use App\Models\FeatureOverride;
use App\Models\Organization;
use App\Models\User;

it('shows the assistant empty state and chat without accessibility issues', function (): void {
    $organization = Organization::factory()->create();

    FeatureOverride::factory()->create([
        'organization_id' => $organization->id,
        'feature' => KnownFeatures::AiAssistEnabled->value,
        'value' => true,
    ]);

    $this->actingAs(User::factory()->forOrganization($organization)->create());

    visit('/assistant')
        ->assertPresent('[data-test="empty-state"]')
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors();

    visit('/assistant/new')
        ->assertPresent('[data-test="assistant-input"]')
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors();
});
