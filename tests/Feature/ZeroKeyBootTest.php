<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Every credential a fresh clone does not have. Blanked through the config layer
 * rather than by editing `.env`, because the suite must never depend on, or
 * mutate, the environment file of whoever is running it.
 *
 * @return list<string>
 */
function optionalCredentials(): array
{
    return [
        'services.postmark.key',
        'services.resend.key',
        'services.ses.key',
        'services.ses.secret',
        'services.google.client_id',
        'services.google.client_secret',
        'services.github.client_id',
        'services.github.client_secret',
        'services.microsoft.client_id',
        'services.microsoft.client_secret',
        'services.slack.notifications.bot_user_oauth_token',
        'services.slack.notifications.channel',
        'mail.mailers.smtp.username',
        'mail.mailers.smtp.password',
        'filesystems.disks.s3.key',
        'filesystems.disks.s3.secret',
        'filesystems.disks.s3.bucket',
    ];
}

/**
 * The pages this kit defines that a browser can reach without a parameter, read
 * from the router so a page added tomorrow is covered the day it lands. Only
 * routes declared in `routes/web.php` — Fortify and the local tooling packages
 * register their own, and their JSON endpoints are not pages.
 *
 * @return list<string>
 */
function ownPageUris(bool $guest): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('GET', $route->methods(), true))
        ->reject(fn (RoutingRoute $route): bool => str_contains($route->uri(), '{'))
        ->filter(fn (RoutingRoute $route): bool => declaredInWebRoutes($route))
        // `MakeResourceEndToEndTest` generates a Widget resource into
        // routes/web.php and takes it out again. A parallel worker that boots
        // mid-generation sees pages this kit does not ship, whose permissions
        // were never seeded here.
        ->reject(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'widgets'))
        ->filter(fn (RoutingRoute $route): bool => in_array('guest', $route->gatherMiddleware(), true) === $guest)
        ->map(fn (RoutingRoute $route): string => '/'.mb_ltrim($route->uri(), '/'))
        ->unique()
        ->values()
        ->all();
}

function declaredInWebRoutes(RoutingRoute $route): bool
{
    $action = $route->getAction('uses');

    if ($action instanceof Closure) {
        return new ReflectionFunction($action)->getFileName() === base_path('routes/web.php');
    }

    return is_string($action) && str_starts_with($action, 'App\\Http\\Controllers\\');
}

beforeEach(function (): void {
    foreach (optionalCredentials() as $key) {
        config()->set($key, '');
    }
});

it('serves every guest page with no third-party credentials', function (): void {
    $uris = ownPageUris(guest: true);

    expect($uris)->not->toBeEmpty();

    foreach ($uris as $uri) {
        $this->get($uri)->assertOk();
    }
});

it('serves every authenticated page with no third-party credentials', function (): void {
    $user = User::factory()->forOrganization(Organization::factory()->create())->create();

    // Everything but the page that exists to be shown before verification: a
    // verified user is redirected away from it, which is the correct answer.
    $uris = array_values(array_diff(ownPageUris(guest: false), ['/verify-email']));

    expect($uris)->not->toBeEmpty();

    $statuses = [];

    foreach ($uris as $uri) {
        // Passkeys and two-factor sit behind password confirmation; without the
        // marker they redirect for a reason that has nothing to do with keys.
        $statuses[$uri] = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get($uri)
            ->status();
    }

    expect($statuses)->toBe(array_fill_keys($uris, 200));
});

it('serves the verification notice with no third-party credentials', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/verify-email')->assertOk();
});
