<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * The routes that validate through a form request and are still allowed to ship
 * without precognition.
 *
 * An escape hatch that anyone may widen is an off switch, so this one is small
 * and argued for: every entry carries a written reason, and the list itself is
 * capped. A sixth route cannot be added quietly — the application refuses to
 * build the list at all, and the parity test that walks the router goes red.
 */
final readonly class PrecognitionAllowlist
{
    /**
     * How many routes may sit outside the gate at once.
     */
    public const int MAXIMUM = 5;

    /**
     * @param  array<string, string>  $reasons  route name => why it is excused
     */
    public function __construct(private array $reasons)
    {
        if (count($this->reasons) > self::MAXIMUM) {
            throw new InvalidArgumentException(
                'The precognition allowlist holds at most '.self::MAXIMUM.' routes, '.count($this->reasons).' given.',
            );
        }

        foreach ($this->reasons as $route => $reason) {
            throw_if(mb_trim($reason) === '', InvalidArgumentException::class, "The precognition allowlist entry for [{$route}] needs a written reason.");
        }
    }

    /**
     * The list this application ships with.
     */
    public static function shipped(): self
    {
        // Every route this application declares carries the middleware, because
        // it is on the route groups in routes/web.php rather than on a list of
        // individual routes. What is left is the five a package registers, none
        // of which may take a per-route middleware from here, and all of which
        // answer questions about credentials that a live check would leak.
        return new self([
            'two-factor.login' => 'Fortify registers it; a live check would confirm a challenge code one digit at a time.',
            'two-factor.login.store' => 'Fortify registers it; same code-guessing oracle as the challenge form.',
            'passkey.login' => 'Passkeys registers it; the assertion is one signed exchange, not a field a person edits.',
            'passkey.confirm' => 'Passkeys registers it; confirming an assertion has no partial state to validate.',
            'passkey.store' => 'Passkeys registers it; the credential is produced by the browser, not typed.',
        ]);
    }

    /**
     * Whether the named route is excused from carrying the middleware.
     */
    public function excuses(string $route): bool
    {
        return array_key_exists($route, $this->reasons);
    }
}
