<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImpersonationLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Close impersonation logs whose session expired before the admin stopped')]
#[Signature('impersonation:end-stale')]
final class EndStaleImpersonationsCommand extends Command
{
    /**
     * A log not stamped by a request for a whole session lifetime belongs to an
     * expired session. It ends when it was last used, and the save goes through
     * Eloquent so the audit bridge records the end.
     */
    public function handle(): int
    {
        $stale = ImpersonationLog::query()
            ->whereNull('ended_at')
            ->where('updated_at', '<', now()->subMinutes(config()->integer('session.lifetime')))
            ->get();

        $stale->each(fn (ImpersonationLog $log): bool => $log->forceFill(['ended_at' => $log->updated_at])->save());

        $this->components->info(sprintf('Closed %d stale impersonation logs.', $stale->count()));

        return self::SUCCESS;
    }
}
