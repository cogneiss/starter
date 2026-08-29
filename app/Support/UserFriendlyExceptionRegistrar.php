<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * What a person is told when a request fails.
 *
 * Two things live here. The status codes every application produces get one
 * sentence each, written once so that a 403 reads the same wherever it comes
 * from. Anything a feature knows better than the status code does — a quota, a
 * blocked call — registers its own sentence against its exception class, which
 * is why this is a registrar and not a fixed table.
 */
final class UserFriendlyExceptionRegistrar
{
    /**
     * Sentences for the failures a person can act on. A status not named here
     * keeps whatever answer the framework already gave it.
     *
     * @var array<int, string>
     */
    private const STATUSES = [
        401 => 'You are signed out. Sign in again to pick up where you left off.',
        403 => 'You do not have permission to do that.',
        404 => 'We could not find that. It may have been deleted, or belong to another organization.',
        419 => 'Your session expired while that page was open. Sign in again and the form will still be there.',
        429 => 'That was a lot of requests at once. Wait a moment and try again.',
        500 => 'Something went wrong at our end. The error has been recorded.',
        503 => 'The application is briefly unavailable. Try again in a minute.',
    ];

    /**
     * @var array<class-string<Throwable>, array{status: int, message: string}>
     */
    private static array $registered = [];

    /**
     * Give one exception class its own status and sentence.
     *
     * @param  class-string<Throwable>  $exception
     */
    public static function register(string $exception, int $status, string $message): void
    {
        self::$registered[$exception] = ['status' => $status, 'message' => $message];
    }

    /**
     * The status and sentence to show for a failure, or null to leave it alone.
     *
     * @return array{status: int, message: string}|null
     */
    public static function describe(Throwable $throwable, int $status): ?array
    {
        foreach (self::$registered as $exception => $described) {
            if ($throwable instanceof $exception) {
                return $described;
            }
        }

        if (! array_key_exists($status, self::STATUSES)) {
            return null;
        }

        return ['status' => $status, 'message' => self::STATUSES[$status]];
    }

    /**
     * Turn a failed response into one a person can read.
     *
     * An expired token is the one failure that gets a page only when the browser
     * cannot fix it itself. A visit or an XHR call is left as it is, because the
     * client asks for a new token and offers the request again; a plain form
     * post has nobody to do that for it, so it gets the sentence.
     */
    public static function respond(Response $response, Throwable $throwable, Request $request): Response
    {
        $described = self::describe($throwable, $response->getStatusCode());

        if ($described === null) {
            return $response;
        }

        if ($described['status'] === 419 && ($request->expectsJson() || $request->hasHeader('X-Inertia'))) {
            return $response;
        }

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $described['message']], $described['status']);
        }

        return Inertia::render('error', $described)
            ->toResponse($request)
            ->setStatusCode($described['status']);
    }
}
