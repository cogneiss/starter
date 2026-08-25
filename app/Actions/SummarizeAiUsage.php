<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\AiUsageData;
use App\Data\AiUsageRowData;
use App\Enums\AiAuditStatus;
use App\Models\AiAuditLog;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

/**
 * What the AI layer has run and what it cost, read from the append-only audit
 * log. One action answers both `php artisan ai:usage` and the organization's
 * usage page, so the terminal and the screen cannot quote different numbers.
 */
final readonly class SummarizeAiUsage
{
    public function handle(?Organization $organization, CarbonInterface $since): AiUsageData
    {
        $totals = $this->query($organization, $since)
            ->selectRaw('count(*) as runs')
            ->selectRaw('coalesce(sum(total_tokens), 0) as tokens')
            ->selectRaw('coalesce(sum(cost_micros), 0) as cost_micros')
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as blocked', [AiAuditStatus::Blocked->value])
            ->first();

        return new AiUsageData(
            since: $since->toIso8601String(),
            runs: $this->toInt($totals?->runs),
            tokens: $this->toInt($totals?->tokens),
            cost_micros: $this->toInt($totals?->cost_micros),
            blocked: $this->toInt($totals?->blocked),
            agents: $this->breakdown($organization, $since, "coalesce(agent, 'unknown') as name", 'agent'),
            tiers: $this->breakdown($organization, $since, "coalesce(tier, 'unknown') as name", 'tier'),
        );
    }

    /**
     * One row per distinct value of the column, dearest first.
     *
     * @param  literal-string  $name  The expression the column is reported under.
     * @return list<AiUsageRowData>
     */
    private function breakdown(?Organization $organization, CarbonInterface $since, string $name, string $column): array
    {
        $rows = $this->query($organization, $since)
            ->selectRaw($name)
            ->selectRaw('count(*) as runs')
            ->selectRaw('coalesce(sum(total_tokens), 0) as tokens')
            ->selectRaw('coalesce(sum(cost_micros), 0) as cost_micros')
            ->groupBy($column)
            ->orderByDesc('cost_micros')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row): AiUsageRowData => new AiUsageRowData(
                // Agents are stored as class names. Nobody reading a report
                // wants the namespace.
                name: class_basename(is_string($row->name) ? $row->name : 'unknown'),
                runs: $this->toInt($row->runs),
                tokens: $this->toInt($row->tokens),
                cost_micros: $this->toInt($row->cost_micros),
            ))
            ->all();

        return array_values($rows);
    }

    /**
     * Reporting is the one read that deliberately spans organizations: the
     * command runs in the console with nothing bound, and narrows by hand when
     * an organization is named. Callers that must not see another organization
     * pass one — the page always does.
     */
    private function query(?Organization $organization, CarbonInterface $since): Builder
    {
        return AiAuditLog::withoutOrganizationScope()
            ->toBase()
            ->when($organization, fn (Builder $query): Builder => $query->where('organization_id', $organization?->id))
            ->where('created_at', '>=', $since);
    }

    /**
     * Aggregates come back as whatever the driver felt like: an int on Postgres,
     * a string on some others, null from an empty table.
     */
    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
