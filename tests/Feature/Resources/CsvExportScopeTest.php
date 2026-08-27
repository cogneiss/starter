<?php

declare(strict_types=1);

use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Fixtures\Resources\Filters\ExportedList;

beforeEach(function (): void {
    Route::get('/audit-logs.csv', fn (): StreamedResponse => ExportedList::for(request()->query()));
});

/**
 * The export is the highest-consequence read in the list kit: a screen that
 * loses its organization scope shows one page of another tenant's rows, while an
 * export that loses it writes the whole table to a file. So the assertion is on
 * the bytes that leave the server, not on a count the caller could have taken
 * from anywhere.
 */
it("writes only the acting organization's rows, however many the table holds", function (): void {
    $ours = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    resolve(OrganizationContext::class)->runAs($theirs, function () use ($theirs): void {
        AiAuditLog::factory()->create([
            'organization_id' => $theirs->id,
            'agent' => 'agent-from-another-tenant',
        ]);
    });

    resolve(OrganizationContext::class)->set($ours);

    AiAuditLog::factory()->create([
        'organization_id' => $ours->id,
        'agent' => 'agent-of-ours',
    ]);

    $csv = $this->get('/audit-logs.csv')->streamedContent();

    expect($csv)->toContain('agent-of-ours');

    $this->assertStringNotContainsString('agent-from-another-tenant', $csv);
});

it('streams rather than collecting, so the row count does not decide the memory', function (): void {
    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->set($organization);

    AiAuditLog::factory()->create(['organization_id' => $organization->id, 'agent' => 'streamed-agent']);

    $response = $this->get('/audit-logs.csv');

    // Nothing is written until the stream is read: the response carries a
    // callback, not a body.
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('streamed-agent');
});
