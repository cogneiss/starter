<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * Every way a confirmation can be refused. The messages are deliberately dull:
 * they are shown to a person, and they never repeat the payload back.
 */
final class InvalidConfirmToken extends RuntimeException
{
    public static function unknown(string $id): self
    {
        return new self("There is no confirmation waiting with the id [{$id}].");
    }

    public static function consumed(): self
    {
        return new self('That confirmation has already been used.');
    }

    public static function expired(): self
    {
        return new self('That confirmation has expired. Ask for the change again.');
    }

    public static function tampered(): self
    {
        return new self('That confirmation no longer matches what was proposed.');
    }

    public static function wrongUser(): self
    {
        return new self('Only the person who was shown a confirmation can confirm it.');
    }

    public static function wrongOrganization(): self
    {
        return new self('That confirmation belongs to a different organization.');
    }

    public static function unmappedAction(string $action): self
    {
        return new self("The action [{$action}] is not one an agent may propose.");
    }

    /**
     * A refused confirmation is an ordinary answer to a person who clicked a
     * stale button — a replay or an expiry is expected, not a server fault.
     */
    public function render(Request $request): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $this->getMessage(),
        ]);

        return back();
    }
}
