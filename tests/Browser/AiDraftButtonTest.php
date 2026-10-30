<?php

declare(strict_types=1);

use App\Enums\KnownFeatures;
use App\Models\FeatureOverride;
use App\Models\Organization;
use App\Models\User;

it('AiAssist draft button is is accessible when the feature is on', function (): void {
    $organization = Organization::factory()->create();

    FeatureOverride::factory()->create([
        'organization_id' => $organization->id,
        'feature' => KnownFeatures::AiAssistEnabled->value,
        'value' => true,
    ]);

    $admin = User::factory()->superAdmin()->forOrganization($organization)->create();

    $this->actingAs($admin);

    visit('/admin/announcements/create')
        ->assertPresent('[data-test="ai-draft-button"]')
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors();
});
