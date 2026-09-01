<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\DeleteUser;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hard-deletes anonymised accounts once the retention window has passed. The
 * rows were already stripped of personal data at deletion time; this removes
 * the skeleton and everything cascading from it.
 */
#[Description('Hard-delete anonymised accounts past the retention window')]
#[Signature('gdpr:purge {--dry-run : Count the accounts due without deleting anything}')]
final class GdprPurgeCommand extends Command
{
    public function handle(DeleteUser $delete): int
    {
        $due = User::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(config()->integer('gdpr.purge_after_days')))
            ->get();

        if ($this->option('dry-run')) {
            $this->components->info(sprintf('%d account(s) due for purge.', $due->count()));

            return self::SUCCESS;
        }

        $due->each(function (User $user) use ($delete): void {
            $user->memberships()->pluck('organization_id')->unique()
                ->each(fn (mixed $organizationId) => Activity::query()->create([
                    'organization_id' => $organizationId,
                    'log_name' => 'audit',
                    'description' => 'An anonymised account was purged after the retention window.',
                    'event' => 'purged',
                    'properties' => [],
                ]));

            $delete->handle($user);
        });

        $this->components->info(sprintf('Purged %d account(s).', $due->count()));

        return self::SUCCESS;
    }
}
