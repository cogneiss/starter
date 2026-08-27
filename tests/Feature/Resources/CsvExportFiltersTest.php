<?php

declare(strict_types=1);

use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Resources\Filters\AuditLogFixture;
use Tests\Fixtures\Resources\Filters\ExportedList;

beforeEach(function (): void {
    Route::get('/audit-logs.csv', fn () => ExportedList::for(request()->query()));

    $this->organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->set($this->organization);
});

/**
 * An export is the list in front of the person, not the table behind it. Every
 * filter type is checked, because each one narrows the query differently and a
 * type that quietly did nothing here would hand over rows the screen had
 * already excluded.
 */
it('exports the rows the current filters leave, and no others', function (string $type): void {
    $records = AuditLogFixture::seed($this->organization);

    $csv = $this->get('/audit-logs.csv?'.http_build_query(['f' => AuditLogFixture::narrowing()[$type]]))
        ->streamedContent();

    expect($csv)->toContain($records['matching']->user->name);

    $this->assertStringNotContainsString($records['other']->user->name, $csv);
})->with(array_keys(AuditLogFixture::narrowing()));

it('exports the rows a search term leaves', function (): void {
    $searched = AiAuditLog::factory()->create([
        'organization_id' => $this->organization->id,
        'agent' => 'the-agent-searched-for',
    ]);

    $other = AuditLogFixture::other($this->organization);

    $csv = $this->get('/audit-logs.csv?'.http_build_query(['q' => 'the-agent-searched-for']))
        ->streamedContent();

    expect($csv)->toContain($searched->user->name);

    $this->assertStringNotContainsString($other->user->name, $csv);
});

it('writes an empty field where a record has no related row to read', function (): void {
    AiAuditLog::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => null,
    ]);

    $csv = $this->get('/audit-logs.csv')->streamedContent();

    // Four visible columns, the last of which is the person who is not there.
    expect(mb_trim(explode("\n", $csv)[1]))->toEndWith(',');
});
