<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SyncPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Create any permission the catalog defines but the database is missing')]
#[Signature('app:sync-permissions')]
final class SyncPermissionsCommand extends Command
{
    public function handle(SyncPermissions $sync): int
    {
        $result = $sync->handle();

        foreach ($result['created'] as $name) {
            $this->components->info(sprintf('Created permission [%s].', $name));
        }

        foreach ($result['orphaned'] as $name) {
            $this->components->warn(sprintf('Permission [%s] is not in the catalog. Remove it by hand once nothing uses it.', $name));
        }

        if ($result['created'] === [] && $result['orphaned'] === []) {
            $this->components->info('Permissions are already in sync.');
        }

        return self::SUCCESS;
    }
}
