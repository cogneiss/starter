<?php

declare(strict_types=1);

use App\Jobs\BuildGdprExport;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use App\Notifications\GdprExportReady;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('temp-uploads');

    $this->organization = Organization::factory()->create();
    $this->member = User::factory()->forOrganization($this->organization)->create();
});

/**
 * The stored archive for a user, or null when none was written.
 */
function exportArchive(User $user): ?ZipArchive
{
    $files = Storage::disk('local')->files("gdpr/{$user->id}");

    if ($files === []) {
        return null;
    }

    $zip = new ZipArchive();
    $zip->open(Storage::disk('local')->path($files[0]));

    return $zip;
}

it('declares every table holding a user_id column, in both directions', function (): void {
    $withUserId = collect(DB::select(
        "select distinct table_name from information_schema.columns where table_schema = 'public' and column_name = 'user_id'",
    ))->pluck('table_name')->sort()->values()->all();

    // Excluded tables carry a written reason in config/gdpr.php: sessions is
    // serialized framework state, regenerated on every login.
    $declared = collect([
        ...config()->array('gdpr.tables'),
        ...array_keys(config()->array('gdpr.excluded')),
    ])->sort()->values()->all();

    expect($declared)->toBe($withUserId);
})->group('pgvector');

it('builds a ZIP holding the profile and one JSON file per declared table', function (): void {
    TempUpload::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->member->id,
    ]);

    new BuildGdprExport($this->member->id)->handle();

    $zip = exportArchive($this->member);

    expect($zip)->not->toBeNull();

    $profile = json_decode((string) $zip->getFromName('profile.json'), true);

    expect($profile['email'])->toBe($this->member->email)
        ->and($profile)->not->toHaveKeys(['password', 'remember_token', 'two_factor_secret']);

    foreach (config()->array('gdpr.tables') as $table) {
        expect($zip->getFromName("tables/{$table}.json"))->not->toBeFalse($table.' missing from archive');
    }

    $memberships = json_decode((string) $zip->getFromName('tables/organization_memberships.json'), true);

    expect($memberships)->toHaveCount(1)
        ->and($memberships[0]['user_id'])->toBe($this->member->id);
});

it('embeds the actual bytes of every upload the user owns, not a path reference', function (): void {
    $upload = TempUpload::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->member->id,
        'path' => 'temp/export-proof.csv',
    ]);

    Storage::disk('temp-uploads')->put('temp/export-proof.csv', 'name,email
proof-row,proof@example.com
');

    new BuildGdprExport($this->member->id)->handle();

    $zip = exportArchive($this->member);

    expect($zip->getFromName("uploads/{$upload->id}-{$upload->original_name}"))
        ->toContain('proof-row,proof@example.com');
});

it('audits the export and keeps the archive path out of every log', function (): void {
    $logged = [];
    Log::listen(function (object $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    $this->actingAs($this->member)
        ->post(route('gdpr-export.store'))
        ->assertRedirect();

    $entry = Activity::withoutOrganizationScope()
        ->where('event', 'exported')
        ->where('causer_id', $this->member->id)
        ->sole();

    expect($entry->organization_id)->toBe($this->organization->id);

    $file = basename(Storage::disk('local')->files("gdpr/{$this->member->id}")[0]);

    foreach ($logged as $line) {
        expect($line)->not->toContain('gdpr/')
            ->and($line)->not->toContain($file);
    }
});

it('audits against the first membership or not at all for a user without one', function (): void {
    // Fallback: no current organization set, but a membership exists.
    $drifter = User::factory()->forOrganization($this->organization)->create();
    $drifter->forceFill(['current_organization_id' => null])->save();

    new BuildGdprExport($drifter->id)->handle();

    expect(Activity::withoutOrganizationScope()
        ->where('event', 'exported')
        ->where('causer_id', $drifter->id)
        ->where('organization_id', $this->organization->id)
        ->count())->toBe(1);

    // No membership anywhere: the export still builds, no entry is written.
    $loner = User::factory()->create();

    new BuildGdprExport($loner->id)->handle();

    expect(Storage::disk('local')->files("gdpr/{$loner->id}"))->toHaveCount(1)
        ->and(Activity::withoutOrganizationScope()->where('causer_id', $loner->id)->count())->toBe(0);
});

it('delivers a signed link that serves the archive and refuses every forgery', function (): void {
    $this->actingAs($this->member)->post(route('gdpr-export.store'));

    $notification = DB::table('notifications')
        ->where('notifiable_id', $this->member->id)
        ->where('type', GdprExportReady::class)
        ->sole();

    $url = json_decode((string) $notification->data, true)['url'];

    $this->actingAs($this->member)->get($url)
        ->assertOk()
        ->assertDownload('personal-data-export.zip');

    // Tampered: swap the file name inside the signed URL.
    $file = basename(Storage::disk('local')->files("gdpr/{$this->member->id}")[0]);
    $this->actingAs($this->member)
        ->get(str_replace($file, str_repeat('a', 40).'.zip', $url))
        ->assertForbidden();

    // Unsigned: the bare route with no signature at all.
    $this->actingAs($this->member)
        ->get(route('gdpr-export.download', ['file' => $file]))
        ->assertForbidden();

    // Expired: the same link a week later.
    $this->travel(8)->days();
    $this->actingAs($this->member)->get($url)->assertForbidden();
});

it('serves a file only from the requester’s own directory', function (): void {
    new BuildGdprExport($this->member->id)->handle();

    $file = basename(Storage::disk('local')->files("gdpr/{$this->member->id}")[0]);
    $url = URL::temporarySignedRoute('gdpr-export.download', now()->addDay(), ['file' => $file]);

    // A validly signed link for an existing file, fetched by somebody else:
    // the lookup is scoped to the requester's own directory, so it is a 404.
    $other = User::factory()->forOrganization(Organization::factory()->create())->create();

    $this->actingAs($other)->get($url)->assertNotFound();

    // A file name that is not forty alphanumerics never reaches the disk.
    $this->actingAs($this->member)
        ->get(URL::temporarySignedRoute('gdpr-export.download', now()->addDay(), ['file' => '../secrets.zip']))
        ->assertNotFound();
});

it('queues an export from the console by id or email and never prints the path', function (): void {
    $this->artisan('gdpr:export', ['user' => $this->member->email])
        ->expectsOutputToContain('Export queued.')
        ->assertSuccessful();

    $this->artisan('gdpr:export', ['user' => $this->member->id])
        ->assertSuccessful();

    $this->artisan('gdpr:export', ['user' => 'nobody@example.com'])
        ->expectsOutputToContain('No user found.')
        ->assertFailed();

    expect(Storage::disk('local')->files("gdpr/{$this->member->id}"))->toHaveCount(2);
});
