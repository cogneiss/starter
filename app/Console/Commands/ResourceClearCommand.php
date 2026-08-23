<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Resources\ResourceRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Description('Remove the cached resource adapter file')]
#[Signature('resource:clear')]
final class ResourceClearCommand extends Command
{
    public function handle(ResourceRegistry $resources, Filesystem $files): int
    {
        $files->delete($resources->cachePath());

        $this->components->info('Resource cache cleared.');

        return self::SUCCESS;
    }
}
