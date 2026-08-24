<?php

declare(strict_types=1);

use App\Ai\Agents\BlockComposer;
use App\Models\Organization;
use App\Models\User;

/**
 * The block gallery in a real browser. The static section proves the renderer
 * handles every member of the union, and the streamed section proves the same
 * components render blocks that arrive a line at a time.
 */
function signInForBlocks(): void
{
    test()->actingAs(User::factory()->forOrganization(Organization::factory()->create())->create());
}

it('renders every block the union knows', function (): void {
    signInForBlocks();

    $page = visit('/_block-gallery');

    $page->assertSeeIn('[data-test="static-blocks"]', 'A sentence the agent wrote.')
        ->assertSeeIn('[data-test="static-blocks"]', 'Heading')
        ->assertSeeIn('[data-test="static-blocks"]', 'Taylor')
        ->assertSeeIn('[data-test="static-blocks"]', 'Second')
        ->assertSeeIn('[data-test="static-blocks"]', 'Active members')
        ->assertSeeIn('[data-test="static-blocks"]', 'Invite Member')
        ->assertNoJavaScriptErrors();
});

it('renders nothing for a block of an unknown type', function (): void {
    signInForBlocks();

    $page = visit('/_block-gallery');

    // The gallery hands the page a member no build knows about. The renderer's
    // default arm is what keeps it off the screen.
    $page->assertDontSee('A block from the future.')
        ->assertDontSee('quantum-hologram')
        ->assertCount('[data-test="static-blocks"] [data-test$="-block"]', 6)
        ->assertNoJavaScriptErrors();
});

it('renders a markdown block without the script it carried', function (): void {
    signInForBlocks();

    $page = visit('/_block-gallery');

    // The sanitizer keeps the text and drops the element, so the page shows
    // `alert(1)` as words and never as a script the browser would run.
    $page->assertSourceMissing('<script>alert(1)</script>')
        ->assertPresent('[data-test="ai-markdown-block"]')
        ->assertCount('[data-test="ai-markdown-block"] script', 0)
        ->assertNoJavaScriptErrors();
});

it('renders every block of a streamed multi-block response', function (): void {
    signInForBlocks();

    BlockComposer::fake([implode("\n", [
        '{"type":"text","text":"Streamed sentence."}',
        '{"type":"metric","label":"Streamed members","value":"7"}',
        '{"type":"list","ordered":true,"items":["Streamed item"]}',
    ])])->preventStrayPrompts();

    $page = visit('/_block-gallery');

    $page->click('[data-test="ai-stream-submit"]')
        ->assertSeeIn('[data-test="streamed-blocks"]', 'Streamed sentence.')
        ->assertSeeIn('[data-test="streamed-blocks"]', 'Streamed members')
        ->assertSeeIn('[data-test="streamed-blocks"]', 'Streamed item')
        ->assertCount('[data-test="streamed-blocks"] [data-test$="-block"]', 3)
        ->assertNoJavaScriptErrors();
});
