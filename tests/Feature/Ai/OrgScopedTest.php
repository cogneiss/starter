<?php

declare(strict_types=1);

use App\Ai\Memory\AssistantMemory;
use App\Models\AiAuditLog;
use App\Models\AiConfirmToken;
use App\Models\AiCreditLedgerEntry;
use App\Models\AiDocument;
use App\Models\AiMemory;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * Every AI table that carries an organization, in one place: whatever else a
 * query says, a member of one organization reads their own rows and nothing
 * else. Each model is checked the same way so a table added later without the
 * scope shows up here as a missing entry rather than as a leak in production.
 *
 * @return list<class-string<Model>>
 */
function orgScopedAiModels(): array
{
    return [AiAuditLog::class, AiConfirmToken::class, AiCreditLedgerEntry::class, AiDocument::class];
}

it('reads only the acting organization rows, for every OrgScoped AI model', function (string $model): void {
    $theirs = Organization::factory()->create();
    $hidden = $model::factory()->create(['organization_id' => $theirs->id]);

    $mine = Organization::factory()->create();
    $visible = $model::factory()->create(['organization_id' => $mine->id]);

    resolve(OrganizationContext::class)->set($mine);

    expect($model::query()->pluck('id')->all())->toBe([$visible->id])
        ->and($model::query()->find($hidden->id))->toBeNull()
        ->and($model::query()->whereKey($hidden->id)->exists())->toBeFalse();
})->with(orgScopedAiModels());

it('counts every OrgScoped AI model, so a new table is not silently left out', function (): void {
    $scoped = collect(File::files(app_path('Models')))
        ->filter(fn ($file): bool => str_starts_with($file->getFilename(), 'Ai'))
        ->filter(fn ($file): bool => str_contains($file->getContents(), 'use BelongsToOrganization;'))
        ->map(fn ($file): string => 'App\\Models\\'.$file->getFilenameWithoutExtension())
        ->values()
        ->all();

    expect($scoped)->toBe(orgScopedAiModels());
});

it('keeps OrgScoped assistant memory to one person in one organization', function (): void {
    $organization = Organization::factory()->create();
    $mine = User::factory()->forOrganization($organization)->create();
    $theirs = User::factory()->forOrganization($organization)->create();

    AiMemory::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $theirs->id,
        'key' => 'tone',
        'value' => 'writes in bullet points',
    ]);

    expect(new AssistantMemory($mine, $organization)->instructions())
        ->not->toContain('writes in bullet points');
});
