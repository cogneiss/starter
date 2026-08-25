<?php

declare(strict_types=1);

use App\Ai\Tools\ListResourceRecords;
use App\Ai\Tools\ProposeAction;
use App\Ai\Tools\ShowResourceRecord;
use App\Exceptions\UnknownResource;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;

/**
 * An owner of a fresh organization, plus the membership row that owner holds.
 *
 * @return array{0: User, 1: Organization, 2: OrganizationMembership}
 */
function toolOwner(): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->set($organization);

    $membership = OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $owner->id)
        ->sole();

    return [$owner, $organization, $membership];
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function toolResult(object $tool, array $arguments): array
{
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($tool->handle(new Request($arguments)), true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

it('lists only the records of the acting member, never those of two organizations at once', function (): void {
    $other = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($other)->create();

    $hidden = OrganizationMembership::query()
        ->where('organization_id', $other->id)
        ->where('user_id', $stranger->id)
        ->sole();

    [$owner, $organization, $membership] = toolOwner();

    $result = resolve(OrganizationContext::class)->runAs($organization, fn (): array => toolResult(
        new ListResourceRecords($owner, $organization, resolve(ResourceRegistry::class)),
        ['resource' => 'organization-members'],
    ));

    expect($result['records'])->toHaveCount(1)
        ->and($result['records'][0]['id'])->toBe($membership->id)
        ->and(json_encode($result))->not->toContain($hidden->id);
});

it('refuses an unregistered resource key rather than reaching a model', function (): void {
    [$owner, $organization] = toolOwner();

    $tool = new ListResourceRecords($owner, $organization, resolve(ResourceRegistry::class));

    expect(fn (): array => toolResult($tool, ['resource' => 'invoices']))
        ->toThrow(UnknownResource::class);
});

it("returns the resource's own Data object rather than an ad hoc array", function (): void {
    [$owner, $organization, $membership] = toolOwner();

    $result = resolve(OrganizationContext::class)->runAs($organization, fn (): array => toolResult(
        new ListResourceRecords($owner, $organization, resolve(ResourceRegistry::class)),
        ['resource' => 'organization-members'],
    ));

    expect(array_keys($result['records'][0]))
        ->toBe(['id', 'name', 'email', 'status', 'role'])
        ->and($result['records'][0]['email'])->toBe($owner->email)
        ->and($result['records'][0]['id'])->toBe($membership->id);
});

it('reads one record by id and refuses one belonging to another organization', function (): void {
    $other = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($other)->create();

    $hidden = OrganizationMembership::query()
        ->where('organization_id', $other->id)
        ->where('user_id', $stranger->id)
        ->sole();

    [$owner, $organization, $membership] = toolOwner();

    $tool = new ShowResourceRecord($owner, $organization, resolve(ResourceRegistry::class));

    resolve(OrganizationContext::class)->runAs($organization, function () use ($tool, $membership, $hidden): void {
        $result = toolResult($tool, ['resource' => 'organization-members', 'id' => $membership->id]);

        expect($result['record']['id'])->toBe($membership->id);

        expect(fn (): array => toolResult($tool, ['resource' => 'organization-members', 'id' => $hidden->id]))
            ->toThrow(AuthorizationException::class);

        expect(toolResult($tool, ['resource' => 'organization-members', 'id' => 'not-a-record'])['record'])
            ->toBeNull();
    });
});

it('proves ProposeAction mutates nothing but the confirm token it hands back', function (): void {
    [$owner, $organization] = toolOwner();

    $before = [
        User::query()->count(),
        OrganizationMembership::query()->count(),
        OrganizationInvitation::withoutOrganizationScope()->count(),
    ];

    $result = resolve(OrganizationContext::class)->runAs($organization, fn (): array => toolResult(
        resolve(ProposeAction::class, ['user' => $owner, 'organization' => $organization]),
        ['action' => 'invite-member', 'fields' => ['email' => 'new@example.com', 'role' => 'member']],
    ));

    expect([
        User::query()->count(),
        OrganizationMembership::query()->count(),
        OrganizationInvitation::withoutOrganizationScope()->count(),
    ])->toBe($before);

    $token = AiConfirmToken::withoutOrganizationScope()->findOrFail($result['token']);

    expect($result['type'])->toBe('confirm')
        ->and($token->consumed_at)->toBeNull()
        ->and($token->action)->toBe('invite-member')
        ->and($token->organization_id)->toBe($organization->id);
});

it('refuses an invalid payload before any confirm token exists', function (): void {
    [$owner, $organization] = toolOwner();

    $before = AiConfirmToken::withoutOrganizationScope()->count();

    expect(fn (): array => resolve(OrganizationContext::class)->runAs($organization, fn (): array => toolResult(
        resolve(ProposeAction::class, ['user' => $owner, 'organization' => $organization]),
        ['action' => 'invite-member', 'fields' => ['email' => 'not-an-email', 'role' => 'member']],
    )))->toThrow(ValidationException::class);

    expect(AiConfirmToken::withoutOrganizationScope()->count())->toBe($before);
});

it('keeps every tool but the named ProposeAction free of writes — arch', function (): void {
    $files = glob(app_path('Ai/Tools').'/*.php') ?: [];

    $writes = ['->save(', '->update(', '->delete(', '->create(', 'DB::', '::create('];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        // ProposeAction is the one exemption, and it writes a proposal only —
        // never the record the proposal is about.
        $allowed = basename($file) === 'ProposeAction.php';

        foreach ($writes as $write) {
            expect(str_contains($source, $write) && ! $allowed)
                ->toBeFalse(basename($file)." must not write: it contains [{$write}]");
        }
    }

    expect($files)->not->toBeEmpty();
})->group('arch');

it('describes itself and its arguments so a model can call it', function (): void {
    [$owner, $organization] = toolOwner();

    $registry = resolve(ResourceRegistry::class);

    $tools = [
        new ListResourceRecords($owner, $organization, $registry),
        new ShowResourceRecord($owner, $organization, $registry),
        resolve(ProposeAction::class, ['user' => $owner, 'organization' => $organization]),
    ];

    foreach ($tools as $tool) {
        $schema = $tool->schema(new JsonSchemaTypeFactory);

        expect($tool->description())->toBeString()->not->toBeEmpty()
            ->and($schema)->not->toBeEmpty();
    }

    $arguments = $tools[0]->schema(new JsonSchemaTypeFactory);

    // The resource argument is an enum of registered keys, so an unregistered
    // key is not something a well-behaved model can even name.
    expect($arguments['resource']->toArray()['enum'])->toContain('organization-members')
        ->and($tools[2]->schema(new JsonSchemaTypeFactory)['action']->toArray()['enum'])->toContain('invite-member');
});
