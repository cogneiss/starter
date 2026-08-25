<?php

declare(strict_types=1);

use App\Mcp\Servers\StarterAiServer;
use App\Mcp\Tools\ListRecords;
use App\Mcp\Tools\ProposeChange;
use App\Mcp\Tools\ShowRecord;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;

/**
 * A member of a fresh organization, already their current one, as an MCP client
 * would find them after signing in.
 *
 * @return array{0: User, 1: Organization}
 */
function mcpMember(): array
{
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->forget();

    return [$member, $organization];
}

it('offers the phase 6 tool list and nothing that forks it', function (): void {
    $tools = new StarterAiServer(new FakeTransporter)->createContext()->tools();

    expect($tools->map(fn (Tool $tool): string => $tool::class)->all())
        ->toBe([ListRecords::class, ShowRecord::class, ProposeChange::class])
        ->and($tools->map(fn (Tool $tool): string => $tool->name())->all())
        ->toBe(['list-records', 'show-record', 'propose-change']);

    [$member, $organization] = mcpMember();

    foreach ($tools as $tool) {
        expect($tool)->toBeInstanceOf(Tool::class);

        /** @var ListRecords|ProposeChange|ShowRecord $tool */
        expect($tool->delegate($member, $organization)::class)
            ->toBe(StarterAiServer::DELEGATES[$tool::class]);
    }
});

it('describes each tool and its arguments so a client can call it', function (): void {
    $tools = new StarterAiServer(new FakeTransporter)->createContext()->tools();

    foreach ($tools as $tool) {
        expect($tool->description())->not->toBeEmpty();
    }

    /** @var ListRecords $list */
    $list = $tools[0];
    /** @var ShowRecord $show */
    $show = $tools[1];
    /** @var ProposeChange $propose */
    $propose = $tools[2];

    expect($list->schema(new JsonSchemaTypeFactory)['resource']->toArray()['enum'])
        ->toContain('organization-members')
        ->and(array_keys($show->schema(new JsonSchemaTypeFactory)))->toBe(['resource', 'id'])
        ->and($propose->schema(new JsonSchemaTypeFactory)['action']->toArray()['enum'])
        ->toContain('invite-member');
});

it('reads a record for the member the client is signed in as', function (): void {
    [$member, $organization] = mcpMember();

    $membership = OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $member->id)
        ->sole();

    StarterAiServer::actingAs($member)
        ->tool(ListRecords::class, ['resource' => 'organization-members'])
        ->assertOk()
        ->assertSee($membership->id);

    StarterAiServer::actingAs($member)
        ->tool(ShowRecord::class, ['resource' => 'organization-members', 'id' => $membership->id])
        ->assertOk()
        ->assertSee($member->email);
});

it('is refused when the member may not view the organization', function (): void {
    $organization = Organization::factory()->create();
    User::factory()->forOrganization($organization)->create();

    $stranger = User::factory()->create();
    $stranger->forceFill(['current_organization_id' => $organization->id])->save();

    resolve(OrganizationContext::class)->forget();

    StarterAiServer::actingAs($stranger)
        ->tool(ListRecords::class, ['resource' => 'organization-members'])
        ->assertHasErrors();
});

it('is refused when nobody is signed in', function (): void {
    StarterAiServer::tool(ListRecords::class, ['resource' => 'organization-members'])
        ->assertHasErrors();
});

it('is refused when the member has no current organization', function (): void {
    $member = User::factory()->create();

    StarterAiServer::actingAs($member)
        ->tool(ListRecords::class, ['resource' => 'organization-members'])
        ->assertHasErrors();
});

it('answers a write with a proposal the person still has to confirm', function (): void {
    [$member] = mcpMember();

    StarterAiServer::actingAs($member)
        ->tool(ProposeChange::class, [
            'action' => 'invite-member',
            'fields' => ['email' => 'new@example.com', 'role' => 'member'],
        ])
        ->assertOk()
        ->assertSee('confirm');

    $token = AiConfirmToken::withoutOrganizationScope()->sole();

    expect($token->action)->toBe('invite-member')
        ->and($token->consumed_at)->toBeNull();
});

it('writes nothing but the proposal when an mcp client proposes an invitation', function (): void {
    [$member] = mcpMember();

    $before = [
        User::query()->count(),
        OrganizationMembership::query()->count(),
        OrganizationInvitation::withoutOrganizationScope()->count(),
    ];

    StarterAiServer::actingAs($member)
        ->tool(ProposeChange::class, [
            'action' => 'invite-member',
            'fields' => ['email' => 'new@example.com', 'role' => 'member'],
        ])
        ->assertOk();

    expect([
        User::query()->count(),
        OrganizationMembership::query()->count(),
        OrganizationInvitation::withoutOrganizationScope()->count(),
    ])->toBe($before)
        ->and(AiConfirmToken::withoutOrganizationScope()->count())->toBe(1);
});
