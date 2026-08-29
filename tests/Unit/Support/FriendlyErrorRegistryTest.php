<?php

declare(strict_types=1);

use App\Exceptions\AiQuotaExceededException;
use App\Exceptions\BlockedEgressException;
use App\Support\UserFriendlyExceptionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The mapping is a registry keyed by exception class, not a switch inside the
 * handler. A feature that knows more than the status code says so by registering
 * its own sentence, which is how the AI layer's two exceptions get theirs
 * without this class or `bootstrap/app.php` learning anything about the AI
 * layer.
 */
it('gives a registered exception its own status and sentence', function (): void {
    expect(UserFriendlyExceptionRegistrar::describe(new AiQuotaExceededException, 500))
        ->toBe([
            'status' => 429,
            'message' => 'This organization has used its AI allowance for now. It resets shortly.',
        ]);

    expect(UserFriendlyExceptionRegistrar::describe(new BlockedEgressException('nope'), 500))
        ->toBe([
            'status' => 502,
            'message' => 'The assistant tried to reach an address it is not allowed to. Nothing was sent.',
        ]);
});

it('registers a new exception without touching the handler', function (): void {
    $exception = new class('nope') extends RuntimeException {};

    UserFriendlyExceptionRegistrar::register($exception::class, 418, 'This one is a teapot.');

    expect(UserFriendlyExceptionRegistrar::describe($exception, 500))
        ->toBe(['status' => 418, 'message' => 'This one is a teapot.']);
});

it('falls back to the sentence for the status', function (): void {
    expect(UserFriendlyExceptionRegistrar::describe(new HttpException(404), 404))
        ->toBe([
            'status' => 404,
            'message' => 'We could not find that. It may have been deleted, or belong to another organization.',
        ]);
});

it('leaves a status nobody wrote a sentence for alone', function (): void {
    expect(UserFriendlyExceptionRegistrar::describe(new HttpException(418), 418))->toBeNull();
});
