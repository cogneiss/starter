<?php

declare(strict_types=1);

it('renders every value component with a value', function (): void {
    $page = visit('/_value-gallery');

    // Money and percent.
    $page->assertSeeIn('[data-test="with-value"]', '$1,234.50')
        ->assertSeeIn('[data-test="with-value"]', '+12.5%')
        ->assertDataAttribute('[data-test="money"]', 'currency', 'USD');

    // Dates carry the machine-readable value, whatever the locale renders.
    $page->assertPresent('time[data-test="date-value"][datetime]')
        ->assertPresent('time[data-test="relative-time"][datetime]')
        ->assertSeeIn('[data-test="relative-time"]', '3 days ago');

    // Booleans and statuses.
    $page->assertPresent('[data-test="boolean-pill"][data-value="true"]')
        ->assertPresent('[data-test="boolean-pill"][data-value="false"]')
        ->assertDataAttribute('[data-test="status-badge"]', 'status', 'suspended')
        ->assertSeeIn('[data-test="status-badge"]', 'Suspended');

    // Links point where they say they do.
    $page->assertAttribute('[data-test="email-value"]', 'href', 'mailto:taylor@example.com')
        ->assertAttribute('[data-test="url-value"]', 'href', 'https://example.com/pricing')
        ->assertAttribute('[data-test="phone-value"]', 'href', 'tel:+15550109999');

    // Tags past the limit collapse into a chip listing the rest.
    $page->assertSeeIn('[data-test="tag-list-overflow"]', '+2')
        ->assertAttribute('[data-test="tag-list-overflow"]', 'title', 'gamma, delta');

    // Code and long text bring their own controls.
    $page->assertSeeIn('[data-test="code-value"]', 'org_01H9')
        ->assertAriaAttribute('[data-test="copy-code-button"]', 'label', 'Copy org_01H9')
        ->assertAriaAttribute('[data-test="long-text-toggle"]', 'expanded', 'false');

    $page->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('gives every empty value the same treatment', function (): void {
    $page = visit('/_value-gallery');

    // Every component in this section was handed null, so each one falls back
    // to the shared empty value and nothing renders a stray "null" or "NaN".
    $page->assertPresent('[data-test="without-value"] [data-test="empty-value"]')
        ->assertDontSeeIn('[data-test="without-value"]', 'null')
        ->assertDontSeeIn('[data-test="without-value"]', 'NaN')
        ->assertDontSeeIn('[data-test="without-value"]', 'Invalid Date');

    // Thirteen empty slots: twelve components handed null, plus the bare
    // component itself. The em-dash is decoration; the label is for readers.
    $page->assertCount('[data-test="without-value"] [data-test="empty-value"]', 13)
        ->assertSourceHas('<span class="sr-only">No value</span>')
        ->assertSourceHas('<span class="sr-only">No tags</span>');
});

it('expands long text on demand', function (): void {
    $page = visit('/_value-gallery');

    $page->click('[data-test="long-text-toggle"]')
        ->assertAriaAttribute('[data-test="long-text-toggle"]', 'expanded', 'true')
        ->assertSeeIn('[data-test="long-text-toggle"]', 'Show less');
});
