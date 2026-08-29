<?php

declare(strict_types=1);

use App\Support\PrecognitionAllowlist;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;

/**
 * Precognition is only worth having if it is everywhere the rules are.
 *
 * A form that validates live against a route without the middleware gets no
 * validation at all — the request runs the controller instead — so the gate is
 * not "the four forms we remembered", it is the router itself: every action
 * that type-hints a form request carries the middleware, or is named in the
 * allowlist with a reason.
 */

/**
 * The form request an action type-hints, if it takes one.
 */
function formRequestOf(RoutingRoute $route): ?string
{
    try {
        $reflection = $route->getControllerClass() === null
            ? null
            : new ReflectionMethod($route->getController(), $route->getActionMethod());
    } catch (ReflectionException) {
        return null;
    }

    if (! $reflection instanceof ReflectionMethod) {
        return null;
    }

    foreach ($reflection->getParameters() as $parameter) {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            continue;
        }

        if (is_subclass_of($type->getName(), FormRequest::class)) {
            return $type->getName();
        }
    }

    return null;
}

/**
 * @return array<string, string> route name => the form request it validates with
 */
function routesMissingPrecognition(PrecognitionAllowlist $allowlist): array
{
    // The HTTP kernel is what copies the configured groups into the router, so
    // resolving it first is the difference between reading the real stack a
    // request runs and reading an unexpanded group name.
    resolve(Kernel::class);

    $router = resolve(Router::class);
    $missing = [];

    foreach ($router->getRoutes() as $route) {
        $request = formRequestOf($route);
        $name = (string) $route->getName();

        if ($request === null || $allowlist->excuses($name)) {
            continue;
        }

        if (! in_array(HandlePrecognitiveRequests::class, $router->gatherRouteMiddleware($route), true)) {
            $missing[$name === '' ? $route->uri() : $name] = $request;
        }
    }

    return $missing;
}

it('PrecognitionParity carries the middleware on every route that validates', function (): void {
    expect(routesMissingPrecognition(PrecognitionAllowlist::shipped()))->toBe([]);
});

it('PrecognitionParity finds the routes it is walking', function (): void {
    $router = resolve(Router::class);

    $validating = collect($router->getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => formRequestOf($route) !== null);

    expect($validating)->not->toBeEmpty();
});
