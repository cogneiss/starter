<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Support\UserFriendlyExceptionRegistrar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Every failure a person can meet is answered with a sentence written for them.
 * The pairs below are the whole table: a status, and the words it must say.
 */
dataset('failures', [
    'signed out' => [401, 'You are signed out.'],
    'forbidden' => [403, 'You do not have permission to do that.'],
    'missing' => [404, 'We could not find that.'],
    'expired session' => [419, 'Your session expired while that page was open.'],
    'too many requests' => [429, 'That was a lot of requests at once.'],
    'server error' => [500, 'Something went wrong at our end.'],
    'unavailable' => [503, 'The application is briefly unavailable.'],
]);

it('answers a failed request with a sentence and no stack trace', function (int $status, string $sentence): void {
    $request = Request::create('/somewhere');

    $response = UserFriendlyExceptionRegistrar::respond(
        new Response('', $status),
        new HttpException($status),
        $request,
    );

    $body = (string) $response->getContent();

    expect($response->getStatusCode())->toBe($status)
        ->and($body)->toContain($sentence)
        // Nothing about how the application is built is any of the reader's
        // business, and a class name is not an explanation.
        ->and($body)->not->toContain('HttpException')
        ->and($body)->not->toContain('Symfony\\Component')
        ->and($body)->not->toContain('Stack trace')
        ->and($body)->not->toContain('/vendor/');
})->with('failures');

it('sends the same sentence as JSON when the caller asked for JSON', function (): void {
    $request = Request::create('/somewhere', server: ['HTTP_ACCEPT' => 'application/json']);

    $response = UserFriendlyExceptionRegistrar::respond(
        new Response('', 403),
        new HttpException(403),
        $request,
    );

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode((string) $response->getContent(), true))
        ->toBe(['message' => 'You do not have permission to do that.']);
});

/**
 * The one status the browser can recover from on its own. A visit gets the raw
 * refusal so the client can fetch a new token and offer the request again;
 * replacing the screen with an error page would throw away what was typed.
 */
it('leaves an expired token alone for requests the browser can retry', function (): void {
    $inertia = Request::create('/somewhere', server: ['HTTP_X_INERTIA' => 'true']);
    $original = new Response('', 419);

    expect(UserFriendlyExceptionRegistrar::respond($original, new HttpException(419), $inertia))
        ->toBe($original);
});

it('shows the missing page sentence on a real request', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create())
        ->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('We could not find that.', escape: false);
});
