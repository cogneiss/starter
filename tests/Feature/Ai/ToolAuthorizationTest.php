<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Ai\Tools\Request;
use Tests\Fixtures\Ai\FixtureTool;

it('throws and records a blocked audit row for a member without the ability', function (): void {
    $membership = OrganizationMembership::factory()->create();

    expect(fn (): string => resolve(OrganizationContext::class)->runAs(
        $membership->organization,
        fn (): string => (new FixtureTool($membership->user, $membership->organization))->handle(new Request),
    ))->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('ai_audit_logs', [
        'organization_id' => $membership->organization->id,
        'user_id' => $membership->user->id,
        'agent' => FixtureTool::class,
        'status' => 'blocked',
    ]);
});

it('runs the tool for a member who holds the ability, recording nothing', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $result = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): string => (new FixtureTool($owner, $organization))->handle(new Request),
    );

    expect($result)->toBe($organization->name);

    $this->assertDatabaseCount('ai_audit_logs', 0);
});
