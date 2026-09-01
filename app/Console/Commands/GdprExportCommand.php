<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BuildGdprExport;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Operator-side entry to the same export pipeline the self-service button
 * uses, for the data-subject request that arrives by email instead.
 */
#[Description('Queue a personal-data export for a user, by id or email')]
#[Signature('gdpr:export {user : The user id or email address}')]
final class GdprExportCommand extends Command
{
    public function handle(): int
    {
        $needle = $this->argument('user');

        $user = User::query()
            ->where(Str::isUuid($needle) ? 'id' : 'email', $needle)
            ->first();

        if ($user === null) {
            $this->components->error('No user found.');

            return self::FAILURE;
        }

        BuildGdprExport::dispatch($user->id);

        $this->components->info('Export queued. The person will be notified with a signed download link.');

        return self::SUCCESS;
    }
}
