<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FeatureOverride;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete feature overrides whose expiry has passed')]
#[Signature('app:expire-feature-overrides')]
final class ExpireFeatureOverridesCommand extends Command
{
    public function handle(): int
    {
        $expired = FeatureOverride::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        $deleted = $expired->count();

        $expired->delete();

        $this->components->info(sprintf('Deleted %d expired feature override(s).', $deleted));

        return self::SUCCESS;
    }
}
