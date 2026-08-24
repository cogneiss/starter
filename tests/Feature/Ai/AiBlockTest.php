<?php

declare(strict_types=1);

use App\Actions\StreamBlocks;
use App\Ai\Agents\BlockComposer;
use App\Ai\Blocks\AiBlock;
use App\Ai\Blocks\BlockCollection;
use App\Ai\Blocks\ConfirmBlock;
use App\Ai\Blocks\FormBlock;
use App\Ai\Blocks\ListBlock;
use App\Ai\Blocks\MarkdownBlock;
use App\Ai\Blocks\MetricBlock;
use App\Ai\Blocks\TableBlock;
use App\Ai\Blocks\TextBlock;
use App\Enums\AiBlockType;
use App\Enums\AiMetricTrend;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * @return array{0: User, 1: Organization}
 */
function blockMember(): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->set($organization);

    return [$owner, $organization];
}

it('builds every block the union knows', function (): void {
    blockMember();

    $blocks = BlockCollection::fromPayloads([
        ['type' => 'text', 'text' => 'A sentence.'],
        ['type' => 'markdown', 'markdown' => '# Title'],
        ['type' => 'table', 'columns' => ['A', 'B'], 'rows' => [['1', '2']]],
        ['type' => 'list', 'ordered' => true, 'items' => ['One']],
        ['type' => 'metric', 'label' => 'Members', 'value' => '12', 'delta' => '+2', 'trend' => 'up'],
        ['type' => 'form', 'action' => 'invite-member', 'values' => ['email' => 'new@example.com']],
    ]);

    expect($blocks->blocks)->toHaveCount(6)
        ->and(array_map(fn (AiBlock $block): AiBlockType => $block->type, $blocks->blocks))
        ->toBe([
            AiBlockType::Text,
            AiBlockType::Markdown,
            AiBlockType::Table,
            AiBlockType::ListItems,
            AiBlockType::Metric,
            AiBlockType::Form,
        ]);
});

it('refuses a block of an unknown type', function (mixed $payload): void {
    expect(BlockCollection::block($payload))->toBeNull();
})->with([
    'a type nothing renders' => [['type' => 'quantum-hologram', 'text' => 'Hello.']],
    'no type at all' => [['text' => 'Hello.']],
    'a type that is not a string' => [['type' => ['text'], 'text' => 'Hello.']],
    'a known type with the wrong shape' => [['type' => 'table', 'columns' => 'A']],
]);

it('strips a script from a markdown block', function (): void {
    $block = new MarkdownBlock('Hello <script>alert(1)</script> world.');

    expect($block->html)->not->toContain('<script>')
        ->and($block->html)->not->toContain('</script>')
        ->and($block->html)->toContain('Hello');
});

it('will not let a payload choose its own html for a markdown block', function (): void {
    expect(BlockCollection::block([
        'type' => 'markdown',
        'markdown' => 'Safe.',
        'html' => '<script>alert(1)</script>',
    ]))->toBeNull();

    expect(BlockCollection::block(['type' => 'markdown', 'markdown' => 'Safe.']))
        ->toBeInstanceOf(MarkdownBlock::class);
});

it('will not let a payload contradict the type of its own block', function (): void {
    $block = BlockCollection::block(['type' => 'text', 'text' => 'A sentence.']);

    expect($block)->toBeInstanceOf(TextBlock::class)
        ->and($block->type)->toBe(AiBlockType::Text);
});

it('refuses a table row that does not fit its columns', function (): void {
    expect(fn (): TableBlock => new TableBlock(['A', 'B'], [['1']]))
        ->toThrow(InvalidArgumentException::class, 'Every row must have one cell per column.');
});

it('renders a list either way round', function (): void {
    expect((new ListBlock(['One']))->ordered)->toBeFalse()
        ->and((new ListBlock(['One'], ordered: true))->ordered)->toBeTrue();
});

it('carries a metric without a delta or a trend', function (): void {
    $block = new MetricBlock('Members', '12');

    expect($block->delta)->toBeNull()
        ->and($block->trend)->toBeNull()
        ->and((new MetricBlock('Members', '12', '-2', AiMetricTrend::Down))->trend)
        ->toBe(AiMetricTrend::Down);
});

it('reads a form block\'s fields off the action rather than the payload', function (): void {
    blockMember();

    $block = new FormBlock('invite-member', ['email' => 'new@example.com', 'sneaky' => 'yes']);

    expect(array_map(fn ($field): string => $field->name, $block->fields))
        ->toBe(['email', 'role'])
        ->and($block->fields[0]->value)->toBe('new@example.com')
        ->and($block->fields[1]->value)->toBe('')
        ->and($block->summary)->toBe('Invite Member');
});

