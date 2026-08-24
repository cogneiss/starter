<?php

declare(strict_types=1);

use App\Actions\ConsumeConfirmToken;
use App\Actions\CreateConfirmToken;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Two processes racing for the same token is not something a single-process
 * test suite can stage, so the guarantee is asserted from the queries the
 * consume issues: the token is read `for update`, inside a transaction, and
 * `consumed_at` is written in that same transaction. A reader holding the row
 * lock is what makes the `consumed_at` check meaningful under a race.
 */
it('locks the token row for update inside the consuming transaction', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $token = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): AiConfirmToken => resolve(CreateConfirmToken::class)->handle(
            $owner,
            'invite-member',
            ['email' => 'locked@example.com', 'role' => 'Member'],
        ),
    );

    $outside = DB::transactionLevel();

    /** @var list<array{sql: string, level: int}> $queries */
    $queries = [];

    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = ['sql' => mb_strtolower($query->sql), 'level' => DB::transactionLevel()];
    });

    resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): mixed => resolve(ConsumeConfirmToken::class)->handle($token->id, $owner),
    );

    $select = collect($queries)->first(
        fn (array $query): bool => str_starts_with($query['sql'], 'select')
            && str_contains($query['sql'], 'ai_confirm_tokens'),
    );

    $update = collect($queries)->first(
        fn (array $query): bool => str_starts_with($query['sql'], 'update')
            && str_contains($query['sql'], 'ai_confirm_tokens')
            && str_contains($query['sql'], 'consumed_at'),
    );

    expect($select)->not->toBeNull()
        ->and($update)->not->toBeNull()
        ->and($select['sql'])->toContain('for update')
        ->and($select['level'])->toBeGreaterThan($outside)
        ->and($update['level'])->toBe($select['level']);
})->group('pgvector');
