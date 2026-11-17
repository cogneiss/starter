<?php

declare(strict_types=1);

use App\Http\Middleware\ReadOnlyWhileImpersonating;
use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * @return array{0: User, 1: array<string, string>}
 */
function readOnlyTarget(): array
{
    $organization = Organization::factory()->create();
    $target = User::factory()->forOrganization($organization)->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);

    return [$target, [Impersonation::USER_KEY => $admin->id, Impersonation::LOG_KEY => $log->id]];
}

beforeEach(function (): void {
    Route::middleware(['web', 'auth'])->group(function (): void {
        Route::match(['post', 'put', 'patch', 'delete'], '__read-only/write', fn (): string => 'written');
        Route::get('__read-only/read', fn (): string => 'read');
        Route::post('__read-only/guarded', fn (): string => 'guarded')
            ->middleware(HandlePrecognitiveRequests::class);
    });

    Route::middleware(['web', 'auth', HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('__read-only/stripped', fn (): string => 'stripped')
            ->withoutMiddleware(HandlePrecognitiveRequests::class);
    });
});

it('refuses every write while impersonating', function (string $method): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->call($method, '/__read-only/write')
        ->assertForbidden();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('lets reads through while impersonating', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->get('/__read-only/read')
        ->assertOk();
});

it('refuses a CSV export while impersonating', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->get('/__read-only/read', ['Accept' => 'text/csv'])
        ->assertForbidden();
});

it('is inert when nobody is impersonating', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/__read-only/write')
        ->assertOk();
});

it('lets the admin stop impersonating', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->delete(route('user-impersonation.destroy'))
        ->assertRedirectToRoute('dashboard');
});

it('lets the admin log out', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

it('lets the broadcasting handshake through', function (): void {
    [, $session] = readOnlyTarget();
    session()->put($session);

    $response = resolve(ReadOnlyWhileImpersonating::class)->handle(
        Request::create('/broadcasting/auth', 'POST'),
        fn (): Response => new Response('ok'),
    );

    expect($response->getContent())->toBe('ok');
});

it('lets a precognitive request through on a route that stops it before the controller', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->withHeader('Precognition', 'true')
        ->post('/__read-only/guarded')
        ->assertSuccessful();
});

it('refuses a precognitive header on a route without the precognition middleware', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->withHeader('Precognition', 'true')
        ->post('/__read-only/write')
        ->assertForbidden();
});

it('refuses a precognitive header on a route that strips the inherited precognition middleware', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->withHeader('Precognition', 'true')
        ->post('/__read-only/stripped')
        ->assertForbidden();
});

it('refuses a real write on a guarded route without the precognition header', function (): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->post('/__read-only/guarded')
        ->assertForbidden();
});

it('refuses the reads that expose credentials, second factors and exports', function (string $path): void {
    [$target, $session] = readOnlyTarget();

    $this->actingAs($target)
        ->withSession($session)
        ->get($path)
        ->assertForbidden();
})->with([
    'two-factor page' => '/settings/two-factor',
    'two-factor secret' => '/user/two-factor-secret-key',
    'recovery codes' => '/user/two-factor-recovery-codes',
    'passkey page' => '/settings/passkeys',
    'passkey options' => '/user/passkeys/options',
    'password confirmation' => '/user/confirm-password',
    'browser sessions' => '/settings/sessions',
    'gdpr export download' => '/settings/profile/gdpr-export/'.str_repeat('a', 40).'.zip',
]);

it('refuses an import download while impersonating', function (): void {
    [$target, $session] = readOnlyTarget();
    $upload = TempUpload::factory()->create([
        'organization_id' => $target->current_organization_id,
        'user_id' => $target->id,
    ]);

    $this->actingAs($target)
        ->withSession($session)
        ->get(route('import.download', ['upload' => $upload->id]))
        ->assertForbidden();
});
