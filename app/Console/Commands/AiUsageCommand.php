<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SummarizeAiUsage;
use App\Data\AiUsageRowData;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * What the AI layer has run, and what it cost, since a given moment.
 *
 * Reads the audit log rather than a provider dashboard, so the numbers here are
 * the numbers the quota middleware enforces against.
 */
#[Description('Report AI runs, tokens and spend from the audit log')]
#[Signature('ai:usage {--org= : Limit to one organization, by id or slug} {--since=30 days ago : Anything strtotime understands} {--json : Emit the report as JSON}')]
final class AiUsageCommand extends Command
{
    public function handle(SummarizeAiUsage $summarize): int
    {
        $organization = $this->organization();

        if ($organization === false) {
            $this->components->error(sprintf('No organization matches [%s].', $this->option('org')));

            return self::FAILURE;
        }

        $since = $this->since();

        if (! $since instanceof CarbonInterface) {
            $this->components->error(sprintf('Could not read [%s] as a date.', $this->option('since')));

            return self::FAILURE;
        }

        $usage = $summarize->handle($organization, $since);

        $this->option('json')
            ? $this->output->writeln($usage->toJson(JSON_PRETTY_PRINT))
            : $this->renderLines($usage->since, $usage->runs, $usage->tokens, $usage->cost_micros, $usage->blocked, $usage->agents, $usage->tiers);

        return self::SUCCESS;
    }

    /**
     * The organization named by `--org`, null for every organization, or false
     * when a name was given and matched nothing — which is an error rather than
     * a silent report about the whole installation.
     */
    private function organization(): Organization|false|null
    {
        $option = $this->option('org');

        if (! is_string($option) || $option === '') {
            return null;
        }

        // Deliberately unscoped: the console has no organization bound, and
        // naming one is the whole point of the option.
        return Organization::query()
            ->where('slug', $option)
            // Only when it could be one: Postgres refuses to compare a uuid
            // column against a word.
            ->when(Str::isUuid($option), fn (Builder $query): Builder => $query->orWhere('id', $option))
            ->first() ?? false;
    }

    private function since(): ?CarbonInterface
    {
        $option = $this->option('since');

        try {
            return now()->parse(is_string($option) && $option !== '' ? $option : '30 days ago');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<AiUsageRowData>  $agents
     * @param  list<AiUsageRowData>  $tiers
     */
    private function renderLines(string $since, int $runs, int $tokens, int $costMicros, int $blocked, array $agents, array $tiers): void
    {
        $this->newLine();

        $this->components->twoColumnDetail('Since', $since);
        $this->components->twoColumnDetail('Runs', (string) $runs);
        $this->components->twoColumnDetail('Blocked', (string) $blocked);
        $this->components->twoColumnDetail('Tokens', number_format($tokens));
        $this->components->twoColumnDetail('Spend', $this->money($costMicros));

        foreach (['Agent' => $agents, 'Tier' => $tiers] as $heading => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->newLine();
            $this->components->twoColumnDetail(sprintf('<fg=gray>%s</>', $heading), '<fg=gray>runs / tokens / spend</>');

            foreach ($rows as $row) {
                $this->components->twoColumnDetail($row->name, sprintf(
                    '%d / %s / %s',
                    $row->runs,
                    number_format($row->tokens),
                    $this->money($row->cost_micros),
                ));
            }
        }

        $this->newLine();
    }

    private function money(int $micros): string
    {
        return sprintf('$%s', number_format($micros / 1_000_000, 2));
    }
}
