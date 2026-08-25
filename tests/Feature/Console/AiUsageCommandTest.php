<?php

declare(strict_types=1);

use App\Models\AiAuditLog;
use App\Models\Organization;
use Illuminate\Support\Facades\Artisan;

/**
 * The report, decoded.
 *
 * @param  array<string, mixed>  $options
 * @return array{since: string, runs: int, tokens: int, cost_micros: int, blocked: int, agents: list<array{name: string, runs: int, tokens: int, cost_micros: int}>, tiers: list<array{name: string, runs: int, tokens: int, cost_micros: int}>}
 */
function usageReport(array $options = []): array
{
    Artisan::call('ai:usage', [...$options, '--json' => true]);

    return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
}

it('reports nothing on an installation where no agent has run', function (): void {
    $report = usageReport();

    expect($report['runs'])->toBe(0)
        ->and($report['tokens'])->toBe(0)
        ->and($report['cost_micros'])->toBe(0)
        ->and($report['blocked'])->toBe(0)
        ->and($report['agents'])->toBe([])
        ->and($report['tiers'])->toBe([]);
});

it('adds up runs, tokens and spend from the audit log', function (): void {
    AiAuditLog::factory()->count(3)->create();
    AiAuditLog::factory()->blocked()->create();

    $report = usageReport();

    // Blocked runs cost nothing, so they are counted separately rather than
    // being folded into the spend.
    expect($report['runs'])->toBe(4)
        ->and($report['blocked'])->toBe(1)
        ->and($report['tokens'])->toBe(360)
        ->and($report['cost_micros'])->toBe(600);
});

it('breaks the totals down by agent and by tier', function (): void {
    AiAuditLog::factory()->count(2)->create(['agent' => 'App\Ai\Agents\Drafter', 'tier' => 'cheap']);
    AiAuditLog::factory()->create(['agent' => 'App\Ai\Agents\Briefer', 'tier' => 'smart']);

    $report = usageReport();

    expect($report['agents'])->toBe([
        ['name' => 'Drafter', 'runs' => 2, 'tokens' => 240, 'cost_micros' => 400],
        ['name' => 'Briefer', 'runs' => 1, 'tokens' => 120, 'cost_micros' => 200],
    ])->and($report['tiers'])->toBe([
        ['name' => 'cheap', 'runs' => 2, 'tokens' => 240, 'cost_micros' => 400],
        ['name' => 'smart', 'runs' => 1, 'tokens' => 120, 'cost_micros' => 200],
    ]);
});

it('counts every org by default and only the named org with --org', function (): void {
    $organization = Organization::factory()->create(['name' => 'Reported', 'slug' => 'reported']);

    AiAuditLog::factory()->create(['organization_id' => $organization->id]);
    AiAuditLog::factory()->count(2)->create();

    expect(usageReport()['runs'])->toBe(3)
        ->and(usageReport(['--org' => 'reported'])['runs'])->toBe(1)
        ->and(usageReport(['--org' => $organization->id])['runs'])->toBe(1);
});

it('fails rather than reporting the whole installation when the org is unknown', function (): void {
    expect(Artisan::call('ai:usage', ['--org' => 'nobody', '--json' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('No organization matches [nobody]');
});

it('counts only what happened since the window given by --since', function (): void {
    AiAuditLog::factory()->create(['created_at' => now()->subDays(2)]);
    AiAuditLog::factory()->create(['created_at' => now()->subDays(40)]);

    // The default window is thirty days, so the older run is outside it.
    expect(usageReport()['runs'])->toBe(1)
        ->and(usageReport(['--since' => '90 days ago'])['runs'])->toBe(2)
        ->and(usageReport(['--since' => '1 day ago'])['runs'])->toBe(0);
});

it('fails rather than guessing when --since is not a date', function (): void {
    expect(Artisan::call('ai:usage', ['--since' => 'whenever']))->toBe(1)
        ->and(Artisan::output())->toContain('Could not read [whenever] as a date');
});

it('prints a table when nobody asked for JSON', function (): void {
    AiAuditLog::factory()->create(['agent' => 'App\Ai\Agents\Drafter']);

    $this->artisan('ai:usage')
        ->assertExitCode(0)
        ->expectsOutputToContain('Runs')
        ->expectsOutputToContain('Drafter')
        ->expectsOutputToContain('$0.00');
});

it('prints the totals and no breakdown when nothing has run', function (): void {
    $this->artisan('ai:usage')
        ->assertExitCode(0)
        ->expectsOutputToContain('Runs')
        ->doesntExpectOutputToContain('runs / tokens / spend');
});
