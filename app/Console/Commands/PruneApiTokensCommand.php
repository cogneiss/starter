<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Retires tokens that stopped working long ago.
 *
 * A revoked or expired token is kept for a while so the settings page and the
 * usage log can still name it, then deleted once the retention window passes.
 * This runs across every organization by design — retention is a housekeeping
 * fact, not a tenant's decision — which is why it is scheduled rather than
 * reachable from a request.
 */
#[Description('Delete API tokens revoked or expired beyond the retention window')]
#[Signature('tokens:prune')]
final class PruneApiTokensCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(config()->integer('api.retention.tokens'));

        $deleted = ApiToken::withoutOrganizationScope()
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('revoked_at', '<=', $cutoff)
                    ->orWhere('expires_at', '<=', $cutoff);
            })
            ->toBase()
            ->delete();

        $this->components->info(sprintf('Pruned %d retired API token(s).', $deleted));

        return self::SUCCESS;
    }
}
