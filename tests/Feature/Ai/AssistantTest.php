<?php

declare(strict_types=1);

use App\Actions\AssistantBlocks;
use App\Ai\Agents\Assistant;
use App\Ai\Tools\ListResourceRecords;
use App\Ai\Tools\ProposeAction;
use App\Ai\Tools\ShowResourceRecord;
use App\Models\AiAuditLog;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

/**
 * A member of an organization with the assistant switched on.
 *
 * @return array{0: User, 1: Organization}
 */
function assistantMember(): array
{
    config()->set('features.defaults.ai-assist-enabled', true);

    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    return [$user, $organization];
}

function assistantConversation(User $user, Organization $organization, string $title = 'Earlier question'): Conversation
{
    return Conversation::query()->create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'title' => $title,
    ]);
}

it('sends a message through the faked agent and saves the conversation', function (): void {
    [$user, $organization] = assistantMember();

    Assistant::fake(['Three members.'])->preventStrayPrompts();

    $response = $this->actingAs($user)
        ->postJson(route('assistant.store'), ['message' => 'How many members do we have?'])
        ->assertOk()
        ->assertJsonPath('text', 'Three members.')
        ->assertJsonPath('blocks', []);

    $conversation = Conversation::query()->sole();

    expect($response->json('conversation_id'))->toBe($conversation->id)
        ->and($conversation->organization_id)->toBe($organization->id)
        ->and($conversation->user_id)->toBe($user->id)
        ->and($conversation->title)->toBe('How many members do we have?')
        ->and(ConversationMessage::query()->where('conversation_id', $conversation->id)->pluck('role')->all())
        ->toEqualCanonicalizing(['user', 'assistant']);

    Assistant::assertPrompted('How many members do we have?');
});

it('continues a saved conversation instead of starting another', function (): void {
    [$user, $organization] = assistantMember();
    $conversation = assistantConversation($user, $organization);

    Assistant::fake(['Still three.'])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('assistant.store'), ['message' => 'And now?', 'conversation_id' => $conversation->id])
        ->assertOk()
        ->assertJsonPath('conversation_id', $conversation->id);

    expect(Conversation::query()->count())->toBe(1);
});

it("lists only the member's own conversations", function (): void {
    [$user, $organization] = assistantMember();
    $mine = assistantConversation($user, $organization, 'Mine');
    $colleague = User::factory()->forOrganization($organization)->create();
    assistantConversation($colleague, $organization, 'Theirs');

    $this->actingAs($user)->get(route('assistant.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('assistant/index')->loadDeferredProps(
            fn ($reload) => $reload->has('conversations', 1)->where('conversations.0.id', $mine->id)
        ));

});

it('shows a saved conversation with its messages', function (): void {
    [$user, $organization] = assistantMember();
    $conversation = assistantConversation($user, $organization);

    Assistant::fake(['Hi.'])->preventStrayPrompts();

    $this->actingAs($user)
        ->postJson(route('assistant.store'), ['message' => 'Hello', 'conversation_id' => $conversation->id])
        ->assertOk();

    $this->actingAs($user)->get(route('assistant.show', $conversation->id))->assertOk()
        ->assertInertia(fn ($page) => $page->component('assistant/show')
            ->where('conversation.id', $conversation->id)
            ->has('messages', 2)
            ->where('messages.0.text', 'Hello')
            ->where('messages.1.text', 'Hi.'));
});

it("answers 404 for another member's conversation", function (): void {
    [$user, $organization] = assistantMember();
    $colleague = User::factory()->forOrganization($organization)->create();
    $theirs = assistantConversation($colleague, $organization);

    Assistant::fake(['Never.'])->preventStrayPrompts();

    $this->actingAs($user)->get(route('assistant.show', $theirs->id))->assertNotFound();
    $this->actingAs($user)
        ->postJson(route('assistant.store'), ['message' => 'Hi', 'conversation_id' => $theirs->id])
        ->assertNotFound();
});

it('answers 404 for a conversation of another organization', function (): void {
    [$user, $organization] = assistantMember();
    $other = Organization::factory()->create();
    OrganizationMembership::factory()->create(['organization_id' => $other->id, 'user_id' => $user->id]);

    $foreign = assistantConversation($user, $other);

    $this->actingAs($user)->get(route('assistant.show', $foreign->id))->assertNotFound();
});

it('turns the whole area into a 404 when the feature is off', function (): void {
    [$user] = assistantMember();

    config()->set('features.defaults.ai-assist-enabled', false);

    $this->actingAs($user)->get(route('assistant.index'))->assertNotFound();
});

it('sends a guest to the login page', function (): void {
    $this->get(route('assistant.index'))->assertRedirect(route('login'));
    $this->post(route('assistant.store'), ['message' => 'Hi'])->assertRedirect(route('login'));
});

it('validates the message', function (): void {
    [$user] = assistantMember();

    $this->actingAs($user)->postJson(route('assistant.store'), ['message' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('message');
});

it('answers 429 with the friendly quota message when the member is over the limit', function (): void {
    [$user, $organization] = assistantMember();

    config()->set('ai.quotas.user_requests_per_hour', 1);

    AiAuditLog::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);

    Assistant::fake(['Never sent.'])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('assistant.store'), ['message' => 'Hi'])
        ->assertStatus(429)->assertJsonPath('message', 'This organization has used its AI allowance for now. It resets shortly.');
});

it('equips the agent with tools scoped to the member and the organization', function (): void {
    [$user, $organization] = assistantMember();

    $tools = array_map(fn (object $tool): string => $tool::class, new Assistant($user, $organization)->tools());

    expect($tools)->toContain(ListResourceRecords::class, ShowResourceRecord::class, ProposeAction::class);
});

it('refuses to build the agent for an organization the member does not belong to', function (): void {
    [$user] = assistantMember();

    new Assistant($user, Organization::factory()->create());
})->throws(AuthorizationException::class);

it('lifts only confirm blocks out of tool results', function (): void {
    [$user, $organization] = assistantMember();
    resolve(OrganizationContext::class)->set($organization);

    $token = AiConfirmToken::factory()->for($user)->for($organization)->create(['summary' => 'Invite a@example.com.']);

    $blocks = resolve(AssistantBlocks::class)->handle([
        json_encode(['type' => 'confirm', 'token' => $token->id]),
        json_encode(['type' => 'text', 'text' => 'Not lifted.']),
        'plain words',
    ]);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['summary'])->toBe('Invite a@example.com.');
});
