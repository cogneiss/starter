<?php

declare(strict_types=1);

use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Fixtures\Resources\Filters\ExportedList;

beforeEach(function (): void {
    Route::get('/audit-logs.csv', fn (): StreamedResponse => ExportedList::for(request()->query()));

    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->set($organization);

    $this->user = User::factory()->create();

    AiAuditLog::factory()->create([
        'organization_id' => $organization->id,
        'agent' => 'exported-agent',
        'total_tokens' => 987654,
    ]);
});

/**
 * A column behind an ability is withheld from the file for the same reason it is
 * withheld from the screen. Both the heading and the value have to go: a header
 * naming a column nobody may see still says the column exists, and a value under
 * a missing heading is worse — the field is there, just mislabelled.
 */
it('leaves a column the person may not see out of the header and out of every row', function (): void {
    Gate::define('audit-logs.view-tokens', fn (User $user): bool => false);

    $csv = $this->actingAs($this->user)->get('/audit-logs.csv')->streamedContent();

    expect($csv)->toContain('exported-agent');

    $this->assertStringNotContainsString('Tokens', $csv);
    $this->assertStringNotContainsString('987654', $csv);
});

it('writes a column the person may see', function (): void {
    Gate::define('audit-logs.view-tokens', fn (User $user): bool => true);

    $csv = $this->actingAs($this->user)->get('/audit-logs.csv')->streamedContent();

    expect($csv)->toContain('Tokens')->toContain('987654');
});

it('writes an enum as its stored value and a timestamp in a format that sorts', function (): void {
    Gate::define('audit-logs.view-tokens', fn (User $user): bool => false);

    $csv = $this->actingAs($this->user)->get('/audit-logs.csv')->streamedContent();

    expect($csv)->toContain('Agent,Status,Used,Person')
        // Not 'AiAuditStatus::Ok' and not a localised date: a spreadsheet reads
        // neither.
        ->toContain(',ok,')
        ->toMatch('/"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"/');
});