it('refuses a form block for an action that is not on the allowlist', function (): void {
    expect(fn (): FormBlock => new FormBlock('delete-everything'))
        ->toThrow(InvalidArgumentException::class);

    expect(BlockCollection::block(['type' => 'form', 'action' => 'delete-everything']))->toBeNull();
});

it('reads a confirm block\'s summary from the token rather than the payload', function (): void {
    [$user, $organization] = blockMember();

    $token = AiConfirmToken::factory()->for($user)->for($organization)->create([
        'summary' => 'Invite new@example.com as member.',
    ]);

    expect(BlockCollection::block([
        'type' => 'confirm',
        'token' => $token->id,
        'summary' => 'Something else entirely.',
    ]))->toBeNull();

    $block = BlockCollection::block(['type' => 'confirm', 'token' => $token->id]);

    expect($block)->toBeInstanceOf(ConfirmBlock::class)
        ->and($block->summary)->toBe('Invite new@example.com as member.')
        ->and($block->expires_at)->toBe($token->expires_at->toIso8601String());
});

it('refuses a confirm block naming another organization\'s token', function (): void {
    $other = Organization::factory()->create();

    $token = AiConfirmToken::factory()
        ->for(User::factory()->forOrganization($other))
        ->for($other)
        ->create();

    blockMember();

    expect(BlockCollection::block(['type' => 'confirm', 'token' => $token->id]))->toBeNull();
});

it('turns an agent stream into one block per line', function (): void {
    [$user, $organization] = blockMember();

    BlockComposer::fake([implode("\n", [
        '{"type":"text","text":"First."}',
        'not json at all',
        '',
        '{"type":"quantum-hologram"}',
        '{"type":"metric","label":"Members","value":"12"}',
    ])])->preventStrayPrompts();

    $blocks = iterator_to_array(
        resolve(StreamBlocks::class)->handle(new BlockComposer($user, $organization), 'Summarise.')
    );

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0])->toBeInstanceOf(TextBlock::class)
        ->and($blocks[1])->toBeInstanceOf(MetricBlock::class);
});

it('streams the blocks over http as newline delimited json', function (): void {
    [$user] = blockMember();

    BlockComposer::fake(["{\"type\":\"text\",\"text\":\"First.\"}\n{\"type\":\"list\",\"items\":[\"One\"]}"])
        ->preventStrayPrompts();

    $response = $this->actingAs($user)->post(route('ai-block.store'), ['prompt' => 'Summarise.']);

    $response->assertOk()->assertHeader('Content-Type', 'application/x-ndjson');

    $lines = array_values(array_filter(explode("\n", $response->streamedContent())));

    expect($lines)->toHaveCount(2)
        ->and(json_decode($lines[0], true))->toMatchArray(['type' => 'text', 'text' => 'First.'])
        ->and(json_decode($lines[1], true)['type'])->toBe('list');
});

it('refuses to stream without a prompt', function (): void {
    [$user] = blockMember();

    BlockComposer::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->post(route('ai-block.store'), [])
        ->assertSessionHasErrors('prompt');
});

it('answers a submitted form block with a confirm block', function (): void {
    [$user] = blockMember();

    $response = $this->actingAs($user)->postJson(route('ai-proposal.store'), [
        'action' => 'invite-member',
        'fields' => ['email' => 'new@example.com', 'role' => 'member'],
    ]);

    $response->assertOk()->assertJson([
        'type' => 'confirm',
        'summary' => 'Invite new@example.com as member.',
    ]);

    expect(AiConfirmToken::query()->sole()->action)->toBe('invite-member');
});

it('refuses to propose an action that is not on the allowlist', function (): void {
    [$user] = blockMember();

    $this->actingAs($user)
        ->postJson(route('ai-proposal.store'), ['action' => 'delete-everything'])
        ->assertJsonValidationErrors('action');

    expect(AiConfirmToken::query()->count())->toBe(0);
});

it('hands the gallery a built block for every payload it names', function (): void {
    [$user] = blockMember();

    $this->actingAs($user)
        ->get(route('block-gallery'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('block-gallery')
            ->has('blocks', 7)
            ->where('blocks.0.type', 'text')
            // The last one is the payload the collection refused to build, so
            // it reaches the page as the raw array and the renderer drops it.
            ->where('blocks.6.type', 'quantum-hologram')
        );
});

it('tells the model about every block type it may emit', function (): void {
    [$user, $organization] = blockMember();

    $instructions = (new BlockComposer($user, $organization))->instructions();

    foreach (AiBlockType::cases() as $type) {
        expect($instructions)->toContain($type->value);
    }
});
