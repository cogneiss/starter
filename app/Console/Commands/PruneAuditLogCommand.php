<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Activity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes audit entries past the retention window, at the query layer, across
 * every organization — the one sanctioned eraser of audit history.
 */
#[Description('Delete audit log entries past the retention window')]
#[Signature('audit:prune')]
final class PruneAuditLogCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(config()->integer('audit.retention'));

        $deleted = Activity::withoutOrganizationScope()
            ->where('created_at', '<=', $cutoff)
            ->toBase()
            ->delete();

        $this->components->info(sprintf('Pruned %d audit log row(s).', $deleted));

        return self::SUCCESS;
    }
}
