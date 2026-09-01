<?php

declare(strict_types=1);

use App\Models\ApiRequestLog;
use App\Models\Organization;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * The usage log: one row per authenticated API request, carrying routing facts
 * only, pruned on a configurable retention.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();

    [$this->token, $this->bearer] = apiBearer($this->organization);
});

it('writes one usage log row per api request', function (): void {
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1')->assertOk();

    $rows = ApiRequestLog::withoutOrganizationScope()->orderBy('path')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->organization_id)->toBe($this->organization->id)
        ->and($rows[0]->api_token_id)->toBe($this->token->id)
        ->and($rows[0]->method)->toBe('GET')
        ->and($rows[0]->path)->toBe('api/v1')
        ->and($rows[0]->resource)->toBeNull()
        ->and($rows[0]->status)->toBe(200)
        ->and($rows[0]->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($rows[1]->path)->toBe('api/v1/users')
        ->and($rows[1]->resource)->toBe('users');
});

it('links a usage row back to the token that made the request', function (): void {
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();

    $log = ApiRequestLog::withoutOrganizationScope()->sole();

    expect($log->token?->id)->toBe($this->token->id);
});

it('writes no row for a request that never authenticated', function (): void {
    $this->withHeader('Authorization', 'Bearer garbage')->get('/api/v1/users')->assertUnauthorized();

    expect(ApiRequestLog::withoutOrganizationScope()->count())->toBe(0);
});

it('usage log stores no payload data', function (): void {
    $sentinel = 'sentinel-cec1e4e2-never-logged';

    $this->withHeader('Authorization', $this->bearer)
        ->withHeader('X-Sentinel', $sentinel.'-header')
        ->json('GET', '/api/v1/users?q='.$sentinel.'-query', ['note' => $sentinel.'-body'])
        ->assertOk();

    $rows = DB::table('api_request_logs')->get();

    expect($rows)->toHaveCount(1)
        ->and(json_encode($rows))->not->toContain($sentinel);
});

it('is append-only: update and delete refuse', function (): void {
    $log = ApiRequestLog::factory()->create(['organization_id' => $this->organization->id]);

    expect(fn (): bool => $log->update(['status' => 500]))
        ->toThrow(LogicException::class, 'append-only')
        ->and(fn (): bool => $log->delete())
        ->toThrow(LogicException::class, 'append-only');
});

it('api:prune-logs respects retention', function (): void {
    config()->set('api.retention.logs', 30);

    $old = ApiRequestLog::factory()->create([
        'organization_id' => $this->organization->id,
        'api_token_id' => $this->token->id,
        'created_at' => now()->subDays(31),
    ]);
    $recent = ApiRequestLog::factory()->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDays(29),
    ]);

    $this->artisan('api:prune-logs')
        ->expectsOutputToContain('Pruned 1 API request log row(s).')
        ->assertSuccessful();

    $ids = ApiRequestLog::withoutOrganizationScope()->pluck('id');

    expect($ids->all())->toBe([$recent->id])
        ->and($ids->contains($old->id))->toBeFalse();
});

it('keeps both prune commands on the daily schedule', function (): void {
    $commands = collect(resolve(Schedule::class)->events())
        ->map(fn (object $event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'api:prune-logs')))->toBeTrue()
        ->and($commands->contains(fn (string $command): bool => str_contains($command, 'tokens:prune')))->toBeTrue();
});
