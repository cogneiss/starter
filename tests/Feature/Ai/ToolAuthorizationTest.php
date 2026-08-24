<?php

declare(strict_types=1);

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Ai\Tools\ListResourceRecords;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Ai\Tools\Request;
use Tests\Fixtures\Ai\FixtureTool;
use Tests\Fixtures\Ai\UnauthorizedFixtureTool;

/**
 * A tool authorizes when it carries the concern and its own `handle()` asks it.
 * Inheriting the method and never calling it is the failure mode worth catching.
 *
 * @param  class-string  $class
 */
function toolAsksAPolicy(string $class): bool
{
    if (! in_array(AuthorizesToolCall::class, class_uses_recursive($class), true)) {
        return false;
    }

    $method = new ReflectionMethod($class, 'handle');

    $lines = file((string) $method->getFileName()) ?: [];

    $body = implode('', array_slice(
        $lines,
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    return str_contains($body, 'authorizeFor(');
}

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

it('holds every tool in the tools directory to asking a policy — arch', function (): void {
    $files = glob(app_path('Ai/Tools').'/*.php') ?: [];

    foreach ($files as $file) {
        /** @var class-string $class */
        $class = 'App\\Ai\\Tools\\'.basename($file, '.php');

        expect(toolAsksAPolicy($class))->toBeTrue("{$class}::handle() must call authorizeFor()");
    }

    // The guard is only worth running if it rejects something: a tool that
    // reads a record without asking a policy fails it.
    expect(toolAsksAPolicy(UnauthorizedFixtureTool::class))->toBeFalse()
        ->and(toolAsksAPolicy(FixtureTool::class))->toBeTrue();
})->group('arch');

it('refuses a resource listing for a member without the ability', function (): void {
    $membership = OrganizationMembership::factory()->create();

    $tool = new ListResourceRecords(
        $membership->user,
        $membership->organization,
        resolve(ResourceRegistry::class),
    );

    expect(fn (): string => resolve(OrganizationContext::class)->runAs(
        $membership->organization,
        fn (): string => $tool->handle(new Request(['resource' => 'organization-members'])),
    ))->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('ai_audit_logs', [
        'user_id' => $membership->user->id,
        'agent' => ListResourceRecords::class,
        'status' => 'blocked',
    ]);
});
