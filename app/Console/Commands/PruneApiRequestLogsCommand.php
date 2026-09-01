<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes usage log rows older than the retention window. The model refuses
 * delete() so nothing in a request path can erase usage history; this scheduled
 * command, deleting at the query layer, is the one sanctioned eraser.
 */
#[Description('Delete API request log rows past the retention window')]
#[Signature('api:prune-logs')]
final class PruneApiRequestLogsCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(config()->integer('api.retention.logs'));

        $deleted = ApiRequestLog::withoutOrganizationScope()
            ->where('created_at', '<=', $cutoff)
            ->toBase()
            ->delete();

        $this->components->info(sprintf('Pruned %d API request log row(s).', $deleted));

        return self::SUCCESS;
    }
}
