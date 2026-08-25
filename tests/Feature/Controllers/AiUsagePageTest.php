<?php

declare(strict_types=1);

use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Models\User;

it('renders the AI usage page for a member of the organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    AiAuditLog::factory()->count(2)->create([
        'organization_id' => $organization->id,
        'agent' => 'App\Ai\Agents\Drafter',
    ]);

    $this->actingAs($user)
        ->get(route('organization.ai-usage'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/ai-usage')
            ->where('usage.runs', 2)
            ->where('usage.tokens', 240)
            ->where('usage.cost_micros', 400)
            ->where('usage.agents.0.name', 'Drafter'));
});

it('never counts another organization on the AI usage page', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    AiAuditLog::factory()->create(['organization_id' => $organization->id]);
    AiAuditLog::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('organization.ai-usage'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('usage.runs', 1));
});

it('turns a guest away from the AI usage page', function (): void {
    $this->get(route('organization.ai-usage'))->assertRedirect(route('login'));
});
