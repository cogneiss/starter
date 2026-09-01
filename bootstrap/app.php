<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForbiddenDuringImpersonation;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleBrand;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HonorDoNotTrack;
use App\Http\Middleware\RedirectIfNotOnboarded;
use App\Http\Middleware\RequireOrganization;
use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\SetLocale;
use App\Support\UserFriendlyExceptionRegistrar;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        // The authorization endpoint runs the same stack a page does, so the
        // organization is resolved and the permission gates read from it.
        ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            AuthenticateSession::class,
            EnsureUserIsActive::class,
            HandleAppearance::class,
            SetLocale::class,
            HonorDoNotTrack::class,
            ResolveOrganization::class,
            HandleBrand::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Route model binding queries organization-scoped models, so the
        // organization has to be resolved before the bindings are substituted.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveOrganization::class,
        );

        $middleware->alias([
            'organization' => RequireOrganization::class,
            'not-impersonating' => ForbiddenDuringImpersonation::class,
            'two-factor' => EnsureTwoFactorEnabled::class,
            'onboarded' => RedirectIfNotOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API is JSON no matter what Accept header the client sent: a
        // failure must never come back as a redirect or an HTML page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $throwable): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(
            fn (Response $response, Throwable $throwable, Request $request): Response => UserFriendlyExceptionRegistrar::respond($response, $throwable, $request),
        );
    })->create();
