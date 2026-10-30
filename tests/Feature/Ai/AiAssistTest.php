<?php

declare(strict_types=1);

use App\Ai\Agents\FieldDrafter;
use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * A platform admin who belongs to an organization with the feature on.
 *
 * @return array{0: User, 1: Organization}
 */
function assistAdmin(bool $enabled = true): array
{
    config()->set('features.defaults.ai-assist-enabled', $enabled);

    $organization = Organization::factory()->create();
    $admin = User::factory()->superAdmin()->forOrganization($organization)->create();

    return [$admin, $organization];
}

/**
 * @return array{field: string, text: string}
 */
function assistPayload(string $field = 'announcement.body'): array
{
    return ['field' => $field, 'text' => 'maintenance tonight'];
}

it('AiAssist drafts the field text through the agent', function (): void {
    [$admin] = assistAdmin();

    FieldDrafter::fake(['  Maintenance tonight at 22:00 UTC.  '])->preventStrayPrompts();

    $this->actingAs($admin)
        ->postJson(route('ai-draft.store'), [...assistPayload(), 'instruction' => 'be brief'])
        ->assertOk()
        ->assertExactJson(['text' => 'Maintenance tonight at 22:00 UTC.']);

    FieldDrafter::assertPrompted(fn (object $prompt): bool => str_contains((string) $prompt->prompt, 'maintenance tonight')
        && str_contains((string) $prompt->prompt, 'be brief'));
});

it('AiAssist records the usage in the audit log', function (): void {
    [$admin, $organization] = assistAdmin();

    FieldDrafter::fake(['A draft.'])->preventStrayPrompts();

    $this->actingAs($admin)->postJson(route('ai-draft.store'), assistPayload())->assertOk();

    resolve(OrganizationContext::class)->set($organization);

    $log = AiAuditLog::query()->sole();

    expect($log->agent)->toBe(FieldDrafter::class)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->status->value)->toBe('ok');
});

it('AiAssist is blocked and audited once the quota is used up', function (): void {
    [$admin, $organization] = assistAdmin();

    config()->set('ai.quotas.user_requests_per_hour', 1);

    AiAuditLog::factory()->create(['organization_id' => $organization->id, 'user_id' => $admin->id]);

    FieldDrafter::fake(['Never sent.'])->preventStrayPrompts();

    $this->actingAs($admin)
        ->postJson(route('ai-draft.store'), assistPayload())
        ->assertStatus(429);

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->where('status', 'blocked')->count())->toBe(1);
});

it('AiAssist is a 404 for a member without the form ability', function (): void {
    config()->set('features.defaults.ai-assist-enabled', true);

    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create();

    FieldDrafter::fake(['Never sent.'])->preventStrayPrompts();

    $this->actingAs($member)->postJson(route('ai-draft.store'), assistPayload())->assertNotFound();

    FieldDrafter::assertNeverPrompted();
});

it('AiAssist never drafts into another organization', function (): void {
    [$admin] = assistAdmin();
    $foreign = Organization::factory()->create();

    FieldDrafter::fake(['A draft.'])->preventStrayPrompts();

    $this->actingAs($admin)
        ->withSession(['current_organization_id' => $foreign->id])
        ->postJson(route('ai-draft.store'), assistPayload());

    resolve(OrganizationContext::class)->set($foreign);

    expect(AiAuditLog::query()->count())->toBe(0);
});

it('AiAssist refuses an unknown field with a validation error', function (): void {
    [$admin] = assistAdmin();

    $this->actingAs($admin)
        ->postJson(route('ai-draft.store'), assistPayload('users.password_hash'))
        ->assertJsonValidationErrors('field');
});

it('AiAssist turns an unauthenticated caller away', function (): void {
    $this->postJson(route('ai-draft.store'), assistPayload())->assertUnauthorized();
});

it('AiAssist refuses and hides itself when the feature is off', function (): void {
    [$admin] = assistAdmin(enabled: false);

    FieldDrafter::fake(['Never sent.'])->preventStrayPrompts();

    $this->actingAs($admin)->postJson(route('ai-draft.store'), assistPayload())->assertNotFound();

    $this->actingAs($admin)->get(route('admin.announcements.create'))
        ->assertInertia(fn ($page) => $page->where('aiAssist', false));

    FieldDrafter::assertNeverPrompted();
});

it('AiAssist is shared with the page when the feature is on', function (): void {
    [$admin] = assistAdmin();

    $this->actingAs($admin)->get(route('admin.announcements.create'))
        ->assertInertia(fn ($page) => $page->where('aiAssist', true));
});
