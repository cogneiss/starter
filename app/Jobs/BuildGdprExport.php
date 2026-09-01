<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\GdprExportReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds the personal-data archive for one person: their profile, every row
 * keyed to them in the tables config/gdpr.php declares, their audit trail,
 * their notifications and the raw bytes of their pending uploads. The archive
 * lands on the local disk under the person's own id and is announced through
 * a signed, expiring link — the path itself is never logged and never shown.
 */
final class BuildGdprExport implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $userId) {}

    public function handle(): void
    {
        $user = User::query()->findOrFail($this->userId);

        $file = Str::random(40).'.zip';
        Storage::disk('local')->makeDirectory("gdpr/{$user->id}");

        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path("gdpr/{$user->id}/{$file}"), ZipArchive::CREATE);

        $zip->addFromString('profile.json', $this->json($user->toArray()));

        foreach (array_filter(config()->array('gdpr.tables'), is_string(...)) as $table) {
            $rows = DB::table($table)->where('user_id', $user->id)->get();
            $zip->addFromString("tables/{$table}.json", $this->json($rows->all()));
        }

        $audit = DB::table('activity_log')
            ->where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->id)
            ->get();
        $zip->addFromString('audit.json', $this->json($audit->all()));

        $notifications = DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->get();
        $zip->addFromString('notifications.json', $this->json($notifications->all()));

        DB::table('temp_uploads')->where('user_id', $user->id)->get()
            ->each(function (object $upload) use ($zip): void {
                if (is_string($upload->disk) && is_string($upload->path) && is_string($upload->id) && is_string($upload->original_name)) {
                    $content = Storage::disk($upload->disk)->get($upload->path);

                    if ($content !== null) {
                        $zip->addFromString("uploads/{$upload->id}-{$upload->original_name}", $content);
                    }
                }
            });

        $zip->close();

        $this->audit($user);

        $user->notify(new GdprExportReady($file));
    }

    private function audit(User $user): void
    {
        $organizationId = $user->current_organization_id
            ?? $user->memberships()->value('organization_id');

        if ($organizationId === null) {
            return;
        }

        Activity::query()->create([
            'organization_id' => $organizationId,
            'log_name' => 'audit',
            'description' => 'A personal-data export was generated.',
            'event' => 'exported',
            'causer_type' => $user->getMorphClass(),
            'causer_id' => $user->id,
            'properties' => [],
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
